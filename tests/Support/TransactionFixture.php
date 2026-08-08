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

namespace IEXBase\TronAPI\Tests\Support;

use IEXBase\TronAPI\Contract\Abi;
use IEXBase\TronAPI\Encoding\Hex;
use IEXBase\TronAPI\Transaction\Permission;
use IEXBase\TronAPI\Transaction\Transaction;
use IEXBase\TronAPI\Transaction\TransactionIntent;
use IEXBase\TronAPI\Transaction\TransactionWireEncoder;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\ByteString;

/**
 * Builds internally consistent node transaction objects for isolated unit tests.
 */
final class TransactionFixture
{
    /**
     * Creates transaction JSON that matches an approved intent.
     *
     * @return array<string, mixed>
     *
     */
    public static function data(TransactionIntent $intent): array
    {
        $value = [
            'owner_address' => $intent->ownerAddress->toBase58(),
            ...self::nodeValues($intent->contractFields()),
        ];
        $contract = [
            'parameter' => [
                'value' => $value,
                'type_url' => 'type.googleapis.com/protocol.' . $intent->contractType,
            ],
            'type' => $intent->contractType,
        ];
        if ($intent->permissionId !== 0) {
            $contract['Permission_id'] = $intent->permissionId;
        }

        $rawData = [
            'contract' => [$contract],
            'ref_block_bytes' => '0001',
            'ref_block_hash' => '0000000000000001',
            'expiration' => 4_102_444_800_000,
            'timestamp' => 1_700_000_000_000,
        ];
        if ($intent->memoHex() !== null) {
            $rawData['data'] = $intent->memoHex();
        }
        if ($intent->feeLimit !== null) {
            $rawData['fee_limit'] = $intent->feeLimit->atomicInteger();
        }

        $rawDataBytes = (new TransactionWireEncoder())->encode($intent, $rawData);
        $rawDataHex = Hex::fromBytes($rawDataBytes);

        return [
            'visible' => true,
            'txID' => hash('sha256', $rawDataBytes),
            'raw_data' => $rawData,
            'raw_data_hex' => $rawDataHex,
        ];
    }

    /**
     * Creates a verified immutable transaction from matching fixture data.
     */
    public static function transaction(TransactionIntent $intent): Transaction
    {
        return Transaction::fromNodeData(self::data($intent));
    }

    /**
     * Converts typed intent values into the visible=true native JSON shape.
     *
     * @param array<string, mixed> $values Typed intent values.
     * @return array<string, mixed>
     *
     */
    private static function nodeValues(array $values): array
    {
        $result = [];
        foreach ($values as $key => $value) {
            $result[$key] = self::nodeValue($value);
        }

        return $result;
    }

    /**
     * Converts one recursive typed intent value into its node representation.
     *
     */
    private static function nodeValue(mixed $value): mixed
    {
        if ($value instanceof Address) {
            return $value->toBase58();
        }
        if ($value instanceof ByteString) {
            return $value->toHex(false);
        }
        if ($value instanceof Permission) {
            return $value->toNodeData();
        }
        if ($value instanceof Abi) {
            return $value->protocolFields();
        }
        if (!is_array($value)) {
            return $value;
        }

        $result = [];
        foreach ($value as $key => $item) {
            $result[$key] = self::nodeValue($item);
        }

        return $result;
    }
}
