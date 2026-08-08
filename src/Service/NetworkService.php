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
use IEXBase\TronAPI\Model\NodeHealth;
use IEXBase\TronAPI\Value\Amount;

/**
 * Reads node topology, chain parameters, resource prices, burn, and sync health.
 */
final readonly class NetworkService
{
    /**
     * Creates the service from native node and block clients.
     */
    public function __construct(
        private ApiClient $client,
        private BlockService $blocks,
    ) {
    }

    /**
     * Returns node runtime and synchronization information for one state role.
     *
     * @return array<string, mixed>
     */
    public function nodeInfo(ConfirmationLevel $level = ConfirmationLevel::Latest): array
    {
        $endpoint = Endpoint::forConfirmation($level, Endpoint::GetNodeInfo, Endpoint::GetConfirmedNodeInfo);

        return DataDecoder::object($this->client->request($endpoint->request())->data(), 'node information');
    }

    /**
     * Returns peer connection records reported by the FullNode.
     *
     * @return list<array<string, mixed>>
     */
    public function peers(): array
    {
        $response = $this->client->request(Endpoint::ListNodes->request());

        return DataDecoder::objectList($response->value('nodes') ?? [], 'nodes');
    }

    /**
     * Returns chain parameters indexed by their documented unique keys.
     *
     * @return array<string, string>
     */
    public function chainParameters(): array
    {
        $response = $this->client->request(Endpoint::GetChainParameters->request());
        $records = DataDecoder::objectList($response->value('chainParameter') ?? [], 'chainParameter');
        $parameters = [];

        foreach ($records as $position => $record) {
            $key = DataDecoder::string($record['key'] ?? null, sprintf('chainParameter[%d].key', $position));
            if (isset($parameters[$key])) {
                throw new ValidationException(sprintf('Chain parameter `%s` is duplicated.', $key));
            }
            $parameters[$key] = DataDecoder::signedDecimal(
                $record['value'] ?? 0,
                sprintf('chainParameter[%d].value', $position),
            );
        }

        return $parameters;
    }

    /**
     * Returns the native historical Energy price series string.
     */
    public function energyPrices(ConfirmationLevel $level = ConfirmationLevel::Latest): string
    {
        return $this->stateString(
            $level,
            Endpoint::GetEnergyPrices,
            Endpoint::GetConfirmedEnergyPrices,
            'prices',
        );
    }

    /**
     * Returns the native historical Bandwidth price series string.
     */
    public function bandwidthPrices(ConfirmationLevel $level = ConfirmationLevel::Latest): string
    {
        return $this->stateString(
            $level,
            Endpoint::GetBandwidthPrices,
            Endpoint::GetConfirmedBandwidthPrices,
            'prices',
        );
    }

    /**
     * Returns the current chain-governed Bandwidth unit price as exact sun.
     */
    public function bandwidthUnitPrice(): Amount
    {
        return $this->chainParameterAmount('getTransactionFee');
    }

    /**
     * Returns the current chain-governed Energy unit price as exact sun.
     */
    public function energyUnitPrice(): Amount
    {
        return $this->chainParameterAmount('getEnergyFee');
    }

    /**
     * Returns the native historical memo-fee series string from FullNode state.
     */
    public function memoFees(): string
    {
        return $this->endpointString(Endpoint::GetMemoFee, 'prices');
    }

    /**
     * Returns total burned TRX from latest or irreversible chain state.
     */
    public function burnedTrx(ConfirmationLevel $level = ConfirmationLevel::Confirmed): Amount
    {
        $endpoint = Endpoint::forConfirmation($level, Endpoint::GetBurnedTrx, Endpoint::GetConfirmedBurnedTrx);
        $response = $this->client->request($endpoint->request());

        return Amount::fromAtomic(DataDecoder::unsignedDecimal(
            $response->value('burnTrxAmount') ?? 0,
            'burnTrxAmount',
        ));
    }

    /**
     * Compares FullNode and SolidityNode heights without assuming the same host.
     */
    public function health(int $maximumHealthyLag = 64): NodeHealth
    {
        if ($maximumHealthyLag < 0) {
            throw new ValidationException('Maximum healthy node lag cannot be negative.');
        }

        return new NodeHealth(
            $this->blocks->latest(ConfirmationLevel::Latest)->number,
            $this->blocks->latest(ConfirmationLevel::Confirmed)->number,
            $maximumHealthyLag,
        );
    }

    /**
     * Reads one string field from latest or confirmed native state.
     */
    private function stateString(
        ConfirmationLevel $level,
        Endpoint $latestEndpoint,
        Endpoint $confirmedEndpoint,
        string $field,
    ): string {
        return $this->endpointString(
            Endpoint::forConfirmation($level, $latestEndpoint, $confirmedEndpoint),
            $field,
        );
    }

    /**
     * Returns one required non-negative chain parameter as a TRX-denominated amount.
     */
    private function chainParameterAmount(string $key): Amount
    {
        $parameters = $this->chainParameters();
        if (!array_key_exists($key, $parameters)) {
            throw new ValidationException(sprintf('Chain parameter `%s` is unavailable.', $key));
        }

        return Amount::fromAtomic(DataDecoder::unsignedDecimal($parameters[$key], $key));
    }

    /**
     * Reads one required string field from a native endpoint.
     */
    private function endpointString(Endpoint $endpoint, string $field): string
    {
        return DataDecoder::string(
            $this->client->request($endpoint->request())->value($field) ?? '',
            $field,
        );
    }
}
