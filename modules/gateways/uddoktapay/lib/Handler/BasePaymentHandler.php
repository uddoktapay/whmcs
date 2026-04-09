<?php

namespace WHMCS\Module\Gateway\UddoktaPay\Handler;

use Carbon\Carbon;
use Symfony\Component\HttpFoundation\Request;
use WHMCS\Config\Setting;
use WHMCS\Database\Capsule;
use WHMCS\Module\Gateway\UddoktaPay\Enums\ErrorCode;
use WHMCS\Module\Gateway\UddoktaPay\Enums\GatewayType;
use WHMCS\Module\Gateway\UddoktaPay\Enums\PaymentAction;
use WHMCS\Module\Gateway\UddoktaPay\Enums\PaymentStatus;
use WHMCS\Module\Gateway\UddoktaPay\Exception\UddoktaPayException;
use WHMCS\Module\Gateway\UddoktaPay\Http\UddoktaPayAPI;

abstract class BasePaymentHandler
{
    /** @var string */
    protected $gatewayModuleName;

    /** @var array */
    protected $gatewayParams;

    /** @var array */
    protected $invoice;

    /** @var object */
    protected $clientDetails;

    /** @var array */
    protected $customerCurrency;

    /** @var float */
    protected $due;

    /** @var float */
    protected $fee;

    /** @var float */
    protected $total;

    /** @var UddoktaPayAPI */
    protected $api;

    /** @var bool */
    public $isActive;

    /** @var Request */
    public $request;

    /**
     * @return string
     */
    abstract protected function getGatewayType();

    protected function __construct()
    {
        $this->request = Request::createFromGlobals();
        $this->gatewayModuleName = GatewayType::moduleName($this->getGatewayType());
        $this->gatewayParams = getGatewayVariables($this->gatewayModuleName);
        $this->isActive = !empty($this->gatewayParams['type']);

        $this->api = UddoktaPayAPI::make(
            $this->gatewayParams['api_key'],
            $this->gatewayParams['api_url']
        );

        $this->invoice = $this->fetchInvoice();
        $this->customerCurrency = $this->fetchCurrency();
        $this->clientDetails = $this->fetchClient();
        $this->due = (float) $this->invoice['balance'];
        $this->fee = 0.0;
        $this->total = $this->due + $this->fee;
    }

    /**
     * @return void
     */
    public function run()
    {
        if (!$this->isActive) {
            exit('The gateway is unavailable.');
        }

        $actionValue = $this->request->get('action');
        $action = PaymentAction::tryFrom($actionValue !== null ? $actionValue : '');
        $invoiceId = (int) $this->request->get('id');

        if ($action === PaymentAction::INIT) {
            $this->handleInit($invoiceId);
        } elseif ($action === PaymentAction::VERIFY) {
            $this->handleVerify($invoiceId);
        } elseif ($action === PaymentAction::IPN) {
            $this->handleIpn();
        } else {
            $this->redirectWithError($invoiceId, ErrorCode::SOMETHING_WRONG);
        }
    }

    /**
     * @param int $invoiceId
     * @return void
     */
    protected function handleInit($invoiceId)
    {
        $response = $this->createPayment();

        if ($response['status'] === 'success') {
            header('Location: ' . $response['payment_url']);
            exit;
        }

        $this->redirectWithError($invoiceId, (string) $response['errorCode']);
    }

    /**
     * @param int $invoiceId
     * @return void
     */
    protected function handleVerify($invoiceId)
    {
        $paymentInvoiceId = $this->request->get('invoice_id');
        $response = $this->processPayment($paymentInvoiceId !== null ? $paymentInvoiceId : '');

        if ($response['status'] === 'success') {
            redirSystemURL("id={$invoiceId}", 'viewinvoice.php');
            exit;
        }

        $this->redirectWithError($invoiceId, (string) $response['errorCode']);
    }

    /**
     * @return void
     */
    protected function handleIpn()
    {
        $invoiceId = $this->request->get('invoice_id');
        $this->processPayment($invoiceId !== null ? $invoiceId : '', true);
        exit;
    }

    /**
     * @param int $invoiceId
     * @param string $error
     * @return void
     */
    protected function redirectWithError($invoiceId, $error)
    {
        redirSystemURL("id={$invoiceId}&error={$error}", 'viewinvoice.php');
        exit;
    }

    /**
     * @return array
     */
    public function createPayment()
    {
        $systemUrl = Setting::getValue('SystemURL');
        $invoiceId = $this->invoice['invoiceid'];
        $baseCallbackUrl = "{$systemUrl}/modules/gateways/callback/{$this->gatewayModuleName}.php";

        $fields = [
            'full_name' => trim($this->clientDetails->firstname . ' ' . $this->clientDetails->lastname),
            'email' => $this->clientDetails->email,
            'phone' => $this->formatPhoneNumber($this->clientDetails->phonenumber),
            'amount' => $this->total,
            'currency' => $this->customerCurrency['code'],
            'metadata' => ['invoice_id' => $invoiceId],
            'redirect_url' => "{$baseCallbackUrl}?id={$invoiceId}&action=verify",
            'return_type' => 'GET',
            'cancel_url' => "{$systemUrl}/viewinvoice.php?error=cancelled&id={$invoiceId}",
            'webhook_url' => "{$baseCallbackUrl}?id={$invoiceId}&action=ipn",
        ];

        try {
            return [
                'status' => 'success',
                'payment_url' => $this->api->initPayment($fields, $this->getGatewayType()),
            ];
        } catch (UddoktaPayException $e) {
            return $this->errorResponse(ErrorCode::INVALID_RESPONSE, $e->getMessage());
        } catch (\Exception $e) {
            return $this->errorResponse(ErrorCode::SOMETHING_WRONG);
        }
    }

    /**
     * @param string $invoiceId
     * @param bool $isIpn
     * @return array
     */
    public function processPayment($invoiceId, $isIpn = false)
    {
        try {
            $payment = $isIpn
                ? $this->api->executePayment()
                : $this->api->verifyPayment($invoiceId);

            return $this->handlePaymentResult($payment);
        } catch (UddoktaPayException $e) {
            return $this->errorResponse(ErrorCode::INVALID_RESPONSE, $e->getMessage());
        } catch (\Exception $e) {
            return $this->errorResponse(ErrorCode::SOMETHING_WRONG);
        }
    }

    /**
     * @param array $payment
     * @return array
     */
    private function handlePaymentResult(array $payment)
    {
        $status = PaymentStatus::tryFrom(isset($payment['status']) ? $payment['status'] : '');

        if ($status === PaymentStatus::COMPLETED) {
            return $this->handleCompletedPayment($payment);
        } elseif ($status === PaymentStatus::PENDING) {
            return $this->handlePendingPayment();
        }

        return $this->errorResponse(ErrorCode::INVALID_RESPONSE);
    }

    /**
     * @param array $payment
     * @return array
     */
    private function handleCompletedPayment(array $payment)
    {
        $transactionId = $payment['transaction_id'];

        if ($this->transactionExists($transactionId)) {
            return [
                'status' => 'success',
                'message' => ErrorCode::message(ErrorCode::TRANSACTION_USED),
                'errorCode' => ErrorCode::TRANSACTION_USED,
            ];
        }

        if ($payment['amount'] < $this->total) {
            return $this->errorResponse(ErrorCode::LESS_AMOUNT);
        }

        $this->logTransaction($payment);
        $result = $this->addTransaction($transactionId);

        if ($result['result'] !== 'success') {
            return $this->errorResponse(ErrorCode::SOMETHING_WRONG);
        }

        return [
            'status' => 'success',
            'message' => 'The payment has been successfully verified.',
        ];
    }

    /**
     * @return array
     */
    private function handlePendingPayment()
    {
        return $this->errorResponse(ErrorCode::PENDING_VERIFICATION);
    }

    /**
     * @param string $code
     * @param string|null $customMessage
     * @return array
     */
    protected function errorResponse($code, $customMessage = null)
    {
        return [
            'status' => 'error',
            'message' => $customMessage !== null ? $customMessage : ErrorCode::message($code),
            'errorCode' => $customMessage !== null ? $customMessage : $code,
        ];
    }

    /**
     * @param string $phoneNumber
     * @return string
     */
    private function formatPhoneNumber($phoneNumber)
    {
        $phoneNumber = trim($phoneNumber);

        // Handle WHMCS default format: +CountryCode.SubscriberNumber
        if (strpos($phoneNumber, '.') !== false && strpos($phoneNumber, '+') === 0) {
            $parts = explode('.', $phoneNumber, 2);
            $country = $parts[0];
            $subscriber = $parts[1];
            $lastDigit = substr($country, -1);
            $subscriber = preg_replace('/\D/', '', $subscriber);

            return $lastDigit . $subscriber;
        }

        return preg_replace('/\D/', '', $phoneNumber);
    }

    /**
     * @return array
     */
    private function fetchInvoice()
    {
        return localAPI('GetInvoice', ['invoiceid' => $this->request->get('id')]);
    }

    /**
     * @return array
     */
    private function fetchCurrency()
    {
        $currencyId = Capsule::table('tblclients')
            ->where('id', '=', $this->invoice['userid'])
            ->value('currency');

        return (array) Capsule::table('tblcurrencies')
            ->where('id', '=', $currencyId)
            ->first();
    }

    /**
     * @return object
     */
    private function fetchClient()
    {
        return Capsule::table('tblclients')
            ->where('id', '=', $this->invoice['userid'])
            ->first();
    }

    /**
     * @param string $transactionId
     * @return bool
     */
    private function transactionExists($transactionId)
    {
        $result = localAPI('GetTransactions', ['transid' => $transactionId]);

        return (isset($result['totalresults']) ? $result['totalresults'] : 0) > 0;
    }

    /**
     * @param array $payload
     * @return void
     */
    private function logTransaction(array $payload)
    {
        logTransaction(
            $this->gatewayParams['name'],
            [
                $this->gatewayModuleName => $payload,
                'request_data' => $this->request->request->all(),
            ],
            $payload['status']
        );
    }

    /**
     * @param string $transactionId
     * @return array
     */
    private function addTransaction($transactionId)
    {
        $fields = [
            'invoiceid' => $this->invoice['invoiceid'],
            'transid' => $transactionId,
            'gateway' => $this->gatewayModuleName,
            'date' => Carbon::now()->toDateTimeString(),
            'amount' => $this->due,
            'fees' => $this->fee,
        ];

        return array_merge(localAPI('AddInvoicePayment', $fields), $fields);
    }
}
