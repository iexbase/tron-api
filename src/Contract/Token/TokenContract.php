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

namespace IEXBase\TronAPI\Contract\Token;

use IEXBase\TronAPI\Contract\Abi;
use IEXBase\TronAPI\Contract\ContractCall;
use IEXBase\TronAPI\Contract\DecodedValues;
use IEXBase\TronAPI\Enum\ConfirmationLevel;
use IEXBase\TronAPI\Exception\ContractException;
use IEXBase\TronAPI\Service\ContractService;
use IEXBase\TronAPI\Transaction\Transaction;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\Amount;
use IEXBase\TronAPI\Value\Memo;

/**
 * Shares ABI call construction and strict output checks across token standards.
 */
abstract class TokenContract
{
    /**
     * Stores the contract service, target, caller, and canonical token ABI.
     */
    public function __construct(
        protected readonly ContractService $contracts,
        public readonly Address $contractAddress,
        public readonly Address $callerAddress,
        protected readonly Abi $abi,
    ) {
    }

    /**
     * Executes a standard read-only function and returns all decoded outputs.
     *
     * @param array<mixed> $arguments Ordered standard arguments.
     */
    protected function read(
        string $signature,
        array $arguments = [],
        ConfirmationLevel $level = ConfirmationLevel::Confirmed,
    ): DecodedValues {
        return $this->contracts->read($this->call($signature, $arguments), $level)->outputs;
    }

    /**
     * Builds a verified standard token transaction with an explicit fee limit.
     *
     * @param array<mixed> $arguments Ordered standard arguments.
     */
    protected function createTransaction(
        string $signature,
        array $arguments,
        Amount $feeLimit,
        ?Memo $memo,
        int $permissionId,
    ): Transaction {
        return $this->contracts->createTransaction(
            $this->call($signature, $arguments),
            $feeLimit,
            $memo,
            $permissionId,
        );
    }

    /**
     * Creates an ABI-resolved call with this token's target and caller.
     *
     * @param array<mixed> $arguments Ordered standard arguments.
     */
    protected function call(string $signature, array $arguments): ContractCall
    {
        return new ContractCall(
            $this->callerAddress,
            $this->contractAddress,
            $this->abi->function($signature),
            $arguments,
            abi: $this->abi,
        );
    }

    /**
     * Returns a required string output at one position.
     */
    protected function stringOutput(DecodedValues $values, int $position = 0): string
    {
        $value = $values->value($position);
        if (!is_string($value)) {
            throw new ContractException('The token contract output is not a string.');
        }

        return $value;
    }

    /**
     * Returns a required boolean output at one position.
     */
    protected function booleanOutput(DecodedValues $values, int $position = 0): bool
    {
        $value = $values->value($position);
        if (!is_bool($value)) {
            throw new ContractException('The token contract output is not boolean.');
        }

        return $value;
    }

    /**
     * Returns a required address output at one position.
     */
    protected function addressOutput(DecodedValues $values, int $position = 0): Address
    {
        $value = $values->value($position);
        if (!$value instanceof Address) {
            throw new ContractException('The token contract output is not an address.');
        }

        return $value;
    }

    /**
     * Validates one semantically named unsigned decimal contract argument.
     */
    protected function unsignedDecimal(int|string $value, string $label): string
    {
        $decimal = (string) $value;
        if (preg_match('/^(0|[1-9][0-9]*)$/D', $decimal) !== 1) {
            throw new ContractException(sprintf(
                'A %s must be a canonical non-negative decimal integer.',
                $label,
            ));
        }

        return $decimal;
    }
}
