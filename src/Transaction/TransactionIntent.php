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

use IEXBase\TronAPI\Contract\Abi;
use IEXBase\TronAPI\Exception\ValidationException;
use IEXBase\TronAPI\Encoding\Hex;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\Amount;
use IEXBase\TronAPI\Value\ByteString;

/**
 * Captures the exact user-approved operation that a node transaction must match.
 */
final readonly class TransactionIntent
{
    /** @var array<string, mixed> */
    private array $contractFields;

    private ?string $memoHex;

    /** @var array<string, true> */
    private array $omittableFields;

    /**
     * Validates all security-sensitive fields expected in the node transaction.
     *
     * @param string                                  $contractType Protocol contract type.
     * @param Address                                 $ownerAddress Account authorizing the operation.
     * @param array<string, mixed>                    $contractFields Exact recursive contract fields except owner_address.
     * @param int                                     $permissionId Owner (0) or active (2-9) permission.
     * @param string|null                             $memoHex Exact UTF-8 memo encoded as hex.
     * @param Amount|null                             $feeLimit Maximum contract Energy payment in sun.
     * @param list<string>                            $omittableFields Non-scalar protobuf defaults that a node may omit.
     */
    public function __construct(
        public string $contractType,
        public Address $ownerAddress,
        array $contractFields,
        public int $permissionId = 0,
        ?string $memoHex = null,
        public ?Amount $feeLimit = null,
        array $omittableFields = [],
    ) {
        if ($contractType === '' || isset($contractFields['owner_address'])) {
            throw new ValidationException('A transaction intent requires a contract type and manages owner_address separately.');
        }

        if ($permissionId === 1 || $permissionId < 0 || $permissionId > 9) {
            throw new ValidationException('Normal transactions require owner permission 0 or an active permission from 2 to 9.');
        }

        if ($feeLimit !== null && $feeLimit->decimals() !== Amount::TRX_DECIMALS) {
            throw new ValidationException('A fee limit must be denominated in sun with six TRX decimals.');
        }

        if ($feeLimit !== null && $feeLimit->compare(Amount::fromDecimal('15000')) > 0) {
            throw new ValidationException('A smart-contract fee limit cannot exceed the protocol maximum of 15,000 TRX.');
        }

        foreach ($contractFields as $name => $value) {
            if ($name === '') {
                throw new ValidationException('Transaction intent field names cannot be empty.');
            }
            self::assertSupportedValue($value, 0);
        }

        $omittable = [];
        foreach ($omittableFields as $name) {
            if (!array_key_exists($name, $contractFields) || isset($omittable[$name])) {
                throw new ValidationException('Omittable transaction fields must be unique fields present in the approved intent.');
            }
            $omittable[$name] = true;
        }

        $this->contractFields = $contractFields;
        $this->memoHex = $memoHex === null ? null : Hex::canonicalize($memoHex);
        $this->omittableFields = $omittable;
    }

    /**
     * Returns exact expected contract fields without owner_address.
     *
     * @return array<string, mixed>
     */
    public function contractFields(): array
    {
        return $this->contractFields;
    }

    /**
     * Returns the exact memo hex or null when no memo was authorized.
     */
    public function memoHex(): ?string
    {
        return $this->memoHex;
    }

    /**
     * Returns whether java-tron may omit a selected enum-like protobuf default.
     */
    public function fieldMayBeOmitted(string $name): bool
    {
        return isset($this->omittableFields[$name]);
    }

    /**
     * Rejects floats, objects other than Address, nulls, and unsafe deep arrays.
     */
    private static function assertSupportedValue(mixed $value, int $depth): void
    {
        if ($depth > 32) {
            throw new ValidationException('Transaction intent field nesting exceeds the safety limit.');
        }

        if ($value instanceof Address
            || $value instanceof Abi
            || $value instanceof ByteString
            || $value instanceof Permission
            || is_bool($value)
            || is_int($value)
            || is_string($value)
        ) {
            return;
        }

        if (!is_array($value)) {
            throw new ValidationException('Transaction intent fields may contain only exact JSON values and Address objects.');
        }

        foreach ($value as $item) {
            self::assertSupportedValue($item, $depth + 1);
        }
    }
}
