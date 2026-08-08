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
use IEXBase\TronAPI\Contract\AbiCodec;
use IEXBase\TronAPI\Contract\ContractCall;
use IEXBase\TronAPI\Contract\ContractCallResult;
use IEXBase\TronAPI\Contract\ContractDefinition;
use IEXBase\TronAPI\Contract\DecodedFunctionCall;
use IEXBase\TronAPI\Contract\DeploymentRequest;
use IEXBase\TronAPI\Contract\DeploymentTransaction;
use IEXBase\TronAPI\Contract\EventLog;
use IEXBase\TronAPI\Contract\Abi;
use IEXBase\TronAPI\Contract\Token\Trc20Contract;
use IEXBase\TronAPI\Contract\Token\Trc721Contract;
use IEXBase\TronAPI\Contract\Token\Trc1155Contract;
use IEXBase\TronAPI\Enum\ConfirmationLevel;
use IEXBase\TronAPI\Exception\ContractException;
use IEXBase\TronAPI\Exception\ContractExecutionException;
use IEXBase\TronAPI\Exception\NodeException;
use IEXBase\TronAPI\Model\EnergyEstimate;
use IEXBase\TronAPI\Model\TransactionReceipt;
use IEXBase\TronAPI\Transaction\Transaction;
use IEXBase\TronAPI\Transaction\TransactionFactory;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\Amount;
use IEXBase\TronAPI\Value\ByteString;
use IEXBase\TronAPI\Value\Memo;

/**
 * Loads contracts and provides ABI-aware read, simulation, estimate, and send flows.
 */
final readonly class ContractService
{
    /**
     * Creates the service with one shared codec and verified transaction factory.
     */
    public function __construct(
        private ApiClient $client,
        private TransactionFactory $transactions,
        private AbiCodec $codec = new AbiCodec(),
    ) {
    }

    /**
     * Returns a deployed contract definition, or null for a non-contract address.
     */
    public function get(Address $contractAddress): ?ContractDefinition
    {
        $data = $this->client->request(Endpoint::GetContract->request([
            'value' => $contractAddress->toBase58(),
            'visible' => true,
        ]))->data();

        return $data === []
            ? null
            : ContractDefinition::fromNodeData(DataDecoder::object($data, 'contract'), $contractAddress);
    }

    /**
     * Returns dynamic Energy model and runtime metadata for one contract.
     *
     * @return array<string, mixed>
     */
    public function information(Address $contractAddress): array
    {
        return DataDecoder::object($this->client->request(Endpoint::GetContractInfo->request([
            'value' => $contractAddress->toBase58(),
            'visible' => true,
        ]))->data(), 'contract information');
    }

    /**
     * Creates a canonical or custom-ABI TRC-20 contract client.
     */
    public function trc20(
        Address $contractAddress,
        Address $callerAddress,
        ?Abi $abi = null,
    ): Trc20Contract {
        return new Trc20Contract($this, $contractAddress, $callerAddress, $abi);
    }

    /**
     * Creates a canonical or custom-ABI TRC-721 contract client.
     */
    public function trc721(
        Address $contractAddress,
        Address $callerAddress,
        ?Abi $abi = null,
    ): Trc721Contract {
        return new Trc721Contract($this, $contractAddress, $callerAddress, $abi);
    }

    /**
     * Creates a canonical or custom-ABI TRC-1155 contract client.
     */
    public function trc1155(
        Address $contractAddress,
        Address $callerAddress,
        ?Abi $abi = null,
    ): Trc1155Contract {
        return new Trc1155Contract($this, $contractAddress, $callerAddress, $abi);
    }

    /**
     * Executes a pure/view function against latest or confirmed node state.
     */
    public function read(
        ContractCall $call,
        ConfirmationLevel $level = ConfirmationLevel::Confirmed,
    ): ContractCallResult {
        if (!$call->function->isReadOnly()) {
            throw new ContractException('ContractService::read() accepts only pure or view functions.');
        }

        return $this->simulate($call, $level);
    }

    /**
     * Simulates any ABI function and decodes its returned values without broadcasting.
     */
    public function simulate(
        ContractCall $call,
        ConfirmationLevel $level = ConfirmationLevel::Latest,
    ): ContractCallResult {
        $endpoint = Endpoint::forConfirmation(
            $level,
            Endpoint::TriggerConstantContract,
            Endpoint::TriggerConfirmedConstantContract,
        );
        $response = $this->client->request($endpoint->request($call->nodeParameters()));
        try {
            $data = $response->requireAccepted()->data();
        } catch (NodeException $exception) {
            $failureData = $response->value('constant_result', 0);
            if (is_string($failureData) && $failureData !== '') {
                throw new ContractExecutionException(
                    $this->codec->decodeFailure($failureData, $call->abi),
                    $exception,
                );
            }

            throw $exception;
        }
        $resultValues = $data['constant_result'] ?? [];
        if (!is_array($resultValues) || !array_is_list($resultValues)) {
            throw new ContractException('The node returned a malformed constant_result list.');
        }

        $rawResults = array_map(
            static fn (mixed $value): ByteString => ByteString::fromHex(
                DataDecoder::string($value, 'constant_result[]'),
            ),
            $resultValues,
        );
        $firstResult = $rawResults[0] ?? ByteString::fromBytes('');
        if ($rawResults === [] && $call->function->outputs() !== []) {
            throw new ContractException('The node returned no data for a function that declares outputs.');
        }

        return new ContractCallResult(
            $this->codec->decodeFunctionResult($call->function, $firstResult->toHex(false)),
            $rawResults,
            DataDecoder::unsignedDecimal($data['energy_used'] ?? 0, 'energy_used'),
            DataDecoder::unsignedDecimal($data['energy_penalty'] ?? 0, 'energy_penalty'),
            DataDecoder::object($data, 'contract simulation'),
        );
    }

    /**
     * Estimates Energy using a node that has the estimateenergy feature enabled.
     */
    public function estimateEnergy(
        ContractCall $call,
        ConfirmationLevel $level = ConfirmationLevel::Latest,
    ): EnergyEstimate {
        $endpoint = Endpoint::forConfirmation(
            $level,
            Endpoint::EstimateEnergy,
            Endpoint::EstimateConfirmedEnergy,
        );
        $data = $this->client->request($endpoint->request($call->nodeParameters()))
            ->requireAccepted()
            ->data();

        return EnergyEstimate::fromNodeData(DataDecoder::object($data, 'energy estimate'));
    }

    /**
     * Builds and verifies a deployment transaction and its deterministic address.
     */
    public function deploy(DeploymentRequest $request): DeploymentTransaction
    {
        $transaction = $this->transactions->createNativeContract(
            Endpoint::DeployContract,
            'CreateSmartContract',
            $request->ownerAddress,
            $request->contractFields(),
            $request->memo,
            $request->permissionId,
            $request->nodeFields(),
            feeLimit: $request->feeLimit,
        );

        return new DeploymentTransaction($transaction, $request->ownerAddress);
    }

    /**
     * Builds a verified TriggerSmartContract transaction with an explicit fee cap.
     */
    public function createTransaction(
        ContractCall $call,
        Amount $feeLimit,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        if ($feeLimit->isZero()) {
            throw new ContractException('A state-changing contract call requires a positive fee limit.');
        }

        return $this->transactions->createNativeContract(
            Endpoint::TriggerContract,
            'TriggerSmartContract',
            $call->callerAddress,
            $call->contractFields(),
            $memo,
            $permissionId,
            $call->transactionRequestFields(),
            feeLimit: $feeLimit,
        );
    }

    /**
     * Decodes every TVM log in a transaction receipt against a contract ABI.
     *
     * Unknown topics remain available as undecoded EventLog values.
     *
     * @return list<EventLog>
     */
    public function decodeReceiptEvents(TransactionReceipt $receipt, Abi $abi): array
    {
        $logs = DataDecoder::objectList($receipt->rawData()['log'] ?? [], 'log');

        return array_map(
            fn (array $log): EventLog => EventLog::fromNodeData($log, $abi, $this->codec),
            $logs,
        );
    }

    /**
     * Resolves and decodes complete smart-contract calldata against a supplied ABI.
     */
    public function decodeFunctionCall(Abi $abi, string $data): DecodedFunctionCall
    {
        return $this->codec->decodeFunctionCall($abi, $data);
    }

    /**
     * Decodes one native log, optionally selecting an anonymous event signature.
     *
     * @param array<string, mixed> $log Native receipt log object.
     */
    public function decodeEvent(
        array $log,
        Abi $abi,
        ?string $eventSignature = null,
    ): EventLog {
        return EventLog::fromNodeData($log, $abi, $this->codec, $eventSignature);
    }

    /**
     * Builds a verified transaction that changes caller Energy responsibility.
     */
    public function updateCallerEnergyPercent(
        Address $ownerAddress,
        Address $contractAddress,
        int $percent,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        if ($percent < 0 || $percent > 100) {
            throw new ContractException('Contract caller Energy percent must be between 0 and 100.');
        }

        $fields = [
            'contract_address' => $contractAddress,
            'consume_user_resource_percent' => $percent,
        ];

        return $this->transactions->createNativeContract(
            Endpoint::UpdateContractSetting,
            'UpdateSettingContract',
            $ownerAddress,
            $fields,
            $memo,
            $permissionId,
        );
    }

    /**
     * Builds a verified transaction that changes the creator's Energy limit.
     */
    public function updateOriginEnergyLimit(
        Address $ownerAddress,
        Address $contractAddress,
        int $energyLimit,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        if ($energyLimit <= 0) {
            throw new ContractException('A contract origin Energy limit must be positive.');
        }

        $fields = ['contract_address' => $contractAddress, 'origin_energy_limit' => $energyLimit];

        return $this->transactions->createNativeContract(
            Endpoint::UpdateContractEnergyLimit,
            'UpdateEnergyLimitContract',
            $ownerAddress,
            $fields,
            $memo,
            $permissionId,
        );
    }

    /**
     * Builds a verified transaction that irreversibly clears the stored contract ABI.
     */
    public function clearAbi(
        Address $ownerAddress,
        Address $contractAddress,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        $fields = ['contract_address' => $contractAddress];

        return $this->transactions->createNativeContract(
            Endpoint::ClearContractAbi,
            'ClearABIContract',
            $ownerAddress,
            $fields,
            $memo,
            $permissionId,
        );
    }
}
