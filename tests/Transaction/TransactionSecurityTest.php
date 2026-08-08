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

namespace IEXBase\TronAPI\Tests\Transaction;

use DateTimeImmutable;
use IEXBase\TronAPI\Crypto\LocalPrivateKeySigner;
use IEXBase\TronAPI\Encoding\Hex;
use IEXBase\TronAPI\Encoding\Protobuf;
use IEXBase\TronAPI\Enum\ContractType;
use IEXBase\TronAPI\Exception\TransactionException;
use IEXBase\TronAPI\Exception\ValidationException;
use IEXBase\TronAPI\Tests\Support\TransactionFixture;
use IEXBase\TronAPI\Transaction\Permission;
use IEXBase\TronAPI\Transaction\PermissionSet;
use IEXBase\TronAPI\Transaction\ContractWireEncoder;
use IEXBase\TronAPI\Transaction\Transaction;
use IEXBase\TronAPI\Transaction\TransactionIntent;
use IEXBase\TronAPI\Transaction\TransactionSigner;
use IEXBase\TronAPI\Transaction\TransactionVerifier;
use IEXBase\TronAPI\Transaction\TransactionWireEncoder;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\Amount;
use IEXBase\TronAPI\Value\Memo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies transaction hashes, approved intent, permission bits, and local signatures.
 */
#[CoversClass(Transaction::class)]
#[CoversClass(TransactionIntent::class)]
#[CoversClass(TransactionVerifier::class)]
#[CoversClass(TransactionWireEncoder::class)]
#[CoversClass(ContractWireEncoder::class)]
#[CoversClass(Protobuf::class)]
#[CoversClass(TransactionSigner::class)]
#[CoversClass(Permission::class)]
#[CoversClass(PermissionSet::class)]
final class TransactionSecurityTest extends TestCase
{
    private const OWNER_KEY = '0000000000000000000000000000000000000000000000000000000000000001';
    private const SECOND_KEY = '0000000000000000000000000000000000000000000000000000000000000002';
    private const RECIPIENT = 'TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY';

    /**
     * Accepts a node transaction only when every approved field matches.
     */
    public function testMatchingTransactionIntentIsAccepted(): void
    {
        $intent = $this->transferIntent();
        $transaction = TransactionFixture::transaction($intent);

        (new TransactionVerifier())->verify($transaction, $intent);

        self::assertSame($intent->contractType, $transaction->singleContract()['type']);
        self::assertFalse($transaction->isSigned());
    }

    /**
     * Rejects a modified structured transaction even when a node supplies a valid hash field.
     */
    public function testModifiedAmountIsRejectedBeforeSigning(): void
    {
        $intent = $this->transferIntent();
        $transaction = TransactionFixture::transaction($this->transferIntent(2));
        $this->expectException(TransactionException::class);

        (new TransactionVerifier())->verify($transaction, $intent);
    }

    /**
     * Rejects a txID that is not SHA-256 of the exact raw_data_hex bytes.
     */
    public function testMismatchedTransactionHashIsRejected(): void
    {
        $data = TransactionFixture::data($this->transferIntent());
        $data['txID'] = str_repeat('00', 32);
        $this->expectException(TransactionException::class);

        Transaction::fromNodeData($data);
    }

    /**
     * Rejects validly hashed protobuf bytes that differ from the displayed intent.
     */
    public function testWirePayloadCannotDisagreeWithStructuredTransaction(): void
    {
        $intent = $this->transferIntent();
        $data = TransactionFixture::data($intent);
        $rawData = $this->rawData($data);

        $maliciousBytes = (new TransactionWireEncoder())->encode($this->transferIntent(2), $rawData);
        $data['raw_data_hex'] = Hex::fromBytes($maliciousBytes);
        $data['txID'] = hash('sha256', $maliciousBytes);
        $transaction = Transaction::fromNodeData($data);
        $this->expectException(TransactionException::class);

        (new TransactionVerifier())->verify($transaction, $intent);
    }

    /**
     * Rejects a protobuf Any type URL that disagrees with the contract enum.
     */
    public function testParameterTypeUrlMustMatchIntent(): void
    {
        $intent = $this->transferIntent();
        $data = TransactionFixture::data($intent);
        $rawData = $this->rawData($data);
        $contracts = $rawData['contract'] ?? null;
        if (!is_array($contracts) || !isset($contracts[0]) || !is_array($contracts[0])) {
            self::fail('The transaction fixture does not contain its contract.');
        }
        $parameter = $contracts[0]['parameter'] ?? null;
        if (!is_array($parameter)) {
            self::fail('The transaction fixture does not contain its parameter.');
        }
        $parameter['type_url'] = 'type.googleapis.com/protocol.TransferAssetContract';
        $contracts[0]['parameter'] = $parameter;
        $rawData['contract'] = $contracts;
        $data['raw_data'] = $rawData;
        $transaction = Transaction::fromNodeData($data);
        $this->expectException(TransactionException::class);

        (new TransactionVerifier())->verify($transaction, $intent);
    }

    /**
     * Returns a fixture raw_data object with verified string field names.
     *
     * @param array<string, mixed> $transaction Transaction fixture data.
     * @return array<string, mixed>
     */
    private function rawData(array $transaction): array
    {
        $value = $transaction['raw_data'] ?? null;
        if (!is_array($value)) {
            self::fail('The transaction fixture does not contain raw_data.');
        }

        $result = [];
        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                self::fail('The transaction fixture raw_data must use string field names.');
            }
            $result[$key] = $item;
        }

        return $result;
    }

    /**
     * Signs locally and recovers the expected owner address.
     */
    public function testOwnerSignatureIsAppendedLocally(): void
    {
        $signer = new LocalPrivateKeySigner(self::OWNER_KEY);
        $intent = $this->transferIntent();
        $signed = (new TransactionSigner())->appendSignature(
            TransactionFixture::transaction($intent),
            $signer,
            $intent,
        );

        self::assertTrue($signed->isSigned());
        self::assertTrue($signed->signerAddresses()[0]->equals($signer->address()));
    }

    /**
     * Requires an explicit matching Permission for a non-owner signing key.
     */
    public function testWrongOwnerKeyWithoutPermissionIsRejected(): void
    {
        $intent = $this->transferIntent();
        $this->expectException(TransactionException::class);

        (new TransactionSigner())->appendSignature(
            TransactionFixture::transaction($intent),
            new LocalPrivateKeySigner(self::SECOND_KEY),
            $intent,
        );
    }

    /**
     * Allows an active permission only when its little-endian operation bit is set.
     */
    public function testActivePermissionOperationBitmapIsEnforced(): void
    {
        $owner = new LocalPrivateKeySigner(self::OWNER_KEY);
        $activeSigner = new LocalPrivateKeySigner(self::SECOND_KEY);
        $intent = new TransactionIntent(
            'TransferContract',
            $owner->address(),
            ['to_address' => Address::fromBase58(self::RECIPIENT), 'amount' => 1],
            2,
        );
        $permission = new Permission(2, 'payments', 'Active', 1, [
            ['address' => $activeSigner->address(), 'weight' => 1],
        ], '02' . str_repeat('00', 31));

        $signed = (new TransactionSigner())->appendSignature(
            TransactionFixture::transaction($intent),
            $activeSigner,
            $intent,
            $permission,
        );

        self::assertTrue($permission->allows(ContractType::TransferContract));
        self::assertFalse($permission->allows(ContractType::TriggerSmartContract));
        self::assertTrue($signed->isSigned());
    }

    /**
     * Rejects duplicate signatures recovered from the same permission key.
     */
    public function testDuplicateSignerIsRejected(): void
    {
        $signer = new LocalPrivateKeySigner(self::OWNER_KEY);
        $intent = $this->transferIntent();
        $signed = (new TransactionSigner())->appendSignature(
            TransactionFixture::transaction($intent),
            $signer,
            $intent,
        );
        $this->expectException(TransactionException::class);

        $signed->appendSignature($signed->signatures()[0]);
    }

    /**
     * Compares expiration using millisecond precision rather than rounded seconds.
     */
    public function testExpirationUsesMillisecondPrecision(): void
    {
        $transaction = TransactionFixture::transaction($this->transferIntent());

        self::assertFalse($transaction->isExpired(new DateTimeImmutable('2099-12-31T23:59:59.999Z')));
        self::assertTrue($transaction->isExpired(new DateTimeImmutable('2100-01-01T00:00:00.000Z')));
    }

    /**
     * Enforces the documented 15,000 TRX smart-contract fee limit ceiling.
     */
    public function testFeeLimitMaximumIsEnforced(): void
    {
        $this->expectException(ValidationException::class);

        new TransactionIntent(
            'TriggerSmartContract',
            (new LocalPrivateKeySigner(self::OWNER_KEY))->address(),
            ['contract_address' => Address::fromBase58(self::RECIPIENT), 'data' => '00'],
            feeLimit: Amount::fromDecimal('15000.000001'),
        );
    }

    /**
     * Creates one canonical transfer intent shared by transaction tests.
     */
    private function transferIntent(int $amount = 1): TransactionIntent
    {
        return new TransactionIntent(
            'TransferContract',
            (new LocalPrivateKeySigner(self::OWNER_KEY))->address(),
            ['to_address' => Address::fromBase58(self::RECIPIENT), 'amount' => $amount],
            memoHex: Memo::fromText('test')->toHex(),
        );
    }
}
