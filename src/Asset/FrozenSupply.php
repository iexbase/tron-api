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

namespace IEXBase\TronAPI\Asset;

use IEXBase\TronAPI\Exception\ValidationException;
use IEXBase\TronAPI\Support\IntegerHelper;
use IEXBase\TronAPI\Value\Amount;

/**
 * Represents one positive time-locked portion of a new TRC-10 supply.
 */
final readonly class FrozenSupply
{
    /**
     * Validates the amount and protocol-supported lock duration.
     */
    public function __construct(
        public Amount $amount,
        public int $days,
    ) {
        if ($amount->isZero()) {
            throw new ValidationException('A frozen TRC-10 supply amount must be positive.');
        }
        IntegerHelper::between($days, 1, 3_652, 'Frozen supply duration');
    }

    /**
     * Returns the exact native FrozenSupply message fields.
     *
     * @return array{frozen_amount: int, frozen_days: int}
     */
    public function toNodeData(): array
    {
        return [
            'frozen_amount' => $this->amount->atomicInteger(),
            'frozen_days' => $this->days,
        ];
    }
}
