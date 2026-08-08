<?php

declare(strict_types=1);

namespace IEXBase\TronAPI\Examples;

use IEXBase\TronAPI\Configuration\NodeConfiguration;
use IEXBase\TronAPI\Crypto\LocalPrivateKeySigner;
use IEXBase\TronAPI\Enum\Network;
use IEXBase\TronAPI\Transaction\Transaction;
use IEXBase\TronAPI\Tron;
use IEXBase\TronAPI\Value\Address;
use JsonException;
use RuntimeException;

require_once dirname(__DIR__) . '/vendor/autoload.php';

/**
 * Centralizes safe environment parsing, client creation, output, and signing for examples.
 */
final class ExampleEnvironment
{
    private static ?Tron $tron = null;

    /**
     * Returns one shared public-network client, defaulting examples to Shasta.
     */
    public static function tron(): Tron
    {
        if (self::$tron !== null) {
            return self::$tron;
        }

        $network = match (strtolower(self::value('TRON_NETWORK', 'shasta'))) {
            'mainnet' => Network::Mainnet,
            'shasta' => Network::Shasta,
            'nile' => Network::Nile,
            default => throw new RuntimeException('TRON_NETWORK must be mainnet, shasta, or nile.'),
        };
        $apiKey = self::optional('TRON_API_KEY');

        return self::$tron = Tron::create(NodeConfiguration::forNetwork($network, $apiKey));
    }

    /**
     * Returns a required non-empty environment value.
     */
    public static function required(string $name): string
    {
        return self::optional($name)
            ?? throw new RuntimeException(sprintf('The %s environment variable is required.', $name));
    }

    /**
     * Returns an environment value or a documented example default.
     */
    public static function value(string $name, string $default): string
    {
        return self::optional($name) ?? $default;
    }

    /**
     * Parses a required TRON address from Base58Check or hexadecimal text.
     */
    public static function address(string $name): Address
    {
        return Address::fromString(self::required($name));
    }

    /**
     * Creates a local signer from a required private-key environment variable.
     */
    public static function signer(string $name = 'TRON_PRIVATE_KEY'): LocalPrivateKeySigner
    {
        return new LocalPrivateKeySigner(self::required($name));
    }

    /**
     * Reads a required example input file without accepting an unreadable path.
     */
    public static function file(string $environmentName): string
    {
        $path = self::required($environmentName);
        $contents = file_get_contents($path);

        return $contents === false
            ? throw new RuntimeException(sprintf('The file configured by %s cannot be read.', $environmentName))
            : $contents;
    }

    /**
     * Decodes a required or default JSON array for ABI argument examples.
     *
     * @return array<mixed>
     *
     * @throws JsonException When the configured JSON is malformed.
     */
    public static function jsonArray(string $name, string $default = '[]'): array
    {
        $decoded = json_decode(self::value($name, $default), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException(sprintf('%s must contain a JSON array or object.', $name));
        }

        return $decoded;
    }

    /**
     * Prints JSON without silently replacing unsupported values.
     *
     * @throws JsonException When a value cannot be represented as JSON.
     */
    public static function output(mixed $value): void
    {
        echo json_encode(
            $value,
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ) . PHP_EOL;
    }

    /**
     * Signs a verified transaction locally and broadcasts only after explicit opt-in.
     */
    public static function signAndMaybeBroadcast(
        Transaction $transaction,
        string $privateKeyEnvironment = 'TRON_PRIVATE_KEY',
    ): Transaction {
        $signed = self::tron()->transactions()->appendSignature(
            $transaction,
            self::signer($privateKeyEnvironment),
        );
        self::output(['transaction_id' => $signed->id(), 'signed' => true]);
        self::broadcastWhenEnabled($signed);

        return $signed;
    }

    /**
     * Broadcasts an already signed transaction when the shared example flag is enabled.
     */
    public static function broadcastWhenEnabled(Transaction $transaction): void
    {
        if (self::value('TRON_BROADCAST', '0') === '1') {
            self::output(self::tron()->transactions()->broadcast($transaction));
        }
    }

    /**
     * Returns a trimmed environment value or null when it is absent or empty.
     */
    public static function optional(string $name): ?string
    {
        $value = getenv($name);
        if ($value === false || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
