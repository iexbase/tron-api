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
use IEXBase\TronAPI\Encoding\Hex;
use IEXBase\TronAPI\Enum\ConfirmationLevel;
use IEXBase\TronAPI\Exception\ValidationException;
use IEXBase\TronAPI\Model\Block;

/**
 * Reads latest and irreversible blocks without conflating their confirmation state.
 */
final readonly class BlockService
{
    /**
     * Creates a block service over the role-aware API client.
     */
    public function __construct(private ApiClient $client)
    {
    }

    /**
     * Returns the newest block visible at the selected confirmation level.
     */
    public function latest(ConfirmationLevel $level = ConfirmationLevel::Confirmed): Block
    {
        $endpoint = Endpoint::forConfirmation(
            $level,
            Endpoint::GetLatestBlock,
            Endpoint::GetConfirmedLatestBlock,
        );

        return $this->block($endpoint, ['visible' => true], $level);
    }

    /**
     * Returns a block by non-negative height, or null when it is unavailable.
     */
    public function byNumber(
        int $number,
        ConfirmationLevel $level = ConfirmationLevel::Confirmed,
    ): ?Block {
        if ($number < 0) {
            throw new ValidationException('A block number cannot be negative.');
        }

        $endpoint = Endpoint::forConfirmation(
            $level,
            Endpoint::GetBlockByNumber,
            Endpoint::GetConfirmedBlockByNumber,
        );

        return $this->optionalBlock($endpoint, ['num' => $number, 'visible' => true], $level);
    }

    /**
     * Returns a block by its exact 32-byte identifier, or null when unavailable.
     */
    public function byId(
        string $blockId,
        ConfirmationLevel $level = ConfirmationLevel::Confirmed,
    ): ?Block {
        $endpoint = Endpoint::forConfirmation($level, Endpoint::GetBlockById, Endpoint::GetConfirmedBlockById);

        return $this->optionalBlock($endpoint, [
            'value' => Hex::canonicalize($blockId, 32),
            'visible' => true,
        ], $level);
    }

    /**
     * Returns a bounded sequence of the newest blocks in node-provided order.
     *
     * @return list<Block>
     */
    public function latestCount(
        int $count,
        ConfirmationLevel $level = ConfirmationLevel::Confirmed,
    ): array {
        if ($count < 1 || $count > 100) {
            throw new ValidationException('The latest block count must be between 1 and 100.');
        }

        $endpoint = Endpoint::forConfirmation(
            $level,
            Endpoint::GetLatestBlocks,
            Endpoint::GetConfirmedLatestBlocks,
        );

        return $this->blockList($endpoint, ['num' => $count, 'visible' => true], $level);
    }

    /**
     * Returns blocks in the half-open interval `[start, end)` with bounded size.
     *
     * @return list<Block>
     */
    public function range(
        int $start,
        int $end,
        ConfirmationLevel $level = ConfirmationLevel::Confirmed,
    ): array {
        if ($start < 0 || $end <= $start || $end - $start > 100) {
            throw new ValidationException('A block range must be positive, ordered, and contain at most 100 blocks.');
        }

        $endpoint = Endpoint::forConfirmation($level, Endpoint::GetBlockRange, Endpoint::GetConfirmedBlockRange);

        return $this->blockList($endpoint, [
            'startNum' => $start,
            'endNum' => $end,
            'visible' => true,
        ], $level);
    }

    /**
     * Parses a required single-block response.
     *
     * @param array<string, mixed> $parameters Endpoint request fields.
     */
    private function block(Endpoint $endpoint, array $parameters, ConfirmationLevel $level): Block
    {
        $data = DataDecoder::object($this->client->request($endpoint->request($parameters))->data(), 'block');

        return Block::fromNodeData($data, $level->isConfirmed());
    }

    /**
     * Parses an optional block response where an empty object means not found.
     *
     * @param array<string, mixed> $parameters Endpoint request fields.
     */
    private function optionalBlock(Endpoint $endpoint, array $parameters, ConfirmationLevel $level): ?Block
    {
        $data = $this->client->request($endpoint->request($parameters))->data();

        return $data === []
            ? null
            : Block::fromNodeData(DataDecoder::object($data, 'block'), $level->isConfirmed());
    }

    /**
     * Parses the shared `{block: [...]}` response used by range endpoints.
     *
     * @param array<string, mixed> $parameters Endpoint request fields.
     * @return list<Block>
     */
    private function blockList(Endpoint $endpoint, array $parameters, ConfirmationLevel $level): array
    {
        $response = $this->client->request($endpoint->request($parameters));
        $blocks = DataDecoder::objectList($response->value('block') ?? [], 'block');

        return array_map(
            static fn (array $data): Block => Block::fromNodeData(
                $data,
                $level->isConfirmed(),
            ),
            $blocks,
        );
    }
}
