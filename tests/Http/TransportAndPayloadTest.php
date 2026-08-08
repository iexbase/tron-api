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

namespace IEXBase\TronAPI\Tests\Http;

use ArrayObject;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use IEXBase\TronAPI\Api\ApiClient;
use IEXBase\TronAPI\Api\ApiRequest;
use IEXBase\TronAPI\Api\ApiResponse;
use IEXBase\TronAPI\Api\DataDecoder;
use IEXBase\TronAPI\Configuration\NodeConfiguration;
use IEXBase\TronAPI\Enum\HttpMethod;
use IEXBase\TronAPI\Enum\NodeRole;
use IEXBase\TronAPI\Exception\NodeException;
use IEXBase\TronAPI\Exception\ResponseDecodingException;
use IEXBase\TronAPI\Exception\TransportException;
use IEXBase\TronAPI\Exception\ValidationException;
use IEXBase\TronAPI\Http\GuzzleTransport;
use IEXBase\TronAPI\Http\HttpRequest;
use IEXBase\TronAPI\Http\HttpResponse;
use IEXBase\TronAPI\Tests\Support\QueueTransport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

/**
 * Verifies transport semantics and strict decoding of untrusted endpoint data.
 */
#[CoversClass(GuzzleTransport::class)]
#[CoversClass(HttpRequest::class)]
#[CoversClass(HttpResponse::class)]
#[CoversClass(ApiRequest::class)]
#[CoversClass(ApiResponse::class)]
#[CoversClass(DataDecoder::class)]
final class TransportAndPayloadTest extends TestCase
{
    /**
     * Sends GET parameters as a query and POST parameters as a JSON document.
     */
    public function testGuzzleTransportPreservesHttpSemantics(): void
    {
        $history = $this->requestHistory();
        $mock = new MockHandler([
            new Response(200, ['X-Node' => ['full', 'primary']], '{"ok":true}'),
            new Response(201, [], '{"created":true}'),
        ]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::tap(
            static function (RequestInterface $request, array $options) use ($history): void {
                $history->append([
                    'request' => $request,
                    'response' => null,
                    'error' => null,
                    'options' => $options,
                ]);
            },
        ));
        $transport = new GuzzleTransport(new Client(['handler' => $stack]));

        $getResponse = $transport->send(new HttpRequest(
            HttpMethod::Get,
            'https://node.example/wallet/getnodeinfo',
            ['Accept' => 'application/json'],
            ['visible' => true],
            1.0,
            2.0,
            1_048_576,
        ));
        $postResponse = $transport->send(new HttpRequest(
            HttpMethod::Post,
            'https://node.example/wallet/getaccount',
            ['Content-Type' => 'application/json'],
            ['address' => 'TAddress', 'url' => 'https://example.com', 'memo' => 'TRON ✓'],
            1.0,
            2.0,
            1_048_576,
        ));

        self::assertSame(200, $getResponse->statusCode);
        self::assertSame('full, primary', $getResponse->headerLine('x-node'));
        self::assertSame(201, $postResponse->statusCode);
        self::assertCount(2, $history);
        $getRequest = $this->historyRequest($history, 0);
        $postRequest = $this->historyRequest($history, 1);
        self::assertSame('visible=1', $getRequest->getUri()->getQuery());
        self::assertSame('', (string) $getRequest->getBody());
        self::assertSame(
            '{"address":"TAddress","url":"https://example.com","memo":"TRON ✓"}',
            (string) $postRequest->getBody(),
        );
        self::assertFalse($this->historyOptions($history, 1)['allow_redirects'] ?? true);
    }

    /**
     * Returns a request captured by the Guzzle history middleware.
     *
     * @param ArrayObject<int, array{request: RequestInterface, response: null, error: null, options: array<array-key, mixed>}> $history
     */
    private function historyRequest(ArrayObject $history, int $index): RequestInterface
    {
        if (!$history->offsetExists($index)) {
            self::fail(sprintf('History entry %d does not contain a PSR-7 request.', $index));
        }

        $entry = $history->offsetGet($index);
        if ($entry === null) {
            self::fail(sprintf('History entry %d is unexpectedly empty.', $index));
        }

        return $entry['request'];
    }

    /**
     * Returns the transport options captured for one Guzzle request.
     *
     * @param ArrayObject<int, array{request: RequestInterface, response: null, error: null, options: array<array-key, mixed>}> $history
     * @return array<array-key, mixed>
     */
    private function historyOptions(ArrayObject $history, int $index): array
    {
        if (!$history->offsetExists($index)) {
            self::fail(sprintf('History entry %d does not contain Guzzle options.', $index));
        }

        $entry = $history->offsetGet($index);
        if ($entry === null) {
            self::fail(sprintf('History entry %d is unexpectedly empty.', $index));
        }

        return $entry['options'];
    }

    /**
     * Creates a countable, precisely typed request history container.
     *
     * @return ArrayObject<int, array{request: RequestInterface, response: null, error: null, options: array<array-key, mixed>}>
     */
    private function requestHistory(): ArrayObject
    {
        return new ArrayObject();
    }

    /**
     * Converts a Guzzle connection failure into the package transport exception.
     */
    public function testGuzzleFailureDoesNotLeakVendorException(): void
    {
        $request = new Request('GET', 'https://node.example');
        $transport = new GuzzleTransport(new Client([
            'handler' => HandlerStack::create(new MockHandler([
                new ConnectException('offline', $request),
            ])),
        ]));
        $this->expectException(TransportException::class);

        $transport->send(new HttpRequest(
            HttpMethod::Get,
            'https://node.example/wallet/getnodeinfo',
            [],
            [],
            1.0,
            2.0,
            1_048_576,
        ));
    }

    /**
     * Stops reading a streamed response immediately after its configured size limit.
     */
    public function testGuzzleTransportRejectsOversizedResponse(): void
    {
        $transport = new GuzzleTransport(new Client([
            'handler' => HandlerStack::create(new MockHandler([
                new Response(200, [], '12345'),
            ])),
        ]));
        $this->expectException(TransportException::class);

        $transport->send(new HttpRequest(
            HttpMethod::Get,
            'https://node.example/wallet/getnodeinfo',
            [],
            [],
            1.0,
            2.0,
            4,
        ));
    }

    /**
     * Rejects malformed response status and header values from custom transports.
     */
    public function testHttpResponseRejectsMalformedTransportMetadata(): void
    {
        foreach ([
            static fn (): HttpResponse => new HttpResponse(99, [], ''),
            static fn (): HttpResponse => new HttpResponse(200, ['X-Test' => [123]], ''),
            static fn (): HttpResponse => new HttpResponse(200, ['Bad Header' => ['value']], ''),
        ] as $response) {
            try {
                $response();
                self::fail('Malformed transport response metadata was accepted.');
            } catch (TransportException) {
                self::addToAssertionCount(1);
            }
        }
    }

    /**
     * Coalesces duplicate response header names without case-sensitive data loss.
     */
    public function testHttpResponseCombinesHeaderNamesCaseInsensitively(): void
    {
        $response = new HttpResponse(200, [
            'X-Node' => ['primary'],
            'x-node' => ['secondary'],
        ], '');

        self::assertSame('primary, secondary', $response->headerLine('X-NODE'));
        self::assertSame(['x-node' => ['primary', 'secondary']], $response->headers());
    }

    /**
     * Enforces the configured body limit even when a replacement transport ignores it.
     */
    public function testApiClientRejectsOversizedCustomTransportResponse(): void
    {
        $transport = new QueueTransport(
            new HttpResponse(200, [], '12345'),
        );
        $client = new ApiClient(
            NodeConfiguration::custom(
                'https://node.example',
                maximumResponseBytes: 4,
            ),
            $transport,
        );
        try {
            $client->request(new ApiRequest(
                NodeRole::FullNode,
                HttpMethod::Get,
                '/wallet/getnodeinfo',
            ));
            self::fail('An oversized custom-transport response must be rejected.');
        } catch (TransportException $exception) {
            self::assertFalse($exception->retryable);
            self::assertCount(1, $transport->requests());
        }
    }

    /**
     * Rejects unsafe relative paths before they can reach a configured node.
     */
    public function testApiRequestRejectsPathTraversal(): void
    {
        foreach ([
            '/wallet/../admin',
            '/wallet/%2e%2e/admin',
            '/wallet/getnodeinfo?api_key=secret',
            '/wallet/getnodeinfo#fragment',
            '/wallet\\getnodeinfo',
        ] as $path) {
            try {
                new ApiRequest(NodeRole::FullNode, HttpMethod::Post, $path);
                self::fail(sprintf('The unsafe API path `%s` was accepted.', $path));
            } catch (ValidationException) {
                self::addToAssertionCount(1);
            }
        }
    }

    /**
     * Reads nested response values and decodes a node rejection message.
     */
    public function testApiResponseProvidesTypedEnvelopeBehavior(): void
    {
        $accepted = new ApiResponse(['outer' => ['value' => 7]], 200, ['x-test' => ['yes']]);

        self::assertSame(7, $accepted->value('outer', 'value'));
        self::assertNull($accepted->value('missing'));
        self::assertSame(['x-test' => ['yes']], $accepted->headers());
        self::assertSame($accepted, $accepted->requireAccepted());

        try {
            (new ApiResponse([
                'result' => ['result' => false, 'code' => 'SIGERROR', 'message' => bin2hex('bad signature')],
            ], 200, []))->requireAccepted();
            self::fail('A rejected node envelope must throw.');
        } catch (NodeException $exception) {
            self::assertSame('bad signature', $exception->getMessage());
            self::assertSame('SIGERROR', $exception->nodeCode);
        }
    }

    /**
     * Accepts exact scalar shapes and rejects list/object ambiguity.
     */
    public function testDataDecoderRequiresExactJsonShapes(): void
    {
        self::assertSame(['name' => 'TRON'], DataDecoder::object(['name' => 'TRON'], 'item'));
        self::assertSame([['id' => 1]], DataDecoder::objectList([['id' => 1]], 'items'));
        self::assertSame('-5', DataDecoder::signedDecimal('-5', 'value'));
        self::assertSame(5, DataDecoder::integer('5', 'value'));

        $this->expectException(ResponseDecodingException::class);
        DataDecoder::object(['list-item'], 'object');
    }
}
