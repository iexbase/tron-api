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

namespace IEXBase\TronAPI\Contract;

use IEXBase\TronAPI\Exception\ContractException;
use IEXBase\TronAPI\Value\Amount;

/**
 * Centralizes native TRX and optional TRC-10 value rules for contract execution.
 */
final readonly class ContractValue
{
    /**
     * Stores already validated native and token amounts.
     */
    private function __construct(
        private Amount $nativeAmount,
        private ?string $tokenId,
        private ?Amount $tokenAmount,
    ) {
    }

    /**
     * Creates a payable-aware value for a function call or constructor.
     */
    public static function create(
        ?Amount $nativeAmount,
        ?string $tokenId,
        ?Amount $tokenAmount,
        bool $payable,
        string $operation,
    ): self {
        $native = $nativeAmount ?? Amount::fromAtomic(0);
        if ($native->decimals() !== Amount::TRX_DECIMALS) {
            throw new ContractException(sprintf('%s TRX value must use six decimals.', $operation));
        }
        if (($tokenId === null) !== ($tokenAmount === null)) {
            throw new ContractException(sprintf('%s TRC-10 value requires both token ID and token amount.', $operation));
        }
        if ($tokenId !== null && preg_match('/^[1-9][0-9]*$/D', $tokenId) !== 1) {
            throw new ContractException(sprintf('%s TRC-10 token ID must be a positive decimal integer.', $operation));
        }
        if ($tokenAmount !== null && $tokenAmount->isZero()) {
            throw new ContractException(sprintf('%s TRC-10 token amount must be positive.', $operation));
        }
        if (!$payable && (!$native->isZero() || $tokenAmount !== null)) {
            throw new ContractException(sprintf('A nonpayable %s cannot receive TRX or TRC-10 value.', $operation));
        }

        return new self($native, $tokenId, $tokenAmount);
    }

    /**
     * Returns the exact native TRX value.
     */
    public function nativeAmount(): Amount
    {
        return $this->nativeAmount;
    }

    /**
     * Returns the optional exact atomic TRC-10 value.
     */
    public function tokenAmount(): ?Amount
    {
        return $this->tokenAmount;
    }

    /**
     * Returns call/deployment request fields including native and token values.
     *
     * @return array<string, int|string>
     */
    public function requestFields(): array
    {
        return [
            'call_value' => $this->nativeAmount->atomicInteger(),
            ...$this->tokenFields(),
        ];
    }

    /**
     * Returns token-only fields for raw contract intent structures.
     *
     * @return array<string, int|string>
     */
    public function tokenFields(): array
    {
        return $this->tokenId === null || $this->tokenAmount === null
            ? []
            : [
                'token_id' => $this->tokenId,
                'call_token_value' => $this->tokenAmount->atomicInteger(),
            ];
    }
}
