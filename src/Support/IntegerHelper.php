<?php

declare(strict_types=1);

/**
 * TronAPI 6.0
 *
 * Copyright (c) 2018-2026 iEXBase.
 *
 * @author  Shamsudin Serderov <steein.shamsudin@gmail.com>
 * @license https://github.com/iexbase/tron-api/blob/master/LICENSE MIT License
 * @link    https://github.com/iexbase/tron-api
 */

namespace IEXBase\TronAPI\Support;

use IEXBase\TronAPI\Exception\ValidationException;

/**
 * Provides reusable bounds checks for native protocol integer inputs.
 */
final class IntegerHelper
{
    /**
     * Returns an integer only when it lies inside the inclusive range.
     */
    public static function between(int $value, int $minimum, int $maximum, string $field): int
    {
        if ($minimum > $maximum || $value < $minimum || $value > $maximum) {
            throw new ValidationException(sprintf(
                '%s must be between %d and %d.',
                $field,
                $minimum,
                $maximum,
            ));
        }

        return $value;
    }

    /**
     * Returns a non-negative integer or rejects invalid counters and limits.
     */
    public static function nonNegative(int $value, string $field): int
    {
        if ($value < 0) {
            throw new ValidationException(sprintf('%s cannot be negative.', $field));
        }

        return $value;
    }

    /**
     * Returns a positive integer or rejects zero and negative values.
     */
    public static function positive(int $value, string $field): int
    {
        if ($value <= 0) {
            throw new ValidationException(sprintf('%s must be positive.', $field));
        }

        return $value;
    }
}
