# TronAPI 6.0

[![Latest Stable Version](https://poser.pugx.org/iexbase/tron-api/version)](https://packagist.org/packages/iexbase/tron-api)
[![CI](https://github.com/iexbase/tron-api/actions/workflows/ci.yml/badge.svg)](https://github.com/iexbase/tron-api/actions/workflows/ci.yml)
[![License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.4-777BB4.svg)](https://www.php.net/releases/8.4/)

TronAPI 6.0 is a strictly typed PHP 8.4 SDK for TRON FullNode, SolidityNode,
JSON-RPC, smart contracts, tokens, staking, governance, indexed data, local
signing, and hierarchical wallets.

Version 6.0 is a complete architecture rewrite. It is not source-compatible
with 5.x. The new API deliberately removes global address/private-key state,
floating-point asset values, duplicated route logic, and any native-service
dependency on TronGrid.

The default dependency resolution uses Guzzle 8. Applications whose ecosystem
still requires the current Guzzle 7 line can use it without replacing the
TronAPI transport API.

## Requirements

- PHP 8.4 on a 64-bit platform.
- Extensions: `bcmath`, `ctype`, `curl`, `filter`, `gmp`, `hash`, `json`, and
  `mbstring`.
- Composer 2.

Install the package without bypassing platform checks:

```bash
composer require iexbase/tron-api:^6.0
```

## Quick start

```php
<?php

declare(strict_types=1);

use IEXBase\TronAPI\Configuration\NodeConfiguration;
use IEXBase\TronAPI\Enum\Network;
use IEXBase\TronAPI\Tron;
use IEXBase\TronAPI\Value\Address;

$tron = Tron::create(NodeConfiguration::forNetwork(
    Network::Shasta,
    getenv('TRON_API_KEY') ?: null,
));

$account = $tron->accounts()->get(
    Address::fromBase58('T...'),
);

echo $account?->balance->decimalValue() ?? 'Account is not activated', PHP_EOL;
```

The public-network profiles use TronGrid as the default hosted endpoint and
indexer adapter. The SDK core is not coupled to it. A local FullNode, a separate
SolidityNode, another JSON-RPC server, and any custom indexer can be composed
independently:

```php
use IEXBase\TronAPI\Configuration\NodeConfiguration;
use IEXBase\TronAPI\Tron;

$configuration = NodeConfiguration::custom(
    fullNodeUri: 'http://full-node.internal:8090',
    solidityNodeUri: 'http://solidity-node.internal:8091',
    indexerUri: 'https://indexer.example.com',
    jsonRpcUri: 'http://full-node.internal:8545',
);

$tron = Tron::create(
    $configuration,
    accountHistoryProvider: $yourHistoryProvider,
    eventProvider: $yourEventProvider,
    tokenIndexProvider: $yourTokenProvider,
);
```

`jsonRpcUri` is the complete endpoint. Use the dedicated port root for a
self-hosted java-tron node (for example `http://full-node.internal:8545`) or the
provider's exact path when it exposes JSON-RPC through a gateway.

See [Node providers](docs/node-providers.md) for role routing and custom adapter
contracts.

## Exact values and addresses

TRX values are never represented by `float`. `Amount` stores an exact
non-negative decimal count of sun and converts decimal text without precision
loss:

```php
use IEXBase\TronAPI\Value\Amount;

$amount = Amount::fromDecimal('12.345678');

echo $amount->atomicValue();  // 12345678
echo $amount->decimalValue(); // 12.345678
```

`Address` accepts checksum-verified Base58Check, 21-byte TRON hex, or explicit
20-byte ABI/EVM hex. Conversion never relies on an external node.

## Safe transaction lifecycle

A state-changing service builds a transaction and approves its local
`TransactionIntent` only after the returned node payload is checked against the
requested owner, recipient/contract, amount, token ID, permission, memo, and fee
limit. The SDK also reconstructs the official protobuf `Transaction.raw` bytes
and requires an exact match with `raw_data_hex`, so displayed JSON cannot hide a
different payload. Signing occurs locally and broadcasting is a separate
explicit action.

```php
use IEXBase\TronAPI\Crypto\LocalPrivateKeySigner;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\Amount;
use IEXBase\TronAPI\Value\Memo;

$privateKey = getenv('TRON_PRIVATE_KEY');
if (!is_string($privateKey) || $privateKey === '') {
    throw new RuntimeException('TRON_PRIVATE_KEY is required.');
}

$signer = new LocalPrivateKeySigner($privateKey);
$transaction = $tron->transfers()->createTrxTransfer(
    $signer->address(),
    Address::fromBase58('T...'),
    Amount::fromDecimal('1.25'),
    Memo::fromText('Invoice 1042'),
);

$signed = $tron->transactions()->appendSignature($transaction, $signer);
$result = $tron->transactions()->broadcast($signed);
```

Imported transactions have no trusted local intent and therefore require an
explicit `TransactionIntent` before signing. Permission-aware multisignature
workflows use the same verifier and append signatures immutably.

Before signing, `estimateBandwidth()` calculates the official protobuf size for
the expected signature count. `maximumBandwidthBurn()` combines it with
`$tron->network()->bandwidthUnitPrice()`; the result deliberately excludes
available staked/free resources, Energy, account activation, memo, multisignature,
and other operation-specific fixed fees.

## Hierarchical wallets and message signatures

The wallet module implements BIP-39, BIP-32, the TRON BIP-44 path
`m/44'/195'/account'/change/index`, xprv/xpub import/export, and public-only
non-hardened address derivation.

```php
use IEXBase\TronAPI\Crypto\MessageSigner;
use IEXBase\TronAPI\Crypto\Wallet\ExtendedKey;
use IEXBase\TronAPI\Crypto\Wallet\HierarchicalWallet;
use IEXBase\TronAPI\Crypto\Wallet\MnemonicPhrase;

$mnemonic = getenv('TRON_MNEMONIC');
if (!is_string($mnemonic) || $mnemonic === '') {
    throw new RuntimeException('TRON_MNEMONIC is required.');
}

$phrase = MnemonicPhrase::parse($mnemonic);
$passphrase = getenv('TRON_PASSPHRASE');
$wallet = HierarchicalWallet::fromMnemonic($phrase, is_string($passphrase) ? $passphrase : '');
$account = $wallet->account("m/44'/195'/0'/0/0");

$accountXpub = $wallet->accountExtendedPublicKey(0);
$watchAddress = ExtendedKey::fromBase58($accountXpub)
    ->derivePath('0/1')
    ->address();

$signature = MessageSigner::sign('Sign in to Example', $account->signer());
$isValid = MessageSigner::verify(
    'Sign in to Example',
    $signature->toMessageHex(),
    $account->address(),
);
```

Recovery phrases, passphrases, private keys, xprv chain codes, and provider API
keys are redacted from debugger output. Private signers and wallet objects reject
serialization. See [Security model](docs/security-model.md).

## Smart contracts and tokens

The ABI subsystem supports overloaded functions, constructors, nested tuples,
fixed/dynamic arrays, signed and unsigned integers, bytes, strings, addresses,
return values, custom errors, revert reasons, anonymous events, and indexed log
topics. Solidity aliases are expanded to their canonical types before selector
hashing, and recursive encoded data is bounded before allocation. Complete
calldata can be resolved by its four-byte selector and decoded with
`ContractService::decodeFunctionCall()`.

Typed contract services provide:

- constant calls through confirmed or latest state;
- Energy estimation and state-changing trigger transactions;
- deployment with constructor arguments, fee limit, call value, token value,
  origin Energy limit, and resource percentage;
- ABI replacement/removal and contract resource settings;
- TRC-20, TRC-721, and TRC-1155 wrappers;
- receipt event decoding and provider-neutral indexed event queries.

Runnable examples are documented in [examples/README.md](examples/README.md),
including local key generation, balances, contract reads/writes, deployment,
token standards, ABI events, and revert decoding.

## Service map

| Entry point | Responsibility |
| --- | --- |
| `$tron->accounts()` | Accounts, resources, activation, names, IDs, exact TRC-10 balances |
| `$tron->blocks()` | Latest, confirmed, hash/number/range, transaction counts |
| `$tron->transactions()` | Lookup, local signing, intent verification, multisig, broadcast, receipts |
| `$tron->transfers()` | Verified TRX transfers |
| `$tron->assets()` | TRC-10 issue/update/query/transfer/participation |
| `$tron->stake()` | Stake 2.0 freeze, unfreeze, cancel, delegate, reclaim, resource queries |
| `$tron->legacyStake()` | Explicit Stake 1.0 compatibility operations |
| `$tron->contracts()` | ABI calls, estimation, writes, deployment, settings, tokens, events |
| `$tron->permissions()` | Owner/active/witness permissions and operation bitmaps |
| `$tron->network()` | Node state, peers, chain parameters, prices, synchronization |
| `$tron->witnesses()` | Super Representatives, votes, rewards, brokerage, candidacy |
| `$tron->governance()` | Proposals, approvals, cancellation, maintenance schedule |
| `$tron->exchanges()` | Protocol-native exchange operations |
| `$tron->market()` | Native market orders and order-book queries |
| `$tron->jsonRpc()` | Complete documented TRON Ethereum-compatible JSON-RPC surface |
| `$tron->accountHistory()` | Optional provider-neutral indexed account history |
| `$tron->events()` | Optional provider-neutral indexed event discovery |
| `$tron->tokenIndex()` | Optional provider-neutral indexed token discovery |
| `$tron->api()` | Strict role-aware access to any new native endpoint |

The complete coverage map is documented in [API coverage](docs/api-coverage.md).

## Examples

Every example is executable and defaults to Shasta for read operations. Any
broadcast requires `TRON_BROADCAST=1`; transaction examples read private keys
only from the environment and never send them to a node. Examples 01 and 18
print newly generated local private/public values for direct inspection.

```bash
TRON_NETWORK=shasta \
TRON_API_KEY=... \
TRON_ADDRESS=T... \
php examples/02-account-block-and-network.php
```

The numbered examples cover:

1. local account/private-key generation, addresses, and exact amounts;
2. accounts, blocks, and network information;
3. custom node topology;
4. build, verify, sign, and optionally broadcast;
5. multisignature permissions;
6. TRC-10;
7. Stake 2.0;
8. contract reads;
9. contract writes;
10. contract deployment;
11. ABI function calldata and event decoding;
12. TRC-20, TRC-721, and TRC-1155;
13. provider-neutral indexed history;
14. JSON-RPC;
15. witnesses and governance;
16. native exchanges and market orders;
17. low-level/current-future endpoint access and active shielded TRC-20 routes;
18. BIP-39/BIP-32/xprv/xpub wallets and local key output;
19. Message Signature V2.

## Documentation

- [Architecture](docs/architecture.md)
- [API coverage](docs/api-coverage.md)
- [Node providers](docs/node-providers.md)
- [Security model](docs/security-model.md)
- [Migration from 5.x](docs/migration-6.0.md)

The protocol reference used by this project is the official
[TRON developer documentation](https://developers.tron.network/), current
[java-tron HTTP API implementation](https://github.com/tronprotocol/java-tron),
and [TronWeb](https://github.com/tronprotocol/tronweb) interoperability behavior.

## Development and verification

```bash
composer validate --strict
composer audit
composer check
```

`composer check` runs syntax validation, PHPStan at maximum level, and the full
PHPUnit suite. Network-independent tests use deterministic transports and
official protocol vectors; they never broadcast funds.

## License

TronAPI is released under the [MIT License](LICENSE).

Copyright (c) 2018-2026 iEXBase.
