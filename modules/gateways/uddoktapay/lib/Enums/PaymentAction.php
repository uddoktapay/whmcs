<?php

namespace WHMCS\Module\Gateway\UddoktaPay\Enums;

final class PaymentAction
{
    const INIT = 'init';
    const VERIFY = 'verify';
    const IPN = 'ipn';

    /**
     * @var array<string, string>
     */
    private static $validValues = [
        self::INIT => self::INIT,
        self::VERIFY => self::VERIFY,
        self::IPN => self::IPN,
    ];

    /**
     * @param string $value
     * @return string|null
     */
    public static function tryFrom($value)
    {
        return isset(self::$validValues[$value]) ? self::$validValues[$value] : null;
    }
}
