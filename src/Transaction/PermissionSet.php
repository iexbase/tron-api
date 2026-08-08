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

namespace IEXBase\TronAPI\Transaction;

use IEXBase\TronAPI\Api\DataDecoder;
use IEXBase\TronAPI\Exception\ValidationException;
use IEXBase\TronAPI\Value\Address;

/**
 * Represents owner, optional witness, and up to eight Active permissions.
 */
final readonly class PermissionSet
{
    /** @var array<int, Permission> */
    private array $permissionsById;

    /**
     * Validates permission roles, IDs, uniqueness, and Active permission count.
     *
     * @param list<Permission> $activePermissions Active permissions with IDs 2-9.
     */
    public function __construct(
        public Permission $ownerPermission,
        public ?Permission $witnessPermission,
        array $activePermissions,
    ) {
        if ($ownerPermission->id !== 0 || $ownerPermission->type !== 'Owner') {
            throw new ValidationException('A permission set requires an Owner permission with ID 0.');
        }
        if ($witnessPermission !== null
            && ($witnessPermission->id !== 1 || $witnessPermission->type !== 'Witness')
        ) {
            throw new ValidationException('A witness permission must use Witness type and ID 1.');
        }
        if ($activePermissions === [] || count($activePermissions) > 8) {
            throw new ValidationException('A permission set requires between one and eight Active permissions.');
        }

        $byId = [0 => $ownerPermission];
        if ($witnessPermission !== null) {
            $byId[1] = $witnessPermission;
        }
        foreach ($activePermissions as $permission) {
            if ($permission->type !== 'Active'
                || $permission->id < 2
                || $permission->id > 9
                || isset($byId[$permission->id])
            ) {
                throw new ValidationException('Active permissions require unique IDs from 2 to 9.');
            }
            $byId[$permission->id] = $permission;
        }

        $this->permissionsById = $byId;
    }

    /**
     * Parses explicit node permissions or synthesizes protocol defaults when omitted.
     *
     * @param array<string, mixed> $accountData Native account object.
     */
    public static function fromAccountData(Address $accountAddress, array $accountData): self
    {
        $ownerData = $accountData['owner_permission'] ?? null;
        $owner = $ownerData === null
            ? self::implicitPermission($accountAddress, 0, 'owner', 'Owner')
            : Permission::fromNodeData(DataDecoder::object($ownerData, 'owner_permission'));

        $witnessData = $accountData['witness_permission'] ?? null;
        $witness = $witnessData === null
            ? null
            : Permission::fromNodeData(DataDecoder::object($witnessData, 'witness_permission'));

        $activeData = $accountData['active_permission'] ?? null;
        if ($activeData === null) {
            $active = [self::implicitPermission($accountAddress, 2, 'active', 'Active')];
        } else {
            $active = array_map(
                static fn (array $data): Permission => Permission::fromNodeData($data),
                DataDecoder::objectList($activeData, 'active_permission'),
            );
        }

        return new self($owner, $witness, $active);
    }

    /**
     * Returns one permission by protocol ID.
     */
    public function permission(int $id): Permission
    {
        return $this->permissionsById[$id]
            ?? throw new ValidationException(sprintf('Permission ID %d does not exist.', $id));
    }

    /**
     * Returns Active permissions ordered by their IDs.
     *
     * @return list<Permission>
     */
    public function activePermissions(): array
    {
        $active = array_filter(
            $this->permissionsById,
            static fn (Permission $permission): bool => $permission->type === 'Active',
        );
        ksort($active);

        return array_values($active);
    }

    /**
     * Creates the implicit one-of-one permission available for every account.
     */
    private static function implicitPermission(
        Address $address,
        int $id,
        string $name,
        string $type,
    ): Permission {
        return new Permission($id, $name, $type, 1, [
            ['address' => $address, 'weight' => 1],
        ]);
    }
}
