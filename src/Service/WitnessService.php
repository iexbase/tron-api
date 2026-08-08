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
use IEXBase\TronAPI\Governance\WitnessVote;
use IEXBase\TronAPI\Model\Witness;
use IEXBase\TronAPI\Support\IntegerHelper;
use IEXBase\TronAPI\Support\TextHelper;
use IEXBase\TronAPI\Transaction\Transaction;
use IEXBase\TronAPI\Transaction\TransactionFactory;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\Amount;
use IEXBase\TronAPI\Value\Memo;

/**
 * Reads SR state and builds verified candidate, vote, brokerage, and reward transactions.
 */
final readonly class WitnessService
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
     * Returns every SR candidate visible at the selected consistency level.
     *
     * @return list<Witness>
     */
    public function list(ConfirmationLevel $level = ConfirmationLevel::Confirmed): array
    {
        $endpoint = Endpoint::forConfirmation($level, Endpoint::ListWitnesses, Endpoint::ListConfirmedWitnesses);
        $response = $this->client->request($endpoint->request());

        return $this->witnesses($response->value('witnesses') ?? []);
    }

    /**
     * Returns a bounded real-time witness ranking page.
     *
     * @return list<Witness>
     */
    public function page(
        int $offset = 0,
        int $limit = 20,
        ConfirmationLevel $level = ConfirmationLevel::Confirmed,
    ): array {
        IntegerHelper::nonNegative($offset, 'Witness page offset');
        IntegerHelper::between($limit, 1, 200, 'Witness page limit');
        $endpoint = Endpoint::forConfirmation(
            $level,
            Endpoint::ListWitnessesPaginated,
            Endpoint::ListConfirmedWitnessesPaginated,
        );
        $response = $this->client->request($endpoint->request([
            'offset' => $offset,
            'limit' => $limit,
        ]));

        return $this->witnesses($response->value('witnesses') ?? []);
    }

    /**
     * Builds an application transaction to become an SR candidate.
     */
    public function createCandidate(
        Address $ownerAddress,
        string $url,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        $url = TextHelper::webUrl($url, 'Witness URL');

        return $this->transactions->createNativeContract(
            Endpoint::CreateWitness,
            'WitnessCreateContract',
            $ownerAddress,
            ['url' => $url],
            $memo,
            $permissionId,
        );
    }

    /**
     * Builds a transaction that changes an existing SR candidate URL.
     */
    public function updateCandidateUrl(
        Address $ownerAddress,
        string $url,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        $url = TextHelper::webUrl($url, 'Witness URL', allowEmpty: true);

        return $this->transactions->createNativeContract(
            Endpoint::UpdateWitness,
            'WitnessUpdateContract',
            $ownerAddress,
            ['update_url' => $url],
            $memo,
            $permissionId,
        );
    }

    /**
     * Builds a complete replacement of an account's witness vote allocation.
     *
     * @param list<WitnessVote> $votes One to thirty unique SR vote allocations.
     */
    public function vote(
        Address $ownerAddress,
        array $votes,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        if ($votes === [] || count($votes) > 30) {
            throw new ValidationException('A witness vote transaction requires between 1 and 30 allocations.');
        }

        $addresses = [];
        $intentVotes = [];
        $requestVotes = [];
        foreach ($votes as $vote) {
            $address = $vote->witnessAddress->toBase58();
            if (isset($addresses[$address])) {
                throw new ValidationException('A witness vote transaction cannot contain the same candidate twice.');
            }
            $addresses[$address] = true;
            $intentVotes[] = [
                'vote_address' => $vote->witnessAddress,
                'vote_count' => $vote->voteCount,
            ];
            $requestVotes[] = $vote->toNodeData();
        }

        return $this->transactions->createNativeContract(
            Endpoint::VoteWitnesses,
            'VoteWitnessContract',
            $ownerAddress,
            ['votes' => $intentVotes],
            $memo,
            $permissionId,
            ['votes' => $requestVotes],
        );
    }

    /**
     * Returns the SR brokerage percentage from latest or confirmed state.
     */
    public function brokerage(
        Address $address,
        ConfirmationLevel $level = ConfirmationLevel::Confirmed,
    ): int {
        return IntegerHelper::between(
            DataDecoder::integer($this->addressStateValue(
                $address,
                $level,
                Endpoint::GetBrokerage,
                Endpoint::GetConfirmedBrokerage,
                'brokerage',
            ), 'brokerage'),
            0,
            100,
            'Witness brokerage',
        );
    }

    /**
     * Builds a transaction that changes an SR's retained reward percentage.
     */
    public function updateBrokerage(
        Address $ownerAddress,
        int $brokerage,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        IntegerHelper::between($brokerage, 0, 100, 'Witness brokerage');
        $fields = ['brokerage' => $brokerage];

        return $this->transactions->createNativeContract(
            Endpoint::UpdateBrokerage,
            'UpdateBrokerageContract',
            $ownerAddress,
            $fields,
            $memo,
            $permissionId,
        );
    }

    /**
     * Returns voting or SR rewards that have not yet been withdrawn.
     */
    public function unclaimedReward(
        Address $address,
        ConfirmationLevel $level = ConfirmationLevel::Confirmed,
    ): Amount {
        return Amount::fromAtomic(DataDecoder::unsignedDecimal($this->addressStateValue(
            $address,
            $level,
            Endpoint::GetReward,
            Endpoint::GetConfirmedReward,
            'reward',
        ), 'reward'));
    }

    /**
     * Builds a transaction that claims every currently withdrawable reward.
     */
    public function withdrawReward(
        Address $ownerAddress,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        return $this->transactions->createNativeContract(
            Endpoint::WithdrawReward,
            'WithdrawBalanceContract',
            $ownerAddress,
            [],
            $memo,
            $permissionId,
        );
    }

    /**
     * Reads one scalar witness field from latest or confirmed account state.
     */
    private function addressStateValue(
        Address $address,
        ConfirmationLevel $level,
        Endpoint $latestEndpoint,
        Endpoint $confirmedEndpoint,
        string $field,
    ): mixed {
        $endpoint = Endpoint::forConfirmation($level, $latestEndpoint, $confirmedEndpoint);

        return $this->client->request($endpoint->request([
            'address' => $address->toBase58(),
            'visible' => true,
        ]))->value($field) ?? 0;
    }

    /**
     * Parses a native repeated witness field into typed values.
     *
     * @return list<Witness>
     */
    private function witnesses(mixed $value): array
    {
        return array_map(
            static fn (array $data): Witness => Witness::fromNodeData($data),
            DataDecoder::objectList($value, 'witnesses'),
        );
    }
}
