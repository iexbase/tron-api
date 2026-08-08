<?php

declare(strict_types=1);

use IEXBase\TronAPI\Examples\ExampleEnvironment;
use IEXBase\TronAPI\Value\Address;

require_once __DIR__ . '/ExampleEnvironment.php';

$tron = ExampleEnvironment::tron();
$caller = ExampleEnvironment::address('TRON_ADDRESS');
$trc20Address = ExampleEnvironment::optional('TRON_TRC20_CONTRACT');
$trc721Address = ExampleEnvironment::optional('TRON_TRC721_CONTRACT');
$trc1155Address = ExampleEnvironment::optional('TRON_TRC1155_CONTRACT');

if ($trc20Address === null && $trc721Address === null && $trc1155Address === null) {
    throw new \RuntimeException(
        'Set TRON_TRC20_CONTRACT, TRON_TRC721_CONTRACT, or TRON_TRC1155_CONTRACT.',
    );
}

$result = ['account' => $caller];

if ($trc20Address !== null) {
    $trc20 = $tron->contracts()->trc20(Address::fromString($trc20Address), $caller);
    $result['trc20'] = [
        'contract' => $trc20->contractAddress,
        'name' => $trc20->name(),
        'symbol' => $trc20->symbol(),
        'decimals' => $trc20->decimals(),
        'total_supply' => $trc20->totalSupply(),
        'balance' => $trc20->balanceOf($caller),
    ];
}

if ($trc721Address !== null) {
    $tokenId = ExampleEnvironment::value('TRON_TRC721_TOKEN_ID', '1');
    $trc721 = $tron->contracts()->trc721(Address::fromString($trc721Address), $caller);
    $result['trc721'] = [
        'contract' => $trc721->contractAddress,
        'name' => $trc721->name(),
        'symbol' => $trc721->symbol(),
        'balance' => $trc721->balanceOf($caller),
        'token_id' => $tokenId,
        'owner' => $trc721->ownerOf($tokenId),
        'token_uri' => $trc721->tokenUri($tokenId),
    ];
}

if ($trc1155Address !== null) {
    $tokenId = ExampleEnvironment::value('TRON_TRC1155_TOKEN_ID', '1');
    $trc1155 = $tron->contracts()->trc1155(Address::fromString($trc1155Address), $caller);
    $result['trc1155'] = [
        'contract' => $trc1155->contractAddress,
        'token_id' => $tokenId,
        'balance' => $trc1155->balanceOf($caller, $tokenId),
        'uri' => $trc1155->uri($tokenId),
    ];
}

ExampleEnvironment::output($result);
