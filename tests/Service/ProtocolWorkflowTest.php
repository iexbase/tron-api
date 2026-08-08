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
use IEXBase\TronAPI\Governance\ProposalParameter;
use IEXBase\TronAPI\Governance\WitnessVote;
use IEXBase\TronAPI\Model\Asset;
use IEXBase\TronAPI\Model\Proposal;
use IEXBase\TronAPI\Service\AssetService;
use IEXBase\TronAPI\Service\GovernanceService;
use IEXBase\TronAPI\Service\StakeService;
use IEXBase\TronAPI\Service\StakeDelegationReader;
use IEXBase\TronAPI\Service\WitnessService;
use IEXBase\TronAPI\Tests\Support\HttpResponseFactory;
use IEXBase\TronAPI\Tests\Support\QueueTransport;
use IEXBase\TronAPI\Tests\Support\TransactionFixture;
use IEXBase\TronAPI\Transaction\TransactionFactory;
use IEXBase\TronAPI\Transaction\TransactionIntent;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\Amount;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies TRC-10, Stake 2.0, governance, and witness transaction contracts.
 */
#[CoversClass(AssetService::class)]
#[CoversClass(Asset::class)]
#[CoversClass(StakeService::class)]
#[CoversClass(GovernanceService::class)]
#[CoversClass(Proposal::class)]
#[CoversClass(ProposalParameter::class)]
#[CoversClass(WitnessService::class)]
#[CoversClass(WitnessVote::class)]
final class ProtocolWorkflowTest extends TestCase
{
    private const OWNER_KEY = '0000000000000000000000000000000000000000000000000000000000000001';
    private const RECIPIENT = 'TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY';

    /**
     * Parses token-specific precision and uses it for a verified TRC-10 transfer.
     */
    public function testTrc10TransferUsesAssetPrecisionAndNumericId(): void
    {
        $owner = (new LocalPrivateKeySigner(self::OWNER_KEY))->address();
        $asset = Asset::fromNodeData([
            'id' => '1002000',
            'name' => 'Example Token',
            'abbr' => 'EXT',
            'owner_address' => $owner->toBase58(),
            'precision' => 3,
            'total_supply' => '9007199254740993',
            'start_time' => 1_700_000_000_000,
            'end_time' => 1_800_000_000_000,
        ]);
        $recipient = Address::fromBase58(self::RECIPIENT);
        $intent = new TransactionIntent('TransferAssetContract', $owner, [
            'to_address' => $recipient,
            'asset_name' => $asset->id,
            'amount' => 1_250,
        ]);
        $transport = new QueueTransport(HttpResponseFactory::json(TransactionFixture::data($intent)));
        $api = $this->api($transport);
        $service = new AssetService($api, new TransactionFactory($api));

        $transaction = $service->createTransfer($owner, $recipient, $asset, $asset->amountFromDecimal('1.25'));

        self::assertSame('9007199254740.993', $asset->totalSupply->decimalValue(false));
        self::assertSame('1002000', $transport->request()->parameters['asset_name']);
        self::assertSame(1_250, $transport->request()->parameters['amount']);
        self::assertSame('TransferAssetContract', $transaction->singleContract()['type']);
    }

    /**
     * Builds a locked Stake 2.0 delegation with explicit block duration.
     */
    public function testLockedStakeDelegationPreservesEveryField(): void
    {
        $owner = (new LocalPrivateKeySigner(self::OWNER_KEY))->address();
        $recipient = Address::fromBase58(self::RECIPIENT);
        $fields = [
            'receiver_address' => $recipient,
            'balance' => 2_500_000,
            'resource' => ResourceType::Energy->value,
            'lock' => true,
            'lock_period' => 28_800,
        ];
        $intent = new TransactionIntent('DelegateResourceContract', $owner, $fields);
        $transport = new QueueTransport(HttpResponseFactory::json(TransactionFixture::data($intent)));
        $api = $this->api($transport);
        $service = new StakeService($api, new TransactionFactory($api), new StakeDelegationReader($api));

        $transaction = $service->delegate(
            $owner,
            $recipient,
            Amount::fromDecimal('2.5'),
            ResourceType::Energy,
            true,
            28_800,
        );

        self::assertSame(2_500_000, $transport->request()->parameters['balance']);
        self::assertSame('ENERGY', $transport->request()->parameters['resource']);
        self::assertTrue($transport->request()->parameters['lock'] === true);
        self::assertSame(28_800, $transport->request()->parameters['lock_period']);
        self::assertSame('DelegateResourceContract', $transaction->singleContract()['type']);
    }

    /**
     * Allows migrated TRON Power only for unstaking, not for new positions.
     */
    public function testNewTronPowerStakeIsRejected(): void
    {
        $api = $this->api(new QueueTransport());
        $service = new StakeService($api, new TransactionFactory($api), new StakeDelegationReader($api));
        $this->expectException(ValidationException::class);

        $service->stake(
            (new LocalPrivateKeySigner(self::OWNER_KEY))->address(),
            Amount::fromDecimal('1'),
            ResourceType::TronPower,
        );
    }

    /**
     * Parses a bounded proposal page with signed chain parameter values.
     */
    public function testGovernancePageParsesSignedParameters(): void
    {
        $owner = (new LocalPrivateKeySigner(self::OWNER_KEY))->address();
        $transport = new QueueTransport(HttpResponseFactory::json(['proposals' => [[
            'proposal_id' => 7,
            'proposer_address' => $owner->toBase58(),
            'parameters' => [['key' => 1, 'value' => -5]],
            'create_time' => 1_700_000_000_000,
            'expiration_time' => 1_700_100_000_000,
            'approvals' => [$owner->toBase58()],
            'state' => 'APPROVED',
        ]]]));
        $api = $this->api($transport);
        $proposals = (new GovernanceService($api, new TransactionFactory($api)))->page(10, 5);

        self::assertCount(1, $proposals);
        self::assertSame('-5', $proposals[0]->parameters()[1]);
        self::assertSame(['offset' => 10, 'limit' => 5], $transport->request()->parameters);
    }

    /**
     * Rejects duplicate SR candidates in a complete vote replacement.
     */
    public function testDuplicateWitnessVoteIsRejected(): void
    {
        $api = $this->api(new QueueTransport());
        $service = new WitnessService($api, new TransactionFactory($api));
        $owner = (new LocalPrivateKeySigner(self::OWNER_KEY))->address();
        $candidate = Address::fromBase58(self::RECIPIENT);
        $this->expectException(ValidationException::class);

        $service->vote($owner, [
            new WitnessVote($candidate, 1),
            new WitnessVote($candidate, 2),
        ]);
    }

    /**
     * Creates one API client over the supplied deterministic transport.
     */
    private function api(QueueTransport $transport): ApiClient
    {
        return new ApiClient(NodeConfiguration::custom('https://node.example'), $transport);
    }
}
