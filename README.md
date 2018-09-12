# TronAPI
A PHP API for interacting with the Tron (TRX)

## Install

```bash
> composer require iexbase/tron-api
```

## Example Usage

```php
use IEXBase\TronAPI\Tron;

$tron = new Tron('address', 'private_key');
//alternative way to enter a private key
$tron->setPrivateKey('private_key');


//Example 1
var_dump($tron->getBalance());
//Example 2
var_dump($tron->getBalance('address'))

// Transfer Trx
$transfer = $tron->sendTransaction('from', 'to', 'amount');
var_dump($transfer);

//Generate Address
var_dump($tron->generateAddress());

//Get Last Blocks
var_dump($tron->getLatestBlocks(2));

```

## Donations
Tron: TGtVSXjrk9RByP6ddEEBMg5ttjFMqKYtTP
