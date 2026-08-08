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

use IEXBase\TronAPI\Api\ApiClient;
use IEXBase\TronAPI\Api\DataDecoder;
use IEXBase\TronAPI\Api\Endpoint;
use IEXBase\TronAPI\Encoding\Hex;
use IEXBase\TronAPI\Exception\TransactionException;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\Amount;
use IEXBase\TronAPI\Value\Memo;

/**
 * Builds node transactions and verifies their hash and approved intent centrally.
 */
final readonly class TransactionFactory
{
    /**
     * Creates a factory with the generic API client and exact intent verifier.
     */
    public function __construct(
        private ApiClient $client,
        private TransactionVerifier $verifier = new TransactionVerifier(),
    ) {
    }

    /**
     * Builds and verifies one native-system-contract transaction.
     *
     * Request fields default to the exact fields expected in `raw_data`. Callers
     * provide a separate request map only when an HTTP endpoint uses a different
     * representation, such as encoded smart-contract parameters or witness votes.
     *
     * @param array<string, mixed>      $contractFields Fields expected in `raw_data`.
     * @param array<string, mixed>|null $requestFields Endpoint-specific JSON fields.
     * @param list<string>              $omittableFields Protobuf default fields the node may omit.
     */
    public function createNativeContract(
        Endpoint $endpoint,
        string $contractType,
        Address $ownerAddress,
        array $contractFields,
        ?Memo $memo = null,
        int $permissionId = 0,
        ?array $requestFields = null,
        array $omittableFields = [],
        ?Amount $feeLimit = null,
    ): Transaction {
        return $this->create(
            $endpoint,
            new TransactionIntent(
                $contractType,
                $ownerAddress,
                $contractFields,
                $permissionId,
                $memo?->toHex(),
                $feeLimit,
                $omittableFields,
            ),
            $requestFields ?? $contractFields,
        );
    }

    /**
     * Requests a node-built transaction and rejects every unapproved field.
     *
     * The caller supplies endpoint-specific request fields separately from the
     * expected protocol contract fields because smart-contract HTTP parameters
     * use `function_selector`/`parameter` while raw_data stores combined `data`.
     *
     * @param array<string, mixed> $requestFields Endpoint-specific JSON fields.
     */
    public function create(Endpoint $endpoint, TransactionIntent $intent, array $requestFields): Transaction
    {
        $reservedFields = ['owner_address', 'visible', 'Permission_id', 'extra_data', 'fee_limit'];
        $conflictingFields = array_intersect(array_keys($requestFields), $reservedFields);
        if ($conflictingFields !== []) {
            throw new TransactionException(
                'Transaction request fields conflict with factory-managed fields: ' . implode(', ', $conflictingFields),
            );
        }

        $payload = [
            'owner_address' => $intent->ownerAddress->toBase58(),
            ...$this->apiValues($requestFields, 0),
            'visible' => true,
        ];

        if ($intent->permissionId !== 0) {
            $payload['Permission_id'] = $intent->permissionId;
        }
        if ($intent->memoHex() !== null) {
            $payload['extra_data'] = Hex::decodeUtf8($intent->memoHex());
        }
        if ($intent->feeLimit !== null) {
            $payload['fee_limit'] = $intent->feeLimit->atomicInteger();
        }

        $response = $this->client->request($endpoint->request($payload))->requireAccepted();
        $data = $response->data();
        $transactionData = isset($data['transaction'])
            ? DataDecoder::object($data['transaction'], 'transaction')
            : DataDecoder::object($data, 'transaction');

        $transaction = Transaction::fromNodeData($transactionData);

        return $transaction->approveIntent($intent, $this->verifier);
    }

    /**
     * Converts Address objects recursively while preserving exact scalar values.
     *
     * @param array<string, mixed> $values Endpoint request fields.
     * @return array<string, mixed>
     */
    private function apiValues(array $values, int $depth): array
    {
        if ($depth > 32) {
            throw new TransactionException('Transaction request field nesting exceeds the safety limit.');
        }

        $result = [];
        foreach ($values as $key => $value) {
            $result[$key] = $this->apiValue($value, $depth);
        }

        return $result;
    }

    /**
     * Converts one recursive endpoint value into JSON-compatible data.
     */
    private function apiValue(mixed $value, int $depth): mixed
    {
        if ($value instanceof Address) {
            return $value->toBase58();
        }

        if (!is_array($value)) {
            if (!is_bool($value) && !is_int($value) && !is_string($value)) {
                throw new TransactionException('A transaction request contains an unsupported JSON value.');
            }

            return $value;
        }

        if ($depth >= 32) {
            throw new TransactionException('Transaction request field nesting exceeds the safety limit.');
        }

        $result = [];
        foreach ($value as $key => $item) {
            $result[$key] = $this->apiValue($item, $depth + 1);
        }

        return $result;
    }
}
