<?php declare(strict_types=1);

namespace IEXBase\TronAPI\Provider;

use GuzzleHttp\{Client, ClientInterface};
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\StreamInterface;
use IEXBase\TronAPI\Exception\{NotFoundException, TronException};
use IEXBase\TronAPI\Support\Utils;

class HttpProvider implements HttpProviderInterface
{
    /**
     * Обработчик HTTP-клиента
     *
     * @var ClientInterface.
     */
    protected $httpClient;

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
     * @param string $host
     * @param int $timeout
     * @param $user
     * @param $password
     * @param array $headers
     * @param string $statusPage
     * @throws TronException
     */
    public function __construct($host, int $timeout = 30000,
                                $user = false, $password = false,
                                array $headers = [], string $statusPage = '/')
    {
        if(!Utils::isValidUrl($host)) {
            throw new TronException('Invalid URL provided to HttpProvider');
        }

        if(is_nan($timeout) || $timeout < 0) {
            throw new TronException('Invalid timeout duration provided');
        }

        if(!Utils::isArray($headers)) {
            throw new TronException('Invalid headers array provided');
        }

        $this->statusPage = $statusPage;

        $this->httpClient = new Client([
            'base_uri'  =>  $host,
            'timeout'   =>  $timeout,
            'auth'      =>  $user && [$user, $password]
        ]);
    }

    /**
     * Указываем новую страницу
     *
     * @param string $page
     */
    public function setStatusPage(string $page = '/') {
        $this->statusPage = $page;
    }

    /**
     * Проверить соединение
     *
     * @return bool
     * @throws TronException
     */
    public function isConnected() : bool
    {
        $response = $this->request($this->statusPage);
        return (array_key_exists('blockID', $response) ? true : false);
    }

    /**
     * Отправляем запросы на сервер
     *
     * @param $url
     * @param array $payload
     * @param string $method
     * @return array|mixed
     * @throws TronException
     */
    public function request($url, array $payload = [], string $method = 'get')
    {
        $method = strtoupper($method);

        if(!in_array($method, ['GET', 'POST'])) {
            throw new TronException('The method is not defined');
        }

        $options = [
            'headers'   => $this->headers,
            'body'      => json_encode($payload)
        ];

        $request = new Request($method, $url, $options['headers'], $options['body']);
        $rawResponse = $this->httpClient->send($request, $options);

        return $this->decodeBody(
            $rawResponse->getBody(),
            $rawResponse->getStatusCode()
        );
    }

    /**
     * Преобразуем исходный ответ в массив
     *
     * @param StreamInterface $stream
     * @param int $status
     * @return array|mixed
     */
    protected function decodeBody(StreamInterface $stream, int $status): array
    {
        $decodedBody = json_decode($stream->getContents(),true);

        if ($decodedBody == null or !is_array($decodedBody)) {
            $decodedBody = [];
        }

        if($status == 404) {
            throw new NotFoundException('Page not found');
        }

        return $decodedBody;
    }
}
