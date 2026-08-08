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

use IEXBase\TronAPI\Api\DataDecoder;
use IEXBase\TronAPI\Encoding\Hex;
use IEXBase\TronAPI\Exception\ContractException;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\ByteString;
use JsonSerializable;

/**
 * Represents one TVM event log with optional ABI-resolved decoded values.
 */
final readonly class EventLog implements JsonSerializable
{
    /** @var list<string> */
    private array $topics;

    /** @var array<string, mixed> */
    private array $rawData;

    /**
     * Stores the emitting address, exact topics/data, ABI entry, and decoded values.
     *
     * @param list<string>        $topics Canonical 32-byte topics without 0x prefixes.
     * @param array<string, mixed> $rawData Complete native log object.
     */
    private function __construct(
        public Address $contractAddress,
        array $topics,
        public ByteString $data,
        public ?AbiEntry $event,
        public ?DecodedValues $values,
        array $rawData,
    ) {
        $this->topics = $topics;
        $this->rawData = $rawData;
    }

    /**
     * Parses and decodes a native receipt log against one complete contract ABI.
     *
     * An explicit signature is required for anonymous events because they omit
     * the identifying signature topic. Unknown non-anonymous topics are safely
     * preserved with null event and values instead of discarding the log.
     *
     * @param array<string, mixed> $data Native transaction-info log object.
     */
    public static function fromNodeData(
        array $data,
        Abi $abi,
        AbiCodec $codec,
        ?string $eventSignature = null,
    ): self {
        $addressText = DataDecoder::string($data['address'] ?? null, 'log.address');
        $addressHex = Hex::canonicalize($addressText);
        $address = strlen($addressHex) === Address::EVM_BYTES * 2
            ? Address::fromEvmHex($addressHex)
            : Address::fromHex($addressHex);

        $topicValues = $data['topics'] ?? [];
        if (!is_array($topicValues) || !array_is_list($topicValues)) {
            throw new ContractException('A native event log topics field must be a list.');
        }
        $topics = array_map(
            static fn (mixed $topic): string => Hex::canonicalize(
                DataDecoder::string($topic, 'log.topics[]'),
                32,
            ),
            $topicValues,
        );
        $encodedData = DataDecoder::string($data['data'] ?? '', 'log.data');
        $event = $eventSignature === null
            ? (isset($topics[0]) ? $abi->eventByTopic($topics[0]) : null)
            : $abi->event($eventSignature);

        if ($event !== null && $eventSignature !== null && $event->anonymous === false
            && (!isset($topics[0]) || !hash_equals($event->eventTopic(), $topics[0]))
        ) {
            throw new ContractException('The selected event signature does not match the native log topic.');
        }

        return new self(
            $address,
            $topics,
            ByteString::fromHex($encodedData),
            $event,
            $event === null ? null : $codec->decodeEvent($event, $topics, $encodedData),
            $data,
        );
    }

    /**
     * Returns every canonical event topic in receipt order.
     *
     * @return list<string>
     */
    public function topics(): array
    {
        return $this->topics;
    }

    /**
     * Returns whether the ABI recognized and decoded this log.
     */
    public function isDecoded(): bool
    {
        return $this->event !== null && $this->values !== null;
    }

    /**
     * Returns the untouched native log object.
     *
     * @return array<string, mixed>
     */
    public function rawData(): array
    {
        return $this->rawData;
    }

    /**
     * Serializes the original lossless native log object.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->rawData;
    }
}
