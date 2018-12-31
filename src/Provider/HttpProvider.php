<?php declare(strict_types=1);

namespace IEXBase\TronAPI\Provider;

use GuzzleHttp\{Psr7\Request, Client, ClientInterface};
use Psr\Http\Message\StreamInterface;
use IEXBase\TronAPI\Exception\{NotFoundException, TronException};
use IEXBase\TronAPI\Support\Utils;

class HttpProvider implements HttpProviderInterface
{
    /**
     * HTTP Client Handler
     *
     * @var ClientInterface.
     */
    protected $httpClient;

    /**
     * Server or RPC URL
     *
     * @var string
    */
    protected $host;

    /**
     * Waiting time
     *
     * @var int
     */
    protected $timeout = 30000;

    /**
     * Get custom headers
     *
     * @var array
    */
    protected $headers = [];

    /**
     * Get the pages
     *
     * @var string
    */
    protected $statusPage = '/';

    /**
     * Create an HttpProvider object
     *
     * @param string $host
     * @param int $timeout
     * @param $user
     * @param $password
     * @param array $headers
     * @param string $statusPage
     * @throws TronException
     */
    public function __construct(string $host, int $timeout = 30000,
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

        $this->host = $host;
        $this->timeout = $timeout;
        $this->statusPage = $statusPage;
        $this->headers = $headers;

        $this->httpClient = new Client([
            'base_uri'  =>  $host,
            'timeout'   =>  $timeout,
            'auth'      =>  $user && [$user, $password]
        ]);
    }

    /**
     * Enter a new page
     *
     * @param string $page
     */
    public function setStatusPage(string $page = '/'): void
    {
        $this->statusPage = $page;
    }

    /**
     * Check connection
     *
     * @return bool
     * @throws TronException
     */
    public function isConnected() : bool
    {
        $response = $this->request($this->statusPage);

        if(array_key_exists('blockID', $response)) {
            return true;
        } elseif(array_key_exists('status', $response)) {
            return true;
        }
        return false;
    }

    /**
     * Getting a host
     *
     * @return string
    */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * Getting timeout
     *
     * @return int
    */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * We send requests to the server
     *
     * @param $url
     * @param array $payload
     * @param string $method
     * @return array|mixed
     * @throws TronException
     */
    public function request($url, array $payload = [], string $method = 'get'): array
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
     * Convert the original answer to an array
     *
     * @param StreamInterface $stream
     * @param int $status
     * @return array|mixed
     */
    protected function decodeBody(StreamInterface $stream, int $status): array
    {
        $decodedBody = json_decode($stream->getContents(),true);

        if((string)$stream == 'OK') {
            $decodedBody = [
                'status'    =>  1
            ];
        }elseif ($decodedBody == null or !is_array($decodedBody)) {
            $decodedBody = [];
        }

        if($status == 404) {
            throw new NotFoundException('Page not found');
        }

        return $decodedBody;
    }
}
