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

namespace IEXBase\TronAPI\Transaction;

use IEXBase\TronAPI\Contract\Abi;
use IEXBase\TronAPI\Contract\AbiEntry;
use IEXBase\TronAPI\Contract\AbiParameter;
use IEXBase\TronAPI\Encoding\Hex;
use IEXBase\TronAPI\Encoding\Protobuf;
use IEXBase\TronAPI\Exception\TransactionException;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\ByteString;

/**
 * Encodes approved native-system-contract fields with official TRON schemas.
 *
 * Keeping every field number and wire kind in one registry prevents services
 * from implementing protobuf rules independently. The resulting bytes are used
 * only for integrity comparison; transaction creation remains a node operation.
 *
 * @phpstan-type FieldSchema array{number: int, kind: string, message?: string, repeated?: bool}
 * @phpstan-type MessageSchema array<string, FieldSchema>
 */
final class ContractWireEncoder
{
    /**
     * Official transaction-contract and nested-message field definitions.
     *
     * @var array<string, MessageSchema>
     */
    private const array SCHEMAS = [
        'AccountCreateContract' => [
            'owner_address' => ['number' => 1, 'kind' => 'address'],
            'account_address' => ['number' => 2, 'kind' => 'address'],
            'type' => ['number' => 3, 'kind' => 'integer'],
        ],
        'AccountUpdateContract' => [
            'account_name' => ['number' => 1, 'kind' => 'text'],
            'owner_address' => ['number' => 2, 'kind' => 'address'],
        ],
        'SetAccountIdContract' => [
            'account_id' => ['number' => 1, 'kind' => 'text'],
            'owner_address' => ['number' => 2, 'kind' => 'address'],
        ],
        'AccountPermissionUpdateContract' => [
            'owner_address' => ['number' => 1, 'kind' => 'address'],
            'owner' => ['number' => 2, 'kind' => 'message', 'message' => 'Permission'],
            'witness' => ['number' => 3, 'kind' => 'message', 'message' => 'Permission'],
            'actives' => ['number' => 4, 'kind' => 'message', 'message' => 'Permission', 'repeated' => true],
        ],
        'TransferContract' => [
            'owner_address' => ['number' => 1, 'kind' => 'address'],
            'to_address' => ['number' => 2, 'kind' => 'address'],
            'amount' => ['number' => 3, 'kind' => 'integer'],
        ],
        'FreezeBalanceContract' => [
            'owner_address' => ['number' => 1, 'kind' => 'address'],
            'frozen_balance' => ['number' => 2, 'kind' => 'integer'],
            'frozen_duration' => ['number' => 3, 'kind' => 'integer'],
            'resource' => ['number' => 10, 'kind' => 'resource'],
            'receiver_address' => ['number' => 15, 'kind' => 'address'],
        ],
        'UnfreezeBalanceContract' => [
            'owner_address' => ['number' => 1, 'kind' => 'address'],
            'resource' => ['number' => 10, 'kind' => 'resource'],
            'receiver_address' => ['number' => 15, 'kind' => 'address'],
        ],
        'WithdrawBalanceContract' => [
            'owner_address' => ['number' => 1, 'kind' => 'address'],
        ],
        'FreezeBalanceV2Contract' => [
            'owner_address' => ['number' => 1, 'kind' => 'address'],
            'frozen_balance' => ['number' => 2, 'kind' => 'integer'],
            'resource' => ['number' => 3, 'kind' => 'resource'],
        ],
        'UnfreezeBalanceV2Contract' => [
            'owner_address' => ['number' => 1, 'kind' => 'address'],
            'unfreeze_balance' => ['number' => 2, 'kind' => 'integer'],
            'resource' => ['number' => 3, 'kind' => 'resource'],
        ],
        'WithdrawExpireUnfreezeContract' => [
            'owner_address' => ['number' => 1, 'kind' => 'address'],
        ],
        'DelegateResourceContract' => [
            'owner_address' => ['number' => 1, 'kind' => 'address'],
            'resource' => ['number' => 2, 'kind' => 'resource'],
            'balance' => ['number' => 3, 'kind' => 'integer'],
            'receiver_address' => ['number' => 4, 'kind' => 'address'],
            'lock' => ['number' => 5, 'kind' => 'boolean'],
            'lock_period' => ['number' => 6, 'kind' => 'integer'],
        ],
        'UnDelegateResourceContract' => [
            'owner_address' => ['number' => 1, 'kind' => 'address'],
            'resource' => ['number' => 2, 'kind' => 'resource'],
            'balance' => ['number' => 3, 'kind' => 'integer'],
            'receiver_address' => ['number' => 4, 'kind' => 'address'],
        ],
        'CancelAllUnfreezeV2Contract' => [
            'owner_address' => ['number' => 1, 'kind' => 'address'],
        ],
        'AssetIssueContract' => [
            'owner_address' => ['number' => 1, 'kind' => 'address'],
            'name' => ['number' => 2, 'kind' => 'text'],
            'abbr' => ['number' => 3, 'kind' => 'text'],
            'total_supply' => ['number' => 4, 'kind' => 'integer'],
            'frozen_supply' => ['number' => 5, 'kind' => 'message', 'message' => 'FrozenSupply', 'repeated' => true],
            'trx_num' => ['number' => 6, 'kind' => 'integer'],
            'precision' => ['number' => 7, 'kind' => 'integer'],
            'num' => ['number' => 8, 'kind' => 'integer'],
            'start_time' => ['number' => 9, 'kind' => 'integer'],
            'end_time' => ['number' => 10, 'kind' => 'integer'],
            'order' => ['number' => 11, 'kind' => 'integer'],
            'vote_score' => ['number' => 16, 'kind' => 'integer'],
            'description' => ['number' => 20, 'kind' => 'text'],
            'url' => ['number' => 21, 'kind' => 'text'],
            'free_asset_net_limit' => ['number' => 22, 'kind' => 'integer'],
            'public_free_asset_net_limit' => ['number' => 23, 'kind' => 'integer'],
            'public_free_asset_net_usage' => ['number' => 24, 'kind' => 'integer'],
            'public_latest_free_net_time' => ['number' => 25, 'kind' => 'integer'],
            'id' => ['number' => 41, 'kind' => 'text'],
        ],
        'TransferAssetContract' => [
            'asset_name' => ['number' => 1, 'kind' => 'text'],
            'owner_address' => ['number' => 2, 'kind' => 'address'],
            'to_address' => ['number' => 3, 'kind' => 'address'],
            'amount' => ['number' => 4, 'kind' => 'integer'],
        ],
        'UnfreezeAssetContract' => [
            'owner_address' => ['number' => 1, 'kind' => 'address'],
        ],
        'UpdateAssetContract' => [
            'owner_address' => ['number' => 1, 'kind' => 'address'],
            'description' => ['number' => 2, 'kind' => 'text'],
            'url' => ['number' => 3, 'kind' => 'text'],
            'new_limit' => ['number' => 4, 'kind' => 'integer'],
            'new_public_limit' => ['number' => 5, 'kind' => 'integer'],
        ],
        'ParticipateAssetIssueContract' => [
            'owner_address' => ['number' => 1, 'kind' => 'address'],
            'to_address' => ['number' => 2, 'kind' => 'address'],
            'asset_name' => ['number' => 3, 'kind' => 'text'],
            'amount' => ['number' => 4, 'kind' => 'integer'],
        ],
        'WitnessCreateContract' => [
            'owner_address' => ['number' => 1, 'kind' => 'address'],
            'url' => ['number' => 2, 'kind' => 'text'],
        ],
        'WitnessUpdateContract' => [
            'owner_address' => ['number' => 1, 'kind' => 'address'],
            'update_url' => ['number' => 12, 'kind' => 'text'],
        ],
        'VoteWitnessContract' => [
            'owner_address' => ['number' => 1, 'kind' => 'address'],
            'votes' => ['number' => 2, 'kind' => 'message', 'message' => 'WitnessVote', 'repeated' => true],
            'support' => ['number' => 3, 'kind' => 'boolean'],
        ],
        'ProposalApproveContract' => [
            'owner_address' => ['number' => 1, 'kind' => 'address'],
            'proposal_id' => ['number' => 2, 'kind' => 'integer'],
            'is_add_approval' => ['number' => 3, 'kind' => 'boolean'],
        ],
        'ProposalCreateContract' => [
            'owner_address' => ['number' => 1, 'kind' => 'address'],
            'parameters' => ['number' => 2, 'kind' => 'message', 'message' => 'ProposalParameter', 'repeated' => true],
        ],
        'ProposalDeleteContract' => [
            'owner_address' => ['number' => 1, 'kind' => 'address'],
            'proposal_id' => ['number' => 2, 'kind' => 'integer'],
        ],
        'CreateSmartContract' => [
            'owner_address' => ['number' => 1, 'kind' => 'address'],
            'new_contract' => ['number' => 2, 'kind' => 'message', 'message' => 'SmartContract'],
            'call_token_value' => ['number' => 3, 'kind' => 'integer'],
            'token_id' => ['number' => 4, 'kind' => 'integer'],
        ],
        'TriggerSmartContract' => [
            'owner_address' => ['number' => 1, 'kind' => 'address'],
            'contract_address' => ['number' => 2, 'kind' => 'address'],
            'call_value' => ['number' => 3, 'kind' => 'integer'],
            'data' => ['number' => 4, 'kind' => 'hex'],
            'call_token_value' => ['number' => 5, 'kind' => 'integer'],
            'token_id' => ['number' => 6, 'kind' => 'integer'],
        ],
        'ClearABIContract' => [
            'owner_address' => ['number' => 1, 'kind' => 'address'],
            'contract_address' => ['number' => 2, 'kind' => 'address'],
        ],
        'UpdateSettingContract' => [
            'owner_address' => ['number' => 1, 'kind' => 'address'],
            'contract_address' => ['number' => 2, 'kind' => 'address'],
            'consume_user_resource_percent' => ['number' => 3, 'kind' => 'integer'],
        ],
        'UpdateEnergyLimitContract' => [
            'owner_address' => ['number' => 1, 'kind' => 'address'],
            'contract_address' => ['number' => 2, 'kind' => 'address'],
            'origin_energy_limit' => ['number' => 3, 'kind' => 'integer'],
        ],
        'ExchangeCreateContract' => [
            'owner_address' => ['number' => 1, 'kind' => 'address'],
            'first_token_id' => ['number' => 2, 'kind' => 'text'],
            'first_token_balance' => ['number' => 3, 'kind' => 'integer'],
            'second_token_id' => ['number' => 4, 'kind' => 'text'],
            'second_token_balance' => ['number' => 5, 'kind' => 'integer'],
        ],
        'ExchangeInjectContract' => [
            'owner_address' => ['number' => 1, 'kind' => 'address'],
            'exchange_id' => ['number' => 2, 'kind' => 'integer'],
            'token_id' => ['number' => 3, 'kind' => 'text'],
            'quant' => ['number' => 4, 'kind' => 'integer'],
        ],
        'ExchangeWithdrawContract' => [
            'owner_address' => ['number' => 1, 'kind' => 'address'],
            'exchange_id' => ['number' => 2, 'kind' => 'integer'],
            'token_id' => ['number' => 3, 'kind' => 'text'],
            'quant' => ['number' => 4, 'kind' => 'integer'],
        ],
        'ExchangeTransactionContract' => [
            'owner_address' => ['number' => 1, 'kind' => 'address'],
            'exchange_id' => ['number' => 2, 'kind' => 'integer'],
            'token_id' => ['number' => 3, 'kind' => 'text'],
            'quant' => ['number' => 4, 'kind' => 'integer'],
            'expected' => ['number' => 5, 'kind' => 'integer'],
        ],
        'UpdateBrokerageContract' => [
            'owner_address' => ['number' => 1, 'kind' => 'address'],
            'brokerage' => ['number' => 2, 'kind' => 'integer'],
        ],
        'MarketSellAssetContract' => [
            'owner_address' => ['number' => 1, 'kind' => 'address'],
            'sell_token_id' => ['number' => 2, 'kind' => 'text'],
            'sell_token_quantity' => ['number' => 3, 'kind' => 'integer'],
            'buy_token_id' => ['number' => 4, 'kind' => 'text'],
            'buy_token_quantity' => ['number' => 5, 'kind' => 'integer'],
        ],
        'MarketCancelOrderContract' => [
            'owner_address' => ['number' => 1, 'kind' => 'address'],
            'order_id' => ['number' => 2, 'kind' => 'hex'],
        ],
        'FrozenSupply' => [
            'frozen_amount' => ['number' => 1, 'kind' => 'integer'],
            'frozen_days' => ['number' => 2, 'kind' => 'integer'],
        ],
        'WitnessVote' => [
            'vote_address' => ['number' => 1, 'kind' => 'address'],
            'vote_count' => ['number' => 2, 'kind' => 'integer'],
        ],
        'ProposalParameter' => [
            'key' => ['number' => 1, 'kind' => 'integer'],
            'value' => ['number' => 2, 'kind' => 'integer'],
        ],
        'Permission' => [
            'type' => ['number' => 1, 'kind' => 'integer'],
            'id' => ['number' => 2, 'kind' => 'integer'],
            'permission_name' => ['number' => 3, 'kind' => 'text'],
            'threshold' => ['number' => 4, 'kind' => 'integer'],
            'parent_id' => ['number' => 5, 'kind' => 'integer'],
            'operations' => ['number' => 6, 'kind' => 'hex'],
            'keys' => ['number' => 7, 'kind' => 'message', 'message' => 'PermissionKey', 'repeated' => true],
        ],
        'PermissionKey' => [
            'address' => ['number' => 1, 'kind' => 'address'],
            'weight' => ['number' => 2, 'kind' => 'integer'],
        ],
        'SmartContract' => [
            'origin_address' => ['number' => 1, 'kind' => 'address'],
            'contract_address' => ['number' => 2, 'kind' => 'address'],
            'abi' => ['number' => 3, 'kind' => 'message', 'message' => 'Abi'],
            'bytecode' => ['number' => 4, 'kind' => 'hex'],
            'call_value' => ['number' => 5, 'kind' => 'integer'],
            'consume_user_resource_percent' => ['number' => 6, 'kind' => 'integer'],
            'name' => ['number' => 7, 'kind' => 'text'],
            'origin_energy_limit' => ['number' => 8, 'kind' => 'integer'],
            'code_hash' => ['number' => 9, 'kind' => 'hex'],
            'trx_hash' => ['number' => 10, 'kind' => 'hex'],
            'version' => ['number' => 11, 'kind' => 'integer'],
        ],
        'Abi' => [
            'entrys' => ['number' => 1, 'kind' => 'message', 'message' => 'AbiEntry', 'repeated' => true],
        ],
        'AbiEntry' => [
            'anonymous' => ['number' => 1, 'kind' => 'boolean'],
            'constant' => ['number' => 2, 'kind' => 'boolean'],
            'name' => ['number' => 3, 'kind' => 'text'],
            'inputs' => ['number' => 4, 'kind' => 'message', 'message' => 'AbiParameter', 'repeated' => true],
            'outputs' => ['number' => 5, 'kind' => 'message', 'message' => 'AbiParameter', 'repeated' => true],
            'type' => ['number' => 6, 'kind' => 'abi_entry_type'],
            'payable' => ['number' => 7, 'kind' => 'boolean'],
            'state_mutability' => ['number' => 8, 'kind' => 'abi_mutability'],
        ],
        'AbiParameter' => [
            'indexed' => ['number' => 1, 'kind' => 'boolean'],
            'name' => ['number' => 2, 'kind' => 'text'],
            'type' => ['number' => 3, 'kind' => 'text'],
        ],
    ];

    /**
     * Encodes one complete approved contract parameter message.
     *
     * @param array<string, mixed> $contractFields Fields excluding owner_address.
     */
    public function encode(string $contractType, Address $ownerAddress, array $contractFields): string
    {
        return $this->encodeMessage($contractType, [
            'owner_address' => $ownerAddress,
            ...$contractFields,
        ]);
    }

    /**
     * Encodes one schema-bound message and rejects unregistered expected fields.
     *
     * @param array<string, mixed> $values Message values.
     */
    private function encodeMessage(string $schemaName, array $values): string
    {
        $schema = self::SCHEMAS[$schemaName] ?? null;
        if ($schema === null) {
            throw new TransactionException(sprintf(
                'No protobuf integrity schema is registered for `%s`.',
                $schemaName,
            ));
        }

        $unknownFields = array_diff(array_keys($values), array_keys($schema));
        if ($unknownFields !== []) {
            throw new TransactionException(sprintf(
                'The `%s` integrity schema does not define fields: %s.',
                $schemaName,
                implode(', ', $unknownFields),
            ));
        }

        uasort(
            $schema,
            static fn (array $left, array $right): int => $left['number'] <=> $right['number'],
        );

        $bytes = '';
        foreach ($schema as $fieldName => $fieldSchema) {
            if (!array_key_exists($fieldName, $values)) {
                continue;
            }

            $value = $values[$fieldName];
            if (($fieldSchema['repeated'] ?? false) === true) {
                if (!is_array($value) || !array_is_list($value)) {
                    throw new TransactionException(sprintf('The `%s.%s` field must be a list.', $schemaName, $fieldName));
                }
                foreach ($value as $item) {
                    $bytes .= $this->encodeField($fieldSchema, $item, $schemaName . '.' . $fieldName);
                }
                continue;
            }

            $bytes .= $this->encodeField($fieldSchema, $value, $schemaName . '.' . $fieldName);
        }

        return $bytes;
    }

    /**
     * Encodes one scalar or nested field according to its centralized schema.
     *
     * @param FieldSchema $schema Field definition.
     */
    private function encodeField(array $schema, mixed $value, string $label): string
    {
        $number = $schema['number'];

        return match ($schema['kind']) {
            'address' => Protobuf::bytesField($number, $this->addressBytes($value, $label)),
            'text' => Protobuf::bytesField($number, $this->text($value, $label)),
            'hex' => Protobuf::bytesField($number, $this->hexBytes($value, $label)),
            'integer' => Protobuf::integerField($number, $this->integer($value, $label)),
            'boolean' => Protobuf::booleanField($number, $this->boolean($value, $label)),
            'resource' => Protobuf::integerField($number, $this->resourceCode($value, $label)),
            'abi_entry_type' => Protobuf::integerField($number, $this->abiEntryType($value, $label)),
            'abi_mutability' => Protobuf::integerField($number, $this->abiMutability($value, $label)),
            'message' => $this->nestedMessageField($schema, $value, $label),
            default => throw new TransactionException(sprintf('The `%s` protobuf wire kind is unsupported.', $label)),
        };
    }

    /**
     * Encodes a schema-bound nested message after validating its descriptor.
     *
     * @param FieldSchema $schema Field definition.
     */
    private function nestedMessageField(array $schema, mixed $value, string $label): string
    {
        $messageSchema = $schema['message'] ?? null;
        if (!is_string($messageSchema) || $messageSchema === '') {
            throw new TransactionException(sprintf('The `%s` nested protobuf schema is missing.', $label));
        }

        return Protobuf::messageField(
            $schema['number'],
            $this->encodeMessage(
                $messageSchema,
                $this->messageValues($value, $messageSchema, $label),
            ),
        );
    }

    /**
     * Converts typed nested values into their official protobuf field shapes.
     *
     * @return array<string, mixed>
     */
    private function messageValues(mixed $value, string $schemaName, string $label): array
    {
        if ($value instanceof Permission) {
            return $value->toNodeData();
        }
        if ($value instanceof Abi) {
            return $value->protocolFields();
        }
        if ($value instanceof AbiEntry) {
            return $value->protocolFields();
        }
        if ($value instanceof AbiParameter) {
            return $value->protocolFields();
        }
        if (!is_array($value)) {
            throw new TransactionException(sprintf('The `%s` field must contain a `%s` message.', $label, $schemaName));
        }

        $result = [];
        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                throw new TransactionException(sprintf('The `%s` message must use string field names.', $label));
            }
            $result[$key] = $item;
        }

        return $result;
    }

    /**
     * Converts an Address object or strict textual address into 21 protocol bytes.
     */
    private function addressBytes(mixed $value, string $label): string
    {
        if ($value instanceof Address) {
            return $value->bytes();
        }
        if (!is_string($value)) {
            throw new TransactionException(sprintf('The `%s` field must be an Address.', $label));
        }

        return Address::fromString($value)->bytes();
    }

    /**
     * Returns exact text bytes without conflating them with hexadecimal values.
     */
    private function text(mixed $value, string $label): string
    {
        if (!is_string($value)) {
            throw new TransactionException(sprintf('The `%s` field must be text.', $label));
        }

        return $value;
    }

    /**
     * Converts a ByteString or hexadecimal field into its wire bytes.
     */
    private function hexBytes(mixed $value, string $label): string
    {
        if ($value instanceof ByteString) {
            return $value->bytes();
        }
        if (!is_string($value)) {
            throw new TransactionException(sprintf('The `%s` field must be hexadecimal bytes.', $label));
        }

        return $value === '' ? '' : Hex::toBytes($value);
    }

    /**
     * Returns an exact canonical signed decimal integer accepted by Protobuf.
     */
    private function integer(mixed $value, string $label): int|string
    {
        if (!is_int($value) && (!is_string($value) || preg_match('/^-?(?:0|[1-9][0-9]*)$/D', $value) !== 1)) {
            throw new TransactionException(sprintf('The `%s` field must be a canonical integer.', $label));
        }

        return $value;
    }

    /**
     * Returns a strict boolean value for a protobuf bool field.
     */
    private function boolean(mixed $value, string $label): bool
    {
        if (!is_bool($value)) {
            throw new TransactionException(sprintf('The `%s` field must be boolean.', $label));
        }

        return $value;
    }

    /**
     * Maps the visible JSON resource name onto the protocol enum value.
     */
    private function resourceCode(mixed $value, string $label): int
    {
        return match ($value) {
            0, 'BANDWIDTH' => 0,
            1, 'ENERGY' => 1,
            2, 'TRON_POWER' => 2,
            default => throw new TransactionException(sprintf('The `%s` field contains an unknown resource.', $label)),
        };
    }

    /**
     * Maps a standard Solidity ABI entry type onto SmartContract.ABI.EntryType.
     */
    private function abiEntryType(mixed $value, string $label): int
    {
        return match (is_string($value) ? strtolower($value) : $value) {
            0, 'unknownentrytype' => 0,
            1, 'constructor' => 1,
            2, 'function' => 2,
            3, 'event' => 3,
            4, 'fallback' => 4,
            5, 'receive' => 5,
            6, 'error' => 6,
            default => throw new TransactionException(sprintf('The `%s` field contains an unknown ABI entry type.', $label)),
        };
    }

    /**
     * Maps Solidity mutability onto SmartContract.ABI.StateMutabilityType.
     */
    private function abiMutability(mixed $value, string $label): int
    {
        return match (is_string($value) ? strtolower($value) : $value) {
            0, 'unknownmutabilitytype' => 0,
            1, 'pure' => 1,
            2, 'view' => 2,
            3, 'nonpayable' => 3,
            4, 'payable' => 4,
            default => throw new TransactionException(sprintf('The `%s` field contains unknown ABI mutability.', $label)),
        };
    }
}
