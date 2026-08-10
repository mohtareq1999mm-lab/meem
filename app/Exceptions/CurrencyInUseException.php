<?php

namespace App\Exceptions;

use Exception;

class CurrencyInUseException extends Exception
{
    public const REASON_BASE_CURRENCY = 'base_currency';
    public const REASON_REFERENCED_BY_RATES = 'referenced_by_rates';

    public function __construct(
        string $message,
        public readonly string $reason,
    ) {
        parent::__construct($message);
    }

    public static function referencedByRates(): self
    {
        return new self('Cannot delete a currency that has exchange rates.', self::REASON_REFERENCED_BY_RATES);
    }

    public static function isBaseCurrency(): self
    {
        return new self('Cannot delete the base currency.', self::REASON_BASE_CURRENCY);
    }
}
