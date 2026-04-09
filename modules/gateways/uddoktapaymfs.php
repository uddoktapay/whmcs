<?php

if (!defined('WHMCS')) {
    exit('This file cannot be accessed directly');
}

use WHMCS\Module\Gateway\UddoktaPay\Enums\GatewayType;
use WHMCS\Module\Gateway\UddoktaPay\Handler\GatewayHelper;

function uddoktapaymfs_config()
{
    return GatewayHelper::getBaseConfig(GatewayType::MFS);
}

function uddoktapaymfs_link(array $params)
{
    return GatewayHelper::handleLink($params, GatewayType::MFS);
}
