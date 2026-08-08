# Security model

TronAPI assumes that remote nodes, hosted indexers, logs, and application input
can be untrusted. It protects local intent and key material but cannot secure a
compromised host process or recover a disclosed private key/recovery phrase.

## Private keys stay outside transport objects

No `ApiClient`, HTTP request, configuration, service, or `Tron` facade accepts a
private key. `LocalPrivateKeySigner` holds one validated scalar in memory and
implements only address discovery and 32-byte digest signing.

Private key generation is local. The removed 5.x node-side account generator is
not used because a hosted API must never generate or receive a spending key.

`LocalPrivateKeySigner`, `MnemonicPhrase`, `ExtendedKey`, and
`HierarchicalWallet` reject PHP serialization. Debug output redacts private keys,
recovery phrases, entropy, passphrases, and BIP-32 chain codes. Configuration
debug output redacts authentication headers.

Explicit export methods exist for secure backup and custody integration. Their
return values are sensitive and must not be logged, returned from web handlers,
stored unencrypted, or placed in exception messages.

## Build, verify, sign, broadcast

State-changing workflows are intentionally separated:

1. A typed service records the requested operation as a `TransactionIntent`.
2. A FullNode builds the protocol transaction.
3. `TransactionVerifier` checks the displayed contract and `Any` type URL against
   the intent.
4. The verifier reconstructs the official protobuf `Transaction.raw` bytes and
   requires an exact match with `raw_data_hex`.
5. A signer signs `SHA-256(raw_data_hex)` locally.
6. Signature recovery proves that the signer matches the intended permission.
7. The application explicitly calls `broadcast()`.

This prevents a compromised or misconfigured node from silently replacing the
recipient, amount, contract, token, permission, memo, or fee limit before local
signing. It also prevents a node from displaying approved JSON while returning
different validly hashed protobuf bytes for the signer.

An imported transaction does not have a trusted intent. The SDK refuses to sign
it until the application provides and approves one. Do not construct an intent
from the same untrusted payload; derive it from the application's original
business request.

## Multisignature permissions

Permission IDs and operation bitmaps are exact protocol values. Each appended
signature is recovered locally, duplicate signer addresses are rejected, and
the transaction remains immutable. `getsignweight` and `getapprovedlist` are
available for node-side permission state, but their responses do not replace
local signature and intent checks.

## Hierarchical wallets

The standard address path is:

```text
m / 44' / 195' / account' / change / index
```

`195` is the registered TRON coin type. The recovery phrase uses BIP-39 and the
key tree uses BIP-32. An optional BIP-39 passphrase creates a different valid
wallet; there is no way to detect a mistyped passphrase later.

An account-level xpub can safely derive `change/index` public children, but it
reveals the complete address history for that branch. It must not be treated as
anonymous metadata. Public-only keys cannot derive hardened children or create a
signer.

Keep recovery phrases and xprv values offline. Prefer an HSM or hardware wallet
implementation of `SignerInterface` for production treasury keys.

## Message Signature V2

`MessageSigner` implements the current TronWeb-compatible scheme:

```text
Keccak-256("\x19TRON Signed Message:\n" || decimal_byte_length || message_bytes)
```

The V2 wire signature stores the recovery byte as 27 or 28. Transaction wire
signatures store it as 0 or 1; `Signature::fromMessageHex()` and
`Signature::toMessageHex()` keep the two formats explicit.

A valid signature proves control of an address for exactly those bytes. It does
not prove user identity, authorization scope, freshness, or intent. Login
challenges should include a domain, action, random nonce, audience, chain, and
short expiration, and must be single-use server-side.

## ABI and contracts

- Every state-changing contract call requires an explicit positive fee limit.
- Addresses and integers are range-checked before ABI encoding.
- Recursive ABI widths, aggregate value traversal, dynamic offsets, array
  counts, and byte padding are validated before allocation or decoding.
- JSON-RPC log filters enforce block-range, address, topic-position, and topic
  alternative limits before transport.
- Constant-call failure envelopes and revert data raise typed exceptions.
- Contract metadata fetched from a node is not assumed to be trusted business
  configuration.
- Token decimals are metadata; never use a guessed default for money movement.

## HTTP and provider secrets

Authentication belongs in role-specific `authenticationHeadersByRole`, not in
base-URI credentials or query parameters. Header names and values are validated,
and a credential configured for one role is not sent to another node host.
Timeouts, bounded retries, and streamed response limits constrain remote
failures; redirects are disabled so provider credentials cannot be forwarded to
an unexpected origin. Error exceptions may contain response context but cannot
contain local key material because transport objects never hold it.

Broadcast and JSON-RPC requests are non-retryable by default. Broadcast outcome
is ambiguous after a lost response, while JSON-RPC filter creation and change
polling mutate node-side cursor state. Native and indexed reads/builds retain
bounded retry behavior.

## Shielded APIs

java-tron exposes key-derivation, proof-parameter, authorization-signature, and
shielded TRC-20 endpoints through `Endpoint`/`ApiRequest`. TronAPI does not
pretend that sending a spending key to a remote hosted node is safe. Use a node
under your control and perform privacy-sensitive proof/key operations in an
audited environment. Disabled legacy shielded-TRX servlet routes are not listed
as supported endpoints.

## Application responsibilities

- Secure the PHP host, dependency supply chain, environment, backups, and logs.
- Validate business authorization before transaction construction.
- Apply rate limits and replay protection to signed-message authentication.
- Use correct network/node endpoints and independently monitor broadcasts.
- Test with Shasta, Nile, or a private network before Mainnet.
- Never enable example broadcasting in an environment holding real funds.
