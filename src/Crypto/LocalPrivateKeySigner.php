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

namespace IEXBase\TronAPI\Crypto;

use IEXBase\TronAPI\Exception\CryptoException;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\Signature;

/**
 * Signs transaction digests locally without exposing a key to the API client.
 *
 * The signer is deliberately passed to signing operations instead of being set
 * on a global Tron object. Debug and serialization paths redact or reject the
 * key, and no transport-facing type has a property for private key material.
 */
final class LocalPrivateKeySigner implements SignerInterface
{
    private readonly string $privateKey;
    private readonly string $publicKey;
    private readonly Address $address;

    /**
     * Validates a private key and derives its public identity entirely locally.
     */
    public function __construct(#[\SensitiveParameter] string $privateKey)
    {
        $this->privateKey = Secp256k1::canonicalPrivateKey($privateKey);
        $this->publicKey = Secp256k1::publicKeyFromPrivateKey($this->privateKey);
        $this->address = Secp256k1::addressFromPublicKey($this->publicKey);
    }

    /**
     * Generates a cryptographically random local signer.
     */
    public static function generate(): self
    {
        return new self(Secp256k1::generatePrivateKey());
    }

    /**
     * Returns the address controlled by this private key.
     */
    public function address(): Address
    {
        return $this->address;
    }

    /**
     * Returns the uncompressed public key as 65-byte hexadecimal text.
     */
    public function publicKeyHex(): string
    {
        return $this->publicKey;
    }

    /**
     * Explicitly exports the private key for secure backup of generated keys.
     *
     * The returned value must never be logged, serialized, or sent to a node.
     */
    public function exportPrivateKey(): string
    {
        return $this->privateKey;
    }

    /**
     * Signs a 32-byte digest locally in canonical recoverable TRON form.
     */
    public function signDigest(string $digestHex): Signature
    {
        return Secp256k1::signDigest($digestHex, $this->privateKey);
    }

    /**
     * Prevents accidental persistence of private key material via serialize().
     *
     * @return array<never, never>
     */
    public function __serialize(): array
    {
        throw new CryptoException('LocalPrivateKeySigner cannot be serialized.');
    }

    /**
     * Redacts key material when the signer is inspected by a debugger.
     *
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return [
            'privateKey' => '[REDACTED]',
            'publicKey' => $this->publicKey,
            'address' => $this->address->toBase58(),
        ];
    }
}
