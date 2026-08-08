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

namespace IEXBase\TronAPI\Tests\Contract;

use IEXBase\TronAPI\Api\ApiClient;
use IEXBase\TronAPI\Configuration\NodeConfiguration;
use IEXBase\TronAPI\Contract\Abi;
use IEXBase\TronAPI\Contract\AbiCodec;
use IEXBase\TronAPI\Contract\ContractCall;
use IEXBase\TronAPI\Contract\ContractValue;
use IEXBase\TronAPI\Contract\Token\OperatorTokenContract;
use IEXBase\TronAPI\Contract\Token\TokenAbi;
use IEXBase\TronAPI\Contract\Token\TokenContract;
use IEXBase\TronAPI\Contract\Token\Trc1155Contract;
use IEXBase\TronAPI\Contract\Token\Trc20Contract;
use IEXBase\TronAPI\Contract\Token\Trc721Contract;
use IEXBase\TronAPI\Crypto\LocalPrivateKeySigner;
use IEXBase\TronAPI\Exception\ContractException;
use IEXBase\TronAPI\Http\HttpRequest;
use IEXBase\TronAPI\Http\HttpResponse;
use IEXBase\TronAPI\Service\ContractService;
use IEXBase\TronAPI\Tests\Support\HttpResponseFactory;
use IEXBase\TronAPI\Tests\Support\QueueTransport;
use IEXBase\TronAPI\Tests\Support\TransactionFixture;
use IEXBase\TronAPI\Transaction\TransactionFactory;
use IEXBase\TronAPI\Transaction\TransactionIntent;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\Amount;
use IEXBase\TronAPI\Value\ByteString;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Verifies every typed TRC-20, TRC-721, and TRC-1155 wrapper family.
 */
#[CoversClass(TokenContract::class)]
#[CoversClass(OperatorTokenContract::class)]
#[CoversClass(Trc20Contract::class)]
#[CoversClass(Trc721Contract::class)]
#[CoversClass(Trc1155Contract::class)]
#[CoversClass(ContractValue::class)]
final class TokenStandardsTest extends TestCase
{
    private const OWNER_KEY = '0000000000000000000000000000000000000000000000000000000000000001';
    private const CONTRACT = 'TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY';
    private const RECIPIENT = 'TMVQGm1qAQYVdetCeGRRkTWYYrLXuHK2HC';

    /**
     * Reads all TRC-20 metadata and account amount families exactly.
     */
    public function testTrc20ReadFamiliesUseDeclaredDecimals(): void
    {
        $abi = TokenAbi::trc20();
        $transport = new QueueTransport(
            $this->constantResponse($abi, 'name()', ['Example Token']),
            $this->constantResponse($abi, 'symbol()', ['EXT']),
            $this->constantResponse($abi, 'decimals()', ['3']),
            $this->constantResponse($abi, 'totalSupply()', ['1000000']),
            $this->constantResponse($abi, 'balanceOf(address)', ['1250']),
            $this->constantResponse($abi, 'allowance(address,address)', ['500']),
        );
        $owner = $this->owner();
        $recipient = Address::fromBase58(self::RECIPIENT);
        $token = $this->service($transport)->trc20(Address::fromBase58(self::CONTRACT), $owner);

        self::assertSame('Example Token', $token->name());
        self::assertSame('EXT', $token->symbol());
        self::assertSame(3, $token->decimals());
        self::assertSame('1000', $token->totalSupply()->decimalValue());
        self::assertSame('1.25', $token->balanceOf($recipient)->decimalValue());
        self::assertSame('0.5', $token->allowance($owner, $recipient)->decimalValue());
    }

    /**
     * Builds every TRC-20 mutation through one cached decimal-scale rule.
     */
    public function testTrc20MutationFamiliesBuildVerifiedTransactions(): void
    {
        $abi = TokenAbi::trc20();
        $owner = $this->owner();
        $recipient = Address::fromBase58(self::RECIPIENT);
        $feeLimit = Amount::fromDecimal('10');
        $transport = new QueueTransport(
            $this->constantResponse($abi, 'decimals()', ['3']),
            $this->transactionResponse($abi, $owner, 'transfer(address,uint256)', [$recipient, '1250'], $feeLimit),
            $this->transactionResponse($abi, $owner, 'approve(address,uint256)', [$recipient, '1250'], $feeLimit),
            $this->transactionResponse($abi, $owner, 'transferFrom(address,address,uint256)', [$owner, $recipient, '1250'], $feeLimit),
        );
        $token = $this->service($transport)->trc20(Address::fromBase58(self::CONTRACT), $owner);
        $amount = Amount::fromDecimal('1.250', 3);

        $token->transfer($recipient, $amount, $feeLimit);
        $token->approve($recipient, $amount, $feeLimit);
        $token->transferFrom($owner, $recipient, $amount, $feeLimit);

        self::assertSame([
            'transfer(address,uint256)',
            'approve(address,uint256)',
            'transferFrom(address,address,uint256)',
        ], $this->selectors($transport, 1));
    }

    /**
     * Reads TRC-721 ownership, metadata, interface, and approval families.
     */
    public function testTrc721ReadFamiliesDecodeTypedOutputs(): void
    {
        $abi = TokenAbi::trc721();
        $owner = $this->owner();
        $recipient = Address::fromBase58(self::RECIPIENT);
        $transport = new QueueTransport(
            $this->constantResponse($abi, 'balanceOf(address)', ['2']),
            $this->constantResponse($abi, 'ownerOf(uint256)', [$owner]),
            $this->constantResponse($abi, 'name()', ['Example Collection']),
            $this->constantResponse($abi, 'symbol()', ['NFT']),
            $this->constantResponse($abi, 'tokenURI(uint256)', ['ipfs://token/7']),
            $this->constantResponse($abi, 'getApproved(uint256)', [$recipient]),
            $this->constantResponse($abi, 'supportsInterface(bytes4)', [true]),
            $this->constantResponse($abi, 'isApprovedForAll(address,address)', [false]),
        );
        $token = $this->service($transport)->trc721(Address::fromBase58(self::CONTRACT), $owner);

        self::assertSame('2', $token->balanceOf($owner));
        self::assertTrue($owner->equals($token->ownerOf('7')));
        self::assertSame('Example Collection', $token->name());
        self::assertSame('NFT', $token->symbol());
        self::assertSame('ipfs://token/7', $token->tokenUri('7'));
        self::assertTrue($recipient->equals($token->approvedAddress('7')));
        self::assertTrue($token->supportsInterface(ByteString::fromHex('80ac58cd')));
        self::assertFalse($token->isApprovedForAll($owner, $recipient));
    }

    /**
     * Builds every TRC-721 ownership and operator mutation overload.
     */
    public function testTrc721MutationFamiliesSelectExactOverloads(): void
    {
        $abi = TokenAbi::trc721();
        $owner = $this->owner();
        $recipient = Address::fromBase58(self::RECIPIENT);
        $data = ByteString::fromHex('cafe');
        $feeLimit = Amount::fromDecimal('10');
        $signatures = [
            'transferFrom(address,address,uint256)',
            'safeTransferFrom(address,address,uint256)',
            'safeTransferFrom(address,address,uint256,bytes)',
            'approve(address,uint256)',
            'setApprovalForAll(address,bool)',
        ];
        $transport = new QueueTransport(
            $this->transactionResponse($abi, $owner, $signatures[0], [$owner, $recipient, '7'], $feeLimit),
            $this->transactionResponse($abi, $owner, $signatures[1], [$owner, $recipient, '7'], $feeLimit),
            $this->transactionResponse($abi, $owner, $signatures[2], [$owner, $recipient, '7', $data], $feeLimit),
            $this->transactionResponse($abi, $owner, $signatures[3], [$recipient, '7'], $feeLimit),
            $this->transactionResponse($abi, $owner, $signatures[4], [$recipient, true], $feeLimit),
        );
        $token = $this->service($transport)->trc721(Address::fromBase58(self::CONTRACT), $owner);

        $token->transferFrom($owner, $recipient, '7', $feeLimit);
        $token->safeTransferFrom($owner, $recipient, '7', $feeLimit);
        $token->safeTransferFromWithData($owner, $recipient, '7', $data, $feeLimit);
        $token->approve($recipient, '7', $feeLimit);
        $token->setApprovalForAll($recipient, true, $feeLimit);

        self::assertSame($signatures, $this->selectors($transport));
    }

    /**
     * Reads TRC-1155 single/batch balances, metadata, interface, and approvals.
     */
    public function testTrc1155ReadFamiliesDecodeListsStrictly(): void
    {
        $abi = TokenAbi::trc1155();
        $owner = $this->owner();
        $recipient = Address::fromBase58(self::RECIPIENT);
        $transport = new QueueTransport(
            $this->constantResponse($abi, 'balanceOf(address,uint256)', ['9']),
            $this->constantResponse($abi, 'balanceOfBatch(address[],uint256[])', [['1', '2']]),
            $this->constantResponse($abi, 'uri(uint256)', ['ipfs://collection/{id}.json']),
            $this->constantResponse($abi, 'supportsInterface(bytes4)', [true]),
            $this->constantResponse($abi, 'isApprovedForAll(address,address)', [false]),
        );
        $token = $this->service($transport)->trc1155(Address::fromBase58(self::CONTRACT), $owner);

        self::assertSame('9', $token->balanceOf($owner, '7'));
        self::assertSame(['1', '2'], $token->balanceOfBatch([$owner, $recipient], ['7', '8']));
        self::assertSame('ipfs://collection/{id}.json', $token->uri('7'));
        self::assertTrue($token->supportsInterface(ByteString::fromHex('d9b67a26')));
        self::assertFalse($token->isApprovedForAll($owner, $recipient));
    }

    /**
     * Builds TRC-1155 single, batch, and operator mutation transactions.
     */
    public function testTrc1155MutationFamiliesPreserveParallelLists(): void
    {
        $abi = TokenAbi::trc1155();
        $owner = $this->owner();
        $recipient = Address::fromBase58(self::RECIPIENT);
        $data = ByteString::fromHex('');
        $feeLimit = Amount::fromDecimal('10');
        $signatures = [
            'safeTransferFrom(address,address,uint256,uint256,bytes)',
            'safeBatchTransferFrom(address,address,uint256[],uint256[],bytes)',
            'setApprovalForAll(address,bool)',
        ];
        $transport = new QueueTransport(
            $this->transactionResponse($abi, $owner, $signatures[0], [$owner, $recipient, '7', '2', $data], $feeLimit),
            $this->transactionResponse($abi, $owner, $signatures[1], [$owner, $recipient, ['7', '8'], ['2', '3'], $data], $feeLimit),
            $this->transactionResponse($abi, $owner, $signatures[2], [$recipient, true], $feeLimit),
        );
        $token = $this->service($transport)->trc1155(Address::fromBase58(self::CONTRACT), $owner);

        $token->safeTransferFrom($owner, $recipient, '7', '2', $data, $feeLimit);
        $token->safeBatchTransferFrom($owner, $recipient, ['7', '8'], ['2', '3'], $data, $feeLimit);
        $token->setApprovalForAll($recipient, true, $feeLimit);

        self::assertSame($signatures, $this->selectors($transport));
    }

    /**
     * Rejects mismatched TRC-1155 batch dimensions before any node request.
     */
    public function testTrc1155RejectsMismatchedBatchLists(): void
    {
        $token = $this->service(new QueueTransport())->trc1155(
            Address::fromBase58(self::CONTRACT),
            $this->owner(),
        );
        $this->expectException(ContractException::class);

        $token->balanceOfBatch([$this->owner()], ['1', '2']);
    }

    /**
     * Applies one payable TRX/TRC-10 schema to calls and deployments.
     */
    public function testContractValueBuildsSharedValueFields(): void
    {
        $value = ContractValue::create(
            Amount::fromDecimal('2'),
            '1002000',
            Amount::fromAtomic('3'),
            true,
            'contract operation',
        );

        self::assertSame(2_000_000, $value->nativeAmount()->atomicInteger());
        self::assertSame('3', $value->tokenAmount()?->atomicValue());
        self::assertSame([
            'call_value' => 2_000_000,
            'token_id' => '1002000',
            'call_token_value' => 3,
        ], $value->requestFields());

        $this->expectException(ContractException::class);
        ContractValue::create(Amount::fromDecimal('1'), null, null, false, 'contract operation');
    }

    /**
     * Creates an encoded constant-call response for one canonical token function.
     *
     * @param list<mixed> $values Function output values.
     */
    private function constantResponse(Abi $abi, string $signature, array $values): HttpResponse
    {
        return HttpResponseFactory::json([
            'result' => ['result' => true],
            'constant_result' => [(new AbiCodec())->encodeParameters(
                $abi->function($signature)->outputs(),
                $values,
            )],
        ]);
    }

    /**
     * Creates a matching TriggerSmartContract response for one token mutation.
     *
     * @param list<mixed> $arguments Function arguments.
     */
    private function transactionResponse(
        Abi $abi,
        Address $owner,
        string $signature,
        array $arguments,
        Amount $feeLimit,
    ): HttpResponse {
        $call = new ContractCall(
            $owner,
            Address::fromBase58(self::CONTRACT),
            $abi->function($signature),
            $arguments,
            abi: $abi,
        );

        return HttpResponseFactory::json(TransactionFixture::data(new TransactionIntent(
            'TriggerSmartContract',
            $owner,
            $call->contractFields(),
            feeLimit: $feeLimit,
        )));
    }

    /**
     * Creates one deterministic contract service over a queued native transport.
     */
    private function service(QueueTransport $transport): ContractService
    {
        $api = new ApiClient(NodeConfiguration::custom('https://node.example'), $transport);

        return new ContractService($api, new TransactionFactory($api));
    }

    /**
     * Returns recorded ABI selectors after an optional leading read request count.
     *
     * @return list<string>
     */
    private function selectors(QueueTransport $transport, int $offset = 0): array
    {
        return array_map(static function (HttpRequest $request): string {
            $selector = $request->parameters['function_selector'] ?? null;
            if (!is_string($selector)) {
                throw new RuntimeException('A token transaction request is missing its ABI selector.');
            }

            return $selector;
        }, array_slice($transport->requests(), $offset));
    }

    /**
     * Returns the deterministic caller used throughout token standard tests.
     */
    private function owner(): Address
    {
        return (new LocalPrivateKeySigner(self::OWNER_KEY))->address();
    }
}
