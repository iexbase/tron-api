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
use IEXBase\TronAPI\Asset\AssetIssuanceRequest;
use IEXBase\TronAPI\Asset\AssetUpdateRequest;
use IEXBase\TronAPI\Enum\ConfirmationLevel;
use IEXBase\TronAPI\Exception\ValidationException;
use IEXBase\TronAPI\Model\Asset;
use IEXBase\TronAPI\Transaction\Transaction;
use IEXBase\TronAPI\Transaction\TransactionFactory;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\Amount;
use IEXBase\TronAPI\Value\Memo;

/**
 * Reads TRC-10 metadata and builds precision-safe native asset transfers.
 */
final readonly class AssetService
{
    /**
     * Creates the service over native nodes and the verified transaction factory.
     */
    public function __construct(
        private ApiClient $client,
        private TransactionFactory $transactions,
    ) {
    }

    /**
     * Returns a TRC-10 asset by numeric ID, or null when no asset exists.
     */
    public function byId(
        string $assetId,
        ConfirmationLevel $level = ConfirmationLevel::Confirmed,
    ): ?Asset {
        if (preg_match('/^[1-9][0-9]*$/D', $assetId) !== 1) {
            throw new ValidationException('A TRC-10 asset ID must be a positive decimal integer.');
        }

        $endpoint = Endpoint::forConfirmation($level, Endpoint::GetAssetById, Endpoint::GetConfirmedAssetById);
        $data = $this->client->request($endpoint->request([
            'value' => $assetId,
            'visible' => true,
        ]))->data();

        return $data === [] ? null : Asset::fromNodeData(DataDecoder::object($data, 'asset'));
    }

    /**
     * Returns every TRC-10 asset matching an exact UTF-8 name.
     *
     * @return list<Asset>
     */
    public function byName(
        string $name,
        ConfirmationLevel $level = ConfirmationLevel::Confirmed,
    ): array {
        $endpoint = Endpoint::forConfirmation(
            $level,
            Endpoint::GetAssetsByName,
            Endpoint::GetConfirmedAssetsByName,
        );
        $response = $this->client->request($endpoint->request([
            'value' => $name,
            'visible' => true,
        ]));

        return $this->assetList($response->value('assetIssue') ?? []);
    }

    /**
     * Returns a bounded native asset metadata page using offset pagination.
     *
     * @return list<Asset>
     */
    public function list(
        int $offset = 0,
        int $limit = 20,
        ConfirmationLevel $level = ConfirmationLevel::Confirmed,
    ): array {
        if ($offset < 0 || $limit < 1 || $limit > 200) {
            throw new ValidationException('A TRC-10 asset page requires a non-negative offset and limit from 1 to 200.');
        }

        $endpoint = Endpoint::forConfirmation(
            $level,
            Endpoint::ListAssetsPaginated,
            Endpoint::ListConfirmedAssetsPaginated,
        );
        $response = $this->client->request($endpoint->request([
            'offset' => $offset,
            'limit' => $limit,
            'visible' => true,
        ]));

        return $this->assetList($response->value('assetIssue') ?? []);
    }

    /**
     * Returns TRC-10 assets issued by one account from latest FullNode state.
     *
     * @return list<Asset>
     */
    public function issuedBy(Address $issuerAddress): array
    {
        $response = $this->client->request(Endpoint::GetAssetByAccount->request([
            'address' => $issuerAddress->toBase58(),
            'visible' => true,
        ]));

        return $this->assetList($response->value('assetIssue') ?? []);
    }

    /**
     * Builds a verified TRC-10 transfer with asset-specific decimal validation.
     */
    public function createTransfer(
        Address $ownerAddress,
        Address $recipientAddress,
        Asset $asset,
        Amount $amount,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        if ($amount->decimals() !== $asset->precision || $amount->isZero()) {
            throw new ValidationException('A TRC-10 transfer requires a positive amount matching asset precision.');
        }

        $fields = [
            'to_address' => $recipientAddress,
            'asset_name' => $asset->id,
            'amount' => $amount->atomicInteger(),
        ];

        return $this->transactions->createNativeContract(
            Endpoint::TransferAsset,
            'TransferAssetContract',
            $ownerAddress,
            $fields,
            $memo,
            $permissionId,
        );
    }

    /**
     * Builds a verified native TRC-10 issuance transaction.
     */
    public function createIssuance(
        Address $ownerAddress,
        AssetIssuanceRequest $request,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        $fields = $request->fields();

        return $this->transactions->createNativeContract(
            Endpoint::CreateAsset,
            'AssetIssueContract',
            $ownerAddress,
            $fields,
            $memo,
            $permissionId,
            omittableFields: $request->totalSupply->decimals() === 0 ? ['precision'] : [],
        );
    }

    /**
     * Builds a verified transaction that buys a TRC-10 asset during issuance.
     */
    public function participate(
        Address $ownerAddress,
        Address $issuerAddress,
        Asset $asset,
        Amount $trxAmount,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        if ($trxAmount->decimals() !== Amount::TRX_DECIMALS || $trxAmount->isZero()) {
            throw new ValidationException('TRC-10 participation requires a positive TRX amount with six decimals.');
        }

        $fields = [
            'to_address' => $issuerAddress,
            'asset_name' => $asset->id,
            'amount' => $trxAmount->atomicInteger(),
        ];

        return $this->transactions->createNativeContract(
            Endpoint::ParticipateAsset,
            'ParticipateAssetIssueContract',
            $ownerAddress,
            $fields,
            $memo,
            $permissionId,
        );
    }

    /**
     * Builds a transaction that releases every eligible frozen issuance portion.
     */
    public function unfreeze(
        Address $ownerAddress,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        return $this->transactions->createNativeContract(
            Endpoint::UnfreezeAsset,
            'UnfreezeAssetContract',
            $ownerAddress,
            [],
            $memo,
            $permissionId,
        );
    }

    /**
     * Builds a verified transaction that updates mutable TRC-10 metadata and quotas.
     */
    public function update(
        Address $ownerAddress,
        AssetUpdateRequest $request,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        $fields = $request->fields();

        return $this->transactions->createNativeContract(
            Endpoint::UpdateAsset,
            'UpdateAssetContract',
            $ownerAddress,
            $fields,
            $memo,
            $permissionId,
        );
    }

    /**
     * Parses repeated native asset objects into typed metadata values.
     *
     * @return list<Asset>
     */
    private function assetList(mixed $value): array
    {
        return array_map(
            static fn (array $data): Asset => Asset::fromNodeData($data),
            DataDecoder::objectList($value, 'assetIssue'),
        );
    }
}
