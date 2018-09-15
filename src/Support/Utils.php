<?php
namespace IEXBase\TronAPI\Support;

class Utils
{
    /**
     * Проверка ссылков
     *
     * @param $url
     * @return bool
     */
    public static function isValidUrl($url) :bool {
        return (bool)parse_url($url);
    }

    /**
     * Проверить, является ли строка 16-ной записью числа
     *
     * @param $str
     * @return bool
     */
    public static function isHex($str) : bool {
        return is_string($str) && !is_nan(intval($str,16));
    }

    /**
     * Проверить, является ли переданный параметр массивом
     *
     * @param $array
     * @return bool
     */
    public static function isArray($array) : bool {
        return is_array($array);
    }
}
