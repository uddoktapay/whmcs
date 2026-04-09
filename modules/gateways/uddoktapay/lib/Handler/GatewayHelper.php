<?php

namespace WHMCS\Module\Gateway\UddoktaPay\Handler;

use WHMCS\Module\Gateway\UddoktaPay\Enums\ErrorCode;
use WHMCS\Module\Gateway\UddoktaPay\Enums\GatewayType;

final class GatewayHelper
{
    /**
     * @param string $message
     * @param string $type
     * @return string
     */
    public static function renderAlert($message, $type)
    {
        return sprintf(
            '<div class="alert alert-%s" style="margin-top: 10px;" role="alert">%s</div>',
            htmlspecialchars($type, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * @param string $actionUrl
     * @param int $invoiceId
     * @param string $buttonText
     * @return string
     */
    public static function renderPaymentForm($actionUrl, $invoiceId, $buttonText)
    {
        $escapedUrl = htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8');
        $escapedButtonText = htmlspecialchars($buttonText, ENT_QUOTES, 'UTF-8');

        return '<form method="GET" action="' . $escapedUrl . '">'
            . '<input type="hidden" name="action" value="init" />'
            . '<input type="hidden" name="id" value="' . $invoiceId . '" />'
            . '<input class="btn btn-primary" type="submit" value="' . $escapedButtonText . '" />'
            . '</form>';
    }

    /**
     * @param string $type
     * @return array
     */
    public static function getBaseConfig($type)
    {
        return [
            'FriendlyName' => [
                'Type' => 'System',
                'Value' => GatewayType::displayName($type),
            ],
            'api_key' => [
                'FriendlyName' => 'API KEY',
                'Type' => 'text',
                'Size' => '40',
            ],
            'api_url' => [
                'FriendlyName' => 'API URL',
                'Type' => 'text',
                'Size' => '50',
            ],
        ];
    }

    /**
     * @param array $params
     * @param string $type
     * @return string
     */
    public static function handleLink(array $params, $type)
    {
        $invoiceId = (int) $params['invoiceid'];

        $errorCode = isset($_REQUEST['error']) ? $_REQUEST['error'] : '';
        $paymentForm = self::renderPaymentForm(
            $params['systemurl'] . '/modules/gateways/callback/' . GatewayType::moduleName($type) . '.php',
            $invoiceId,
            $params['langpaynow']
        );

        if ($errorCode === '') {
            return $paymentForm;
        }

        $isPending = $errorCode === ErrorCode::PENDING_VERIFICATION;
        $alert = self::renderAlert(ErrorCode::getMessage($errorCode), $isPending ? 'warning' : 'danger');

        return $isPending ? $alert : $alert . $paymentForm;
    }
}
