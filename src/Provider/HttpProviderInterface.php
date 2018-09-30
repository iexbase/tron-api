<?php declare(strict_types=1);

namespace IEXBase\TronAPI\Provider;


interface HttpProviderInterface
{
    /**
     * Указываем новую страницу
     *
     * @param string $page
     */
    public function setStatusPage(string $page = '/');

    /**
     * Проверить соединение
     *
     * @return bool
     */
    public function isConnected() : bool;

    /**
     * Отправляем запросы на сервер
     *
     * @param string $url
     * @param array $payload
     * @param string $method
     * @return array
     */
    public function request($url, array $payload = [], string $method = 'get');
}
