# Migrating from TronAPI 5.x to 6.0

TronAPI 6.0 is a deliberate breaking rewrite for PHP 8.4. There is no deprecated
compatibility layer because retaining the old mutable facade, ambiguous amounts,
and provider coupling would also retain their failure modes.

## Platform changes

| 5.x | 6.0 |
| --- | --- |
| PHP 7.x/8.x compatibility varied by dependency | PHP 8.4 only |
| Legacy HTTP provider | Guzzle 8 transport behind `TransportInterface` |
| Mixed integer/float amounts | Exact `Amount` and decimal-string atomic values |
| Global address/private-key state | Address and signer passed explicitly |
| Node-side account creation | Local secp256k1 or BIP-39/BIP-32 wallet |
| Native methods mixed with TronGrid v1 routes | Native services plus optional indexer interfaces |
| Contract behavior spread across facade/classes | One recursive ABI codec and typed contract/token wrappers |

Run Composer normally. Do not use `--ignore-platform-reqs`; missing extensions
must be fixed in the runtime image.

```bash
composer require iexbase/tron-api:^6.0
```

## Client construction

Old:

```php
$fullNode = new HttpProvider('https://api.trongrid.io');
$solidityNode = new HttpProvider('https://api.trongrid.io');
$eventServer = new HttpProvider('https://api.trongrid.io');
$tron = new Tron($fullNode, $solidityNode, $eventServer);
```

New:

```php
use IEXBase\TronAPI\Configuration\NodeConfiguration;
use IEXBase\TronAPI\Enum\Network;
use IEXBase\TronAPI\Tron;

$tron = Tron::create(NodeConfiguration::forNetwork(
    Network::Mainnet,
    tronGridApiKey: 'provider-key',
));
```

Use `NodeConfiguration::custom()` when any role uses a private or alternative
provider. See [Node providers](node-providers.md).

## Address state

Old code often called `setAddress()` and then omitted the account argument from
later calls. Version 6.0 has no current address:

```php
use IEXBase\TronAPI\Value\Address;

$address = Address::fromString('T...');
$account = $tron->accounts()->get($address);
$resources = $tron->accounts()->resources($address);
```

This makes concurrent workers, queues, and multi-account requests independent.

## Amounts

Do not pass TRX as `float`:

```php
use IEXBase\TronAPI\Value\Amount;

$amount = Amount::fromDecimal('1.250001');
```

`Amount::fromAtomic()` accepts exact sun as `int|string`. TRC-10 and contract
integer values remain exact atomic strings until authoritative decimals are
known.

## Account generation and signing

Old node-assisted account generation and private-key methods are removed.

```php
use IEXBase\TronAPI\Crypto\LocalPrivateKeySigner;
use IEXBase\TronAPI\Crypto\Wallet\HierarchicalWallet;

$singleKey = LocalPrivateKeySigner::generate();
$wallet = HierarchicalWallet::generate(wordCount: 24);
$firstSigner = $wallet->signer("m/44'/195'/0'/0/0");
```

Back up an explicitly exported private key or phrase securely. Never return it
from a web endpoint.

## Transfers

Old:

```php
$tron->setAddress($owner);
$result = $tron->send($recipient, 1.5);
```

New:

```php
$transaction = $tron->transfers()->createTrxTransfer(
    $signer->address(),
    Address::fromString($recipient),
    Amount::fromDecimal('1.5'),
);

$signed = $tron->transactions()->appendSignature($transaction, $signer);
$result = $tron->transactions()->broadcast($signed);
```

Construction, signing, and broadcasting are separate so applications can apply
authorization, custody, multisignature, and audit controls between them.

## Account and block reads

| 5.x call | 6.0 call |
| --- | --- |
| `$tron->getBalance()` | `$tron->accounts()->get($address)?->balance` |
| `$tron->getAccount()` | `$tron->accounts()->get($address)` |
| `$tron->getLatestBlocks($count)` | `$tron->blocks()->latestCount($count)` |
| `$tron->getTransaction($id)` | `$tron->transactions()->find($id)` |
| `$tron->getTransactionInfo($id)` | `$tron->transactions()->receipt($id)` |
| `$tron->isConnected()` | `$tron->network()->health()` |

Methods that support both states accept `ConfirmationLevel`; confirmed reads
default to SolidityNode routes where appropriate.

## Contracts

The old `contract($address)` entry point and bundled `trc20.json` file are
removed. Parse the authoritative ABI and create a wrapper explicitly:

```php
use IEXBase\TronAPI\Contract\Abi;
use IEXBase\TronAPI\Contract\ContractCall;
use IEXBase\TronAPI\Value\Address;

$abiJson = file_get_contents('/secure/path/contract-abi.json');
if ($abiJson === false) {
    throw new RuntimeException('The contract ABI cannot be read.');
}

$contractAddress = Address::fromString('T...');
$caller = Address::fromString('T...');
$abi = Abi::fromJson($abiJson);
$call = new ContractCall(
    $caller,
    $contractAddress,
    $abi->function('name()'),
    abi: $abi,
);
$result = $tron->contracts()->read($call);
$token = $tron->contracts()->trc20($contractAddress, $caller);
```

Use `ContractService::read()` for constant functions,
`createTransaction()` for state changes, and `deploy()` for bytecode plus
constructor arguments. Every mutation requires an explicit fee limit.

## TRC-20 and other token standards

`Trc20Contract` exposes exact `name`, `symbol`, `decimals`, `totalSupply`,
`balanceOf`, `allowance`, `transfer`, `approve`, and `transferFrom` operations.
TRC-721 and TRC-1155 have separate typed wrappers; token IDs are `int|string` so
large IDs do not overflow.

## Events and transaction history

Native node reads do not implicitly call TronGrid. Public network profiles expose
the default adapter through `$tron->events()`, `$tron->accountHistory()`, and
`$tron->tokenIndex()`. Custom deployments must inject implementations of the
corresponding interfaces.

Replace offset loops and ad-hoc fingerprints with `PageRequest` and the cursor
returned by `IndexerPage`.

## Exceptions

Catch the narrow type when recovery differs, or `TronApiException` at the
application boundary:

```php
use IEXBase\TronAPI\Exception\RateLimitException;
use IEXBase\TronAPI\Exception\TronApiException;

try {
    // SDK operation
} catch (RateLimitException $exception) {
    // Schedule a bounded retry according to application policy.
} catch (TronApiException $exception) {
    // Report a safe domain error.
}
```

The hierarchy separates configuration, validation, cryptography, transport,
HTTP status, node rejection, response decoding, transaction, and contract
execution failures.

## Removed classes

The following 5.x types have no aliases in 6.0:

- `HttpProvider` and `HttpProviderInterface`;
- `TronManager`, `TronInterface`, and `TronAwareTrait`;
- `TransactionBuilder`;
- `TronAddress`;
- `TRC20Contract`;
- legacy `Support` cryptography/integer utility classes;
- Tronscan/universal traits and obsolete exception classes.

Use the service/value/interface replacements described above. Static aliases are
not added because they would make two competing APIs and duplicate maintenance.
