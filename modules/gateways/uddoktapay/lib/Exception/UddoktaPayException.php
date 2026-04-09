<?php

namespace WHMCS\Module\Gateway\UddoktaPay\Exception;

use Exception;
use Throwable;

class UddoktaPayException extends Exception
{
    /**
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     * @return self
     */
    public static function make($message, $code = 0, $previous = null)
    {
        return new self($message, $code, $previous);
    }
}
