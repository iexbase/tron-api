<?php

declare(strict_types=1);

use IEXBase\TronAPI\Examples\ExampleEnvironment;

require_once __DIR__ . '/ExampleEnvironment.php';

$tron = ExampleEnvironment::tron();
$caller = ExampleEnvironment::address('TRON_ADDRESS');
$trc20 = $tron->contracts()->trc20(
    ExampleEnvironment::address('TRON_TRC20_CONTRACT'),
    $caller,
);
$nft = $tron->contracts()->trc721(
    ExampleEnvironment::address('TRON_TRC721_CONTRACT'),
    $caller,
);
$multiToken = $tron->contracts()->trc1155(
    ExampleEnvironment::address('TRON_TRC1155_CONTRACT'),
    $caller,
);
$trc721TokenId = ExampleEnvironment::value('TRON_TRC721_TOKEN_ID', '1');
$trc1155TokenId = ExampleEnvironment::value('TRON_TRC1155_TOKEN_ID', '1');

ExampleEnvironment::output([
    'trc20' => [
        'contract' => $trc20->contractAddress,
        'name' => $trc20->name(),
        'symbol' => $trc20->symbol(),
        'decimals' => $trc20->decimals(),
        'total_supply' => $trc20->totalSupply(),
        'balance' => $trc20->balanceOf($caller),
    ],
    'trc721' => [
        'contract' => $nft->contractAddress,
        'name' => $nft->name(),
        'symbol' => $nft->symbol(),
        'balance' => $nft->balanceOf($caller),
        'owner' => $nft->ownerOf($trc721TokenId),
        'token_uri' => $nft->tokenUri($trc721TokenId),
    ],
    'trc1155' => [
        'contract' => $multiToken->contractAddress,
        'balance' => $multiToken->balanceOf($caller, $trc1155TokenId),
        'uri' => $multiToken->uri($trc1155TokenId),
    ],
]);
