<?php

if (!defined('WHMCS')) {
    exit('This file cannot be accessed directly');
}

use WHMCS\Module\Gateway\UddoktaPay\Enums\GatewayType;
use WHMCS\Module\Gateway\UddoktaPay\Handler\GatewayHelper;

function uddoktapaybank_config()
{
    return GatewayHelper::getBaseConfig(GatewayType::BANK);
}

function uddoktapaybank_link(array $params)
{
    return GatewayHelper::handleLink($params, GatewayType::BANK);
}
