<?php

if (!defined('WHMCS')) {
    exit('This file cannot be accessed directly');
}

use WHMCS\Module\Gateway\UddoktaPay\Enums\GatewayType;
use WHMCS\Module\Gateway\UddoktaPay\Handler\GatewayHelper;

function uddoktapay_config()
{
    return GatewayHelper::getBaseConfig(GatewayType::DEFAULT);
}

function uddoktapay_link(array $params)
{
    return GatewayHelper::handleLink($params, GatewayType::DEFAULT);
}
