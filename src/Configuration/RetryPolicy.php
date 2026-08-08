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

namespace IEXBase\TronAPI\Configuration;

use IEXBase\TronAPI\Exception\ConfigurationException;

/**
 * Defines bounded exponential backoff for transient transport and rate errors.
 */
final readonly class RetryPolicy
{
    /**
     * Validates and stores retry limits and delay controls.
     *
     * @param int $maximumAttempts Total attempts, including the initial request.
     * @param int $initialDelayMilliseconds Delay before the first retry.
     * @param int $maximumDelayMilliseconds Upper bound for calculated delays.
     * @param int $jitterPercent Random positive jitter percentage from 0 to 100.
     */
    public function __construct(
        public int $maximumAttempts = 3,
        public int $initialDelayMilliseconds = 250,
        public int $maximumDelayMilliseconds = 5_000,
        public int $jitterPercent = 20,
    ) {
        if ($maximumAttempts < 1 || $maximumAttempts > 10) {
            throw new ConfigurationException('Maximum attempts must be between 1 and 10.');
        }

        if ($initialDelayMilliseconds < 0 || $maximumDelayMilliseconds < $initialDelayMilliseconds) {
            throw new ConfigurationException('Retry delays must be non-negative and use a valid upper bound.');
        }

        if ($jitterPercent < 0 || $jitterPercent > 100) {
            throw new ConfigurationException('Retry jitter must be between 0 and 100 percent.');
        }
    }

    /**
     * Calculates the next delay while respecting an optional Retry-After value.
     *
     * @param int      $failedAttempt One-based number of the failed attempt.
     * @param int|null $retryAfterSeconds Server-advised minimum delay.
     */
    public function delayMilliseconds(int $failedAttempt, ?int $retryAfterSeconds = null): int
    {
        if ($failedAttempt < 1) {
            throw new ConfigurationException('A failed attempt number must be positive.');
        }
        if ($retryAfterSeconds !== null && $retryAfterSeconds < 0) {
            throw new ConfigurationException('Retry-After seconds cannot be negative.');
        }

        $delay = $this->initialDelayMilliseconds;
        for ($attempt = 1;
            $attempt < $failedAttempt && $delay > 0 && $delay < $this->maximumDelayMilliseconds;
            ++$attempt
        ) {
            $delay = $delay > intdiv($this->maximumDelayMilliseconds, 2)
                ? $this->maximumDelayMilliseconds
                : $delay * 2;
        }

        if ($retryAfterSeconds !== null) {
            $retryAfterMilliseconds = $retryAfterSeconds > intdiv($this->maximumDelayMilliseconds, 1_000)
                ? $this->maximumDelayMilliseconds
                : $retryAfterSeconds * 1_000;
            $delay = max($delay, $retryAfterMilliseconds);
        }

        $jitterMaximum = min(
            (intdiv($delay, 100) * $this->jitterPercent)
                + intdiv(($delay % 100) * $this->jitterPercent, 100),
            $this->maximumDelayMilliseconds - $delay,
        );

        return $delay + ($jitterMaximum > 0 ? random_int(0, $jitterMaximum) : 0);
    }
}
