<?php
namespace IEXBase\TronAPI;

use IEXBase\TronAPI\Contracts\TronContract;
use IEXBase\TronAPI\Exceptions\TronException;

class Tron implements TronContract
{
    use Support\Traits\CryptoTrait;

    /**
     * Версия Tron API библиотеки
     *
     * @const string
     */
    const VERSION = 'v1.3';

    /**
     * Экземпляр приложения TronClient.
     *
     * @var TronClient
    */
    protected $client;

    /**
     * URL полной ноды
     *
     * @var string
    */
    protected $urlFullNode = 'http://13.125.210.234:8090';

    /**
     * Серверный нод TRON
     *
     * @var string
    */
    protected $tronServer = 'https://server.tron.network/api/v2/node';

    /**
     * Адрес учетной записи
     *
     * @var string
    */
    protected $accountAddress;

    /**
     * Приватный ключ
     *
     * @var string
    */
    protected $privateKey;

    /**
     * Создаем новый объект Tron
     *
     * @param $address
     * @param null $privateKey
     */
    public function __construct($address = null, $privateKey = null)
    {
        if(!$this->urlFullNode) {
            die('Warning: No Fullnode API provided. Functionality may be limited');
        }

        $this->accountAddress = $address;
        $this->privateKey = $privateKey;

        $this->client = new TronClient();
    }

    /**
     * Укажите ссылку на полную ноду
     * @param $url
     */
    public function setFullNodeServer($url) : void
    {
        $this->urlFullNode = $url;
    }

    /**
     * Укажите ссылку на новую серверную ноду
     *
     * @param $url
     */
    public function setTronServer($url) : void
    {
        $this->tronServer = $url;
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
    public function setAccount(string $address) : void
    {
        $this->accountAddress = $address;
    }

    /**
     * Получение баланса учетной записи
     *
     * @param null $address
     * @return array
     */
    public function getBalance($address = null)
    {
        $address = (isset($address) ? $address : $this->accountAddress);

        return $this->call('/wallet/getaccount', [
            'address'   =>  $this->toHex($address)
        ]);
    }

    /**
     * Последний номер блока
     *
     * @return array
    */
    public function latestBlockNumber()
    {
        return $this->call('/wallet/getnowblock');
    }

    /**
     * Получаем детали блока с помощью HashString или blockNumber
     *
     * @param $blockIdentifier
     * @return array
     */
    public function getBlock($blockIdentifier)
    {
        if(is_nan($blockIdentifier))
        {
            return $this->call('/wallet/getblockbyid', [
                'value' =>  $blockIdentifier
            ]);
        }

        return $this->call('/wallet/getblockbynum', [
            'num'   =>  (int)$blockIdentifier
        ]);
    }

    /**
     * Получаем информацию о блоке по Hash
     *
     * @param $hashBlock
     * @return array
     */
    public function getBlockByHash($hashBlock)
    {
        return $this->call('/wallet/getblockbyid', [
            'value' =>  $hashBlock
        ]);
    }

    /**
     * Получаем информацию о блоке по номеру
     *
     * @param $blockNumber
     * @return array
     */
    public function getBlockByNumber($blockNumber)
    {
        return $this->call('/wallet/getblockbynum', [
            'num'   =>  (int)$blockNumber
        ]);
    }

    /**
     * Получаем счетчик транзакций в блоке по hashString или blockNumber
     *
     * @param $blockIdentifier
     * @return int
     */
    public function getBlockTransactionCount($blockIdentifier)
    {
        $transaction = $this->getBlock($blockIdentifier)['transactions'];

        if(!$transaction) {
            return 0;
        }

        return sizeof($transaction);
    }

    /**
     * Получаем информацию о транзакции по TxID
     *
     * @param $transactionID
     * @return array
     */
    public function getTransaction($transactionID)
    {
        return $this->call('/wallet/gettransactionbyid', [
           'value'  =>  $transactionID
        ]);
    }

    /**
     * Получаем счетчик транзакций в Blockchain
     *
     * @return integer
    */
    public function getTransactionCount()
    {
        $response = $this->call('/wallet/totaltransaction');
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
        $response = $this->call('/wallet/createtransaction', [
            'to_address'    =>  $this->toHex($to),
            'owner_address' =>  $this->toHex($from),
            'amount'        =>  $this->trxToSun($amount)
        ]);

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
        return $this->call('/wallet/gettransactionsign', [
            'transaction' => $transaction,
            'privateKey' => $this->privateKey
        ]);
    }

    /**
     * Отправляем подписанную транзакцию
     *
     * @param $signedTransaction
     * @return array
     */
    public function sendRawTransaction($signedTransaction)
    {
        return $this->call('/wallet/broadcasttransaction', $signedTransaction);
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
        $address = (isset($address) ? $address : $this->accountAddress);

        $transaction = $this->call('/wallet/updateaccount', [
            'account_name'  =>  $this->stringUtf8toHex($newName),
            'owner_address' =>  $this->toHex($address)
        ]);

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
        return $this->call('/wallet/createassetissue', [
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
        return $this->call('/wallet/createaccount', [
            'owner_address'     =>  $this->toHex($address),
            'account_address'   =>  $this->toHex($newAccountAddress)
        ]);
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
        return $this->call('/wallet/createwitness', [
            'owner_address' =>  $this->toHex($address),
            'url'           =>  $this->stringUtf8toHex($url)
        ]);
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
        return $this->call('/wallet/transferasset', [
            'owner_address'  =>  $this->toHex($from),
            'to_address'    =>  $this->toHex($to),
            'asset_name'    =>  $this->stringUtf8toHex($assetID),
            'amount'        =>  $this->trxToSun($amount)
        ]);
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
        return $this->call('/wallet/easytransfer', [
            'passPhrase'    =>  $this->stringUtf8toHex($password),
            'toAddress'     =>  $this->toHex($to),
            'amount'        =>  $this->trxToSun($amount)
        ]);
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
        return $this->call('/wallet/easytransferbyprivate', [
            'privateKey'    =>  $this->stringUtf8toHex($privateKey),
            'toAddress'     =>  $this->toHex($to),
            'amount'        =>  $this->trxToSun($amount)
        ]);
    }

    /**
     * Создание нового адрес с паролем
     *
     * @param $password
     * @return array
     */
    public function createAddressWithPassword($password)
    {
        return $this->call('/wallet/createaddress', [
            'value' =>  $this->stringUtf8toHex($password)
        ]);
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
        return $this->call('/wallet/participateassetissue', [
            'to_address'    =>  $this->toHex($tokenIssuer),
            'owner_address' =>  $this->toHex($address),
            'asset_name'    =>  $this->stringUtf8toHex($assetID),
            'amount'        =>  $this->trxToSun($amount)
        ]);
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
        return $this->call('/wallet/freezebalance', [
            'owner_address'     =>  $this->toHex($address),
            'frozen_balance'    =>  $this->trxToSun($amount),
            'frozen_duration'   =>  $duration
        ]);
    }

    /**
     * Создаем транзакцию баланса заморозки и размораживания
     *
     * @param $address
     * @return array
     */
    public function createUnfreezeBalanceTransaction($address)
    {
        return $this->call('/wallet/unfreezebalance', [
            'owner_address' =>  $this->toHex($address)
        ]);
    }

    /**
     * Создает транзакцию без разглашения (используется для учетных записей, создавших замороженный актив)
     *
     * @param $address
     * @return array
     */
    public function createUnfreezeAssetTransaction($address)
    {
        return $this->call('/wallet/unfreezeasset', [
            'owner_address' =>  $this->toHex($address)
        ]);
    }

    /**
     * Создаем транзакцию для SRs, чтобы снять свои бонусные вознаграждения
     *
     * @param $address
     * @return array
     */
    public function createWithdrawBlockRewardTransaction($address)
    {
        return $this->call('/wallet/withdrawbalance', [
            'owner_address' =>  $this->toHex($address)
        ]);
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
        return $this->call('/wallet/updateasset', [
           'owner_address'      =>  $this->toHex($address),
           'description'        =>  $this->stringUtf8toHex($description),
            'url'               =>  $this->stringUtf8toHex($url),
            'new_limit'         =>  $bandwidthLimit,
            'new_public_limit'  =>  $freeBandwidthLimit
        ]);
    }

    /**
     * Список нодов
     *
     * @return array
    */
    public function listNodes()
    {
        return $this->call('/wallet/listnodes');
    }

    /**
     * Попытки найти токен с адресом учетной записи, который его выпустил
     *
     * @param $address
     * @return array
     */
    public function getTokenFromAddress($address)
    {
        return $this->call('/wallet/getassetissuebyaccount',[
            'address'   =>  $this->toHex($address)
        ]);
    }

    /**
     * Выбирает доступную пропускную способность для определенной учетной записи
     *
     * @param $address
     * @return array
     */
    public function getAccountBandwidth($address)
    {
        return $this->call('/wallet/getaccountnet', [
            'address'   =>  $this->toHex($address)
        ]);
    }

    /**
     * Попытки найти токен по имени
     *
     * @param $name
     * @return array
     */
    public function getAssetIssueFromName($name)
    {
        return $this->call('/wallet/getassetissuebyname', [
            'value' =>  $this->stringUtf8toHex($name)
        ]);
    }

    /**
     * Получаем список блоков из определенного диапазона
     *
     * @param $startBlock
     * @param $endBlock
     * @return array
     */
    public function getBlocksInRange($startBlock, $endBlock)
    {
        return $this->call('/wallet/getblockbylimitnext', [
            'startNum'  =>  $startBlock,
            'endNum'    =>  $endBlock
        ]);
    }

    /**
     * Получаем список последних блоков
     *
     * @param int $limit
     * @return array
     */
    public function getLatestBlocks($limit = 1)
    {
        return $this->call('/wallet/getblockbylatestnum', [
            'num'   =>  $limit
        ]);
    }

    /**
     * Получаем список суперпредставителей
     *
     * @return array
    */
    public function listSuperRepresentatives()
    {
        return $this->call('/wallet/listwitnesses');
    }

    /**
     * Получаем список выпущенных токенов
     *
     * @return array
    */
    public function listAssets()
    {
        return $this->call('/wallet/getassetissuelist');
    }

    /**
     * Получаем постраничный список выпущенных токенов
     *
     * @param $limit
     * @param int $offset
     * @return array
     */
    public function listAssetsPaginated($limit, $offset = 0)
    {
        return $this->call('/wallet/getpaginatedassetissuelist', [
            'limit'     =>  $limit,
            'offset'    =>  $offset
        ]);
    }

    /**
     * Возвращает время в миллисекундах до следующего подсчета голосов SR
     *
     * @return array
    */
    public function timeUntilNextVoteCycle()
    {
        return $this->call('/wallet/getnextmaintenancetime');
    }

    /**
     * Проверка адреса
     *
     * @param $address
     * @return array
     */
    public function validateAddress($address)
    {
        return $this->call('/wallet/validateaddress', [
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

        return $this->call('/wallet/deploycontract', [
            'owner_address' =>  $this->toHex($address),
            'fee_limit'     =>  $feeLimit,
            'call_value'    =>  $callValue,
            'consume_user_resource_percent' =>  $bandwidthLimit,
            'abi'           =>  $abi,
            'bytecode'      =>  $bytecode
        ]);
    }

    /**
     * Получение контракта
     *
     * @param $contractAddress
     * @return array
     */
    public function getContract($contractAddress)
    {
        return $this->call('/wallet/getcontract', [
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
        return $this->call('/wallet/freezebalance', [
            'owner_address'     =>  $this->toHex($owner_address),
            'frozen_balance'    =>  $frozen_balance,
            'frozen_duration'   =>  $frozen_duration,
            'resource'          =>  $resource
        ]);
    }

    /**
     * Генерация нового адреса
     *
     * @return array
    */
    public function generateAddress() : array
    {
        return $this->call('/wallet/generateaddress');
    }

    /**
     * Статистика учетных записей (с крупными балансами)
     *
     * @return array
     */
    public function getBalanceInfo() : array
    {
        return $this->call('/balance_info', [
            'http_provider'  =>  'server'
        ]);
    }

    /**
     * Получаем карту узлов
     *
     * @return array
     */
    public function getNodeMap() : array
    {
        return $this->call('/nodemap', [
            'http_provider'  =>  'server'
        ]);
    }

    /**
     * Получаем базовую ссылку
     *
     * @param $type
     * @return string
     */
    protected function getUrl($type = null)
    {
        if($type == 'server')
            return $this->tronServer;

        return $this->urlFullNode;
    }

    /**
     * Отправка запросов
     *
     * @param $path
     * @param array $options
     *
     * @return array
     */
    protected function call($path, $options = [])
    {
        $response = $this->client->sendRequest('auto',
            sprintf('%s%s', $this->getUrl($options['http_provider']), $path), $options);

       return $response;
    }
}