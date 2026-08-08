# TronAPI 6.0 examples

The examples are executable PHP files built against the public TronAPI 6.0 API.
Run `composer install` in the project root before using them.

Public-network examples use Shasta by default. Select another network and add a
TronGrid key when required:

```bash
export TRON_NETWORK=shasta
export TRON_API_KEY=your-api-key
```

`TRON_NETWORK` accepts `mainnet`, `shasta`, or `nile`. Native services do not
require TronGrid: example 03 shows independently configured FullNode,
SolidityNode, indexer, and JSON-RPC endpoints.

## Local key examples

Generate a standalone account, print its private/public keys, convert its
address, and work with exact TRX amounts:

```bash
php examples/01-addresses-and-amounts.php
```

Generate a BIP-39/BIP-32 wallet, print its recovery phrase, private key, xprv,
xpub, and watch-only address:

```bash
php examples/18-hierarchical-wallet.php
```

Set `TRON_MNEMONIC` and optional `TRON_PASSPHRASE` to restore an existing
derivation instead of generating a new one.

## Balances

Read an activated account's exact TRX balance, TRC-10 atomic balances,
Bandwidth, Energy resources, node state, and current network prices:

```bash
TRON_ADDRESS=T... php examples/02-account-block-and-network.php
```

Read complete TRC-20 metadata and an account balance:

```bash
TRON_ADDRESS=T... \
TRON_CONTRACT=T... \
php examples/08-read-trc20-token.php
```

Set `TRON_SPENDER` to include the account-to-spender allowance in the same
result.

Read TRC-20, TRC-721, and TRC-1155 balances and metadata together:

```bash
TRON_ADDRESS=T... \
TRON_TRC20_CONTRACT=T... \
TRON_TRC721_CONTRACT=T... \
TRON_TRC721_TOKEN_ID=1 \
TRON_TRC1155_CONTRACT=T... \
TRON_TRC1155_TOKEN_ID=1 \
php examples/12-work-with-tokens.php
```

Only the contract variables for the token standards being queried are required.
TRC-10 metadata, precision-aware balance, and transfer construction are shown
in example 06.

## Getting transactions

Read confirmed native, TRC-20, and internal transactions for an account through
the configured provider-neutral history adapter:

```bash
TRON_ADDRESS=T... php examples/13-get-transactions.php
```

Set `TRON_PAGE_LIMIT` and the endpoint-specific `TRON_TRANSACTION_CURSOR`,
`TRON_TRC20_CURSOR`, or `TRON_INTERNAL_CURSOR` values to continue a result set.
Opaque cursors returned by one endpoint must not be reused for another endpoint.

Set a transaction ID to retrieve the confirmed native transaction, its receipt,
and its indexed internal transactions in the same result:

```bash
TRON_TRANSACTION_ID=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef \
php examples/13-get-transactions.php
```

## Sending transactions

Transaction examples read private keys only from environment variables. They
always build and sign locally, but do not broadcast unless
`TRON_BROADCAST=1` is explicitly set.

```bash
TRON_PRIVATE_KEY=... \
TRON_RECIPIENT=T... \
TRON_AMOUNT=1.25 \
php examples/04-send-trx-transaction.php
```

To broadcast the same verified transaction:

```bash
TRON_BROADCAST=1 \
TRON_PRIVATE_KEY=... \
TRON_RECIPIENT=T... \
TRON_AMOUNT=1.25 \
php examples/04-send-trx-transaction.php
```

A transport failure during broadcast is intentionally not retried. The printed
transaction ID can be queried before deciding whether another broadcast is
required.

Build, sign, and optionally broadcast a TRC-20 token transfer with an exact
token amount and an explicit TRX fee limit:

```bash
TRON_BROADCAST=1 \
TRON_PRIVATE_KEY=... \
TRON_CONTRACT=T... \
TRON_RECIPIENT=T... \
TRON_TOKEN_AMOUNT=25.5 \
TRON_FEE_LIMIT=100 \
php examples/09-send-trc20-transaction.php
```

## Complete example map

| File | Demonstrates |
| --- | --- |
| `01-addresses-and-amounts.php` | Random local account, private/public keys, Base58/hex addresses, exact TRX/sun |
| `02-account-block-and-network.php` | TRX/TRC-10 balances, account resources, blocks, node and chain prices |
| `03-custom-node-topology.php` | Independent FullNode, SolidityNode, indexer, JSON-RPC and role-specific authentication |
| `04-send-trx-transaction.php` | Verified TRX transfer, memo, Bandwidth estimate, local signature, optional broadcast |
| `05-multisignature.php` | Permission-aware multi-signature collection and weight |
| `06-trc10.php` | TRC-10 metadata, exact balance, amount precision and transfer |
| `07-stake-2.php` | Stake 2.0 resource queries and Energy staking |
| `08-read-trc20-token.php` | TRC-20 metadata, total supply, balance and optional allowance |
| `09-send-trc20-transaction.php` | TRC-20 transfer with exact token amount, fee limit, memo and optional broadcast |
| `10-contract-deployment.php` | ABI/bytecode deployment and predicted contract address |
| `11-abi-and-events.php` | Function calldata and confirmed receipt-event decoding |
| `12-work-with-tokens.php` | Optional TRC-20, TRC-721 and TRC-1155 metadata, ownership and balances |
| `13-get-transactions.php` | Native, TRC-20 and internal history, cursor pagination, transaction and receipt lookup |
| `14-json-rpc.php` | TRON JSON-RPC chain, block, balance and code reads |
| `15-witnesses-and-governance.php` | Super Representatives, proposals and maintenance time |
| `16-native-exchange-and-market.php` | Native exchange list, market pairs, orders and prices |
| `17-low-level-endpoints.php` | Typed low-level access and active shielded TRC-20 routes |
| `18-hierarchical-wallet.php` | BIP-39/BIP-32/BIP-44, private keys, xprv/xpub and watch-only derivation |
| `19-message-signature.php` | Local key generation plus TronWeb-compatible Message Signature V2 sign/recover/verify |

Each script validates required values and throws a descriptive exception when
an environment variable, address, response field, or node capability is
invalid. Amounts are strings or exact value objects; examples never use floats
for TRX or token quantities.
