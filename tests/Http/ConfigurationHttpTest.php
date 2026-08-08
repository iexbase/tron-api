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

use IEXBase\TronAPI\Api\ApiClient;
use IEXBase\TronAPI\Api\Endpoint;
use IEXBase\TronAPI\Configuration\NodeConfiguration;
use IEXBase\TronAPI\Configuration\RetryPolicy;
use IEXBase\TronAPI\Enum\HttpMethod;
use IEXBase\TronAPI\Enum\Network;
use IEXBase\TronAPI\Enum\NodeRole;
use IEXBase\TronAPI\Exception\ConfigurationException;
use IEXBase\TronAPI\Exception\HttpException;
use IEXBase\TronAPI\Exception\NodeException;
use IEXBase\TronAPI\Exception\RateLimitException;
use IEXBase\TronAPI\Exception\ResponseDecodingException;
use IEXBase\TronAPI\Http\HttpResponse;
use IEXBase\TronAPI\Tests\Support\HttpResponseFactory;
use IEXBase\TronAPI\Tests\Support\QueueTransport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies node topology, routing, headers, retries, and response errors.
 */
#[CoversClass(NodeConfiguration::class)]
#[CoversClass(RetryPolicy::class)]
#[CoversClass(ApiClient::class)]
#[CoversClass(Endpoint::class)]
final class ConfigurationHttpTest extends TestCase
{
    /**
     * Routes each API role to an independently configured base URI.
     */
    public function testCustomTopologyRoutesRolesIndependently(): void
    {
        $configuration = NodeConfiguration::custom(
            'http://full-node:8090',
            'http://solidity-node:8091',
            'https://indexer.example',
            'http://json-rpc:8545',
        );

        self::assertSame('http://full-node:8090', $configuration->baseUri(NodeRole::FullNode));
        self::assertSame('http://solidity-node:8091', $configuration->baseUri(NodeRole::SolidityNode));
        self::assertSame('https://indexer.example', $configuration->baseUri(NodeRole::Indexer));
        self::assertSame('http://json-rpc:8545', $configuration->baseUri(NodeRole::JsonRpc));
    }

    /**
     * Rejects an absent optional indexer instead of silently falling back to TronGrid.
     */
    public function testCustomTopologyDoesNotAssumeIndexer(): void
    {
        $configuration = NodeConfiguration::custom('http://localhost:8090');

        self::assertFalse($configuration->hasEndpoint(NodeRole::Indexer));
        $this->expectException(ConfigurationException::class);

        $configuration->baseUri(NodeRole::Indexer);
    }

    /**
     * Redacts provider authentication material in debugger output.
     */
    public function testAuthenticationHeadersAreRedacted(): void
    {
        $configuration = NodeConfiguration::custom(
            'https://node.example',
            authenticationHeadersByRole: [
                NodeRole::Indexer->value => ['Authorization' => 'Bearer secret-value'],
            ],
        );
        $debug = print_r($configuration, true);

        self::assertStringContainsString('[REDACTED]', $debug);
        self::assertStringNotContainsString('secret-value', $debug);
        self::assertArrayNotHasKey('Authorization', $configuration->requestHeaders(NodeRole::FullNode));
        self::assertSame(
            'Bearer secret-value',
            $configuration->requestHeaders(NodeRole::Indexer)['Authorization'],
        );
    }

    /**
     * Rejects credentials, queries, and fragments in every configured node URI.
     */
    public function testNodeUrisRejectAuthorityAndRequestComponents(): void
    {
        foreach ([
            'https://user@node.example',
            'https://user:password@node.example',
            'https://node.example?api_key=secret',
            'https://node.example#fragment',
        ] as $uri) {
            try {
                NodeConfiguration::custom($uri);
                self::fail(sprintf('The unsafe node URI `%s` was accepted.', $uri));
            } catch (ConfigurationException) {
                self::addToAssertionCount(1);
            }
        }
    }

    /**
     * Rejects authentication maps that are not assigned to a known node role.
     */
    public function testAuthenticationHeadersRequireAKnownNodeRole(): void
    {
        $this->expectException(ConfigurationException::class);

        NodeConfiguration::custom(
            'https://node.example',
            authenticationHeadersByRole: ['unknown' => ['Authorization' => 'Bearer secret-value']],
        );
    }

    /**
     * Prevents a credential from being assigned to global headers shared by every node role.
     */
    public function testDefaultHeadersRejectCredentialNames(): void
    {
        $this->expectException(ConfigurationException::class);

        NodeConfiguration::custom(
            'https://node.example',
            defaultHeaders: ['Authorization' => 'Bearer secret-value'],
        );
    }

    /**
     * Rejects non-finite timeouts and non-positive response limits before transport creation.
     */
    public function testTransportLimitsMustBeFiniteAndPositive(): void
    {
        $invalidConfigurations = [
            static fn (): NodeConfiguration => NodeConfiguration::custom(
                'https://node.example',
                connectTimeoutSeconds: NAN,
            ),
            static fn (): NodeConfiguration => NodeConfiguration::custom(
                'https://node.example',
                requestTimeoutSeconds: INF,
            ),
            static fn (): NodeConfiguration => NodeConfiguration::custom(
                'https://node.example',
                maximumResponseBytes: 0,
            ),
        ];

        foreach ($invalidConfigurations as $configuration) {
            try {
                $configuration();
                self::fail('An invalid HTTP transport limit was accepted.');
            } catch (ConfigurationException) {
                self::addToAssertionCount(1);
            }
        }
    }

    /**
     * Sends pending-list requests through GET with no JSON array body.
     */
    public function testEndpointUsesDocumentedGetSemantics(): void
    {
        $transport = new QueueTransport(HttpResponseFactory::json(['txId' => []]));
        $client = new ApiClient(NodeConfiguration::forNetwork(Network::Mainnet), $transport);

        $client->request(Endpoint::GetPendingTransactions->request());

        self::assertSame(HttpMethod::Get, $transport->request()->method);
        self::assertSame([], $transport->request()->parameters);
        self::assertSame('https://api.trongrid.io/wallet/gettransactionlistfrompending', $transport->request()->uri);
    }

    /**
     * Prevents both broadcast routes from retrying after an ambiguous failure by default.
     */
    public function testBroadcastEndpointsAreNotRetryableByDefault(): void
    {
        self::assertFalse(Endpoint::BroadcastTransaction->request()->retryable);
        self::assertFalse(Endpoint::BroadcastHex->request()->retryable);
        self::assertTrue(Endpoint::GetNodeInfo->request()->retryable);
    }

    /**
     * Retries a transient server error using a deterministic zero-delay policy.
     */
    public function testTransientHttpFailureIsRetried(): void
    {
        $transport = new QueueTransport(
            HttpResponseFactory::json(['error' => 'temporary'], 500),
            HttpResponseFactory::json(['pendingSize' => 3]),
        );
        $configuration = NodeConfiguration::custom(
            'https://node.example',
            retryPolicy: new RetryPolicy(2, 0, 0, 0),
        );
        $client = new ApiClient($configuration, $transport);

        self::assertSame(3, $client->request(Endpoint::GetPendingTransactionCount->request())->value('pendingSize'));
        self::assertCount(2, $transport->requests());
    }

    /**
     * Treats an authentication-related HTTP 403 as a non-retryable HTTP failure.
     */
    public function testForbiddenAuthenticationFailureIsNotClassifiedAsRateLimit(): void
    {
        $this->assertForbiddenResponseIsNotRetried('invalid API key');
    }

    /**
     * Does not mistake ordinary words containing `rate` for a provider rate limit.
     */
    public function testForbiddenMessageUsesCompleteRateLimitTerms(): void
    {
        $this->assertForbiddenResponseIsNotRetried('corporate access policy denied');
    }

    /**
     * Verifies that one ordinary HTTP 403 response keeps its HTTP classification.
     */
    private function assertForbiddenResponseIsNotRetried(string $message): void
    {
        $transport = new QueueTransport(
            HttpResponseFactory::json(['error' => $message], 403),
            HttpResponseFactory::json(['pendingSize' => 3]),
        );
        $client = new ApiClient(NodeConfiguration::custom(
            'https://node.example',
            retryPolicy: new RetryPolicy(2, 0, 0, 0),
        ), $transport);

        try {
            $client->request(Endpoint::GetPendingTransactionCount->request());
            self::fail('An authorization failure must not be treated as a rate limit.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->statusCode);
            self::assertCount(1, $transport->requests());
        }
    }

    /**
     * Retries an HTTP 403 only when the provider explicitly identifies throttling.
     */
    public function testForbiddenRateLimitResponseUsesBoundedRetry(): void
    {
        $transport = new QueueTransport(
            HttpResponseFactory::json(['error' => 'API rate limit exceeded'], 403),
            HttpResponseFactory::json(['pendingSize' => 3]),
        );
        $client = new ApiClient(NodeConfiguration::custom(
            'https://node.example',
            retryPolicy: new RetryPolicy(2, 0, 0, 0),
        ), $transport);

        self::assertSame(
            3,
            $client->request(Endpoint::GetPendingTransactionCount->request())->value('pendingSize'),
        );
        self::assertCount(2, $transport->requests());
    }

    /**
     * Exposes an exhausted HTTP 429 response as a typed rate-limit exception.
     */
    public function testRateLimitResponseUsesTypedException(): void
    {
        $transport = new QueueTransport(HttpResponseFactory::json(
            ['error' => 'too many requests'],
            429,
            ['Retry-After' => ['1']],
        ));
        $client = new ApiClient(NodeConfiguration::custom(
            'https://node.example',
            retryPolicy: new RetryPolicy(1, 0, 0, 0),
        ), $transport);

        try {
            $client->request(Endpoint::GetPendingTransactionCount->request());
            self::fail('An exhausted rate limit must throw.');
        } catch (RateLimitException $exception) {
            self::assertSame(1, $exception->retryAfterSeconds);
        }
    }

    /**
     * Keeps exponential delay and jitter arithmetic inside the native integer range.
     */
    public function testRetryPolicyCannotOverflowAtThePlatformLimit(): void
    {
        $policy = new RetryPolicy(10, PHP_INT_MAX, PHP_INT_MAX, 100);

        self::assertSame(PHP_INT_MAX, $policy->delayMilliseconds(10, PHP_INT_MAX));
    }

    /**
     * Rejects a negative server Retry-After value supplied by custom integrations.
     */
    public function testRetryPolicyRejectsNegativeRetryAfter(): void
    {
        $this->expectException(ConfigurationException::class);

        (new RetryPolicy())->delayMilliseconds(1, -1);
    }

    /**
     * Rejects malformed JSON instead of returning null or an empty object.
     */
    public function testMalformedJsonIsRejected(): void
    {
        $transport = new QueueTransport(new HttpResponse(200, [], '{broken'));
        $client = new ApiClient(NodeConfiguration::forNetwork(Network::Shasta), $transport);
        $this->expectException(ResponseDecodingException::class);

        $client->request(Endpoint::GetPendingTransactionCount->request());
    }

    /**
     * Converts HTTP-200 node Error envelopes into typed exceptions.
     */
    public function testEmbeddedNodeErrorIsRejected(): void
    {
        $transport = new QueueTransport(HttpResponseFactory::json(['Error' => 'invalid request']));
        $client = new ApiClient(NodeConfiguration::forNetwork(Network::Nile), $transport);
        $this->expectException(NodeException::class);

        $client->request(Endpoint::GetPendingTransactionCount->request());
    }
}
