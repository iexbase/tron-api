<?php declare(strict_types=1);

namespace IEXBase\TronAPI;

use IEXBase\TronAPI\Exception\TronException;

interface TronInterface
{
    /**
     * Enter the link to the manager nodes
     *
     * @param $providers
     */
    public function setManager($providers);

    /**
     * Enter your private account key
     *
     * @param string $privateKey
     */
    public function setPrivateKey(string $privateKey): void;

    /**
     * Enter your account address
     *
     * @param string $address
     */
    public function setAddress(string $address) : void;

    /**
     * Getting a balance
     *
     * @param string $address
     * @return array
     */
    public function getBalance(string $address = null);

    /**
     * Query transaction based on id
     *
     * @param $transactionID
     * @return array
     */
    public function getTransaction(string $transactionID);

    /**
     * Count all transactions on the network
     *
     * @return integer
     */
    public function getTransactionCount();

    /**
     * Send transaction to Blockchain
     *
     * @param $to
     * @param $amount
     * @param $from
     *
     * @return array
     * @throws TronException
     */
    public function sendTransaction(string $to, float $amount, string $from = null);

    /**
     * Modify account name
     * Note: Username is allowed to edit only once.
     *
     * @param $address
     * @param $account_name
     * @return array
     */
    public function changeAccountName(string $address = null, string $account_name);

    /**
     * Create an account.
     * Uses an already activated account to create a new account
     *
     * @param $address
     * @param $newAccountAddress
     * @return array
     */
    public function registerAccount(string $address, string $newAccountAddress);

    /**
     * Apply to become a super representative
     *
     * @param string $address
     * @param string $url
     * @return array
     */
    public function applyForSuperRepresentative(string $address, string $url);


    /**
     * Get block details using HashString or blockNumber
     *
     * @param null $block
     * @return array
     */
    public function getBlock($block = null);

    /**
     * Query the latest blocks
     *
     * @param int $limit
     * @return array
     */
    public function getLatestBlocks(int $limit = 1);

    /**
     * Validate Address
     *
     * @param string $address
     * @param bool $hex
     * @return array
     */
    public function validateAddress(string $address, bool $hex = false);

    /**
     * Generate new address
     *
     * @return array
     */
    public function generateAddress();

    /**
     * Check the address before converting to Hex
     *
     * @param $sHexAddress
     * @return string
     */
    public function address2HexString($sHexAddress);
}
