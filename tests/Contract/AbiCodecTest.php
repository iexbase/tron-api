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

use IEXBase\TronAPI\Contract\Abi;
use IEXBase\TronAPI\Contract\AbiCodec;
use IEXBase\TronAPI\Contract\AbiEntry;
use IEXBase\TronAPI\Contract\AbiParameter;
use IEXBase\TronAPI\Contract\ContractFailure;
use IEXBase\TronAPI\Contract\DecodedFunctionCall;
use IEXBase\TronAPI\Contract\DecodedValues;
use IEXBase\TronAPI\Contract\EventLog;
use IEXBase\TronAPI\Exception\ContractException;
use IEXBase\TronAPI\Value\Address;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies canonical ABI signatures, recursive values, events, and failures.
 */
#[CoversClass(Abi::class)]
#[CoversClass(AbiCodec::class)]
#[CoversClass(AbiEntry::class)]
#[CoversClass(AbiParameter::class)]
#[CoversClass(ContractFailure::class)]
#[CoversClass(DecodedFunctionCall::class)]
#[CoversClass(DecodedValues::class)]
#[CoversClass(EventLog::class)]
final class AbiCodecTest extends TestCase
{
    private const OWNER = 'TMVQGm1qAQYVdetCeGRRkTWYYrLXuHK2HC';
    private const RECIPIENT = 'TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY';

    /**
     * Produces the established ERC/TRC balanceOf selector.
     */
    public function testKnownFunctionSelectorAndCallData(): void
    {
        $function = $this->function('balanceOf', [new AbiParameter('owner', 'address')], [
            new AbiParameter('balance', 'uint256'),
        ]);
        $encoded = (new AbiCodec())->encodeFunctionCall($function, [Address::fromBase58(self::OWNER)]);

        self::assertSame('balanceOf(address)', $function->signature());
        self::assertSame('70a08231', $function->selector());
        self::assertStringStartsWith('70a08231', $encoded);
        self::assertSame(8 + 64, strlen($encoded));
    }

    /**
     * Resolves TRC-20 calldata by selector and decodes its exact transfer arguments.
     */
    public function testCompleteFunctionCallDataIsDecoded(): void
    {
        $function = $this->function('transfer', [
            new AbiParameter('recipient', 'address'),
            new AbiParameter('amount', 'uint256'),
        ]);
        $abi = new Abi([$function]);
        $codec = new AbiCodec();
        $recipient = Address::fromBase58(self::RECIPIENT);
        $encoded = $codec->encodeFunctionCall($function, [$recipient, '9007199254740993']);

        $call = $codec->decodeFunctionCall($abi, $encoded);

        self::assertSame('transfer(address,uint256)', $call->signature());
        self::assertInstanceOf(Address::class, $call->argument('recipient'));
        self::assertTrue($recipient->equals($call->argument('recipient')));
        self::assertSame('9007199254740993', $call->argument('amount'));
        self::assertSame($encoded, $call->data->toHex(false));
    }

    /**
     * Round-trips nested dynamic arrays and tuples without losing integer precision.
     */
    public function testRecursiveValuesRoundTripExactly(): void
    {
        $parameters = [
            new AbiParameter('label', 'string'),
            new AbiParameter('values', 'uint256[]'),
            new AbiParameter('record', 'tuple', [
                new AbiParameter('identifier', 'uint256'),
                new AbiParameter('owner', 'address'),
            ]),
        ];
        $owner = Address::fromBase58(self::OWNER);
        $codec = new AbiCodec();
        $encoded = $codec->encodeParameters($parameters, [
            'label' => 'TRON API',
            'values' => ['1', '340282366920938463463374607431768211455'],
            'record' => ['identifier' => '42', 'owner' => $owner],
        ]);
        $decoded = $codec->decodeParameters($parameters, $encoded);

        self::assertSame('TRON API', $decoded->value('label'));
        self::assertSame(['1', '340282366920938463463374607431768211455'], $decoded->value('values'));
        $record = $decoded->value('record');
        self::assertIsArray($record);
        self::assertSame('42', $record[0]);
        self::assertInstanceOf(Address::class, $record[1]);
        self::assertTrue($owner->equals($record[1]));
    }

    /**
     * Requires a full signature when a function name is overloaded.
     */
    public function testOverloadedFunctionRequiresCanonicalSignature(): void
    {
        $abi = new Abi([
            $this->function('safeTransferFrom', [new AbiParameter('to', 'address')]),
            $this->function('safeTransferFrom', [
                new AbiParameter('to', 'address'),
                new AbiParameter('data', 'bytes'),
            ]),
        ]);

        self::assertSame(
            'safeTransferFrom(address,bytes)',
            $abi->function('safeTransferFrom(address,bytes)')->signature(),
        );
        $this->expectException(ContractException::class);

        $abi->function('safeTransferFrom');
    }

    /**
     * Decodes indexed TRON addresses and a non-indexed integer from a receipt log.
     */
    public function testTransferEventLogIsDecoded(): void
    {
        $event = new AbiEntry('event', 'Transfer', [
            new AbiParameter('from', 'address', indexed: true),
            new AbiParameter('to', 'address', indexed: true),
            new AbiParameter('value', 'uint256'),
        ], [], 'nonpayable');
        $abi = new Abi([$event]);
        $codec = new AbiCodec();
        $from = Address::fromBase58(self::OWNER);
        $to = Address::fromBase58(self::RECIPIENT);
        $log = EventLog::fromNodeData([
            'address' => $to->toEvmHex(false),
            'topics' => [
                $event->eventTopic(),
                str_pad($from->toEvmHex(false), 64, '0', STR_PAD_LEFT),
                str_pad($to->toEvmHex(false), 64, '0', STR_PAD_LEFT),
            ],
            'data' => $codec->encodeParameters([new AbiParameter('value', 'uint256')], ['9007199254740993']),
        ], $abi, $codec);
        $decodedFrom = $log->values?->value('from');
        $decodedTo = $log->values?->value('to');

        self::assertTrue($log->isDecoded());
        self::assertSame('Transfer(address,address,uint256)', $log->event?->signature());
        self::assertInstanceOf(Address::class, $decodedFrom);
        self::assertInstanceOf(Address::class, $decodedTo);
        self::assertTrue($from->equals($decodedFrom));
        self::assertTrue($to->equals($decodedTo));
        self::assertSame('9007199254740993', $log->values?->value('value'));
    }

    /**
     * Decodes the standard Solidity Error(string) envelope.
     */
    public function testStandardErrorPayloadIsDecoded(): void
    {
        $codec = new AbiCodec();
        $payload = '08c379a0' . $codec->encodeParameters(
            [new AbiParameter('reason', 'string')],
            ['insufficient balance'],
        );
        $failure = $codec->decodeFailure($payload);

        self::assertSame('error', $failure->kind);
        self::assertSame('Error(string)', $failure->signature);
        self::assertSame('insufficient balance', $failure->message);
    }

    /**
     * Maps a standard Solidity Panic(uint256) code to a useful diagnostic.
     */
    public function testPanicPayloadIsDecoded(): void
    {
        $codec = new AbiCodec();
        $payload = '4e487b71' . $codec->encodeParameters([new AbiParameter('code', 'uint256')], ['17']);
        $failure = $codec->decodeFailure($payload);

        self::assertSame('panic', $failure->kind);
        self::assertSame('17', $failure->arguments->value('code'));
        self::assertStringContainsString('overflow or underflow', $failure->message);
    }

    /**
     * Resolves and decodes a custom Solidity error declared by the contract ABI.
     */
    public function testCustomErrorPayloadIsDecoded(): void
    {
        $error = new AbiEntry('error', 'InsufficientBalance', [
            new AbiParameter('available', 'uint256'),
            new AbiParameter('required', 'uint256'),
        ], [], 'nonpayable');
        $abi = new Abi([$error]);
        $codec = new AbiCodec();
        $failure = $codec->decodeFailure(
            $error->selector() . $codec->encodeParameters($error->inputs(), ['3', '10']),
            $abi,
        );

        self::assertSame('custom', $failure->kind);
        self::assertSame('InsufficientBalance(uint256,uint256)', $failure->signature);
        self::assertSame(['3', '10'], $failure->arguments->all());
    }

    /**
     * Rejects an untrusted dynamic offset that points outside returned data.
     */
    public function testOutOfBoundsDynamicOffsetIsRejected(): void
    {
        $this->expectException(ContractException::class);

        (new AbiCodec())->decodeParameters(
            [new AbiParameter('text', 'string')],
            str_repeat('f', 64),
        );
    }

    /**
     * Creates a function entry with consistent defaults for focused tests.
     *
     * @param list<AbiParameter> $inputs Function inputs.
     * @param list<AbiParameter> $outputs Function outputs.
     */
    private function function(string $name, array $inputs, array $outputs = []): AbiEntry
    {
        return new AbiEntry('function', $name, $inputs, $outputs, 'view');
    }
}
