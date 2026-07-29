<?php

namespace App\Rules;

use Closure;
use DateTimeZone;
use Exception;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A timezone this application can actually use.
 *
 * Laravel's own timezone rule checks timezone_identifiers_list(), which omits
 * the backward-compatibility aliases. Browsers still report them: Chrome on
 * Windows says "Asia/Calcutta" rather than "Asia/Kolkata", and rejecting that
 * refuses a check-in from a perfectly ordinary machine.
 *
 * Constructing a DateTimeZone is the criterion that matters, because it is
 * exactly what the service does with the value afterwards.
 */
class ValidTimezone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::usable($value)) {
            $fail('The :attribute is not a timezone this application recognises.');
        }
    }

    public static function usable(mixed $value): bool
    {
        if (! is_string($value) || $value === '') {
            return false;
        }

        try {
            new DateTimeZone($value);

            return true;
        } catch (Exception) {
            return false;
        }
    }
}
