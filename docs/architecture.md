# TronAPI 6.0 architecture

TronAPI 6.0 is organized around explicit boundaries. Protocol values are
validated once, transport code is isolated from domain services, state-changing
responses are verified locally, and optional indexed-data providers cannot leak
into native node operations.

## Request and transaction flow

```text
Application
    |
    +-- immutable value objects (Address, Amount, Memo, NativeAssetId)
    |
    +-- typed service (TransferService, ContractService, StakeService, ...)
            |
            +-- TransactionFactory + TransactionIntent verification
            |       |
            |       +-- ApiClient -> role-specific node -> GuzzleTransport
            |
            +-- immutable unsigned Transaction
                    |
                    +-- local SignerInterface implementation
                    |
                    +-- TransactionService broadcast -> FullNode
```

Read operations follow the same service → `ApiClient` → role-specific node
route, without the transaction factory.

## Source layout

| Directory | Responsibility |
| --- | --- |
| `src/Api` | Endpoint catalog, requests, responses, envelope and field decoding |
| `src/Asset` | Typed TRC-10 issuance/update request models |
| `src/Configuration` | Node topology, headers, timeouts, bounded retry policy |
| `src/Contract` | ABI types/codecs, calls, deployment, events, token standards |
| `src/Crypto` | secp256k1, local signers, contract addresses, V2 messages, HD wallets |
| `src/Encoding` | The only Base58, Base58Check, hexadecimal, and protobuf primitive implementations |
| `src/Enum` | Network, role, confirmation, resource, sorting, and protocol enums |
| `src/Exception` | Stable domain-specific failure categories |
| `src/Governance` | Proposal parameters and witness votes |
| `src/Http` | Transport contract and Guzzle implementation |
| `src/Indexer` | Provider-neutral history/event/token contracts and TronGrid adapter |
| `src/JsonRpc` | Strict TRON JSON-RPC client and value objects |
| `src/Model` | Immutable decoded account/block/receipt/protocol models |
| `src/Service` | Cohesive public operations grouped by protocol domain |
| `src/Support` | Small shared helpers for exact integers, stake fields, and text |
| `src/Transaction` | Intent, factory, verifier, protobuf wire encoding, cost calculation, permissions, signatures, immutable transaction |
| `src/Value` | Exact addresses, amounts, bytes, memo, native IDs, signatures |

The top-level `Tron` class is a dependency-composition facade. It does not
contain a current address, private key, or mutable network state.

## Data invariants

### Addresses

`Address` stores exactly 21 bytes: `0x41` followed by the 20-byte account ID.
Base58 input is accepted only after double-SHA-256 checksum verification. ABI
and JSON-RPC conversions use an explicit 20-byte representation rather than
guessing from string length at call sites.

### Monetary values

`Amount` stores exact sun as non-negative decimal text. Native-asset atomic
values also remain decimal strings because a TRC-10 ID or balance may exceed
assumptions made by PHP array keys or floating-point arithmetic. Token precision
is applied only when the caller has authoritative token metadata.

### API responses

JSON is decoded with exceptions. Every typed field passes through `DataDecoder`;
numeric strings are not silently converted to floats, and malformed success
envelopes cannot masquerade as empty results. Raw response data remains
available on models where java-tron may add protocol fields between releases.

## Node roles

Every request declares one `NodeRole`:

- `FullNode` for latest state and transaction mutation/broadcast;
- `SolidityNode` for confirmed/solidified state;
- `Indexer` for provider-specific historical discovery;
- `JsonRpc` for the Ethereum-compatible RPC endpoint.

`NodeConfiguration` resolves each role independently. Services never concatenate
base URIs or repeat provider headers. See [Node providers](node-providers.md).

The JSON-RPC role stores a complete endpoint instead of a base URI. This keeps
the hosted TronGrid `/jsonrpc` path separate from java-tron's self-hosted root
path on its dedicated JSON-RPC port.

## Transaction integrity

The node is allowed to construct protocol protobuf JSON, but it is not trusted to
return the transaction the caller requested. A service creates a
`TransactionIntent`, the factory receives the node response, and
`TransactionVerifier` checks the decoded contract, protobuf `Any` type URL, and
every security-critical field. It then deterministically reconstructs the
official `Transaction.raw` protobuf bytes from that approved intent and compares
them byte-for-byte with `raw_data_hex`. Only then is the intent attached to the
immutable `Transaction`.

`TransactionSigner` hashes `raw_data_hex` with SHA-256, signs locally, verifies
that the signature recovers the selected permission address, and returns a new
transaction instance. Imported transactions must be paired with an explicit
intent before signing.

## Contracts

`Abi` is the parsed schema. `AbiType` recursively represents elementary,
array, and tuple forms. `AbiCodec` is the single encoder/decoder for calls,
returns, constructor arguments, errors, and event data. Contract and token
wrappers compose this codec rather than implementing their own padding or
selector rules.

The local ABI retains tuple component schemas for selector construction and
encoding. TRON's on-chain `SmartContract.ABI.Entry.Param` protobuf stores only
`indexed`, `name`, and `type`, so transaction verification compares that exact
protocol projection while retaining the richer local schema. Applications that
need tuple components after loading a deployed contract should keep the
compiler-produced ABI as their authoritative interface.

## Extensibility

- `TransportInterface` replaces Guzzle for tests or specialized networking.
- `SignerInterface` supports hardware, HSM, custody, or remote digest signers.
- Three indexer interfaces replace TronGrid without altering native services.
- `ApiRequest` and `JsonRpcClient::request()` provide strict low-level access to
  newly released routes while typed coverage catches up.

Extensibility points receive already validated domain values whenever possible;
they do not require inheriting from the facade or modifying global state.
