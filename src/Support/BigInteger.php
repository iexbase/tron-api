<?php
namespace IEXBase\TronAPI\Support;

use GMP;
use InvalidArgumentException;
use RuntimeException;

class BigInteger
{
    /**
     * Значение представлено в виде строки.
     *
     * @var string
     */
    private $value;

    /**
     * Флаг, указывающий, можно ли изменить состояние этого объекта.
     *
     * @var bool
     */
    private $mutable;

    /**
     * Инициализирует новый экземпляр этого класса.
     *
     * @param string $value
     * @param bool $mutable
     */
    public function __construct(string $value = '0', bool $mutable = true)
    {
        $this->value = $this->initValue($value);
        $this->mutable = $mutable;
    }

    /**
     * Получает значение большого целого.
     *
     * @return string
     */
    public function getValue(): string
    {
        return gmp_strval($this->value);
    }

    /**
     * Устанавливает значение.
     *
     * @param string $value
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
     * Преобразует значение в абсолютное число.
     *
     * @return BigInteger
     */
    public function abs(): BigInteger
    {
        $value = gmp_abs($this->value);

        return $this->assignValue($value);
    }

    /**
     * Добавляет заданное значение к этому значению.
     *
     * @param string $value
     * @return BigInteger
     */
    public function add(string $value): BigInteger
    {
        $gmp = $this->initValue($value);

        $calculatedValue = gmp_add($this->value, $gmp);

        return $this->assignValue($calculatedValue);
    }

    /**
     * Сравнивает это число и заданный номер.
     *
     * @param string $value
     * @return int
     */
    public function cmp($value): int
    {
        $value = $this->initValue($value);

        $result = gmp_cmp($this->value, $value);

        if ($result > 0) {
            return 1;
        } elseif ($result < 0) {
            return -1;
        }

        return 0;
    }

    /**
     * Делит это значение на заданное значение.
     *
     * @param string $value
     * @return BigInteger
     */
    public function divide(string $value): BigInteger
    {
        $gmp = $this->initValue($value);

        $calculatedValue = gmp_div_q($this->value, $gmp, GMP_ROUND_ZERO);

        return $this->assignValue($calculatedValue);
    }

    /**
     * Вычисляет факториал этого значения.
     *
     * @return BigInteger
     */
    public function factorial(): BigInteger
    {
        $calculatedValue = gmp_fact($this->getValue());

        return $this->assignValue($calculatedValue);
    }

    /**
     * Выполняет операцию по модулю с заданным номером.
     *
     * @param string $value
     * @return BigInteger
     */
    public function mod(string $value): BigInteger
    {
        $gmp = $this->initValue($value);

        $calculatedValue = gmp_mod($this->value, $gmp);

        return $this->assignValue($calculatedValue);
    }

    /**
     * Умножает заданное значение на это значение
     *
     * @param string $value
     * @return BigInteger
     */
    public function multiply(string $value): BigInteger
    {
        $gmp = $this->initValue($value);

        $calculatedValue = gmp_mul($this->value, $gmp);

        return $this->assignValue($calculatedValue);
    }

    /**
     * Отрицает значение.
     *
     * @return BigInteger
     */
    public function negate(): BigInteger
    {
        $calculatedValue = gmp_neg($this->value);

        return $this->assignValue($calculatedValue);
    }

    /**
     * Выполняет операцию с заданным номером.
     *
     * @param int $value
     * @return BigInteger
     */
    public function pow(int $value): BigInteger
    {
        $calculatedValue = gmp_pow($this->value, $value);

        return $this->assignValue($calculatedValue);
    }

    /**
     * Вычитает заданное значение из этого значения.
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
     * Проверяет, является ли большой интеграл простым числом.
     *
     * @param float $probabilityFactor
     * @return bool
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
     * Проверяет, является ли этот объект изменчивым.
     *
     * @return bool
     */
    public function isMutable(): bool
    {
        return $this->mutable;
    }

    /**
     * Преобразует этот класс в строку.
     *
     * @return string
     */
    public function toString(): string
    {
        return $this->getValue();
    }

    /**
     * Преобразует этот класс в строку.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Вспомогательный метод для присвоения заданного значения.
     *
     * @param GMP $value
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
     * Создает новый объект GMP.
     *
     * @param string $value
     * @return GMP
     * @throws InvalidArgumentException
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