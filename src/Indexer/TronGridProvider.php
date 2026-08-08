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

namespace IEXBase\TronAPI\Indexer;

use IEXBase\TronAPI\Api\ApiClient;
use IEXBase\TronAPI\Api\ApiResponse;
use IEXBase\TronAPI\Api\DataDecoder;
use IEXBase\TronAPI\Api\Endpoint;
use IEXBase\TronAPI\Encoding\Hex;
use IEXBase\TronAPI\Enum\SortOrder;
use IEXBase\TronAPI\Exception\NodeException;
use IEXBase\TronAPI\Exception\ValidationException;
use IEXBase\TronAPI\Value\Address;

/**
 * Adapts the default TronGrid v1 schema to provider-neutral indexer contracts.
 *
 * No native account, block, transaction, staking, or contract service depends
 * on this adapter. Applications may replace it with any implementation of the
 * three small provider interfaces without changing node configuration.
 */
final readonly class TronGridProvider implements
    AccountHistoryProviderInterface,
    EventProviderInterface,
    TokenIndexProviderInterface
{
    /**
     * Creates an adapter over the generic role-aware API client.
     */
    public function __construct(private ApiClient $client)
    {
    }

    /**
     * Returns the first indexed account record or null when no record exists.
     */
    public function account(Address $address): ?array
    {
        $response = $this->client->request(Endpoint::GetIndexedAccount->request(
            pathParameters: ['address' => $address->toBase58()],
        ));
        $items = $this->acceptedItems($response);

        return $items[0] ?? null;
    }

    /**
     * Returns native account transaction history.
     */
    public function transactions(Address $address, TransactionSearch $search): IndexerPage
    {
        return $this->page(
            Endpoint::GetIndexedAccountTransactions,
            $this->transactionParameters($search),
            ['address' => $address->toBase58()],
        );
    }

    /**
     * Returns TRC-20 account transfer history.
     */
    public function trc20Transactions(Address $address, TransactionSearch $search): IndexerPage
    {
        return $this->page(
            Endpoint::GetIndexedAccountTrc20Transactions,
            $this->transactionParameters($search),
            ['address' => $address->toBase58()],
        );
    }

    /**
     * Returns indexed internal transactions for one account.
     */
    public function internalTransactions(Address $address, PageRequest $page): IndexerPage
    {
        return $this->page(
            Endpoint::GetIndexedAccountInternalTransactions,
            $this->pageParameters($page),
            ['address' => $address->toBase58()],
        );
    }

    /**
     * Returns indexed internal transactions for one outer transaction.
     */
    public function transactionInternalTransactions(string $transactionId, PageRequest $page): IndexerPage
    {
        return $this->transactionPage(
            Endpoint::GetIndexedTransactionInternalTransactions,
            $transactionId,
            $page,
        );
    }

    /**
     * Returns every indexed event emitted by one transaction.
     */
    public function transactionEvents(string $transactionId, PageRequest $page): IndexerPage
    {
        return $this->transactionPage(
            Endpoint::GetIndexedTransactionEvents,
            $transactionId,
            $page,
        );
    }

    /**
     * Returns indexed events emitted by one contract.
     */
    public function contractEvents(Address $contractAddress, EventSearch $search): IndexerPage
    {
        return $this->page(
            Endpoint::GetIndexedContractEvents,
            $this->eventParameters($search),
            ['address' => $contractAddress->toBase58()],
        );
    }

    /**
     * Returns indexed events emitted in one block.
     */
    public function blockEvents(int $blockNumber, EventSearch $search): IndexerPage
    {
        if ($blockNumber < 0) {
            throw new ValidationException('A block number cannot be negative.');
        }

        return $this->page(
            Endpoint::GetIndexedBlockEvents,
            $this->eventParameters($search),
            ['blockNumber' => $blockNumber],
        );
    }

    /**
     * Returns events from the provider's latest indexed block.
     */
    public function latestBlockEvents(EventSearch $search): IndexerPage
    {
        return $this->page(Endpoint::GetIndexedLatestBlockEvents, $this->eventParameters($search));
    }

    /**
     * Returns indexed TRC-20 account balances.
     */
    public function trc20Balances(Address $address, PageRequest $page): IndexerPage
    {
        return $this->page(
            Endpoint::GetIndexedAccountTrc20Balances,
            $this->pageParameters($page),
            ['address' => $address->toBase58()],
        );
    }

    /**
     * Returns indexed TRC-20 metadata.
     */
    public function trc20Information(PageRequest $page): IndexerPage
    {
        return $this->page(Endpoint::GetIndexedTrc20Information, $this->pageParameters($page));
    }

    /**
     * Returns indexed tokens associated with a contract.
     */
    public function contractTokens(Address $contractAddress, PageRequest $page): IndexerPage
    {
        return $this->page(
            Endpoint::GetIndexedContractTokens,
            $this->pageParameters($page),
            ['contractAddress' => $contractAddress->toBase58()],
        );
    }

    /**
     * Requests one v1 page and validates the provider's success envelope.
     *
     * @param array<string, mixed>      $parameters Query parameters.
     * @param array<string, int|string> $pathParameters Endpoint placeholders.
     */
    private function page(Endpoint $endpoint, array $parameters, array $pathParameters = []): IndexerPage
    {
        $response = $this->client->request($endpoint->request($parameters, $pathParameters));
        $items = $this->acceptedItems($response);
        $metadata = DataDecoder::object($response->value('meta') ?? [], 'meta');
        $cursor = DataDecoder::optionalString($metadata['fingerprint'] ?? null, 'meta.fingerprint');
        $generatedAt = isset($metadata['at'])
            ? DataDecoder::integer($metadata['at'], 'meta.at')
            : null;

        return new IndexerPage($items, $cursor, $generatedAt, $metadata);
    }

    /**
     * Returns one page whose path is scoped by a canonical transaction ID.
     */
    private function transactionPage(Endpoint $endpoint, string $transactionId, PageRequest $page): IndexerPage
    {
        return $this->page(
            $endpoint,
            $this->pageParameters($page),
            ['transactionId' => Hex::canonicalize($transactionId, 32)],
        );
    }

    /**
     * Requires `success=true` and returns the provider's ordered data records.
     *
     * @return list<array<string, mixed>>
     */
    private function acceptedItems(ApiResponse $response): array
    {
        if ($response->value('success') !== true) {
            throw new NodeException('The indexed-data provider rejected the request.');
        }

        return DataDecoder::objectList($response->value('data') ?? [], 'data');
    }

    /**
     * Converts neutral pagination names to the TronGrid fingerprint schema.
     *
     * @return array<string, int|string>
     */
    private function pageParameters(PageRequest $page): array
    {
        $parameters = ['limit' => $page->limit];
        if ($page->cursor !== null) {
            $parameters['fingerprint'] = $page->cursor;
        }

        return $parameters;
    }

    /**
     * Converts neutral transaction filters to documented TronGrid query fields.
     *
     * @return array<string, int|string>
     */
    private function transactionParameters(TransactionSearch $search): array
    {
        $parameters = $this->orderedParameters($search->page, $search->sortOrder, $search->confirmed);
        if ($search->outgoingOnly) {
            $parameters['only_from'] = 'true';
        }
        if ($search->incomingOnly) {
            $parameters['only_to'] = 'true';
        }
        if ($search->contractAddress !== null) {
            $parameters['contract_address'] = $search->contractAddress->toBase58();
        }
        if ($search->timestamps->minimumMilliseconds !== null) {
            $parameters['min_timestamp'] = $search->timestamps->minimumMilliseconds;
        }
        if ($search->timestamps->maximumMilliseconds !== null) {
            $parameters['max_timestamp'] = $search->timestamps->maximumMilliseconds;
        }

        return $parameters;
    }

    /**
     * Converts neutral event filters to documented TronGrid query fields.
     *
     * @return array<string, int|string>
     */
    private function eventParameters(EventSearch $search): array
    {
        $parameters = $this->orderedParameters($search->page, $search->sortOrder, $search->confirmed);

        if ($search->eventName !== null) {
            $parameters['event_name'] = $search->eventName;
        }
        if ($search->timestamps->minimumMilliseconds !== null) {
            $parameters['min_block_timestamp'] = $search->timestamps->minimumMilliseconds;
        }
        if ($search->timestamps->maximumMilliseconds !== null) {
            $parameters['max_block_timestamp'] = $search->timestamps->maximumMilliseconds;
        }

        return $parameters;
    }

    /**
     * Converts shared pagination, ordering, and confirmation filters once.
     *
     * @return array<string, int|string>
     */
    private function orderedParameters(PageRequest $page, SortOrder $sortOrder, ?bool $confirmed): array
    {
        $parameters = [
            ...$this->pageParameters($page),
            'order_by' => 'block_timestamp,' . $sortOrder->value,
        ];

        if ($confirmed !== null) {
            $parameters[$confirmed ? 'only_confirmed' : 'only_unconfirmed'] = 'true';
        }

        return $parameters;
    }
}
