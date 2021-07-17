<?php declare(strict_types=1);

namespace IEXBase\TronAPI\Secp256k1;

use InvalidArgumentException;
use IEXBase\TronAPI\Secp256k1\Serializer\HexPrivateKeySerializer;
use IEXBase\TronAPI\Secp256k1\Signature\Signer;
use Mdanter\Ecc\Crypto\Signature\SignatureInterface;
use Mdanter\Ecc\Curves\CurveFactory;
use Mdanter\Ecc\Curves\SecgCurve;
use Mdanter\Ecc\EccFactory;
use Mdanter\Ecc\Primitives\PointInterface;
use Mdanter\Ecc\Random\RandomGeneratorFactory;

class Secp256k1
{
    protected $adapter;

    protected $generator;

    protected $curve;

    protected $deserializer;

    protected $algorithm;

    public function __construct(string $hashAlgorithm='sha256') {
        $this->adapter = EccFactory::getAdapter();
        $this->generator = CurveFactory::getGeneratorByName(SecgCurve::NAME_SECP_256K1);
        $this->curve = $this->generator->getCurve();
        $this->deserializer = new HexPrivateKeySerializer($this->generator);
        $this->algorithm = $hashAlgorithm;
    }

    public function sign(string $hash, string $privateKey, array $options=[]): SignatureInterface {
        $key = $this->deserializer->parse($privateKey);
        $hex_hash = gmp_init($hash, 16);

        if (!isset($options['n'])) {
            $random = RandomGeneratorFactory::getHmacRandomGenerator($key, $hex_hash, $this->algorithm);
            $n = $this->generator->getOrder();
            $randomK = $random->generate($n);

            $options['n']  = $n;
        }
        if (!isset($options['canonical'])) {
            $options['canonical'] = true;
        }
        $signer = new Signer($this->adapter, $options);

        return $signer->sign($key, $hex_hash, $randomK);
    }

    public function verify(string $hash, SignatureInterface $signature, string $publicKey): bool
    {
        $gmpKey = $this->decodePoint($publicKey);
        $key = $this->generator->getPublickeyFrom($gmpKey->getX(), $gmpKey->getY());
        $hex_hash = gmp_init($hash, 16);
        $signer = new Signer($this->adapter);

        return $signer->verify($key, $signature, $hex_hash);
    }

    protected function decodePoint(string $publicKey): PointInterface
    {
        $order = $this->generator->getOrder();
        $orderString = gmp_strval($order, 16);
        $length = mb_strlen($orderString);
        $keyLength = mb_strlen($publicKey);
        $num = hexdec(mb_substr($publicKey, 0, 2));

        if (
            ($num === 4 || $num === 6 || $num === 7) &&
            ($length * 2 + 2) === $keyLength
            ) {
            $x = gmp_init(mb_substr($publicKey, 2, $length), 16);
            $y = gmp_init(mb_substr($publicKey, ($length + 2), $length), 16);

            if ($this->generator->isValid($x, $y) !== true) {
                throw new InvalidArgumentException('Invalid public key point x and y.');
            }

            return $this->curve->getPoint($x, $y, $order);
        } elseif (
            ($num === 2 || $num === 3) &&
            ($length + 2) === $keyLength
        ) {
            $x = gmp_init(mb_substr($publicKey, 2, $length), 16);
            $y = $this->curve->recoverYfromX($num === 3, $x);

            return $this->curve->getPoint($x, $y, $order);
        }
        throw new InvalidArgumentException('Invalid public key point format.');
    }
}
