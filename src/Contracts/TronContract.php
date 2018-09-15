<?php
namespace IEXBase\TronAPI\Contracts;

use IEXBase\TronAPI\Exceptions\TronException;

interface TronContract
{
    /**
     * Укажите ссылку на полную ноду
     * @param $provider
     */
    public function setFullNode($provider);

    /**
     * Указываем приватный ключ к учетной записи
     *
     * @param string $privateKey
     */
    public function setPrivateKey(string $privateKey): void;

    /**
     * Указываем базовый адрес учетной записи
     *
     * @param string $address
     */
    public function setAddress(string $address) : void;

    /**
     * Получение баланса учетной записи
     *
     * @param null $address
     * @return array
     */
    public function getBalance($address = null);

    /**
     * Получаем информацию о транзакции по TxID
     *
     * @param $transactionID
     * @return array
     */
    public function getTransaction($transactionID);

    /**
     * Получаем счетчик транзакций в Blockchain
     *
     * @return integer
     */
    public function getTransactionCount();

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
    public function sendTransaction($from, $to, $amount);

    /**
     * Изменить имя учетной записи (только один раз)
     *
     * @param $address
     * @param $newName
     * @return array
     */
    public function changeAccountName($address = null, $newName);

    /**
     * Регистрация новой учетной записи в сети
     *
     * @param $address
     * @param $newAccountAddress
     * @return array
     */
    public function registerAccount($address, $newAccountAddress);

    /**
     * Применяется, чтобы стать супер представителем. Стоимость 9999 TRX.
     *
     * @param $address
     * @param $url
     * @return array
     */
    public function applyForSuperRepresentative($address, $url);

    /**
     * Возвращает транзакцию передачи неподписанных активов
     *
     * @param $from
     * @param $to
     * @param $assetID
     * @param $amount
     * @return array
     */
    public function createSendAssetTransaction($from, $to, $assetID, $amount);

    /**
     * Создаем и отправляем транзакцию с использованием пароля
     *
     * @param $to
     * @param $amount
     * @param $password
     * @return array
     */
    public function sendTransactionByPassword($to, $amount, $password);

    /**
     * Создаем и отправляем транзакцию с использованием приватного ключа
     *
     * @param $to
     * @param $amount
     * @param $privateKey
     * @return array
     */
    public function sendTransactionByPrivateKey($to, $amount, $privateKey);

    /**
     * Создание нового адрес с паролем
     *
     * @param $password
     * @return array
     */
    public function createAddressWithPassword($password);

    /**
     * Создаем транзакцию с фиксированным балансом
     *
     * @param $address
     * @param $amount
     * @param int $duration
     * @return array
     */
    public function createFreezeBalanceTransaction($address, $amount, $duration = 3);

    /**
     * Создаем транзакцию баланса заморозки и размораживания
     *
     * @param $address
     * @return array
     */
    public function createUnfreezeBalanceTransaction($address);

    /**
     * Получаем детали блока с помощью HashString или blockNumber
     *
     * @param null $block
     * @return array
     */
    public function getBlock($block = null);

    /**
     * Получаем список последних блоков
     *
     * @param int $limit
     * @return array
     */
    public function getLatestBlocks($limit = 1);

    /**
     * Проверка адреса
     *
     * @param $address
     * @return array
     */
    public function validateAddress($address);

    /**
     * Генерация нового адреса
     *
     * @return array
     */
    public function generateAddress();
}
