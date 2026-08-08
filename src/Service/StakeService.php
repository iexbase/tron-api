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

use DateTimeImmutable;
use IEXBase\TronAPI\Api\ApiClient;
use IEXBase\TronAPI\Api\DataDecoder;
use IEXBase\TronAPI\Api\Endpoint;
use IEXBase\TronAPI\Enum\ConfirmationLevel;
use IEXBase\TronAPI\Enum\ResourceType;
use IEXBase\TronAPI\Exception\ValidationException;
use IEXBase\TronAPI\Support\StakeHelper;
use IEXBase\TronAPI\Transaction\Transaction;
use IEXBase\TronAPI\Transaction\TransactionFactory;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\Amount;
use IEXBase\TronAPI\Value\Memo;

/**
 * Implements Stake 2.0 staking, unstaking, delegation, withdrawal, and queries.
 *
 * Deprecated Stake 1.0 mutation endpoints are intentionally excluded from the
 * primary API so new integrations cannot accidentally start legacy positions.
 */
final readonly class StakeService
{
    /**
     * Creates the service over native nodes and the verified transaction factory.
     */
    public function __construct(
        private ApiClient $client,
        private TransactionFactory $transactions,
        private StakeDelegationReader $delegationReader,
    ) {
    }

    /**
     * Builds a Stake 2.0 transaction that stakes TRX for Bandwidth or Energy.
     */
    public function stake(
        Address $ownerAddress,
        Amount $amount,
        ResourceType $resource,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        StakeHelper::requireBandwidthOrEnergy($resource, 'Stake 2.0 staking');

        return $this->resourceAmountTransaction(
            Endpoint::FreezeBalanceV2,
            'FreezeBalanceV2Contract',
            $ownerAddress,
            'frozen_balance',
            $amount,
            $resource,
            $memo,
            $permissionId,
        );
    }

    /**
     * Builds a Stake 2.0 unstaking request subject to the chain delay and slot limit.
     */
    public function unstake(
        Address $ownerAddress,
        Amount $amount,
        ResourceType $resource,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        return $this->resourceAmountTransaction(
            Endpoint::UnfreezeBalanceV2,
            'UnfreezeBalanceV2Contract',
            $ownerAddress,
            'unfreeze_balance',
            $amount,
            $resource,
            $memo,
            $permissionId,
        );
    }

    /**
     * Builds a transaction that cancels every pending Stake 2.0 unstaking request.
     */
    public function cancelPendingUnstakes(
        Address $ownerAddress,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        return $this->transactions->createNativeContract(
            Endpoint::CancelAllUnfreezeV2,
            'CancelAllUnfreezeV2Contract',
            $ownerAddress,
            [],
            $memo,
            $permissionId,
        );
    }

    /**
     * Builds a resource delegation with an optional block-based lock period.
     */
    public function delegate(
        Address $ownerAddress,
        Address $recipientAddress,
        Amount $amount,
        ResourceType $resource,
        bool $locked = false,
        ?int $lockPeriodBlocks = null,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        $fields = $this->delegationFields(
            $ownerAddress,
            $recipientAddress,
            $amount,
            $resource,
            'delegated to',
        );
        if ($locked && ($lockPeriodBlocks === null || $lockPeriodBlocks <= 0)) {
            throw new ValidationException('A locked resource delegation requires a positive block period.');
        }
        if (!$locked && $lockPeriodBlocks !== null) {
            throw new ValidationException('An unlocked resource delegation cannot specify a lock period.');
        }

        $fields['lock'] = $locked;
        if ($lockPeriodBlocks !== null) {
            $fields['lock_period'] = $lockPeriodBlocks;
        }

        return $this->transactions->createNativeContract(
            Endpoint::DelegateResource,
            'DelegateResourceContract',
            $ownerAddress,
            $fields,
            $memo,
            $permissionId,
            omittableFields: StakeHelper::omittableResourceField($resource),
        );
    }

    /**
     * Builds a transaction that returns delegated resources to the owner.
     */
    public function undelegate(
        Address $ownerAddress,
        Address $recipientAddress,
        Amount $amount,
        ResourceType $resource,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        $fields = $this->delegationFields(
            $ownerAddress,
            $recipientAddress,
            $amount,
            $resource,
            'undelegated from',
        );

        return $this->transactions->createNativeContract(
            Endpoint::UndelegateResource,
            'UnDelegateResourceContract',
            $ownerAddress,
            $fields,
            $memo,
            $permissionId,
            omittableFields: StakeHelper::omittableResourceField($resource),
        );
    }

    /**
     * Builds a transaction that withdraws all expired unstaking balances.
     */
    public function withdrawExpired(
        Address $ownerAddress,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        return $this->transactions->createNativeContract(
            Endpoint::WithdrawExpiredUnfreeze,
            'WithdrawExpireUnfreezeContract',
            $ownerAddress,
            [],
            $memo,
            $permissionId,
        );
    }

    /**
     * Returns remaining concurrent unstaking slots at the selected consistency level.
     */
    public function availableUnstakeCount(
        Address $ownerAddress,
        ConfirmationLevel $level = ConfirmationLevel::Confirmed,
    ): int {
        $endpoint = Endpoint::forConfirmation(
            $level,
            Endpoint::GetAvailableUnfreezeCount,
            Endpoint::GetConfirmedAvailableUnfreezeCount,
        );
        $response = $this->client->request($endpoint->request([
            'owner_address' => $ownerAddress->toBase58(),
            'visible' => true,
        ]));

        return DataDecoder::integer($response->value('count') ?? 0, 'count');
    }

    /**
     * Returns the amount withdrawable at a timestamp from latest or confirmed state.
     */
    public function withdrawableAmount(
        Address $ownerAddress,
        ?DateTimeImmutable $at = null,
        ConfirmationLevel $level = ConfirmationLevel::Confirmed,
    ): Amount {
        $endpoint = Endpoint::forConfirmation(
            $level,
            Endpoint::GetWithdrawableUnfreezeAmount,
            Endpoint::GetConfirmedWithdrawableUnfreezeAmount,
        );
        $timestamp = (int) ($at ?? new DateTimeImmutable())->format('Uv');
        $response = $this->client->request($endpoint->request([
            'owner_address' => $ownerAddress->toBase58(),
            'timestamp' => $timestamp,
            'visible' => true,
        ]));

        return Amount::fromAtomic(DataDecoder::unsignedDecimal(
            $response->value('amount') ?? 0,
            'amount',
        ));
    }

    /**
     * Returns the maximum currently delegatable stake share for one resource.
     */
    public function delegatableAmount(
        Address $ownerAddress,
        ResourceType $resource,
        ConfirmationLevel $level = ConfirmationLevel::Confirmed,
    ): Amount {
        StakeHelper::requireBandwidthOrEnergy($resource, 'Delegatable resource queries');
        $endpoint = Endpoint::forConfirmation(
            $level,
            Endpoint::GetDelegatableResourceAmount,
            Endpoint::GetConfirmedDelegatableResourceAmount,
        );
        $response = $this->client->request($endpoint->request([
            'owner_address' => $ownerAddress->toBase58(),
            'type' => $resource->code(),
            'visible' => true,
        ]));

        return Amount::fromAtomic(DataDecoder::unsignedDecimal(
            $response->value('max_size') ?? 0,
            'max_size',
        ));
    }

    /**
     * Returns resource delegation records between two accounts.
     *
     * @return list<array<string, mixed>>
     */
    public function delegations(
        Address $ownerAddress,
        Address $recipientAddress,
        ConfirmationLevel $level = ConfirmationLevel::Confirmed,
    ): array {
        return $this->delegationReader->records(
            $ownerAddress,
            $recipientAddress,
            $level,
            Endpoint::GetDelegatedResourceV2,
            Endpoint::GetConfirmedDelegatedResourceV2,
        );
    }

    /**
     * Returns incoming and outgoing Stake 2.0 delegation account indexes.
     *
     * @return array<string, mixed>
     */
    public function delegationIndex(
        Address $address,
        ConfirmationLevel $level = ConfirmationLevel::Confirmed,
    ): array {
        return $this->delegationReader->accountIndex(
            $address,
            $level,
            Endpoint::GetDelegatedResourceIndexV2,
            Endpoint::GetConfirmedDelegatedResourceIndexV2,
            'delegation index',
        );
    }

    /**
     * Builds a Stake 2.0 amount/resource mutation with exact intent verification.
     */
    private function resourceAmountTransaction(
        Endpoint $endpoint,
        string $contractType,
        Address $ownerAddress,
        string $amountField,
        Amount $amount,
        ResourceType $resource,
        ?Memo $memo,
        int $permissionId,
    ): Transaction {
        $fields = [
            $amountField => StakeHelper::positiveSun($amount),
            'resource' => $resource->value,
        ];

        return $this->transactions->createNativeContract(
            $endpoint,
            $contractType,
            $ownerAddress,
            $fields,
            $memo,
            $permissionId,
            omittableFields: StakeHelper::omittableResourceField($resource),
        );
    }

    /**
     * Returns shared delegation fields after rejecting TRON Power and self-use.
     *
     * @return array{receiver_address: Address, balance: int, resource: string}
     */
    private function delegationFields(
        Address $ownerAddress,
        Address $recipientAddress,
        Amount $amount,
        ResourceType $resource,
        string $relationship,
    ): array {
        StakeHelper::requireBandwidthOrEnergy($resource, 'Stake 2.0 delegation');
        if ($ownerAddress->equals($recipientAddress)) {
            throw new ValidationException(sprintf(
                'Stake 2.0 resources cannot be %s the same account.',
                $relationship,
            ));
        }

        return [
            'receiver_address' => $recipientAddress,
            'balance' => StakeHelper::positiveSun($amount),
            'resource' => $resource->value,
        ];
    }
}
