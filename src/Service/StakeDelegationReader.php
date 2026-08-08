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
use IEXBase\TronAPI\Value\Address;

/**
 * Shares native delegation record/index reads between Stake 1.0 and Stake 2.0.
 */
final readonly class StakeDelegationReader
{
    /**
     * Creates the reader over the role-aware native API client.
     */
    public function __construct(private ApiClient $client)
    {
    }

    /**
     * Returns delegation records from the selected protocol endpoint pair.
     *
     * @return list<array<string, mixed>>
     */
    public function records(
        Address $ownerAddress,
        Address $recipientAddress,
        ConfirmationLevel $level,
        Endpoint $latestEndpoint,
        Endpoint $confirmedEndpoint,
    ): array {
        $endpoint = Endpoint::forConfirmation($level, $latestEndpoint, $confirmedEndpoint);
        $response = $this->client->request($endpoint->request([
            'fromAddress' => $ownerAddress->toBase58(),
            'toAddress' => $recipientAddress->toBase58(),
            'visible' => true,
        ]));

        return DataDecoder::objectList($response->value('delegatedResource') ?? [], 'delegatedResource');
    }

    /**
     * Returns one delegation account index from the selected endpoint pair.
     *
     * @return array<string, mixed>
     */
    public function accountIndex(
        Address $address,
        ConfirmationLevel $level,
        Endpoint $latestEndpoint,
        Endpoint $confirmedEndpoint,
        string $responseName,
    ): array {
        $endpoint = Endpoint::forConfirmation($level, $latestEndpoint, $confirmedEndpoint);

        return DataDecoder::object($this->client->request($endpoint->request([
            'value' => $address->toBase58(),
            'visible' => true,
        ]))->data(), $responseName);
    }
}
