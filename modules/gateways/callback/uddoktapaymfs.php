<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

use WHMCS\Module\Gateway\UddoktaPay\Enums\ErrorCode;
use WHMCS\Module\Gateway\UddoktaPay\Enums\GatewayType;
use WHMCS\Module\Gateway\UddoktaPay\Enums\PaymentAction;
use WHMCS\Module\Gateway\UddoktaPay\Handler\BasePaymentHandler;

final class UddoktaPayMfsHandler extends BasePaymentHandler
{
    protected function getGatewayType(): GatewayType
    {
        return GatewayType::MFS;
    }

    public static function init(): self
    {
        return new self();
    }
}

$handler = UddoktaPayMfsHandler::init();

if (!$handler->isActive) {
    exit('The gateway is unavailable.');
}

$action = PaymentAction::tryFrom($handler->request->get('action') ?? '');
$invoiceId = (int) $handler->request->get('id');

match ($action) {
    PaymentAction::INIT => handleInit($handler, $invoiceId),
    PaymentAction::VERIFY => handleVerify($handler, $invoiceId),
    PaymentAction::IPN => handleIpn($handler),
    default => redirectWithError($invoiceId, ErrorCode::SOMETHING_WRONG),
};

function handleInit(BasePaymentHandler $handler, int $invoiceId): never
{
    $response = $handler->createPayment();

    if ($response['status'] === 'success') {
        header('Location: ' . $response['payment_url']);
        exit;
    }

    redirectWithError($invoiceId, ErrorCode::tryFrom($response['errorCode']) ?? ErrorCode::SOMETHING_WRONG);
}

function handleVerify(BasePaymentHandler $handler, int $invoiceId): never
{
    $paymentInvoiceId = $handler->request->get('invoice_id') ?? '';
    $response = $handler->processPayment($paymentInvoiceId);

    if ($response['status'] === 'success') {
        redirSystemURL("id={$invoiceId}", 'viewinvoice.php');
        exit;
    }

    redirectWithError($invoiceId, ErrorCode::tryFrom($response['errorCode']) ?? ErrorCode::SOMETHING_WRONG);
}

function handleIpn(BasePaymentHandler $handler): never
{
    $invoiceId = $handler->request->get('invoice_id') ?? '';
    $handler->processPayment($invoiceId, isIpn: true);
    exit;
}

function redirectWithError(int $invoiceId, ErrorCode $error): never
{
    redirSystemURL("id={$invoiceId}&error={$error->value}", 'viewinvoice.php');
    exit;
}
