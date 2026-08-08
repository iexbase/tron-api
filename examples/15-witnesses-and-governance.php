<?php

declare(strict_types=1);

use IEXBase\TronAPI\Examples\ExampleEnvironment;

require_once __DIR__ . '/ExampleEnvironment.php';

$tron = ExampleEnvironment::tron();

ExampleEnvironment::output([
    'witnesses' => $tron->witnesses()->page(limit: 20),
    'proposals' => $tron->governance()->page(limit: 20),
    'next_maintenance_at' => $tron->governance()->nextMaintenanceAtMilliseconds(),
]);
