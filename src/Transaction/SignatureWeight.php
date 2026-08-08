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
use IEXBase\TronAPI\Exception\ResponseDecodingException;
use IEXBase\TronAPI\Value\Address;

/**
 * Represents node-verified multi-signature weight and approved signer addresses.
 */
final readonly class SignatureWeight
{
    /** @var list<Address> */
    private array $approvedSigners;

    /**
     * Stores the selected permission, accumulated weight, and distinct signers.
     *
     * @param list<Address> $approvedSigners Addresses accepted by the FullNode.
     */
    private function __construct(
        public Permission $permission,
        public int $currentWeight,
        array $approvedSigners,
    ) {
        $this->approvedSigners = $approvedSigners;
    }

    /**
     * Creates a signature weight result from `/wallet/getsignweight` data.
     *
     * @param array<string, mixed> $data Accepted node response.
     */
    public static function fromNodeData(array $data): self
    {
        $permission = Permission::fromNodeData(DataDecoder::object($data['permission'] ?? null, 'permission'));
        $currentWeight = DataDecoder::integer($data['current_weight'] ?? 0, 'current_weight');
        $approvedValues = $data['approved_list'] ?? [];
        if (!is_array($approvedValues) || !array_is_list($approvedValues)) {
            throw new ResponseDecodingException('The `approved_list` response field must be a list.');
        }

        $approved = [];
        $seen = [];
        foreach ($approvedValues as $position => $value) {
            $address = Address::fromString(DataDecoder::string($value, sprintf('approved_list[%d]', $position)));
            $key = $address->toBase58();
            if (isset($seen[$key])) {
                throw new ResponseDecodingException('The node returned a duplicate approved signer.');
            }
            $seen[$key] = true;
            $approved[] = $address;
        }

        return new self($permission, $currentWeight, $approved);
    }

    /**
     * Returns whether the node-calculated weight reaches the permission threshold.
     */
    public function hasRequiredWeight(): bool
    {
        return $this->currentWeight >= $this->permission->threshold;
    }

    /**
     * Returns distinct signer addresses accepted by the node.
     *
     * @return list<Address>
     */
    public function approvedSigners(): array
    {
        return $this->approvedSigners;
    }
}
