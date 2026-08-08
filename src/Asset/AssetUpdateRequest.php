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

namespace IEXBase\TronAPI\Asset;

use IEXBase\TronAPI\Support\IntegerHelper;
use IEXBase\TronAPI\Support\TextHelper;

/**
 * Captures mutable metadata and Bandwidth quotas for an issued TRC-10 token.
 */
final readonly class AssetUpdateRequest
{
    /**
     * Validates all mutable asset fields before a node request is made.
     */
    public function __construct(
        public string $description,
        public string $url,
        public int $freeAssetNetLimit,
        public int $publicFreeAssetNetLimit,
    ) {
        TextHelper::utf8($description, 'TRC-10 description', 200, true);
        TextHelper::webUrl($url, 'TRC-10 URL');
        IntegerHelper::nonNegative($freeAssetNetLimit, 'TRC-10 free asset bandwidth limit');
        IntegerHelper::nonNegative($publicFreeAssetNetLimit, 'TRC-10 public bandwidth limit');
    }

    /**
     * Returns exact native UpdateAssetContract fields.
     *
     * @return array{description: string, url: string, new_limit: int, new_public_limit: int}
     */
    public function fields(): array
    {
        return [
            'description' => $this->description,
            'url' => $this->url,
            'new_limit' => $this->freeAssetNetLimit,
            'new_public_limit' => $this->publicFreeAssetNetLimit,
        ];
    }
}
