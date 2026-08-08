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

use IEXBase\TronAPI\Configuration\NodeConfiguration;
use IEXBase\TronAPI\Exception\HttpException;
use IEXBase\TronAPI\Exception\NodeException;
use IEXBase\TronAPI\Exception\RateLimitException;
use IEXBase\TronAPI\Exception\ResponseDecodingException;
use IEXBase\TronAPI\Exception\TransportException;
use IEXBase\TronAPI\Http\HttpRequest;
use IEXBase\TronAPI\Http\HttpResponse;
use IEXBase\TronAPI\Http\TransportInterface;
use JsonException;

/**
 * Routes API requests to the correct node role and applies safe error handling.
 */
final readonly class ApiClient
{
    /**
     * Creates a client from an explicit node topology and HTTP transport.
     */
    public function __construct(
        private NodeConfiguration $configuration,
        private TransportInterface $transport,
    ) {
    }

    /**
     * Sends one request with bounded retries and returns decoded response data.
     */
    public function request(ApiRequest $request): ApiResponse
    {
        $attempt = 0;

        while (true) {
            ++$attempt;
            try {
                return $this->sendOnce($request);
            } catch (TransportException|RateLimitException|HttpException $exception) {
                if (!$request->retryable
                    || $attempt >= $this->configuration->retryPolicy->maximumAttempts
                    || ($exception instanceof TransportException && !$exception->retryable)
                    || ($exception instanceof HttpException && $exception->statusCode < 500)
                ) {
                    throw $exception;
                }

                $retryAfter = $exception instanceof RateLimitException
                    ? $exception->retryAfterSeconds
                    : null;
                $delay = $this->configuration->retryPolicy->delayMilliseconds($attempt, $retryAfter);
                if ($delay > 0) {
                    self::waitMilliseconds($delay);
                }
            }
        }
    }

    /**
     * Resolves, sends, validates, and decodes a single HTTP attempt.
     */
    private function sendOnce(ApiRequest $request): ApiResponse
    {
        $httpResponse = $this->transport->send(new HttpRequest(
            $request->method,
            $this->configuration->baseUri($request->nodeRole) . $request->path,
            $this->configuration->requestHeaders($request->nodeRole),
            $request->parameters,
            $this->configuration->connectTimeoutSeconds,
            $this->configuration->requestTimeoutSeconds,
            $this->configuration->maximumResponseBytes,
        ));
        if (strlen($httpResponse->body) > $this->configuration->maximumResponseBytes) {
            throw new TransportException(sprintf(
                'The TRON response exceeded the configured limit of %d bytes.',
                $this->configuration->maximumResponseBytes,
            ), retryable: false);
        }

        if ($httpResponse->statusCode === 429) {
            $message = self::httpErrorMessage($httpResponse, 'The configured endpoint rate limit was reached.');
            throw new RateLimitException(
                $message,
                self::retryAfterSeconds($httpResponse) ?? self::retryAfterFromMessage($message),
            );
        }

        if ($httpResponse->statusCode === 403) {
            $message = self::httpErrorMessage($httpResponse, 'The configured endpoint denied the request.');
            if (self::isRateLimitMessage($message)) {
                throw new RateLimitException(
                    $message,
                    self::retryAfterSeconds($httpResponse) ?? self::retryAfterFromMessage($message),
                );
            }
        }

        if ($httpResponse->statusCode < 200 || $httpResponse->statusCode >= 300) {
            throw new HttpException($httpResponse->statusCode, $httpResponse->body);
        }

        $data = $this->decodeJson($httpResponse);
        $this->throwForEmbeddedError($data);

        return new ApiResponse($data, $httpResponse->statusCode, $httpResponse->headers());
    }

    /**
     * Decodes a JSON object or list and rejects silent malformed responses.
     *
     * @return array<mixed>
     */
    private function decodeJson(HttpResponse $response): array
    {
        if (trim($response->body) === '') {
            return [];
        }

        try {
            $decoded = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ResponseDecodingException('The TRON endpoint returned malformed JSON.', 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new ResponseDecodingException('The TRON endpoint JSON response must be an object or list.');
        }

        return $decoded;
    }

    /**
     * Converts HTTP-200 error envelopes used by nodes and JSON-RPC to exceptions.
     *
     * @param array<mixed> $data Decoded response data.
     */
    private function throwForEmbeddedError(array $data): void
    {
        $error = $data['Error'] ?? $data['error'] ?? null;
        if ($error === null) {
            return;
        }

        if (is_array($error)) {
            $message = isset($error['message']) && is_scalar($error['message'])
                ? (string) $error['message']
                : json_encode($error, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $code = isset($error['code']) && is_scalar($error['code']) ? (string) $error['code'] : null;
        } else {
            $message = is_scalar($error) ? (string) $error : 'The TRON endpoint returned an unknown error.';
            $code = null;
        }

        $message = is_string($message) ? $message : 'The TRON endpoint returned an unknown error.';
        if (self::isRateLimitMessage($message)) {
            throw new RateLimitException($message, self::retryAfterFromMessage($message));
        }

        throw new NodeException($message, $code);
    }

    /**
     * Extracts a readable message from a failed HTTP response when possible.
     */
    private static function httpErrorMessage(HttpResponse $response, string $fallback): string
    {
        try {
            $decoded = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $fallback;
        }

        if (!is_array($decoded)) {
            return $fallback;
        }

        $message = $decoded['Error'] ?? $decoded['error'] ?? $decoded['message'] ?? null;
        if (is_array($message)) {
            $message = $message['message'] ?? null;
        }

        return is_scalar($message) ? (string) $message : $fallback;
    }

    /**
     * Parses a non-negative integer Retry-After header expressed in seconds.
     */
    private static function retryAfterSeconds(HttpResponse $response): ?int
    {
        $value = $response->headerLine('Retry-After');

        return $value !== null && ctype_digit($value) ? (int) $value : null;
    }

    /**
     * Extracts provider-advised suspension seconds when Retry-After is absent.
     */
    private static function retryAfterFromMessage(string $message): ?int
    {
        if (preg_match('/(?:suspend(?:ed)?|retry)\D{0,32}([0-9]+)\s*(?:s|sec|second)/i', $message, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    /**
     * Distinguishes provider throttling from authentication and authorization failures.
     */
    private static function isRateLimitMessage(string $message): bool
    {
        return preg_match(
            '/\brate(?:[\s_-]+)?limit\b|\bquota\b|\btoo many\b|\bexceeded\b|\bsuspend(?:ed|sion)?\b/i',
            $message,
        ) === 1;
    }

    /**
     * Sleeps in bounded chunks so a configured millisecond value cannot overflow microseconds.
     */
    private static function waitMilliseconds(int $delay): void
    {
        while ($delay > 0) {
            $chunk = min($delay, 60_000);
            usleep($chunk * 1_000);
            $delay -= $chunk;
        }
    }
}
