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

use GMP;
use IEXBase\TronAPI\Encoding\Hex;
use IEXBase\TronAPI\Exception\ContractException;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\ByteString;
use Throwable;

/**
 * Encodes and decodes Solidity ABI values according to the official head/tail model.
 *
 * Supported values include signed and unsigned integers, fixed-point numbers,
 * addresses, booleans, strings, dynamic and fixed bytes, function pointers,
 * nested fixed/dynamic arrays, and nested tuples. All integer work uses GMP and
 * every decoder offset is bounds checked before reading untrusted node data.
 */
final class AbiCodec
{
    private const WORD_BYTES = 32;
    private const WORD_HEX_LENGTH = 64;
    private const MAX_ARRAY_ELEMENTS = 100_000;
    private const MAX_DATA_BYTES = 16_777_216;

    /**
     * Encodes ordered or parameter-name-indexed arguments without a selector.
     *
     * @param list<AbiParameter> $parameters Function or constructor parameters.
     * @param array<mixed>       $arguments Ordered values or values keyed by parameter name.
     */
    public function encodeParameters(array $parameters, array $arguments): string
    {
        $types = $this->types($parameters);
        $values = $this->orderedValues($arguments, $this->names($parameters));

        return $this->encodeSequence($types, $values);
    }

    /**
     * Encodes a complete function selector followed by its ABI arguments.
     *
     * @param AbiEntry    $function Function ABI entry.
     * @param array<mixed> $arguments Ordered or named input values.
     */
    public function encodeFunctionCall(AbiEntry $function, array $arguments): string
    {
        if ($function->type !== 'function') {
            throw new ContractException('Only a function ABI entry can encode function call data.');
        }

        return $function->selector() . $this->encodeParameters($function->inputs(), $arguments);
    }

    /**
     * Resolves a complete calldata selector and decodes its arguments against an ABI.
     */
    public function decodeFunctionCall(Abi $abi, string $encoded): DecodedFunctionCall
    {
        $data = $this->dataHex($encoded);
        if (strlen($data) < 8) {
            throw new ContractException('Function call data must contain a four-byte selector.');
        }

        $function = $abi->functionBySelector(substr($data, 0, 8));
        if ($function === null) {
            throw new ContractException('Function call data contains a selector not declared by the supplied ABI.');
        }

        return new DecodedFunctionCall(
            $function,
            $this->decodeParameters($function->inputs(), substr($data, 8)),
            ByteString::fromHex($data),
        );
    }

    /**
     * Decodes ABI parameters into a single ordered value collection.
     *
     * @param list<AbiParameter> $parameters Expected value definitions.
     */
    public function decodeParameters(array $parameters, string $encoded): DecodedValues
    {
        $data = $this->dataHex($encoded);
        $values = $this->decodeSequence($this->types($parameters), $data, 0);

        return new DecodedValues($values, $this->names($parameters));
    }

    /**
     * Decodes the output payload of one ABI function.
     */
    public function decodeFunctionResult(AbiEntry $function, string $encoded): DecodedValues
    {
        if ($function->type !== 'function') {
            throw new ContractException('Only a function ABI entry can decode function output.');
        }

        return $this->decodeParameters($function->outputs(), $encoded);
    }

    /**
     * Decodes indexed topics and non-indexed data for one contract event.
     *
     * Dynamic indexed values remain their Keccak-256 ByteString because the
     * original value cannot be reconstructed from an event topic.
     *
     * @param list<string> $topics Event log topics, including signature unless anonymous.
     */
    public function decodeEvent(AbiEntry $event, array $topics, string $encodedData): DecodedValues
    {
        if ($event->type !== 'event') {
            throw new ContractException('Only an event ABI entry can decode event log data.');
        }

        $topicOffset = 0;
        if (!$event->anonymous) {
            $signatureTopic = $topics[0] ?? null;
            if (!is_string($signatureTopic)
                || !hash_equals($event->eventTopic(), Hex::canonicalize($signatureTopic, 32))
            ) {
                throw new ContractException('The event signature topic does not match the selected ABI event.');
            }
            $topicOffset = 1;
        }

        $nonIndexedParameters = array_values(array_filter(
            $event->inputs(),
            static fn (AbiParameter $parameter): bool => !$parameter->indexed,
        ));
        $nonIndexed = $this->decodeParameters($nonIndexedParameters, $encodedData)->all();
        $nonIndexedPosition = 0;
        $indexedPosition = $topicOffset;
        $values = [];

        foreach ($event->inputs() as $parameter) {
            if (!$parameter->indexed) {
                $values[] = $nonIndexed[$nonIndexedPosition++];
                continue;
            }

            $topic = $topics[$indexedPosition++] ?? null;
            if (!is_string($topic)) {
                throw new ContractException('The event log does not contain every indexed topic.');
            }

            $topicHex = Hex::canonicalize($topic, 32);
            $type = AbiType::fromParameter($parameter);
            $values[] = $type->isHashedInEventTopic()
                ? ByteString::fromHex($topicHex)
                : $this->decodeStaticValue($type, $topicHex, 0);
        }

        if ($indexedPosition !== count($topics)) {
            throw new ContractException('The event log contains unexpected extra topics.');
        }

        return new DecodedValues($values, $this->names($event->inputs()));
    }

    /**
     * Decodes standard Error/Panic and ABI-declared custom Solidity reverts.
     */
    public function decodeFailure(string $encoded, ?Abi $abi = null): ContractFailure
    {
        $data = $this->dataHex($encoded);
        if (strlen($data) < 8) {
            throw new ContractException('A Solidity failure payload must contain a four-byte selector.');
        }

        $selector = substr($data, 0, 8);
        $payload = substr($data, 8);
        $rawData = ByteString::fromHex($data);

        if ($selector === '08c379a0') {
            $parameter = new AbiParameter('reason', 'string');
            $arguments = $this->decodeParameters([$parameter], $payload);
            $reason = $arguments->value('reason');
            if (!is_string($reason)) {
                throw new ContractException('The standard Solidity Error reason did not decode to text.');
            }

            return new ContractFailure('error', $selector, 'Error(string)', $reason, $arguments, $rawData);
        }

        if ($selector === '4e487b71') {
            $parameter = new AbiParameter('code', 'uint256');
            $arguments = $this->decodeParameters([$parameter], $payload);
            $code = $arguments->value('code');
            if (!is_string($code)) {
                throw new ContractException('The standard Solidity Panic code did not decode to an integer.');
            }

            return new ContractFailure(
                'panic',
                $selector,
                'Panic(uint256)',
                $this->panicMessage($code),
                $arguments,
                $rawData,
            );
        }

        $customError = $abi?->errorBySelector($selector);
        if ($customError !== null) {
            $arguments = $this->decodeParameters($customError->inputs(), $payload);

            return new ContractFailure(
                'custom',
                $selector,
                $customError->signature(),
                sprintf('Contract reverted with custom error `%s`.', $customError->signature()),
                $arguments,
                $rawData,
            );
        }

        return new ContractFailure(
            'unknown',
            $selector,
            null,
            sprintf('Contract reverted with unknown selector `%s`.', $selector),
            new DecodedValues([], []),
            $rawData,
        );
    }

    /**
     * Converts ABI parameter declarations into recursive type descriptors.
     *
     * @param list<AbiParameter> $parameters ABI parameters.
     * @return list<AbiType>
     */
    private function types(array $parameters): array
    {
        return array_map(
            static fn (AbiParameter $parameter): AbiType => AbiType::fromParameter($parameter),
            $parameters,
        );
    }

    /**
     * Extracts ABI parameter names in source order.
     *
     * @param list<AbiParameter> $parameters ABI parameters.
     * @return list<string>
     */
    private function names(array $parameters): array
    {
        return array_map(
            static fn (AbiParameter $parameter): string => $parameter->name,
            $parameters,
        );
    }

    /**
     * Converts an ordered list or a complete named map into ordered ABI values.
     *
     * @param array<mixed> $values Supplied values.
     * @param list<string> $names Expected names in source order.
     * @return list<mixed>
     */
    private function orderedValues(array $values, array $names): array
    {
        if (array_is_list($values)) {
            if (count($values) !== count($names)) {
                throw new ContractException('The ABI argument count does not match the parameter count.');
            }

            return $values;
        }

        $ordered = [];
        $seenNames = [];
        foreach ($names as $name) {
            if ($name === '' || isset($seenNames[$name]) || !array_key_exists($name, $values)) {
                throw new ContractException('Named ABI arguments require unique non-empty parameter names and every value.');
            }
            $seenNames[$name] = true;
            $ordered[] = $values[$name];
        }

        if (count($values) !== count($ordered)) {
            throw new ContractException('Named ABI arguments contain unexpected keys.');
        }

        return $ordered;
    }

    /**
     * Applies Solidity head/tail encoding to one tuple-like sequence.
     *
     * @param list<AbiType> $types Ordered ABI types.
     * @param list<mixed>   $values Ordered values.
     */
    private function encodeSequence(array $types, array $values): string
    {
        if (count($types) !== count($values)) {
            throw new ContractException('ABI types and values must have equal lengths.');
        }

        $headBytes = array_sum(array_map(
            static fn (AbiType $type): int => $type->isDynamic() ? self::WORD_BYTES : $type->staticByteLength(),
            $types,
        ));
        $head = '';
        $tail = '';

        foreach ($types as $position => $type) {
            if ($type->isDynamic()) {
                $head .= $this->encodeUnsignedInteger($headBytes + intdiv(strlen($tail), 2), 256);
                $tail .= $this->encodeValue($type, $values[$position]);
            } else {
                $head .= $this->encodeValue($type, $values[$position]);
            }
        }

        return $head . $tail;
    }

    /**
     * Encodes one elementary or recursive ABI value.
     */
    private function encodeValue(AbiType $type, mixed $value): string
    {
        return match ($type->kind) {
            'address' => $this->encodeAddress($value),
            'bool' => $this->encodeBoolean($value),
            'bytes' => $this->encodeDynamicBytes($this->binaryValue($value)),
            'fixed_bytes' => $this->encodeFixedBytes($this->binaryValue($value), $type->size),
            'function' => $this->encodeFixedBytes($this->binaryValue($value), 24),
            'int' => $this->encodeSignedInteger($value, $this->requiredSize($type)),
            'uint' => $this->encodeUnsignedInteger($value, $this->requiredSize($type)),
            'fixed' => $this->encodeSignedInteger(
                $this->scaledInteger($value, $this->requiredPrecision($type), true),
                $this->requiredSize($type),
            ),
            'ufixed' => $this->encodeUnsignedInteger(
                $this->scaledInteger($value, $this->requiredPrecision($type), false),
                $this->requiredSize($type),
            ),
            'string' => $this->encodeString($value),
            'array' => $this->encodeArray($type, $value),
            'tuple' => $this->encodeTuple($type, $value),
            default => throw new ContractException(sprintf('ABI encoder has no implementation for `%s`.', $type->kind)),
        };
    }

    /**
     * Encodes a TRON or EVM address into a left-padded 20-byte ABI word.
     */
    private function encodeAddress(mixed $value): string
    {
        if (is_string($value)) {
            try {
                $value = Address::fromString($value);
            } catch (Throwable $exception) {
                throw new ContractException('The ABI address argument is invalid.', 0, $exception);
            }
        }

        if (!$value instanceof Address) {
            throw new ContractException('An ABI address argument must be an Address or valid TRON address string.');
        }

        return str_pad($value->toEvmHex(false), self::WORD_HEX_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * Encodes a strict boolean into a 32-byte ABI word.
     */
    private function encodeBoolean(mixed $value): string
    {
        if (!is_bool($value)) {
            throw new ContractException('An ABI bool argument must be a PHP boolean.');
        }

        return str_repeat('0', self::WORD_HEX_LENGTH - 1) . ($value ? '1' : '0');
    }

    /**
     * Encodes dynamic bytes with length and right-padding.
     */
    private function encodeDynamicBytes(string $bytes): string
    {
        $hex = bin2hex($bytes);
        $paddedHexLength = (int) (ceil(strlen($bytes) / self::WORD_BYTES) * self::WORD_HEX_LENGTH);

        return $this->encodeUnsignedInteger(strlen($bytes), 256)
            . str_pad($hex, $paddedHexLength, '0');
    }

    /**
     * Encodes fixed bytes or a function pointer with mandatory zero padding.
     */
    private function encodeFixedBytes(string $bytes, ?int $expectedLength): string
    {
        if ($expectedLength === null || strlen($bytes) !== $expectedLength) {
            throw new ContractException(sprintf('The fixed ABI byte value must contain exactly %d bytes.', $expectedLength ?? 0));
        }

        return str_pad(bin2hex($bytes), self::WORD_HEX_LENGTH, '0');
    }

    /**
     * Encodes a valid UTF-8 Solidity string as dynamic bytes.
     */
    private function encodeString(mixed $value): string
    {
        if (!is_string($value) || preg_match('//u', $value) !== 1) {
            throw new ContractException('An ABI string argument must contain valid UTF-8 text.');
        }

        return $this->encodeDynamicBytes($value);
    }

    /**
     * Encodes a fixed or dynamic array using one shared sequence implementation.
     */
    private function encodeArray(AbiType $type, mixed $value): string
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new ContractException('An ABI array argument must be a PHP list.');
        }

        $count = count($value);
        if ($count > self::MAX_ARRAY_ELEMENTS) {
            throw new ContractException('The ABI array exceeds the configured safety limit.');
        }

        if ($type->arrayLength !== null && $count !== $type->arrayLength) {
            throw new ContractException(sprintf('The fixed ABI array requires exactly %d values.', $type->arrayLength));
        }

        $types = array_fill(0, $count, $type->requiredElementType());
        $encoded = $this->encodeSequence($types, $value);

        return $type->arrayLength === null
            ? $this->encodeUnsignedInteger($count, 256) . $encoded
            : $encoded;
    }

    /**
     * Encodes tuple values supplied as a list or as unique component names.
     */
    private function encodeTuple(AbiType $type, mixed $value): string
    {
        if (!is_array($value)) {
            throw new ContractException('An ABI tuple argument must be a PHP array.');
        }

        return $this->encodeSequence(
            $type->components(),
            $this->orderedValues($value, $type->componentNames()),
        );
    }

    /**
     * Converts ByteString or string input into exact ABI bytes.
     */
    private function binaryValue(mixed $value): string
    {
        if ($value instanceof ByteString) {
            return $value->bytes();
        }

        if (!is_string($value)) {
            throw new ContractException('An ABI bytes argument must be ByteString or string.');
        }

        return str_starts_with($value, '0x') || str_starts_with($value, '0X')
            ? Hex::toBytes($value)
            : $value;
    }

    /**
     * Encodes a non-negative integer after checking its declared bit width.
     */
    private function encodeUnsignedInteger(mixed $value, int $bits): string
    {
        $number = $this->integer($value, false);
        if (gmp_cmp($number, gmp_sub(gmp_pow(2, $bits), 1)) > 0) {
            throw new ContractException(sprintf('The ABI uint%d value is out of range.', $bits));
        }

        return $this->word($number);
    }

    /**
     * Encodes a signed integer with 256-bit sign extension.
     */
    private function encodeSignedInteger(mixed $value, int $bits): string
    {
        $number = $this->integer($value, true);
        $limit = gmp_pow(2, $bits - 1);
        if (gmp_cmp($number, gmp_neg($limit)) < 0 || gmp_cmp($number, gmp_sub($limit, 1)) > 0) {
            throw new ContractException(sprintf('The ABI int%d value is out of range.', $bits));
        }

        if (gmp_cmp($number, 0) < 0) {
            $number = gmp_add(gmp_pow(2, 256), $number);
        }

        return $this->word($number);
    }

    /**
     * Parses a strict base-10 integer without accepting floats or exponents.
     */
    private function integer(mixed $value, bool $signed): GMP
    {
        if (!is_int($value) && !is_string($value)) {
            throw new ContractException('An ABI integer argument must be an integer or exact decimal string.');
        }

        $decimal = (string) $value;
        $pattern = $signed ? '/^-?(0|[1-9][0-9]*)$/D' : '/^(0|[1-9][0-9]*)$/D';
        if (preg_match($pattern, $decimal) !== 1) {
            throw new ContractException('An ABI integer argument must use canonical base-10 notation.');
        }

        return gmp_init($decimal, 10);
    }

    /**
     * Converts an exact fixed-point decimal into its scaled integer value.
     */
    private function scaledInteger(mixed $value, int $precision, bool $signed): string
    {
        if (!is_int($value) && !is_string($value)) {
            throw new ContractException('An ABI fixed-point argument must be an integer or exact decimal string.');
        }

        $decimal = (string) $value;
        $pattern = $signed
            ? '/^(-?)([0-9]+)(?:\.([0-9]+))?$/D'
            : '/^()([0-9]+)(?:\.([0-9]+))?$/D';
        if (preg_match($pattern, $decimal, $matches) !== 1) {
            throw new ContractException('An ABI fixed-point argument must use plain decimal notation.');
        }

        $fraction = $matches[3] ?? '';
        if (strlen($fraction) > $precision && trim(substr($fraction, $precision), '0') !== '') {
            throw new ContractException(sprintf('The ABI fixed-point value exceeds %d decimal places.', $precision));
        }

        $fraction = str_pad(substr($fraction, 0, $precision), $precision, '0');
        $atomic = ltrim($matches[2] . $fraction, '0');
        $atomic = $atomic === '' ? '0' : $atomic;

        return $matches[1] === '-' && $atomic !== '0' ? '-' . $atomic : $atomic;
    }

    /**
     * Encodes a non-negative GMP value as one 32-byte word.
     */
    private function word(GMP $number): string
    {
        $hex = gmp_strval($number, 16);
        if (strlen($hex) > self::WORD_HEX_LENGTH) {
            throw new ContractException('The ABI integer exceeds one 32-byte word.');
        }

        return str_pad($hex, self::WORD_HEX_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * Decodes a tuple-like sequence at one byte offset.
     *
     * @param list<AbiType> $types Ordered expected types.
     * @return list<mixed>
     */
    private function decodeSequence(array $types, string $data, int $sequenceOffset): array
    {
        $cursor = $sequenceOffset;
        $values = [];

        foreach ($types as $type) {
            if ($type->isDynamic()) {
                $relativeOffset = $this->wordInteger($this->readWord($data, $cursor));
                if ($relativeOffset % self::WORD_BYTES !== 0) {
                    throw new ContractException('An ABI dynamic offset must be aligned to a 32-byte word.');
                }
                $values[] = $this->decodeValue($type, $data, $sequenceOffset + $relativeOffset);
                $cursor += self::WORD_BYTES;
            } else {
                $values[] = $this->decodeStaticValue($type, $data, $cursor);
                $cursor += $type->staticByteLength();
            }
        }

        return $values;
    }

    /**
     * Decodes one dynamic or static ABI value from a byte offset.
     */
    private function decodeValue(AbiType $type, string $data, int $offset): mixed
    {
        if (!$type->isDynamic()) {
            return $this->decodeStaticValue($type, $data, $offset);
        }

        if ($type->kind === 'bytes' || $type->kind === 'string') {
            $length = $this->wordInteger($this->readWord($data, $offset));
            $hex = $this->readHex($data, $offset + self::WORD_BYTES, $length);
            $bytes = $hex === '' ? '' : Hex::toBytes($hex);

            if ($type->kind === 'string') {
                if (preg_match('//u', $bytes) !== 1) {
                    throw new ContractException('Decoded ABI string data is not valid UTF-8.');
                }

                return $bytes;
            }

            return ByteString::fromBytes($bytes);
        }

        if ($type->kind === 'array' || $type->kind === 'tuple') {
            return $this->decodeComposite($type, $data, $offset);
        }

        throw new ContractException(sprintf('ABI decoder has no dynamic implementation for `%s`.', $type->kind));
    }

    /**
     * Decodes one statically placed ABI value at a byte offset.
     */
    private function decodeStaticValue(AbiType $type, string $data, int $offset): mixed
    {
        if ($type->kind === 'array' || $type->kind === 'tuple') {
            return $this->decodeComposite($type, $data, $offset);
        }

        $word = $this->readWord($data, $offset);

        return match ($type->kind) {
            'address' => $this->decodeAddress($word),
            'bool' => $this->decodeBoolean($word),
            'fixed_bytes' => $this->decodeFixedBytes($word, $type->size),
            'function' => $this->decodeFixedBytes($word, 24),
            'uint' => $this->decodeUnsignedInteger($word, $this->requiredSize($type)),
            'int' => $this->decodeSignedInteger($word, $this->requiredSize($type)),
            'ufixed' => $this->decimalFromScaledInteger(
                $this->decodeUnsignedInteger($word, $this->requiredSize($type)),
                $this->requiredPrecision($type),
            ),
            'fixed' => $this->decimalFromScaledInteger(
                $this->decodeSignedInteger($word, $this->requiredSize($type)),
                $this->requiredPrecision($type),
            ),
            default => throw new ContractException(sprintf('ABI decoder has no static implementation for `%s`.', $type->kind)),
        };
    }

    /**
     * Decodes one tuple or fixed/dynamic array from its sequence head.
     *
     * @return list<mixed>
     */
    private function decodeComposite(AbiType $type, string $data, int $offset): array
    {
        if ($type->kind === 'tuple') {
            return $this->decodeSequence($type->components(), $data, $offset);
        }

        $length = $type->arrayLength;
        $sequenceOffset = $offset;
        if ($length === null) {
            $length = $this->wordInteger($this->readWord($data, $offset));
            $sequenceOffset += self::WORD_BYTES;
        }

        if ($length > self::MAX_ARRAY_ELEMENTS) {
            throw new ContractException('Decoded ABI array length exceeds the safety limit.');
        }

        return $this->decodeSequence(
            array_fill(0, $length, $type->requiredElementType()),
            $data,
            $sequenceOffset,
        );
    }

    /**
     * Decodes a zero-padded 20-byte ABI address into a TRON Address.
     */
    private function decodeAddress(string $word): Address
    {
        if (trim(substr($word, 0, 24), '0') !== '') {
            throw new ContractException('Decoded ABI address contains non-zero high bytes.');
        }

        return Address::fromEvmHex(substr($word, 24));
    }

    /**
     * Decodes a strict ABI boolean word.
     */
    private function decodeBoolean(string $word): bool
    {
        if ($word === str_repeat('0', self::WORD_HEX_LENGTH)) {
            return false;
        }

        if ($word === str_repeat('0', self::WORD_HEX_LENGTH - 1) . '1') {
            return true;
        }

        throw new ContractException('Decoded ABI bool word must equal zero or one.');
    }

    /**
     * Decodes fixed bytes after checking all right-padding bytes are zero.
     */
    private function decodeFixedBytes(string $word, ?int $length): ByteString
    {
        if ($length === null || trim(substr($word, $length * 2), '0') !== '') {
            throw new ContractException('Decoded fixed ABI bytes contain invalid padding.');
        }

        return ByteString::fromHex(substr($word, 0, $length * 2));
    }

    /**
     * Decodes an unsigned integer and validates unused high bits.
     */
    private function decodeUnsignedInteger(string $word, int $bits): string
    {
        $number = gmp_init($word, 16);
        if (gmp_cmp($number, gmp_pow(2, $bits)) >= 0) {
            throw new ContractException(sprintf('Decoded ABI uint%d has non-zero unused high bits.', $bits));
        }

        return gmp_strval($number, 10);
    }

    /**
     * Decodes a sign-extended two's-complement integer.
     */
    private function decodeSignedInteger(string $word, int $bits): string
    {
        $number = gmp_init($word, 16);
        $positiveLimit = gmp_pow(2, $bits - 1);
        if (gmp_cmp($number, $positiveLimit) < 0) {
            return gmp_strval($number, 10);
        }

        $negativeBoundary = gmp_sub(gmp_pow(2, 256), $positiveLimit);
        if (gmp_cmp($number, $negativeBoundary) < 0) {
            throw new ContractException(sprintf('Decoded ABI int%d has invalid sign extension.', $bits));
        }

        return gmp_strval(gmp_sub($number, gmp_pow(2, 256)), 10);
    }

    /**
     * Inserts a fixed-point decimal separator into a signed integer string.
     */
    private function decimalFromScaledInteger(string $integer, int $precision): string
    {
        $negative = str_starts_with($integer, '-');
        $digits = $negative ? substr($integer, 1) : $integer;
        $digits = str_pad($digits, $precision + 1, '0', STR_PAD_LEFT);
        $whole = substr($digits, 0, -$precision);
        $fraction = substr($digits, -$precision);

        return ($negative ? '-' : '') . $whole . '.' . $fraction;
    }

    /**
     * Reads one 32-byte word from untrusted ABI data.
     */
    private function readWord(string $data, int $byteOffset): string
    {
        return $this->readHex($data, $byteOffset, self::WORD_BYTES);
    }

    /**
     * Reads an exact byte slice and rejects negative or out-of-bounds offsets.
     */
    private function readHex(string $data, int $byteOffset, int $byteLength): string
    {
        $dataBytes = intdiv(strlen($data), 2);
        if ($byteOffset < 0 || $byteLength < 0 || $byteOffset > $dataBytes - $byteLength) {
            throw new ContractException('ABI decoder attempted to read beyond the returned data.');
        }

        return substr($data, $byteOffset * 2, $byteLength * 2);
    }

    /**
     * Converts a 32-byte offset or length word into a safe native integer.
     */
    private function wordInteger(string $word): int
    {
        $number = gmp_init($word, 16);
        if (gmp_cmp($number, PHP_INT_MAX) > 0) {
            throw new ContractException('ABI offset or length exceeds the native safety range.');
        }

        return gmp_intval($number);
    }

    /**
     * Validates returned hex and enforces a maximum decoder allocation size.
     */
    private function dataHex(string $encoded): string
    {
        $withoutPrefix = str_starts_with($encoded, '0x') || str_starts_with($encoded, '0X')
            ? substr($encoded, 2)
            : $encoded;

        if ($withoutPrefix === '') {
            return '';
        }

        $data = Hex::canonicalize($withoutPrefix);
        if (intdiv(strlen($data), 2) > self::MAX_DATA_BYTES) {
            throw new ContractException('ABI data exceeds the configured decoder safety limit.');
        }

        return $data;
    }

    /**
     * Returns a required integer bit or fixed-byte size from a descriptor.
     */
    private function requiredSize(AbiType $type): int
    {
        if ($type->size === null) {
            throw new ContractException(sprintf('ABI type `%s` is missing its size.', $type->kind));
        }

        return $type->size;
    }

    /**
     * Returns a required fixed-point precision from a descriptor.
     */
    private function requiredPrecision(AbiType $type): int
    {
        if ($type->precision === null) {
            throw new ContractException(sprintf('ABI type `%s` is missing its precision.', $type->kind));
        }

        return $type->precision;
    }

    /**
     * Converts a standard Solidity Panic code into a concise diagnostic message.
     */
    private function panicMessage(string $decimalCode): string
    {
        $hexCode = strtolower(gmp_strval(gmp_init($decimalCode, 10), 16));
        $description = match ($hexCode) {
            '0' => 'generic compiler panic',
            '1' => 'assertion failed',
            '11' => 'arithmetic overflow or underflow',
            '12' => 'division or modulo by zero',
            '21' => 'invalid enum conversion',
            '22' => 'invalid storage byte array encoding',
            '31' => 'pop on an empty array',
            '32' => 'array index out of bounds',
            '41' => 'excessive memory allocation',
            '51' => 'call to an uninitialized internal function',
            default => 'unknown panic condition',
        };

        return sprintf('Contract panicked with code 0x%s: %s.', $hexCode, $description);
    }
}
