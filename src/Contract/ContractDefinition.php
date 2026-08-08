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

use IEXBase\TronAPI\Api\DataDecoder;
use IEXBase\TronAPI\Exception\ContractException;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\ByteString;
use JsonSerializable;

/**
 * Represents a deployed contract, its ABI, bytecode, owner, and resource policy.
 */
final readonly class ContractDefinition implements JsonSerializable
{
    /** @var array<string, mixed> */
    private array $rawData;

    /**
     * Stores typed contract metadata while preserving the original node object.
     *
     * @param array<string, mixed> $rawData Complete getcontract response.
     */
    private function __construct(
        public Address $address,
        public ?Address $originAddress,
        public string $name,
        public Abi $abi,
        public ByteString $bytecode,
        public int $callerEnergyPercent,
        public string $originEnergyLimit,
        array $rawData,
    ) {
        $this->rawData = $rawData;
    }

    /**
     * Creates a definition from a getcontract response and checks its address.
     *
     * @param array<string, mixed> $data Native contract response.
     */
    public static function fromNodeData(array $data, Address $requestedAddress): self
    {
        $addressValue = $data['contract_address'] ?? $requestedAddress->toBase58();
        $address = Address::fromString(DataDecoder::string($addressValue, 'contract_address'));
        if (!$address->equals($requestedAddress)) {
            throw new ContractException('The node returned metadata for a different contract address.');
        }

        $originValue = $data['origin_address'] ?? null;
        $originAddress = $originValue === null
            ? null
            : Address::fromString(DataDecoder::string($originValue, 'origin_address'));
        $abiData = $data['abi'] ?? [];
        if (!is_array($abiData)) {
            throw new ContractException('The deployed contract ABI must be an object or list.');
        }
        $bytecode = DataDecoder::string($data['bytecode'] ?? '', 'bytecode');
        $callerEnergyPercent = DataDecoder::integer(
            $data['consume_user_resource_percent'] ?? 100,
            'consume_user_resource_percent',
        );
        if ($callerEnergyPercent > 100) {
            throw new ContractException('The contract caller Energy percentage exceeds 100.');
        }

        return new self(
            $address,
            $originAddress,
            DataDecoder::string($data['name'] ?? '', 'name'),
            Abi::fromArray($abiData),
            ByteString::fromHex($bytecode),
            $callerEnergyPercent,
            DataDecoder::unsignedDecimal($data['origin_energy_limit'] ?? 0, 'origin_energy_limit'),
            $data,
        );
    }

    /**
     * Resolves one overloaded function through this contract's ABI.
     */
    public function function(string $nameOrSignature, ?int $argumentCount = null): AbiEntry
    {
        return $this->abi->function($nameOrSignature, $argumentCount);
    }

    /**
     * Returns the untouched getcontract response.
     *
     * @return array<string, mixed>
     */
    public function rawData(): array
    {
        return $this->rawData;
    }

    /**
     * Serializes the original lossless contract response.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->rawData;
    }
}
