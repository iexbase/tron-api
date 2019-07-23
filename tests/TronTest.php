<?php
namespace IEXBase\TronAPI\Tests;

use IEXBase\TronAPI\Provider\HttpProvider;
use IEXBase\TronAPI\Tron;
use PHPUnit\Framework\TestCase;

class TronTest extends TestCase
{
    const ADDRESS_HEX = '41928c9af0651632157ef27a2cf17ca72c575a4d21';
    const ADDRESS_BASE58 = 'TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY';
    const FULL_NODE_API = 'https://api.trongrid.io';
    const SOLIDITY_NODE_API = 'https://api.trongrid.io';



    public function test_isValidProvider()
    {
        $tron = new Tron(new HttpProvider(self::FULL_NODE_API), new HttpProvider(self::SOLIDITY_NODE_API));
        $provider = new HttpProvider(self::FULL_NODE_API);

        $this->assertEquals($tron->isValidProvider($provider), true);
    }

    public function test_setAddress()
    {
        $tron = new Tron(new HttpProvider(self::FULL_NODE_API), new HttpProvider(self::SOLIDITY_NODE_API));
        $tron->setAddress(self::ADDRESS_HEX);

        $this->assertEquals($tron->getAddress()['hex'],self::ADDRESS_HEX);
        $this->assertEquals($tron->getAddress()['base58'], self::ADDRESS_BASE58);
    }

    public function test_setDefaultBlock()
    {
        $tron = new Tron(new HttpProvider(self::FULL_NODE_API),new HttpProvider(self::SOLIDITY_NODE_API));
        $tron->setDefaultBlock(1);
        $this->assertEquals($tron->getDefaultBlock(), 1);

        $tron->setDefaultBlock(-2);
        $this->assertEquals($tron->getDefaultBlock(),2);

        $tron->setDefaultBlock(0);
        $this->assertEquals($tron->getDefaultBlock(),0);

        $tron->setDefaultBlock();
        $this->assertEquals($tron->getDefaultBlock(),false);

        $tron->setDefaultBlock('latest');
        $this->assertEquals($tron->getDefaultBlock(),'latest');
    }
}