<?php

namespace WHMCS\Module\Gateway\UddoktaPay\Enums;

final class GatewayType
{
    const DEFAULT = 'checkout-v2';
    const MFS = 'checkout-v2/mfs';
    const BANK = 'checkout-v2/bank';
    const GLOBAL = 'checkout-v2/global';

    /**
     * @var array<string, string>
     */
    private static $displayNames = [
        self::DEFAULT => 'UddoktaPay',
        self::MFS => 'UddoktaPay MFS',
        self::BANK => 'UddoktaPay Bank',
        self::GLOBAL => 'UddoktaPay Global',
    ];

    /**
     * @var array<string, string>
     */
    private static $moduleNames = [
        self::DEFAULT => 'uddoktapay',
        self::MFS => 'uddoktapaymfs',
        self::BANK => 'uddoktapaybank',
        self::GLOBAL => 'uddoktapayglobal',
    ];

    /**
     * @param string $type
     * @return string
     */
    public static function displayName($type)
    {
        return isset(self::$displayNames[$type]) ? self::$displayNames[$type] : 'UddoktaPay';
    }

    /**
     * @param string $type
     * @return string
     */
    public static function moduleName($type)
    {
        return isset(self::$moduleNames[$type]) ? self::$moduleNames[$type] : 'uddoktapay';
    }
}
