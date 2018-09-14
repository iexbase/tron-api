<?php
include_once '../vendor/autoload.php';

$tron = new \IEXBase\TronAPI\Tron();

$tron->toHex('TT67rPNwgmpeimvHUMVzFfKsjL9GZ1wGw8');
//result: 41BBC8C05F1B09839E72DB044A6AA57E2A5D414A10

$tron->fromHex('41BBC8C05F1B09839E72DB044A6AA57E2A5D414A10');
//result: TT67rPNwgmpeimvHUMVzFfKsjL9GZ1wGw8