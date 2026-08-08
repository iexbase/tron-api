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

namespace IEXBase\TronAPI\Service;

use IEXBase\TronAPI\Api\ApiClient;
use IEXBase\TronAPI\Api\DataDecoder;
use IEXBase\TronAPI\Api\Endpoint;
use IEXBase\TronAPI\Enum\ConfirmationLevel;
use IEXBase\TronAPI\Model\Account;
use IEXBase\TronAPI\Support\TextHelper;
use IEXBase\TronAPI\Transaction\Transaction;
use IEXBase\TronAPI\Transaction\TransactionFactory;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\Memo;

/**
 * Reads native account state and builds account-management transactions.
 */
final readonly class AccountService
{
    /**
     * Creates the service and a shared verified transaction factory.
     */
    public function __construct(
        private ApiClient $client,
        private TransactionFactory $transactions,
    ) {
    }

    /**
     * Returns the latest or confirmed account, or null when it is not activated.
     */
    public function get(Address $address, ConfirmationLevel $level = ConfirmationLevel::Confirmed): ?Account
    {
        $endpoint = Endpoint::forConfirmation($level, Endpoint::GetAccount, Endpoint::GetConfirmedAccount);
        $data = $this->addressData($endpoint, $address);

        return $data === [] ? null : Account::fromNodeData(DataDecoder::object($data, 'account'));
    }

    /**
     * Returns the latest or confirmed account selected by its permanent account ID.
     */
    public function getById(
        string $accountId,
        ConfirmationLevel $level = ConfirmationLevel::Confirmed,
    ): ?Account {
        $accountId = TextHelper::printableAscii($accountId, 'Account ID', 8, 32);
        $endpoint = Endpoint::forConfirmation(
            $level,
            Endpoint::GetAccountById,
            Endpoint::GetConfirmedAccountById,
        );
        $data = $this->requestData($endpoint, ['account_id' => $accountId]);

        return $data === [] ? null : Account::fromNodeData(DataDecoder::object($data, 'account'));
    }

    /**
     * Returns exact native account resource counters from current FullNode state.
     *
     * java-tron does not expose getaccountresource through walletsolidity, so
     * this query intentionally has no confirmed-state selector.
     *
     * @return array<string, mixed>
     */
    public function resources(Address $address): array
    {
        return DataDecoder::object(
            $this->addressData(Endpoint::GetAccountResource, $address),
            'account resources',
        );
    }

    /**
     * Returns the latest Bandwidth accounting fields from FullNode state.
     *
     * @return array<string, mixed>
     */
    public function bandwidth(Address $address): array
    {
        return DataDecoder::object(
            $this->addressData(Endpoint::GetAccountBandwidth, $address),
            'account bandwidth',
        );
    }

    /**
     * Builds an account activation transaction without exposing a private key.
     */
    public function create(
        Address $ownerAddress,
        Address $newAddress,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        return $this->transactions->createNativeContract(
            Endpoint::CreateAccount,
            'AccountCreateContract',
            $ownerAddress,
            ['account_address' => $newAddress],
            $memo,
            $permissionId,
        );
    }

    /**
     * Builds a transaction that sets or changes the account's human-readable name.
     */
    public function updateName(
        Address $ownerAddress,
        string $name,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        $name = TextHelper::utf8($name, 'Account name', 200);
        $fields = ['account_name' => $name];

        return $this->transactions->createNativeContract(
            Endpoint::UpdateAccount,
            'AccountUpdateContract',
            $ownerAddress,
            $fields,
            $memo,
            $permissionId,
        );
    }

    /**
     * Builds a transaction that permanently sets the account's printable ASCII ID.
     */
    public function setId(
        Address $ownerAddress,
        string $accountId,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        $accountId = TextHelper::printableAscii($accountId, 'Account ID', 8, 32);
        $fields = ['account_id' => $accountId];

        return $this->transactions->createNativeContract(
            Endpoint::SetAccountId,
            'SetAccountIdContract',
            $ownerAddress,
            $fields,
            $memo,
            $permissionId,
        );
    }

    /**
     * Requests one account-scoped native object using visible Base58 addresses.
     *
     * @return array<string, mixed>
     */
    private function addressData(Endpoint $endpoint, Address $address): array
    {
        return $this->requestData($endpoint, ['address' => $address->toBase58()]);
    }

    /**
     * Requests one account-domain object with visible string encoding enabled.
     *
     * @param array<string, mixed> $parameters Endpoint-specific account fields.
     * @return array<string, mixed>
     */
    private function requestData(Endpoint $endpoint, array $parameters): array
    {
        return DataDecoder::object(
            $this->client->request($endpoint->request([
                ...$parameters,
                'visible' => true,
            ]))->data(),
            'account data',
        );
    }
}
