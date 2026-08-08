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

use IEXBase\TronAPI\Api\Endpoint;
use IEXBase\TronAPI\Enum\ConfirmationLevel;
use IEXBase\TronAPI\Enum\ResourceType;
use IEXBase\TronAPI\Exception\ValidationException;
use IEXBase\TronAPI\Support\IntegerHelper;
use IEXBase\TronAPI\Support\StakeHelper;
use IEXBase\TronAPI\Transaction\Transaction;
use IEXBase\TronAPI\Transaction\TransactionFactory;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\Amount;
use IEXBase\TronAPI\Value\Memo;

/**
 * Supports existing Stake 1.0 positions through an explicitly legacy API boundary.
 *
 * New integrations should use StakeService and Stake 2.0. This service remains
 * available so older positions can be queried or released without raw arrays.
 */
final readonly class LegacyStakeService
{
    /**
     * Creates the legacy service over native nodes and the transaction factory.
     */
    public function __construct(
        private TransactionFactory $transactions,
        private StakeDelegationReader $delegationReader,
    ) {
    }

    /**
     * Builds a legacy Stake 1.0 freeze transaction when a network still accepts it.
     */
    public function freeze(
        Address $ownerAddress,
        Amount $amount,
        ResourceType $resource,
        int $durationDays = 3,
        ?Address $recipientAddress = null,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        StakeHelper::requireBandwidthOrEnergy($resource, 'Stake 1.0 freezing');
        IntegerHelper::positive($durationDays, 'Stake 1.0 duration');
        $this->requireDifferentRecipient($ownerAddress, $recipientAddress);
        $fields = [
            'frozen_balance' => StakeHelper::positiveSun($amount),
            'frozen_duration' => $durationDays,
            'resource' => $resource->value,
        ];
        if ($recipientAddress !== null) {
            $fields['receiver_address'] = $recipientAddress;
        }

        return $this->transactions->createNativeContract(
            Endpoint::FreezeBalanceLegacy,
            'FreezeBalanceContract',
            $ownerAddress,
            $fields,
            $memo,
            $permissionId,
            omittableFields: StakeHelper::omittableResourceField($resource),
        );
    }

    /**
     * Builds a legacy Stake 1.0 unfreeze transaction for an existing position.
     */
    public function unfreeze(
        Address $ownerAddress,
        ResourceType $resource,
        ?Address $recipientAddress = null,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        StakeHelper::requireBandwidthOrEnergy($resource, 'Stake 1.0 unfreezing');
        $this->requireDifferentRecipient($ownerAddress, $recipientAddress);
        $fields = ['resource' => $resource->value];
        if ($recipientAddress !== null) {
            $fields['receiver_address'] = $recipientAddress;
        }

        return $this->transactions->createNativeContract(
            Endpoint::UnfreezeBalanceLegacy,
            'UnfreezeBalanceContract',
            $ownerAddress,
            $fields,
            $memo,
            $permissionId,
            omittableFields: StakeHelper::omittableResourceField($resource),
        );
    }

    /**
     * Returns legacy resource delegations between two accounts.
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
            Endpoint::GetDelegatedResourceLegacy,
            Endpoint::GetConfirmedDelegatedResourceLegacy,
        );
    }

    /**
     * Returns accounts participating in legacy delegations for one address.
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
            Endpoint::GetDelegatedResourceIndexLegacy,
            Endpoint::GetConfirmedDelegatedResourceIndexLegacy,
            'legacy delegation index',
        );
    }

    /**
     * Rejects self-delegation while allowing a null direct-stake recipient.
     */
    private function requireDifferentRecipient(Address $ownerAddress, ?Address $recipientAddress): void
    {
        if ($recipientAddress !== null && $ownerAddress->equals($recipientAddress)) {
            throw new ValidationException('A legacy delegated stake recipient must differ from its owner.');
        }
    }
}
