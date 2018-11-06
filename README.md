# TRON API
A PHP API for interacting with the Tron Protocol

[![Latest Stable Version](https://poser.pugx.org/iexbase/tron-api/version)](https://packagist.org/packages/iexbase/tron-api)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Build Status](https://api.travis-ci.com/iexbase/tron-api.svg?branch=master)](https://travis-ci.com/iexbase/tron-api)
[![Issues](https://img.shields.io/github/issues/iexbase/tron-api.svg)](https://github.com/iexbase/tron-api/issues)
[![Pull Requests](https://img.shields.io/github/issues-pr/iexbase/tron-api.svg)](https://github.com/iexbase/tron-api/pulls)
[![Contributors](https://img.shields.io/github/contributors/iexbase/tron-api.svg)](https://github.com/iexbase/tron-api/graphs/contributors)

## Install

```bash
> composer require iexbase/tron-api
```
## Requirements

The following versions of PHP are supported by this version.

* PHP 7.1
* PHP 7.2
* PHP 7.3

## Example Usage

```php
use IEXBase\TronAPI\Tron;

$fullNode = new \IEXBase\TronAPI\Provider\HttpProvider('https://api.trongrid.io');
$solidityNode = new \IEXBase\TronAPI\Provider\HttpProvider('https://api.trongrid.io');
$eventServer = new \IEXBase\TronAPI\Provider\HttpProvider('https://api.trongrid.io');

try {
    $tron = new \IEXBase\TronAPI\Tron($fullNode, $solidityNode, $eventServer);
} catch (\IEXBase\TronAPI\Exception\TronException $e) {
    exit($e->getMessage());
}


$this->setAddress('..');
//Balance
$tron->getBalance(null, true);

// Transfer Trx
var_dump($tron->send('to', 1.5));

//Generate Address
var_dump($tron->createAccount());

//Get Last Blocks
var_dump($tron->getLatestBlocks(2));

//Change account name (only once)
var_dump($tron->changeAccountName('address', 'NewName'));
```

## Testing

``` bash
$ vendor/bin/phpunit
```

## Donations
**Tron(TRX)**: TRWBqiqoFZysoAeyR1J35ibuyc8EvhUAoY
