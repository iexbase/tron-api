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
$tron->setPrivateKey('private_key');


//Example 1
var_dump($tron->getBalance());
//Example 2
var_dump($tron->getBalance('address'))

```

## Donations
Tron: TGtVSXjrk9RByP6ddEEBMg5ttjFMqKYtTP
