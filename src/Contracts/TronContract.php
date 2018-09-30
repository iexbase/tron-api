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
     * @param string $address
     * @return array
     */
    public function getBalance(string $address = null);

    /**
     * Получаем информацию о транзакции по TxID
     *
     * @param $transactionID
     * @return array
     */
    public function getTransaction(string $transactionID);

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
    public function sendTransaction(string $from, string $to, float $amount);

    /**
     * Изменить имя учетной записи (только один раз)
     *
     * @param $address
     * @param $newName
     * @return array
     */
    public function changeAccountName(string $address = null, string $newName);

    /**
     * Регистрация новой учетной записи в сети
     *
     * @param $address
     * @param $newAccountAddress
     * @return array
     */
    public function registerAccount(string $address, string $newAccountAddress);

    /**
     * Применяется, чтобы стать супер представителем. Стоимость 9999 TRX.
     *
     * @param string $address
     * @param string $url
     * @return array
     */
    public function applyForSuperRepresentative(string $address, string $url);

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
     * @param string $to
     * @param float $amount
     * @param string $password
     * @return array
     */
    public function sendTransactionByPassword(string $to, float $amount, string $password);

    /**
     * Создаем и отправляем транзакцию с использованием приватного ключа
     *
     * @param string $to
     * @param float $amount
     * @param string $privateKey
     * @return array
     */
    public function sendTransactionByPrivateKey(string $to, float $amount, string $privateKey);

    /**
     * Создание нового адрес с паролем
     *
     * @param $password
     * @return array
     */
    public function createAddressWithPassword(string $password);

    /**
     * Создаем транзакцию с фиксированным балансом
     *
     * @param string $address
     * @param float $amount
     * @param int $duration
     * @return array
     */
    public function createFreezeBalanceTransaction(string $address, float $amount, int $duration = 3);

    /**
     * Создаем транзакцию баланса заморозки и размораживания
     *
     * @param $address
     * @return array
     */
    public function createUnfreezeBalanceTransaction(string $address);

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
    public function getLatestBlocks(int $limit = 1);

    /**
     * Проверка адреса
     *
     * @param string $address
     * @param bool $hex
     * @return array
     */
    public function validateAddress(string $address, bool $hex = false);

    /**
     * Генерация нового адреса
     *
     * @return array
     */
    public function generateAddress();
}
