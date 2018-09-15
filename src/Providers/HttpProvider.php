<?php
namespace IEXBase\TronAPI\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Request;
use IEXBase\TronAPI\Contracts\HttpProviderContract;
use IEXBase\TronAPI\Exceptions\TronException;
use IEXBase\TronAPI\Support\Utils;
use IEXBase\TronAPI\TronResponse;

class HttpProvider implements HttpProviderContract
{
    /**
     * Обработчик HTTP-клиента
     *
     * @var ClientInterface.
     */
    protected $httpClient;

    /**
     * URL Сервера или RPC
     *
     * @var string
    */
    protected $host;

    /**
     * Время ожидания
     *
     * @var int
     */
    protected $timeOut = 30000;

    /**
     * Имя пользователя
     *
     * @var int
     */
    protected $user = null;

    /**
     * Пароль пользователя
     *
     * @var int
     */
    protected $password = null;

    /**
     * Получаем кастомные заголовки
     *
     * @var array
    */
    protected $headers = [];

    /**
     * Получаем страницы
     *
     * @var string
    */
    protected $statusPage = '/';

    /**
     * Создаем объект HttpProvider
     *
     * @param $host
     * @param int $timeout
     * @param $user
     * @param $password
     * @param array $headers
     * @param string $statusPage
     */
    public function __construct($host, $timeout = 30000, $user = false, $password = false, $headers = [], $statusPage = '/')
    {
        if(!Utils::isValidUrl($host)) {
            die('Invalid URL provided to HttpProvider');
        }

        if(is_nan($timeout) || $timeout < 0) {
            die('Invalid timeout duration provided');
        }

        if(!Utils::isArray($headers)) {
            die('Invalid headers array provided');
        }

        if(substr($host,strlen($host) - 1) === '/') {
            $host = substr($host, 0,strlen($host) - 1);
        }

        $this->host = $host;
        $this->timeOut = $timeout;
        $this->user = $user;
        $this->password = $password;
        $this->statusPage = $statusPage;


        $this->httpClient = new Client([
            'base_uri'  =>  $this->host,
            'timeout'   =>  $this->timeOut,
            'auth'      =>  $user && [$user, $password]
        ]);
    }


    /**
     * Получаем отформатированные параметры для отправки
     *
     * @param $body
     * @return string
     */
    public function getBody($body)
    {
        // Удаляем из массива
        if(array_key_exists('http_provider', $body))
            unset($body['http_provider']);

        return json_encode($body);
    }

    /**
     * Отправляем запросы на сервер
     *
     * @param $url
     * @param array $payload
     * @param string $method
     * @return array
     */
    public function request($url, $payload = [], $method = 'get')
    {
        $method = strtoupper($method);

        $options = [
            'headers' => $this->headers,
            'body' => $this->getBody($payload)
        ];

        $request = new Request($method, $url, $options['headers'], $options['body']);
        $rawResponse = $this->httpClient->send($request, $options);

        try {
            $returnResponse = new TronResponse(
                $rawResponse->getBody(),
                $rawResponse->getStatusCode()
            );
        } catch (TronException $e) {
            die($e->getMessage());
        }

        return $returnResponse->getDecodedBody();
    }
}
