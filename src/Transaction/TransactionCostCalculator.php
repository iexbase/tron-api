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

namespace IEXBase\TronAPI\Transaction;

use IEXBase\TronAPI\Encoding\Hex;
use IEXBase\TronAPI\Encoding\Protobuf;
use IEXBase\TronAPI\Exception\TransactionException;
use IEXBase\TronAPI\Value\Amount;

/**
 * Calculates pre-broadcast Bandwidth and its maximum resource-burn cost.
 *
 * TRON charges one Bandwidth point per protobuf byte. The official estimate is
 * the transaction without result records plus a fixed 64-byte result allowance.
 * Resource availability and operation-specific fixed fees remain chain state
 * and are intentionally not guessed by this pure calculator.
 */
final readonly class TransactionCostCalculator
{
    private const int RESULT_ALLOWANCE_BYTES = 64;
    private const int SIGNATURE_BYTES = 65;
    private const int MAX_SIGNATURES = 32;

    /**
     * Returns the official pre-broadcast Bandwidth estimate for a final signature count.
     */
    public function bandwidth(Transaction $transaction, ?int $signatureCount = null): int
    {
        $existingSignatures = count($transaction->signatures());
        $finalSignatureCount = $signatureCount ?? max(1, $existingSignatures);
        if ($finalSignatureCount < max(1, $existingSignatures)
            || $finalSignatureCount > self::MAX_SIGNATURES
        ) {
            throw new TransactionException(sprintf(
                'The final transaction signature count must be between %d and %d.',
                max(1, $existingSignatures),
                self::MAX_SIGNATURES,
            ));
        }

        $rawDataField = Protobuf::messageField(1, Hex::toBytes($transaction->rawDataHex()));
        $signatureFieldBytes = strlen(Protobuf::bytesField(2, str_repeat("\0", self::SIGNATURE_BYTES)));

        return strlen($rawDataField)
            + ($signatureFieldBytes * $finalSignatureCount)
            + self::RESULT_ALLOWANCE_BYTES;
    }

    /**
     * Returns the maximum Bandwidth burn when no staked or free Bandwidth is sufficient.
     */
    public function maximumBandwidthBurn(
        Transaction $transaction,
        Amount $unitPrice,
        ?int $signatureCount = null,
    ): Amount {
        if ($unitPrice->decimals() !== Amount::TRX_DECIMALS) {
            throw new TransactionException('A Bandwidth unit price must be denominated in sun.');
        }

        return $unitPrice->multiplyByInteger($this->bandwidth($transaction, $signatureCount));
    }
}
