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

namespace IEXBase\TronAPI\JsonRpc;

use IEXBase\TronAPI\Api\ApiClient;
use IEXBase\TronAPI\Api\Endpoint;
use IEXBase\TronAPI\Encoding\Hex;
use IEXBase\TronAPI\Exception\JsonRpcException;
use IEXBase\TronAPI\Exception\ResponseDecodingException;
use IEXBase\TronAPI\Exception\ValidationException;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\Amount;
use IEXBase\TronAPI\Value\ByteString;

/**
 * Implements the complete documented TRON FullNode JSON-RPC method surface.
 */
final class JsonRpcClient
{
    private const LATEST_BLOCK = 'latest';

    private int $nextRequestId = 1;

    /**
     * Creates a JSON-RPC client over the independently configured JSON-RPC role.
     */
    public function __construct(private readonly ApiClient $client)
    {
    }

    /**
     * Calls any current or future JSON-RPC method and validates its envelope ID.
     *
     * @param array<mixed> $parameters JSON-RPC parameters to validate as an ordered list.
     */
    public function request(string $method, array $parameters = []): mixed
    {
        if ($method === ''
            || preg_match('//u', $method) !== 1
            || preg_match('/[\x00-\x20\x7F]/', $method) === 1
        ) {
            throw new ValidationException(
                'A JSON-RPC method name must be non-empty text without whitespace or control bytes.',
            );
        }
        if (!array_is_list($parameters)) {
            throw new ValidationException('TRON JSON-RPC parameters must be an ordered list.');
        }

        $requestId = $this->nextRequestId++;
        $response = $this->client->request(Endpoint::JsonRpc->request([
            'jsonrpc' => '2.0',
            'id' => $requestId,
            'method' => $method,
            'params' => $parameters,
        ]));
        if ($response->value('jsonrpc') !== '2.0' || $response->value('id') !== $requestId) {
            throw new ResponseDecodingException('The JSON-RPC response version or request ID does not match.');
        }
        $data = $response->data();
        $hasResult = array_key_exists('result', $data);
        $hasError = array_key_exists('error', $data);
        if ($hasResult === $hasError) {
            throw new ResponseDecodingException('A JSON-RPC response must contain exactly one result or error field.');
        }
        if ($hasError) {
            $error = $data['error'];
            if (!is_array($error)
                || !is_int($error['code'] ?? null)
                || !is_string($error['message'] ?? null)
            ) {
                throw new ResponseDecodingException('The JSON-RPC response contains a malformed error object.');
            }

            throw new JsonRpcException(
                $error['code'],
                $error['message'],
                $error['data'] ?? null,
            );
        }
        return $data['result'];
    }

    /**
     * Returns every account address owned by the configured JSON-RPC node.
     *
     * @return list<Address>
     */
    public function accounts(): array
    {
        $result = $this->request('eth_accounts');
        if (!is_array($result) || !array_is_list($result)) {
            throw new ResponseDecodingException('JSON-RPC method `eth_accounts` did not return a list.');
        }

        $addresses = [];
        foreach ($result as $value) {
            if (!is_string($value)) {
                throw new ResponseDecodingException('JSON-RPC method `eth_accounts` returned a non-string address.');
            }
            $addresses[] = self::address($value, 'eth_accounts');
        }

        return $addresses;
    }

    /**
     * Returns an account TRX balance in exact sun units.
     */
    public function balance(
        Address $address,
        BlockTag|Quantity $block = BlockTag::Latest,
    ): Amount
    {
        return Amount::fromAtomic($this->quantityResult('eth_getBalance', [
            $address->toEvmHex(),
            self::latestStateBlock($block, 'eth_getBalance'),
        ])->decimal());
    }

    /**
     * Returns the latest block number.
     */
    public function blockNumber(): Quantity
    {
        return $this->quantityResult('eth_blockNumber');
    }

    /**
     * Returns a block by 32-byte hash with hashes or complete transactions.
     */
    public function blockByHash(string $hash, bool $completeTransactions = false): mixed
    {
        return $this->request('eth_getBlockByHash', [JsonRpcParameter::hash32($hash), $completeTransactions]);
    }

    /**
     * Returns a block by tag/number with hashes or complete transactions.
     */
    public function blockByNumber(BlockTag|Quantity $block, bool $completeTransactions = false): mixed
    {
        return $this->request('eth_getBlockByNumber', [JsonRpcParameter::block($block), $completeTransactions]);
    }

    /**
     * Returns every receipt in one block reference.
     */
    public function blockReceipts(BlockTag|Quantity $block): mixed
    {
        return $this->request('eth_getBlockReceipts', [JsonRpcParameter::block($block)]);
    }

    /**
     * Returns every receipt in a block selected by its 32-byte hash.
     */
    public function blockReceiptsByHash(string $hash): mixed
    {
        return $this->request('eth_getBlockReceipts', [JsonRpcParameter::hash32($hash)]);
    }

    /**
     * Returns transaction count for a block hash.
     */
    public function blockTransactionCountByHash(string $hash): Quantity
    {
        return $this->quantityResult('eth_getBlockTransactionCountByHash', [JsonRpcParameter::hash32($hash)]);
    }

    /**
     * Returns transaction count for a block tag or number.
     */
    public function blockTransactionCountByNumber(BlockTag|Quantity $block): Quantity
    {
        return $this->quantityResult('eth_getBlockTransactionCountByNumber', [JsonRpcParameter::block($block)]);
    }

    /**
     * Returns the current TRON work/block hash tuple.
     */
    public function work(): mixed
    {
        return $this->request('eth_getWork');
    }

    /**
     * Returns a transaction by hash, or null when unavailable.
     */
    public function transactionByHash(string $hash): mixed
    {
        return $this->request('eth_getTransactionByHash', [JsonRpcParameter::hash32($hash)]);
    }

    /**
     * Returns a transaction at an index within a hash-selected block.
     */
    public function transactionByBlockHashAndIndex(string $hash, Quantity $index): mixed
    {
        return $this->request('eth_getTransactionByBlockHashAndIndex', [JsonRpcParameter::hash32($hash), $index->hex()]);
    }

    /**
     * Returns a transaction at an index within a tag/number-selected block.
     */
    public function transactionByBlockNumberAndIndex(BlockTag|Quantity $block, Quantity $index): mixed
    {
        return $this->request('eth_getTransactionByBlockNumberAndIndex', [JsonRpcParameter::block($block), $index->hex()]);
    }

    /**
     * Returns an Ethereum-compatible transaction receipt by hash.
     */
    public function transactionReceipt(string $hash): mixed
    {
        return $this->request('eth_getTransactionReceipt', [JsonRpcParameter::hash32($hash)]);
    }

    /**
     * Executes an Ethereum-compatible read-only call object.
     *
     * @param array<string, mixed> $call Call fields using hexadecimal addresses/quantities.
     */
    public function call(
        array $call,
        BlockTag|Quantity|CallBlockReference $block = BlockTag::Latest,
    ): ByteString
    {
        return ByteString::fromHex($this->stringResult('eth_call', [
            $call,
            self::callBlock($block),
        ]));
    }

    /**
     * Builds a native TRON transaction from the documented JSON-RPC call object.
     *
     * @param array<string, mixed> $transaction BuildTransaction fields.
     */
    public function buildTransaction(array $transaction): mixed
    {
        return $this->request('buildTransaction', [$transaction]);
    }

    /**
     * Returns deployed bytecode at an address using java-tron's latest state.
     */
    public function code(
        Address $address,
        BlockTag|Quantity $block = BlockTag::Latest,
    ): ByteString
    {
        return ByteString::fromHex($this->stringResult('eth_getCode', [
            $address->toEvmHex(),
            self::latestStateBlock($block, 'eth_getCode'),
        ]));
    }

    /**
     * Returns one 32-byte contract storage slot.
     */
    public function storageAt(
        Address $address,
        Quantity $position,
        BlockTag|Quantity $block = BlockTag::Latest,
    ): ByteString {
        return ByteString::fromHex($this->stringResult('eth_getStorageAt', [
            $address->toEvmHex(),
            $position->hex(),
            self::latestStateBlock($block, 'eth_getStorageAt'),
        ]));
    }

    /**
     * Estimates Energy for an Ethereum-compatible call object.
     *
     * @param array<string, mixed> $call Call fields.
     */
    public function estimateEnergy(array $call): Quantity
    {
        return $this->quantityResult('eth_estimateGas', [$call]);
    }

    /**
     * Returns the current Energy unit price as a JSON-RPC quantity.
     */
    public function energyPrice(): Quantity
    {
        return $this->quantityResult('eth_gasPrice');
    }

    /**
     * Creates a stateful log filter and returns its identifier.
     */
    public function createLogFilter(LogFilter $filter): Quantity
    {
        $filter->assertStatefulCompatibility();

        return $this->quantityResult('eth_newFilter', [$filter->toArray()]);
    }

    /**
     * Creates a stateful new-block filter and returns its identifier.
     */
    public function createBlockFilter(): Quantity
    {
        return $this->quantityResult('eth_newBlockFilter');
    }

    /**
     * Returns changes since the last poll of a stateful filter.
     */
    public function filterChanges(Quantity $filterId): mixed
    {
        return $this->request('eth_getFilterChanges', [$filterId->hex()]);
    }

    /**
     * Returns every log currently matching a stateful filter.
     */
    public function filterLogs(Quantity $filterId): mixed
    {
        return $this->request('eth_getFilterLogs', [$filterId->hex()]);
    }

    /**
     * Executes a stateless strict log search.
     */
    public function logs(LogFilter $filter): mixed
    {
        return $this->request('eth_getLogs', [$filter->toArray()]);
    }

    /**
     * Removes a stateful filter and reports whether it existed.
     */
    public function uninstallFilter(Quantity $filterId): bool
    {
        return $this->booleanResult('eth_uninstallFilter', [$filterId->hex()]);
    }

    /**
     * Returns the chain identifier.
     */
    public function chainId(): Quantity
    {
        return $this->quantityResult('eth_chainId');
    }

    /**
     * Returns the node's current block-producing account address.
     */
    public function coinbase(): Address
    {
        return self::address($this->stringResult('eth_coinbase'), 'eth_coinbase');
    }

    /**
     * Returns the node's TRON block protocol version quantity.
     */
    public function protocolVersion(): Quantity
    {
        return $this->quantityResult('eth_protocolVersion');
    }

    /**
     * Returns false when synchronized or the node's sync progress object.
     */
    public function syncing(): mixed
    {
        return $this->request('eth_syncing');
    }

    /**
     * Returns whether the JSON-RPC node is listening for peers.
     */
    public function isListening(): bool
    {
        return $this->booleanResult('net_listening');
    }

    /**
     * Returns the connected peer count.
     */
    public function peerCount(): Quantity
    {
        return $this->quantityResult('net_peerCount');
    }

    /**
     * Returns the TRON network/genesis identifier exposed by net_version.
     */
    public function networkVersion(): string
    {
        return $this->stringResult('net_version');
    }

    /**
     * Returns the node software and version description.
     */
    public function clientVersion(): string
    {
        return $this->stringResult('web3_clientVersion');
    }

    /**
     * Returns Keccak-256 of arbitrary bytes as a 32-byte value.
     */
    public function sha3(ByteString $data): ByteString
    {
        return ByteString::fromHex($this->stringResult('web3_sha3', [$data->toHex()]));
    }

    /**
     * Calls a method and requires a canonical quantity result.
     *
     * @param list<mixed> $parameters Ordered JSON-RPC parameters.
     */
    private function quantityResult(string $method, array $parameters = []): Quantity
    {
        return Quantity::fromHex($this->stringResult($method, $parameters));
    }

    /**
     * Calls a method and requires a strict string result.
     *
     * @param list<mixed> $parameters Ordered JSON-RPC parameters.
     */
    private function stringResult(string $method, array $parameters = []): string
    {
        $result = $this->request($method, $parameters);
        if (!is_string($result)) {
            throw new ResponseDecodingException(sprintf('JSON-RPC method `%s` did not return a string.', $method));
        }

        return $result;
    }

    /**
     * Calls a method and requires a strict boolean result.
     *
     * @param list<mixed> $parameters Ordered JSON-RPC parameters.
     */
    private function booleanResult(string $method, array $parameters = []): bool
    {
        $result = $this->request($method, $parameters);
        if (!is_bool($result)) {
            throw new ResponseDecodingException(sprintf('JSON-RPC method `%s` did not return boolean.', $method));
        }

        return $result;
    }

    /**
     * Returns java-tron's only supported scalar state selector.
     */
    private static function latestStateBlock(BlockTag|Quantity $block, string $method): string
    {
        if ($block !== BlockTag::Latest) {
            throw new ValidationException(sprintf(
                'TRON JSON-RPC method `%s` supports only the latest state.',
                $method,
            ));
        }

        return self::LATEST_BLOCK;
    }

    /**
     * Returns the documented eth_call scalar or object block selector.
     *
     * @return string|array{blockNumber: string}|array{blockHash: string}
     */
    private static function callBlock(BlockTag|Quantity|CallBlockReference $block): string|array
    {
        if ($block instanceof CallBlockReference) {
            return $block->toArray();
        }
        if ($block instanceof Quantity) {
            return CallBlockReference::forNumber($block)->toArray();
        }
        if ($block !== BlockTag::Latest) {
            throw new ValidationException('TRON eth_call supports only `latest` as a scalar block tag.');
        }

        return self::LATEST_BLOCK;
    }

    /**
     * Parses a 20-byte EVM or 21-byte TRON hexadecimal JSON-RPC address.
     */
    private static function address(string $value, string $method): Address
    {
        try {
            $hex = Hex::canonicalize($value);

            return match (strlen($hex)) {
                Address::EVM_BYTES * 2 => Address::fromEvmHex($hex),
                Address::PAYLOAD_BYTES * 2 => Address::fromHex($hex),
                default => throw new ValidationException('The address has an unsupported byte length.'),
            };
        } catch (ValidationException $exception) {
            throw new ResponseDecodingException(
                sprintf('JSON-RPC method `%s` returned an invalid address.', $method),
                0,
                $exception,
            );
        }
    }
}
