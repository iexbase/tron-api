<?php
namespace IEXBase\TronAPI\Support;

use GMP;
use InvalidArgumentException;
use RuntimeException;

class BigInteger
{
    /**
     * The value represented as a string.
     *
     * @var string
     */
    private $value;

    /**
     * A flag that indicates whether or not the state of this object can be changed.
     *
     * @var bool
     */
    private $mutable;

    /**
     * Initializes a new instance of this class.
     *
     * @param string $value The value to set.
     * @param bool $mutable Whether or not the state of this object can be changed.
     */
    public function __construct(string $value = '0', bool $mutable = true)
    {
        $this->value = $this->initValue($value);
        $this->mutable = $mutable;
    }

    /**
     * Gets the value of the big integer.
     *
     * @return string
     */
    public function getValue(): string
    {
        return gmp_strval($this->value);
    }

    /**
     * Sets the value.
     *
     * @param string $value The value to set.
     * @return BigInteger
     */
    public function setValue(string $value): BigInteger
    {
        if (!$this->isMutable()) {
            throw new RuntimeException('Cannot set the value since the number is immutable.');
        }

        $this->value = $this->initValue($value);

        return $this;
    }

    /**
     * Converts the value to an absolute number.
     *
     * @return BigInteger
     */
    public function abs(): BigInteger
    {
        $value = gmp_abs($this->value);

        return $this->assignValue($value);
    }

    /**
     * Adds the given value to this value.
     *
     * @param string $value The value to add.
     * @return BigInteger
     */
    public function add(string $value): BigInteger
    {
        $gmp = $this->initValue($value);

        $calculatedValue = gmp_add($this->value, $gmp);

        return $this->assignValue($calculatedValue);
    }

    /**
     * Compares this number and the given number.
     *
     * @param string $value The value to compare.
     * @return int Returns -1 is the number is less than this number. 0 if equal and 1 when greater.
     */
    public function cmp($value): int
    {
        $value = $this->initValue($value);

        $result = gmp_cmp($this->value, $value);

        // It could happen that gmp_cmp returns a value greater than one (e.g. gmp_cmp('123', '-123')). That's why
        // we do an additional check to make sure to return the correct value.

        if ($result > 0) {
            return 1;
        } elseif ($result < 0) {
            return -1;
        }

        return 0;
    }

    /**
     * Divides this value by the given value.
     *
     * @param string $value The value to divide by.
     * @return BigInteger
     */
    public function divide(string $value): BigInteger
    {
        $gmp = $this->initValue($value);

        $calculatedValue = gmp_div_q($this->value, $gmp, GMP_ROUND_ZERO);

        return $this->assignValue($calculatedValue);
    }

    /**
     * Calculates factorial of this value.
     *
     * @return BigInteger
     */
    public function factorial(): BigInteger
    {
        $calculatedValue = gmp_fact($this->getValue());

        return $this->assignValue($calculatedValue);
    }

    /**
     * Performs a modulo operation with the given number.
     *
     * @param string $value The value to perform a modulo operation with.
     * @return BigInteger
     */
    public function mod(string $value): BigInteger
    {
        $gmp = $this->initValue($value);

        $calculatedValue = gmp_mod($this->value, $gmp);

        return $this->assignValue($calculatedValue);
    }

    /**
     * Multiplies the given value with this value.
     *
     * @param string $value The value to multiply with.
     * @return BigInteger
     */
    public function multiply(string $value): BigInteger
    {
        $gmp = $this->initValue($value);

        $calculatedValue = gmp_mul($this->value, $gmp);

        return $this->assignValue($calculatedValue);
    }

    /**
     * Negates the value.
     *
     * @return BigInteger
     */
    public function negate(): BigInteger
    {
        $calculatedValue = gmp_neg($this->value);

        return $this->assignValue($calculatedValue);
    }

    /**
     * Performs a power operation with the given number.
     *
     * @param int $value The value to perform a power operation with.
     * @return BigInteger
     */
    public function pow(int $value): BigInteger
    {
        $calculatedValue = gmp_pow($this->value, $value);

        return $this->assignValue($calculatedValue);
    }

    /**
     * Subtracts the given value from this value.
     *
     * @param string $value The value to subtract.
     * @return BigInteger
     */
    public function subtract(string $value): BigInteger
    {
        $gmp = $this->initValue($value);

        $calculatedValue = gmp_sub($this->value, $gmp);

        return $this->assignValue($calculatedValue);
    }

    /**
     * Checks if the big integr is the prime number.
     *
     * @param float $probabilityFactor A normalized factor between 0 and 1 used for checking the probability.
     * @return bool Returns true if the number is a prime number false if not.
     */
    public function isPrimeNumber(float $probabilityFactor = 1.0): bool
    {
        $reps = (int)floor(($probabilityFactor * 5.0) + 5.0);

        if ($reps < 5 || $reps > 10) {
            throw new InvalidArgumentException('The provided probability number should be 5 to 10.');
        }

        return gmp_prob_prime($this->value, $reps) !== 0;
    }

    /**
     * Checks if this object is mutable.
     *
     * @return bool
     */
    public function isMutable(): bool
    {
        return $this->mutable;
    }

    /**
     * Converts this class to a string.
     *
     * @return string
     */
    public function toString(): string
    {
        return $this->getValue();
    }

    /**
     * Converts this class to a string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * A helper method to assign the given value.
     *
     * @param GMP $value The value to assign.
     * @return BigInteger
     */
    private function assignValue(GMP $value): BigInteger
    {
        $rawValue = gmp_strval($value);

        if ($this->isMutable()) {
            $this->value = gmp_init($rawValue);

            return $this;
        }

        return new BigInteger($rawValue, false);
    }

    /**
     * Creates a new GMP object.
     *
     * @param string $value The value to initialize with.
     * @return GMP
     * @throws InvalidArgumentException Thrown when the value is invalid.
     */
    private function initValue(string $value): GMP
    {
        $result = @gmp_init($value);

        if ($result === false) {
            throw new InvalidArgumentException('The provided number is invalid.');
        }

        return $result;
    }
}
