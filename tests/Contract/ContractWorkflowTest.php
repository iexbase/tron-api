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
use IEXBase\TronAPI\Contract\AbiEntry;
use IEXBase\TronAPI\Contract\AbiParameter;
use IEXBase\TronAPI\Contract\ContractCall;
use IEXBase\TronAPI\Contract\ContractCallResult;
use IEXBase\TronAPI\Contract\ContractDefinition;
use IEXBase\TronAPI\Contract\DeploymentRequest;
use IEXBase\TronAPI\Contract\DeploymentTransaction;
use IEXBase\TronAPI\Contract\Token\TokenAbi;
use IEXBase\TronAPI\Contract\Token\Trc20Contract;
use IEXBase\TronAPI\Crypto\ContractAddress;
use IEXBase\TronAPI\Crypto\LocalPrivateKeySigner;
use IEXBase\TronAPI\Exception\ContractExecutionException;
use IEXBase\TronAPI\Service\ContractService;
use IEXBase\TronAPI\Tests\Support\HttpResponseFactory;
use IEXBase\TronAPI\Tests\Support\QueueTransport;
use IEXBase\TronAPI\Tests\Support\TransactionFixture;
use IEXBase\TronAPI\Transaction\Transaction;
use IEXBase\TronAPI\Transaction\TransactionFactory;
use IEXBase\TronAPI\Transaction\TransactionIntent;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\Amount;
use IEXBase\TronAPI\Value\ByteString;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies ABI-aware reads, token values, reverts, transactions, and deployment.
 */
#[CoversClass(ContractService::class)]
#[CoversClass(ContractCall::class)]
#[CoversClass(ContractCallResult::class)]
#[CoversClass(ContractDefinition::class)]
#[CoversClass(DeploymentRequest::class)]
#[CoversClass(DeploymentTransaction::class)]
#[CoversClass(ContractAddress::class)]
#[CoversClass(TokenAbi::class)]
#[CoversClass(Trc20Contract::class)]
final class ContractWorkflowTest extends TestCase
{
    private const OWNER_KEY = '0000000000000000000000000000000000000000000000000000000000000001';
    private const CONTRACT = 'TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY';

    /**
     * Sends a confirmed constant call and decodes its arbitrary-precision result.
     */
    public function testConfirmedContractReadDecodesOutput(): void
    {
        $codec = new AbiCodec();
        $function = new AbiEntry('function', 'balanceOf', [
            new AbiParameter('owner', 'address'),
        ], [new AbiParameter('balance', 'uint256')], 'view');
        $output = $codec->encodeParameters($function->outputs(), ['9007199254740993']);
        $transport = new QueueTransport(HttpResponseFactory::json([
            'result' => ['result' => true],
            'constant_result' => [$output],
            'energy_used' => '1234',
            'energy_penalty' => 5,
        ]));
        $service = $this->service($transport);
        $owner = (new LocalPrivateKeySigner(self::OWNER_KEY))->address();
        $call = new ContractCall(
            $owner,
            Address::fromBase58(self::CONTRACT),
            $function,
            [$owner],
        );
        $result = $service->read($call);

        self::assertSame('9007199254740993', $result->outputs->value('balance'));
        self::assertSame('1234', $result->energyUsed);
        self::assertSame('5', $result->energyPenalty);
        self::assertSame('/walletsolidity/triggerconstantcontract', parse_url($transport->request()->uri, PHP_URL_PATH));
        self::assertSame('balanceOf(address)', $transport->request()->parameters['function_selector']);
    }

    /**
     * Converts a node-rejected simulation into a decoded Solidity failure exception.
     */
    public function testRevertedSimulationExposesDecodedFailure(): void
    {
        $codec = new AbiCodec();
        $function = new AbiEntry('function', 'willFail', [], [], 'view');
        $failure = '08c379a0' . $codec->encodeParameters(
            [new AbiParameter('reason', 'string')],
            ['permission denied'],
        );
        $service = $this->service(new QueueTransport(HttpResponseFactory::json([
            'result' => ['result' => false, 'message' => bin2hex('REVERT')],
            'constant_result' => [$failure],
        ])));
        $this->expectException(ContractExecutionException::class);
        $this->expectExceptionMessage('permission denied');

        $service->read(new ContractCall(
            (new LocalPrivateKeySigner(self::OWNER_KEY))->address(),
            Address::fromBase58(self::CONTRACT),
            $function,
        ));
    }

    /**
     * Loads and validates a deployed contract definition including java-tron ABI shape.
     */
    public function testContractDefinitionRetainsTypedMetadata(): void
    {
        $contractAddress = Address::fromBase58(self::CONTRACT);
        $owner = (new LocalPrivateKeySigner(self::OWNER_KEY))->address();
        $transport = new QueueTransport(HttpResponseFactory::json([
            'contract_address' => $contractAddress->toBase58(),
            'origin_address' => $owner->toBase58(),
            'name' => 'Treasury',
            'abi' => ['entrys' => [[
                'type' => 'function',
                'name' => 'version',
                'inputs' => [],
                'outputs' => [['name' => '', 'type' => 'uint256']],
                'stateMutability' => 'view',
            ]]],
            'bytecode' => '60006000',
            'consume_user_resource_percent' => 40,
            'origin_energy_limit' => '1000000',
        ]));
        $definition = $this->service($transport)->get($contractAddress);

        self::assertInstanceOf(ContractDefinition::class, $definition);
        self::assertSame('Treasury', $definition->name);
        self::assertSame('version()', $definition->function('version')->signature());
        self::assertSame(40, $definition->callerEnergyPercent);
        self::assertSame('1000000', $definition->originEnergyLimit);
    }

    /**
     * Caches TRC-20 decimals and applies them to exact token supply values.
     */
    public function testTrc20MetadataUsesTokenSpecificDecimals(): void
    {
        $codec = new AbiCodec();
        $abi = TokenAbi::trc20();
        $transport = new QueueTransport(
            HttpResponseFactory::json([
                'result' => ['result' => true],
                'constant_result' => [$codec->encodeParameters(
                    $abi->function('decimals()')->outputs(),
                    ['6'],
                )],
            ]),
            HttpResponseFactory::json([
                'result' => ['result' => true],
                'constant_result' => [$codec->encodeParameters(
                    $abi->function('totalSupply()')->outputs(),
                    ['1000000000000000'],
                )],
            ]),
        );
        $owner = (new LocalPrivateKeySigner(self::OWNER_KEY))->address();
        $token = $this->service($transport)->trc20(Address::fromBase58(self::CONTRACT), $owner);

        self::assertSame(6, $token->decimals());
        self::assertSame(6, $token->decimals());
        self::assertSame('1000000000', $token->totalSupply()->decimalValue());
        self::assertCount(2, $transport->requests());
    }

    /**
     * Builds and verifies a state-changing ABI call with a strict fee ceiling.
     */
    public function testContractTransactionUsesEncodedIntent(): void
    {
        $owner = (new LocalPrivateKeySigner(self::OWNER_KEY))->address();
        $contract = Address::fromBase58(self::CONTRACT);
        $abi = TokenAbi::trc20();
        $call = new ContractCall(
            $owner,
            $contract,
            $abi->function('transfer(address,uint256)'),
            [$owner, '123456'],
            abi: $abi,
        );
        $feeLimit = Amount::fromDecimal('20');
        $intent = new TransactionIntent(
            'TriggerSmartContract',
            $owner,
            $call->contractFields(),
            feeLimit: $feeLimit,
        );
        $transport = new QueueTransport(HttpResponseFactory::json(TransactionFixture::data($intent)));
        $transaction = $this->service($transport)->createTransaction($call, $feeLimit);

        self::assertSame($intent->contractType, $transaction->singleContract()['type']);
        self::assertSame(20_000_000, $transport->request()->parameters['fee_limit']);
        self::assertSame('transfer(address,uint256)', $transport->request()->parameters['function_selector']);
        self::assertSame($call->parameterHex(), $transport->request()->parameters['parameter']);
    }

    /**
     * Verifies a node-predicted deployment address against local TRON derivation.
     */
    public function testDeploymentAddressIsVerifiedLocally(): void
    {
        $owner = (new LocalPrivateKeySigner(self::OWNER_KEY))->address();
        $abi = new Abi([new AbiEntry('constructor', '', [
            new AbiParameter('initialValue', 'uint256'),
        ], [], 'nonpayable')]);
        $request = new DeploymentRequest(
            $owner,
            'Counter',
            $abi,
            ByteString::fromHex('60006000'),
            ['7'],
            Amount::fromDecimal('100'),
            originEnergyLimit: 10_000,
        );
        $intent = new TransactionIntent(
            'CreateSmartContract',
            $owner,
            $request->contractFields(),
            feeLimit: $request->feeLimit,
        );
        $data = TransactionFixture::data($intent);
        $fixtureTransaction = Transaction::fromNodeData($data);
        $predicted = ContractAddress::fromTransaction($owner, $fixtureTransaction->id());
        $data['contract_address'] = $predicted->toBase58();
        $transport = new QueueTransport(HttpResponseFactory::json($data));
        $deployment = $this->service($transport)->deploy($request);

        self::assertTrue($predicted->equals($deployment->contractAddress));
        self::assertSame(
            $request->deploymentBytecode()->toHex(false),
            $transport->request()->parameters['bytecode'],
        );
        self::assertSame(100_000_000, $transport->request()->parameters['fee_limit']);
    }

    /**
     * Creates a contract service over an isolated custom FullNode topology.
     */
    private function service(QueueTransport $transport): ContractService
    {
        $api = new ApiClient(NodeConfiguration::custom('https://node.example'), $transport);

        return new ContractService($api, new TransactionFactory($api));
    }
}
