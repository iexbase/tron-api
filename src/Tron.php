<?php

/**
 * TronAPI
 *
 * @author  Shamsudin Serderov <steein.shamsudin@gmail.com>
 * @license https://github.com/iexbase/tron-api/blob/master/LICENSE (MIT License)
 * @version 1.3.4
 * @link    https://github.com/iexbase/tron-api
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace IEXBase\TronAPI;

use IEXBase\TronAPI\Support\Utils;
use IEXBase\TronAPI\Provider\{HttpProvider, HttpProviderInterface};
use IEXBase\TronAPI\Exception\TronException;

/**
 * A PHP API for interacting with the Tron (TRX)
 *
 * @package TronAPI
 * @author  Shamsudin Serderov <steein.shamsudin@gmail.com>
 * @since   1.0.0
 */
class Tron implements TronInterface
{
    use TronAwareTrait,
        Concerns\ManagesUniversal;

    /**
     * Full node URL
     *
     * @var HttpProviderInterface|string
    */
    protected $fullNode = 'https://api.trongrid.io';

    /**
     * TRON Server Node
     *
     * @var HttpProviderInterface|string
    */
    protected $tronNode = 'https://server.tron.network';

    /**
     * Solidity URL Node
     *
     * @var HttpProviderInterface|string
    */
    protected $solidityNode;

    /**
     * Default Address
     *
     * @var string
    */
    protected $address;

    /**
     * Private key
     *
     * @var string
    */
    protected $privateKey;

    /**
     * Default block
     *
     * @var string|integer|bool
    */
    protected $defaultBlock = false;

    /**
     * Create a new Tron object
     *
     * @param HttpProviderInterface $fullNode
     * @param HttpProviderInterface $solidityNode
     * @param string $privateKey
     * @throws TronException
     */
    public function __construct(?HttpProviderInterface $fullNode = null,
                                ?HttpProviderInterface $solidityNode = null,
                                string $privateKey = null)
    {
        if(!$fullNode instanceof HttpProviderInterface) {
            $fullNode = new HttpProvider((string)$this->fullNode);
        }

        if(!$solidityNode instanceof HttpProviderInterface) {
            $solidityNode = new HttpProvider((string)$this->solidityNode);
        }

        $tronNode = new HttpProvider((string)$this->tronNode);

        $this->setFullNode($fullNode);
        $this->setSolidityNode($solidityNode);
        $this->setTronNode($tronNode);

        if(!is_null($privateKey)) {
            $this->setPrivateKey($privateKey);
        }
    }

    /**
     * Check connected provider
     *
     * @param $provider
     * @return bool
     */
    public function isValidProvider($provider): bool
    {
        return ($provider instanceof HttpProviderInterface);
    }

    /**
     * Enter the link to the full node
     *
     * @param $provider
     *
     * @return void | string
     * @throws TronException
     */
    public function setFullNode($provider): void
    {
        if(!$this->isValidProvider($provider)) {
            throw new TronException('Invalid full node provided');
        }

        $this->fullNode = $provider;
        $this->fullNode->setStatusPage('wallet/getnowblock');
    }

    /**
     * Enter the link to the solidity node
     * @param $provider
     *
     * @return void | string
     * @throws TronException
     */
    public function setSolidityNode($provider): void
    {
        if(!$this->isValidProvider($provider)) {
            throw new TronException('Invalid solidity node provided');
        }

        $this->solidityNode = $provider;
        $this->solidityNode->setStatusPage('walletsolidity/getnowblock');
    }

    /**
     * Enter the link to the server node
     *
     * @param $provider
     *
     * @return void | string
     * @throws TronException
     */
    public function setTronNode($provider): void
    {
        if(!$this->isValidProvider($provider)) {
            throw new TronException('Invalid tron node provided');
        }

        $this->tronNode = $provider;
    }

    /**
     * Enter the default block
     *
     * @param bool $blockID
     * @return void
     * @throws TronException
     */
    public function setDefaultBlock($blockID = false): void
    {
        if($blockID === false || $blockID == 'latest' || $blockID == 'earliest' || $blockID === 0) {
            $this->defaultBlock = $blockID;
            return;
        }

        if(!is_integer($blockID)) {
            throw new TronException('Invalid block ID provided');
        }

        $this->defaultBlock = abs($blockID);
    }

    /**
     * Get default block
     *
     * @return string|integer|bool
    */
    public function getDefaultBlock()
    {
        return $this->defaultBlock;
    }

    /**
     * Enter your private account key
     *
     * @param string $privateKey
     */
    public function setPrivateKey(string $privateKey): void
    {
        $this->privateKey = $privateKey;
    }

    /**
     * Enter your account address
     *
     * @param string $address
     */
    public function setAddress(string $address): void
    {
        $this->address = $address;
    }

    /**
     * Get account address
     *
     * @return string
    */
    public function getAddress(): string
    {
        return $this->address;
    }

    /**
     * Get customized provider data
     *
     * @return array
    */
    public function currentProviders(): array
    {
        return [
            'fullNode'      =>  $this->fullNode,
            'solidityNode'  =>  $this->solidityNode,
            'tronNode'      =>  $this->tronNode
        ];
    }

    /**
     * Last block number
     *
     * @return array
    */
    public function getCurrentBlock(): array
    {
        return $this->fullNode->request('wallet/getnowblock');
    }

    /**
     * Get block details using HashString or blockNumber
     *
     * @param null $block
     * @return array
     * @throws TronException
     */
    public function getBlock($block = null): array
    {
        $block = (is_null($block) ? $this->defaultBlock : $block);

        if($block === false) {
            throw new TronException('No block identifier provided');
        }

        if($block == 'earliest') {
            $block = 0;
        }

        if($block == 'latest') {
            return $this->getCurrentBlock();
        }

        if(Utils::isHex($block)) {
            return $this->getBlockByHash($block);
        }

        return $this->getBlockByNumber($block);
    }

    /**
     * Query block by ID
     *
     * @param $hashBlock
     * @return array
     */
    public function getBlockByHash(string $hashBlock): array
    {
        return $this->fullNode->request('wallet/getblockbyid', [
            'value' =>  $hashBlock
        ],'post');
    }

    /**
     * Query block by height
     *
     * @param $blockID
     * @return array
     * @throws TronException
     */
    public function getBlockByNumber(int $blockID): array
    {
        if(!is_integer($blockID) || $blockID < 0) {
            throw new TronException('Invalid block number provided');
        }

        return $this->fullNode->request('wallet/getblockbynum', [
            'num'   =>  intval($blockID)
        ],'post');
    }

    /**
     * Total number of transactions in a block
     *
     * @param $block
     * @return int
     * @throws TronException
     */
    public function getBlockTransactionCount($block): int
    {
        $transaction = $this->getBlock($block)['transactions'];

        if(!$transaction) {
            return 0;
        }

        return count($transaction);
    }

    /**
     * Get transaction details from Block
     *
     * @param null $block
     * @param int $index
     * @return array | string
     * @throws TronException
     */
    public function getTransactionFromBlock($block = null, $index = 0)
    {
        if(!is_integer($index) || $index < 0) {
            throw new TronException('Invalid transaction index provided');
        }

        $transactions = $this->getBlock($block)['transactions'];
        if(!$transactions || count($transactions) < $index) {
            throw new TronException('Transaction not found in block');
        }

        return $transactions[$index];
    }

    /**
     * Query transaction based on id
     *
     * @param $transactionID
     * @return array
     * @throws TronException
     */
    public function getTransaction(string $transactionID): array
    {
        $response = $this->fullNode->request('wallet/gettransactionbyid', [
            'value' =>  $transactionID
        ],'post');

        if(!$response) {
            throw new TronException('Transaction not found');
        }

        return $response;
    }

    /**
     * Query transaction fee based on id
     *
     * @param $transactionID
     * @return array
     */
    public function getTransactionInfo(string $transactionID): array
    {
        return $this->solidityNode->request('walletsolidity/gettransactioninfobyid', [
            'value' =>  $transactionID
        ],'post');
    }

    /**
     * Query the list of transactions received by an address
     *
     * @param string $address
     * @param int $limit
     * @param int $offset
     * @return array
     * @throws TronException
     */
    public function getTransactionsToAddress(string $address, int $limit = 30, int $offset = 0)
    {
        return $this->getTransactionsRelated($address,'to', $limit, $offset);
    }

    /**
     * Query the list of transactions sent by an address
     *
     * @param string $address
     * @param int $limit
     * @param int $offset
     * @return array
     * @throws TronException
     */
    public function getTransactionsFromAddress(string $address, int $limit = 30, int $offset = 0)
    {
        return $this->getTransactionsRelated($address,'from', $limit, $offset);
    }

    /**
     * Query information about an account
     *
     * @param $address
     * @return array
     */
    public function getAccount(string $address = null): array
    {
        $address = (!is_null($address) ? $address : $this->address);

        return $this->fullNode->request('wallet/getaccount', [
            'address'   =>  $this->toHex($address)
        ],'post');
    }

    /**
     * Getting a balance
     *
     * @param string $address
     * @param bool $fromTron
     * @return float
     */
    public function getBalance(string $address = null, bool $fromTron = false): float
    {
        $address = (!is_null($address) ? $address : $this->address);
        $account = $this->getAccount($address);

        if(!array_key_exists('balance', $account)) {
            return 0;
        }

        return ($fromTron == true ?
            $this->fromTron($account['balance']) :
            $account['balance']);
    }

    /**
     * Query bandwidth information.
     *
     * @param $address
     * @return array
     */
    public function getBandwidth(string $address = null)
    {
        $address = (!is_null($address) ? $address : $this->address);

        return $this->fullNode->request('wallet/getaccountnet', [
            'address'   =>  $this->toHex($address)
        ],'post');
    }

    /**
     * Getting data in the "from","to" directions
     *
     * @param string $address
     * @param string $direction
     * @param int $limit
     * @param int $offset
     * @return array
     * @throws TronException
     */
    public function getTransactionsRelated(string $address, string $direction = 'to', int $limit = 30, int $offset = 0)
    {
        if(!in_array($direction, ['to', 'from'])) {
            throw new TronException('Invalid direction provided: Expected "to", "from"');
        }

        if(!is_integer($limit) || $limit < 0 || ($offset && $limit < 1)) {
            throw new TronException('Invalid limit provided');
        }

        if(!is_integer($offset) || $offset < 0) {
            throw new TronException('Invalid offset provided');
        }

        $response = $this->solidityNode->request(sprintf('walletextension/gettransactions%sthis', $direction), [
            'account'   =>  ['address' => $this->toHex($address)],
            'limit'     =>  $limit,
            'offset'    =>  $offset
        ],'post');

        return array_merge($response, ['direction' => $direction]);
    }

    /**
     * Count all transactions on the network
     *
     * @return integer
    */
    public function getTransactionCount(): int
    {
        $response = $this->fullNode->request('wallet/totaltransaction');
        return $response['num'];
    }

    /**
     * Send transaction to Blockchain
     *
     * @param string $to
     * @param float $amount
     * @param string $from
     *
     * @return array
     * @throws TronException
     */
    public function sendTransaction(string $to, float $amount, string $from = null): array
    {
        if (is_null($from)) {
            $from = $this->address;
        }

        $transaction = $this->createTransaction($from, $to, $amount);
        $signedTransaction = $this->signTransaction($transaction);
        $response = $this->sendRawTransaction($signedTransaction);

        return array_merge($response, $signedTransaction);
    }

    /**
     * Creates a transaction of transfer.
     * If the recipient address does not exist, a corresponding account will be created on the blockchain.
     *
     * @param string $from
     * @param string $to
     * @param float $amount
     * @return array
     * @throws TronException
     */
    protected function createTransaction(string $from, string $to, float $amount): array
    {
        if(!is_float($amount) || $amount < 0) {
            throw new TronException('Invalid amount provided');
        }

        $to = $this->toHex($to);
        $from = $this->toHex($from);

        if($from === $to) {
            throw new TronException('Cannot transfer TRX to the same account');
        }
        
        $response = $this->fullNode->request('wallet/createtransaction', [
            'to_address'    =>  $to,
            'owner_address' =>  $from,
            'amount'        =>  $this->toTron($amount),
        ], 'post');

        return $response;
    }

    /**
     * Sign the transaction, the api has the risk of leaking the private key,
     * please make sure to call the api in a secure environment
     *
     * @param $transaction
     * @return array
     * @throws TronException
     */
    protected function signTransaction($transaction): array
    {
        if(!$this->privateKey) {
            throw new TronException('Missing private key');
        }

        if(!is_array($transaction)) {
            throw new TronException('Invalid transaction provided');
        }

        if(isset($transaction['signature'])) {
            throw new TronException('Transaction is already signed');
        }

        return $this->fullNode->request('wallet/gettransactionsign', [
            'transaction'   => $transaction,
            'privateKey'    => $this->privateKey
        ],'post');
    }

    /**
     * Broadcast the signed transaction
     *
     * @param $signedTransaction
     * @return array
     * @throws TronException
     */
    protected function sendRawTransaction($signedTransaction): array
    {
        if(!is_array($signedTransaction)) {
            throw new TronException('Invalid transaction provided');
        }

        if(!array_key_exists('signature', $signedTransaction) || !is_array($signedTransaction['signature'])) {
            throw new TronException('Transaction is not signed');
        }

        return $this->fullNode->request('wallet/broadcasttransaction',
            $signedTransaction,'post');
    }

    /**
     * Modify account name
     * Note: Username is allowed to edit only once.
     *
     * @param $address
     * @param $account_name
     * @return array
     * @throws TronException
     */
    public function changeAccountName(string $address = null, string $account_name)
    {
        $address = (!is_null($address) ? $address : $this->address);

        $transaction = $this->fullNode->request('wallet/updateaccount', [
            'account_name'  =>  $this->stringUtf8toHex($account_name),
            'owner_address' =>  $this->toHex($address)
        ],'post');

        $signedTransaction = $this->signTransaction($transaction);
        $response = $this->sendRawTransaction($signedTransaction);

        return $response;
    }

    /**
     * Transfer Token (option 2)
     *
     * @param array $args
     * @return array
     * @throws TronException
     */
    public function sendToken(...$args): array  {
        return $this->createSendAssetTransaction(...$args);
    }

    /**
     * Send funds to the Tron account (option 2)
     *
     * @param array $args
     * @return array
     * @throws TronException
     */
    public function send(...$args): array {
        return $this->sendTransaction(...$args);
    }

    /**
     * Send funds to the Tron account (option 3)
     *
     * @param array $args
     * @return array
     * @throws TronException
     */
    public function sendTrx(...$args): array {
        return $this->sendTransaction(...$args);
    }

    /**
     * Creating a new token based on Tron
     *
     *   @param array token {
     *   "owner_address": "41e552f6487585c2b58bc2c9bb4492bc1f17132cd0",
     *   "name": "0x6173736574497373756531353330383934333132313538",
     *   "abbr": "0x6162627231353330383934333132313538",
     *   "total_supply": 4321,
     *   "trx_num": 1,
     *   "num": 1,
     *   "start_time": 1530894315158,
     *   "end_time": 1533894312158,
     *   "description": "007570646174654e616d6531353330363038383733343633",
     *   "url": "007570646174654e616d6531353330363038383733343633",
     *   "free_asset_net_limit": 10000,
     *   "public_free_asset_net_limit": 10000,
     *   "frozen_supply": { "frozen_amount": 1, "frozen_days": 2 }
     *
     * @return array
     */
    public function createToken($token = [])
    {
        return $this->fullNode->request('wallet/createassetissue', [
            'owner_address'                 =>  $this->toHex($token['owner_address']),
            'name'                          =>  $this->stringUtf8toHex($token['name']),
            'abbr'                          =>  $this->stringUtf8toHex($token['abbr']),
            'description'                   =>  $this->stringUtf8toHex($token['description']),
            'url'                           =>  $this->stringUtf8toHex($token['url']),
            'total_supply'                  =>  $token['total_supply'],
            'trx_num'                       =>  $token['trx_num'],
            'num'                           =>  $token['num'],
            'start_time'                    =>  $token['start_time'],
            'end_time'                      =>  $token['end_time'],
            'free_asset_net_limit'          =>  $token['free_asset_net_limit'],
            'public_free_asset_net_limit'   => $token['public_free_asset_net_limit'],
            'frozen_supply'                 =>  $token['frozen_supply']
        ],'post');
    }

    /**
     * Create an account.
     * Uses an already activated account to create a new account
     *
     * @param $address
     * @param $newAccountAddress
     * @return array
     */
    public function registerAccount(string $address, string $newAccountAddress): array
    {
        return $this->fullNode->request('wallet/createaccount', [
            'owner_address'     =>  $this->toHex($address),
            'account_address'   =>  $this->toHex($newAccountAddress)
        ],'post');
    }

    /**
     * Apply to become a super representative
     *
     * @param $address
     * @param $url
     * @return array
     */
    public function applyForSuperRepresentative(string $address, string $url)
    {
        return $this->fullNode->request('wallet/createwitness', [
            'owner_address' =>  $this->toHex($address),
            'url'           =>  $this->stringUtf8toHex($url)
        ],'post');
    }

    /**
     * Transfer Token
     *
     * @param $to
     * @param $amount
     * @param $tokenID
     * @param $from
     * @return array
     * @throws TronException
     */
    public function createSendAssetTransaction($to, $amount, $tokenID, $from = null)
    {
        if($from == null) {
            $from = $this->address;
        }

        if (!is_float($amount) or $amount <= 0) {
            throw new TronException('Invalid amount provided');
        }

        if (!is_string($tokenID)) {
            throw new TronException('Invalid token ID provided');
        }

        $transfer =  $this->fullNode->request('wallet/transferasset', [
            'owner_address' =>  $this->toHex($from),
            'to_address'    =>  $this->toHex($to),
            'asset_name'    =>  $this->stringUtf8toHex($tokenID),
            'amount'        =>  $this->toTron($amount)
        ],'post');

        $signedTransaction = $this->signTransaction($transfer);
        $response = $this->sendRawTransaction($signedTransaction);

        return array_merge($response, $signedTransaction);
    }

    /**
     * Create address from a specified password string (NOT PRIVATE KEY)
     *
     * @param $password
     * @return array
     */
    public function createAddressWithPassword(string $password): array
    {
        return $this->fullNode->request('wallet/createaddress', [
            'value' =>  $this->stringUtf8toHex($password)
        ],'post');
    }

    /**
     * Purchase a Token
     *
     * @param $tokenIssuer
     * @param $address
     * @param $amount
     * @param $assetID
     * @return array
     */
    public function createPurchaseAssetTransaction($tokenIssuer, $address, $amount, $assetID)
    {
        return $this->fullNode->request('wallet/participateassetissue', [
            'to_address'    =>  $this->toHex($tokenIssuer),
            'owner_address' =>  $this->toHex($address),
            'asset_name'    =>  $this->stringUtf8toHex($assetID),
            'amount'        =>  $this->toTron($amount)
        ],'post');
    }

    /**
     * Freezes an amount of TRX.
     * Will give bandwidth OR Energy and TRON Power(voting rights) to the owner of the frozen tokens.
     *
     * @param string $address
     * @param float $amount
     * @param int $duration
     * @return array
     */
    public function createFreezeBalanceTransaction(string $address, float $amount, int $duration = 3)
    {
        return $this->fullNode->request('wallet/freezebalance', [
            'owner_address'     =>  $this->toHex($address),
            'frozen_balance'    =>  $this->toTron($amount),
            'frozen_duration'   =>  $duration
        ],'post');
    }

    /**
     * Unfreeze TRX that has passed the minimum freeze duration.
     * Unfreezing will remove bandwidth and TRON Power.
     *
     * @param string $address
     * @return array
     */
    public function createUnfreezeBalanceTransaction(string $address)
    {
        return $this->fullNode->request('wallet/unfreezebalance', [
            'owner_address' =>  $this->toHex($address)
        ],'post');
    }

    /**
     * Unfreeze a token that has passed the minimum freeze duration.
     *
     * @param $address
     * @return array
     */
    public function createUnfreezeAssetTransaction(string $address)
    {
        return $this->fullNode->request('wallet/unfreezeasset', [
            'owner_address' =>  $this->toHex($address)
        ],'post');
    }

    /**
     * Withdraw Super Representative rewards, useable every 24 hours.
     *
     * @param string $address
     * @return array
     */
    public function createWithdrawBlockRewardTransaction(string $address)
    {
        return $this->fullNode->request('wallet/withdrawbalance', [
            'owner_address' =>  $this->toHex($address)
        ],'post');
    }

    /**
     * Update a Token's information
     *
     * @param $address
     * @param $description
     * @param $url
     * @param int $bandwidthLimit
     * @param int $freeBandwidthLimit
     * @return array
     */
    public function createUpdateAssetTransaction($address, $description, $url, $bandwidthLimit = 0, $freeBandwidthLimit = 0)
    {
        return $this->fullNode->request('wallet/updateasset', [
           'owner_address'      =>  $this->toHex($address),
           'description'        =>  $this->stringUtf8toHex($description),
            'url'               =>  $this->stringUtf8toHex($url),
            'new_limit'         =>  $bandwidthLimit,
            'new_public_limit'  =>  $freeBandwidthLimit
        ],'post');
    }

    /**
     * Node list
     *
     * @return array
    */
    public function listNodes(): array
    {
        $nodes = $this->fullNode->request('wallet/listnodes');

        return array_map(function($item) {
            $address = $item['address'];

            return sprintf('%s:%s', $this->toUtf8($address['host']), $address['port']);
        }, $nodes['nodes']);
    }


    /**
     * List the tokens issued by an account.
     *
     * @param string $address
     * @return array
     */
    public function getTokensIssuedByAddress(string $address = null)
    {
        $address = (!is_null($address) ? $address : $this->address);

        return $this->fullNode->request('wallet/getassetissuebyaccount',[
            'address'   =>  $this->toHex($address)
        ],'post');
    }

    /**
     * Query token by name.
     *
     * @param $tokenID
     * @return array
     */
    public function getTokenFromID($tokenID = null)
    {
        return $this->fullNode->request('wallet/getassetissuebyname', [
            'value' =>  $this->stringUtf8toHex($tokenID)
        ],'post');
    }

    /**
     * Query a range of blocks by block height
     *
     * @param int $start
     * @param int $end
     * @return array
     * @throws TronException
     */
    public function getBlockRange(int $start = 0, int $end = 30)
    {
        if(!is_integer($start) || $start < 0) {
            throw new TronException('Invalid start of range provided');
        }

        if(!is_integer($end) || $end <= $start) {
            throw new TronException('Invalid end of range provided');
        }

        return $this->fullNode->request('wallet/getblockbylimitnext', [
            'startNum'  =>  intval($start),
            'endNum'    =>  intval($end) + 1
        ],'post')['block'];
    }

    /**
     * Query the latest blocks
     *
     * @param int $limit
     * @return array
     * @throws TronException
     */
    public function getLatestBlocks(int $limit = 1): array
    {
        if(!is_integer($limit) || $limit <= 0) {
            throw new TronException('Invalid limit provided');
        }

        return $this->fullNode->request('wallet/getblockbylatestnum', [
            'num'   =>  $limit
        ],'post')['block'];
    }

    /**
     * Query the list of Super Representatives
     *
     * @return array
    */
    public function listSuperRepresentatives(): array
    {
        return $this->fullNode->request('wallet/listwitnesses')['witnesses'];
    }

    /**
     * Query the list of Tokens with pagination
     *
     * @param int $limit
     * @param int $offset
     * @return array
     * @throws TronException
     */
    public function listTokens(int $limit = 0, int $offset = 0)
    {
        if(!is_integer($limit) || $limit < 0 || ($offset && $limit < 1)) {
            throw new TronException('Invalid limit provided');
        }

        if(!is_integer($offset) || $offset < 0) {
            throw new TronException('Invalid offset provided');
        }

        if(!$limit) {
            return $this->fullNode->request('wallet/getassetissuelist')['assetIssue'];
        }

        return $this->fullNode->request('wallet/getpaginatedassetissuelist', [
            'offset'    =>  intval($offset),
            'limit'     =>  intval($limit)
        ],'post')['assetIssue'];
    }

    /**
     * Get the time of the next Super Representative vote
     *
     * @return float
     * @throws TronException
     */
    public function timeUntilNextVoteCycle(): float
    {
        $num = $this->fullNode->request('wallet/getnextmaintenancetime')['num'];

        if($num == -1) {
            throw new TronException('Failed to get time until next vote cycle');
        }

        return floor($num / 1000);
    }

    /**
     * Validate address
     *
     * @param string $address
     * @param bool $hex
     * @return array
     */
    public function validateAddress(string $address = null, bool $hex = false): array
    {
        $address = (!is_null($address) ? $address : $this->address);
        if($hex) {
            $address = $this->toHex($address);
        }
        return $this->fullNode->request('wallet/validateaddress', [
            'address'   =>  $address
        ],'post');
    }

    /**
     * Deploys a contract
     *
     * @param $abi
     * @param $bytecode
     * @param $feeLimit
     * @param $address
     * @param int $callValue
     * @param int $bandwidthLimit
     * @return array
     * @throws TronException
     */
    public function deployContract($abi, $bytecode, $feeLimit, $address, $callValue = 0, $bandwidthLimit = 0)
    {
        $payable = array_filter(json_decode($abi, true), function($v)
        {
            if($v['type'] == 'constructor' && $v['payable']) {
                return $v['payable'];
            }
            return null;
        });

        if($feeLimit > 1000000000) {
            throw new TronException('fee_limit must not be greater than 1000000000');
        }

        if($payable && $callValue == 0) {
            throw new TronException('call_value must be greater than 0 if contract is type payable');
        }

        if(!$payable && $callValue > 0) {
            throw new TronException('call_value can only equal to 0 if contract type isn‘t payable');
        }

        return $this->fullNode->request('wallet/deploycontract', [
            'owner_address' =>  $this->toHex($address),
            'fee_limit'     =>  $feeLimit,
            'call_value'    =>  $callValue,
            'consume_user_resource_percent' =>  $bandwidthLimit,
            'abi'           =>  $abi,
            'bytecode'      =>  $bytecode
        ],'post');
    }

    /**
     * Freezes an amount of TRX.
     * Will give bandwidth OR Energy and TRON Power(voting rights) to the owner of the frozen tokens.
     *
     * @param string $owner_address
     * @param float $frozen_balance
     * @param int $frozen_duration
     * @param string $resource
     * @return array
     */
    public function freezeBalance($owner_address, $frozen_balance, $frozen_duration, $resource='BANDWIDTH')
    {
        return $this->fullNode->request('wallet/freezebalance', [
            'owner_address'     =>  $this->toHex($owner_address),
            'frozen_balance'    =>  $frozen_balance,
            'frozen_duration'   =>  $frozen_duration,
            'resource'          =>  $resource
        ], 'post');
    }

    /**
     * Get a list of exchanges
     *
     * @return array
    */
    public function listExchanges()
    {
        return $this->fullNode->request('/wallet/listexchanges', [], 'post');
    }

    /**
     * Query the resource information of the account
     *
     * @param string $address
     * @return array
     */
    public function getAccountResources(string $address = null)
    {
        $address = (!is_null($address) ? $address : $this->address);

        return $this->fullNode->request('/wallet/getaccountresource', [
           'address' =>  $this->toHex($address)
        ], 'post');
    }

    /**
     * Create a new account
     *
     * @return array
     */
    public function createAccount(): array
    {
        return $this->generateAddress();
    }

    /**
     * Generate new address
     *
     * @return array
    */
    public function generateAddress(): array
    {
        return $this->fullNode->request('wallet/generateaddress');
    }

    /**
     * Get Balance Info
     *
     * @return array
     */
    public function getBalanceInfo(): array
    {
        return $this->tronNode->request('api/v2/node/balance_info');
    }

    /**
     * Get the node map
     *
     * @return array
     */
    public function getNodeMap(): array
    {
        return $this->tronNode->request('api/v2/node/nodemap');
    }

    /**
     * Helper function that will convert HEX to UTF8
     *
     * @param $str
     * @return string
     */
    public function toUtf8($str): string {
        return pack('H*', $str);
    }

    /**
     * Check all connected nodes
     *
     * @return array
    */
    public function isConnected(): array
    {
        return [
            'fullNode'      =>  $this->fullNode->isConnected(),
            'solidityNode'  =>  $this->solidityNode->isConnected()
        ];
    }
}
