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

use IEXBase\TronAPI\Exception\ContractException;
use JsonSerializable;
use kornrunner\Keccak;

/**
 * Represents one function, constructor, event, error, fallback, or receive ABI entry.
 */
final readonly class AbiEntry implements JsonSerializable
{
    private const TYPES = ['constructor', 'error', 'event', 'fallback', 'function', 'receive'];
    private const MUTABILITIES = ['nonpayable', 'payable', 'pure', 'view'];

    /** @var list<AbiParameter> */
    private array $inputs;

    /** @var list<AbiParameter> */
    private array $outputs;

    /**
     * Validates and stores a complete ABI entry.
     *
     * @param string             $type ABI entry category.
     * @param string             $name Function, event, or error name.
     * @param list<AbiParameter> $inputs Ordered input parameters.
     * @param list<AbiParameter> $outputs Ordered output parameters.
     * @param string             $stateMutability Solidity state mutability.
     * @param bool               $anonymous Whether an event omits its signature topic.
     */
    public function __construct(
        public string $type,
        public string $name,
        array $inputs,
        array $outputs,
        public string $stateMutability,
        public bool $anonymous = false,
    ) {
        if (!in_array($type, self::TYPES, true)) {
            throw new ContractException(sprintf('Unsupported ABI entry type `%s`.', $type));
        }

        if (!in_array($stateMutability, self::MUTABILITIES, true)) {
            throw new ContractException(sprintf('Unsupported ABI state mutability `%s`.', $stateMutability));
        }

        if (in_array($type, ['function', 'event', 'error'], true) && $name === '') {
            throw new ContractException(sprintf('An ABI %s entry requires a name.', $type));
        }

        $this->inputs = $inputs;
        $this->outputs = $outputs;
    }

    /**
     * Hydrates one entry from decoded contract ABI JSON.
     *
     * @param array<string, mixed> $data ABI entry object.
     */
    public static function fromArray(array $data): self
    {
        $type = isset($data['type']) && is_string($data['type'])
            ? strtolower($data['type'])
            : 'function';
        $name = isset($data['name']) && is_string($data['name']) ? $data['name'] : '';
        $inputs = self::parseParameters($data['inputs'] ?? []);
        $outputs = self::parseParameters($data['outputs'] ?? []);

        $stateMutability = $data['stateMutability'] ?? $data['state_mutability'] ?? null;
        if (!is_string($stateMutability)) {
            $stateMutability = ($data['constant'] ?? false) === true
                ? 'view'
                : (($data['payable'] ?? false) === true ? 'payable' : 'nonpayable');
        } else {
            $stateMutability = strtolower($stateMutability);
        }

        $anonymous = $data['anonymous'] ?? false;
        if (!is_bool($anonymous)) {
            throw new ContractException('An ABI event anonymous flag must be boolean.');
        }

        return new self($type, $name, $inputs, $outputs, $stateMutability, $anonymous);
    }

    /**
     * Returns ordered input parameters.
     *
     * @return list<AbiParameter>
     */
    public function inputs(): array
    {
        return $this->inputs;
    }

    /**
     * Returns ordered output parameters.
     *
     * @return list<AbiParameter>
     */
    public function outputs(): array
    {
        return $this->outputs;
    }

    /**
     * Returns the canonical name and expanded input types used for hashing.
     */
    public function signature(): string
    {
        if ($this->name === '') {
            throw new ContractException(sprintf('ABI entry type `%s` does not have a signature name.', $this->type));
        }

        return $this->name . '(' . implode(',', array_map(
            static fn (AbiParameter $parameter): string => $parameter->canonicalType(),
            $this->inputs,
        )) . ')';
    }

    /**
     * Returns the first four Keccak-256 bytes used as a function selector.
     */
    public function selector(): string
    {
        if ($this->type !== 'function' && $this->type !== 'error') {
            throw new ContractException('Only function and error entries have four-byte selectors.');
        }

        return substr(Keccak::hash($this->signature(), 256), 0, 8);
    }

    /**
     * Returns the complete Keccak-256 event signature topic.
     */
    public function eventTopic(): string
    {
        if ($this->type !== 'event') {
            throw new ContractException('Only event entries have event signature topics.');
        }

        return Keccak::hash($this->signature(), 256);
    }

    /**
     * Returns whether the entry can execute without changing chain state.
     */
    public function isReadOnly(): bool
    {
        return in_array($this->stateMutability, ['pure', 'view'], true);
    }

    /**
     * Returns whether the entry accepts a native TRX call value.
     */
    public function isPayable(): bool
    {
        return $this->stateMutability === 'payable';
    }

    /**
     * Serializes this entry in standard Solidity ABI JSON shape.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = [
            'type' => $this->type,
            'stateMutability' => $this->stateMutability,
        ];
        if ($this->name !== '') {
            $data['name'] = $this->name;
        }
        if ($this->inputs !== []) {
            $data['inputs'] = $this->inputs;
        }
        if ($this->outputs !== []) {
            $data['outputs'] = $this->outputs;
        }
        if ($this->type === 'event') {
            $data['anonymous'] = $this->anonymous;
        }

        return $data;
    }

    /**
     * Parses a list of untrusted ABI parameter records.
     *
     * @return list<AbiParameter>
     */
    private static function parseParameters(mixed $data): array
    {
        if (!is_array($data) || !array_is_list($data)) {
            throw new ContractException('ABI entry parameters must be a list.');
        }

        $parameters = [];
        foreach ($data as $parameter) {
            if (!is_array($parameter)) {
                throw new ContractException('Every ABI parameter must be an object.');
            }
            $record = [];
            foreach ($parameter as $key => $value) {
                if (!is_string($key)) {
                    throw new ContractException('An ABI parameter object must use string field names.');
                }
                $record[$key] = $value;
            }
            $parameters[] = AbiParameter::fromArray($record);
        }

        return $parameters;
    }
}
