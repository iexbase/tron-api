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

/**
 * Captures one ABI-resolved contract call shared by read, estimate, and send flows.
 */
final readonly class ContractCall
{
    private ContractValue $value;
    private string $parameterHex;
    private string $dataHex;

    /**
     * Encodes arguments once and validates native/TRC-10 values and payability.
     *
     * @param array<mixed> $arguments Ordered or named ABI arguments.
     */
    public function __construct(
        public Address $callerAddress,
        public Address $contractAddress,
        public AbiEntry $function,
        array $arguments = [],
        ?Amount $callValue = null,
        ?string $tokenId = null,
        ?Amount $tokenValue = null,
        public ?Abi $abi = null,
        AbiCodec $codec = new AbiCodec(),
    ) {
        if ($function->type !== 'function') {
            throw new ContractException('A contract call requires a function ABI entry.');
        }

        $this->value = ContractValue::create(
            $callValue,
            $tokenId,
            $tokenValue,
            $function->isPayable(),
            'contract function',
        );
        $this->parameterHex = $codec->encodeParameters($function->inputs(), $arguments);
        $this->dataHex = $function->selector() . $this->parameterHex;
    }

    /**
     * Creates a call by resolving a function directly from a deployed definition.
     *
     * @param array<mixed> $arguments Ordered or named ABI arguments.
     */
    public static function forContract(
        ContractDefinition $contract,
        Address $callerAddress,
        string $nameOrSignature,
        array $arguments = [],
        ?Amount $callValue = null,
        ?string $tokenId = null,
        ?Amount $tokenValue = null,
        ?int $argumentCount = null,
        AbiCodec $codec = new AbiCodec(),
    ): self {
        return new self(
            $callerAddress,
            $contract->address,
            $contract->function($nameOrSignature, $argumentCount ?? count($arguments)),
            $arguments,
            $callValue,
            $tokenId,
            $tokenValue,
            $contract->abi,
            $codec,
        );
    }

    /**
     * Returns the exact native TRX call value.
     */
    public function callValue(): Amount
    {
        return $this->value->nativeAmount();
    }

    /**
     * Returns the optional exact atomic TRC-10 call value.
     */
    public function tokenValue(): ?Amount
    {
        return $this->value->tokenAmount();
    }

    /**
     * Returns ABI arguments without the four-byte function selector.
     */
    public function parameterHex(): string
    {
        return $this->parameterHex;
    }

    /**
     * Returns the complete four-byte selector plus ABI arguments.
     */
    public function dataHex(): string
    {
        return $this->dataHex;
    }

    /**
     * Returns request fields shared by constant, estimate, and trigger endpoints.
     *
     * @return array<string, mixed>
     */
    public function nodeParameters(): array
    {
        return [
            'owner_address' => $this->callerAddress->toBase58(),
            ...$this->transactionRequestFields(),
            'visible' => true,
        ];
    }

    /**
     * Returns trigger request fields managed outside owner/fee/memo handling.
     *
     * @return array<string, mixed>
     */
    public function transactionRequestFields(): array
    {
        $fields = [
            'contract_address' => $this->contractAddress,
            'function_selector' => $this->function->signature(),
            'parameter' => $this->parameterHex,
            ...$this->value->requestFields(),
        ];

        return $fields;
    }

    /**
     * Returns exact TriggerSmartContract raw_data fields for intent verification.
     *
     * @return array<string, mixed>
     */
    public function contractFields(): array
    {
        $fields = [
            'contract_address' => $this->contractAddress,
            'call_value' => $this->value->nativeAmount()->atomicInteger(),
            'data' => $this->dataHex,
            ...$this->value->tokenFields(),
        ];

        return $fields;
    }
}
