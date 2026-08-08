<?php

declare(strict_types=1);

use IEXBase\TronAPI\Configuration\NodeConfiguration;
use IEXBase\TronAPI\Enum\NodeRole;
use IEXBase\TronAPI\Examples\ExampleEnvironment;
use IEXBase\TronAPI\Tron;

require_once __DIR__ . '/ExampleEnvironment.php';

$fullNodeUri = ExampleEnvironment::required('TRON_FULL_NODE');
$configuration = NodeConfiguration::custom(
    $fullNodeUri,
    ExampleEnvironment::value('TRON_SOLIDITY_NODE', $fullNodeUri),
    ExampleEnvironment::required('TRON_INDEXER'),
    ExampleEnvironment::required('TRON_JSON_RPC'),
    authenticationHeadersByRole: [
        NodeRole::Indexer->value => [
            'Authorization' => ExampleEnvironment::required('TRON_INDEXER_AUTHORIZATION'),
        ],
    ],
);
$tron = Tron::create($configuration);

ExampleEnvironment::output([
    'full_node' => $configuration->baseUri(NodeRole::FullNode),
    'solidity_node' => $configuration->baseUri(NodeRole::SolidityNode),
    'indexer' => $configuration->baseUri(NodeRole::Indexer),
    'json_rpc' => $configuration->baseUri(NodeRole::JsonRpc),
    'native_services_are_indexer_independent' => $tron->accountHistory() === null,
]);
