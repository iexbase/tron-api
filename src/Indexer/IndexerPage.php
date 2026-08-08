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

namespace IEXBase\TronAPI\Indexer;

use Countable;
use IEXBase\TronAPI\Api\DataDecoder;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * Stores one vendor-neutral indexed result page and its opaque next cursor.
 *
 * @implements IteratorAggregate<int, array<string, mixed>>
 */
final readonly class IndexerPage implements Countable, IteratorAggregate, JsonSerializable
{
    /** @var list<array<string, mixed>> */
    private array $items;

    /** @var array<string, mixed> */
    private array $metadata;

    /**
     * Validates indexed records and stores provider metadata without interpreting it.
     *
     * @param list<array<string, mixed>> $items Ordered indexed records.
     * @param array<string, mixed>       $metadata Provider-specific page metadata.
     */
    public function __construct(
        array $items,
        public ?string $nextCursor = null,
        public ?int $generatedAtMilliseconds = null,
        array $metadata = [],
    ) {
        $this->items = DataDecoder::objectList($items, 'items');
        $this->metadata = DataDecoder::object($metadata, 'metadata');
    }

    /**
     * Returns records in the exact provider order.
     *
     * @return list<array<string, mixed>>
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * Returns provider-specific metadata for diagnostics and future fields.
     *
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * Returns the number of records in this page.
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Iterates records without copying them into another collection.
     *
     * @return Traversable<int, array<string, mixed>>
     */
    public function getIterator(): Traversable
    {
        yield from $this->items;
    }

    /**
     * Serializes the page using provider-neutral field names.
     *
     * @return array{items: list<array<string, mixed>>, next_cursor: ?string, generated_at: ?int, metadata: array<string, mixed>}
     */
    public function jsonSerialize(): array
    {
        return [
            'items' => $this->items,
            'next_cursor' => $this->nextCursor,
            'generated_at' => $this->generatedAtMilliseconds,
            'metadata' => $this->metadata,
        ];
    }
}
