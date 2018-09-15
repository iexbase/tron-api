<h1 align="center">
  TRON API
  <br>
</h1>
<h4 align="center">
  A PHP API for interacting with the Tron (TRX)
</h4>

## Install

```bash
> composer require iexbase/tron-api
```

## Example Usage

```php
use IEXBase\TronAPI\Tron;

$tron = new Tron();

//Change node address
$fullNode = new HttpProvider('https://api.trongrid.io:8090');
$solidityNode = new HttpProvider('https://api.trongrid.io:8091');
$tron - new Tron($fullNode, $solidityNode);

//alternative way to enter a private key
$tron->setPrivateKey('private_key');

//Balance
$tron->fromTron($tron->getBalance());

// Transfer Trx
var_dump($tron->sendTransaction('from', 'to', 'amount'));

//Generate Address
var_dump($tron->generateAddress());

//Get Last Blocks
var_dump($tron->getLatestBlocks(2));

//Change account name (only once)
var_dump($tron->changeAccountName('address', 'NewName'));

```

## Donations
**Tron(TRX)**: TRWBqiqoFZysoAeyR1J35ibuyc8EvhUAoY
