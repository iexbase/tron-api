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

namespace IEXBase\TronAPI\Http;

use IEXBase\TronAPI\Enum\HttpMethod;

/**
 * Contains an absolute HTTP request ready for a transport implementation.
 */
final readonly class HttpRequest
{
    /**
     * Stores the fully resolved HTTP request and its timeout constraints.
     *
     * @param HttpMethod           $method Request method.
     * @param string               $uri Absolute target URI.
     * @param array<string,string> $headers Outgoing headers.
     * @param array<string,mixed>  $parameters JSON body or query parameters.
     * @param float                $connectTimeoutSeconds Connection timeout.
     * @param float                $requestTimeoutSeconds Complete request timeout.
     * @param int                  $maximumResponseBytes Maximum accepted response body size.
     */
    public function __construct(
        public HttpMethod $method,
        public string $uri,
        public array $headers,
        public array $parameters,
        public float $connectTimeoutSeconds,
        public float $requestTimeoutSeconds,
        public int $maximumResponseBytes,
    ) {
    }
}
