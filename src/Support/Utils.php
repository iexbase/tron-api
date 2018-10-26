<?php
namespace IEXBase\TronAPI\Support;

class Utils
{
    /**
     * Link verification
     *
     * @param $url
     * @return bool
     */
    public static function isValidUrl($url) :bool {
        return (bool)parse_url($url);
    }

    /**
     * Check if the string is a 16th notation
     *
     * @param $str
     * @return bool
     */
    public static function isHex($str) : bool {
        return is_string($str) && !is_nan(intval($str,16));
    }

    /**
     * Check whether the passed parameter is an array
     *
     * @param $array
     * @return bool
     */
    public static function isArray($array) : bool {
        return is_array($array);
    }
}
