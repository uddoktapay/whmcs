<?php

namespace WHMCS\Module\Gateway\UddoktaPay\Enums;

final class ErrorCode
{
    const CANCELLED = 'cancelled';
    const INVALID_RESPONSE = 'irs';
    const TRANSACTION_USED = 'tau';
    const LESS_AMOUNT = 'lpa';
    const PENDING_VERIFICATION = 'pfv';
    const SOMETHING_WRONG = 'sww';

    /**
     * @var array<string, string>
     */
    private static $messages = [
        self::CANCELLED => 'Payment has been cancelled.',
        self::INVALID_RESPONSE => 'Invalid response from UddoktaPay API.',
        self::TRANSACTION_USED => 'This transaction has already been processed.',
        self::LESS_AMOUNT => 'The paid amount is less than the required amount.',
        self::PENDING_VERIFICATION => 'Your payment is pending verification.',
        self::SOMETHING_WRONG => 'Something went wrong. Please try again.',
    ];

    /**
     * @param string $code
     * @return string
     */
    public static function message($code)
    {
        return isset(self::$messages[$code]) ? self::$messages[$code] : $code;
    }

    /**
     * @param string $code
     * @return string
     */
    public static function getMessage($code)
    {
        return self::message($code);
    }
}
