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

use Countable;
use IEXBase\TronAPI\Exception\ContractException;
use JsonSerializable;

/**
 * Stores decoded ABI values once while supporting ordered and named access.
 */
final readonly class DecodedValues implements Countable, JsonSerializable
{
    /** @var list<mixed> */
    private array $values;

    /** @var array<string, int> */
    private array $positionsByName;

    /**
     * Associates unique non-empty ABI names with their ordered positions.
     *
     * @param list<mixed>  $values Decoded values in ABI order.
     * @param list<string> $names Parameter names in the same order.
     */
    public function __construct(array $values, array $names)
    {
        if (count($values) !== count($names)) {
            throw new ContractException('Decoded ABI values and names must have equal lengths.');
        }

        $positions = [];
        foreach ($names as $position => $name) {
            if ($name === '') {
                continue;
            }
            if (isset($positions[$name])) {
                throw new ContractException(sprintf('Decoded ABI output name `%s` is duplicated.', $name));
            }
            $positions[$name] = $position;
        }

        $this->values = $values;
        $this->positionsByName = $positions;
    }

    /**
     * Returns one decoded value by zero-based position or unique ABI name.
     */
    public function value(int|string $positionOrName): mixed
    {
        $position = is_int($positionOrName)
            ? $positionOrName
            : ($this->positionsByName[$positionOrName] ?? null);

        if ($position === null || !array_key_exists($position, $this->values)) {
            throw new ContractException(sprintf('Decoded ABI value `%s` does not exist.', (string) $positionOrName));
        }

        return $this->values[$position];
    }

    /**
     * Returns every decoded value in ABI order.
     *
     * @return list<mixed>
     */
    public function all(): array
    {
        return $this->values;
    }

    /**
     * Returns the number of decoded values.
     */
    public function count(): int
    {
        return count($this->values);
    }

    /**
     * Serializes decoded values once in their original ABI order.
     *
     * @return list<mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->values;
    }
}
