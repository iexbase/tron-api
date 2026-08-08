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

use IEXBase\TronAPI\Api\DataDecoder;
use IEXBase\TronAPI\Contract\Abi;
use IEXBase\TronAPI\Exception\TransactionException;
use IEXBase\TronAPI\Encoding\Hex;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\ByteString;
use JsonException;

/**
 * Verifies every user-controlled field before a node-built transaction is signed.
 */
final class TransactionVerifier
{
    /**
     * Creates an intent verifier with deterministic protobuf reconstruction.
     */
    public function __construct(private TransactionWireEncoder $wireEncoder = new TransactionWireEncoder())
    {
    }

    /**
     * Requires the transaction to match its approved intent exactly.
     */
    public function verify(Transaction $transaction, TransactionIntent $intent): void
    {
        $contract = $transaction->singleContract();
        if (($contract['type'] ?? null) !== $intent->contractType) {
            throw new TransactionException('The node returned a different contract type than the approved intent.');
        }

        $parameter = $contract['parameter'] ?? null;
        $actualFields = is_array($parameter) ? ($parameter['value'] ?? null) : null;
        if (!is_array($actualFields)) {
            throw new TransactionException('The transaction contract does not contain a parameter value object.');
        }

        $typeUrl = $parameter['type_url'] ?? null;
        if (!is_string($typeUrl)
            || !hash_equals('type.googleapis.com/protocol.' . $intent->contractType, $typeUrl)
        ) {
            throw new TransactionException('The transaction parameter type_url does not match the approved contract type.');
        }

        $expectedFields = ['owner_address' => $intent->ownerAddress, ...$intent->contractFields()];
        $unexpectedFields = array_diff(array_keys($actualFields), array_keys($expectedFields));
        if ($unexpectedFields !== []) {
            throw new TransactionException('The node inserted unexpected contract fields: ' . implode(', ', $unexpectedFields));
        }

        foreach ($expectedFields as $name => $expectedValue) {
            if (!array_key_exists($name, $actualFields)) {
                if (in_array($expectedValue, [0, false, ''], true) || $intent->fieldMayBeOmitted($name)) {
                    continue;
                }
                throw new TransactionException(sprintf('The transaction is missing the approved `%s` field.', $name));
            }

            if (!$this->valuesMatch($actualFields[$name], $expectedValue)) {
                throw new TransactionException(sprintf('The transaction `%s` field does not match the approved intent.', $name));
            }
        }

        $permissionId = $contract['Permission_id'] ?? 0;
        if (!is_int($permissionId) || $permissionId !== $intent->permissionId) {
            throw new TransactionException('The transaction Permission_id does not match the approved intent.');
        }

        $rawData = $transaction->rawData();
        $actualMemo = $rawData['data'] ?? null;
        if ($intent->memoHex() === null) {
            if ($actualMemo !== null && $actualMemo !== '') {
                throw new TransactionException('The node inserted a memo that was not approved.');
            }
        } elseif (!is_string($actualMemo)
            || !hash_equals($intent->memoHex(), Hex::canonicalize($actualMemo))
        ) {
            throw new TransactionException('The transaction memo does not match the approved intent.');
        }

        $actualFeeLimit = $rawData['fee_limit'] ?? null;
        if ($intent->feeLimit === null) {
            if ($actualFeeLimit !== null && $actualFeeLimit !== 0) {
                throw new TransactionException('The node inserted a fee_limit that was not approved.');
            }
        } elseif (!is_int($actualFeeLimit)
            || (string) $actualFeeLimit !== $intent->feeLimit->atomicValue()
        ) {
            throw new TransactionException('The transaction fee_limit does not match the approved intent.');
        }

        $expectedRawDataHex = Hex::fromBytes($this->wireEncoder->encode($intent, $rawData));
        if (!hash_equals($expectedRawDataHex, $transaction->rawDataHex())) {
            throw new TransactionException(
                'The signed raw_data_hex bytes do not match the approved transaction fields and node metadata.',
            );
        }
    }

    /**
     * Compares an API value against an address or exact scalar intent value.
     */
    private function valuesMatch(mixed $actual, mixed $expected): bool
    {
        if ($expected instanceof Address) {
            if (!is_string($actual)) {
                return false;
            }

            try {
                return Address::fromString($actual)->equals($expected);
            } catch (\Throwable) {
                return false;
            }
        }

        if ($expected instanceof ByteString) {
            return is_string($actual)
                && hash_equals($expected->toHex(false), Hex::canonicalize($actual));
        }

        if ($expected instanceof Abi) {
            if (!is_array($actual)) {
                return false;
            }

            try {
                return hash_equals($this->abiJson($expected), $this->abiJson(Abi::fromArray($actual)));
            } catch (\Throwable) {
                return false;
            }
        }

        if ($expected instanceof Permission) {
            if (!is_array($actual)) {
                return false;
            }

            try {
                return $expected->equals(Permission::fromNodeData(DataDecoder::object($actual, 'permission')));
            } catch (\Throwable) {
                return false;
            }
        }

        if (is_int($expected)) {
            return (is_int($actual) || (is_string($actual) && ctype_digit($actual)))
                && (string) $actual === (string) $expected;
        }

        if (is_array($expected)) {
            if (!is_array($actual) || array_diff(array_keys($actual), array_keys($expected)) !== []) {
                return false;
            }

            foreach ($expected as $key => $expectedValue) {
                if (!array_key_exists($key, $actual)) {
                    if (in_array($expectedValue, [0, false, ''], true)) {
                        continue;
                    }

                    return false;
                }
                if (!$this->valuesMatch($actual[$key], $expectedValue)) {
                    return false;
                }
            }

            return true;
        }

        return $actual === $expected;
    }

    /**
     * Serializes a parsed ABI into one deterministic semantic comparison string.
     *
     * @throws JsonException When an internally constructed ABI cannot be encoded.
     */
    private function abiJson(Abi $abi): string
    {
        return json_encode($abi, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
