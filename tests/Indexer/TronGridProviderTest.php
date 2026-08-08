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

namespace IEXBase\TronAPI\Tests\Indexer;

use IEXBase\TronAPI\Api\ApiClient;
use IEXBase\TronAPI\Configuration\NodeConfiguration;
use IEXBase\TronAPI\Enum\HttpMethod;
use IEXBase\TronAPI\Enum\SortOrder;
use IEXBase\TronAPI\Exception\NodeException;
use IEXBase\TronAPI\Exception\ValidationException;
use IEXBase\TronAPI\Indexer\EventSearch;
use IEXBase\TronAPI\Indexer\IndexerPage;
use IEXBase\TronAPI\Indexer\PageRequest;
use IEXBase\TronAPI\Indexer\TimestampRange;
use IEXBase\TronAPI\Indexer\TransactionSearch;
use IEXBase\TronAPI\Indexer\TronGridProvider;
use IEXBase\TronAPI\Tests\Support\HttpResponseFactory;
use IEXBase\TronAPI\Tests\Support\QueueTransport;
use IEXBase\TronAPI\Value\Address;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that the optional TronGrid adapter maps neutral searches at one boundary.
 */
#[CoversClass(TronGridProvider::class)]
#[CoversClass(PageRequest::class)]
#[CoversClass(TransactionSearch::class)]
#[CoversClass(EventSearch::class)]
#[CoversClass(TimestampRange::class)]
#[CoversClass(IndexerPage::class)]
final class TronGridProviderTest extends TestCase
{
    private const ACCOUNT = 'TMVQGm1qAQYVdetCeGRRkTWYYrLXuHK2HC';
    private const CONTRACT = 'TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY';

    /**
     * Converts all neutral transaction filters into one TronGrid query.
     */
    public function testTransactionSearchMapsAtProviderBoundary(): void
    {
        $transport = new QueueTransport(HttpResponseFactory::json([
            'success' => true,
            'data' => [['txID' => str_repeat('ab', 32)]],
            'meta' => ['fingerprint' => 'next-page', 'at' => 1_700_000_000_000],
        ]));
        $provider = $this->provider($transport);
        $search = new TransactionSearch(
            new PageRequest(50, 'current-page'),
            confirmed: false,
            outgoingOnly: true,
            contractAddress: Address::fromBase58(self::CONTRACT),
            timestamps: new TimestampRange(100, 200),
            sortOrder: SortOrder::Ascending,
        );
        $page = $provider->transactions(Address::fromBase58(self::ACCOUNT), $search);

        self::assertCount(1, $page);
        self::assertSame('next-page', $page->nextCursor);
        self::assertSame(1_700_000_000_000, $page->generatedAtMilliseconds);
        self::assertSame(HttpMethod::Get, $transport->request()->method);
        self::assertSame(
            'https://indexer.example/v1/accounts/' . self::ACCOUNT . '/transactions',
            $transport->request()->uri,
        );
        self::assertSame([
            'limit' => 50,
            'fingerprint' => 'current-page',
            'order_by' => 'block_timestamp,asc',
            'only_unconfirmed' => 'true',
            'only_from' => 'true',
            'contract_address' => self::CONTRACT,
            'min_timestamp' => 100,
            'max_timestamp' => 200,
        ], $transport->request()->parameters);
    }

    /**
     * Maps event filtering and its timestamp field names independently of native APIs.
     */
    public function testContractEventSearchUsesIndexerRole(): void
    {
        $transport = new QueueTransport(HttpResponseFactory::json([
            'success' => true,
            'data' => [],
            'meta' => [],
        ]));
        $provider = $this->provider($transport);
        $search = new EventSearch(
            new PageRequest(10),
            'Transfer',
            true,
            new TimestampRange(300, 400),
        );

        $page = $provider->contractEvents(Address::fromBase58(self::CONTRACT), $search);

        self::assertCount(0, $page);
        self::assertSame([
            'limit' => 10,
            'order_by' => 'block_timestamp,desc',
            'only_confirmed' => 'true',
            'event_name' => 'Transfer',
            'min_block_timestamp' => 300,
            'max_block_timestamp' => 400,
        ], $transport->request()->parameters);
    }

    /**
     * Rejects the provider's success=false envelope rather than returning an empty page.
     */
    public function testRejectedProviderEnvelopeThrows(): void
    {
        $provider = $this->provider(new QueueTransport(HttpResponseFactory::json([
            'success' => false,
            'data' => [],
        ])));
        $this->expectException(NodeException::class);

        $provider->trc20Balances(Address::fromBase58(self::ACCOUNT), new PageRequest());
    }

    /**
     * Rejects contradictory direction filters before any provider request.
     */
    public function testContradictoryDirectionsAreRejected(): void
    {
        $this->expectException(ValidationException::class);

        new TransactionSearch(outgoingOnly: true, incomingOnly: true);
    }

    /**
     * Creates the optional adapter over a separately configured indexer URI.
     */
    private function provider(QueueTransport $transport): TronGridProvider
    {
        $configuration = NodeConfiguration::custom(
            'https://full-node.example',
            indexerUri: 'https://indexer.example',
        );

        return new TronGridProvider(new ApiClient($configuration, $transport));
    }
}
