<?php
namespace IEXBase\TronAPI\Support;

use InvalidArgumentException;

final class Base58
{
    public const SIGNATURE = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    private const BASE58_LENGTH = '58';

    private const BASE256_LENGTH = '256';

    /**
     * Кодирует переданную целую строку в представление base58.
     *
     * @param string|int $string
     * @return string
     */
    public static function encode($string): string
    {
        $string = (string) $string;
        // Если строка пуста, база также пуста.
        if (empty($string)) {
            return '';
        }

        $bytes = array_values(array_map(function($byte) { return (string) $byte; }, unpack('C*', $string)));
        $base10 = $bytes[0];

        // Преобразование строки в базу 10
        for ($i = 1, $l = count($bytes); $i < $l; $i++) {
            $base10 = bcmul($base10, self::BASE256_LENGTH);
            $base10 = bcadd($base10, $bytes[$i]);
        }

        // Преобразуйте основание 10 в базовую строку 58
        $base58 = '';
        while ($base10 >= self::BASE58_LENGTH) {
            $div = bcdiv($base10, self::BASE58_LENGTH, 0);
            $mod = bcmod($base10, self::BASE58_LENGTH);
            $base58 .= self::SIGNATURE[$mod];
            $base10 = $div;
        }
        if ($base10 > 0) {
            $base58 .= self::SIGNATURE[$base10];
        }

        // База от 10 до базы 58 требует преобразования
        $base58 = strrev($base58);

        // Добавить ведущие нули
        foreach ($bytes as $byte) {
            if ($byte === '0') {
                $base58 = self::SIGNATURE[0] . $base58;
                continue;
            }
            break;
        }

        return $base58;
    }

    /**
     * Декодирует base58 представление большого целого в строку.
     *
     * @param string $base58
     * @return string
     */
    public static function decode(string $base58): string
    {
        if (empty($base58)) {
            return '';
        }

        $indexes = array_flip(str_split(self::SIGNATURE));
        $chars = str_split($base58);

        // Проверьте наличие недопустимых символов в поставляемой строке base58
        foreach ($chars as $char) {
            if (isset($indexes[$char]) === false) {
                throw new InvalidArgumentException('Argument $base58 contains invalid characters. ($char: "'.$char.'" | $base58: "'.$base58.'") ');
            }
        }

        // Преобразовать из base58 в base10
        $decimal = (string) $indexes[$chars[0]];

        for ($i = 1, $l = count($chars); $i < $l; $i++) {
            $decimal = bcmul($decimal, self::BASE58_LENGTH);
            $decimal = bcadd($decimal, (string) $indexes[$chars[$i]]);
        }

        // Преобразовать из базы 10 в базовую 256 (8-разрядный байтовый массив)
        $output = '';
        while ($decimal > 0) {
            $byte = bcmod($decimal, self::BASE256_LENGTH);
            $output = pack('C', $byte) . $output;
            $decimal = bcdiv($decimal, self::BASE256_LENGTH, 0);
        }

        // Теперь нам нужно добавить начальные нули
        foreach ($chars as $char) {
            if ($indexes[$char] === 0) {
                $output = "\x00" . $output;
                continue;
            }
            break;
        }

        return $output;
    }
}