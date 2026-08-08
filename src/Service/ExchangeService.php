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

namespace IEXBase\TronAPI\Service;

use IEXBase\TronAPI\Api\ApiClient;
use IEXBase\TronAPI\Api\DataDecoder;
use IEXBase\TronAPI\Api\Endpoint;
use IEXBase\TronAPI\Enum\ConfirmationLevel;
use IEXBase\TronAPI\Exception\ValidationException;
use IEXBase\TronAPI\Model\Exchange;
use IEXBase\TronAPI\Support\IntegerHelper;
use IEXBase\TronAPI\Transaction\Transaction;
use IEXBase\TronAPI\Transaction\TransactionFactory;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\Memo;
use IEXBase\TronAPI\Value\NativeAssetAmount;

/**
 * Reads and operates protocol-native TRX/TRC-10 bonding-curve exchanges.
 */
final readonly class ExchangeService
{
    /**
     * Creates the service over native nodes and the shared transaction factory.
     */
    public function __construct(
        private ApiClient $client,
        private TransactionFactory $transactions,
    ) {
    }

    /**
     * Returns every native exchange at the selected consistency level.
     *
     * @return list<Exchange>
     */
    public function list(ConfirmationLevel $level = ConfirmationLevel::Confirmed): array
    {
        $endpoint = Endpoint::forConfirmation($level, Endpoint::ListExchanges, Endpoint::ListConfirmedExchanges);
        $response = $this->client->request($endpoint->request());

        return $this->exchanges($response->value('exchanges') ?? []);
    }

    /**
     * Returns one native exchange by ID, or null when absent.
     */
    public function find(
        int $exchangeId,
        ConfirmationLevel $level = ConfirmationLevel::Confirmed,
    ): ?Exchange {
        IntegerHelper::nonNegative($exchangeId, 'Exchange ID');
        $endpoint = Endpoint::forConfirmation($level, Endpoint::GetExchange, Endpoint::GetConfirmedExchange);
        $data = $this->client->request($endpoint->request(['id' => $exchangeId]))->data();

        return $data === [] ? null : Exchange::fromNodeData(DataDecoder::object($data, 'exchange'));
    }

    /**
     * Returns a bounded latest-state page of native exchanges.
     *
     * @return list<Exchange>
     */
    public function page(int $offset = 0, int $limit = 20): array
    {
        IntegerHelper::nonNegative($offset, 'Exchange page offset');
        IntegerHelper::between($limit, 1, 200, 'Exchange page limit');
        $response = $this->client->request(Endpoint::ListExchangesPaginated->request([
            'offset' => $offset,
            'limit' => $limit,
        ]));

        return $this->exchanges($response->value('exchanges') ?? []);
    }

    /**
     * Builds a transaction that creates a new native exchange pair.
     */
    public function create(
        Address $ownerAddress,
        NativeAssetAmount $first,
        NativeAssetAmount $second,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        if ($first->assetId->equals($second->assetId)) {
            throw new ValidationException('A native exchange requires two different asset identifiers.');
        }

        $fields = [
            'first_token_id' => $first->assetId->value(),
            'first_token_balance' => $first->amount->atomicInteger(),
            'second_token_id' => $second->assetId->value(),
            'second_token_balance' => $second->amount->atomicInteger(),
        ];

        return $this->transactions->createNativeContract(
            Endpoint::CreateExchange,
            'ExchangeCreateContract',
            $ownerAddress,
            $fields,
            $memo,
            $permissionId,
        );
    }

    /**
     * Builds a transaction that adds one side's amount and its computed counterpart.
     */
    public function inject(
        Address $ownerAddress,
        int $exchangeId,
        NativeAssetAmount $asset,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        return $this->liquidityTransaction(
            Endpoint::InjectExchangeLiquidity,
            'ExchangeInjectContract',
            $ownerAddress,
            $exchangeId,
            $asset,
            $memo,
            $permissionId,
        );
    }

    /**
     * Builds a transaction that removes one side's amount and its computed counterpart.
     */
    public function withdraw(
        Address $ownerAddress,
        int $exchangeId,
        NativeAssetAmount $asset,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        return $this->liquidityTransaction(
            Endpoint::WithdrawExchangeLiquidity,
            'ExchangeWithdrawContract',
            $ownerAddress,
            $exchangeId,
            $asset,
            $memo,
            $permissionId,
        );
    }

    /**
     * Builds an inject or withdrawal transaction from one shared liquidity schema.
     */
    private function liquidityTransaction(
        Endpoint $endpoint,
        string $contractType,
        Address $ownerAddress,
        int $exchangeId,
        NativeAssetAmount $asset,
        ?Memo $memo,
        int $permissionId,
    ): Transaction {
        return $this->transactions->createNativeContract(
            $endpoint,
            $contractType,
            $ownerAddress,
            $this->liquidityFields($exchangeId, $asset),
            $memo,
            $permissionId,
        );
    }

    /**
     * Builds a swap with an explicit minimum amount for the other pair asset.
     */
    public function trade(
        Address $ownerAddress,
        int $exchangeId,
        NativeAssetAmount $sold,
        NativeAssetAmount $minimumReceived,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        IntegerHelper::nonNegative($exchangeId, 'Exchange ID');
        if ($sold->assetId->equals($minimumReceived->assetId)) {
            throw new ValidationException('An exchange trade must sell and receive different assets.');
        }
        $fields = [
            'exchange_id' => $exchangeId,
            'token_id' => $sold->assetId->value(),
            'quant' => $sold->amount->atomicInteger(),
            'expected' => $minimumReceived->amount->atomicInteger(),
        ];

        return $this->transactions->createNativeContract(
            Endpoint::TradeExchange,
            'ExchangeTransactionContract',
            $ownerAddress,
            $fields,
            $memo,
            $permissionId,
        );
    }

    /**
     * Returns shared inject/withdraw fields after validating the exchange ID.
     *
     * @return array{exchange_id: int, token_id: string, quant: int}
     */
    private function liquidityFields(int $exchangeId, NativeAssetAmount $asset): array
    {
        IntegerHelper::nonNegative($exchangeId, 'Exchange ID');

        return [
            'exchange_id' => $exchangeId,
            'token_id' => $asset->assetId->value(),
            'quant' => $asset->amount->atomicInteger(),
        ];
    }

    /**
     * Parses a native repeated exchange field into typed values.
     *
     * @return list<Exchange>
     */
    private function exchanges(mixed $value): array
    {
        return array_map(
            static fn (array $data): Exchange => Exchange::fromNodeData($data),
            DataDecoder::objectList($value, 'exchanges'),
        );
    }
}
