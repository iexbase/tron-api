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

namespace IEXBase\TronAPI\Api;

use IEXBase\TronAPI\Enum\HttpMethod;
use IEXBase\TronAPI\Enum\NodeRole;
use IEXBase\TronAPI\Exception\ValidationException;

/**
 * Describes one relative TRON API request independently of its HTTP transport.
 */
final readonly class ApiRequest
{
    /**
     * Validates and stores a node role, path, method, parameters, and retry flag.
     *
     * @param NodeRole             $nodeRole Data source that owns the path.
     * @param HttpMethod           $method HTTP method required by the endpoint.
     * @param string               $path Absolute path relative to the base URI.
     * @param array<string, mixed> $parameters JSON body or query parameters.
     * @param bool                 $retryable Whether the exact request may be retried.
     */
    public function __construct(
        public NodeRole $nodeRole,
        public HttpMethod $method,
        public string $path,
        public array $parameters = [],
        public bool $retryable = true,
    ) {
        $decodedPath = rawurldecode($path);
        if (!str_starts_with($path, '/')
            || str_contains($path, '://')
            || str_contains($path, '?')
            || str_contains($path, '#')
            || str_contains($decodedPath, '..')
            || str_contains($decodedPath, '\\')
            || preg_match('/[\x00-\x20\x7F]/', $decodedPath) === 1
        ) {
            throw new ValidationException(
                'An API request path must be a safe absolute path without traversal, query, or fragment components.',
            );
        }
    }
}
