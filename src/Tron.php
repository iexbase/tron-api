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

use Elliptic\EC;
use IEXBase\TronAPI\Exception\TRC20Exception;
use IEXBase\TronAPI\Support\Base58;
use IEXBase\TronAPI\Support\Base58Check;
use IEXBase\TronAPI\Support\Crypto;
use IEXBase\TronAPI\Support\Hash;
use IEXBase\TronAPI\Support\Keccak;
use IEXBase\TronAPI\Support\Utils;
use IEXBase\TronAPI\Provider\HttpProviderInterface;
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
        Concerns\ManagesUniversal,
        Concerns\ManagesTronscan;

    const ADDRESS_SIZE = 34;
    const ADDRESS_PREFIX = "41";
    const ADDRESS_PREFIX_BYTE = 0x41;

    /**
     * Default Address:
     * Example:
     *      - base58:   T****
     *      - hex:      41****
     *
     * @var array
    */
    public $address = [
        'base58'    =>  null,
        'hex'       =>  null
    ];

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
    protected $defaultBlock = 'latest';

    /**
     * Transaction Builder
     *
     * @var TransactionBuilder
     */
    protected $transactionBuilder;

    /**
     * Transaction Builder
     *
     * @var TransactionBuilder
     */
    protected $trc20Contract;

    /**
     * Provider manager
     *
     * @var TronManager
    */
    protected $manager;

    /**
     * Object Result
     *
     * @var bool
    */
    protected $isObject = false;

    /**
     * Create a new Tron object
     *
     * @param HttpProviderInterface $fullNode
     * @param HttpProviderInterface $solidityNode
     * @param HttpProviderInterface|null $eventServer
     * @param HttpProviderInterface|null $signServer
     * @param HttpProviderInterface|null $explorer
     * @param string $privateKey

     * @throws TronException
     */
    public function __construct(?HttpProviderInterface $fullNode = null,
                                ?HttpProviderInterface $solidityNode = null,
                                ?HttpProviderInterface $eventServer = null,
                                ?HttpProviderInterface $signServer = null,
                                ?HttpProviderInterface $explorer = null,
                                ?string $privateKey = null)
    {
        if(!is_null($privateKey)) {
            $this->setPrivateKey($privateKey);
        }

        $this->setManager(new TronManager($this, [
            'fullNode'      =>  $fullNode,
            'solidityNode'  =>  $solidityNode,
            'eventServer'   =>  $eventServer,
            'signServer'    =>  $signServer,
        ]));

        $this->transactionBuilder = new TransactionBuilder($this);
    }

    /**
     * Create a new tron instance if the value isn't one already.
     *
     * @param HttpProviderInterface|null $fullNode
     * @param HttpProviderInterface|null $solidityNode
     * @param HttpProviderInterface|null $eventServer
     * @param HttpProviderInterface|null $signServer
     * @param string|null $privateKey
     * @return static
     * @throws TronException
     */
    public static function make(?HttpProviderInterface $fullNode = null,
                                ?HttpProviderInterface $solidityNode = null,
                                ?HttpProviderInterface $eventServer = null,
                                ?HttpProviderInterface $signServer = null,
                                string $privateKey = null) {
        return new static($fullNode, $solidityNode, $eventServer, $signServer, $privateKey);
    }

    /**
     * Фасад для Laravel
     *
     * @return Tron
    */
    public function getFacade(): Tron {
        return $this;
    }

    /**
     * Enter the link to the manager nodes
     *
     * @param $providers
     */
    public function setManager($providers) {
        $this->manager = $providers;
    }

    /**
     * Get provider manager
     *
     * @return TronManager
     */
    public function getManager(): TronManager {
        return $this->manager;
    }


    /**
     * Contract module
     *
     * @param string $contractAddress
     * @param string|null $abi
     * @return TRC20Contract
     */
    public function contract(string $contractAddress, string $abi = null)
    {
        return new TRC20Contract($this, $contractAddress, $abi);
    }

    /**
     * Set is object
     *
     * @param bool $value
     * @return Tron
     */
    public function setIsObject(bool $value)
    {
        $this->isObject = boolval($value);
        return $this;
    }

    /**
     * Get Transaction Builder
     *
     * @return TransactionBuilder
     */
    public function getTransactionBuilder(): TransactionBuilder
    {
        return $this->transactionBuilder;
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
        $_toHex = $this->address2HexString($address);
        $_fromHex = $this->hexString2Address($address);

        $this->address = [
            'hex'       =>  $_toHex,
            'base58'    =>  $_fromHex
        ];
    }

    /**
     * Get account address
     *
     * @return array
    */
    public function getAddress(): array
    {
        return $this->address;
    }

    /**
     * Get customized provider data
     *
     * @return array
     */
    public function providers(): array
    {
        return $this->manager->getProviders();
    }

    /**
     * Check Connection Providers
     *
     * @return array
     */
    public function isConnected(): array
    {
        return $this->manager->isConnected();
    }

    /**
     * Last block number
     *
     * @return array
     * @throws TronException
     */
    public function getCurrentBlock(): array
    {
        return $this->manager->request('wallet/getnowblock');
    }

    /**
     * Will return all events matching the filters.
     *
     * @param $contractAddress
     * @param int $sinceTimestamp
     * @param string|null $eventName
     * @param int $blockNumber
     * @return array
     * @throws TronException
     */
    public function getEventResult($contractAddress, int $sinceTimestamp = 0, string $eventName = null, int $blockNumber = 0)
    {
        if (!$this->isValidProvider($this->manager->eventServer())) {
            throw new TronException('No event server configured');
        }

        $routeParams = [];
        if($eventName && !$contractAddress) {
            throw new TronException('Usage of event name filtering requires a contract address');
        }

        if($blockNumber && !$eventName)
            throw new TronException('Usage of block number filtering requires an event name');

        if($contractAddress) {
            array_push($routeParams, $contractAddress);
        }
        if($eventName) {
            array_push($routeParams, $eventName);
        }
        if($blockNumber) {
            array_push($routeParams, $blockNumber);
        }

        $routeParams = implode('/', $routeParams);
        return $this->manager->request("event/contract/{$routeParams}?since={$sinceTimestamp}");
    }


    /**
     * Will return all events within a transactionID.
     *
     * @param string $transactionID
     * @return array
     * @throws TronException
     */
    public function getEventByTransactionID(string $transactionID)
    {
        if (!$this->isValidProvider($this->manager->eventServer())) {
            throw new TronException('No event server configured');
        }
        return $this->manager->request("event/transaction/{$transactionID}");
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
     * @throws TronException
     */
    public function getBlockByHash(string $hashBlock): array
    {
        return $this->manager->request('wallet/getblockbyid', [
            'value' =>  $hashBlock
        ]);
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

        $response = $this->manager->request('wallet/getblockbynum', [
            'num'   =>  intval($blockID)
        ]);

        if (empty($response)) {
            throw new TronException('Block not found');
        }
        return $response;
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
        $response = $this->manager->request('wallet/gettransactionbyid', [
            'value' =>  $transactionID
        ]);

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
     * @throws TronException
     */
    public function getTransactionInfo(string $transactionID): array
    {
        return $this->manager->request('walletsolidity/gettransactioninfobyid', [
            'value' =>  $transactionID
        ]);
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
     * @throws TronException
     */
    public function getAccount(string $address = null): array
    {
        $address = (!is_null($address) ? $this->toHex($address) : $this->address['hex']);

        return $this->manager->request('walletsolidity/getaccount', [
            'address'   =>  $address
        ]);
    }

    /**
     * Getting a balance
     *
     * @param string $address
     * @param bool $fromTron
     * @return float
     * @throws TronException
     */
    public function getBalance(string $address = null, bool $fromTron = false): float
    {
        $account = $this->getAccount($address);

        if(!array_key_exists('balance', $account)) {
            return 0;
        }

        return ($fromTron == true ?
            $this->fromTron($account['balance']) :
            $account['balance']);
    }


    /**
     * Get token balance
     *
     * @param string $address
     * @param int $tokenId
     * @param bool $fromTron
     * @return array|int
     * @throws TronException
     */
    public function getTokenBalance(int $tokenId, string $address, bool $fromTron = false)
    {
        $account = $this->getAccount($address);

        if(isset($account['assetV2']) and !empty($account['assetV2']) )
        {
            $value = array_filter($account['assetV2'], function($item) use ($tokenId) {
                return $item['key'] == $tokenId;
            });

            if(empty($value)) {
                throw new TronException('Token id not found');
            }

            $first = array_shift($value);
            return ($fromTron == true ? $this->fromTron($first['value']) : $first['value']);
        }

        return 0;
    }

    /**
     * Query bandwidth information.
     *
     * @param $address
     * @return array
     * @throws TronException
     */
    public function getBandwidth(string $address = null)
    {
        $address = (!is_null($address) ? $this->toHex($address) : $this->address['hex']);
        return $this->manager->request('wallet/getaccountnet', [
            'address'   =>  $address
        ]);
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

        $response = $this->manager->request(sprintf('walletextension/gettransactions%sthis', $direction), [
            'account'   =>  ['address' => $this->toHex($address)],
            'limit'     =>  $limit,
            'offset'    =>  $offset
        ]);

        return array_merge($response, ['direction' => $direction]);
    }

    /**
     * Count all transactions on the network
     *
     * @return integer
     * @throws TronException
     */
    public function getTransactionCount(): int
    {
        $response = $this->manager->request('wallet/totaltransaction');
        return $response['num'];
    }

    /**
     * Send transaction to Blockchain
     *
     * @param string $to
     * @param float $amount
     * @param string|null $message
     * @param string|null $from
     *
     * @return array
     * @throws TronException
     */
    public function sendTransaction(string $to, float $amount, string $from = null, string $message = null): array
    {
        if (is_null($from)) {
            $from = $this->address['hex'];
        }

        $transaction = $this->transactionBuilder->sendTrx($to, $amount, $from, $message);
        $signedTransaction = $this->signTransaction($transaction);


        $response = $this->sendRawTransaction($signedTransaction);
        return array_merge($response, $signedTransaction);
    }

    /**
     * Send token transaction to Blockchain
     *
     * @param string $to
     * @param float $amount
     * @param int $tokenID
     * @param string $from
     *
     * @return array
     * @throws TronException
     */
    public function sendTokenTransaction(string $to, float $amount, int $tokenID = null, string $from = null): array
    {
        if (is_null($from)) {
            $from = $this->address['hex'];
        }

        $transaction = $this->transactionBuilder->sendToken($to, $this->toTron($amount), (string)$tokenID, $from);
        $signedTransaction = $this->signTransaction($transaction);

        $response = $this->sendRawTransaction($signedTransaction);

        return array_merge($response, $signedTransaction);
    }

    /**
     * Sign the transaction, the api has the risk of leaking the private key,
     * please make sure to call the api in a secure environment
     *
     * @param $transaction
     * @param string|null $message
     * @return array
     * @throws TronException
     */
    public function signTransaction($transaction, string $message = null): array
    {
        if(!$this->privateKey) {
            throw new TronException('Missing private key');
        }

        if(!is_array($transaction)) {
            throw new TronException('Invalid transaction provided');
        }

        if(isset($transaction['Error']))
            throw new TronException($transaction['Error']);


        if(isset($transaction['signature'])) {
            throw new TronException('Transaction is already signed');
        }

        if(!is_null($message)) {
            $transaction['raw_data']['data'] = $this->stringUtf8toHex($message);
        }


        $signature = Support\Secp::sign($transaction['txID'], $this->privateKey);
        $transaction['signature'] = [$signature];

        return $transaction;
    }

    /**
     * Broadcast the signed transaction
     *
     * @param $signedTransaction
     * @return array
     * @throws TronException
     */
    public function sendRawTransaction($signedTransaction): array
    {
        if(!is_array($signedTransaction)) {
            throw new TronException('Invalid transaction provided');
        }

        if(!array_key_exists('signature', $signedTransaction) || !is_array($signedTransaction['signature'])) {
            throw new TronException('Transaction is not signed');
        }

        return $this->manager->request('wallet/broadcasttransaction',
            $signedTransaction);
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
        $address = (!is_null($address) ? $address : $this->address['hex']);

        $transaction = $this->manager->request('wallet/updateaccount', [
            'account_name'  =>  $this->stringUtf8toHex($account_name),
            'owner_address' =>  $this->toHex($address)
        ]);

        $signedTransaction = $this->signTransaction($transaction);
        $response = $this->sendRawTransaction($signedTransaction);

        return $response;
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
     * @param array token {
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
     * @throws TronException
     */
    public function createToken($token = [])
    {
        return $this->manager->request('wallet/createassetissue', [
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
        ]);
    }

    /**
     * Create an account.
     * Uses an already activated account to create a new account
     *
     * @param $address
     * @param $newAccountAddress
     * @return array
     * @throws TronException
     */
    public function registerAccount(string $address, string $newAccountAddress): array
    {
        return $this->manager->request('wallet/createaccount', [
            'owner_address'     =>  $this->toHex($address),
            'account_address'   =>  $this->toHex($newAccountAddress)
        ]);
    }

    /**
     * Apply to become a super representative
     *
     * @param $address
     * @param $url
     * @return array
     * @throws TronException
     */
    public function applyForSuperRepresentative(string $address, string $url)
    {
        return $this->manager->request('wallet/createwitness', [
            'owner_address' =>  $this->toHex($address),
            'url'           =>  $this->stringUtf8toHex($url)
        ]);
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
    public function sendToken(string $to, int $amount, string $tokenID, string $from = null)
    {
        if($from == null) {
            $from = $this->address['hex'];
        }

        $transfer = $this->transactionBuilder->sendToken($to, $amount, $tokenID, $from);
        $signedTransaction = $this->signTransaction($transfer);
        $response = $this->sendRawTransaction($signedTransaction);

        return array_merge($response, $signedTransaction);
    }

    /**
     * Purchase a Token
     * @param $issuerAddress
     * @param $tokenID
     * @param $amount
     * @param null $buyer
     * @return array
     * @throws TronException
     */
    public function purchaseToken($issuerAddress, $tokenID, $amount, $buyer = null)
    {
        if($buyer == null) {
            $buyer = $this->address['hex'];
        }

        $purchase = $this->transactionBuilder->purchaseToken($issuerAddress, $tokenID, $amount, $buyer);
        $signedTransaction = $this->signTransaction($purchase);
        $response = $this->sendRawTransaction($signedTransaction);

        return array_merge($response, $signedTransaction);
    }

    /**
     * Freezes an amount of TRX.
     * Will give bandwidth OR Energy and TRON Power(voting rights) to the owner of the frozen tokens.
     *
     * @param float $amount
     * @param int $duration
     * @param string $resource
     * @param string $owner_address
     * @return array
     * @throws TronException
     */
    public function freezeBalance(float $amount = 0, int $duration = 3, string $resource = 'BANDWIDTH', string $owner_address = null)
    {
        if($owner_address == null) {
            $owner_address = $this->address['hex'];
        }

        $freeze = $this->transactionBuilder->freezeBalance($amount, $duration, $resource, $owner_address);
        $signedTransaction = $this->signTransaction($freeze);
        $response = $this->sendRawTransaction($signedTransaction);

        return array_merge($response, $signedTransaction);
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
    public function unfreezeBalance(string $resource = 'BANDWIDTH', string $owner_address = null)
    {
        if($owner_address == null) {
            $owner_address = $this->address['hex'];
        }

        $unfreeze = $this->transactionBuilder->unfreezeBalance($resource, $owner_address);
        $signedTransaction = $this->signTransaction($unfreeze);
        $response = $this->sendRawTransaction($signedTransaction);

        return array_merge($response, $signedTransaction);
    }

    /**
     * Withdraw Super Representative rewards, useable every 24 hours.
     *
     * @param string $owner_address
     * @return array
     * @throws TronException
     */
    public function withdrawBlockRewards(string $owner_address = null)
    {
        if($owner_address == null) {
            $owner_address = $this->address['hex'];
        }

        $withdraw = $this->transactionBuilder->withdrawBlockRewards($owner_address);
        $signedTransaction = $this->signTransaction($withdraw);
        $response = $this->sendRawTransaction($signedTransaction);

        return array_merge($response, $signedTransaction);
    }

    /**
     * Update a Token's information
     *
     * @param string $description
     * @param string $url
     * @param int $freeBandwidth
     * @param int $freeBandwidthLimit
     * @param $owner_address
     * @return array
     * @throws TronException
     */
    public function updateToken(string $description,
                                string $url,
                                int $freeBandwidth = 0,
                                int $freeBandwidthLimit = 0,
                                string $owner_address = null)
    {
        if($owner_address == null) {
            $owner_address = $this->address['hex'];
        }

        $withdraw = $this->transactionBuilder->updateToken($description, $url, $freeBandwidth, $freeBandwidthLimit, $owner_address);
        $signedTransaction = $this->signTransaction($withdraw);
        $response = $this->sendRawTransaction($signedTransaction);

        return array_merge($response, $signedTransaction);
    }

    /**
     * Node list
     *
     * @return array
     * @throws TronException
     */
    public function listNodes(): array
    {
        $nodes = $this->manager->request('wallet/listnodes');
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
     * @throws TronException
     */
    public function getTokensIssuedByAddress(string $address = null)
    {
        $address = (!is_null($address) ? $this->toHex($address) : $this->address['hex']);
        return $this->manager->request('wallet/getassetissuebyaccount',[
            'address'   =>  $address
        ]);
    }

    /**
     * Query token by name.
     *
     * @param $tokenID
     * @return array
     * @throws TronException
     */
    public function getTokenFromID($tokenID = null)
    {
        return $this->manager->request('wallet/getassetissuebyname', [
            'value' =>  $this->stringUtf8toHex($tokenID)
        ]);
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

        return $this->manager->request('wallet/getblockbylimitnext', [
            'startNum'  =>  intval($start),
            'endNum'    =>  intval($end) + 1
        ])['block'];
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

        return $this->manager->request('wallet/getblockbylatestnum', [
            'num'   =>  $limit
        ])['block'];
    }

    /**
     * Query the list of Super Representatives
     *
     * @return array
     * @throws TronException
     */
    public function listSuperRepresentatives(): array
    {
        return $this->manager->request('wallet/listwitnesses')['witnesses'];
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
            return $this->manager->request('wallet/getassetissuelist')['assetIssue'];
        }

        return $this->manager->request('wallet/getpaginatedassetissuelist', [
            'offset'    =>  intval($offset),
            'limit'     =>  intval($limit)
        ])['assetIssue'];
    }

    /**
     * Get the time of the next Super Representative vote
     *
     * @return float
     * @throws TronException
     */
    public function timeUntilNextVoteCycle(): float
    {
        $num = $this->manager->request('wallet/getnextmaintenancetime')['num'];

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
     * @throws TronException
     */
    public function validateAddress(string $address = null, bool $hex = false): array
    {
        $address = (!is_null($address) ? $address : $this->address['hex']);
        if($hex) {
            $address = $this->toHex($address);
        }
        return $this->manager->request('wallet/validateaddress', [
            'address'   =>  $address
        ]);
    }

    /**
     * Validate Tron Address (Locale)
     *
     * @param string|null $address
     * @return bool
     */
    public function isAddress(string $address = null): bool
    {
        if(strlen($address) !== self::ADDRESS_SIZE)
            return false;

        $address = Base58Check::decode($address, 0, 0, false);
        $utf8 = hex2bin($address);

        if(strlen($utf8) !== 25) return false;
        if(strpos($utf8 , chr(self::ADDRESS_PREFIX_BYTE)) !== 0) return false;

        $checkSum = substr($utf8, 21);
        $address = substr($utf8, 0, 21);

        $hash0 = Hash::SHA256($address);
        $hash1 = Hash::SHA256($hash0);
        $checkSum1 = substr($hash1, 0, 4);

        if ($checkSum === $checkSum1)
            return true;
        return false;
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

        return $this->manager->request('wallet/deploycontract', [
            'owner_address' =>  $this->toHex($address),
            'fee_limit'     =>  $feeLimit,
            'call_value'    =>  $callValue,
            'consume_user_resource_percent' =>  $bandwidthLimit,
            'abi'           =>  $abi,
            'bytecode'      =>  $bytecode
        ]);
    }

    /**
     * Get a list of exchanges
     *
     * @return array
     * @throws TronException
     */
    public function listExchanges()
    {
        return $this->manager->request('/wallet/listexchanges', []);
    }

    /**
     * Query the resource information of the account
     *
     * @param string $address
     * @return array
     * @throws TronException
     */
    public function getAccountResources(string $address = null)
    {
        $address = (!is_null($address) ? $address : $this->address['hex']);

        return $this->manager->request('/wallet/getaccountresource', [
           'address' =>  $this->toHex($address)
        ]);
    }

    /**
     * Create a new account
     *
     * @return TronAddress
     * @throws TronException
     */
    public function createAccount(): TronAddress
    {
        return $this->generateAddress();
    }

    public function getAddressHex(string $pubKeyBin): string
    {
        if (strlen($pubKeyBin) == 65) {
            $pubKeyBin = substr($pubKeyBin, 1);
        }

        $hash = Keccak::hash($pubKeyBin, 256);

        return self::ADDRESS_PREFIX . substr($hash, 24);
    }

    public function getBase58CheckAddress(string $addressBin): string
    {
        $hash0 = Hash::SHA256($addressBin);
        $hash1 = Hash::SHA256($hash0);
        $checksum = substr($hash1, 0, 4);
        $checksum = $addressBin . $checksum;

        return Base58::encode(Crypto::bin2bc($checksum));
    }

    /**
     * Generate new address
     *
     * @return TronAddress
     * @throws TronException
     */
    public function generateAddress(): TronAddress
    {
        $ec = new EC('secp256k1');

        // Generate keys
        $key = $ec->genKeyPair();
        $priv = $ec->keyFromPrivate($key->priv);
        $pubKeyHex = $priv->getPublic(false, "hex");

        $pubKeyBin = hex2bin($pubKeyHex);
        $addressHex = $this->getAddressHex($pubKeyBin);
        $addressBin = hex2bin($addressHex);
        $addressBase58 = $this->getBase58CheckAddress($addressBin);

        return new TronAddress([
            'private_key' => $priv->getPrivate('hex'),
            'public_key'    => $pubKeyHex,
            'address_hex' => $addressHex,
            'address_base58' => $addressBase58
        ]);
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
     * Query token by id.
     *
     * @param string $token_id
     * @return array
     * @throws TronException
     */
    public function getTokenByID(string $token_id): array
    {
        if(!is_string($token_id))
            throw new TronException('Invalid token ID provided');

        return $this->manager->request('/wallet/getassetissuebyid', [
            'value' =>  $token_id
        ]);
    }
}
