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
    /** @var list<Address> */
    private array $addresses;

    /** @var list<string|null|list<string>> */
    private array $topics;

    /**
     * Validates block range, address list, and 32-byte topic alternatives.
     *
     * @param list<Address>                  $addresses Contract addresses.
     * @param list<string|null|list<string>> $topics Positional topics or OR alternatives.
     */
    public function __construct(
        public BlockTag|Quantity|null $fromBlock = null,
        public BlockTag|Quantity|null $toBlock = null,
        array $addresses = [],
        array $topics = [],
        public ?string $blockHash = null,
    ) {
        if ($blockHash !== null && ($fromBlock !== null || $toBlock !== null)) {
            throw new ValidationException('A log filter cannot combine blockHash with fromBlock or toBlock.');
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
            if ($topic === []) {
                throw new ValidationException('A JSON-RPC topic alternative must be a non-empty list.');
            }
            $checkedTopics[] = array_map(
                static fn (string $value): string => JsonRpcParameter::hash32($value),
                $topic,
            );
        }

        $this->addresses = $addresses;
        $this->topics = $checkedTopics;
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

}
