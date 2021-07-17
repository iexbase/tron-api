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
use IEXBase\TronAPI\Web3\Validators\BlockHashValidator;
use IEXBase\TronAPI\Web3\Formatters\HexFormatter;
use IEXBase\TronAPI\Web3\Formatters\BigNumberFormatter;

class GetUncleCountByBlockHash extends EthMethod
{
    /**
     * validators
     *
     * @var array
     */
    protected $validators = [
        BlockHashValidator::class
    ];

    /**
     * inputFormatters
     *
     * @var array
     */
    protected $inputFormatters = [
        HexFormatter::class
    ];

    /**
     * outputFormatters
     *
     * @var array
     */
    protected $outputFormatters = [
        BigNumberFormatter::class
    ];

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
