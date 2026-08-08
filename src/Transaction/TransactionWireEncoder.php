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
use IEXBase\TronAPI\Enum\ContractType;
use IEXBase\TronAPI\Exception\TransactionException;

/**
 * Reconstructs signed Transaction.raw bytes from approved intent and node metadata.
 *
 * Comparing this result with `raw_data_hex` closes the ambiguity between the
 * human-readable `raw_data` object and the actual bytes hashed and signed.
 */
final readonly class TransactionWireEncoder
{
    /** @var list<string> */
    private const array RAW_DATA_FIELDS = [
        'ref_block_bytes',
        'ref_block_num',
        'ref_block_hash',
        'expiration',
        'auths',
        'data',
        'contract',
        'scripts',
        'timestamp',
        'fee_limit',
    ];

    /**
     * Creates the raw-data encoder with the shared native contract schema.
     */
    public function __construct(private ContractWireEncoder $contracts = new ContractWireEncoder())
    {
    }

    /**
     * Encodes the exact protobuf Transaction.raw payload expected for an intent.
     *
     * @param array<string, mixed> $rawData Node-provided structured raw_data.
     */
    public function encode(TransactionIntent $intent, array $rawData): string
    {
        $unknownFields = array_diff(array_keys($rawData), self::RAW_DATA_FIELDS);
        if ($unknownFields !== []) {
            throw new TransactionException(
                'The transaction raw_data contains unsupported fields: ' . implode(', ', $unknownFields),
            );
        }

        $contracts = $rawData['contract'] ?? null;
        if (!is_array($contracts) || !array_is_list($contracts) || count($contracts) !== 1) {
            throw new TransactionException('Transaction.raw integrity verification requires exactly one contract.');
        }
        if (($rawData['auths'] ?? []) !== []) {
            throw new TransactionException('Transaction.raw authority extensions are not accepted for local signing.');
        }
        if (isset($rawData['scripts']) && $rawData['scripts'] !== '') {
            throw new TransactionException('Transaction.raw scripts are not accepted for local signing.');
        }

        $bytes = '';
        $bytes .= $this->hexField($rawData, 'ref_block_bytes', 1, 2);
        $bytes .= $this->integerField($rawData, 'ref_block_num', 3);
        $bytes .= $this->hexField($rawData, 'ref_block_hash', 4, 8);
        $bytes .= $this->integerField($rawData, 'expiration', 8, required: true);
        $bytes .= $this->hexField($rawData, 'data', 10);
        $bytes .= Protobuf::messageField(11, $this->contract($intent));
        $bytes .= $this->integerField($rawData, 'timestamp', 14, required: true);
        $bytes .= $this->integerField($rawData, 'fee_limit', 18);

        return $bytes;
    }

    /**
     * Encodes the single Transaction.Contract and its google.protobuf.Any value.
     */
    private function contract(TransactionIntent $intent): string
    {
        $type = ContractType::fromProtocolName($intent->contractType);
        $payload = $this->contracts->encode(
            $intent->contractType,
            $intent->ownerAddress,
            $intent->contractFields(),
        );
        $typeUrl = 'type.googleapis.com/protocol.' . $intent->contractType;
        $any = Protobuf::bytesField(1, $typeUrl)
            . Protobuf::bytesField(2, $payload);

        return Protobuf::integerField(1, $type->value)
            . Protobuf::messageField(2, $any)
            . Protobuf::integerField(5, $intent->permissionId);
    }

    /**
     * Encodes one optional hexadecimal raw-data field.
     *
     * @param array<string, mixed> $rawData Structured raw_data.
     */
    private function hexField(
        array $rawData,
        string $name,
        int $fieldNumber,
        ?int $expectedBytes = null,
    ): string {
        if (!array_key_exists($name, $rawData)) {
            return '';
        }

        $value = $rawData[$name];
        if (!is_string($value)) {
            throw new TransactionException(sprintf('Transaction.raw `%s` must be hexadecimal text.', $name));
        }

        $bytes = $value === '' ? '' : Hex::toBytes($value, $expectedBytes);

        return Protobuf::bytesField($fieldNumber, $bytes);
    }

    /**
     * Encodes one optional or required raw-data signed int64 field.
     *
     * @param array<string, mixed> $rawData Structured raw_data.
     */
    private function integerField(
        array $rawData,
        string $name,
        int $fieldNumber,
        bool $required = false,
    ): string {
        if (!array_key_exists($name, $rawData)) {
            if ($required) {
                throw new TransactionException(sprintf('Transaction.raw is missing `%s`.', $name));
            }

            return '';
        }

        $value = $rawData[$name];
        if (!is_int($value) && (!is_string($value) || preg_match('/^-?(?:0|[1-9][0-9]*)$/D', $value) !== 1)) {
            throw new TransactionException(sprintf('Transaction.raw `%s` must be a canonical integer.', $name));
        }

        return Protobuf::integerField($fieldNumber, $value);
    }
}
