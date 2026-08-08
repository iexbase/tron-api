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

namespace IEXBase\TronAPI\Api;

use IEXBase\TronAPI\Enum\ConfirmationLevel;
use IEXBase\TronAPI\Enum\HttpMethod;
use IEXBase\TronAPI\Enum\NodeRole;
use IEXBase\TronAPI\Exception\ValidationException;

/**
 * Centralizes built-in HTTP paths so services never duplicate route strings.
 *
 * The enum covers native node and default indexed-provider operations used by
 * the typed services.
 * ApiRequest remains available for newly introduced official endpoints without
 * waiting for a package release, preserving access to the complete API surface.
 */
enum Endpoint: string
{
    case ValidateAddress = '/wallet/validateaddress';
    case CreateTransaction = '/wallet/createtransaction';
    case CreateCommonTransaction = '/wallet/createCommonTransaction';
    case BroadcastTransaction = '/wallet/broadcasttransaction';
    case BroadcastHex = '/wallet/broadcasthex';
    case GetSignWeight = '/wallet/getsignweight';
    case GetApprovedList = '/wallet/getapprovedlist';
    case CreateAccount = '/wallet/createaccount';
    case GetAccount = '/wallet/getaccount';
    case GetAccountById = '/wallet/getaccountbyid';
    case UpdateAccount = '/wallet/updateaccount';
    case SetAccountId = '/wallet/setaccountid';
    case UpdateAccountPermission = '/wallet/accountpermissionupdate';
    case GetAccountBalance = '/wallet/getaccountbalance';
    case GetAccountResource = '/wallet/getaccountresource';
    case GetAccountBandwidth = '/wallet/getaccountnet';
    case FreezeBalanceLegacy = '/wallet/freezebalance';
    case UnfreezeBalanceLegacy = '/wallet/unfreezebalance';
    case GetDelegatedResourceLegacy = '/wallet/getdelegatedresource';
    case GetDelegatedResourceIndexLegacy = '/wallet/getdelegatedresourceaccountindex';
    case FreezeBalanceV2 = '/wallet/freezebalancev2';
    case UnfreezeBalanceV2 = '/wallet/unfreezebalancev2';
    case CancelAllUnfreezeV2 = '/wallet/cancelallunfreezev2';
    case DelegateResource = '/wallet/delegateresource';
    case UndelegateResource = '/wallet/undelegateresource';
    case WithdrawExpiredUnfreeze = '/wallet/withdrawexpireunfreeze';
    case GetAvailableUnfreezeCount = '/wallet/getavailableunfreezecount';
    case GetWithdrawableUnfreezeAmount = '/wallet/getcanwithdrawunfreezeamount';
    case GetDelegatableResourceAmount = '/wallet/getcandelegatedmaxsize';
    case GetDelegatedResourceV2 = '/wallet/getdelegatedresourcev2';
    case GetDelegatedResourceIndexV2 = '/wallet/getdelegatedresourceaccountindexv2';
    case GetBlock = '/wallet/getblock';
    case GetBlockByNumber = '/wallet/getblockbynum';
    case GetBlockById = '/wallet/getblockbyid';
    case GetLatestBlocks = '/wallet/getblockbylatestnum';
    case GetBlockRange = '/wallet/getblockbylimitnext';
    case GetLatestBlock = '/wallet/getnowblock';
    case GetTransaction = '/wallet/gettransactionbyid';
    case GetTransactionReceipt = '/wallet/gettransactioninfobyid';
    case GetBlockReceipts = '/wallet/gettransactioninfobyblocknum';
    case GetBlockBalanceChanges = '/wallet/getblockbalance';
    case GetTransactionCountByBlock = '/wallet/gettransactioncountbyblocknum';
    case GetTransactionReceiptExtension = '/wallet/gettransactionreceiptbyid';
    case ListNodes = '/wallet/listnodes';
    case GetNodeInfo = '/wallet/getnodeinfo';
    case GetChainParameters = '/wallet/getchainparameters';
    case GetEnergyPrices = '/wallet/getenergyprices';
    case GetBandwidthPrices = '/wallet/getbandwidthprices';
    case GetBurnedTrx = '/wallet/getburntrx';
    case GetMemoFee = '/wallet/getmemofee';
    case GetTotalTransactions = '/wallet/totaltransaction';
    case TransferAsset = '/wallet/transferasset';
    case CreateAsset = '/wallet/createassetissue';
    case ParticipateAsset = '/wallet/participateassetissue';
    case UnfreezeAsset = '/wallet/unfreezeasset';
    case UpdateAsset = '/wallet/updateasset';
    case GetAssetByAccount = '/wallet/getassetissuebyaccount';
    case GetAssetById = '/wallet/getassetissuebyid';
    case GetAssetByName = '/wallet/getassetissuebyname';
    case GetAssetsByName = '/wallet/getassetissuelistbyname';
    case ListAssets = '/wallet/getassetissuelist';
    case ListAssetsPaginated = '/wallet/getpaginatedassetissuelist';
    case GetContract = '/wallet/getcontract';
    case GetContractInfo = '/wallet/getcontractinfo';
    case TriggerContract = '/wallet/triggersmartcontract';
    case TriggerConstantContract = '/wallet/triggerconstantcontract';
    case DeployContract = '/wallet/deploycontract';
    case EstimateEnergy = '/wallet/estimateenergy';
    case UpdateContractSetting = '/wallet/updatesetting';
    case UpdateContractEnergyLimit = '/wallet/updateenergylimit';
    case ClearContractAbi = '/wallet/clearabi';
    case ListWitnesses = '/wallet/listwitnesses';
    case ListWitnessesPaginated = '/wallet/getpaginatednowwitnesslist';
    case CreateWitness = '/wallet/createwitness';
    case UpdateWitness = '/wallet/updatewitness';
    case VoteWitnesses = '/wallet/votewitnessaccount';
    case GetBrokerage = '/wallet/getBrokerage';
    case UpdateBrokerage = '/wallet/updateBrokerage';
    case GetReward = '/wallet/getReward';
    case WithdrawReward = '/wallet/withdrawbalance';
    case GetNextMaintenanceTime = '/wallet/getnextmaintenancetime';
    case ListProposals = '/wallet/listproposals';
    case GetProposal = '/wallet/getproposalbyid';
    case CreateProposal = '/wallet/proposalcreate';
    case ApproveProposal = '/wallet/proposalapprove';
    case DeleteProposal = '/wallet/proposaldelete';
    case ListProposalsPaginated = '/wallet/getpaginatedproposallist';
    case GetPendingTransactions = '/wallet/gettransactionlistfrompending';
    case GetPendingTransaction = '/wallet/gettransactionfrompending';
    case GetPendingTransactionCount = '/wallet/getpendingsize';
    case CreateExchange = '/wallet/exchangecreate';
    case InjectExchangeLiquidity = '/wallet/exchangeinject';
    case WithdrawExchangeLiquidity = '/wallet/exchangewithdraw';
    case TradeExchange = '/wallet/exchangetransaction';
    case GetExchange = '/wallet/getexchangebyid';
    case ListExchanges = '/wallet/listexchanges';
    case ListExchangesPaginated = '/wallet/getpaginatedexchangelist';
    case CreateMarketOrder = '/wallet/marketsellasset';
    case CancelMarketOrder = '/wallet/marketcancelorder';
    case GetMarketOrdersByAccount = '/wallet/getmarketorderbyaccount';
    case GetMarketOrder = '/wallet/getmarketorderbyid';
    case GetMarketOrdersByPair = '/wallet/getmarketorderlistbypair';
    case GetMarketPairs = '/wallet/getmarketpairlist';
    case GetMarketPrices = '/wallet/getmarketpricebypair';
    case GetSpendingKey = '/wallet/getspendingkey';
    case GetExpandedSpendingKey = '/wallet/getexpandedspendingkey';
    case GetAkFromAsk = '/wallet/getakfromask';
    case GetNkFromNsk = '/wallet/getnkfromnsk';
    case GetIncomingViewingKey = '/wallet/getincomingviewingkey';
    case GetDiversifier = '/wallet/getdiversifier';
    case GetZenPaymentAddress = '/wallet/getzenpaymentaddress';
    case GetRandomCommitment = '/wallet/getrcm';
    case GetNewShieldedAddress = '/wallet/getnewshieldedaddress';
    case CreateShieldedContractParameters = '/wallet/createshieldedcontractparameters';
    case CreateShieldedContractParametersWithoutAsk = '/wallet/createshieldedcontractparameterswithoutask';
    case CreateSpendAuthorizationSignature = '/wallet/createspendauthsig';
    case GetShieldedTrc20TriggerInput = '/wallet/gettriggerinputforshieldedtrc20contract';
    case IsShieldedTrc20NoteSpent = '/wallet/isshieldedtrc20contractnotespent';
    case ScanShieldedTrc20NotesByIncomingViewingKey = '/wallet/scanshieldedtrc20notesbyivk';
    case ScanShieldedTrc20NotesByOutgoingViewingKey = '/wallet/scanshieldedtrc20notesbyovk';

    case GetConfirmedAccount = '/walletsolidity/getaccount';
    case GetConfirmedAccountById = '/walletsolidity/getaccountbyid';
    case GetConfirmedDelegatedResourceLegacy = '/walletsolidity/getdelegatedresource';
    case GetConfirmedDelegatedResourceIndexLegacy = '/walletsolidity/getdelegatedresourceaccountindex';
    case GetConfirmedBlock = '/walletsolidity/getblock';
    case GetConfirmedLatestBlock = '/walletsolidity/getnowblock';
    case GetConfirmedBlockByNumber = '/walletsolidity/getblockbynum';
    case GetConfirmedBlockById = '/walletsolidity/getblockbyid';
    case GetConfirmedLatestBlocks = '/walletsolidity/getblockbylatestnum';
    case GetConfirmedBlockRange = '/walletsolidity/getblockbylimitnext';
    case GetConfirmedTransaction = '/walletsolidity/gettransactionbyid';
    case GetConfirmedTransactionReceipt = '/walletsolidity/gettransactioninfobyid';
    case GetConfirmedBlockReceipts = '/walletsolidity/gettransactioninfobyblocknum';
    case GetConfirmedBlockTransactionCount = '/walletsolidity/gettransactioncountbyblocknum';
    case GetConfirmedNodeInfo = '/walletsolidity/getnodeinfo';
    case GetConfirmedBurnedTrx = '/walletsolidity/getburntrx';
    case GetConfirmedEnergyPrices = '/walletsolidity/getenergyprices';
    case GetConfirmedBandwidthPrices = '/walletsolidity/getbandwidthprices';
    case GetConfirmedAssetById = '/walletsolidity/getassetissuebyid';
    case GetConfirmedAssetByName = '/walletsolidity/getassetissuebyname';
    case GetConfirmedAssetsByName = '/walletsolidity/getassetissuelistbyname';
    case ListConfirmedAssets = '/walletsolidity/getassetissuelist';
    case ListConfirmedAssetsPaginated = '/walletsolidity/getpaginatedassetissuelist';
    case GetConfirmedAvailableUnfreezeCount = '/walletsolidity/getavailableunfreezecount';
    case GetConfirmedWithdrawableUnfreezeAmount = '/walletsolidity/getcanwithdrawunfreezeamount';
    case GetConfirmedDelegatableResourceAmount = '/walletsolidity/getcandelegatedmaxsize';
    case GetConfirmedDelegatedResourceV2 = '/walletsolidity/getdelegatedresourcev2';
    case GetConfirmedDelegatedResourceIndexV2 = '/walletsolidity/getdelegatedresourceaccountindexv2';
    case TriggerConfirmedConstantContract = '/walletsolidity/triggerconstantcontract';
    case EstimateConfirmedEnergy = '/walletsolidity/estimateenergy';
    case ListConfirmedWitnesses = '/walletsolidity/listwitnesses';
    case ListConfirmedWitnessesPaginated = '/walletsolidity/getpaginatednowwitnesslist';
    case GetConfirmedBrokerage = '/walletsolidity/getBrokerage';
    case GetConfirmedReward = '/walletsolidity/getReward';
    case GetConfirmedExchange = '/walletsolidity/getexchangebyid';
    case ListConfirmedExchanges = '/walletsolidity/listexchanges';
    case GetConfirmedMarketOrdersByAccount = '/walletsolidity/getmarketorderbyaccount';
    case GetConfirmedMarketOrder = '/walletsolidity/getmarketorderbyid';
    case GetConfirmedMarketOrdersByPair = '/walletsolidity/getmarketorderlistbypair';
    case GetConfirmedMarketPairs = '/walletsolidity/getmarketpairlist';
    case GetConfirmedMarketPrices = '/walletsolidity/getmarketpricebypair';
    case IsConfirmedShieldedTrc20NoteSpent = '/walletsolidity/isshieldedtrc20contractnotespent';
    case ScanConfirmedShieldedTrc20NotesByIncomingViewingKey = '/walletsolidity/scanshieldedtrc20notesbyivk';
    case ScanConfirmedShieldedTrc20NotesByOutgoingViewingKey = '/walletsolidity/scanshieldedtrc20notesbyovk';

    case GetIndexedAccount = '/v1/accounts/{address}';
    case GetIndexedAccountTransactions = '/v1/accounts/{address}/transactions';
    case GetIndexedAccountTrc20Transactions = '/v1/accounts/{address}/transactions/trc20';
    case GetIndexedAccountInternalTransactions = '/v1/accounts/{address}/internal-transactions';
    case GetIndexedTransactionInternalTransactions = '/v1/transactions/{transactionId}/internal-transactions';
    case GetIndexedAccountTrc20Balances = '/v1/accounts/{address}/trc20/balance';
    case GetIndexedTransactionEvents = '/v1/transactions/{transactionId}/events';
    case GetIndexedContractEvents = '/v1/contracts/{address}/events';
    case GetIndexedBlockEvents = '/v1/blocks/{blockNumber}/events';
    case GetIndexedLatestBlockEvents = '/v1/blocks/latest/events';
    case GetIndexedTrc20Information = '/v1/trc20/info';
    case GetIndexedContractTokens = '/v1/contracts/{contractAddress}/tokens';
    case ListIndexedAssets = '/v1/assets';
    case GetIndexedAsset = '/v1/assets/{identifier}';
    case GetIndexedBlockStatistics = '/v1/blocks/{blockNumber}/stats';

    case JsonRpc = '/jsonrpc';

    /**
     * Selects one endpoint pair for latest or confirmed state consistently.
     */
    public static function forConfirmation(
        ConfirmationLevel $level,
        self $latest,
        self $confirmed,
    ): self {
        return $level->isConfirmed() ? $confirmed : $latest;
    }

    /**
     * Returns the data source responsible for this endpoint path.
     */
    public function nodeRole(): NodeRole
    {
        return match (true) {
            str_starts_with($this->value, '/walletsolidity/') => NodeRole::SolidityNode,
            str_starts_with($this->value, '/v1/') => NodeRole::Indexer,
            $this === self::JsonRpc => NodeRole::JsonRpc,
            default => NodeRole::FullNode,
        };
    }

    /**
     * Returns GET for indexed endpoints and POST for native node interfaces.
     */
    public function httpMethod(): HttpMethod
    {
        return match ($this) {
            self::ListProposals,
            self::GetPendingTransactions,
            self::GetPendingTransactionCount,
            self::GetMarketPairs,
            self::GetConfirmedMarketPairs => HttpMethod::Get,
            default => $this->nodeRole() === NodeRole::Indexer ? HttpMethod::Get : HttpMethod::Post,
        };
    }

    /**
     * Builds a transport-independent request and safely fills path placeholders.
     *
     * @param array<string, mixed>      $parameters JSON body or query parameters.
     * @param array<string, int|string> $pathParameters Values for `{placeholder}` segments.
     * @param bool|null                 $retryable Explicit retry choice, or the endpoint-safe default.
     */
    public function request(
        array $parameters = [],
        array $pathParameters = [],
        ?bool $retryable = null,
    ): ApiRequest {
        $path = preg_replace_callback(
            '/\{([A-Za-z][A-Za-z0-9_]*)\}/',
            static function (array $matches) use ($pathParameters): string {
                $name = $matches[1];
                if (!array_key_exists($name, $pathParameters)) {
                    throw new ValidationException(sprintf('Missing endpoint path parameter `%s`.', $name));
                }

                return rawurlencode((string) $pathParameters[$name]);
            },
            $this->value,
        );

        if ($path === null || str_contains($path, '{')) {
            throw new ValidationException('The endpoint path could not be constructed.');
        }

        return new ApiRequest(
            $this->nodeRole(),
            $this->httpMethod(),
            $path,
            $parameters,
            $retryable ?? $this->isRetryableByDefault(),
        );
    }

    /**
     * Returns whether an endpoint can be repeated after an ambiguous transport failure.
     */
    public function isRetryableByDefault(): bool
    {
        return $this !== self::BroadcastTransaction && $this !== self::BroadcastHex;
    }
}
