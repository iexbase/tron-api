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
use IEXBase\TronAPI\Exception\ValidationException;
use IEXBase\TronAPI\Governance\ProposalParameter;
use IEXBase\TronAPI\Model\Proposal;
use IEXBase\TronAPI\Support\IntegerHelper;
use IEXBase\TronAPI\Transaction\Transaction;
use IEXBase\TronAPI\Transaction\TransactionFactory;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\Memo;

/**
 * Reads TRON governance state and builds verified proposal transactions.
 */
final readonly class GovernanceService
{
    /**
     * Creates the service over the FullNode and shared transaction factory.
     */
    public function __construct(
        private ApiClient $client,
        private TransactionFactory $transactions,
    ) {
    }

    /**
     * Returns all proposals in node-provided order.
     *
     * @return list<Proposal>
     */
    public function list(): array
    {
        $response = $this->client->request(Endpoint::ListProposals->request());

        return $this->proposals($response->value('proposals') ?? []);
    }

    /**
     * Returns a bounded latest-state proposal page in node-provided order.
     *
     * @return list<Proposal>
     */
    public function page(int $offset = 0, int $limit = 20): array
    {
        IntegerHelper::nonNegative($offset, 'Proposal page offset');
        IntegerHelper::between($limit, 1, 200, 'Proposal page limit');
        $response = $this->client->request(Endpoint::ListProposalsPaginated->request([
            'offset' => $offset,
            'limit' => $limit,
        ]));

        return $this->proposals($response->value('proposals') ?? []);
    }

    /**
     * Returns one proposal by ID, or null when the node has no matching proposal.
     */
    public function find(int $proposalId): ?Proposal
    {
        IntegerHelper::nonNegative($proposalId, 'Proposal ID');

        $data = $this->client->request(Endpoint::GetProposal->request(['id' => $proposalId]))->data();

        return $data === []
            ? null
            : Proposal::fromNodeData(DataDecoder::object($data, 'proposal'));
    }

    /**
     * Returns the millisecond timestamp of the next maintenance period.
     */
    public function nextMaintenanceAtMilliseconds(): int
    {
        $response = $this->client->request(Endpoint::GetNextMaintenanceTime->request());

        return DataDecoder::integer($response->value('num') ?? null, 'num');
    }

    /**
     * Builds a proposal containing unique chain parameter changes.
     *
     * @param list<ProposalParameter> $parameters One or more unique parameter changes.
     */
    public function create(
        Address $ownerAddress,
        array $parameters,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        if ($parameters === []) {
            throw new ValidationException('A governance proposal requires at least one chain parameter.');
        }

        $keys = [];
        $fields = [];
        foreach ($parameters as $parameter) {
            if (isset($keys[$parameter->key])) {
                throw new ValidationException('A governance proposal cannot contain a duplicate parameter ID.');
            }
            $keys[$parameter->key] = true;
            $fields[] = $parameter->toNodeData();
        }

        return $this->transactions->createNativeContract(
            Endpoint::CreateProposal,
            'ProposalCreateContract',
            $ownerAddress,
            ['parameters' => $fields],
            $memo,
            $permissionId,
        );
    }

    /**
     * Builds an SR transaction that adds or removes a proposal approval.
     */
    public function approve(
        Address $ownerAddress,
        int $proposalId,
        bool $addApproval = true,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        return $this->proposalTransaction(
            Endpoint::ApproveProposal,
            'ProposalApproveContract',
            $ownerAddress,
            $proposalId,
            ['is_add_approval' => $addApproval],
            $memo,
            $permissionId,
        );
    }

    /**
     * Builds a creator-authorized transaction that deletes an unexpired proposal.
     */
    public function delete(
        Address $ownerAddress,
        int $proposalId,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        return $this->proposalTransaction(
            Endpoint::DeleteProposal,
            'ProposalDeleteContract',
            $ownerAddress,
            $proposalId,
            [],
            $memo,
            $permissionId,
        );
    }

    /**
     * Builds a verified proposal-ID mutation from one shared native schema.
     *
     * @param array<string, bool> $additionalFields Operation-specific fields.
     */
    private function proposalTransaction(
        Endpoint $endpoint,
        string $contractType,
        Address $ownerAddress,
        int $proposalId,
        array $additionalFields,
        ?Memo $memo,
        int $permissionId,
    ): Transaction {
        IntegerHelper::nonNegative($proposalId, 'Proposal ID');

        return $this->transactions->createNativeContract(
            $endpoint,
            $contractType,
            $ownerAddress,
            ['proposal_id' => $proposalId, ...$additionalFields],
            $memo,
            $permissionId,
        );
    }

    /**
     * Parses a repeated native proposal field into typed values.
     *
     * @return list<Proposal>
     */
    private function proposals(mixed $value): array
    {
        return array_map(
            static fn (array $data): Proposal => Proposal::fromNodeData($data),
            DataDecoder::objectList($value, 'proposals'),
        );
    }
}
