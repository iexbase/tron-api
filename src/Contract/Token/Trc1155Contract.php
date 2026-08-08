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
use IEXBase\TronAPI\Value\ByteString;
use IEXBase\TronAPI\Value\Memo;

/**
 * Provides canonical TRC-1155 single/batch balance, approval, and transfer flows.
 */
final class Trc1155Contract extends OperatorTokenContract
{
    /**
     * Creates a TRC-1155 client with the canonical ABI or a compatible explicit ABI.
     */
    public function __construct(
        ContractService $contracts,
        Address $contractAddress,
        Address $callerAddress,
        ?Abi $abi = null,
    ) {
        parent::__construct($contracts, $contractAddress, $callerAddress, $abi ?? TokenAbi::trc1155());
    }

    /**
     * Returns one account/token balance as exact unsigned decimal text.
     */
    public function balanceOf(Address $account, int|string $tokenId): string
    {
        return $this->stringOutput($this->read(
            'balanceOf(address,uint256)',
            [$account, $this->tokenId($tokenId)],
        ));
    }

    /**
     * Returns balances for equally sized account and token ID lists.
     *
     * @param list<Address>    $accounts Account list.
     * @param list<int|string> $tokenIds Token ID list.
     * @return list<string>
     */
    public function balanceOfBatch(array $accounts, array $tokenIds): array
    {
        $this->requireParallelLists($accounts, $tokenIds, 'balance');
        $ids = $this->decimalValues($tokenIds);
        $value = $this->read('balanceOfBatch(address[],uint256[])', [$accounts, $ids])->value(0);
        if (!is_array($value) || !array_is_list($value)) {
            throw new ContractException('The TRC-1155 batch balance output is not a list.');
        }

        return array_map(static function (mixed $balance): string {
            if (!is_string($balance)) {
                throw new ContractException('A TRC-1155 batch balance is not an unsigned integer string.');
            }

            return $balance;
        }, $value);
    }

    /**
     * Returns the metadata URI template for one token ID.
     */
    public function uri(int|string $tokenId): string
    {
        return $this->stringOutput($this->read('uri(uint256)', [$this->tokenId($tokenId)]));
    }

    /**
     * Builds a verified single-token safe transfer transaction.
     */
    public function safeTransferFrom(
        Address $owner,
        Address $recipient,
        int|string $tokenId,
        int|string $amount,
        ByteString $data,
        Amount $feeLimit,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        return $this->createTransaction(
            'safeTransferFrom(address,address,uint256,uint256,bytes)',
            [$owner, $recipient, $this->tokenId($tokenId), $this->tokenId($amount), $data],
            $feeLimit,
            $memo,
            $permissionId,
        );
    }

    /**
     * Builds a verified multi-token safe batch transfer transaction.
     *
     * @param list<int|string> $tokenIds Token ID list.
     * @param list<int|string> $amounts Exact atomic amount list.
     */
    public function safeBatchTransferFrom(
        Address $owner,
        Address $recipient,
        array $tokenIds,
        array $amounts,
        ByteString $data,
        Amount $feeLimit,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        $this->requireParallelLists($tokenIds, $amounts, 'transfer');
        $ids = $this->decimalValues($tokenIds);
        $values = $this->decimalValues($amounts);

        return $this->createTransaction(
            'safeBatchTransferFrom(address,address,uint256[],uint256[],bytes)',
            [$owner, $recipient, $ids, $values, $data],
            $feeLimit,
            $memo,
            $permissionId,
        );
    }

    /**
     * Requires two non-empty TRC-1155 batch lists to contain the same item count.
     *
     * @param list<mixed> $left First parallel list.
     * @param list<mixed> $right Second parallel list.
     */
    private function requireParallelLists(array $left, array $right, string $operation): void
    {
        if ($left === [] || count($left) !== count($right)) {
            throw new ContractException(sprintf(
                'TRC-1155 batch %s lists must be non-empty and equally sized.',
                $operation,
            ));
        }
    }

    /**
     * Converts token IDs or atomic amounts to canonical unsigned decimal values.
     *
     * @param list<int|string> $values Values to validate.
     * @return list<string>
     */
    private function decimalValues(array $values): array
    {
        return array_map(fn (int|string $value): string => $this->tokenId($value), $values);
    }
}
