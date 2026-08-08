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

use IEXBase\TronAPI\Http\HttpRequest;
use IEXBase\TronAPI\Http\HttpResponse;
use IEXBase\TronAPI\Http\TransportInterface;
use RuntimeException;

/**
 * Records requests and returns deterministic queued responses without network I/O.
 */
final class QueueTransport implements TransportInterface
{
    /** @var list<HttpResponse> */
    private array $responses;

    /** @var list<HttpRequest> */
    private array $requests = [];

    /**
     * Stores responses in the exact order in which requests must consume them.
     */
    public function __construct(HttpResponse ...$responses)
    {
        $this->responses = array_values($responses);
    }

    /**
     * Records one request and removes the next queued response.
     */
    public function send(HttpRequest $request): HttpResponse
    {
        $this->requests[] = $request;
        $response = array_shift($this->responses);
        if ($response === null) {
            throw new RuntimeException('The test transport response queue is empty.');
        }

        return $response;
    }

    /**
     * Returns every recorded HTTP request in call order.
     *
     * @return list<HttpRequest>
     */
    public function requests(): array
    {
        return $this->requests;
    }

    /**
     * Returns one recorded request by zero-based position.
     */
    public function request(int $position = 0): HttpRequest
    {
        return $this->requests[$position]
            ?? throw new RuntimeException('The requested test HTTP call was not recorded.');
    }
}
