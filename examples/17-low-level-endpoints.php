<?php

declare(strict_types=1);

use IEXBase\TronAPI\Api\ApiRequest;
use IEXBase\TronAPI\Api\Endpoint;
use IEXBase\TronAPI\Enum\HttpMethod;
use IEXBase\TronAPI\Enum\NodeRole;
use IEXBase\TronAPI\Examples\ExampleEnvironment;

require_once __DIR__ . '/ExampleEnvironment.php';

$tron = ExampleEnvironment::tron();
$response = $tron->api()->request(Endpoint::GetNodeInfo->request());
$futureEndpoint = new ApiRequest(
    NodeRole::FullNode,
    HttpMethod::Post,
    '/wallet/future-route',
    ['visible' => true],
);

ExampleEnvironment::output([
    'node_info' => $response->data(),
    'future_endpoint_request' => [
        'role' => $futureEndpoint->nodeRole->value,
        'method' => $futureEndpoint->method->value,
        'path' => $futureEndpoint->path,
        'parameters' => $futureEndpoint->parameters,
    ],
    'shielded_endpoint_examples' => [
        Endpoint::ScanShieldedTrc20NotesByIncomingViewingKey->value,
        Endpoint::CreateShieldedContractParameters->value,
    ],
]);
