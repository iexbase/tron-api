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

/**
 * Provides canonical TRC-20, TRC-721, and TRC-1155 interface definitions once.
 */
final class TokenAbi
{
    private static ?Abi $trc20 = null;
    private static ?Abi $trc721 = null;
    private static ?Abi $trc1155 = null;

    /**
     * Returns the canonical fungible-token ABI used by Trc20Contract.
     */
    public static function trc20(): Abi
    {
        return self::$trc20 ??= Abi::fromArray([
            self::function('name', [], [self::parameter('', 'string')], 'view'),
            self::function('symbol', [], [self::parameter('', 'string')], 'view'),
            self::function('decimals', [], [self::parameter('', 'uint8')], 'view'),
            self::function('totalSupply', [], [self::parameter('', 'uint256')], 'view'),
            self::function('balanceOf', [self::parameter('account', 'address')], [self::parameter('', 'uint256')], 'view'),
            self::function('transfer', [self::parameter('to', 'address'), self::parameter('value', 'uint256')], [self::parameter('', 'bool')]),
            self::function('allowance', [self::parameter('owner', 'address'), self::parameter('spender', 'address')], [self::parameter('', 'uint256')], 'view'),
            self::function('approve', [self::parameter('spender', 'address'), self::parameter('value', 'uint256')], [self::parameter('', 'bool')]),
            self::function('transferFrom', [self::parameter('from', 'address'), self::parameter('to', 'address'), self::parameter('value', 'uint256')], [self::parameter('', 'bool')]),
            self::event('Transfer', [
                self::parameter('from', 'address', true),
                self::parameter('to', 'address', true),
                self::parameter('value', 'uint256'),
            ]),
            self::event('Approval', [
                self::parameter('owner', 'address', true),
                self::parameter('spender', 'address', true),
                self::parameter('value', 'uint256'),
            ]),
        ]);
    }

    /**
     * Returns the canonical non-fungible-token and metadata ABI.
     */
    public static function trc721(): Abi
    {
        return self::$trc721 ??= Abi::fromArray([
            self::supportsInterface(),
            self::function('balanceOf', [self::parameter('owner', 'address')], [self::parameter('', 'uint256')], 'view'),
            self::function('ownerOf', [self::parameter('tokenId', 'uint256')], [self::parameter('', 'address')], 'view'),
            self::function('safeTransferFrom', [self::parameter('from', 'address'), self::parameter('to', 'address'), self::parameter('tokenId', 'uint256')]),
            self::function('safeTransferFrom', [self::parameter('from', 'address'), self::parameter('to', 'address'), self::parameter('tokenId', 'uint256'), self::parameter('data', 'bytes')]),
            self::function('transferFrom', [self::parameter('from', 'address'), self::parameter('to', 'address'), self::parameter('tokenId', 'uint256')]),
            self::function('approve', [self::parameter('to', 'address'), self::parameter('tokenId', 'uint256')]),
            self::function('setApprovalForAll', [self::parameter('operator', 'address'), self::parameter('approved', 'bool')]),
            self::function('getApproved', [self::parameter('tokenId', 'uint256')], [self::parameter('', 'address')], 'view'),
            self::function('isApprovedForAll', [self::parameter('owner', 'address'), self::parameter('operator', 'address')], [self::parameter('', 'bool')], 'view'),
            self::function('name', [], [self::parameter('', 'string')], 'view'),
            self::function('symbol', [], [self::parameter('', 'string')], 'view'),
            self::function('tokenURI', [self::parameter('tokenId', 'uint256')], [self::parameter('', 'string')], 'view'),
            self::event('Transfer', [
                self::parameter('from', 'address', true),
                self::parameter('to', 'address', true),
                self::parameter('tokenId', 'uint256', true),
            ]),
            self::event('Approval', [
                self::parameter('owner', 'address', true),
                self::parameter('approved', 'address', true),
                self::parameter('tokenId', 'uint256', true),
            ]),
            self::event('ApprovalForAll', [
                self::parameter('owner', 'address', true),
                self::parameter('operator', 'address', true),
                self::parameter('approved', 'bool'),
            ]),
        ]);
    }

    /**
     * Returns the canonical multi-token ABI including batch operations and metadata.
     */
    public static function trc1155(): Abi
    {
        return self::$trc1155 ??= Abi::fromArray([
            self::supportsInterface(),
            self::function('balanceOf', [self::parameter('account', 'address'), self::parameter('id', 'uint256')], [self::parameter('', 'uint256')], 'view'),
            self::function('balanceOfBatch', [self::parameter('accounts', 'address[]'), self::parameter('ids', 'uint256[]')], [self::parameter('', 'uint256[]')], 'view'),
            self::function('setApprovalForAll', [self::parameter('operator', 'address'), self::parameter('approved', 'bool')]),
            self::function('isApprovedForAll', [self::parameter('account', 'address'), self::parameter('operator', 'address')], [self::parameter('', 'bool')], 'view'),
            self::function('safeTransferFrom', [self::parameter('from', 'address'), self::parameter('to', 'address'), self::parameter('id', 'uint256'), self::parameter('amount', 'uint256'), self::parameter('data', 'bytes')]),
            self::function('safeBatchTransferFrom', [self::parameter('from', 'address'), self::parameter('to', 'address'), self::parameter('ids', 'uint256[]'), self::parameter('amounts', 'uint256[]'), self::parameter('data', 'bytes')]),
            self::function('uri', [self::parameter('id', 'uint256')], [self::parameter('', 'string')], 'view'),
            self::event('TransferSingle', [
                self::parameter('operator', 'address', true),
                self::parameter('from', 'address', true),
                self::parameter('to', 'address', true),
                self::parameter('id', 'uint256'),
                self::parameter('value', 'uint256'),
            ]),
            self::event('TransferBatch', [
                self::parameter('operator', 'address', true),
                self::parameter('from', 'address', true),
                self::parameter('to', 'address', true),
                self::parameter('ids', 'uint256[]'),
                self::parameter('values', 'uint256[]'),
            ]),
            self::event('ApprovalForAll', [
                self::parameter('account', 'address', true),
                self::parameter('operator', 'address', true),
                self::parameter('approved', 'bool'),
            ]),
            self::event('URI', [
                self::parameter('value', 'string'),
                self::parameter('id', 'uint256', true),
            ]),
        ]);
    }

    /**
     * Returns the shared ERC-165 interface-detection function record.
     *
     * @return array<string, mixed>
     */
    private static function supportsInterface(): array
    {
        return self::function(
            'supportsInterface',
            [self::parameter('interfaceId', 'bytes4')],
            [self::parameter('', 'bool')],
            'view',
        );
    }

    /**
     * Builds one standard function ABI record.
     *
     * @param list<array<string, mixed>> $inputs Ordered function inputs.
     * @param list<array<string, mixed>> $outputs Ordered function outputs.
     * @return array<string, mixed>
     */
    private static function function(
        string $name,
        array $inputs,
        array $outputs = [],
        string $stateMutability = 'nonpayable',
    ): array {
        return [
            'type' => 'function',
            'name' => $name,
            'inputs' => $inputs,
            'outputs' => $outputs,
            'stateMutability' => $stateMutability,
        ];
    }

    /**
     * Builds one non-anonymous standard event ABI record.
     *
     * @param list<array<string, mixed>> $inputs Ordered event parameters.
     * @return array<string, mixed>
     */
    private static function event(string $name, array $inputs): array
    {
        return [
            'type' => 'event',
            'name' => $name,
            'inputs' => $inputs,
            'anonymous' => false,
            'stateMutability' => 'nonpayable',
        ];
    }

    /**
     * Builds one elementary standard ABI parameter record.
     *
     * @return array<string, mixed>
     */
    private static function parameter(string $name, string $type, bool $indexed = false): array
    {
        return ['name' => $name, 'type' => $type, 'indexed' => $indexed];
    }
}
