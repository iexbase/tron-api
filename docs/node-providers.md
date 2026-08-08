# Node and provider configuration

TronAPI separates protocol node roles from hosted vendors. TronGrid is the
default public-network adapter, not an architectural dependency.

## Public networks

```php
use IEXBase\TronAPI\Configuration\NodeConfiguration;
use IEXBase\TronAPI\Enum\NodeRole;
use IEXBase\TronAPI\Enum\Network;
use IEXBase\TronAPI\Tron;

$tron = Tron::create(NodeConfiguration::forNetwork(
    Network::Mainnet,
    tronGridApiKey: 'provider-key',
    connectTimeoutSeconds: 5.0,
    requestTimeoutSeconds: 30.0,
));
```

`Network::Mainnet`, `Network::Shasta`, and `Network::Nile` use their official
TronGrid host as a convenient default for all four roles. The
`TRON-PRO-API-KEY` header is added only when a key is supplied and is redacted
from debugger output.

## Custom, local, or mixed topology

```php
use IEXBase\TronAPI\Configuration\NodeConfiguration;

$configuration = NodeConfiguration::custom(
    fullNodeUri: 'http://10.0.0.10:8090',
    solidityNodeUri: 'http://10.0.0.11:8091',
    indexerUri: 'https://history.example.net',
    jsonRpcUri: 'http://10.0.0.10:8545',
    connectTimeoutSeconds: 3.0,
    requestTimeoutSeconds: 20.0,
    maximumResponseBytes: 33_554_432,
    defaultHeaders: ['X-Application' => 'payments'],
    authenticationHeadersByRole: [
        NodeRole::Indexer->value => ['Authorization' => 'Bearer provider-secret'],
    ],
);
```

Omitted SolidityNode and JSON-RPC URIs fall back to the FullNode URI. The indexer
is optional and has no fallback because a native java-tron node does not expose a
generic historical-index schema.

Base URIs must be absolute HTTP(S) addresses without embedded credentials,
query strings, or fragments. This prevents secret leakage and ambiguous path
resolution. Authentication headers are assigned to one explicit node role, so
an indexer credential is not forwarded to a FullNode, SolidityNode, or JSON-RPC
host.

## Indexed-data adapters

Custom network configurations do not automatically construct a TronGrid
adapter. Supply only the capabilities the application needs:

```php
$tron = Tron::create(
    $configuration,
    accountHistoryProvider: $history,
    eventProvider: $events,
    tokenIndexProvider: $tokens,
);
```

Implementations use these small contracts:

- `AccountHistoryProviderInterface` for account, outer transaction, TRC-20, and
  internal transaction history;
- `EventProviderInterface` for transaction, contract, block, and latest events;
- `TokenIndexProviderInterface` for indexed TRC-20 balances and token metadata.

Pagination is expressed through `PageRequest` and returned as `IndexerPage`.
Provider-specific cursor names and envelope fields remain inside the adapter.
The built-in `TronGridProvider` maps the neutral cursor to TronGrid's
`fingerprint` field.

## Transport and HTTP behavior

`GuzzleTransport` is the default `TransportInterface` implementation. The API
client owns request JSON, role routing, timeouts, role-specific headers, retry
decisions, streamed response size limits, and exception translation. The
default response limit is 32 MiB and can be changed through
`maximumResponseBytes`.

The bounded `RetryPolicy` applies only to requests marked as retryable when they
encounter connection failures, rate limiting, or transient server responses.
Backoff respects server retry guidance where available and uses overflow-safe
integer arithmetic. Broadcast requests are never automatically retried because
an earlier attempt may already have reached the node.

To use another transport:

```php
$tron = Tron::create($configuration, transport: $yourTransport);
```

The custom transport receives a complete immutable `HttpRequest` and must return
an `HttpResponse`; it must not interpret TRON protocol payloads.

## Confirmation level

Methods that can read both latest and confirmed state accept
`ConfirmationLevel::Latest` or `ConfirmationLevel::Confirmed`. The service
selects the correct FullNode or SolidityNode route. Callers do not manually
rewrite `/wallet` paths into `/walletsolidity` paths.

## Low-level routes

`Endpoint` centralizes every built-in route. A new java-tron route can be called
before a typed method is released:

```php
use IEXBase\TronAPI\Api\ApiRequest;
use IEXBase\TronAPI\Enum\HttpMethod;
use IEXBase\TronAPI\Enum\NodeRole;

$response = $tron->api()->request(new ApiRequest(
    NodeRole::FullNode,
    HttpMethod::Post,
    '/wallet/newroute',
    ['visible' => true],
));
```

Prefer an `Endpoint` case when one exists; it carries the declared role, HTTP
method, and path-parameter contract.
