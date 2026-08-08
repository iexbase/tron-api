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

use IEXBase\TronAPI\Api\DataDecoder;
use IEXBase\TronAPI\Encoding\Hex;
use IEXBase\TronAPI\Exception\TransactionException;
use JsonSerializable;

/**
 * Represents an included transaction and its resource/execution result.
 */
final readonly class TransactionReceipt implements JsonSerializable
{
    /** @var array<string, mixed> */
    private array $rawData;

    /**
     * Stores lossless fee/resource counters and the TVM result if present.
     *
     * @param array<string, mixed> $rawData Complete transaction-info response.
     */
    private function __construct(
        public string $transactionId,
        public int $blockNumber,
        public int $blockTimestampMilliseconds,
        public string $feeSun,
        public string $energyUsage,
        public string $energyPenalty,
        public ?string $executionResult,
        public ?string $executionMessage,
        array $rawData,
    ) {
        $this->rawData = $rawData;
    }

    /**
     * Creates a receipt from FullNode or confirmed SolidityNode transaction info.
     *
     * @param array<string, mixed> $data Native transaction-info response.
     */
    public static function fromNodeData(array $data): self
    {
        $receipt = DataDecoder::object($data['receipt'] ?? [], 'receipt');
        $result = $receipt['result'] ?? $data['result'] ?? null;
        $message = self::executionMessage($data['resMessage'] ?? null);

        return new self(
            Hex::canonicalize(DataDecoder::string($data['id'] ?? null, 'id'), 32),
            DataDecoder::integer($data['blockNumber'] ?? null, 'blockNumber'),
            DataDecoder::integer($data['blockTimeStamp'] ?? null, 'blockTimeStamp'),
            DataDecoder::unsignedDecimal($data['fee'] ?? 0, 'fee'),
            DataDecoder::unsignedDecimal($receipt['energy_usage_total'] ?? 0, 'receipt.energy_usage_total'),
            DataDecoder::unsignedDecimal($receipt['energy_penalty_total'] ?? 0, 'receipt.energy_penalty_total'),
            $result === null ? null : DataDecoder::string($result, 'receipt.result'),
            $message,
            $data,
        );
    }

    /**
     * Returns true only when the receipt explicitly reports successful execution.
     */
    public function isSuccessful(): bool
    {
        return $this->executionResult !== null
            && strtoupper($this->executionResult) === 'SUCCESS';
    }

    /**
     * Rejects missing or failed execution results for contract workflows.
     */
    public function requireSuccessfulExecution(): self
    {
        if (!$this->isSuccessful()) {
            $details = $this->executionMessage ?? $this->executionResult ?? 'missing execution result';
            throw new TransactionException(sprintf(
                'Transaction `%s` did not execute successfully: %s.',
                $this->transactionId,
                $details,
            ));
        }

        return $this;
    }

    /**
     * Returns the untouched transaction-info response.
     *
     * @return array<string, mixed>
     */
    public function rawData(): array
    {
        return $this->rawData;
    }

    /**
     * Serializes the original lossless transaction-info response.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->rawData;
    }

    /**
     * Decodes a valid UTF-8 hex execution message while preserving plain text.
     */
    private static function executionMessage(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $message = DataDecoder::string($value, 'resMessage');
        if ($message !== '' && strlen($message) % 2 === 0 && ctype_xdigit($message)) {
            $bytes = hex2bin($message);
            if ($bytes !== false && preg_match('//u', $bytes) === 1) {
                return $bytes;
            }
        }

        return $message;
    }
}
