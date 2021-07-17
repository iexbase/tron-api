<?php

/**
 * This file is part of web3.php package.
 *
 * (c) Kuan-Cheng,Lai <alk03073135@gmail.com>
 *
 * @author Peter Lai <alk03073135@gmail.com>
 * @license MIT
 */

namespace IEXBase\TronAPI\Web3\Formatters;

use InvalidArgumentException;
use IEXBase\TronAPI\Web3\Utils;
use IEXBase\TronAPI\Web3\Formatters\IFormatter;

class HexFormatter implements IFormatter
{
    /**
     * format
     *
     * @param mixed $value
     * @return string
     */
    public static function format($value)
    {
        $value = Utils::toString($value);
        $value = mb_strtolower($value);

        if (Utils::isZeroPrefixed($value)) {
            return $value;
        } else {
            $value = Utils::toHex($value, true);
        }
        return $value;
    }
}
