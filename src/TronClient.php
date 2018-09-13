<?php
namespace IEXBase\TronAPI;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Request;

class TronClient
{
    /**
     * Тайм-аут в секундах для обычного запроса.
     *
     * @const int
     */
    const DEFAULT_REQUEST_TIMEOUT = 60;

    /**
     * Обработчик HTTP-клиента
     *
     * @var ClientInterface.
     */
    protected $httpClientHandler;

    /**
     * Счетчик запросов
     *
     * @var integer
    */
    protected $requestCount = 0;

    /**
     * Создаем новый объект TronClient.
    */
    public function __construct()
    {
        $this->httpClientHandler = new Client();
    }

    /**
     * Возвращает обработчик HTTP-клиента.
     *
     * @return ClientInterface
     */
    public function getHttpClientHandler()
    {
        return $this->httpClientHandler;
    }

    /**
     * Отправляем запросы на активный сервер
     *
     * @param $method
     * @param $url
     * @param $body
     * @return array
     */
    public function sendRequest($method, $url, $body)
    {
        $this->requestCount++;
        $options = [
            'headers'   =>  $this->getDefaultHeaders(),
            'body'      =>  json_encode($body),
            'timeout'   =>  self::DEFAULT_REQUEST_TIMEOUT,
        ];

        try {
            $request = new Request($method, $url, $options['headers'], $options['body']);
            $rawResponse = $this->httpClientHandler->send($request, $options);

            $returnResponse = new TronResponse(
                $rawResponse->getBody(),
                $rawResponse->getStatusCode()
            );

        } catch (Exceptions\TronException $e) {
            die($e->getMessage());
        }

        return $returnResponse->getDecodedBody();
    }

    /**
     * Получаем заголовки по умолчанию
     *
     * @return array
     */
    public function getDefaultHeaders()
    {
        return [
            'User-Agent' => 'TronAPI-PHP-'.Tron::VERSION,
        ];
    }
}