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
use IEXBase\TronAPI\Encoding\Hex;
use IEXBase\TronAPI\Enum\ContractType;
use IEXBase\TronAPI\Exception\ValidationException;
use IEXBase\TronAPI\Value\Address;

/**
 * Represents one owner, witness, or active permission and its weighted keys.
 */
final readonly class Permission
{
    /** @var array<string, int> */
    private array $keyWeights;

    public ?string $operations;

    /**
     * Validates a permission and canonicalizes each key by Base58 address.
     *
     * @param int                  $id Permission identifier from 0 to 9.
     * @param string               $name Human-readable permission name.
     * @param string               $type Owner, Witness, or Active.
     * @param int                  $threshold Required aggregate key weight.
     * @param array<mixed>        $keys Weighted signer key records.
     * @param string|null          $operations Optional active-permission bitmap.
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $type,
        public int $threshold,
        array $keys,
        ?string $operations = null,
    ) {
        if ($id < 0 || $id > 9 || $threshold <= 0 || $name === '') {
            throw new ValidationException('A permission requires a valid ID, name, and positive threshold.');
        }

        if (!in_array($type, ['Owner', 'Witness', 'Active'], true)) {
            throw new ValidationException('A permission type must be Owner, Witness, or Active.');
        }

        $weights = [];
        foreach ($keys as $key) {
            if (!is_array($key)
                || !isset($key['address'], $key['weight'])
                || !$key['address'] instanceof Address
                || !is_int($key['weight'])
                || $key['weight'] <= 0
            ) {
                throw new ValidationException('Every permission key requires an address and positive integer weight.');
            }

            $address = $key['address']->toBase58();
            if (isset($weights[$address])) {
                throw new ValidationException('A permission cannot contain duplicate signer addresses.');
            }
            $weights[$address] = $key['weight'];
        }

        if ($weights === []) {
            throw new ValidationException('A permission must contain at least one signer key.');
        }

        if ($operations !== null && $type !== 'Active') {
            throw new ValidationException('Only an Active permission may define an operations bitmap.');
        }

        $this->keyWeights = $weights;
        $this->operations = $operations === null ? null : Hex::canonicalize($operations, 32);
    }

    /**
     * Hydrates a permission from the shape returned by account APIs.
     *
     * @param array<string, mixed> $data Node permission object.
     */
    public static function fromNodeData(array $data): self
    {
        $id = isset($data['id']) ? DataDecoder::integer($data['id'], 'permission.id') : 0;
        $name = isset($data['permission_name']) && is_string($data['permission_name'])
            ? $data['permission_name']
            : ($id === 0 ? 'owner' : 'permission-' . $id);
        $type = match ($data['type'] ?? null) {
            0, 'Owner' => 'Owner',
            1, 'Witness' => 'Witness',
            2, 'Active' => 'Active',
            default => throw new ValidationException('The node returned an unknown permission type.'),
        };
        $threshold = isset($data['threshold'])
            ? DataDecoder::integer($data['threshold'], 'permission.threshold')
            : throw new ValidationException('The node permission is missing its threshold.');
        $nodeKeys = $data['keys'] ?? null;
        if (!is_array($nodeKeys)) {
            throw new ValidationException('The node permission is missing its keys.');
        }

        $keys = [];
        foreach ($nodeKeys as $nodeKey) {
            if (!is_array($nodeKey)
                || !isset($nodeKey['address'], $nodeKey['weight'])
                || !is_string($nodeKey['address'])
            ) {
                throw new ValidationException('The node returned a malformed permission key.');
            }
            $keys[] = [
                'address' => Address::fromString($nodeKey['address']),
                'weight' => DataDecoder::integer($nodeKey['weight'], 'permission.keys[].weight'),
            ];
        }

        $operations = isset($data['operations']) && is_string($data['operations'])
            ? $data['operations']
            : null;

        return new self($id, $name, $type, $threshold, $keys, $operations);
    }

    /**
     * Returns whether this permission explicitly contains a signer address.
     */
    public function contains(Address $address): bool
    {
        return isset($this->keyWeights[$address->toBase58()]);
    }

    /**
     * Returns the configured weight for a signer, or zero when unauthorized.
     */
    public function weight(Address $address): int
    {
        return $this->keyWeights[$address->toBase58()] ?? 0;
    }

    /**
     * Returns all permission keys indexed by their Base58 addresses.
     *
     * @return array<string, int>
     */
    public function keyWeights(): array
    {
        return $this->keyWeights;
    }

    /**
     * Returns whether this permission's little-endian bitmap allows a contract type.
     */
    public function allows(ContractType $contractType): bool
    {
        if ($this->type !== 'Active') {
            return true;
        }

        if ($this->operations === null) {
            return $contractType !== ContractType::AccountPermissionUpdateContract;
        }

        $bytes = Hex::toBytes($this->operations, 32);
        $byte = ord($bytes[intdiv($contractType->value, 8)]);

        return ($byte & (1 << ($contractType->value % 8))) !== 0;
    }

    /**
     * Returns the visible=true permission object accepted by account update APIs.
     *
     * @return array<string, mixed>
     */
    public function toNodeData(): array
    {
        $data = [
            'type' => match ($this->type) {
                'Owner' => 0,
                'Witness' => 1,
                'Active' => 2,
                default => throw new ValidationException('A permission contains an unsupported type.'),
            },
            'id' => $this->id,
            'permission_name' => $this->name,
            'threshold' => $this->threshold,
            'keys' => array_map(
                static fn (string $address, int $weight): array => [
                    'address' => $address,
                    'weight' => $weight,
                ],
                array_keys($this->keyWeights),
                array_values($this->keyWeights),
            ),
        ];
        if ($this->operations !== null) {
            $data['operations'] = $this->operations;
        }

        return $data;
    }

    /**
     * Compares permission meaning independently of key insertion order.
     */
    public function equals(self $other): bool
    {
        $keys = $this->keyWeights;
        $otherKeys = $other->keyWeights;
        ksort($keys);
        ksort($otherKeys);

        return $this->id === $other->id
            && $this->name === $other->name
            && $this->type === $other->type
            && $this->threshold === $other->threshold
            && $this->operations === $other->operations
            && $keys === $otherKeys;
    }
}
