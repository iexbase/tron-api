<?php
namespace IEXBase\TronAPI;

use GuzzleHttp\Client;
use IEXBase\TronAPI\Exceptions\TronException;

class TronResponse
{
    /**
     * Ответ кода состояния HTTP
     *
     * @var integer
    */
    protected $httpStatusCode;

    /**
     * Результат запроса
     *
     * @var string
    */
    protected $body;

    /**
     * Помещаем ответ в массив
     *
     * @var array
    */
    protected $decodedBody = [];

    /**
     * Исключение, вызванное этим запросом
     *
     * @var TronException.
     */
    protected $thrownException;

    /**
     * Создаем объект TronResponse
     *
     * @param null $body
     * @param $httpStatusCode
     * @throws TronException
     */
    public function __construct($body = null, $httpStatusCode)
    {
        $this->body = $body;
        $this->httpStatusCode = $httpStatusCode;

        $this->decodeBody();
    }

    public function getBody()
    {
        return $this->body;
    }

    public function getHttpStatusCode()
    {
        return $this->httpStatusCode;
    }

    public function getDecodedBody()
    {
        return $this->decodedBody;
    }

    public function isError()
    {
        return isset($this->decodedBody['Error']);
    }

    /**
     * Преобразуем исходный ответ в массив
     *
     * @return void
     * @throws TronException
     */
    public function decodeBody()
    {
        $this->decodedBody = json_decode($this->body, true);

        if ($this->decodedBody === null) {
            $this->decodedBody = [];
            parse_str($this->body, $this->decodedBody);
        } elseif (is_bool($this->decodedBody)) {
            $this->decodedBody = ['success' => $this->decodedBody];
        }

        if (!is_array($this->decodedBody)) {
            $this->decodedBody = [];
        }
        if ($this->isError()) {
            throw new TronException($this->getDecodedBody()['Error']);
        }
    }
}