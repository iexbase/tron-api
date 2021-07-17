<?php

/**
 * This file is part of web3.php package.
 *
 * (c) Kuan-Cheng,Lai <alk03073135@gmail.com>
 *
 * @author Peter Lai <alk03073135@gmail.com>
 * @license MIT
 */

namespace IEXBase\TronAPI\Web3\Validators;

use IEXBase\TronAPI\Web3\Validators\IValidator;
use IEXBase\TronAPI\Web3\Utils;

class TagValidator implements IValidator
{
    /**
     * validate
     *
     * @param string $value
     * @return bool
     */
    public static function validate($value)
    {
        $value = Utils::toString($value);
        $tags = [
            'latest', 'earliest', 'pending'
        ];

        return in_array($value, $tags);
    }
}
