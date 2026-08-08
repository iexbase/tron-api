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

namespace IEXBase\TronAPI\Value;

use IEXBase\TronAPI\Exception\ValidationException;
use JsonSerializable;

/**
 * Represents a non-negative token amount without floating-point arithmetic.
 *
 * The atomic value is stored as an arbitrary-precision decimal integer string.
 * A separate decimals value controls display conversion for TRX, TRC-10, and
 * smart-contract tokens without changing global BCMath state.
 */
final readonly class Amount implements JsonSerializable
{
    public const TRX_DECIMALS = 6;

    /** @var numeric-string */
    private string $atomicValue;

    private int $decimals;

    /**
     * Stores a canonical atomic integer and its token-specific decimal scale.
     *
     * @param numeric-string $atomicValue Canonical non-negative atomic integer.
     */
    private function __construct(
        string $atomicValue,
        int $decimals,
    ) {
        self::assertDecimals($decimals);
        $this->atomicValue = $atomicValue;
        $this->decimals = $decimals;
    }

    /**
     * Creates an amount from a non-negative atomic integer such as sun.
     *
     * @param int|string $atomicValue Exact integer value; exponent notation is rejected.
     * @param int        $decimals Token decimal scale used for display conversion.
     */
    public static function fromAtomic(int|string $atomicValue, int $decimals = self::TRX_DECIMALS): self
    {
        return new self(self::canonicalAtomicValue((string) $atomicValue), $decimals);
    }

    /**
     * Creates an amount from exact non-exponent decimal text or an integer.
     *
     * @param int|string $decimalValue Human-readable amount; floats are intentionally unsupported.
     * @param int        $decimals Token decimal scale used to derive the atomic value.
     */
    public static function fromDecimal(int|string $decimalValue, int $decimals = self::TRX_DECIMALS): self
    {
        self::assertDecimals($decimals);
        $value = (string) $decimalValue;

        if (preg_match('/^([0-9]+)(?:\.([0-9]+))?$/D', $value, $matches) !== 1) {
            throw new ValidationException('A decimal amount must use plain non-negative decimal notation.');
        }

        $whole = ltrim($matches[1], '0');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = $matches[2] ?? '';

        if (strlen($fraction) > $decimals && trim(substr($fraction, $decimals), '0') !== '') {
            throw new ValidationException(sprintf('The amount has more than %d significant decimal places.', $decimals));
        }

        $fraction = substr($fraction, 0, $decimals);
        $fraction = str_pad($fraction, $decimals, '0');
        $atomic = ltrim($whole . $fraction, '0');

        return new self(self::canonicalAtomicValue($atomic === '' ? '0' : $atomic), $decimals);
    }

    /**
     * Returns the exact atomic integer as a decimal string.
     */
    public function atomicValue(): string
    {
        return $this->atomicValue;
    }

    /**
     * Returns the exact atomic integer as a native integer when it fits.
     */
    public function atomicInteger(): int
    {
        if (strlen($this->atomicValue) > strlen((string) PHP_INT_MAX)
            || (strlen($this->atomicValue) === strlen((string) PHP_INT_MAX)
                && strcmp($this->atomicValue, (string) PHP_INT_MAX) > 0)
        ) {
            throw new ValidationException('The atomic amount exceeds the native integer range required by this API field.');
        }

        return (int) $this->atomicValue;
    }

    /**
     * Returns the token-specific decimal scale.
     */
    public function decimals(): int
    {
        return $this->decimals;
    }

    /**
     * Converts the atomic integer into exact decimal text.
     */
    public function decimalValue(bool $trimTrailingZeros = true): string
    {
        if ($this->decimals === 0) {
            return $this->atomicValue;
        }

        $padded = str_pad($this->atomicValue, $this->decimals + 1, '0', STR_PAD_LEFT);
        $whole = substr($padded, 0, -$this->decimals);
        $fraction = substr($padded, -$this->decimals);

        if ($trimTrailingZeros) {
            $fraction = rtrim($fraction, '0');
        }

        return $fraction === '' ? $whole : $whole . '.' . $fraction;
    }

    /**
     * Returns whether the atomic value is zero.
     */
    public function isZero(): bool
    {
        return $this->atomicValue === '0';
    }

    /**
     * Adds another amount with the same decimal scale.
     */
    public function add(self $other): self
    {
        $this->assertSameDecimals($other);

        return new self(bcadd($this->atomicValue, $other->atomicValue, 0), $this->decimals);
    }

    /**
     * Subtracts another amount and rejects a negative result.
     */
    public function subtract(self $other): self
    {
        $this->assertSameDecimals($other);
        if ($this->compare($other) < 0) {
            throw new ValidationException('An amount subtraction cannot produce a negative value.');
        }

        return new self(bcsub($this->atomicValue, $other->atomicValue, 0), $this->decimals);
    }

    /**
     * Multiplies the atomic value by a non-negative integer.
     */
    public function multiplyByInteger(int|string $multiplier): self
    {
        $value = self::canonicalAtomicValue((string) $multiplier);

        return new self(bcmul($this->atomicValue, $value, 0), $this->decimals);
    }

    /**
     * Compares two amounts that use the same decimal scale.
     */
    public function compare(self $other): int
    {
        $this->assertSameDecimals($other);

        return bccomp($this->atomicValue, $other->atomicValue, 0);
    }

    /**
     * Serializes the amount as exact human-readable decimal text.
     */
    public function jsonSerialize(): string
    {
        return $this->decimalValue(false);
    }

    /**
     * Validates the supported token decimal range.
     */
    private static function assertDecimals(int $decimals): void
    {
        if ($decimals < 0 || $decimals > 255) {
            throw new ValidationException('Token decimals must be between 0 and 255.');
        }
    }

    /**
     * Validates and returns a canonical non-negative integer for BCMath.
     *
     * @return numeric-string
     */
    private static function canonicalAtomicValue(string $value): string
    {
        if (!is_numeric($value) || preg_match('/^(0|[1-9][0-9]*)$/D', $value) !== 1) {
            throw new ValidationException('An atomic value must be a non-negative decimal integer.');
        }

        return $value;
    }

    /**
     * Requires both operands to describe the same atomic unit.
     */
    private function assertSameDecimals(self $other): void
    {
        if ($this->decimals !== $other->decimals) {
            throw new ValidationException('Amounts with different decimal scales cannot be combined.');
        }
    }
}
