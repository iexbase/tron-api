<?php
namespace IEXBase\TronAPI\Support;


class Hash
{
    /**
     * Хеширование SHA-256
     *
     * @param $data
     * @param bool $raw
     * @return string
     */
    public static function SHA256($data, $raw = true)
    {
        return hash('sha256', $data, $raw);
    }

    /**
     * Двойное хеширование SHA-256
     *
     * @param $data
     * @return string
     */
    public static function sha256d($data)
    {
        return hash('sha256', hash('sha256', $data, true), true);
    }

    /**
     * Хеширование RIPEMD160
     *
     * @param $data
     * @param bool $raw
     * @return string
     */
    public static function RIPEMD160($data, $raw = true)
    {
        return hash('ripemd160', $data, $raw);
    }
}