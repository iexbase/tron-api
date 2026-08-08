# Changelog

All notable changes to TronAPI are documented in this file.

## [6.0.0] - Unreleased

### Added

- PHP 8.4 strict typed architecture with domain services and immutable values.
- Guzzle 7/8 transport, explicit FullNode/SolidityNode/indexer/JSON-RPC
  topology, role-specific credentials, streamed response limits, bounded retry
  behavior, timeouts, and typed HTTP/node errors.
- Exact TRX and native-asset values without floating-point conversion.
- Complete current native route catalog with latest/confirmed role metadata.
- Verified transaction intents, local secp256k1 signing, immutable multisignature,
  permission bitmaps, protobuf wire verification, Bandwidth estimation,
  broadcast, and receipt workflows.
- Recursive Solidity ABI encoding/decoding, deployment, calls, Energy estimation,
  calldata/revert/error/event decoding, and TRC-20/721/1155 wrappers.
- Complete Stake 2.0 service plus isolated legacy staking compatibility.
- Account, block, TRC-10, witness, governance, exchange, market, network, and
  JSON-RPC services.
- Provider-neutral indexed history/event/token interfaces with a replaceable
  TronGrid v1 adapter.
- BIP-39 recovery phrases, BIP-32 xprv/xpub, TRON BIP-44 derivation, watch-only
  addresses, and TronWeb-compatible Message Signature V2.
- Numbered examples, migration/security/provider/API documentation, PHPStan
  maximum-level analysis, PHPUnit 13 tests, and GitHub Actions CI.

### Changed

- Rebuilt the complete `src` tree and public API for TronAPI 6.0.
- TronGrid is now only a default public host/indexer adapter; native services are
  vendor-independent.
- Private keys are passed only through signer boundaries and are never stored on
  the facade or HTTP client.
- Broadcast requests are never retried automatically after an ambiguous
  transport failure.

### Removed

- PHP 7.x/8.0-8.3 compatibility and obsolete dependency constraints.
- Mutable address/private-key facade state and node-side key generation.
- Legacy provider, manager, transaction builder, duplicate cryptographic helper,
  Tronscan/universal trait, bundled ABI JSON, Travis CI, and Jekyll artifacts.
- All 5.x compatibility aliases.
