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

namespace IEXBase\TronAPI\Crypto\Wallet;

use IEXBase\TronAPI\Exception\ValidationException;
use Stringable;

/**
 * Represents an immutable absolute or relative BIP-32 derivation path.
 */
final readonly class DerivationPath implements Stringable
{
    public const HARDENED_OFFSET = 2_147_483_648;
    public const MAX_INDEX = 2_147_483_647;

    /**
     * Stores already validated encoded child numbers.
     *
     * @param list<int> $children Child numbers including the hardened bit.
     */
    private function __construct(
        private bool $absolute,
        private array $children,
    ) {
    }

    /**
     * Parses paths such as `m/44'/195'/0'/0/0` or relative `0/0`.
     */
    public static function fromString(string $path): self
    {
        if ($path === 'm') {
            return new self(true, []);
        }

        if ($path === '' || trim($path) !== $path) {
            throw new ValidationException('A derivation path must not be empty or contain surrounding whitespace.');
        }

        $segments = explode('/', $path);
        $absolute = $segments[0] === 'm';
        if ($absolute) {
            array_shift($segments);
        }

        if ($segments === []) {
            throw new ValidationException('A relative derivation path must contain at least one child index.');
        }

        $children = [];
        foreach ($segments as $segment) {
            if (preg_match('/^(0|[1-9][0-9]*)([\'hH]?)$/D', $segment, $matches) !== 1) {
                throw new ValidationException(sprintf('Invalid derivation path segment `%s`.', $segment));
            }

            $index = filter_var($matches[1], FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 0, 'max_range' => self::MAX_INDEX],
            ]);
            if (!is_int($index)) {
                throw new ValidationException('A derivation child index must be between 0 and 2147483647.');
            }

            $children[] = self::childNumber($index, $matches[2] !== '');
        }

        return new self($absolute, $children);
    }

    /**
     * Creates the standard TRON BIP-44 account address path.
     */
    public static function tronAccount(int $account = 0, int $change = 0, int $index = 0): self
    {
        self::assertIndex($account);
        self::assertIndex($index);
        if (!in_array($change, [0, 1], true)) {
            throw new ValidationException('A BIP-44 change branch must be 0 for external or 1 for internal addresses.');
        }

        return new self(true, [
            self::childNumber(44, true),
            self::childNumber(195, true),
            self::childNumber($account, true),
            self::childNumber($change, false),
            self::childNumber($index, false),
        ]);
    }

    /**
     * Creates an encoded BIP-32 child number from an index and hardening flag.
     */
    public static function childNumber(int $index, bool $hardened): int
    {
        self::assertIndex($index);

        return $hardened ? $index + self::HARDENED_OFFSET : $index;
    }

    /**
     * Returns whether this path begins at the BIP-32 master key.
     */
    public function isAbsolute(): bool
    {
        return $this->absolute;
    }

    /**
     * Returns the encoded child numbers in derivation order.
     *
     * @return list<int>
     */
    public function children(): array
    {
        return $this->children;
    }

    /**
     * Returns whether this is a complete standard TRON BIP-44 address path.
     */
    public function isTronAccount(): bool
    {
        return $this->absolute
            && count($this->children) === 5
            && $this->children[0] === self::childNumber(44, true)
            && $this->children[1] === self::childNumber(195, true)
            && $this->children[2] >= self::HARDENED_OFFSET
            && in_array($this->children[3], [0, 1], true)
            && $this->children[4] < self::HARDENED_OFFSET;
    }

    /**
     * Returns whether an encoded child number denotes hardened derivation.
     */
    public static function isHardened(int $childNumber): bool
    {
        self::assertChildNumber($childNumber);

        return $childNumber >= self::HARDENED_OFFSET;
    }

    /**
     * Returns the user-facing index without the hardened bit.
     */
    public static function indexOf(int $childNumber): int
    {
        return self::isHardened($childNumber)
            ? $childNumber - self::HARDENED_OFFSET
            : $childNumber;
    }

    /**
     * Returns the canonical path using apostrophes for hardened children.
     */
    public function __toString(): string
    {
        $segments = $this->absolute ? ['m'] : [];
        foreach ($this->children as $childNumber) {
            $segments[] = (string) self::indexOf($childNumber)
                . (self::isHardened($childNumber) ? "'" : '');
        }

        return implode('/', $segments);
    }

    /**
     * Rejects indexes outside the non-hardened 31-bit BIP-32 range.
     */
    private static function assertIndex(int $index): void
    {
        if ($index < 0 || $index > self::MAX_INDEX) {
            throw new ValidationException('A derivation child index must be between 0 and 2147483647.');
        }
    }

    /**
     * Rejects encoded child numbers outside the unsigned 32-bit range.
     */
    private static function assertChildNumber(int $childNumber): void
    {
        if ($childNumber < 0 || $childNumber > 4_294_967_295) {
            throw new ValidationException('An encoded BIP-32 child number must fit in an unsigned 32-bit integer.');
        }
    }
}
