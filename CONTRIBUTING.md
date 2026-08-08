# Contributing to TronAPI

TronAPI 6.0 targets PHP 8.4 and follows the current official TRON protocol
documentation and java-tron behavior.

## Development setup

```bash
composer install
composer check
```

Required PHP extensions are declared in `composer.json`. Do not bypass platform
requirements.

## Code rules

- Add `declare(strict_types=1);` to every PHP source, test, and example file.
- Keep the TronAPI/iEXBase copyright, author, license, and project-link header in
  source and test files; examples intentionally use a shorter executable form.
- Write class, method, function, and inline explanatory comments in English.
- Use exact integer/decimal-string values for money and protocol counters; never
  introduce `float` for TRX or token values.
- Reuse the existing address, hex, integer, ABI, HTTP, endpoint, transaction, and
  stake components instead of copying their logic.
- Keep provider-specific behavior behind an adapter interface.
- Never add a private key to configuration, transport, service, facade, logging,
  exception, or serialization paths.
- Add typed tests and a runnable example for new public workflows.

Names should describe domain responsibility. `Helper` and `Manager` are allowed
when they are genuinely the clearest role; generic transformation-oriented class
names should not replace specific domain terms.

## Protocol changes

For a new java-tron endpoint:

1. verify the route, HTTP method, node role, request fields, and response against
   current java-tron source and official documentation;
2. add one `Endpoint` case rather than repeating path strings;
3. place typed behavior in the smallest existing domain service, or introduce a
   cohesive service when it is a separate protocol domain;
4. add latest/confirmed handling only where the node exposes both routes;
5. add deterministic transport tests for request and response contracts.

For ABI or signing changes, include authoritative interoperability vectors.

## Pull requests

Keep changes focused, document breaking behavior, and include the output of:

```bash
composer validate --strict
composer audit
composer check
```

Tests must not require real funds, production credentials, or a live broadcast.
