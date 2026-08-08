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

namespace IEXBase\TronAPI\Contract\Token;

use IEXBase\TronAPI\Contract\Abi;
use IEXBase\TronAPI\Exception\ContractException;
use IEXBase\TronAPI\Service\ContractService;
use IEXBase\TronAPI\Transaction\Transaction;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\Amount;
use IEXBase\TronAPI\Value\Memo;

/**
 * Provides exact-amount TRC-20 metadata, balance, allowance, and transfer flows.
 */
final class Trc20Contract extends TokenContract
{
    private ?int $cachedDecimals = null;

    /**
     * Creates a TRC-20 client with the canonical ABI or an explicit compatible ABI.
     */
    public function __construct(
        ContractService $contracts,
        Address $contractAddress,
        Address $callerAddress,
        ?Abi $abi = null,
    ) {
        parent::__construct($contracts, $contractAddress, $callerAddress, $abi ?? TokenAbi::trc20());
    }

    /**
     * Returns the token's user-visible name.
     */
    public function name(): string
    {
        return $this->stringOutput($this->read('name()'));
    }

    /**
     * Returns the token's user-visible ticker symbol.
     */
    public function symbol(): string
    {
        return $this->stringOutput($this->read('symbol()'));
    }

    /**
     * Returns and caches the token-specific atomic decimal scale.
     */
    public function decimals(): int
    {
        if ($this->cachedDecimals !== null) {
            return $this->cachedDecimals;
        }

        $value = $this->stringOutput($this->read('decimals()'));
        if (!ctype_digit($value) || (int) $value > 255) {
            throw new ContractException('The TRC-20 decimals result must be between 0 and 255.');
        }

        return $this->cachedDecimals = (int) $value;
    }

    /**
     * Returns total supply as an exact amount using this token's decimals.
     */
    public function totalSupply(): Amount
    {
        return Amount::fromAtomic($this->stringOutput($this->read('totalSupply()')), $this->decimals());
    }

    /**
     * Returns an account balance as an exact token-specific amount.
     */
    public function balanceOf(Address $account): Amount
    {
        return Amount::fromAtomic(
            $this->stringOutput($this->read('balanceOf(address)', [$account])),
            $this->decimals(),
        );
    }

    /**
     * Returns the remaining owner-to-spender allowance as an exact amount.
     */
    public function allowance(Address $owner, Address $spender): Amount
    {
        return Amount::fromAtomic(
            $this->stringOutput($this->read('allowance(address,address)', [$owner, $spender])),
            $this->decimals(),
        );
    }

    /**
     * Builds a verified TRC-20 transfer transaction.
     */
    public function transfer(
        Address $recipient,
        Amount $amount,
        Amount $feeLimit,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        return $this->addressAmountTransaction(
            'transfer(address,uint256)',
            $recipient,
            $amount,
            $feeLimit,
            $memo,
            $permissionId,
        );
    }

    /**
     * Builds a verified transaction that sets a spender allowance.
     */
    public function approve(
        Address $spender,
        Amount $amount,
        Amount $feeLimit,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        return $this->addressAmountTransaction(
            'approve(address,uint256)',
            $spender,
            $amount,
            $feeLimit,
            $memo,
            $permissionId,
        );
    }

    /**
     * Builds a two-argument transaction containing an address and token amount.
     */
    private function addressAmountTransaction(
        string $signature,
        Address $address,
        Amount $amount,
        Amount $feeLimit,
        ?Memo $memo,
        int $permissionId,
    ): Transaction {
        return $this->createTransaction(
            $signature,
            [$address, $this->atomicAmount($amount)],
            $feeLimit,
            $memo,
            $permissionId,
        );
    }

    /**
     * Builds a verified allowance-based transferFrom transaction.
     */
    public function transferFrom(
        Address $owner,
        Address $recipient,
        Amount $amount,
        Amount $feeLimit,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        return $this->createTransaction(
            'transferFrom(address,address,uint256)',
            [$owner, $recipient, $this->atomicAmount($amount)],
            $feeLimit,
            $memo,
            $permissionId,
        );
    }

    /**
     * Requires an amount to use this contract's declared decimal scale.
     */
    private function atomicAmount(Amount $amount): string
    {
        if ($amount->decimals() !== $this->decimals()) {
            throw new ContractException('The TRC-20 amount decimals do not match the token contract.');
        }

        return $amount->atomicValue();
    }
}
