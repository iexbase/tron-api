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
use IEXBase\TronAPI\Service\ContractService;
use IEXBase\TronAPI\Transaction\Transaction;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\Amount;
use IEXBase\TronAPI\Value\ByteString;
use IEXBase\TronAPI\Value\Memo;

/**
 * Provides canonical TRC-721 ownership, approval, metadata, and transfer flows.
 */
final class Trc721Contract extends OperatorTokenContract
{
    /**
     * Creates a TRC-721 client with the canonical ABI or an explicit compatible ABI.
     */
    public function __construct(
        ContractService $contracts,
        Address $contractAddress,
        Address $callerAddress,
        ?Abi $abi = null,
    ) {
        parent::__construct($contracts, $contractAddress, $callerAddress, $abi ?? TokenAbi::trc721());
    }

    /**
     * Returns the number of NFTs owned by one account as exact decimal text.
     */
    public function balanceOf(Address $owner): string
    {
        return $this->stringOutput($this->read('balanceOf(address)', [$owner]));
    }

    /**
     * Returns the current owner of one token ID.
     */
    public function ownerOf(int|string $tokenId): Address
    {
        return $this->addressOutput($this->read(
            'ownerOf(uint256)',
            [$this->unsignedDecimal($tokenId, 'token ID')],
        ));
    }

    /**
     * Returns the collection's user-visible name.
     */
    public function name(): string
    {
        return $this->stringOutput($this->read('name()'));
    }

    /**
     * Returns the collection's ticker symbol.
     */
    public function symbol(): string
    {
        return $this->stringOutput($this->read('symbol()'));
    }

    /**
     * Returns the metadata URI for one token ID.
     */
    public function tokenUri(int|string $tokenId): string
    {
        return $this->stringOutput($this->read(
            'tokenURI(uint256)',
            [$this->unsignedDecimal($tokenId, 'token ID')],
        ));
    }

    /**
     * Returns the address approved for one token ID.
     */
    public function approvedAddress(int|string $tokenId): Address
    {
        return $this->addressOutput($this->read(
            'getApproved(uint256)',
            [$this->unsignedDecimal($tokenId, 'token ID')],
        ));
    }

    /**
     * Builds a verified direct TRC-721 transfer transaction.
     */
    public function transferFrom(
        Address $owner,
        Address $recipient,
        int|string $tokenId,
        Amount $feeLimit,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        return $this->transferTransaction(
            'transferFrom(address,address,uint256)',
            $owner,
            $recipient,
            $tokenId,
            $feeLimit,
            $memo,
            $permissionId,
        );
    }

    /**
     * Builds a verified safe TRC-721 transfer without callback data.
     */
    public function safeTransferFrom(
        Address $owner,
        Address $recipient,
        int|string $tokenId,
        Amount $feeLimit,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        return $this->transferTransaction(
            'safeTransferFrom(address,address,uint256)',
            $owner,
            $recipient,
            $tokenId,
            $feeLimit,
            $memo,
            $permissionId,
        );
    }

    /**
     * Builds one three-argument ownership transfer for the selected ABI overload.
     */
    private function transferTransaction(
        string $signature,
        Address $owner,
        Address $recipient,
        int|string $tokenId,
        Amount $feeLimit,
        ?Memo $memo,
        int $permissionId,
    ): Transaction {
        return $this->createTransaction(
            $signature,
            [$owner, $recipient, $this->unsignedDecimal($tokenId, 'token ID')],
            $feeLimit,
            $memo,
            $permissionId,
        );
    }

    /**
     * Builds the overloaded safe TRC-721 transfer that includes callback bytes.
     */
    public function safeTransferFromWithData(
        Address $owner,
        Address $recipient,
        int|string $tokenId,
        ByteString $data,
        Amount $feeLimit,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        return $this->createTransaction(
            'safeTransferFrom(address,address,uint256,bytes)',
            [$owner, $recipient, $this->unsignedDecimal($tokenId, 'token ID'), $data],
            $feeLimit,
            $memo,
            $permissionId,
        );
    }

    /**
     * Builds a verified transaction that approves one address for one token.
     */
    public function approve(
        Address $approved,
        int|string $tokenId,
        Amount $feeLimit,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        return $this->createTransaction(
            'approve(address,uint256)',
            [$approved, $this->unsignedDecimal($tokenId, 'token ID')],
            $feeLimit,
            $memo,
            $permissionId,
        );
    }
}
