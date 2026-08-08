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
use IEXBase\TronAPI\Contract\AbiTraversalBudget;
use IEXBase\TronAPI\Contract\AbiType;
use IEXBase\TronAPI\Contract\ContractFailure;
use IEXBase\TronAPI\Contract\DecodedFunctionCall;
use IEXBase\TronAPI\Contract\DecodedValues;
use IEXBase\TronAPI\Contract\EventLog;
use IEXBase\TronAPI\Exception\ContractException;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\ByteString;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies canonical ABI signatures, recursive values, events, and failures.
 */
#[CoversClass(Abi::class)]
#[CoversClass(AbiCodec::class)]
#[CoversClass(AbiEntry::class)]
#[CoversClass(AbiParameter::class)]
#[CoversClass(AbiTraversalBudget::class)]
#[CoversClass(AbiType::class)]
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
     * Expands every Solidity type alias before hashing a function signature.
     */
    public function testCanonicalAliasesProduceOfficialSelector(): void
    {
        $function = $this->function('sam', [
            new AbiParameter('data', 'bytes'),
            new AbiParameter('enabled', 'bool'),
            new AbiParameter('values', 'uint[]'),
        ]);
        $fixedPoint = $this->function('rates', [
            new AbiParameter('signed', 'fixed'),
            new AbiParameter('unsigned', 'ufixed[]'),
            new AbiParameter('integer', 'int'),
        ]);

        self::assertSame('sam(bytes,bool,uint256[])', $function->signature());
        self::assertSame('a5643bf2', $function->selector());
        self::assertSame('rates(fixed128x18,ufixed128x18[],int256)', $fixedPoint->signature());
    }

    /**
     * Matches the Solidity specification's complete sam(bytes,bool,uint[]) vector.
     */
    public function testOfficialDynamicEncodingVectorMatchesExactly(): void
    {
        $function = $this->function('sam', [
            new AbiParameter('data', 'bytes'),
            new AbiParameter('enabled', 'bool'),
            new AbiParameter('values', 'uint[]'),
        ]);
        $word = static fn (int $value): string => str_pad(dechex($value), 64, '0', STR_PAD_LEFT);
        $expected = 'a5643bf2'
            . $word(96)
            . $word(1)
            . $word(160)
            . $word(4)
            . str_pad(bin2hex('dave'), 64, '0')
            . $word(3)
            . $word(1)
            . $word(2)
            . $word(3);

        self::assertSame(
            $expected,
            (new AbiCodec())->encodeFunctionCall($function, ['dave', true, [1, 2, 3]]),
        );
    }

    /**
     * Supports the zero-length fixed array expressible by the ABI specification.
     */
    public function testZeroLengthFixedArrayRoundTrips(): void
    {
        $parameter = new AbiParameter('values', 'uint[0]');
        $codec = new AbiCodec();

        self::assertSame('uint256[0]', $parameter->canonicalType());
        self::assertSame('', $codec->encodeParameters([$parameter], [[]]));
        self::assertSame([[]], $codec->decodeParameters([$parameter], '')->all());
    }

    /**
     * Rejects fixed-point spellings that are outside the Solidity ABI grammar.
     */
    public function testIncompleteFixedPointTypesAreRejected(): void
    {
        foreach (['fixed128', 'fixedx18', 'fixed128x', 'ufixed256'] as $type) {
            try {
                new AbiParameter('value', $type);
                self::fail(sprintf('Invalid ABI type `%s` was accepted.', $type));
            } catch (ContractException) {
                self::addToAssertionCount(1);
            }
        }
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
     * Keeps every indexed array or tuple as its irreversible event-topic hash.
     */
    public function testIndexedComplexStaticValueRemainsHashed(): void
    {
        $event = new AbiEntry('event', 'Snapshot', [
            new AbiParameter('values', 'uint256[1]', indexed: true),
        ], [], 'nonpayable');
        $hash = str_repeat('ab', 32);

        $decoded = (new AbiCodec())->decodeEvent($event, [$event->eventTopic(), $hash], '');

        $value = $decoded->value('values');
        self::assertInstanceOf(ByteString::class, $value);
        self::assertSame($hash, $value->toHex(false));
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
     * Rejects a dynamic offset that aliases the sequence head.
     */
    public function testDynamicOffsetCannotPointIntoHead(): void
    {
        $this->expectException(ContractException::class);

        (new AbiCodec())->decodeParameters(
            [new AbiParameter('text', 'string')],
            str_repeat('0', 64),
        );
    }

    /**
     * Requires dynamic byte payloads to include canonical zero padding.
     */
    public function testTruncatedDynamicPaddingIsRejected(): void
    {
        $offset = str_pad(dechex(32), 64, '0', STR_PAD_LEFT);
        $length = str_pad(dechex(1), 64, '0', STR_PAD_LEFT);
        $this->expectException(ContractException::class);

        (new AbiCodec())->decodeParameters(
            [new AbiParameter('data', 'bytes')],
            $offset . $length . 'ff',
        );
    }

    /**
     * Rejects a fixed type whose encoded width exceeds the allocation ceiling.
     */
    public function testOversizedStaticTypeIsRejectedBeforeEncoding(): void
    {
        $this->expectException(ContractException::class);

        new AbiParameter('values', 'uint256[100000][6]');
    }

    /**
     * Bounds aggregate work when many dynamic offsets reuse one nested payload.
     */
    public function testRepeatedNestedOffsetsCannotExpandDecoderWorkWithoutBound(): void
    {
        $count = 1_000;
        $word = static fn (int $value): string => str_pad(dechex($value), 64, '0', STR_PAD_LEFT);
        $encoded = $word(32)
            . $word($count)
            . str_repeat($word($count * 32), $count)
            . $word($count)
            . str_repeat($word(0), $count);

        $this->expectException(ContractException::class);
        $this->expectExceptionMessage('aggregate value limit');

        (new AbiCodec())->decodeParameters(
            [new AbiParameter('values', 'uint256[][]')],
            $encoded,
        );
    }

    /**
     * Deduplicates custom errors that Solidity permits multiple sources to declare.
     */
    public function testRepeatedCustomErrorDeclarationHasOneLogicalEntry(): void
    {
        $first = new AbiEntry('error', 'Failure', [new AbiParameter('code', 'uint')], [], 'nonpayable');
        $second = new AbiEntry('error', 'Failure', [new AbiParameter('reason', 'uint256')], [], 'nonpayable');
        $abi = new Abi([$first, $second]);

        self::assertCount(1, $abi->entries());
        self::assertSame('Failure(uint256)', $abi->error('Failure(uint256)')->signature());
    }

    /**
     * Reads a compiler artifact without requiring callers to extract its ABI list.
     */
    public function testCompilerArtifactAbiContainerIsAccepted(): void
    {
        $abi = Abi::fromArray(['abi' => [[
            'type' => 'function',
            'name' => 'value',
            'inputs' => [],
            'outputs' => [['name' => '', 'type' => 'uint']],
            'stateMutability' => 'view',
        ]]]);

        self::assertSame('value()', $abi->function('value')->signature());
        self::assertSame('uint256', $abi->function('value')->outputs()[0]->canonicalType());
    }

    /**
     * Serializes events and functions using their standard ABI JSON fields.
     */
    public function testAbiJsonUsesCanonicalStandardShape(): void
    {
        $abi = new Abi([
            new AbiEntry('event', 'Changed', [
                new AbiParameter('value', 'uint', indexed: false),
            ], [], 'nonpayable'),
            $this->function('read', [], [new AbiParameter('', 'uint')]),
        ]);
        $json = json_decode((string) json_encode($abi, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($json);
        $event = $json[0] ?? null;
        $function = $json[1] ?? null;
        self::assertIsArray($event);
        self::assertIsArray($function);
        $eventInputs = $event['inputs'] ?? null;
        $functionOutputs = $function['outputs'] ?? null;
        self::assertIsArray($eventInputs);
        self::assertIsArray($functionOutputs);
        $eventValue = $eventInputs[0] ?? null;
        $functionValue = $functionOutputs[0] ?? null;
        self::assertIsArray($eventValue);
        self::assertIsArray($functionValue);

        self::assertSame('uint256', $eventValue['type'] ?? null);
        self::assertFalse($eventValue['indexed'] ?? null);
        self::assertArrayNotHasKey('stateMutability', $event);
        self::assertSame([], $function['inputs'] ?? null);
        self::assertSame('uint256', $functionValue['type'] ?? null);
        self::assertSame('view', $function['stateMutability'] ?? null);
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
