<?php

/**
 * This file is part of web3.php package.
 *
 * (c) Kuan-Cheng,Lai <alk03073135@gmail.com>
 *
 * @author Peter Lai <alk03073135@gmail.com>
 * @license MIT
 */

namespace IEXBase\TronAPI\Web3\Methods\Eth;

use InvalidArgumentException;
use IEXBase\TronAPI\Web3\Methods\EthMethod;
use IEXBase\TronAPI\Web3\Validators\TagValidator;
use IEXBase\TronAPI\Web3\Validators\QuantityValidator;
use IEXBase\TronAPI\Web3\Validators\CallValidator;
use IEXBase\TronAPI\Web3\Formatters\TransactionFormatter;
use IEXBase\TronAPI\Web3\Formatters\OptionalQuantityFormatter;

class Call extends EthMethod
{
    /**
     * validators
     *
     * @var array
     */
    protected $validators = [
        CallValidator::class, [
            TagValidator::class, QuantityValidator::class
        ]
    ];

    /**
     * inputFormatters
     *
     * @var array
     */
    protected $inputFormatters = [
        TransactionFormatter::class, OptionalQuantityFormatter::class
    ];

    /**
     * outputFormatters
     *
     * @var array
     */
    protected $outputFormatters = [];

    /**
     * defaultValues
     *
     * @var array
     */
    protected $defaultValues = [
        1 => 'latest'
    ];

    /**
     * construct
     *
     * @param string $method
     * @param array $arguments
     * @return void
     */
    // public function __construct($method='', $arguments=[])
    // {
    //     parent::__construct($method, $arguments);
    // }
}
