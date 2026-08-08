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

use IEXBase\TronAPI\Encoding\Hex;
use IEXBase\TronAPI\Exception\ContractException;
use JsonException;
use JsonSerializable;

/**
 * Stores a contract ABI and resolves overloads by canonical signature.
 */
final readonly class Abi implements JsonSerializable
{
    /** @var list<AbiEntry> */
    private array $entries;

    /**
     * Rejects duplicate canonical entries and stores ABI source order.
     *
     * @param list<AbiEntry> $entries Parsed ABI entries.
     */
    public function __construct(array $entries)
    {
        $identities = [];
        foreach ($entries as $entry) {
            $identity = in_array($entry->type, ['function', 'event', 'error'], true)
                ? $entry->type . ':' . $entry->signature()
                : $entry->type;
            if (isset($identities[$identity])) {
                throw new ContractException(sprintf('The ABI contains duplicate entry `%s`.', $identity));
            }
            $identities[$identity] = true;
        }

        $this->entries = $entries;
    }

    /**
     * Parses a JSON ABI array with exceptions instead of silent null values.
     */
    public static function fromJson(string $json): self
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ContractException('The contract ABI contains malformed JSON.', 0, $exception);
        }

        if (!is_array($data)) {
            throw new ContractException('A contract ABI JSON document must decode to an array.');
        }

        return self::fromArray($data);
    }

    /**
     * Parses either a standard ABI list or java-tron's `{entrys: [...]}` shape.
     *
     * @param array<mixed> $data Decoded ABI data.
     */
    public static function fromArray(array $data): self
    {
        if (isset($data['entrys'])) {
            $data = $data['entrys'];
            if (!is_array($data)) {
                throw new ContractException('The java-tron ABI `entrys` field must be a list.');
            }
        }

        if (!array_is_list($data)) {
            throw new ContractException('A contract ABI must be a list of entries.');
        }

        $entries = [];
        foreach ($data as $entry) {
            if (!is_array($entry)) {
                throw new ContractException('Every contract ABI entry must be an object.');
            }
            $record = [];
            foreach ($entry as $key => $value) {
                if (!is_string($key)) {
                    throw new ContractException('An ABI entry object must use string field names.');
                }
                $record[$key] = $value;
            }
            $entries[] = AbiEntry::fromArray($record);
        }

        return new self($entries);
    }

    /**
     * Resolves a function by exact signature or by an unambiguous name/count.
     */
    public function function(string $nameOrSignature, ?int $argumentCount = null): AbiEntry
    {
        return $this->resolve('function', $nameOrSignature, $argumentCount);
    }

    /**
     * Resolves an event by exact signature or by an unambiguous name/count.
     */
    public function event(string $nameOrSignature, ?int $argumentCount = null): AbiEntry
    {
        return $this->resolve('event', $nameOrSignature, $argumentCount);
    }

    /**
     * Resolves a custom Solidity error by signature or unambiguous name/count.
     */
    public function error(string $nameOrSignature, ?int $argumentCount = null): AbiEntry
    {
        return $this->resolve('error', $nameOrSignature, $argumentCount);
    }

    /**
     * Resolves a function from its exact four-byte selector, or returns null.
     */
    public function functionBySelector(string $selector): ?AbiEntry
    {
        return $this->entryByHash('function', Hex::canonicalize($selector, 4));
    }

    /**
     * Resolves a custom error from its exact four-byte selector, or returns null.
     */
    public function errorBySelector(string $selector): ?AbiEntry
    {
        return $this->entryByHash('error', Hex::canonicalize($selector, 4));
    }

    /**
     * Resolves a non-anonymous event from its complete 32-byte topic, or returns null.
     */
    public function eventByTopic(string $topic): ?AbiEntry
    {
        return $this->entryByHash('event', Hex::canonicalize($topic, 32));
    }

    /**
     * Returns the single constructor definition, or null when it is omitted.
     */
    public function constructor(): ?AbiEntry
    {
        foreach ($this->entries as $entry) {
            if ($entry->type === 'constructor') {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Returns every parsed ABI entry in source order.
     *
     * @return list<AbiEntry>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * Serializes the ABI as the standard top-level entry list.
     *
     * @return list<AbiEntry>
     */
    public function jsonSerialize(): array
    {
        return $this->entries;
    }

    /**
     * Resolves overloads and requires callers to disambiguate when necessary.
     */
    private function resolve(string $type, string $nameOrSignature, ?int $argumentCount): AbiEntry
    {
        $exactSignature = str_contains($nameOrSignature, '(');
        $matches = [];

        foreach ($this->entries as $entry) {
            if ($entry->type !== $type) {
                continue;
            }

            $matchesIdentity = $exactSignature
                ? $entry->signature() === $nameOrSignature
                : $entry->name === $nameOrSignature;
            if ($matchesIdentity && ($argumentCount === null || count($entry->inputs()) === $argumentCount)) {
                $matches[] = $entry;
            }
        }

        if (count($matches) === 1) {
            return $matches[0];
        }

        if ($matches === []) {
            throw new ContractException(sprintf('ABI %s `%s` was not found.', $type, $nameOrSignature));
        }

        throw new ContractException(sprintf(
            'ABI %s `%s` is overloaded; use its full canonical signature.',
            $type,
            $nameOrSignature,
        ));
    }

    /**
     * Resolves a selector/topic and rejects the rare case of an ABI hash collision.
     */
    private function entryByHash(string $type, string $hash): ?AbiEntry
    {
        $matches = [];
        foreach ($this->entries as $entry) {
            if ($entry->type !== $type || ($type === 'event' && $entry->anonymous)) {
                continue;
            }

            $entryHash = $type === 'event' ? $entry->eventTopic() : $entry->selector();
            if (hash_equals($entryHash, $hash)) {
                $matches[] = $entry;
            }
        }

        if (count($matches) > 1) {
            throw new ContractException(sprintf('ABI %s hash `%s` is ambiguous because of a collision.', $type, $hash));
        }

        return $matches[0] ?? null;
    }
}
