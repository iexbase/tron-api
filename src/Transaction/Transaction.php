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

use DateTimeImmutable;
use IEXBase\TronAPI\Exception\TransactionException;
use IEXBase\TronAPI\Encoding\Hex;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\Signature;
use JsonSerializable;

/**
 * Represents a verified node-built transaction with zero or more signatures.
 *
 * Construction always checks `txID === SHA-256(raw_data_hex bytes)`. Signatures
 * can be appended for multi-signature flows without changing the signed bytes.
 */
final readonly class Transaction implements JsonSerializable
{
    /** @var array<string, mixed> */
    private array $data;

    /** @var list<Signature> */
    private array $signatures;

    private string $txId;
    private string $rawDataHexValue;

    private ?TransactionIntent $approvedIntent;

    /** @var array<string, mixed> */
    private array $rawDataValue;

    /**
     * Stores a transaction only after its hash and signatures are validated.
     *
     * @param array<string, mixed> $data Canonical transaction array.
     * @param list<Signature>      $signatures Parsed signatures.
     * @param string               $txId Verified transaction identifier.
     * @param string               $rawDataHex Exact protobuf bytes in hex.
     * @param array<string, mixed> $rawData Structured transaction body.
     * @param TransactionIntent|null $approvedIntent Locally verified operation intent.
     */
    private function __construct(
        array $data,
        array $signatures,
        string $txId,
        string $rawDataHex,
        array $rawData,
        ?TransactionIntent $approvedIntent = null,
    ) {
        $this->data = $data;
        $this->signatures = $signatures;
        $this->txId = $txId;
        $this->rawDataHexValue = $rawDataHex;
        $this->rawDataValue = $rawData;
        $this->approvedIntent = $approvedIntent;
    }

    /**
     * Validates a transaction object returned by a constructor endpoint.
     *
     * @param array<string, mixed> $transaction Native TRON transaction object.
     */
    public static function fromNodeData(array $transaction): self
    {
        $txIdValue = $transaction['txID'] ?? null;
        $rawDataHexValue = $transaction['raw_data_hex'] ?? null;
        $rawData = $transaction['raw_data'] ?? null;

        if (!is_string($txIdValue) || !is_string($rawDataHexValue) || !is_array($rawData)) {
            throw new TransactionException('The node transaction must contain txID, raw_data_hex, and raw_data.');
        }

        $txId = Hex::canonicalize($txIdValue, 32);
        $rawDataHex = Hex::canonicalize($rawDataHexValue);
        $calculatedTxId = hash('sha256', Hex::toBytes($rawDataHex));

        if (!hash_equals($calculatedTxId, $txId)) {
            throw new TransactionException('The transaction txID does not match SHA-256(raw_data_hex).');
        }

        $rawData = self::stringKeyedArray($rawData, 'The transaction raw_data must use string field names.');
        $contracts = $rawData['contract'] ?? null;
        if (!is_array($contracts) || !array_is_list($contracts) || $contracts === []) {
            throw new TransactionException('The transaction raw_data must contain at least one contract.');
        }

        $signatureValues = $transaction['signature'] ?? [];
        if (!is_array($signatureValues) || !array_is_list($signatureValues)) {
            throw new TransactionException('The transaction signature field must be a list.');
        }

        $signatures = [];
        foreach ($signatureValues as $signatureValue) {
            if (!is_string($signatureValue)) {
                throw new TransactionException('Every transaction signature must be hexadecimal text.');
            }
            $signatures[] = Signature::fromHex($signatureValue);
        }

        $transaction['txID'] = $txId;
        $transaction['raw_data_hex'] = $rawDataHex;
        if ($signatures !== []) {
            $transaction['signature'] = array_map(
                static fn (Signature $signature): string => $signature->toHex(),
                $signatures,
            );
        }

        $instance = new self($transaction, $signatures, $txId, $rawDataHex, $rawData);
        $instance->assertUniqueSigners();

        return $instance;
    }

    /**
     * Returns the cryptographic transaction identifier and signing digest.
     */
    public function id(): string
    {
        return $this->txId;
    }

    /**
     * Returns the exact protobuf-serialized raw_data as hexadecimal text.
     */
    public function rawDataHex(): string
    {
        return $this->rawDataHexValue;
    }

    /**
     * Returns the structured raw_data supplied by the node.
     *
     * @return array<string, mixed>
     */
    public function rawData(): array
    {
        return $this->rawDataValue;
    }

    /**
     * Verifies and attaches the user-approved operation intent without changing signed bytes.
     */
    public function approveIntent(
        TransactionIntent $intent,
        TransactionVerifier $verifier = new TransactionVerifier(),
    ): self {
        $verifier->verify($this, $intent);

        return new self(
            $this->data,
            $this->signatures,
            $this->txId,
            $this->rawDataHexValue,
            $this->rawDataValue,
            $intent,
        );
    }

    /**
     * Returns the locally verified operation intent required for safe signing.
     */
    public function approvedIntent(): TransactionIntent
    {
        return $this->approvedIntent
            ?? throw new TransactionException('The transaction has no locally approved intent.');
    }

    /**
     * Returns the transaction's only contract or rejects hidden extra contracts.
     *
     * @return array<string, mixed>
     */
    public function singleContract(): array
    {
        $contracts = $this->rawData()['contract'] ?? null;
        if (!is_array($contracts) || count($contracts) !== 1 || !is_array($contracts[0])) {
            throw new TransactionException('A securely verified operation must contain exactly one contract.');
        }

        return self::stringKeyedArray($contracts[0], 'The transaction contract must use string field names.');
    }

    /**
     * Returns the collected immutable signatures.
     *
     * @return list<Signature>
     */
    public function signatures(): array
    {
        return $this->signatures;
    }

    /**
     * Returns whether at least one signature has been collected.
     */
    public function isSigned(): bool
    {
        return $this->signatures !== [];
    }

    /**
     * Returns whether the transaction has expired relative to a supplied time.
     */
    public function isExpired(?DateTimeImmutable $now = null): bool
    {
        $expiration = $this->rawData()['expiration'] ?? null;
        if (!is_int($expiration)) {
            throw new TransactionException('The transaction does not contain a valid expiration timestamp.');
        }

        $currentMilliseconds = (int) ($now ?? new DateTimeImmutable())->format('Uv');

        return $expiration <= $currentMilliseconds;
    }

    /**
     * Appends one non-duplicate signature without modifying raw_data or txID.
     */
    public function appendSignature(Signature $signature): self
    {
        $newAddress = $signature->recoverAddress($this->id());
        foreach ($this->signatures as $existing) {
            if ($existing->recoverAddress($this->id())->equals($newAddress)) {
                throw new TransactionException('The same permission key cannot sign a transaction twice.');
            }
        }

        $signatures = [...$this->signatures, $signature];
        $data = $this->data;
        $data['signature'] = array_map(
            static fn (Signature $item): string => $item->toHex(),
            $signatures,
        );

        return new self(
            $data,
            $signatures,
            $this->txId,
            $this->rawDataHexValue,
            $this->rawDataValue,
            $this->approvedIntent,
        );
    }

    /**
     * Returns addresses recovered from all collected signatures.
     *
     * @return list<Address>
     */
    public function signerAddresses(): array
    {
        return array_map(
            fn (Signature $signature): Address => $signature->recoverAddress($this->id()),
            $this->signatures,
        );
    }

    /**
     * Returns the complete native transaction payload for node endpoints.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Serializes the complete transaction payload without changing key order.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->data;
    }

    /**
     * Rejects duplicate signers in externally supplied partial transactions.
     */
    private function assertUniqueSigners(): void
    {
        $addresses = [];
        foreach ($this->signerAddresses() as $address) {
            $base58 = $address->toBase58();
            if (isset($addresses[$base58])) {
                throw new TransactionException('The transaction already contains a duplicate signer.');
            }
            $addresses[$base58] = true;
        }
    }

    /**
     * Validates an API object and returns it with a precise string-key type.
     *
     * @param array<mixed> $data Decoded JSON object.
     * @return array<string, mixed>
     */
    private static function stringKeyedArray(array $data, string $errorMessage): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (!is_string($key)) {
                throw new TransactionException($errorMessage);
            }
            $result[$key] = $value;
        }

        return $result;
    }
}
