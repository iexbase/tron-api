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

namespace IEXBase\TronAPI\Tests\Examples;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that executable examples remain discoverable and use the current public API.
 */
#[CoversNothing]
final class ExampleCatalogTest extends TestCase
{
    /**
     * Requires every numbered example to appear exactly once in the complete example map.
     */
    public function testEveryNumberedExampleIsCatalogued(): void
    {
        $root = dirname(__DIR__, 2);
        $paths = glob($root . '/examples/[0-9][0-9]-*.php');
        if ($paths === false) {
            self::fail('The numbered example files could not be enumerated.');
        }

        $filenames = array_map(static fn (string $path): string => basename($path), $paths);
        sort($filenames);

        $matches = [];
        $matchCount = preg_match_all(
            '/^\| `([0-9]{2}-[^`]+\.php)` \|/m',
            self::readFile($root . '/examples/README.md'),
            $matches,
        );
        if ($matchCount === false) {
            self::fail('The example catalog could not be parsed.');
        }

        $catalogued = $matches[1];
        sort($catalogued);

        self::assertCount(19, $filenames);
        self::assertSame($filenames, $catalogued);
    }

    /**
     * Keeps the requested transaction and token workflows explicit without duplicate entry points.
     */
    public function testPrimaryWorkflowsUseDedicatedEntryPoints(): void
    {
        $examples = dirname(__DIR__, 2) . '/examples/';

        foreach ([
            '04-send-trx-transaction.php',
            '08-read-trc20-token.php',
            '09-send-trc20-transaction.php',
            '12-work-with-tokens.php',
            '13-get-transactions.php',
        ] as $filename) {
            self::assertFileExists($examples . $filename);
        }
    }

    /**
     * Rejects obsolete Amount calls in the public installation and quick-start documentation.
     */
    public function testReadmeUsesCurrentAmountMethods(): void
    {
        $readme = self::readFile(dirname(__DIR__, 2) . '/README.md');

        self::assertStringNotContainsString('->atomic()', $readme);
        self::assertStringNotContainsString('->toDecimal()', $readme);
        self::assertStringContainsString('->atomicValue()', $readme);
        self::assertStringContainsString('->decimalValue()', $readme);
    }

    /**
     * Reads a required repository document or fails with its exact path.
     */
    private static function readFile(string $path): string
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            self::fail(sprintf('The required file `%s` could not be read.', $path));
        }

        return $contents;
    }
}
