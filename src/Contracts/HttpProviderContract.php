<?php
namespace IEXBase\TronAPI\Contracts;

interface HttpProviderContract
{
    /**
     * Указываем новую страницу
     *
     * @param string $page
     */
    public function setStatusPage($page = '/');

    /**
     * Проверить соединение
     *
     * @return bool
     */
    public function isConnected() : bool;

    /**
     * Отправляем запросы на сервер
     *
     * @param $url
     * @param array $payload
     * @param string $method
     * @return array
     */
    public function request($url, $payload = [], $method = 'get');
}
