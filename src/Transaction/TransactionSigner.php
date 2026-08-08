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

use IEXBase\TronAPI\Crypto\SignerInterface;
use IEXBase\TronAPI\Enum\ContractType;
use IEXBase\TronAPI\Exception\CryptoException;
use IEXBase\TronAPI\Exception\TransactionException;

/**
 * Verifies an intent and appends a locally produced single or multi-signature.
 */
final readonly class TransactionSigner
{
    /**
     * Creates a signer workflow with an injectable transaction verifier.
     */
    public function __construct(private TransactionVerifier $verifier = new TransactionVerifier())
    {
    }

    /**
     * Appends one signature after validating intent and signer authorization.
     *
     * A permission object is mandatory when the signing key differs from the
     * owner address. This prevents an accidental wrong-key signature while
     * supporting weighted owner and active multi-signature configurations.
     */
    public function appendSignature(
        Transaction $transaction,
        SignerInterface $signer,
        ?TransactionIntent $intent = null,
        ?Permission $permission = null,
    ): Transaction {
        $intent ??= $transaction->approvedIntent();
        $this->verifier->verify($transaction, $intent);

        if ($permission === null) {
            if (!$signer->address()->equals($intent->ownerAddress)) {
                throw new TransactionException('A non-owner signer requires the matching on-chain Permission object.');
            }
        } elseif ($permission->id !== $intent->permissionId
            || !$permission->contains($signer->address())
            || !$permission->allows(ContractType::fromProtocolName($intent->contractType))
        ) {
            throw new TransactionException('The signer is not authorized by the transaction permission.');
        }

        $signature = $signer->signDigest($transaction->id());
        if (!$signature->recoverAddress($transaction->id())->equals($signer->address())) {
            throw new CryptoException('The produced signature does not recover to the signer address.');
        }

        return $transaction->appendSignature($signature);
    }
}
