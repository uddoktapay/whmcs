<?php

if (!defined('WHMCS')) {
    exit('This file cannot be accessed directly');
}

use WHMCS\Module\Gateway\UddoktaPay\Enums\GatewayType;
use WHMCS\Module\Gateway\UddoktaPay\Handler\GatewayHelper;

function uddoktapayglobal_config()
{
    return GatewayHelper::getBaseConfig(GatewayType::GLOBAL);
}

function uddoktapayglobal_link(array $params)
{
    return GatewayHelper::handleLink($params, GatewayType::GLOBAL);
}
