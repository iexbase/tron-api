<?php declare(strict_types=1);

namespace IEXBase\TronAPI;

use IEXBase\TronAPI\Support\{Base58Check, BigInteger};

trait TronAwareTrait
{
    /**
     * Преобразовывание из Hex
     *
     * @param $string
     * @return string
     */
    public function fromHex($string)
    {
        if(strlen($string) == 42 && mb_substr($string,0,2) === '41') {
            return $this->hexString2Address($string);
        }

        return $this->hexString2Utf8($string);
    }

    /**
     * Преобразование в Hex
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
        return Base58Check::decode($sHexAddress,0,3);
    }

    /**
     * Проверяем Hex адрес перед преобразованием в Base58
     *
     * @param $sHexString
     * @return string
     */
    public function hexString2Address($sHexString)
    {
        if(strlen($sHexString) < 2 || (strlen($sHexString) & 1) != 0) {
            return '';
        }

        return Base58Check::encode($sHexString,0,false);
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
     * Преобразовываем hex в строку
     *
     * @param $sHexString
     * @return string
     */
    public function hexString2Utf8($sHexString)
    {
        return hex2bin($sHexString);
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

    /**
     * Преобразовываем сумму из формата Tron
     *
     * @param $amount
     * @return float
     */
    public function fromTron($amount): float {
        return (float) bcdiv((string)$amount, (string)1e6, 8);
    }

    /**
     * Преобразовываем сумму в формат Tron
     *
     * @param $double
     * @return int
     */
    public function toTron($double): int {
        return (int) bcmul((string)$double, (string)1e6,0);
    }
}