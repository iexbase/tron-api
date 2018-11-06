<?php
namespace IEXBase\TronAPI;

use IEXBase\TronAPI\Exception\TronException;

class TransactionBuilder
{
    /**
     * Base Tron object
     *
     * @var Tron
    */
    protected $tron;

    /**
     * Create an TransactionBuilder object
     *
     * @param Tron $tron
     */
    public function __construct(Tron $tron)
    {
        $this->tron = $tron;
    }

    /**
     * Creates a transaction of transfer.
     * If the recipient address does not exist, a corresponding account will be created on the blockchain.
     *
     * @param string $to
     * @param float $amount
     * @param string $from
     * @return array
     * @throws TronException
     */
    public function sendTrx($to, $amount, $from)
    {
        if (!is_float($amount) || $amount < 0) {
            throw new TronException('Invalid amount provided');
        }

        $to = $this->tron->address2HexString($to);
        $from = $this->tron->address2HexString($from);

        if ($from === $to) {
            throw new TronException('Cannot transfer TRX to the same account');
        }

        $response = $this->tron->getManager()->request('wallet/createtransaction', [
            'to_address' => $to,
            'owner_address' => $from,
            'amount' => $this->tron->toTron($amount),
        ]);

        return $response;
    }

    /**
     * Transfer Token
     *
     * @param string $to
     * @param int $amount
     * @param string $tokenID
     * @param string|null $from
     * @return array
     * @throws TronException
     */
    public function sendToken(string $to, int $amount, string $tokenID, string $from)
    {
        if (!is_integer($amount) or $amount <= 0) {
            throw new TronException('Invalid amount provided');
        }

        if (!is_string($tokenID)) {
            throw new TronException('Invalid token ID provided');
        }

        if ($to === $from) {
            throw new TronException('Cannot transfer tokens to the same account');
        }

        $transfer = $this->tron->getManager()->request('wallet/transferasset', [
            'owner_address' => $this->tron->address2HexString($from),
            'to_address' => $this->tron->address2HexString($to),
            'asset_name' => $this->tron->stringUtf8toHex($tokenID),
            'amount' => intval($amount)
        ]);

        if (array_key_exists('Error', $transfer)) {
            throw new TronException($transfer['Error']);
        }
        return $transfer;
    }

    /**
     * Purchase a Token
     *
     * @param $issuerAddress
     * @param $tokenID
     * @param $amount
     * @param $buyer
     * @return array
     * @throws TronException
     */
    public function purchaseToken($issuerAddress, $tokenID, $amount, $buyer)
    {
        if (!is_string($tokenID)) {
            throw new TronException('Invalid token ID provided');
        }

        if (!is_integer($amount) and $amount <= 0) {
            throw new TronException('Invalid amount provided');
        }

        $purchase = $this->tron->getManager()->request('wallet/participateassetissue', [
            'to_address' => $this->tron->address2HexString($issuerAddress),
            'owner_address' => $this->tron->address2HexString($buyer),
            'asset_name' => $this->tron->stringUtf8toHex($tokenID),
            'amount' => $this->tron->toTron($amount)
        ]);

        if (array_key_exists('Error', $purchase)) {
            throw new TronException($purchase['Error']);
        }
        return $purchase;
    }

    /**
     * Freezes an amount of TRX.
     * Will give bandwidth OR Energy and TRON Power(voting rights) to the owner of the frozen tokens.
     *
     * @param float $amount
     * @param int $duration
     * @param string $resource
     * @param string|null $address
     * @return array
     * @throws TronException
     */
    public function freezeBalance(float $amount = 0, int $duration = 3, string $resource = 'BANDWIDTH', string $address)
    {
        if (!in_array($resource, ['BANDWIDTH', 'ENERGY'])) {
            throw new TronException('Invalid resource provided: Expected "BANDWIDTH" or "ENERGY"');
        }

        if (!is_float($amount)) {
            throw new TronException('Invalid amount provided');
        }

        if(!is_integer($duration) and $duration < 3) {
            throw new TronException('Invalid duration provided, minimum of 3 days');
        }

        return $this->tron->getManager()->request('wallet/freezebalance', [
            'owner_address' => $this->tron->address2HexString($address),
            'frozen_balance' => $this->tron->toTron($amount),
            'frozen_duration' => $duration,
            'resource' => $resource
        ]);
    }

    /**
     * Unfreeze TRX that has passed the minimum freeze duration.
     * Unfreezing will remove bandwidth and TRON Power.
     *
     * @param string $resource
     * @param string $owner_address
     * @return array
     * @throws TronException
     */
    public function unfreezeBalance(string $resource = 'BANDWIDTH', string $owner_address)
    {
        if (!in_array($resource, ['BANDWIDTH', 'ENERGY'])) {
            throw new TronException('Invalid resource provided: Expected "BANDWIDTH" or "ENERGY"');
        }

        return $this->tron->getManager()->request('wallet/unfreezebalance', [
            'owner_address' =>  $this->tron->address2HexString($owner_address),
            'resource' => $resource
        ]);
    }

    /**
     * Withdraw Super Representative rewards, useable every 24 hours.
     *
     * @param string $owner_address
     * @return array
     * @throws TronException
     */
    public function withdrawBlockRewards($owner_address = null)
    {
        $withdraw =  $this->tron->getManager()->request('wallet/withdrawbalance', [
            'owner_address' =>  $this->tron->address2HexString($owner_address)
        ]);

        if (array_key_exists('Error', $withdraw)) {
            throw new TronException($withdraw['Error']);
        }
        return $withdraw;
    }

    /**
     * Update a Token's information
     *
     * @param string $description
     * @param string $url
     * @param int $freeBandwidth
     * @param int $freeBandwidthLimit
     * @param $address
     * @return array
     * @throws TronException
     */
    public function updateToken(string $description, string $url, int $freeBandwidth = 0, int $freeBandwidthLimit = 0, $address)
    {
        if (!is_integer($freeBandwidth) || $freeBandwidth < 0) {
            throw new TronException('Invalid free bandwidth amount provided');
        }

        if (!is_integer($freeBandwidthLimit) || $freeBandwidthLimit < 0 && ($freeBandwidth && !$freeBandwidthLimit)) {
            throw new TronException('Invalid free bandwidth limit provided');
        }

        return $this->tron->getManager()->request('wallet/updateasset', [
            'owner_address'      =>  $this->tron->address2HexString($address),
            'description'        =>  $this->tron->stringUtf8toHex($description),
            'url'               =>  $this->tron->stringUtf8toHex($url),
            'new_limit'         =>  intval($freeBandwidth),
            'new_public_limit'  =>  intval($freeBandwidthLimit)
        ]);
    }
}
