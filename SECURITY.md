# Security policy

## Supported versions

Security fixes are provided for the latest released 6.x version. The 5.x branch
and earlier PHP compatibility lines are unsupported after the 6.0 release.

## Reporting a vulnerability

Do not open a public issue for a vulnerability that could expose private keys,
recovery phrases, provider credentials, signatures, transaction intent, or
funds.

Use GitHub's private vulnerability reporting feature for
[`iexbase/tron-api`](https://github.com/iexbase/tron-api/security/advisories/new).
Include:

- the affected version and PHP runtime;
- the smallest reproducible example;
- the expected and observed security boundary;
- realistic impact and prerequisites;
- any suggested fix, if available.

Never include a real Mainnet private key, recovery phrase, API key, signed
transaction with reusable authorization, or other live secret. Use deterministic
test vectors or a Shasta/private-network key.

The maintainers will acknowledge a complete report, validate its scope, and
coordinate remediation and disclosure. Response times depend on severity and
reproducibility.

## Scope notes

The SDK's intended boundaries are documented in
[docs/security-model.md](docs/security-model.md). Compromised application hosts,
malicious dependencies outside the resolved package graph, exposed environment
variables, insecure backups, incorrect business authorization, and provider
availability are operational risks outside the SDK's direct control.
