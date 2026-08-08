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

namespace IEXBase\TronAPI\Enum;

use IEXBase\TronAPI\Exception\ValidationException;

/**
 * Mirrors the current protocol ContractType IDs used by active permission bits.
 */
enum ContractType: int
{
    case AccountCreateContract = 0;
    case TransferContract = 1;
    case TransferAssetContract = 2;
    case VoteAssetContract = 3;
    case VoteWitnessContract = 4;
    case WitnessCreateContract = 5;
    case AssetIssueContract = 6;
    case WitnessUpdateContract = 8;
    case ParticipateAssetIssueContract = 9;
    case AccountUpdateContract = 10;
    case FreezeBalanceContract = 11;
    case UnfreezeBalanceContract = 12;
    case WithdrawBalanceContract = 13;
    case UnfreezeAssetContract = 14;
    case UpdateAssetContract = 15;
    case ProposalCreateContract = 16;
    case ProposalApproveContract = 17;
    case ProposalDeleteContract = 18;
    case SetAccountIdContract = 19;
    case CustomContract = 20;
    case CreateSmartContract = 30;
    case TriggerSmartContract = 31;
    case GetContract = 32;
    case UpdateSettingContract = 33;
    case ExchangeCreateContract = 41;
    case ExchangeInjectContract = 42;
    case ExchangeWithdrawContract = 43;
    case ExchangeTransactionContract = 44;
    case UpdateEnergyLimitContract = 45;
    case AccountPermissionUpdateContract = 46;
    case ClearABIContract = 48;
    case UpdateBrokerageContract = 49;
    case ShieldedTransferContract = 51;
    case MarketSellAssetContract = 52;
    case MarketCancelOrderContract = 53;
    case FreezeBalanceV2Contract = 54;
    case UnfreezeBalanceV2Contract = 55;
    case WithdrawExpireUnfreezeContract = 56;
    case DelegateResourceContract = 57;
    case UnDelegateResourceContract = 58;
    case CancelAllUnfreezeV2Contract = 59;

    /**
     * Resolves the exact protocol contract type name used in transaction JSON.
     */
    public static function fromProtocolName(string $name): self
    {
        foreach (self::cases() as $case) {
            if ($case->name === $name) {
                return $case;
            }
        }

        throw new ValidationException(sprintf('Unknown protocol contract type `%s`.', $name));
    }
}
