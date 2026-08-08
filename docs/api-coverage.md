# API coverage

The `Endpoint` enum is the source of truth for built-in native and indexed HTTP
routes. It declares each route's HTTP method, node role, path parameters, and GET
exceptions. Typed services consume those cases so path strings and
FullNode/SolidityNode selection are not duplicated.

The catalog tracks the current java-tron FullNode and SolidityNode HTTP surface.
Routes not suitable for a high-level security abstraction, especially shielded
key operations, remain available through their `Endpoint` case and the generic
`ApiClient`.

## Typed native services

### Accounts and permissions

- Latest/confirmed account by address and account ID.
- Balance, current FullNode resources/Bandwidth, exact TRC-10 account balances.
- Account activation, name update, and permanent account ID.
- Owner, active, and witness permissions.
- Permission operation bitmap construction and account permission updates.

### Transactions and blocks

- Latest/confirmed blocks by number, ID/hash, count, and range.
- Latest/confirmed transactions, receipts, block receipts, and block counts.
- Pending pool IDs, count, and transaction lookup.
- Intent verification, deterministic local signatures, duplicate prevention,
  multisignature weight/approved lists, broadcast, and broadcast-hex route.
- Official protobuf-size Bandwidth estimation and maximum Bandwidth burn from
  the live `getTransactionFee` chain parameter.
- Block balance changes and receipt-extension routes through `Endpoint`.

### TRX and TRC-10

- Exact TRX transfer transactions.
- TRC-10 list/page, issuer, ID, name, duplicate-name results, issuance,
  transfer, participation, unfreeze, and metadata update.
- Numeric token IDs remain strings and account balances remain exact atomic
  values.

### Stake 2.0

- Freeze, unfreeze, cancel pending unfreezes, withdraw expired amounts.
- Delegate and undelegate Bandwidth/Energy.
- Available unfreeze count, withdrawable amount, delegatable maximum.
- Delegation records and account indexes from latest/confirmed state.

Stake 1.0 freeze/unfreeze/delegation routes are isolated in
`LegacyStakeService` so new integrations do not use them accidentally.

### Contracts

- Contract definition and runtime information.
- Constant calls from latest or confirmed state.
- Simulation, Energy estimation, verified trigger transactions.
- Deployment, constructor encoding, fee/call/token values, origin Energy,
  resource percentage, library addresses, and ABI.
- Caller Energy percentage, origin Energy limit, and ABI clearing.
- Return, revert, custom-error, receipt log, anonymous event, and indexed topic
  decoding.
- Complete function calldata resolution and argument decoding by ABI selector.

### Token wrappers

- TRC-20 metadata, supply, balances, allowances, transfers, approvals, and
  delegated transfers.
- TRC-721 interface detection, metadata, ownership, URI, approvals, regular and
  safe transfers with optional data.
- TRC-1155 interface detection, single/batch balances, URI, operator approval,
  and single/batch safe transfers.

### Witnesses and governance

- Current/page Super Representative lists.
- Candidate creation, URL update, exact votes.
- Brokerage, reward reads, brokerage update, reward withdrawal.
- Proposal list/page/find, creation, approval/removal, deletion.
- Next maintenance timestamp.

### Network

- Latest/confirmed node information.
- Peer list and synchronization health/lag.
- Chain parameters without lossy numeric conversion.
- Energy/Bandwidth price histories, memo fee, confirmed burned TRX.

### Native exchanges and market

- Exchange list/page/find, create, liquidity inject/withdraw, and trade.
- Native market create/cancel, account indexes, order lookup/list, pairs, and
  prices.

## ABI coverage

`AbiCodec` supports the Solidity ABI data types used by TVM:

- `uint<M>` and `int<M>` with exact bounds;
- `bool`, `address`, `string`, `bytes`, and `bytes<M>`;
- fixed and dynamic arrays of static or dynamic values;
- nested tuples and tuple arrays using component schemas;
- overloaded functions selected by canonical signature;
- constructors, function returns, events, and custom errors.

Selectors/topics use Keccak-256. Address values use 20-byte ABI form and are
restored to checksum-verified TRON addresses at the boundary.

## JSON-RPC coverage

`JsonRpcClient` provides typed methods for the complete currently documented
TRON JSON-RPC set:

- `eth_getBalance`, `eth_blockNumber`, block/transaction/receipt queries;
- `eth_call`, `eth_getCode`, `eth_getStorageAt`, `eth_estimateGas`,
  `eth_gasPrice`;
- `eth_newFilter`, `eth_newBlockFilter`, filter changes/logs/uninstall, and
  `eth_getLogs`;
- `eth_chainId`, `eth_coinbase`, `eth_protocolVersion`, `eth_syncing`;
- `net_listening`, `net_peerCount`, `net_version`, `web3_clientVersion`, and
  `web3_sha3`;
- TRON's `buildTransaction` extension.

`Quantity`, `BlockTag`, `ByteString`, and `LogFilter` enforce JSON-RPC hex and
address conventions. `request()` remains available for a newly introduced
method while retaining envelope/version/request-ID validation.

## Indexed-data coverage

The default `TronGridProvider` implements:

- account lookup;
- account outer, TRC-20, and internal transactions;
- transaction internal transactions and events;
- contract, block, and latest-block events;
- account TRC-20 balances;
- TRC-20 metadata and contract token discovery.

Additional indexed asset/statistics routes exist in `Endpoint` for low-level
access. Indexer schemas are vendor APIs and are not represented as java-tron
native capabilities.

## Shielded routes

The endpoint catalog includes the currently registered spending/viewing-key
derivation, shielded-address, proof-parameter, authorization-signature, and
shielded TRC-20 scan/spent/input routes. Legacy shielded-TRX routes whose servlet
registrations are disabled in current java-tron are deliberately excluded.

They intentionally do not receive a convenience service that encourages users
to send spending keys to a public host. Use an owned node and a separately
audited privacy workflow. See [Security model](security-model.md).

## Forward compatibility

For a future native route:

```php
$response = $tron->api()->request(new ApiRequest(
    NodeRole::FullNode,
    HttpMethod::Post,
    '/wallet/future-route',
    ['visible' => true],
));
```

This fallback is intentionally explicit: callers choose role, method, path, and
payload, and receive a strict `ApiResponse` instead of an unvalidated mixed HTTP
result.
