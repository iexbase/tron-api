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

use IEXBase\TronAPI\Crypto\LocalPrivateKeySigner;
use IEXBase\TronAPI\Crypto\Secp256k1;
use IEXBase\TronAPI\Encoding\Base58Check;
use IEXBase\TronAPI\Encoding\Hex;
use IEXBase\TronAPI\Exception\CryptoException;
use IEXBase\TronAPI\Value\Address;
use Throwable;

/**
 * Implements BIP-32 private/public derivation and xprv/xpub interchange.
 *
 * Public-only instances support non-hardened child derivation, allowing an
 * application to allocate and monitor TRON addresses without holding spending
 * keys. Hardened derivation remains available only when private key material is
 * present.
 */
final readonly class ExtendedKey
{
    private const MASTER_HMAC_KEY = 'Bitcoin seed';
    private const PRIVATE_VERSION = "\x04\x88\xad\xe4";
    private const PUBLIC_VERSION = "\x04\x88\xb2\x1e";
    private const PAYLOAD_BYTES = 78;
    private const MASTER_SEED_MIN_BYTES = 16;
    private const MASTER_SEED_MAX_BYTES = 64;

    /**
     * Stores validated BIP-32 metadata and one compressed secp256k1 key.
     */
    private function __construct(
        private int $depth,
        private string $parentFingerprint,
        private int $childNumber,
        #[\SensitiveParameter] private string $chainCode,
        private string $publicKey,
        #[\SensitiveParameter] private ?string $privateKey,
    ) {
    }

    /**
     * Creates the BIP-32 master private key from 128 to 512 bits of seed data.
     */
    public static function fromSeed(#[\SensitiveParameter] string $seed): self
    {
        $length = strlen($seed);
        if ($length < self::MASTER_SEED_MIN_BYTES || $length > self::MASTER_SEED_MAX_BYTES) {
            throw new CryptoException('A BIP-32 master seed must contain between 16 and 64 bytes.');
        }

        $digest = hash_hmac('sha512', $seed, self::MASTER_HMAC_KEY, true);

        return self::create(
            0,
            "\0\0\0\0",
            0,
            substr($digest, 32, 32),
            Hex::fromBytes(substr($digest, 0, 32)),
        );
    }

    /**
     * Imports and checksum-verifies either an xprv or xpub extended key.
     */
    public static function fromBase58(string $extendedKey): self
    {
        try {
            $payload = Base58Check::decode($extendedKey);
        } catch (Throwable $exception) {
            throw new CryptoException('The BIP-32 extended key is not valid Base58Check text.', 0, $exception);
        }

        if (strlen($payload) !== self::PAYLOAD_BYTES) {
            throw new CryptoException('A BIP-32 extended key payload must contain exactly 78 bytes.');
        }

        $version = substr($payload, 0, 4);
        $depth = ord($payload[4]);
        $parentFingerprint = substr($payload, 5, 4);
        $childNumber = self::decodeChildNumber(substr($payload, 9, 4));
        $chainCode = substr($payload, 13, 32);
        $keyData = substr($payload, 45, 33);

        self::assertMetadata($depth, $parentFingerprint, $childNumber, $chainCode);

        if (hash_equals(self::PRIVATE_VERSION, $version)) {
            if ($keyData[0] !== "\0") {
                throw new CryptoException('An xprv key payload must begin with a zero byte.');
            }

            return self::create(
                $depth,
                $parentFingerprint,
                $childNumber,
                $chainCode,
                Hex::fromBytes(substr($keyData, 1)),
            );
        }

        if (!hash_equals(self::PUBLIC_VERSION, $version)) {
            throw new CryptoException('The extended key version must identify a standard xprv or xpub key.');
        }

        return self::create(
            $depth,
            $parentFingerprint,
            $childNumber,
            $chainCode,
            null,
            Hex::fromBytes($keyData),
        );
    }

    /**
     * Derives every child in an absolute master path or a relative child path.
     */
    public function derivePath(DerivationPath|string $path): self
    {
        $derivationPath = is_string($path) ? DerivationPath::fromString($path) : $path;
        if ($derivationPath->isAbsolute() && $this->depth !== 0) {
            throw new CryptoException('An absolute derivation path can only be applied to a master key.');
        }

        $derived = $this;
        foreach ($derivationPath->children() as $childNumber) {
            $derived = $derived->deriveChildNumber($childNumber);
        }

        return $derived;
    }

    /**
     * Derives one private or public child at the requested index.
     */
    public function deriveChild(int $index, bool $hardened = false): self
    {
        return $this->deriveChildNumber(DerivationPath::childNumber($index, $hardened));
    }

    /**
     * Returns a public-only key that can derive non-hardened descendants.
     */
    public function withoutPrivateKey(): self
    {
        return new self(
            $this->depth,
            $this->parentFingerprint,
            $this->childNumber,
            $this->chainCode,
            $this->publicKey,
            null,
        );
    }

    /**
     * Returns whether local private derivation and signing are available.
     */
    public function hasPrivateKey(): bool
    {
        return $this->privateKey !== null;
    }

    /**
     * Returns this key's BIP-32 depth from the master key.
     */
    public function depth(): int
    {
        return $this->depth;
    }

    /**
     * Returns the encoded unsigned BIP-32 child number.
     */
    public function childNumber(): int
    {
        return $this->childNumber;
    }

    /**
     * Returns the compressed 33-byte public key as lowercase hexadecimal text.
     */
    public function publicKeyHex(): string
    {
        return $this->publicKey;
    }

    /**
     * Returns the TRON address derived from this public key.
     */
    public function address(): Address
    {
        return Secp256k1::addressFromPublicKey($this->publicKey);
    }

    /**
     * Creates a local signer when this extended key contains private material.
     */
    public function signer(): LocalPrivateKeySigner
    {
        if ($this->privateKey === null) {
            throw new CryptoException('A public-only extended key cannot create a signer.');
        }

        return new LocalPrivateKeySigner($this->privateKey);
    }

    /**
     * Explicitly exports the xprv representation for secure offline backup.
     */
    public function exportPrivate(): string
    {
        if ($this->privateKey === null) {
            throw new CryptoException('A public-only extended key has no xprv representation.');
        }

        return $this->encode(self::PRIVATE_VERSION, "\0" . Hex::toBytes($this->privateKey, 32));
    }

    /**
     * Exports the shareable watch-only xpub representation.
     */
    public function exportPublic(): string
    {
        return $this->encode(self::PUBLIC_VERSION, Hex::toBytes($this->publicKey, 33));
    }

    /**
     * Prevents accidental persistence of chain codes or private key material.
     *
     * @return array<never, never>
     */
    public function __serialize(): array
    {
        throw new CryptoException('ExtendedKey cannot be serialized.');
    }

    /**
     * Redacts private key material and chain codes in debugger output.
     *
     * @return array<string, bool|int|string>
     */
    public function __debugInfo(): array
    {
        return [
            'depth' => $this->depth,
            'childNumber' => $this->childNumber,
            'hasPrivateKey' => $this->hasPrivateKey(),
            'publicKey' => $this->publicKey,
            'extendedPublicKey' => $this->exportPublic(),
            'privateKey' => '[REDACTED]',
            'chainCode' => '[REDACTED]',
        ];
    }

    /**
     * Validates and creates a private or public extended key instance.
     */
    private static function create(
        int $depth,
        string $parentFingerprint,
        int $childNumber,
        #[\SensitiveParameter] string $chainCode,
        #[\SensitiveParameter] ?string $privateKey,
        ?string $publicKey = null,
    ): self {
        self::assertMetadata($depth, $parentFingerprint, $childNumber, $chainCode);

        if ($privateKey === null && $publicKey === null) {
            throw new CryptoException('An extended key must contain private or public key material.');
        }

        $canonicalPrivateKey = $privateKey === null
            ? null
            : Secp256k1::canonicalPrivateKey($privateKey);
        $canonicalPublicKey = $canonicalPrivateKey === null
            ? Secp256k1::compressPublicKey((string) $publicKey)
            : Secp256k1::compressedPublicKeyFromPrivateKey($canonicalPrivateKey);

        return new self(
            $depth,
            $parentFingerprint,
            $childNumber,
            $chainCode,
            $canonicalPublicKey,
            $canonicalPrivateKey,
        );
    }

    /**
     * Derives one encoded BIP-32 child number without changing its identity.
     */
    private function deriveChildNumber(int $childNumber): self
    {
        if ($this->depth >= 255) {
            throw new CryptoException('A BIP-32 key at depth 255 cannot derive another child.');
        }

        $hardened = DerivationPath::isHardened($childNumber);
        if ($hardened && $this->privateKey === null) {
            throw new CryptoException('Hardened child derivation requires an extended private key.');
        }

        $data = $hardened
            ? "\0" . Hex::toBytes((string) $this->privateKey, 32)
            : Hex::toBytes($this->publicKey, 33);
        $digest = hash_hmac(
            'sha512',
            $data . self::encodeChildNumber($childNumber),
            $this->chainCode,
            true,
        );
        $tweak = Hex::fromBytes(substr($digest, 0, 32));

        try {
            Secp256k1::canonicalPrivateKey($tweak);
            $privateKey = $this->privateKey === null
                ? null
                : Secp256k1::addPrivateScalars($this->privateKey, $tweak);
            $publicKey = $privateKey === null
                ? Secp256k1::addPublicKeyScalar($this->publicKey, $tweak)
                : null;
        } catch (Throwable $exception) {
            throw new CryptoException('BIP-32 produced an invalid child key for the requested index.', 0, $exception);
        }

        return self::create(
            $this->depth + 1,
            $this->fingerprint(),
            $childNumber,
            substr($digest, 32, 32),
            $privateKey,
            $publicKey,
        );
    }

    /**
     * Returns the four-byte HASH160 fingerprint of the current public key.
     */
    private function fingerprint(): string
    {
        $sha256 = hash('sha256', Hex::toBytes($this->publicKey, 33), true);

        return substr(hash('ripemd160', $sha256, true), 0, 4);
    }

    /**
     * Encodes the common 78-byte BIP-32 payload as Base58Check text.
     */
    private function encode(string $version, string $keyData): string
    {
        $payload = $version
            . chr($this->depth)
            . $this->parentFingerprint
            . self::encodeChildNumber($this->childNumber)
            . $this->chainCode
            . $keyData;

        if (strlen($payload) !== self::PAYLOAD_BYTES) {
            throw new CryptoException('The BIP-32 extended key payload has an invalid length.');
        }

        return Base58Check::encode($payload);
    }

    /**
     * Validates common BIP-32 serialization metadata.
     */
    private static function assertMetadata(
        int $depth,
        string $parentFingerprint,
        int $childNumber,
        string $chainCode,
    ): void {
        if ($depth < 0 || $depth > 255) {
            throw new CryptoException('A BIP-32 depth must fit in one byte.');
        }
        if (strlen($parentFingerprint) !== 4 || strlen($chainCode) !== 32) {
            throw new CryptoException('BIP-32 requires a four-byte parent fingerprint and a 32-byte chain code.');
        }
        if ($childNumber < 0 || $childNumber > 4_294_967_295) {
            throw new CryptoException('A BIP-32 child number must fit in an unsigned 32-bit integer.');
        }
        if ($depth === 0 && ($parentFingerprint !== "\0\0\0\0" || $childNumber !== 0)) {
            throw new CryptoException('A BIP-32 master key must use zero parent and child metadata.');
        }
    }

    /**
     * Encodes an unsigned child number in big-endian order.
     */
    private static function encodeChildNumber(int $childNumber): string
    {
        return pack('N', $childNumber);
    }

    /**
     * Decodes a four-byte big-endian unsigned child number.
     */
    private static function decodeChildNumber(string $bytes): int
    {
        $decoded = unpack('NchildNumber', $bytes);
        if (!is_array($decoded) || !isset($decoded['childNumber']) || !is_int($decoded['childNumber'])) {
            throw new CryptoException('The BIP-32 child number could not be decoded.');
        }

        return $decoded['childNumber'];
    }
}
