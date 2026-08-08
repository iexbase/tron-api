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

/**
 * Represents one named Solidity ABI input, output, or event parameter.
 */
final readonly class AbiParameter implements JsonSerializable
{
    /** @var list<self> */
    private array $components;

    private AbiType $descriptor;

    /**
     * Validates a parameter and its recursively declared tuple components.
     *
     * @param string       $name Parameter name, which may be empty in valid ABI.
     * @param string       $type Canonical ABI type expression.
     * @param array<mixed> $components Tuple component definitions to validate.
     * @param bool         $indexed Whether an event parameter is indexed.
     */
    public function __construct(
        public string $name,
        public string $type,
        array $components = [],
        public bool $indexed = false,
    ) {
        if (!array_is_list($components)) {
            throw new ContractException('ABI tuple components must be a list.');
        }

        if ($type === '' || preg_match('/\s/', $type) === 1) {
            throw new ContractException('An ABI parameter requires a non-empty type without whitespace.');
        }

        if (!str_starts_with($type, 'tuple') && $components !== []) {
            throw new ContractException('Only tuple ABI parameters may declare components.');
        }

        $checkedComponents = [];
        foreach ($components as $component) {
            if (!$component instanceof self) {
                throw new ContractException('Every ABI tuple component must be an AbiParameter.');
            }
            if ($component->indexed) {
                throw new ContractException('Nested ABI tuple components cannot be indexed.');
            }
            $checkedComponents[] = $component;
        }

        $this->components = $checkedComponents;
        $this->descriptor = AbiType::fromParameter($this);
        if (!$this->descriptor->isDynamic()) {
            $this->descriptor->staticByteLength();
        }
    }

    /**
     * Hydrates one parameter from decoded contract ABI JSON.
     *
     * @param array<string, mixed> $data ABI parameter object.
     */
    public static function fromArray(array $data): self
    {
        $name = isset($data['name']) && is_string($data['name']) ? $data['name'] : '';
        $type = $data['type'] ?? null;
        if (!is_string($type)) {
            throw new ContractException('An ABI parameter is missing its type.');
        }

        $componentData = $data['components'] ?? [];
        if (!is_array($componentData) || !array_is_list($componentData)) {
            throw new ContractException('ABI tuple components must be a list.');
        }

        $components = [];
        foreach ($componentData as $component) {
            if (!is_array($component)) {
                throw new ContractException('Every ABI tuple component must be an object.');
            }
            $components[] = self::fromArray(self::stringKeyedArray($component));
        }

        $indexed = $data['indexed'] ?? false;
        if (!is_bool($indexed)) {
            throw new ContractException('An ABI event indexed flag must be boolean.');
        }

        return new self($name, $type, $components, $indexed);
    }

    /**
     * Returns tuple component declarations in source order.
     *
     * @return list<self>
     */
    public function components(): array
    {
        return $this->components;
    }

    /**
     * Returns the validated recursive descriptor used by the ABI codec.
     */
    public function typeDescriptor(): AbiType
    {
        return $this->descriptor;
    }

    /**
     * Returns the canonical signature type, expanding tuple component types.
     */
    public function canonicalType(): string
    {
        return $this->descriptor->canonicalType();
    }

    /**
     * Returns fields represented by java-tron's on-chain ABI protobuf.
     *
     * Tuple components are intentionally absent because the TRON protocol ABI
     * parameter message stores only indexed, name, and type.
     *
     * @return array{indexed: bool, name: string, type: string}
     */
    public function protocolFields(): array
    {
        return [
            'indexed' => $this->indexed,
            'name' => $this->name,
            'type' => $this->descriptor->jsonType(),
        ];
    }

    /**
     * Returns one standard ABI JSON parameter record.
     *
     * @return array<string, mixed>
     */
    public function toArray(bool $includeIndexed = false): array
    {
        $data = [
            'name' => $this->name,
            'type' => $this->descriptor->jsonType(),
        ];
        if (str_starts_with($this->type, 'tuple')) {
            $data['components'] = $this->components;
        }
        if ($includeIndexed) {
            $data['indexed'] = $this->indexed;
        }

        return $data;
    }

    /**
     * Serializes this parameter in standard Solidity ABI JSON shape.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Copies an untrusted decoded object into a string-keyed ABI record.
     *
     * @param array<mixed> $data Decoded JSON object.
     * @return array<string, mixed>
     */
    private static function stringKeyedArray(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (!is_string($key)) {
                throw new ContractException('An ABI object must use string field names.');
            }
            $result[$key] = $value;
        }

        return $result;
    }
}
