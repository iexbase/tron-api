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

namespace IEXBase\TronAPI\JsonRpc;

use IEXBase\TronAPI\Exception\ValidationException;
use IEXBase\TronAPI\Value\Address;

/**
 * Builds a strict Ethereum-compatible log filter using 20-byte TRON addresses.
 */
final readonly class LogFilter
{
    private const MAX_ADDRESSES = 1_000;
    private const MAX_TOPIC_ALTERNATIVES = 1_000;
    private const MAX_TOPIC_POSITIONS = 4;

    /** @var list<Address> */
    private array $addresses;

    /** @var list<string|null|list<string>> */
    private array $topics;

    /**
     * Validates block range, address list, and 32-byte topic alternatives.
     *
     * @param array<mixed> $addresses Contract addresses to validate.
     * @param array<mixed> $topics Positional topics or OR alternatives to validate.
     */
    public function __construct(
        public BlockTag|Quantity|null $fromBlock = null,
        public BlockTag|Quantity|null $toBlock = null,
        array $addresses = [],
        array $topics = [],
        public ?string $blockHash = null,
    ) {
        self::validateBlockSelection($fromBlock, $toBlock, $blockHash);
        $this->addresses = self::addressList($addresses);
        $this->topics = self::topicList($topics);
    }

    /**
     * Returns the JSON-RPC filter object with canonical EVM address/topic formats.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $filter = [];
        if ($this->fromBlock !== null) {
            $filter['fromBlock'] = JsonRpcParameter::block($this->fromBlock);
        }
        if ($this->toBlock !== null) {
            $filter['toBlock'] = JsonRpcParameter::block($this->toBlock);
        }
        if ($this->addresses !== []) {
            $values = array_map(
                static fn (Address $address): string => $address->toEvmHex(),
                $this->addresses,
            );
            $filter['address'] = count($values) === 1 ? $values[0] : $values;
        }
        if ($this->topics !== []) {
            $filter['topics'] = $this->topics;
        }
        if ($this->blockHash !== null) {
            $filter['blockHash'] = JsonRpcParameter::hash32($this->blockHash);
        }

        return $filter;
    }

    /**
     * Requires the narrower block selectors accepted by eth_newFilter.
     */
    public function assertStatefulCompatibility(): void
    {
        if ($this->blockHash !== null) {
            throw new ValidationException('TRON eth_newFilter does not accept a blockHash selector.');
        }

        foreach ([$this->fromBlock, $this->toBlock] as $block) {
            if ($block instanceof BlockTag && $block !== BlockTag::Latest) {
                throw new ValidationException('TRON eth_newFilter supports only the latest symbolic block tag.');
            }
        }
    }

    /**
     * Validates mutually exclusive and ordered block selectors.
     */
    private static function validateBlockSelection(
        BlockTag|Quantity|null $fromBlock,
        BlockTag|Quantity|null $toBlock,
        ?string $blockHash,
    ): void {
        if ($blockHash !== null && ($fromBlock !== null || $toBlock !== null)) {
            throw new ValidationException('A log filter cannot combine blockHash with fromBlock or toBlock.');
        }
        if ($blockHash !== null) {
            JsonRpcParameter::hash32($blockHash);
        }
        if ($fromBlock instanceof Quantity
            && $toBlock instanceof Quantity
            && gmp_cmp($fromBlock->decimal(), $toBlock->decimal()) > 0
        ) {
            throw new ValidationException('A log filter fromBlock cannot exceed toBlock.');
        }
    }

    /**
     * Returns a unique validated address list within java-tron's safety limit.
     *
     * @param array<mixed> $addresses Untrusted address collection.
     * @return list<Address>
     */
    private static function addressList(array $addresses): array
    {
        if (!array_is_list($addresses) || count($addresses) > self::MAX_ADDRESSES) {
            throw new ValidationException('A JSON-RPC filter address list is invalid or exceeds 1000 entries.');
        }

        $checkedAddresses = [];
        $seenAddresses = [];
        foreach ($addresses as $address) {
            if (!$address instanceof Address) {
                throw new ValidationException('Every JSON-RPC filter address must be an Address.');
            }
            $identity = $address->toEvmHex(false);
            if (isset($seenAddresses[$identity])) {
                throw new ValidationException('A JSON-RPC filter cannot repeat the same address.');
            }
            $seenAddresses[$identity] = true;
            $checkedAddresses[] = $address;
        }

        return $checkedAddresses;
    }

    /**
     * Returns validated positional topics and OR alternatives.
     *
     * @param array<mixed> $topics Untrusted topic collection.
     * @return list<string|null|list<string>>
     */
    private static function topicList(array $topics): array
    {
        if (!array_is_list($topics) || count($topics) > self::MAX_TOPIC_POSITIONS) {
            throw new ValidationException('A JSON-RPC filter supports at most four ordered topic positions.');
        }

        $checkedTopics = [];
        foreach ($topics as $topic) {
            if ($topic === null) {
                $checkedTopics[] = null;
                continue;
            }
            if (is_string($topic)) {
                $checkedTopics[] = JsonRpcParameter::hash32($topic);
                continue;
            }
            $checkedTopics[] = self::topicAlternatives($topic);
        }

        return $checkedTopics;
    }

    /**
     * Returns one unique non-empty list of canonical topic alternatives.
     *
     * @return list<string>
     */
    private static function topicAlternatives(mixed $topic): array
    {
        if (!is_array($topic)
            || !array_is_list($topic)
            || $topic === []
            || count($topic) > self::MAX_TOPIC_ALTERNATIVES
        ) {
            throw new ValidationException('A JSON-RPC topic alternative list is invalid or exceeds 1000 entries.');
        }

        $alternatives = [];
        $seenAlternatives = [];
        foreach ($topic as $value) {
            if (!is_string($value)) {
                throw new ValidationException('Every JSON-RPC topic alternative must be a 32-byte hash.');
            }
            $canonical = JsonRpcParameter::hash32($value);
            if (isset($seenAlternatives[$canonical])) {
                throw new ValidationException('A JSON-RPC topic position cannot repeat an alternative.');
            }
            $seenAlternatives[$canonical] = true;
            $alternatives[] = $canonical;
        }

        return $alternatives;
    }
}
