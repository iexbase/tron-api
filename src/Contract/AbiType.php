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

/**
 * Parses and describes one recursive Solidity ABI type expression.
 */
final readonly class AbiType
{
    /** Maximum values accepted in one array or tuple descriptor. */
    public const MAX_ARRAY_ELEMENTS = 100_000;

    /** Maximum encoded ABI payload accepted for one operation. */
    public const MAX_ENCODED_BYTES = 16_777_216;

    private const MAX_NESTING_DEPTH = 32;

    /** @var list<self> */
    private array $components;

    /** @var list<string> */
    private array $componentNames;

    private bool $dynamic;
    private ?int $staticBytes;

    /**
     * Stores a validated elementary, tuple, or array ABI type.
     *
     * @param string       $kind Elementary kind, tuple, or array.
     * @param int|null     $size Integer bits, bytesN size, or fixed M bits.
     * @param int|null     $precision Fixed/ufixed decimal precision.
     * @param self|null    $elementType Array element type.
     * @param int|null     $arrayLength Null for a dynamic array.
     * @param list<self>   $components Tuple component types.
     * @param list<string> $componentNames Tuple component names.
     */
    private function __construct(
        public string $kind,
        public ?int $size = null,
        public ?int $precision = null,
        public ?self $elementType = null,
        public ?int $arrayLength = null,
        array $components = [],
        array $componentNames = [],
    ) {
        $this->components = $components;
        $this->componentNames = $componentNames;
        $this->dynamic = match ($kind) {
            'string', 'bytes' => true,
            'array' => $arrayLength === null
                || ($elementType ?? throw new ContractException('An ABI array element type is missing.'))->isDynamic(),
            'tuple' => array_any($components, static fn (self $component): bool => $component->isDynamic()),
            default => false,
        };
        $this->staticBytes = $this->dynamic
            ? null
            : match ($kind) {
                'array' => self::checkedProduct(
                    $arrayLength ?? throw new ContractException('A fixed ABI array length is missing.'),
                    ($elementType ?? throw new ContractException('An ABI array element type is missing.'))
                        ->staticByteLength(),
                ),
                'tuple' => self::tupleByteLength($components),
                default => 32,
            };
    }

    /**
     * Parses a parameter type and recursively expands tuple components.
     */
    public static function fromParameter(AbiParameter $parameter): self
    {
        return self::parse($parameter->type, $parameter->components(), 0);
    }

    /**
     * Returns whether ABI encoding stores this value in a tail section.
     */
    public function isDynamic(): bool
    {
        return $this->dynamic;
    }

    /**
     * Returns whether an indexed event value is represented by its Keccak-256 hash.
     *
     * Solidity hashes indexed values that have dynamic encoding or occupy more
     * than one ABI word, because an event topic always contains exactly 32 bytes.
     */
    public function isHashedInEventTopic(): bool
    {
        return in_array($this->kind, ['array', 'bytes', 'string', 'tuple'], true);
    }

    /**
     * Returns the encoded byte width for a type that never uses a tail section.
     */
    public function staticByteLength(): int
    {
        if ($this->staticBytes === null) {
            throw new ContractException('A dynamic ABI type does not have a static byte length.');
        }

        return $this->staticBytes;
    }

    /**
     * Returns the canonical type used in function, event, and error signatures.
     */
    public function canonicalType(): string
    {
        return match ($this->kind) {
            'array' => $this->requiredElementType()->canonicalType()
                . '[' . ($this->arrayLength === null ? '' : (string) $this->arrayLength) . ']',
            'tuple' => '(' . implode(',', array_map(
                static fn (self $component): string => $component->canonicalType(),
                $this->components,
            )) . ')',
            'int', 'uint' => $this->kind . (string) $this->requiredSize(),
            'fixed', 'ufixed' => $this->kind
                . (string) $this->requiredSize()
                . 'x'
                . (string) $this->requiredPrecision(),
            'fixed_bytes' => 'bytes' . (string) $this->requiredSize(),
            default => $this->kind,
        };
    }

    /**
     * Returns the canonical type spelling used by Solidity ABI JSON.
     */
    public function jsonType(): string
    {
        if ($this->kind === 'array') {
            return $this->requiredElementType()->jsonType()
                . '[' . ($this->arrayLength === null ? '' : (string) $this->arrayLength) . ']';
        }

        return $this->kind === 'tuple' ? 'tuple' : $this->canonicalType();
    }

    /**
     * Returns tuple component types in source order.
     *
     * @return list<self>
     */
    public function components(): array
    {
        return $this->components;
    }

    /**
     * Returns tuple component names in source order.
     *
     * @return list<string>
     */
    public function componentNames(): array
    {
        return $this->componentNames;
    }

    /**
     * Returns the element type of an array or throws for a malformed descriptor.
     */
    public function requiredElementType(): self
    {
        if ($this->kind !== 'array' || $this->elementType === null) {
            throw new ContractException('The ABI type is not a valid array.');
        }

        return $this->elementType;
    }

    /**
     * Returns a fixed array length and rejects calls for dynamic arrays.
     */
    public function requiredArrayLength(): int
    {
        if ($this->kind !== 'array' || $this->arrayLength === null) {
            throw new ContractException('The ABI type is not a fixed-length array.');
        }

        return $this->arrayLength;
    }

    /**
     * Parses arrays outside-in and validates every elementary type constraint.
     *
     * @param list<AbiParameter> $tupleComponents Tuple component declarations.
     */
    private static function parse(string $expression, array $tupleComponents, int $depth): self
    {
        if ($depth > self::MAX_NESTING_DEPTH) {
            throw new ContractException('ABI type nesting exceeds the supported safety limit.');
        }

        if (preg_match('/^(.*)\[([0-9]*)\]$/D', $expression, $matches) === 1) {
            $length = $matches[2] === '' ? null : (int) $matches[2];
            if ($length !== null && $length > self::MAX_ARRAY_ELEMENTS) {
                throw new ContractException('A fixed ABI array has an invalid or unsafe length.');
            }

            return new self(
                'array',
                elementType: self::parse($matches[1], $tupleComponents, $depth + 1),
                arrayLength: $length,
            );
        }

        if ($expression === 'tuple') {
            if (count($tupleComponents) > self::MAX_ARRAY_ELEMENTS) {
                throw new ContractException('An ABI tuple exceeds the configured component safety limit.');
            }

            $components = array_map(
                static fn (AbiParameter $component): self => self::parse(
                    $component->type,
                    $component->components(),
                    $depth + 1,
                ),
                $tupleComponents,
            );

            return new self(
                'tuple',
                components: $components,
                componentNames: array_map(
                    static fn (AbiParameter $component): string => $component->name,
                    $tupleComponents,
                ),
            );
        }

        if (preg_match('/^(u?int)([0-9]*)$/D', $expression, $matches) === 1) {
            $bits = $matches[2] === '' ? 256 : (int) $matches[2];
            self::assertIntegerBits($bits, $expression);

            return new self($matches[1], $bits);
        }

        if ($expression === 'fixed' || $expression === 'ufixed') {
            return new self($expression, 128, 18);
        }

        if (preg_match('/^(u?fixed)([0-9]+)x([0-9]+)$/D', $expression, $matches) === 1) {
            $bits = (int) $matches[2];
            $precision = (int) $matches[3];
            self::assertIntegerBits($bits, $expression);
            if ($precision < 1 || $precision > 80) {
                throw new ContractException(sprintf('ABI type `%s` has an invalid decimal precision.', $expression));
            }

            return new self($matches[1], $bits, $precision);
        }

        if (preg_match('/^bytes([0-9]+)$/D', $expression, $matches) === 1) {
            $bytes = (int) $matches[1];
            if ($bytes < 1 || $bytes > 32) {
                throw new ContractException(sprintf('ABI type `%s` has an invalid fixed-byte size.', $expression));
            }

            return new self('fixed_bytes', $bytes);
        }

        if (in_array($expression, ['address', 'bool', 'bytes', 'function', 'string'], true)) {
            return new self($expression);
        }

        throw new ContractException(sprintf('Unsupported Solidity ABI type `%s`.', $expression));
    }

    /**
     * Validates the Solidity M constraint for integer and fixed-point types.
     */
    private static function assertIntegerBits(int $bits, string $expression): void
    {
        if ($bits < 8 || $bits > 256 || $bits % 8 !== 0) {
            throw new ContractException(sprintf('ABI type `%s` requires bits from 8 to 256 in steps of 8.', $expression));
        }
    }

    /**
     * Returns a required size from a validated elementary descriptor.
     */
    private function requiredSize(): int
    {
        if ($this->size === null) {
            throw new ContractException(sprintf('ABI type `%s` is missing its size.', $this->kind));
        }

        return $this->size;
    }

    /**
     * Returns a required precision from a validated fixed-point descriptor.
     */
    private function requiredPrecision(): int
    {
        if ($this->precision === null) {
            throw new ContractException(sprintf('ABI type `%s` is missing its precision.', $this->kind));
        }

        return $this->precision;
    }

    /**
     * Adds two encoded widths without overflowing or exceeding the ABI safety cap.
     */
    private static function checkedSum(int $left, int $right): int
    {
        if ($left < 0 || $right < 0 || $right > self::MAX_ENCODED_BYTES - $left) {
            throw new ContractException('The static ABI value exceeds the configured encoding safety limit.');
        }

        return $left + $right;
    }

    /**
     * Multiplies a fixed-array width without overflowing or exceeding the ABI safety cap.
     */
    private static function checkedProduct(int $count, int $elementBytes): int
    {
        if ($count < 0
            || $elementBytes < 0
            || ($count !== 0 && $elementBytes > intdiv(self::MAX_ENCODED_BYTES, $count))
        ) {
            throw new ContractException('The static ABI value exceeds the configured encoding safety limit.');
        }

        return $count * $elementBytes;
    }

    /**
     * Calculates one static tuple width once when its descriptor is created.
     *
     * @param list<self> $components Validated tuple components.
     */
    private static function tupleByteLength(array $components): int
    {
        $length = 0;
        foreach ($components as $component) {
            $length = self::checkedSum($length, $component->staticByteLength());
        }

        return $length;
    }
}
