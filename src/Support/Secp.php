<?php

declare(strict_types=1);

namespace IEXBase\TronAPI\Support;

use IEXBase\TronAPI\Secp256k1\Secp256k1;
use IEXBase\TronAPI\Secp256k1\Signature\Signature;

class Secp
{
    public static function sign(string $message, string $privateKey): string
    {
        $secp = new Secp256k1();

        /** @var Signature $sign */
        $sign = $secp->sign($message, $privateKey, ['canonical' => false]);

        return $sign->toHex() . bin2hex(implode('', array_map('chr', [$sign->getRecoveryParam()])));
    }
}
