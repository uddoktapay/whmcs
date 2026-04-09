<?php

namespace WHMCS\Module\Gateway\UddoktaPay\Enums;

final class PaymentStatus
{
    const COMPLETED = 'COMPLETED';
    const PENDING = 'PENDING';

    /**
     * @var array<string, string>
     */
    private static $validValues = [
        self::COMPLETED => self::COMPLETED,
        self::PENDING => self::PENDING,
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
