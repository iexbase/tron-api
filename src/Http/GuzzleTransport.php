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

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use IEXBase\TronAPI\Enum\HttpMethod;
use IEXBase\TronAPI\Exception\TransportException;
use JsonException;
use Psr\Http\Message\StreamInterface;
use Throwable;

/**
 * Sends TRON HTTP requests through Guzzle with correct GET and POST semantics.
 */
final readonly class GuzzleTransport implements TransportInterface
{
    /**
     * Creates a transport with an injectable client for framework integration.
     */
    public function __construct(private ClientInterface $client = new Client())
    {
    }

    /**
     * Sends a request without allowing Guzzle to hide non-2xx response bodies.
     */
    public function send(HttpRequest $request): HttpResponse
    {
        $options = [
            'allow_redirects' => false,
            'connect_timeout' => $request->connectTimeoutSeconds,
            'headers' => $request->headers,
            'http_errors' => false,
            'stream' => true,
            'timeout' => $request->requestTimeoutSeconds,
        ];

        if ($request->method === HttpMethod::Get) {
            $options['query'] = $request->parameters;
        } else {
            try {
                $options['body'] = json_encode(
                    $request->parameters,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                );
            } catch (JsonException $exception) {
                throw new TransportException(
                    'The TRON request parameters could not be encoded as JSON.',
                    previous: $exception,
                    retryable: false,
                );
            }
        }

        try {
            $response = $this->client->request($request->method->value, $request->uri, $options);
        } catch (GuzzleException $exception) {
            throw new TransportException('The TRON endpoint request failed: ' . $exception->getMessage(), 0, $exception);
        }

        return new HttpResponse(
            $response->getStatusCode(),
            $response->getHeaders(),
            $this->readBody($response->getBody(), $request->maximumResponseBytes),
        );
    }

    /**
     * Reads a streamed response without allowing an endpoint to exhaust process memory.
     */
    private function readBody(StreamInterface $body, int $maximumBytes): string
    {
        if ($maximumBytes < 1) {
            throw new TransportException(
                'The maximum TRON response size must be positive.',
                retryable: false,
            );
        }

        $contents = '';

        while (!$body->eof()) {
            $remaining = $maximumBytes - strlen($contents);
            $readLength = $remaining >= 8_192 ? 8_192 : $remaining + 1;
            try {
                $chunk = $body->read($readLength);
            } catch (Throwable $exception) {
                throw new TransportException('The TRON response body could not be read.', 0, $exception);
            }
            if ($chunk === '') {
                throw new TransportException('The TRON response stream stopped before reaching end-of-file.');
            }

            $contents .= $chunk;
            if (strlen($contents) > $maximumBytes) {
                throw new TransportException(sprintf(
                    'The TRON response exceeded the configured limit of %d bytes.',
                    $maximumBytes,
                ), retryable: false);
            }
        }

        return $contents;
    }
}
