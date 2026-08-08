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

namespace IEXBase\TronAPI\Value;

use IEXBase\TronAPI\Exception\ValidationException;
use IEXBase\TronAPI\Model\Asset;

/**
 * Couples a native TRX or TRC-10 identifier with a positive exact amount.
 */
final readonly class NativeAssetAmount
{
    /**
     * Validates a positive amount and the six-decimal TRX rule.
     */
    private function __construct(
        public NativeAssetId $assetId,
        public Amount $amount,
    ) {
        if ($amount->isZero()) {
            throw new ValidationException('A native asset amount must be positive.');
        }
        if ($assetId->isTrx() && $amount->decimals() !== Amount::TRX_DECIMALS) {
            throw new ValidationException('Native TRX amounts must use six decimals.');
        }
    }

    /**
     * Creates an exact native TRX amount.
     */
    public static function trx(Amount $amount): self
    {
        return new self(NativeAssetId::trx(), $amount);
    }

    /**
     * Creates an exact TRC-10 amount and enforces token metadata precision.
     */
    public static function trc10(Asset $asset, Amount $amount): self
    {
        if ($amount->decimals() !== $asset->precision) {
            throw new ValidationException('A native TRC-10 amount must match its asset precision.');
        }

        return new self(NativeAssetId::trc10($asset), $amount);
    }
}
