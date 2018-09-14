<?php
namespace IEXBase\TronAPI\Support;

class Base58
{
    /**
     * Кодирует переданную целую строку в представление base58.
     *
     * @param $num
     * @param int $length
     *
     * @return string
     */
    public static function encode($num, $length = 58): string
    {
        return Utils::dec2base($num, $length, '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz');
    }

    /**
     * Декодирует base58 представление большого целого в строку.
     *
     * @param string $addr
     * @param int $length
     *
     * @return string
     */
    public static function decode(string $addr, int $length = 58): string
    {
        return Utils::base2dec($addr, $length, '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz');
    }
}