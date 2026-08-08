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

use IEXBase\TronAPI\Exception\NodeException;

/**
 * Wraps decoded API data with status and headers instead of returning arrays.
 */
final readonly class ApiResponse
{
    /**
     * Stores one decoded response with its HTTP metadata.
     *
     * @param array<mixed>                $data Decoded JSON object or list.
     * @param int                         $statusCode Successful HTTP status.
     * @param array<string, list<string>> $headers Response headers keyed by lower-case names.
     */
    public function __construct(
        private array $data,
        public int $statusCode,
        private array $headers,
    ) {
    }

    /**
     * Returns the complete decoded response.
     *
     * @return array<mixed>
     */
    public function data(): array
    {
        return $this->data;
    }

    /**
     * Reads a nested value by a sequence of string or integer keys.
     */
    public function value(string|int ...$path): mixed
    {
        $value = $this->data;
        foreach ($path as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * Returns HTTP response headers indexed by lower-case names.
     *
     * @return array<string, list<string>>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Throws a decoded node error when an operation response reports rejection.
     */
    public function requireAccepted(): self
    {
        $result = $this->data['result'] ?? null;
        $rejected = $result === false
            || (is_array($result) && ($result['result'] ?? null) === false);

        if ($rejected) {
            $container = is_array($result) ? $result : $this->data;
            $code = isset($container['code']) && is_scalar($container['code'])
                ? (string) $container['code']
                : null;
            $message = isset($container['message']) && is_scalar($container['message'])
                ? self::decodeNodeMessage((string) $container['message'])
                : 'The TRON node rejected the operation.';

            throw new NodeException($message, $code);
        }

        return $this;
    }

    /**
     * Decodes the protocol's common hex-encoded validation messages when valid.
     */
    private static function decodeNodeMessage(string $message): string
    {
        if ($message !== '' && strlen($message) % 2 === 0 && ctype_xdigit($message)) {
            $decoded = hex2bin($message);
            if ($decoded !== false && preg_match('//u', $decoded) === 1) {
                return $decoded;
            }
        }

        return $message;
    }
}
