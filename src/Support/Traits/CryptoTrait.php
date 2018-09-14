<?php
namespace IEXBase\TronAPI\Support\Traits;

use IEXBase\TronAPI\Support\Base58;
use IEXBase\TronAPI\Support\BigInteger;
use IEXBase\TronAPI\Support\Hash;
use IEXBase\TronAPI\Support\Utils;

trait CryptoTrait
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
        return $this->base58ToHexString($sHexAddress,0,3);
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

        return $this->getBase58CheckAddress($sHexString);
    }

    /**
     * Преобразовываем HexString в Base58
     *
     * @param $string
     * @return string
     */
    public function getBase58CheckAddress($string)
    {
        $string = hex2bin($string);
        $string = $string . substr(Hash::SHA256(Hash::SHA256($string)), 0, 4);

        $base58 =  Base58::encode(Utils::bin2bc($string));
        for ($i = 0; $i < strlen($string); $i++) {
            if ($string[$i] != "\x00") {
                break;
            }

            $base58 = '1' . $base58;
        }
        return $base58;
    }


    /**
     * Преобразовываем Base58 в HexString
     *
     * @param $string
     * @param int $removeLeadingBytes
     * @param int $removeTrailingBytes
     * @param bool $removeCompression
     * @return string
     */
    public function base58ToHexString($string, $removeLeadingBytes = 1, $removeTrailingBytes = 4, $removeCompression = true)
    {
        $string = bin2hex(Utils::bc2bin(Base58::decode($string)));

        //Если конечные байты: Network type
        if ($removeLeadingBytes) {
            $string = substr($string, $removeLeadingBytes * 2);
        }

        //Если конечные байты: Checksum
        if ($removeTrailingBytes) {
            $string = substr($string, 0, -($removeTrailingBytes * 2));
        }

        //Если конечные байты: compressed byte
        if ($removeCompression) {
            $string = substr($string, 0, -2);
        }

        return $string;
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

    public function trxToSun($trxCount) {
        return abs($trxCount) * pow(10,6);
    }

    public function sunToTrx($sunCount) {
        return abs($sunCount) / pow(10,6);
    }
}