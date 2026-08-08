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

namespace IEXBase\TronAPI;

use IEXBase\TronAPI\Api\ApiClient;
use IEXBase\TronAPI\Configuration\NodeConfiguration;
use IEXBase\TronAPI\Enum\Network;
use IEXBase\TronAPI\Http\GuzzleTransport;
use IEXBase\TronAPI\Http\TransportInterface;
use IEXBase\TronAPI\Indexer\AccountHistoryProviderInterface;
use IEXBase\TronAPI\Indexer\EventProviderInterface;
use IEXBase\TronAPI\Indexer\TokenIndexProviderInterface;
use IEXBase\TronAPI\Indexer\TronGridProvider;
use IEXBase\TronAPI\JsonRpc\JsonRpcClient;
use IEXBase\TronAPI\Service\AccountService;
use IEXBase\TronAPI\Service\AssetService;
use IEXBase\TronAPI\Service\BlockService;
use IEXBase\TronAPI\Service\ContractService;
use IEXBase\TronAPI\Service\ExchangeService;
use IEXBase\TronAPI\Service\GovernanceService;
use IEXBase\TronAPI\Service\MarketService;
use IEXBase\TronAPI\Service\LegacyStakeService;
use IEXBase\TronAPI\Service\NetworkService;
use IEXBase\TronAPI\Service\PermissionService;
use IEXBase\TronAPI\Service\StakeService;
use IEXBase\TronAPI\Service\StakeDelegationReader;
use IEXBase\TronAPI\Service\TransactionService;
use IEXBase\TronAPI\Service\TransferService;
use IEXBase\TronAPI\Service\WitnessService;
use IEXBase\TronAPI\Transaction\TransactionFactory;

/**
 * Composes TronAPI services without storing private keys or binding native APIs to TronGrid.
 */
final readonly class Tron
{
    /**
     * Stores one instance of each stateless service and optional indexer contract.
     */
    private function __construct(
        private ApiClient $apiClient,
        private AccountService $accountService,
        private BlockService $blockService,
        private TransactionService $transactionService,
        private TransferService $transferService,
        private AssetService $assetService,
        private StakeService $stakeService,
        private LegacyStakeService $legacyStakeService,
        private ContractService $contractService,
        private PermissionService $permissionService,
        private NetworkService $networkService,
        private WitnessService $witnessService,
        private GovernanceService $governanceService,
        private ExchangeService $exchangeService,
        private MarketService $marketService,
        private JsonRpcClient $jsonRpcClient,
        private ?AccountHistoryProviderInterface $accountHistoryProvider,
        private ?EventProviderInterface $eventProvider,
        private ?TokenIndexProviderInterface $tokenIndexProvider,
    ) {
    }

    /**
     * Creates a complete client with injectable transport and optional indexer providers.
     *
     * Public network profiles use the replaceable TronGrid v1 adapter by default.
     * Custom/local topologies never assume an indexer schema and require explicit
     * provider implementations when indexed history or discovery is needed.
     */
    public static function create(
        NodeConfiguration $configuration,
        ?TransportInterface $transport = null,
        ?AccountHistoryProviderInterface $accountHistoryProvider = null,
        ?EventProviderInterface $eventProvider = null,
        ?TokenIndexProviderInterface $tokenIndexProvider = null,
    ): self {
        $api = new ApiClient($configuration, $transport ?? new GuzzleTransport());
        $transactionFactory = new TransactionFactory($api);
        $stakeDelegations = new StakeDelegationReader($api);
        $accounts = new AccountService($api, $transactionFactory);
        $blocks = new BlockService($api);

        if ($configuration->network !== Network::Custom
            && ($accountHistoryProvider === null || $eventProvider === null || $tokenIndexProvider === null)
        ) {
            $defaultIndexer = new TronGridProvider($api);
            $accountHistoryProvider ??= $defaultIndexer;
            $eventProvider ??= $defaultIndexer;
            $tokenIndexProvider ??= $defaultIndexer;
        }

        return new self(
            $api,
            $accounts,
            $blocks,
            new TransactionService($api),
            new TransferService($transactionFactory),
            new AssetService($api, $transactionFactory),
            new StakeService($api, $transactionFactory, $stakeDelegations),
            new LegacyStakeService($transactionFactory, $stakeDelegations),
            new ContractService($api, $transactionFactory),
            new PermissionService($accounts, $transactionFactory),
            new NetworkService($api, $blocks),
            new WitnessService($api, $transactionFactory),
            new GovernanceService($api, $transactionFactory),
            new ExchangeService($api, $transactionFactory),
            new MarketService($api, $transactionFactory),
            new JsonRpcClient($api),
            $accountHistoryProvider,
            $eventProvider,
            $tokenIndexProvider,
        );
    }

    /**
     * Returns the generic role-aware API client for newly introduced endpoints.
     */
    public function api(): ApiClient
    {
        return $this->apiClient;
    }

    /**
     * Returns native account read and activation operations.
     */
    public function accounts(): AccountService
    {
        return $this->accountService;
    }

    /**
     * Returns latest and confirmed block operations.
     */
    public function blocks(): BlockService
    {
        return $this->blockService;
    }

    /**
     * Returns transaction lookup, local signing, multisig, and broadcast operations.
     */
    public function transactions(): TransactionService
    {
        return $this->transactionService;
    }

    /**
     * Returns exact native TRX transfer construction operations.
     */
    public function transfers(): TransferService
    {
        return $this->transferService;
    }

    /**
     * Returns native TRC-10 metadata and transfer operations.
     */
    public function assets(): AssetService
    {
        return $this->assetService;
    }

    /**
     * Returns complete Stake 2.0 mutation and query operations.
     */
    public function stake(): StakeService
    {
        return $this->stakeService;
    }

    /**
     * Returns explicitly legacy Stake 1.0 release and compatibility operations.
     */
    public function legacyStake(): LegacyStakeService
    {
        return $this->legacyStakeService;
    }

    /**
     * Returns ABI-aware contract, token, simulation, estimate, and deployment operations.
     */
    public function contracts(): ContractService
    {
        return $this->contractService;
    }

    /**
     * Returns owner/active/witness permission and multisig update operations.
     */
    public function permissions(): PermissionService
    {
        return $this->permissionService;
    }

    /**
     * Returns native node, chain parameter, price, and synchronization operations.
     */
    public function network(): NetworkService
    {
        return $this->networkService;
    }

    /**
     * Returns SR discovery, voting, brokerage, candidate, and reward operations.
     */
    public function witnesses(): WitnessService
    {
        return $this->witnessService;
    }

    /**
     * Returns on-chain proposal reads, maintenance time, creation, and voting operations.
     */
    public function governance(): GovernanceService
    {
        return $this->governanceService;
    }

    /**
     * Returns protocol-native bonding-curve exchange reads and transactions.
     */
    public function exchanges(): ExchangeService
    {
        return $this->exchangeService;
    }

    /**
     * Returns protocol-native limit-order market reads and transactions.
     */
    public function market(): MarketService
    {
        return $this->marketService;
    }

    /**
     * Returns the complete Ethereum-compatible TRON JSON-RPC client.
     */
    public function jsonRpc(): JsonRpcClient
    {
        return $this->jsonRpcClient;
    }

    /**
     * Returns the optional vendor-neutral account history provider.
     */
    public function accountHistory(): ?AccountHistoryProviderInterface
    {
        return $this->accountHistoryProvider;
    }

    /**
     * Returns the optional vendor-neutral contract event provider.
     */
    public function events(): ?EventProviderInterface
    {
        return $this->eventProvider;
    }

    /**
     * Returns the optional vendor-neutral token discovery provider.
     */
    public function tokenIndex(): ?TokenIndexProviderInterface
    {
        return $this->tokenIndexProvider;
    }
}
