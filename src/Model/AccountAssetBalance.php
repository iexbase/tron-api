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

namespace IEXBase\TronAPI\Model;

use IEXBase\TronAPI\Exception\ValidationException;
use IEXBase\TronAPI\Value\Amount;
use IEXBase\TronAPI\Value\NativeAssetId;
use JsonSerializable;

/**
 * Represents one exact account TRC-10 balance before token precision is applied.
 */
final readonly class AccountAssetBalance implements JsonSerializable
{
    /**
     * Stores a token identifier and its canonical non-negative atomic balance.
     */
    public function __construct(
        public NativeAssetId $assetId,
        private string $atomicValue,
    ) {
        if (preg_match('/^(0|[1-9][0-9]*)$/D', $atomicValue) !== 1) {
            throw new ValidationException('An account asset balance must be a non-negative atomic integer.');
        }
    }

    /**
     * Returns the exact atomic integer without depending on the PHP integer range.
     */
    public function atomicValue(): string
    {
        return $this->atomicValue;
    }

    /**
     * Applies separately fetched TRC-10 precision to this atomic balance.
     */
    public function amount(int $precision): Amount
    {
        return Amount::fromAtomic($this->atomicValue, $precision);
    }

    /**
     * Serializes a stable object without using a numeric token ID as a PHP array key.
     *
     * @return array{asset_id: string, atomic_value: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'asset_id' => $this->assetId->value(),
            'atomic_value' => $this->atomicValue,
        ];
    }
}
