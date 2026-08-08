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

namespace IEXBase\TronAPI\Governance;

use IEXBase\TronAPI\Exception\ValidationException;

/**
 * Represents one chain parameter ID and its proposed signed int64 value.
 */
final readonly class ProposalParameter
{
    /**
     * Validates the non-negative chain parameter identifier.
     */
    public function __construct(
        public int $key,
        public int $value,
    ) {
        if ($key < 0) {
            throw new ValidationException('A proposal parameter ID cannot be negative.');
        }
    }

    /**
     * Returns the exact native proposal parameter fields.
     *
     * @return array{key: int, value: int}
     */
    public function toNodeData(): array
    {
        return ['key' => $this->key, 'value' => $this->value];
    }
}
