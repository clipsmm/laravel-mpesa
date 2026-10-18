<?php

declare(strict_types=1);

namespace LaravelMpesa\Support;

use Propaganistas\LaravelPhone\PhoneNumber as LaravelPhoneNumber;
use Throwable;

/**
 * Kenyan M-Pesa MSISDNs in `254XXXXXXXXX` format — E.164 without the leading `+`.
 *
 * Wraps [propaganistas/laravel-phone](https://github.com/propaganistas/laravel-phone)
 * so callers can pass any familiar Kenyan format — `0712345678`, `712345678`,
 * `+254712345678`, `254712345678`, or `0712 345 678` — and always receive
 * `254712345678`, the format Safaricom's Daraja API expects for `PartyA` and
 * `PhoneNumber` on STK push requests. Parsing and validation are delegated to
 * Google's libphonenumber.
 */
final class PhoneNumber
{
    /**
     * ISO 3166-1 alpha-2 country code the M-Pesa API operates in.
     */
    public const REGION = 'KE';

    /**
     * Format a phone number to `254XXXXXXXXX` (no leading `+`), or return null
     * when the value is not a valid Kenyan mobile number.
     */
    public static function format(?string $number, string $region = self::REGION): ?string
    {
        if ($number === null || trim($number) === '') {
            return null;
        }

        try {
            $phone = new LaravelPhoneNumber($number, $region);

            if (! $phone->isOfCountry($region) || ! $phone->isOfType('mobile') || ! $phone->isValid()) {
                return null;
            }

            return ltrim($phone->formatE164(), '+');
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Determine whether a value is a valid Kenyan mobile number. Equivalent to
     * checking that {@see self::format()} would return a value.
     */
    public static function isValid(?string $number, string $region = self::REGION): bool
    {
        return self::format($number, $region) !== null;
    }
}
