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
use IEXBase\TronAPI\Crypto\SignerInterface;
use IEXBase\TronAPI\Encoding\Hex;
use IEXBase\TronAPI\Enum\ConfirmationLevel;
use IEXBase\TronAPI\Exception\TransactionException;
use IEXBase\TronAPI\Model\BroadcastResult;
use IEXBase\TronAPI\Model\TransactionReceipt;
use IEXBase\TronAPI\Transaction\Permission;
use IEXBase\TronAPI\Transaction\SignatureWeight;
use IEXBase\TronAPI\Transaction\Transaction;
use IEXBase\TronAPI\Transaction\TransactionCostCalculator;
use IEXBase\TronAPI\Transaction\TransactionIntent;
use IEXBase\TronAPI\Transaction\TransactionSigner;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\Amount;

/**
 * Finds, signs, validates, and broadcasts transactions without storing keys.
 */
final readonly class TransactionService
{
    /**
     * Creates a transaction service with an injectable local signing workflow.
     */
    public function __construct(
        private ApiClient $client,
        private TransactionSigner $signer = new TransactionSigner(),
        private TransactionCostCalculator $costs = new TransactionCostCalculator(),
    ) {
    }

    /**
     * Returns a latest or confirmed transaction, or null when the node has none.
     */
    public function find(
        string $transactionId,
        ConfirmationLevel $level = ConfirmationLevel::Confirmed,
    ): ?Transaction {
        $data = $this->findNodeObject(
            $transactionId,
            $level,
            Endpoint::GetTransaction,
            Endpoint::GetConfirmedTransaction,
            'transaction',
        );

        return $data === null ? null : Transaction::fromNodeData($data);
    }

    /**
     * Returns latest or confirmed transaction execution info, or null if absent.
     */
    public function receipt(
        string $transactionId,
        ConfirmationLevel $level = ConfirmationLevel::Confirmed,
    ): ?TransactionReceipt {
        $data = $this->findNodeObject(
            $transactionId,
            $level,
            Endpoint::GetTransactionReceipt,
            Endpoint::GetConfirmedTransactionReceipt,
            'transaction info',
        );

        return $data === null ? null : TransactionReceipt::fromNodeData($data);
    }

    /**
     * Returns execution receipts for every transaction in one block.
     *
     * @return list<TransactionReceipt>
     */
    public function blockReceipts(
        int $blockNumber,
        ConfirmationLevel $level = ConfirmationLevel::Confirmed,
    ): array {
        if ($blockNumber < 0) {
            throw new TransactionException('A receipt block number cannot be negative.');
        }

        $endpoint = Endpoint::forConfirmation(
            $level,
            Endpoint::GetBlockReceipts,
            Endpoint::GetConfirmedBlockReceipts,
        );
        $data = $this->client->request($endpoint->request(['num' => $blockNumber]))->data();

        return array_map(
            static fn (array $receipt): TransactionReceipt => TransactionReceipt::fromNodeData($receipt),
            DataDecoder::objectList($data, 'transaction info list'),
        );
    }

    /**
     * Returns the number of transactions in a solidified block.
     */
    public function confirmedBlockTransactionCount(int $blockNumber): int
    {
        if ($blockNumber < 0) {
            throw new TransactionException('A transaction-count block number cannot be negative.');
        }

        $response = $this->client->request(
            Endpoint::GetConfirmedBlockTransactionCount->request(['num' => $blockNumber]),
        );

        return DataDecoder::integer($response->value('count') ?? 0, 'count');
    }

    /**
     * Returns every transaction ID currently advertised by the FullNode mempool.
     *
     * @return list<string>
     */
    public function pendingIds(): array
    {
        $response = $this->client->request(Endpoint::GetPendingTransactions->request());
        $values = $response->value('txId') ?? [];
        if (!is_array($values) || !array_is_list($values)) {
            throw new TransactionException('The FullNode returned a malformed pending transaction ID list.');
        }

        return array_map(
            static fn (mixed $value): string => Hex::canonicalize(
                DataDecoder::string($value, 'txId[]'),
                32,
            ),
            $values,
        );
    }

    /**
     * Returns the current FullNode pending-pool size.
     */
    public function pendingCount(): int
    {
        $response = $this->client->request(Endpoint::GetPendingTransactionCount->request());

        return DataDecoder::integer($response->value('pendingSize') ?? 0, 'pendingSize');
    }

    /**
     * Returns one transaction from the FullNode mempool, or null when absent.
     */
    public function pending(string $transactionId): ?Transaction
    {
        $data = $this->nodeObject(
            $transactionId,
            Endpoint::GetPendingTransaction,
            'pending transaction',
            false,
        );

        return $data === null ? null : Transaction::fromNodeData($data);
    }

    /**
     * Appends one locally generated signature after complete intent verification.
     */
    public function appendSignature(
        Transaction $transaction,
        SignerInterface $signer,
        ?TransactionIntent $intent = null,
        ?Permission $permission = null,
    ): Transaction {
        return $this->signer->appendSignature($transaction, $signer, $intent, $permission);
    }

    /**
     * Returns the official Bandwidth estimate for the expected final signature count.
     */
    public function estimateBandwidth(Transaction $transaction, ?int $signatureCount = null): int
    {
        return $this->costs->bandwidth($transaction, $signatureCount);
    }

    /**
     * Returns the maximum Bandwidth burn before accounting for available resources.
     */
    public function maximumBandwidthBurn(
        Transaction $transaction,
        Amount $unitPrice,
        ?int $signatureCount = null,
    ): Amount {
        return $this->costs->maximumBandwidthBurn($transaction, $unitPrice, $signatureCount);
    }

    /**
     * Asks a FullNode to verify signatures and calculate selected permission weight.
     */
    public function signatureWeight(Transaction $transaction): SignatureWeight
    {
        $data = $this->client->request(
            Endpoint::GetSignWeight->request($transaction->toArray()),
        )->requireAccepted()->data();

        return SignatureWeight::fromNodeData(DataDecoder::object($data, 'signature weight'));
    }

    /**
     * Returns signer addresses approved by the FullNode for this transaction.
     *
     * @return list<Address>
     */
    public function approvedSigners(Transaction $transaction): array
    {
        $response = $this->client->request(
            Endpoint::GetApprovedList->request($transaction->toArray()),
        )->requireAccepted();
        $values = $response->value('approved_list') ?? [];
        if (!is_array($values) || !array_is_list($values)) {
            throw new TransactionException('The FullNode returned a malformed approved signer list.');
        }

        return array_map(
            static fn (mixed $value): Address => Address::fromString(
                DataDecoder::string($value, 'approved_list[]'),
            ),
            $values,
        );
    }

    /**
     * Broadcasts an unexpired signed transaction and verifies the returned txID.
     */
    public function broadcast(Transaction $transaction): BroadcastResult
    {
        if (!$transaction->isSigned()) {
            throw new TransactionException('An unsigned transaction cannot be broadcast.');
        }
        if ($transaction->isExpired()) {
            throw new TransactionException('An expired transaction cannot be broadcast.');
        }

        $data = $this->client->request(
            Endpoint::BroadcastTransaction->request($transaction->toArray()),
        )->requireAccepted()->data();
        $result = BroadcastResult::fromAcceptedNodeData(DataDecoder::object($data, 'broadcast result'));
        if (!hash_equals($transaction->id(), $result->transactionId)) {
            throw new TransactionException('The broadcast response txID does not match the signed transaction.');
        }

        return $result;
    }

    /**
     * Finds one object by transaction ID in latest or confirmed chain state.
     *
     * @return array<string, mixed>|null
     */
    private function findNodeObject(
        string $transactionId,
        ConfirmationLevel $level,
        Endpoint $latestEndpoint,
        Endpoint $confirmedEndpoint,
        string $label,
    ): ?array {
        return $this->nodeObject(
            $transactionId,
            Endpoint::forConfirmation($level, $latestEndpoint, $confirmedEndpoint),
            $label,
            true,
        );
    }

    /**
     * Reads one optional native object using a canonical 32-byte transaction ID.
     *
     * @return array<string, mixed>|null
     */
    private function nodeObject(
        string $transactionId,
        Endpoint $endpoint,
        string $label,
        bool $visible,
    ): ?array {
        $fields = ['value' => Hex::canonicalize($transactionId, 32)];
        if ($visible) {
            $fields['visible'] = true;
        }

        $data = $this->client->request($endpoint->request($fields))->data();

        return $data === [] ? null : DataDecoder::object($data, $label);
    }
}
