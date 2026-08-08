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

namespace IEXBase\TronAPI\Model;

use IEXBase\TronAPI\Api\DataDecoder;
use IEXBase\TronAPI\Exception\ResponseDecodingException;
use IEXBase\TronAPI\Value\Address;
use JsonSerializable;

/**
 * Represents an on-chain governance proposal and its approval set.
 */
final readonly class Proposal implements JsonSerializable
{
    /** @var array<int, string> */
    private array $parameters;

    /** @var list<Address> */
    private array $approvals;

    /** @var array<string, mixed> */
    private array $rawData;

    /**
     * Stores exact proposal parameters, timestamps, state, and approving SRs.
     *
     * @param array<int, string>   $parameters Values indexed by chain parameter ID.
     * @param list<Address>        $approvals Approving SR addresses.
     * @param array<string, mixed> $rawData Complete proposal response.
     */
    private function __construct(
        public int $id,
        public Address $proposerAddress,
        array $parameters,
        public int $createdAtMilliseconds,
        public int $expiresAtMilliseconds,
        array $approvals,
        public string $state,
        array $rawData,
    ) {
        $this->parameters = $parameters;
        $this->approvals = $approvals;
        $this->rawData = $rawData;
    }

    /**
     * Creates a proposal from a native FullNode response.
     *
     * @param array<string, mixed> $data Native proposal object.
     */
    public static function fromNodeData(array $data): self
    {
        $parameters = [];
        foreach (DataDecoder::objectList($data['parameters'] ?? [], 'proposal.parameters') as $position => $parameter) {
            $key = DataDecoder::integer($parameter['key'] ?? null, sprintf('proposal.parameters[%d].key', $position));
            if (isset($parameters[$key])) {
                throw new ResponseDecodingException(sprintf('Proposal parameter ID %d is duplicated.', $key));
            }
            $parameters[$key] = DataDecoder::signedDecimal(
                $parameter['value'] ?? null,
                sprintf('proposal.parameters[%d].value', $position),
            );
        }

        $approvalValues = $data['approvals'] ?? [];
        if (!is_array($approvalValues) || !array_is_list($approvalValues)) {
            throw new ResponseDecodingException('The proposal approvals field must be a list.');
        }
        $approvals = array_map(
            static fn (mixed $value): Address => Address::fromString(
                DataDecoder::string($value, 'proposal.approvals[]'),
            ),
            $approvalValues,
        );
        $state = DataDecoder::string($data['state'] ?? 'PENDING', 'proposal.state');
        if ($state === '') {
            throw new ResponseDecodingException('A proposal state cannot be empty.');
        }

        return new self(
            DataDecoder::integer($data['proposal_id'] ?? null, 'proposal.proposal_id'),
            Address::fromString(DataDecoder::string($data['proposer_address'] ?? null, 'proposal.proposer_address')),
            $parameters,
            DataDecoder::integer($data['create_time'] ?? null, 'proposal.create_time'),
            DataDecoder::integer($data['expiration_time'] ?? null, 'proposal.expiration_time'),
            $approvals,
            $state,
            $data,
        );
    }

    /**
     * Returns proposed signed values indexed by chain parameter ID.
     *
     * @return array<int, string>
     */
    public function parameters(): array
    {
        return $this->parameters;
    }

    /**
     * Returns the SR addresses that currently approve the proposal.
     *
     * @return list<Address>
     */
    public function approvals(): array
    {
        return $this->approvals;
    }

    /**
     * Returns the untouched proposal response.
     *
     * @return array<string, mixed>
     */
    public function rawData(): array
    {
        return $this->rawData;
    }

    /**
     * Serializes the original lossless proposal response.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->rawData;
    }
}
