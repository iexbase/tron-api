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

use IEXBase\TronAPI\Exception\ValidationException;

/**
 * Carries bounded cursor pagination independently of any indexed-data vendor.
 */
final readonly class PageRequest
{
    /**
     * Validates the requested page size and opaque continuation cursor.
     */
    public function __construct(
        public int $limit = 20,
        public ?string $cursor = null,
    ) {
        if ($limit < 1 || $limit > 200) {
            throw new ValidationException('An indexed page limit must be between 1 and 200.');
        }

        if ($cursor !== null && ($cursor === '' || strlen($cursor) > 8_192 || preg_match('/[\r\n]/', $cursor) === 1)) {
            throw new ValidationException('An indexed page cursor must be a non-empty bounded single-line value.');
        }
    }

    /**
     * Returns provider-neutral pagination values using semantic field names.
     *
     * @return array{limit: int, cursor?: string}
     */
    public function values(): array
    {
        return $this->cursor === null
            ? ['limit' => $this->limit]
            : ['limit' => $this->limit, 'cursor' => $this->cursor];
    }
}
