<?php

declare(strict_types=1);

/**
 * TronAPI 6.0
 *
 * Copyright (c) 2018-2026 iEXBase.
 *
 * @author  Shamsudin Serderov <steein.shamsudin@gmail.com>
 * @license https://github.com/iexbase/tron-api/blob/master/LICENSE MIT License
 * @link    https://github.com/iexbase/tron-api
 */

namespace IEXBase\TronAPI\Configuration;

use IEXBase\TronAPI\Enum\Network;
use IEXBase\TronAPI\Enum\NodeRole;
use IEXBase\TronAPI\Exception\ConfigurationException;

/**
 * Stores explicit node topology, provider headers, timeouts, and retry behaviour.
 *
 * Each data source has its own base URI so an application can combine a local
 * FullNode, a separate SolidityNode, any compatible indexer, and JSON-RPC.
 * Public network factories route all roles through the official hosted URI.
 */
final class NodeConfiguration
{
    /** @var array<string, string|null> */
    private readonly array $baseUris;

    /** @var array<string, string> */
    private readonly array $defaultHeaders;

    /** @var array<string, array<string, string>> */
    private readonly array $authenticationHeadersByRole;

    /**
     * Creates a fully validated immutable-style node configuration.
     *
     * @param Network                   $network Selected public or custom network.
     * @param string                    $fullNodeUri FullNode base URI.
     * @param string                    $solidityNodeUri SolidityNode base URI.
     * @param string|null               $indexerUri Optional indexed-data provider base URI.
     * @param string                    $jsonRpcUri JSON-RPC node base URI.
     * @param float                     $connectTimeoutSeconds TCP/TLS connection timeout.
     * @param float                     $requestTimeoutSeconds Complete request timeout.
     * @param int                       $maximumResponseBytes Maximum decoded HTTP response body size.
     * @param RetryPolicy               $retryPolicy Bounded retry policy.
     * @param array<array-key, mixed> $defaultHeaders Untrusted additional non-sensitive headers.
     * @param array<array-key, mixed> $authenticationHeadersByRole Untrusted secret headers keyed by node-role value.
     */
    private function __construct(
        public readonly Network $network,
        string $fullNodeUri,
        string $solidityNodeUri,
        ?string $indexerUri,
        string $jsonRpcUri,
        public readonly float $connectTimeoutSeconds,
        public readonly float $requestTimeoutSeconds,
        public readonly int $maximumResponseBytes,
        public readonly RetryPolicy $retryPolicy,
        array $defaultHeaders,
        #[\SensitiveParameter]
        array $authenticationHeadersByRole,
    ) {
        if (!is_finite($connectTimeoutSeconds)
            || !is_finite($requestTimeoutSeconds)
            || $connectTimeoutSeconds <= 0.0
            || $requestTimeoutSeconds <= 0.0
        ) {
            throw new ConfigurationException('HTTP timeouts must be finite positive numbers of seconds.');
        }

        if ($connectTimeoutSeconds > $requestTimeoutSeconds) {
            throw new ConfigurationException('The connection timeout cannot exceed the request timeout.');
        }
        if ($maximumResponseBytes < 1) {
            throw new ConfigurationException('The maximum HTTP response size must be a positive byte count.');
        }

        $this->defaultHeaders = self::checkedHeaders($defaultHeaders, 'Default');
        $checkedAuthenticationHeaders = [];
        foreach ($authenticationHeadersByRole as $role => $headers) {
            if (!is_string($role) || NodeRole::tryFrom($role) === null) {
                throw new ConfigurationException('Authentication headers must be keyed by a valid node-role value.');
            }
            if (!is_array($headers)) {
                throw new ConfigurationException('Authentication headers for a node role must be an array.');
            }
            $checkedAuthenticationHeaders[$role] = self::checkedHeaders(
                $headers,
                sprintf('%s authentication', $role),
                true,
            );
        }
        $this->authenticationHeadersByRole = $checkedAuthenticationHeaders;

        $this->baseUris = [
            NodeRole::FullNode->value => self::validateBaseUri($fullNodeUri),
            NodeRole::SolidityNode->value => self::validateBaseUri($solidityNodeUri),
            NodeRole::Indexer->value => $indexerUri === null ? null : self::validateBaseUri($indexerUri),
            NodeRole::JsonRpc->value => self::validateBaseUri($jsonRpcUri),
        ];
    }

    /**
     * Creates a configuration for an official public TRON network.
     *
     * @param Network              $network Mainnet, Shasta, or Nile.
     * @param string|null          $tronGridApiKey Optional key for the default TronGrid endpoint.
     * @param float                $connectTimeoutSeconds Connection timeout.
     * @param float                $requestTimeoutSeconds Complete request timeout.
     * @param int                  $maximumResponseBytes Maximum HTTP response body size.
     * @param RetryPolicy|null     $retryPolicy Optional custom retry policy.
     * @param array<string,string> $defaultHeaders Additional request headers.
     */
    public static function forNetwork(
        Network $network,
        #[\SensitiveParameter]
        ?string $tronGridApiKey = null,
        float $connectTimeoutSeconds = 5.0,
        float $requestTimeoutSeconds = 30.0,
        int $maximumResponseBytes = 33_554_432,
        ?RetryPolicy $retryPolicy = null,
        array $defaultHeaders = [],
    ): self {
        if ($network === Network::Custom) {
            throw new ConfigurationException('Use NodeConfiguration::custom() for custom or local nodes.');
        }

        if ($tronGridApiKey !== null && trim($tronGridApiKey) === '') {
            throw new ConfigurationException('A TronGrid API key cannot be empty.');
        }

        $baseUri = $network->defaultHttpUri();

        $authenticationHeadersByRole = [];
        if ($tronGridApiKey !== null) {
            foreach (NodeRole::cases() as $role) {
                $authenticationHeadersByRole[$role->value] = ['TRON-PRO-API-KEY' => $tronGridApiKey];
            }
        }

        return new self(
            $network,
            $baseUri,
            $baseUri,
            $baseUri,
            $baseUri,
            $connectTimeoutSeconds,
            $requestTimeoutSeconds,
            $maximumResponseBytes,
            $retryPolicy ?? new RetryPolicy(),
            $defaultHeaders,
            $authenticationHeadersByRole,
        );
    }

    /**
     * Creates a configuration for local, private, or mixed node deployments.
     *
     * @param string               $fullNodeUri Required latest-state node URI.
     * @param string|null          $solidityNodeUri Solidified-state URI, or FullNode URI when omitted.
     * @param string|null          $indexerUri Optional indexed-data provider URI.
     * @param string|null          $jsonRpcUri JSON-RPC URI, or FullNode URI when omitted.
     * @param float                $connectTimeoutSeconds Connection timeout.
     * @param float                $requestTimeoutSeconds Complete request timeout.
     * @param int                  $maximumResponseBytes Maximum HTTP response body size.
     * @param RetryPolicy|null     $retryPolicy Optional custom retry policy.
     * @param array<string,string> $defaultHeaders Additional request headers.
     * @param array<string,array<string,string>> $authenticationHeadersByRole Secret headers keyed by node-role value.
     */
    public static function custom(
        string $fullNodeUri,
        ?string $solidityNodeUri = null,
        ?string $indexerUri = null,
        ?string $jsonRpcUri = null,
        float $connectTimeoutSeconds = 5.0,
        float $requestTimeoutSeconds = 30.0,
        int $maximumResponseBytes = 33_554_432,
        ?RetryPolicy $retryPolicy = null,
        array $defaultHeaders = [],
        #[\SensitiveParameter]
        array $authenticationHeadersByRole = [],
    ): self {
        return new self(
            Network::Custom,
            $fullNodeUri,
            $solidityNodeUri ?? $fullNodeUri,
            $indexerUri,
            $jsonRpcUri ?? $fullNodeUri,
            $connectTimeoutSeconds,
            $requestTimeoutSeconds,
            $maximumResponseBytes,
            $retryPolicy ?? new RetryPolicy(),
            $defaultHeaders,
            $authenticationHeadersByRole,
        );
    }

    /**
     * Returns the configured base URI for one node role.
     */
    public function baseUri(NodeRole $role): string
    {
        $uri = $this->baseUris[$role->value];
        if ($uri === null) {
            throw new ConfigurationException(sprintf('No %s endpoint is configured.', $role->value));
        }

        return $uri;
    }

    /**
     * Returns whether an optional role has a configured endpoint.
     */
    public function hasEndpoint(NodeRole $role): bool
    {
        return $this->baseUris[$role->value] !== null;
    }

    /**
     * Returns all headers that should be attached to an outgoing API request.
     *
     * @return array<string, string>
     */
    public function requestHeaders(NodeRole $role): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'User-Agent' => 'iexbase-tron-api/6.0.0',
            ...$this->defaultHeaders,
            ...($this->authenticationHeadersByRole[$role->value] ?? []),
        ];
    }

    /**
     * Redacts every provider authentication header during debugger inspection.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'network' => $this->network,
            'baseUris' => $this->baseUris,
            'connectTimeoutSeconds' => $this->connectTimeoutSeconds,
            'requestTimeoutSeconds' => $this->requestTimeoutSeconds,
            'maximumResponseBytes' => $this->maximumResponseBytes,
            'retryPolicy' => $this->retryPolicy,
            'defaultHeaders' => $this->defaultHeaders,
            'authenticationHeadersByRole' => array_map(
                static fn (array $headers): array => array_fill_keys(array_keys($headers), '[REDACTED]'),
                $this->authenticationHeadersByRole,
            ),
        ];
    }

    /**
     * Validates a base URI and strips only its trailing slash.
     */
    private static function validateBaseUri(string $uri): string
    {
        $parts = parse_url($uri);
        if ($parts === false
            || !isset($parts['scheme'], $parts['host'])
            || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            throw new ConfigurationException('A node base URI must be an absolute HTTP(S) URI without credentials, query, or fragment.');
        }

        return rtrim($uri, '/');
    }

    /**
     * Validates strict HTTP header names and non-empty single-line values.
     *
     * @param array<array-key, mixed> $headers Untrusted header map.
     * @return array<string, string>
     */
    private static function checkedHeaders(
        array $headers,
        string $description,
        bool $allowSensitive = false,
    ): array {
        $checked = [];
        foreach ($headers as $name => $value) {
            if (!is_string($name)
                || preg_match("/^[!#$%&'*+\\-.^_`|~0-9A-Za-z]+$/D", $name) !== 1
                || !is_string($value)
                || $value === ''
                || preg_match('/[\r\n]/', $value) === 1
            ) {
                throw new ConfigurationException(sprintf(
                    '%s HTTP headers must use valid names and non-empty single-line string values.',
                    $description,
                ));
            }
            if (!$allowSensitive
                && preg_match('/auth|api[-_]?key|token|secret|cookie/i', $name) === 1
            ) {
                throw new ConfigurationException(sprintf(
                    '%s HTTP headers cannot contain credentials; assign them through authenticationHeadersByRole.',
                    $description,
                ));
            }
            $checked[$name] = $value;
        }

        return $checked;
    }
}
