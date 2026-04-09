<?php

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

use WHMCS\Module\Gateway\UddoktaPay\Enums\GatewayType;
use WHMCS\Module\Gateway\UddoktaPay\Handler\BasePaymentHandler;

final class UddoktaPayMfsHandler extends BasePaymentHandler
{
    /**
     * @return string
     */
    protected function getGatewayType()
    {
        return GatewayType::MFS;
    }

    /**
     * @return self
     */
    public static function init()
    {
        return new self();
    }
}

$handler = UddoktaPayMfsHandler::init();
$handler->run();
