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

    /**
     * Validates a parameter and its recursively declared tuple components.
     *
     * @param string     $name Parameter name, which may be empty in valid ABI.
     * @param string     $type Canonical ABI type expression.
     * @param list<self> $components Tuple component definitions.
     * @param bool       $indexed Whether an event parameter is indexed.
     */
    public function __construct(
        public string $name,
        public string $type,
        array $components = [],
        public bool $indexed = false,
    ) {
        if ($type === '' || preg_match('/\s/', $type) === 1) {
            throw new ContractException('An ABI parameter requires a non-empty type without whitespace.');
        }

        if (!str_starts_with($type, 'tuple') && $components !== []) {
            throw new ContractException('Only tuple ABI parameters may declare components.');
        }

        $this->components = $components;
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
     * Returns the canonical signature type, expanding tuple component types.
     */
    public function canonicalType(): string
    {
        if (!str_starts_with($this->type, 'tuple')) {
            return $this->type;
        }

        $suffix = substr($this->type, strlen('tuple'));
        $types = array_map(
            static fn (self $component): string => $component->canonicalType(),
            $this->components,
        );

        return '(' . implode(',', $types) . ')' . $suffix;
    }

    /**
     * Serializes this parameter in standard Solidity ABI JSON shape.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = ['name' => $this->name, 'type' => $this->type];
        if ($this->components !== []) {
            $data['components'] = $this->components;
        }
        if ($this->indexed) {
            $data['indexed'] = true;
        }

        return $data;
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
