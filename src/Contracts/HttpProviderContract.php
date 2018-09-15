<?php
namespace IEXBase\TronAPI\Contracts;

interface HttpProviderContract
{
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
