<?php
include_once '../vendor/autoload.php';

use IEXBase\TronAPI\Tron;

$tron = new Tron();
$tron->setPrivateKey('...');


/**
 * check multi balances
 *
 * $address = [
 *   ['address', 'isFromTron'],
 *   ['address', 'isFromTron'],
 * ]
*/

//address one -> TRWBqiqoFZysoAeyR1J35ibuyc8EvhUAoY
$addresses = [
    ['address one', true],
    ['address two', true],
    ['address three', false],
];

//isValid (tron address) - default false
$check = $tron->balances($addresses);
var_dump($check);


/**
 * send one to many
 *
 * $address = [
 *   ['to address', 'amount float'],
 *   ['to address', 'amount float'],
 * ]
 *
 * toAddress format: TRWBqiqoFZysoAeyR1J35ibuyc8EvhUAoY
 */


$toArray = [
    ['TRWBqiqoFZysoAeyR1J35ibuyc8EvhUAoY', 0.1],
    ['TRWBqiqoFZysoAeyR1J35ibuyc8EvhUAoY', 0.2],
    ['other address', 0.001]
];

//default: $this->setPrivateKey();
$send = $tron->sendOneToMany('from_address', $toArray, 'private_key alt');
var_dump($send);
