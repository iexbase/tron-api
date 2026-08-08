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

namespace IEXBase\TronAPI\Contract;

use IEXBase\TronAPI\Exception\ContractException;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\Amount;
use IEXBase\TronAPI\Value\ByteString;
use IEXBase\TronAPI\Value\Memo;
use JsonException;

/**
 * Captures a complete ABI-aware smart-contract deployment request.
 */
final readonly class DeploymentRequest
{
    private ByteString $deploymentBytecode;
    private ContractValue $value;

    /**
     * Encodes constructor arguments and validates all deployment cost/value fields.
     *
     * @param array<mixed> $constructorArguments Ordered or named constructor arguments.
     */
    public function __construct(
        public Address $ownerAddress,
        public string $name,
        public Abi $abi,
        ByteString $bytecode,
        array $constructorArguments,
        public Amount $feeLimit,
        public int $originEnergyLimit = 0,
        public int $callerEnergyPercent = 100,
        ?Amount $callValue = null,
        ?string $tokenId = null,
        ?Amount $tokenValue = null,
        public ?Memo $memo = null,
        public int $permissionId = 0,
        AbiCodec $codec = new AbiCodec(),
    ) {
        if ($name === '' || strlen($name) > 256 || preg_match('//u', $name) !== 1) {
            throw new ContractException('A contract name must be non-empty valid UTF-8 of at most 256 bytes.');
        }
        if ($bytecode->length() === 0) {
            throw new ContractException('A contract deployment requires non-empty compiled bytecode.');
        }
        if ($originEnergyLimit < 0) {
            throw new ContractException('A contract origin Energy limit cannot be negative.');
        }
        if ($callerEnergyPercent < 0 || $callerEnergyPercent > 100) {
            throw new ContractException('Contract caller Energy percent must be between 0 and 100.');
        }
        if ($feeLimit->decimals() !== Amount::TRX_DECIMALS || $feeLimit->isZero()) {
            throw new ContractException('A deployment fee limit must be a positive amount with six TRX decimals.');
        }

        $constructor = $abi->constructor();
        if ($constructor === null && $constructorArguments !== []) {
            throw new ContractException('Constructor arguments were supplied but the ABI has no constructor.');
        }
        $this->value = ContractValue::create(
            $callValue,
            $tokenId,
            $tokenValue,
            $constructor?->isPayable() ?? false,
            'contract constructor',
        );

        $encodedArguments = $constructor === null
            ? ''
            : $codec->encodeParameters($constructor->inputs(), $constructorArguments);
        $this->deploymentBytecode = ByteString::fromHex($bytecode->toHex(false) . $encodedArguments);
    }

    /**
     * Returns compiled bytecode with ABI-encoded constructor arguments appended.
     */
    public function deploymentBytecode(): ByteString
    {
        return $this->deploymentBytecode;
    }

    /**
     * Returns the exact native TRX value delivered to the constructor.
     */
    public function callValue(): Amount
    {
        return $this->value->nativeAmount();
    }

    /**
     * Returns the optional exact atomic TRC-10 constructor value.
     */
    public function tokenValue(): ?Amount
    {
        return $this->value->tokenAmount();
    }

    /**
     * Returns deploycontract HTTP fields excluding factory-managed owner and fee.
     *
     * @return array<string, mixed>
     * @throws JsonException When the in-memory ABI cannot be serialized.
     */
    public function nodeFields(): array
    {
        $fields = [
            'abi' => json_encode($this->abi, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'bytecode' => $this->deploymentBytecode->toHex(false),
            'name' => $this->name,
            'origin_energy_limit' => $this->originEnergyLimit,
            'consume_user_resource_percent' => $this->callerEnergyPercent,
            ...$this->value->requestFields(),
        ];

        return $fields;
    }

    /**
     * Returns exact CreateSmartContract raw_data fields for intent verification.
     *
     * @return array<string, mixed>
     */
    public function contractFields(): array
    {
        $fields = [
            'new_contract' => [
                'origin_address' => $this->ownerAddress,
                'abi' => $this->abi,
                'bytecode' => $this->deploymentBytecode,
                'call_value' => $this->value->nativeAmount()->atomicInteger(),
                'consume_user_resource_percent' => $this->callerEnergyPercent,
                'name' => $this->name,
                'origin_energy_limit' => $this->originEnergyLimit,
            ],
            ...$this->value->tokenFields(),
        ];

        return $fields;
    }
}
