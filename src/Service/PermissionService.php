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
use IEXBase\TronAPI\Exception\ValidationException;
use IEXBase\TronAPI\Transaction\Permission;
use IEXBase\TronAPI\Transaction\PermissionSet;
use IEXBase\TronAPI\Transaction\Transaction;
use IEXBase\TronAPI\Transaction\TransactionFactory;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\Memo;

/**
 * Reads account permissions and builds verified owner-authorized permission updates.
 */
final readonly class PermissionService
{
    /**
     * Creates the service using the account reader and shared transaction factory.
     */
    public function __construct(
        private AccountService $accounts,
        private TransactionFactory $transactions,
    ) {
    }

    /**
     * Returns explicit or protocol-default permissions, or null for a missing account.
     */
    public function get(
        Address $accountAddress,
        ConfirmationLevel $level = ConfirmationLevel::Confirmed,
    ): ?PermissionSet {
        $account = $this->accounts->get($accountAddress, $level);

        return $account === null
            ? null
            : PermissionSet::fromAccountData($accountAddress, $account->rawData());
    }

    /**
     * Builds a complete permission replacement that must be signed by Owner ID 0.
     *
     * @param list<Permission> $activePermissions Complete replacement Active list.
     */
    public function createUpdate(
        Address $ownerAddress,
        Permission $ownerPermission,
        array $activePermissions,
        ?Permission $witnessPermission = null,
        ?Memo $memo = null,
    ): Transaction {
        $set = new PermissionSet($ownerPermission, $witnessPermission, $activePermissions);
        foreach ($set->activePermissions() as $permission) {
            if ($permission->operations === null) {
                throw new ValidationException('Every explicitly configured Active permission requires a 32-byte operations bitmap.');
            }
        }

        $fields = [
            'owner' => $ownerPermission,
            'actives' => $set->activePermissions(),
        ];
        $requestFields = [
            'owner' => $ownerPermission->toNodeData(),
            'actives' => array_map(
                static fn (Permission $permission): array => $permission->toNodeData(),
                $set->activePermissions(),
            ),
        ];
        if ($witnessPermission !== null) {
            $fields['witness'] = $witnessPermission;
            $requestFields['witness'] = $witnessPermission->toNodeData();
        }

        return $this->transactions->createNativeContract(
            Endpoint::UpdateAccountPermission,
            'AccountPermissionUpdateContract',
            $ownerAddress,
            $fields,
            memo: $memo,
            requestFields: $requestFields,
        );
    }
}
