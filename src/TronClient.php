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
     * Возвращает метод запроса
     *
     * @param string $method
     * @param string $type
     * @return string
     */
    protected function getMethod(string $method = 'POST', string $type = null) : string
    {
        if($method == 'auto' and $type == 'server') {
            return 'GET';
        } else {
            return 'POST';
        }
    }

    /**
     * Получаем отформатированные параметры для отправки
     *
     * @param $body
     * @return string
     */
    public function getBody($body)
    {
        unset($body['http_provider']); // Удаляем из массива
        return json_encode($body);
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
        $method = $this->getMethod($method, $body['http_provider']);

        $options = [
            'headers'   =>  $this->getDefaultHeaders(),
            'body'      =>  $this->getBody($body),
            'timeout'   =>  self::DEFAULT_REQUEST_TIMEOUT,
        ];

        //Увеличиваем запрос на +1
        $this->requestCount++;

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