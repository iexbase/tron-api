<?php
namespace IEXBase\TronAPI\Support\Traits;

use IEXBase\TronAPI\Support\Base58;
use IEXBase\TronAPI\Support\BigInteger;

trait CryptoTrait
{
    /**
     * Конвертируем Tron адрес в Hex
     *
     * @param $str
     * @return string
     */
    public function toHex($str)
    {
        if(mb_strlen($str) == 34 && mb_substr($str, 0, 1) === 'T') {
            return $this->address2HexString($str);
        };

        return $this->stringUtf8toHex($str);
    }

    /**
     * Проверяем адрес перед преобразованием в Hex
     *
     * @param $sHexAddress
     * @return string
     */
    public function address2HexString($sHexAddress)
    {
        if(strlen($sHexAddress) == 42 && mb_strpos($sHexAddress, '41') == 0) {
            return $sHexAddress;
        }
        return $this->base58ToHexString($sHexAddress);
    }

    /**
     * Преобразовываем Base58 в HexString
     *
     * @param $base58
     * @return string
     */
    public function base58ToHexString($base58)
    {
        $decode = Base58::decode($base58);
        $len = strlen($decode) - 4;
        $string = substr($decode, 0, $len);

        return $this->stringUtf8toHex($string);
    }

    /**
     * Преобразовываем строку в Hex
     *
     * @param $sUtf8
     * @return string
     */
    public function stringUtf8toHex($sUtf8)
    {
        return bin2hex($sUtf8);
    }

    /**
     * Преобразовываем в большое значение
     *
     * @param $str
     * @return BigInteger
     */
    public function toBigNumber($str) {
        return new BigInteger($str);
    }

    public function trxToSun($trxCount) {
        return abs($trxCount) * pow(10,6);
    }

    public function sunToTrx($sunCount) {
        return abs($sunCount) / pow(10,6);
    }
}