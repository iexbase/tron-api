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

namespace IEXBase\TronAPI\Tests\Service;

use IEXBase\TronAPI\Api\ApiClient;
use IEXBase\TronAPI\Configuration\NodeConfiguration;
use IEXBase\TronAPI\Crypto\LocalPrivateKeySigner;
use IEXBase\TronAPI\Enum\ResourceType;
use IEXBase\TronAPI\Exception\ValidationException;
use IEXBase\TronAPI\Service\AccountService;
use IEXBase\TronAPI\Service\LegacyStakeService;
use IEXBase\TronAPI\Service\PermissionService;
use IEXBase\TronAPI\Service\StakeDelegationReader;
use IEXBase\TronAPI\Tests\Support\HttpResponseFactory;
use IEXBase\TronAPI\Tests\Support\QueueTransport;
use IEXBase\TronAPI\Tests\Support\TransactionFixture;
use IEXBase\TronAPI\Transaction\Permission;
use IEXBase\TronAPI\Transaction\PermissionSet;
use IEXBase\TronAPI\Transaction\TransactionFactory;
use IEXBase\TronAPI\Transaction\TransactionIntent;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\Amount;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies account permissions and the explicit Stake 1.0 compatibility boundary.
 */
#[CoversClass(PermissionService::class)]
#[CoversClass(PermissionSet::class)]
#[CoversClass(LegacyStakeService::class)]
#[CoversClass(StakeDelegationReader::class)]
final class PermissionAndLegacyStakeTest extends TestCase
{
    private const OWNER_KEY = '0000000000000000000000000000000000000000000000000000000000000001';
    private const RECIPIENT = 'TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY';

    /**
     * Synthesizes protocol-default owner and Active permissions when omitted.
     */
    public function testPermissionReaderProvidesImplicitAccountDefaults(): void
    {
        $owner = $this->owner();
        $transport = new QueueTransport(HttpResponseFactory::json([
            'address' => $owner->toBase58(),
            'balance' => 0,
        ]));
        $api = $this->api($transport);
        $service = new PermissionService(
            new AccountService($api, new TransactionFactory($api)),
            new TransactionFactory($api),
        );
        $set = $service->get($owner);

        self::assertInstanceOf(PermissionSet::class, $set);
        self::assertTrue($set->ownerPermission->contains($owner));
        self::assertSame(2, $set->activePermissions()[0]->id);
    }

    /**
     * Builds a complete owner-authorized permission replacement request.
     */
    public function testPermissionUpdateSerializesTypedPermissionsOnce(): void
    {
        $owner = $this->owner();
        $ownerPermission = new Permission(0, 'owner', 'Owner', 1, [
            ['address' => $owner, 'weight' => 1],
        ]);
        $activePermission = new Permission(2, 'payments', 'Active', 1, [
            ['address' => $owner, 'weight' => 1],
        ], str_repeat('ff', 32));
        $intent = new TransactionIntent('AccountPermissionUpdateContract', $owner, [
            'owner' => $ownerPermission,
            'actives' => [$activePermission],
        ]);
        $transport = new QueueTransport(HttpResponseFactory::json(TransactionFixture::data($intent)));
        $api = $this->api($transport);
        $service = new PermissionService(
            new AccountService($api, new TransactionFactory($api)),
            new TransactionFactory($api),
        );

        $transaction = $service->createUpdate($owner, $ownerPermission, [$activePermission]);

        $activePermissions = $transport->request()->parameters['actives'] ?? null;
        if (!is_array($activePermissions)) {
            self::fail('The permission request does not contain an Active permission list.');
        }

        $serializedPermission = $activePermissions[0] ?? null;
        if (!is_array($serializedPermission)) {
            self::fail('The permission request does not contain its first Active permission.');
        }

        self::assertSame('AccountPermissionUpdateContract', $transaction->singleContract()['type']);
        self::assertSame('payments', $serializedPermission['permission_name'] ?? null);
        self::assertSame(str_repeat('ff', 32), $serializedPermission['operations'] ?? null);
    }

    /**
     * Rejects explicit Active permissions that omit their operation bitmap.
     */
    public function testPermissionUpdateRequiresActiveOperations(): void
    {
        $owner = $this->owner();
        $ownerPermission = new Permission(0, 'owner', 'Owner', 1, [
            ['address' => $owner, 'weight' => 1],
        ]);
        $activePermission = new Permission(2, 'active', 'Active', 1, [
            ['address' => $owner, 'weight' => 1],
        ]);
        $api = $this->api(new QueueTransport());
        $service = new PermissionService(
            new AccountService($api, new TransactionFactory($api)),
            new TransactionFactory($api),
        );
        $this->expectException(ValidationException::class);

        $service->createUpdate($owner, $ownerPermission, [$activePermission]);
    }

    /**
     * Builds legacy delegated freeze and unfreeze transactions explicitly.
     */
    public function testLegacyStakeMutationsRemainIsolatedAndVerified(): void
    {
        $owner = $this->owner();
        $recipient = Address::fromBase58(self::RECIPIENT);
        $freezeFields = [
            'frozen_balance' => 2_000_000,
            'frozen_duration' => 3,
            'resource' => 'ENERGY',
            'receiver_address' => $recipient,
        ];
        $unfreezeFields = [
            'resource' => 'ENERGY',
            'receiver_address' => $recipient,
        ];
        $transport = new QueueTransport(
            HttpResponseFactory::json(TransactionFixture::data(new TransactionIntent(
                'FreezeBalanceContract',
                $owner,
                $freezeFields,
            ))),
            HttpResponseFactory::json(TransactionFixture::data(new TransactionIntent(
                'UnfreezeBalanceContract',
                $owner,
                $unfreezeFields,
            ))),
        );
        $service = $this->legacyService($transport);

        $service->freeze($owner, Amount::fromDecimal('2'), ResourceType::Energy, 3, $recipient);
        $service->unfreeze($owner, ResourceType::Energy, $recipient);

        self::assertSame('/wallet/freezebalance', parse_url($transport->request()->uri, PHP_URL_PATH));
        self::assertSame('/wallet/unfreezebalance', parse_url($transport->request(1)->uri, PHP_URL_PATH));
    }

    /**
     * Reads legacy delegation records and indexes through the shared reader.
     */
    public function testLegacyStakeQueriesUseConfirmedEndpointPairs(): void
    {
        $owner = $this->owner();
        $recipient = Address::fromBase58(self::RECIPIENT);
        $transport = new QueueTransport(
            HttpResponseFactory::json(['delegatedResource' => [[
                'from' => $owner->toBase58(),
                'to' => $recipient->toBase58(),
            ]]]),
            HttpResponseFactory::json([
                'fromAccounts' => [$owner->toBase58()],
                'toAccounts' => [$recipient->toBase58()],
            ]),
        );
        $service = $this->legacyService($transport);

        self::assertCount(1, $service->delegations($owner, $recipient));
        self::assertSame([$owner->toBase58()], $service->delegationIndex($owner)['fromAccounts']);
        self::assertStringContainsString('/walletsolidity/', $transport->request()->uri);
        self::assertStringContainsString('/walletsolidity/', $transport->request(1)->uri);
    }

    /**
     * Creates the isolated legacy service and its shared delegation reader.
     */
    private function legacyService(QueueTransport $transport): LegacyStakeService
    {
        $api = $this->api($transport);

        return new LegacyStakeService(new TransactionFactory($api), new StakeDelegationReader($api));
    }

    /**
     * Creates a role-aware API client over one deterministic transport.
     */
    private function api(QueueTransport $transport): ApiClient
    {
        return new ApiClient(NodeConfiguration::custom('https://node.example'), $transport);
    }

    /**
     * Returns the deterministic owner used by permission and staking fixtures.
     */
    private function owner(): Address
    {
        return (new LocalPrivateKeySigner(self::OWNER_KEY))->address();
    }
}
