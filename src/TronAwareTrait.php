<?php declare(strict_types=1);

namespace IEXBase\TronAPI;

use IEXBase\TronAPI\Support\{Base58Check, BigInteger, Keccak};

trait TronAwareTrait
{
    /**
     * Convert from Hex
     *
     * @param $string
     * @return string
     */
    public static function _fromHex(string $string): string{
        if(strlen($string) == 42 && mb_substr($string,0,2) === '41') {
            return self::hexString2Address($string);
        }

        return self::hexString2Utf8($string);

    }

    /**
     * Convert to Hex
     *
     * @param string $string
     * @return string
     */
    public static function _toHex(string $string): string
    {
        if(mb_strlen($string) == 34 && mb_substr($string, 0, 1) === 'T') {
            return self::address2HexString($string);
        };

        return self::stringUtf8toHex($string);
    }

    /**
     * Check the address before converting to Hex
     *
     * @param $sHexAddress
     * @return string
     */
    public static function _address2HexString(string $sHexAddress): string
    {
        if(strlen($sHexAddress) == 42 && mb_strpos($sHexAddress, '41') == 0) {
            return $sHexAddress;
        }
        return Base58Check::decode($sHexAddress,0,3);
    }

    /**
     * Check Hex address before converting to Base58
     *
     * @param $sHexString
     * @return string
     */
    public static function _hexString2Address(string $sHexString): string
    {
        if(!ctype_xdigit($sHexString)) {
            return $sHexString;
        }

        if(strlen($sHexString) < 2 || (strlen($sHexString) & 1) != 0) {
            return '';
        }

        return Base58Check::encode($sHexString,0,false);
    }

    /**
     * Convert string to hex
     *
     * @param $sUtf8
     * @return string
     */
    public static function _stringUtf8toHex(string $sUtf8): string
    {
        return bin2hex($sUtf8);
    }

    /**
     * Convert hex to string
     *
     * @param $sHexString
     * @return string
     */
    public static function _hexString2Utf8(string $sHexString): string
    {
        return hex2bin($sHexString);
    }

    /**
     * Convert to great value
     *
     * @param $str
     * @return BigInteger
     */
    public static function _toBigNumber($str):BigIntger {
        return new BigInteger($str);
    }

    /**
     * Convert trx to float
     *
     * @param $amount
     * @return float
     */
    public static function _fromTron($amount): float {
        return (float) bcdiv((string)$amount, (string)1e6, 8);
    }

    /**
     * Convert float to trx format
     *
     * @param $double
     * @return int
     */
    public static function _toTron($double): int {
        return (int) bcmul((string)$double, (string)1e6,0);
    }

    /**
     * Convert to SHA3
     *
     * @param $string
     * @param bool $prefix
     * @return string
     * @throws \Exception
     */
    public function _sha3(string $string, $prefix = true): string
    {
        return ($prefix ? '0x' : ''). Keccak::hash($string, 256);
    }

    /**
     * Convert from Hex
     *
     * @param $string
     * @return string
     */
    public function fromHex(string $string):string
    {
        return self::_fromHex($string);
    }

    /**
     * Convert to Hex
     *
     * @param $str
     * @return string
     */
    public function toHex(string $str): string
    {
        return self::_toHex($str);
    }

    /**
     * Check the address before converting to Hex
     *
     * @param $sHexAddress
     * @return string
     */
    public function address2HexString(string $sHexAddress): string
    {
        return self::_address2HexString($sHexAddress);
    }

    /**
     * Check Hex address before converting to Base58
     *
     * @param $sHexString
     * @return string
     */
    public function hexString2Address(string $sHexString): string
    {
        return self::_hexString2Address($sHexString);
    }

    /**
     * Convert string to hex
     *
     * @param $sUtf8
     * @return string
     */
    public function stringUtf8toHex(string $sUtf8): string
    {
        return self::_stringUtf8toHex($sUtf8);
    }

    /**
     * Convert hex to string
     *
     * @param $sHexString
     * @return string
     */
    public function hexString2Utf8(string $sHexString): string
    {
        return self::_hexString2Utf8($sHexString);
    }

    /**
     * Convert to great value
     *
     * @param $str
     * @return BigInteger
     */
    public function toBigNumber($str): BigInteger {
        return self::toBigNumber($str);
    }

    /**
     * Convert trx to float
     *
     * @param $amount
     * @return float
     */
    public function fromTron($amount): float {
        return self::_fromTron($amount);
    }

    /**
     * Convert float to trx format
     *
     * @param $double
     * @return int
     */
    public function toTron($double): int {
        return self::_toTron($double);
    }

    /**
     * Convert to SHA3
     *
     * @param $string
     * @param bool $prefix
     * @return string
     * @throws \Exception
     */
    public function sha3($string, $prefix = true): string
    {
        return self::_sha3($string, $prefix);
    }
}