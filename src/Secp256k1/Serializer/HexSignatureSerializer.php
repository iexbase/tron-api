<?php declare(strict_types=1);

namespace IEXBase\TronAPI\Secp256k1\Serializer;

use InvalidArgumentException;
use Mdanter\Ecc\Crypto\Signature\Signature;
use Mdanter\Ecc\Crypto\Signature\SignatureInterface;

class HexSignatureSerializer
{
    public function serialize(SignatureInterface $signature): string {
        $r = $signature->getR();
        $s = $signature->getS();

        return str_pad(gmp_strval($r, 16), 64, '0', STR_PAD_LEFT) . str_pad(gmp_strval($s, 16), 64, '0', STR_PAD_LEFT);
    }

    public function parse(string $binary): SignatureInterface {
        $binary_lower = mb_strtolower($binary);

        if (strpos($binary_lower, '0x') >= 0) {
            $count = 1;
            $binary_lower = str_replace('0x', '', $binary_lower, $count);
        }
        if (mb_strlen($binary_lower) !== 128) {
            throw new InvalidArgumentException('Binary string was not correct.');
        }
        $r = mb_substr($binary_lower, 0, 64);
        $s = mb_substr($binary_lower, 64, 64);

        return new Signature(
            gmp_init($r, 16),
            gmp_init($s, 16)
        );
    }
}
