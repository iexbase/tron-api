<?php
namespace IEXBase\TronAPI;

use IEXBase\TronAPI\Contracts\HttpProviderContract;
use IEXBase\TronAPI\Contracts\TronContract;
use IEXBase\TronAPI\Exceptions\TronException;
use IEXBase\TronAPI\Providers\HttpProvider;
use IEXBase\TronAPI\Support\Utils;

class Tron implements TronContract
{
    use Support\Traits\CryptoTrait;

    /**
     * URL полной ноды
     *
     * @var HttpProviderContract
    */
    protected $fullNode = 'http://13.125.210.234:8090';

    /**
     * Серверный нод TRON
     *
     * @var HttpProviderContract
    */
    protected $tronNode = 'https://server.tron.network';

    /**
     * Solidity URL Node
     *
     * @var HttpProviderContract
    */
    protected $solidityNode;

    /**
     * Адрес учетной записи
     *
     * @var string
    */
    protected $address;

    /**
     * Приватный ключ
     *
     * @var string
    */
    protected $privateKey;

    /**
     * Создаем новый объект Tron
     *
     * @param string $fullNode
     * @param string $solidityNode
     * @param string $privateKey
     */
    public function __construct($fullNode = null, $solidityNode = null, $privateKey = null)
    {
        if(!$fullNode instanceof HttpProvider) {
            $fullNode = new HttpProvider($this->fullNode);
        }

        if(!$solidityNode instanceof HttpProvider) {
            $solidityNode = new HttpProvider($this->solidityNode);
        }

        $tronNode = new HttpProvider($this->tronNode);

        $this->setFullNode($fullNode);
        $this->setSolidityNode($solidityNode);
        $this->setTronNode($tronNode);

        if($privateKey) {
            $this->setPrivateKey($privateKey);
        }
    }

    /**
     * Проверка провайдера
     *
     * @param $provider
     * @return bool
     */
    public function isValidProvider($provider) : bool
    {
        return ($provider instanceof HttpProvider);
    }

    /**
     * Укажите ссылку на полную ноду
     * @param $provider
     *
     * @return void | string
     */
    public function setFullNode($provider)
    {
        if(!$this->isValidProvider($provider)) {
            die('Invalid full node provided');
        }

        $this->fullNode = $provider;
        $this->fullNode->setStatusPage('wallet/getnowblock');
    }

    /**
     * Укажите ссылку на полную ноду
     * @param $provider
     *
     * @return void | string
     */
    public function setSolidityNode($provider)
    {
        if(!$this->isValidProvider($provider)) {
            die('Invalid solidity node provided');
        }

        $this->solidityNode = $provider;
        $this->solidityNode->setStatusPage('walletsolidity/getnowblock');
    }

    /**
     * Укажите ссылку на новую серверную ноду
     *
     * @param $provider
     *
     * @return void | string
     */
    public function setTronNode($provider)
    {
        if(!$this->isValidProvider($provider)) {
            die('Invalid tron node provided');
        }

        $this->tronNode = $provider;
    }

    /**
     * Указываем приватный ключ к учетной записи
     *
     * @param string $privateKey
     */
    public function setPrivateKey(string $privateKey): void
    {
        $this->privateKey = $privateKey;
    }

    /**
     * Указываем базовый адрес учетной записи
     *
     * @param string $address
     */
    public function setAddress(string $address) : void
    {
        $this->address = $address;
    }

    /**
     * Получаем настроенные данные провайдера
     *
     * @return array
    */
    public function currentProviders()
    {
        return [
            'fullNode'      =>  $this->fullNode,
            'solidityNode'  =>  $this->solidityNode,
            'tronNode'      =>  $this->tronNode
        ];
    }

    /**
     * Последний номер блока
     *
     * @return array
    */
    public function getCurrentBlock()
    {
        return $this->fullNode->request('wallet/getnowblock');
    }

    /**
     * Получаем детали блока с помощью HashString или blockNumber
     *
     * @param null $block
     * @return array
     */
    public function getBlock($block = null)
    {
        if(is_null($block)) {
            die('No block identifier provided');
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
     * Получаем информацию о блоке по Hash
     *
     * @param $hashBlock
     * @return array
     */
    public function getBlockByHash($hashBlock)
    {
        return $this->fullNode->request('wallet/getblockbyid', [
            'value' =>  $hashBlock
        ],'post');
    }

    /**
     * Получаем информацию о блоке по номеру
     *
     * @param $blockID
     * @return array
     */
    public function getBlockByNumber($blockID)
    {
        if(!is_integer($blockID) || $blockID < 0) {
            die('Invalid block number provided');
        }

        return $this->fullNode->request('wallet/getblockbynum', [
            'num'   =>  intval($blockID)
        ],'post');
    }

    /**
     * Получаем счетчик транзакций в блоке по hashString или blockNumber
     *
     * @param $block
     * @return int
     */
    public function getBlockTransactionCount($block)
    {
        $transaction = $this->getBlock($block)['transactions'];

        if(!$transaction) {
            return 0;
        }

        return count($transaction);
    }

    /**
     * Получаем детали транзакции из Блока
     *
     * @param null $block
     * @param int $index
     * @return array | string
     */
    public function getTransactionFromBlock($block = null, $index = 0)
    {
        if(!is_integer($index) || $index < 0) {
            die('Invalid transaction index provided');
        }

        $transactions = $this->getBlock($block)['transactions'];
        if(!$transactions || count($transactions) < $index) {
            die('Transaction not found in block');
        }

        return $transactions[$index];
    }

    /**
     * Получаем информацию о транзакции по TxID
     *
     * @param $transactionID
     * @return array
     */
    public function getTransaction($transactionID)
    {
        $response = $this->fullNode->request('wallet/gettransactionbyid', [
            'value' =>  $transactionID
        ],'post');

        if(!$response) {
            die('Transaction not found');
        }

        return $response;
    }

    /**
     * Получаем информацию о транзакции
     *
     * @param $transactionID
     * @return array
     */
    public function getTransactionInfo($transactionID)
    {
        return $this->solidityNode->request('walletsolidity/gettransactioninfobyid', [
            'value' =>  $transactionID
        ],'post');
    }

    /**
     * Получение транзакций по направлении "to"
     *
     * @param null $address
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getTransactionsToAddress($address = null, $limit = 30, $offset = 0)
    {
        return $this->getTransactionsRelated($address,'to', $limit, $offset);
    }

    /**
     * Получение транзакций по направлении "from"
     *
     * @param null $address
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getTransactionsFromAddress($address = null, $limit = 30, $offset = 0)
    {
        return $this->getTransactionsRelated($address,'from', $limit, $offset);
    }

    /**
     * Информация об аккаунте
     *
     * @param $address
     * @return array
     */
    public function getAccount($address = null)
    {
        $address = (is_string($address) ? $address : $this->address);

        return $this->fullNode->request('wallet/getaccount', [
            'address'   =>  $this->toHex($address)
        ],'post');
    }

    /**
     * Получение баланса
     *
     * @param null $address
     * @return mixed
     */
    public function getBalance($address = null)
    {
        $address = (is_string($address) ? $address : $this->address);

       $balance = $this->getAccount($address)['balance'];

       if(!$balance) {
           return 0;
       }

       return $balance;
    }

    /**
     * Выбирает доступную пропускную способность для определенной учетной записи
     *
     * @param $address
     * @return array
     */
    public function getBandwidth($address = null)
    {
        $address = (is_string($address) ? $address : $this->address);

        return $this->fullNode->request('wallet/getaccountnet', [
            'address'   =>  $this->toHex($address)
        ],'post');
    }

    /**
     * Получение транзакций по направлениям "from" и "to"
     *
     * @param null $address
     * @param string $direction
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getTransactionsRelated($address = null, $direction = 'from', $limit = 30, $offset = 0)
    {
        if(!in_array($direction, ['to', 'from'])) {
            die('Invalid direction provided: Expected "to", "from"');
        }

        if(!is_integer($limit) || $limit < 0 || ($offset && $limit) < 1) {
            die('Invalid limit provided');
        }

        if(!is_integer($offset) || $offset < 0) {
            die('Invalid offset provided');
        }

        $response = $this->solidityNode->request(sprintf('walletextension/gettransactions%sthis', $direction), [
            'account'   =>  ['address' => $this->toHex($address)],
            'limit'     =>  $limit,
            'offset'    =>  $offset
        ],'post');

        return array_merge($response, ['direction' => $direction]);
    }

    /**
     * Получаем счетчик транзакций в Blockchain
     *
     * @return integer
    */
    public function getTransactionCount()
    {
        $response = $this->fullNode->request('wallet/totaltransaction');
        return $response['num'];
    }

    /**
     * Отправляем транзакцию в Blockchain
     *
     * @param $from
     * @param $to
     * @param $amount
     *
     * @return array
     * @throws TronException
     */
    public function sendTransaction($from, $to, $amount)
    {
        if(!$this->privateKey) {
            throw new TronException('Missing private key');
        }

        $transaction = $this->createTransaction($from, $to, $amount);
        $signedTransaction = $this->signTransaction($transaction);
        $response = $this->sendRawTransaction($signedTransaction);

        return array_merge($response, $signedTransaction);
    }

    /**
     * Создаем неподписанную транзакцию
     *
     * @param $from
     * @param $to
     * @param $amount
     * @return array
     */
    public function createTransaction($from, $to, $amount)
    {
        $response = $this->fullNode->request('wallet/createtransaction', [
            'to_address'    =>  $this->toHex($to),
            'owner_address' =>  $this->toHex($from),
            'amount'        =>  $this->toTron($amount),
        ], 'post');

        return $response;
    }

    /**
     * Подписываем транзакцию с использованием PrivateKey
     *
     * @param $transaction
     * @return array
     */
    public function signTransaction($transaction)
    {
        if(!is_array($transaction)) {
            die('Invalid transaction provided');
        }

        if(isset($transaction['signature'])) {
            die('Transaction is already signed');
        }

        return $this->fullNode->request('wallet/gettransactionsign', [
            'transaction'   => $transaction,
            'privateKey'    => $this->privateKey
        ],'post');
    }

    /**
     * Отправляем подписанную транзакцию
     *
     * @param $signedTransaction
     * @return array
     */
    public function sendRawTransaction($signedTransaction)
    {
        if(!is_array($signedTransaction)) {
            die('Invalid transaction provided');
        }

        if(!$signedTransaction['signature'] || !is_array($signedTransaction['signature'])) {
            die('Transaction is not signed');
        }

        return $this->fullNode->request('wallet/broadcasttransaction',
            $signedTransaction,'post');
    }

    /**
     * Изменить имя учетной записи (только один раз)
     *
     * @param $address
     * @param $newName
     * @return array
     */
    public function changeAccountName($address = null, $newName)
    {
        $address = (is_string($address) ? $address : $this->address);

        $transaction = $this->fullNode->request('wallet/updateaccount', [
            'account_name'  =>  $this->stringUtf8toHex($newName),
            'owner_address' =>  $this->toHex($address)
        ],'post');

        $signedTransaction = $this->signTransaction($transaction);
        $response = $this->sendRawTransaction($signedTransaction);

        return $response;
    }

    /**
     * Создание нового токена на базе Tron
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
        ]);
    }

    /**
     * Регистрация новой учетной записи в сети
     *
     * @param $address
     * @param $newAccountAddress
     * @return array
     */
    public function registerAccount($address, $newAccountAddress)
    {
        return $this->fullNode->request('wallet/createaccount', [
            'owner_address'     =>  $this->toHex($address),
            'account_address'   =>  $this->toHex($newAccountAddress)
        ],'post');
    }

    /**
     * Применяется, чтобы стать супер представителем. Стоимость 9999 TRX.
     *
     * @param $address
     * @param $url
     * @return array
     */
    public function applyForSuperRepresentative($address, $url)
    {
        return $this->fullNode->request('wallet/createwitness', [
            'owner_address' =>  $this->toHex($address),
            'url'           =>  $this->stringUtf8toHex($url)
        ],'post');
    }

    /**
     * Возвращает транзакцию передачи неподписанных активов
     *
     * @param $from
     * @param $to
     * @param $assetID
     * @param $amount
     * @return array
     */
    public function createSendAssetTransaction($from, $to, $assetID, $amount)
    {
        $from = (is_string($from) ? $from : $this->address);

        return $this->fullNode->request('wallet/transferasset', [
            'owner_address' =>  $this->toHex($from),
            'to_address'    =>  $this->toHex($to),
            'asset_name'    =>  $this->stringUtf8toHex($assetID),
            'amount'        =>  $this->toTron($amount)
        ],'post');
    }

    /**
     * Создаем и отправляем транзакцию с использованием пароля
     *
     * @param $to
     * @param $amount
     * @param $password
     * @return array
     */
    public function sendTransactionByPassword($to, $amount, $password)
    {
        return $this->fullNode->request('wallet/easytransfer', [
            'passPhrase'    =>  $this->stringUtf8toHex($password),
            'toAddress'     =>  $this->toHex($to),
            'amount'        =>  $this->toTron($amount)
        ],'post');
    }

    /**
     * Создаем и отправляем транзакцию с использованием приватного ключа
     *
     * @param $to
     * @param $amount
     * @param $privateKey
     * @return array
     */
    public function sendTransactionByPrivateKey($to, $amount, $privateKey)
    {
        return $this->fullNode->request('wallet/easytransferbyprivate', [
            'privateKey'    =>  $this->stringUtf8toHex($privateKey),
            'toAddress'     =>  $this->toHex($to),
            'amount'        =>  $this->toTron($amount)
        ],'post');
    }

    /**
     * Создание нового адрес с паролем
     *
     * @param $password
     * @return array
     */
    public function createAddressWithPassword($password)
    {
        return $this->fullNode->request('wallet/createaddress', [
            'value' =>  $this->stringUtf8toHex($password)
        ],'post');
    }

    /**
     * Создаем транзакцию для покупки активов
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
     * Создаем транзакцию с фиксированным балансом
     *
     * @param $address
     * @param $amount
     * @param int $duration
     * @return array
     */
    public function createFreezeBalanceTransaction($address, $amount, $duration = 3)
    {
        return $this->fullNode->request('wallet/freezebalance', [
            'owner_address'     =>  $this->toHex($address),
            'frozen_balance'    =>  $this->toTron($amount),
            'frozen_duration'   =>  $duration
        ],'post');
    }

    /**
     * Создаем транзакцию баланса заморозки и размораживания
     *
     * @param $address
     * @return array
     */
    public function createUnfreezeBalanceTransaction($address)
    {
        return $this->fullNode->request('wallet/unfreezebalance', [
            'owner_address' =>  $this->toHex($address)
        ],'post');
    }

    /**
     * Создает транзакцию без разглашения (используется для учетных записей, создавших замороженный актив)
     *
     * @param $address
     * @return array
     */
    public function createUnfreezeAssetTransaction($address)
    {
        return $this->fullNode->request('wallet/unfreezeasset', [
            'owner_address' =>  $this->toHex($address)
        ],'post');
    }

    /**
     * Создаем транзакцию для SRs, чтобы снять свои бонусные вознаграждения
     *
     * @param $address
     * @return array
     */
    public function createWithdrawBlockRewardTransaction($address)
    {
        return $this->fullNode->request('wallet/withdrawbalance', [
            'owner_address' =>  $this->toHex($address)
        ],'post');
    }

    /**
     * Создаем транзакцию для изменения метаинформации актива
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
     * Список нодов
     *
     * @return array
    */
    public function listNodes()
    {
        return $this->fullNode->request('wallet/listnodes');
    }

    /**
     * Попытки найти токен с адресом учетной записи, который его выпустил
     *
     * @param $address
     * @return array
     */
    public function getTokensIssuedByAddress($address = null)
    {
        $address = (is_string($address) ? $address : $this->address);

        return $this->fullNode->request('wallet/getassetissuebyaccount',[
            'address'   =>  $this->toHex($address)
        ],'post');
    }

    /**
     * Попытки найти токен по имени
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
     * Получаем список блоков из определенного диапазона
     *
     * @param $start
     * @param $end
     * @return array
     */
    public function getBlockRange($start = 0, $end = 30)
    {
        if(!is_integer($start) || $start < 0) {
            die('Invalid start of range provided');
        }

        if(!is_integer($end) || $end <= $start) {
            die('Invalid end of range provided');
        }

        return $this->fullNode->request('wallet/getblockbylimitnext', [
            'startNum'  =>  intval($start),
            'endNum'    =>  intval($end) + 1
        ])['block'];
    }

    /**
     * Получаем список последних блоков
     *
     * @param int $limit
     * @return array
     */
    public function getLatestBlocks($limit = 1)
    {
        if(!is_integer($limit) || $limit <= 0) {
            die('Invalid limit provided');
        }

        return $this->fullNode->request('wallet/getblockbylatestnum', [
            'num'   =>  $limit
        ])['block'];
    }

    /**
     * Получаем список суперпредставителей
     *
     * @return array
    */
    public function listSuperRepresentatives()
    {
        return $this->fullNode->request('wallet/listwitnesses')['witnesses'];
    }

    /**
     * Получаем список выпущенных токенов
     *
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function listTokens($limit = 0, $offset = 0)
    {
        if(!is_integer($limit) || $limit < 0 || ($offset && $limit < 1)) {
            die('Invalid limit provided');
        }

        if(!is_integer($offset) || $offset < 0) {
            die('Invalid offset provided');
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
     * Возвращает время в миллисекундах до следующего подсчета голосов SR
     *
     * @return array
    */
    public function timeUntilNextVoteCycle()
    {
        $num = $this->fullNode->request('wallet/getnextmaintenancetime')['num'];

        if($num == -1) {
            die('Failed to get time until next vote cycle');
        }

        return floor($num / 1000);
    }

    /**
     * Проверка адреса
     *
     * @param $address
     * @param bool $hex
     * @return array
     */
    public function validateAddress($address = null, $hex = false)
    {
        $address = (is_string($address) ? $address : $this->address);

        if($hex) {
            $address = $this->toHex($address);
        }

        return $this->fullNode->request('wallet/validateaddress', [
            'address'   =>  $address
        ]);
    }

    /**
     * Создаем транзакцию для развертывания контракта
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
        $payable = array_filter(json_decode($abi, true), function($v) {
            if($v['type'] == 'constructor' && $v['payable']) {
                return $v['payable'];
            }
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
     * Получение контракта
     *
     * @param $contractAddress
     * @return array
     */
    public function getContract($contractAddress)
    {
        return $this->fullNode->request('wallet/getcontract', [
            'value' =>  $this->toHex($contractAddress)
        ]);
    }

    /**
     * Freeze TRX, получить пропускную способность, получить права голоса или энергию
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
     * Генерация нового адреса
     *
     * @return array
    */
    public function generateAddress() : array
    {
        return $this->fullNode->request('wallet/generateaddress');
    }

    /**
     * Статистика учетных записей (с крупными балансами)
     *
     * @return array
     */
    public function getBalanceInfo() : array
    {
        return $this->tronNode->request('api/v2/node/balance_info');
    }

    /**
     * Получаем карту узлов
     *
     * @return array
     */
    public function getNodeMap() : array
    {
        return $this->tronNode->request('api/v2/node/nodemap');
    }

    /**
     * Проверка всех подключенных нодов
     *
     * @return array
    */
    public function isConnected() : array
    {
        return [
            'fullNode'      =>  $this->fullNode->isConnected(),
            'solidityNode'  =>  $this->solidityNode->isConnected()
        ];
    }
}
