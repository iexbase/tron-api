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

use IEXBase\TronAPI\Exception\TransportException;

/**
 * Represents an HTTP response without binding higher layers to Guzzle or PSR-7.
 */
final readonly class HttpResponse
{
    /** @var array<string, list<string>> */
    private array $headersByLowercaseName;

    /**
     * Stores the status, headers, and unmodified response body.
     *
     * @param int                     $statusCode HTTP response status.
     * @param array<array-key, mixed> $headers Untrusted response headers from the HTTP client.
     * @param string                  $body Unmodified response body.
     */
    public function __construct(
        public int $statusCode,
        array $headers,
        public string $body,
    ) {
        if ($statusCode < 100 || $statusCode > 599) {
            throw new TransportException(
                'An HTTP response status must be between 100 and 599.',
                retryable: false,
            );
        }

        $headersByLowercaseName = [];
        foreach ($headers as $name => $values) {
            if (!is_string($name)
                || preg_match("/^[!#$%&'*+\\-.^_`|~0-9A-Za-z]+$/D", $name) !== 1
                || !is_array($values)
            ) {
                throw new TransportException(
                    'HTTP response headers must use valid string names and value lists.',
                    retryable: false,
                );
            }

            $checkedValues = [];
            foreach ($values as $value) {
                if (!is_string($value) || preg_match('/[\r\n]/', $value) === 1) {
                    throw new TransportException(
                        'HTTP response header values must be single-line strings.',
                        retryable: false,
                    );
                }
                $checkedValues[] = $value;
            }
            $lowercaseName = strtolower($name);
            $headersByLowercaseName[$lowercaseName] = [
                ...($headersByLowercaseName[$lowercaseName] ?? []),
                ...$checkedValues,
            ];
        }
        $this->headersByLowercaseName = $headersByLowercaseName;
    }

    /**
     * Returns a comma-separated header value using case-insensitive lookup.
     */
    public function headerLine(string $name): ?string
    {
        $values = $this->headersByLowercaseName[strtolower($name)] ?? null;

        return $values === null ? null : implode(', ', $values);
    }

    /**
     * Returns response headers indexed by lower-case names.
     *
     * @return array<string, list<string>>
     */
    public function headers(): array
    {
        return $this->headersByLowercaseName;
    }
}
