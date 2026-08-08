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

namespace IEXBase\TronAPI\Tests\Support;

use IEXBase\TronAPI\Http\HttpResponse;
use JsonException;

/**
 * Creates concise deterministic HTTP fixtures while keeping JSON encoding strict.
 */
final class HttpResponseFactory
{
    /**
     * Encodes a JSON-compatible value into a test HTTP response.
     *
     * @param array<mixed>                $data Response JSON object or list.
     * @param array<string, list<string>> $headers Optional response headers.
     *
     * @throws JsonException When a fixture contains unsupported JSON data.
     */
    public static function json(array $data, int $status = 200, array $headers = []): HttpResponse
    {
        return new HttpResponse(
            $status,
            ['Content-Type' => ['application/json'], ...$headers],
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        );
    }
}
