<?php
namespace IEXBase\TronAPI;

use IEXBase\TronAPI\Exception\TronException;

class TronAddress
{
    /**
     * Результаты генерации адресов
     *
     * @var array
    */
    protected $response = [];

    /**
     * Конструктор
     * @param array $data
     * @throws TronException
     */
    public function __construct(array $data)
    {
        $this->response = $data;

        // Проверяем ключи, перед выводом результатов
        if(!$this->array_keys_exist($this->response, ['address_hex', 'private_key', 'public_key'])) {
            throw new TronException('Incorrectly generated address');
        }
    }

    /**
     * Получение адреса
     *
     * @param bool $is_base58
     * @return string
     */
    public function getAddress(bool $is_base58 = false): string
    {
        return $this->response[($is_base58 == false) ? 'address_hex' : 'address_base58'];
    }

    /**
     * Получение публичного ключа
     *
     * @return string
     */
    public function getPublicKey(): string
    {
        return $this->response['public_key'];
    }

    /**
     * Получение приватного ключа
     *
     * @return string
     */
    public function getPrivateKey(): string
    {
        return $this->response['private_key'];
    }

    /**
     * Получение результатов в массике
     *
     * @return array
    */
    public function getRawData(): array
    {
        return $this->response;
    }

    /**
     * Проверка нескольких ключей
     *
     * @param array $array
     * @param array $keys
     * @return bool
     */
    private function array_keys_exist(array $array, array $keys = []): bool
    {
        $count = 0;
        if (!is_array($keys)) {
            $keys = func_get_args();
            array_shift($keys);
        }
        foreach ($keys as $key) {
            if (isset( $array[$key]) || array_key_exists($key, $array)) {
                $count ++;
            }
        }

        return count($keys) === $count;
    }
}