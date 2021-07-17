<?php

/**
 * This file is part of web3.php package.
 *
 * (c) Kuan-Cheng,Lai <alk03073135@gmail.com>
 *
 * @author Peter Lai <alk03073135@gmail.com>
 * @license MIT
 */

namespace IEXBase\TronAPI\Web3\Methods\Personal;

use IEXBase\TronAPI\Web3\Methods\EthMethod;
use IEXBase\TronAPI\Web3\Validators\AddressValidator;
use IEXBase\TronAPI\Web3\Formatters\AddressFormatter;

class LockAccount extends EthMethod
{
    /**
     * validators
     *
     * @var array
     */
    protected $validators = [
        AddressValidator::class
    ];

    /**
     * inputFormatters
     *
     * @var array
     */
    protected $inputFormatters = [
        AddressFormatter::class
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
    protected $defaultValues = [];

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

