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

use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\Signature;

/**
 * Defines a local, hardware, custody, or remote signing boundary.
 *
 * The API client never receives this interface, so a transport cannot include
 * private key material in an HTTP request. Implementations sign only a 32-byte
 * digest and expose the address that the signature must recover.
 */
interface SignerInterface
{
    /**
     * Returns the account or permission-key address controlled by the signer.
     */
    public function address(): Address;

    /**
     * Signs one 32-byte digest in recoverable TRON `r || s || v` form.
     */
    public function signDigest(string $digestHex): Signature;
}
