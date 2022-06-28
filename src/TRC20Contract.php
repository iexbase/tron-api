<?php

/**
 * TronAPI
 *
 * @author  Shamsudin Serderov <steein.shamsudin@gmail.com>
 * @license https://github.com/iexbase/tron-api/blob/master/LICENSE (MIT License)
 * @version 1.3.4
 * @link    https://github.com/iexbase/tron-api
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace IEXBase\TronAPI;

use Comely\DataTypes\BcNumber;
use IEXBase\TronAPI\Exception\TRC20Exception;
use IEXBase\TronAPI\Exception\TronException;

/**
 * Class TRC20Contract
 * @package TronAPI
 */
class TRC20Contract
{
    const TRX_TO_SUN = 1000000;

    /***
     * Maximum decimal supported by the Token
     *
     * @var integer|null
     */
    private ?int $_decimals = null;

    /***
     * Token Name
     *
     * @var string|null
     */
    private ?string $_name = null;

    /***
     * Token Symbol
     *
     * @var string|null
     */
    private ?string $_symbol = null;

    /**
     * The smart contract which issued TRC20 Token
     *
     * @var string
    */
    private string $contractAddress;

    /**
     * ABI Data
     *
     * @var string|null
    */
    private $abiData;

    /**
     * Fee Limit
     *
     * @var integer
     */
    private int $feeLimit = 10;

    /**
     * Base Tron object
     *
     * @var Tron
     */
    protected Tron $_tron;

    /**
     * Total Supply
     *
     * @var string|null
    */
    private ?string $_totalSupply = null;

    /**
     * Create Trc20 Contract
     *
     * @param Tron $tron
     * @param string $contractAddress
     * @param string|null $abi
     */
    public function __construct(Tron $tron, string $contractAddress, string $abi = null)
    {
        $this->_tron = $tron;

        // If abi is absent, then it takes by default
        if(is_null($abi)) {
            $abi = file_get_contents(__DIR__.'/trc20.json');
        }

        $this->abiData = json_decode($abi, true);
        $this->contractAddress = $contractAddress;
    }

    /**
     * Debug Info
     *
     * @return array
     * @throws TronException
     */
    public function __debugInfo(): array
    {
        return $this->array();
    }

    /**
     * Clears cached values
     *
     * @return void
     */
    public function clearCached(): void
    {
        $this->_name = null;
        $this->_symbol = null;
        $this->_decimals = null;
        $this->_totalSupply = null;
    }

    /**
     *  All data
     *
     * @throws TronException
     */
    public function array(): array
    {
        return [
            'name' => $this->name(),
            'symbol' => $this->symbol(),
            'decimals' => $this->decimals(),
            'totalSupply' => $this->totalSupply(true)
        ];
    }

    /**
     * Get token name
     *
     * @return string
     * @throws TronException
     */
    public function name(): string
    {
        if ($this->_name) {
            return $this->_name;
        }

        $result = $this->trigger('name', null, []);
        $name = $result[0] ?? null;

        if (!is_string($name)) {
            throw new TRC20Exception('Failed to retrieve TRC20 token name');
        }

        $this->_name = $this->cleanStr($name);
        return $this->_name;
    }

    /**
     * Get symbol name
     *
     * @return string
     * @throws TronException
     */
    public function symbol(): string
    {
        if ($this->_symbol) {
            return $this->_symbol;
        }
        $result = $this->trigger('symbol', null, []);
        $code = $result[0] ?? null;

        if (!is_string($code)) {
            throw new TRC20Exception('Failed to retrieve TRRC20 token symbol');
        }

        $this->_symbol = $this->cleanStr($code);
        return $this->_symbol;
    }

    /**
     * The total number of tokens issued on the main network
     *
     * @param bool $scaled
     * @return string
     * @throws Exception\TronException
     * @throws TRC20Exception
     */
    public function totalSupply(bool $scaled = true): string
    {
        if (!$this->_totalSupply) {

            $result = $this->trigger('totalSupply', null, []);
            $totalSupply = $result[0]->toString() ?? null;

            if (!is_string($totalSupply) || !preg_match('/^[0-9]+$/', $totalSupply)) {
                throw new TRC20Exception('Failed to retrieve TRC20 token totalSupply');
            }

            $this->_totalSupply = $totalSupply;
        }

        return $scaled ? $this->decimalValue($this->_totalSupply, $this->decimals()) : $this->_totalSupply;
    }

    /**
     * Maximum decimal supported by the Token
     *
     * @throws TRC20Exception
     * @throws TronException
     */
    public function decimals(): int
    {
        if ($this->_decimals) {
            return $this->_decimals;
        }

        $result = $this->trigger('decimals', null, []);
        $scale = intval($result[0]->toString() ?? null);

        if (is_null($scale)) {
            throw new TRC20Exception('Failed to retrieve TRC20 token decimals/scale value');
        }

        $this->_decimals = $scale;
        return $this->_decimals;
    }

    /**
     * Balance TRC20 contract
     *
     * @param string|null $address
     * @param bool $scaled
     * @return string
     * @throws TRC20Exception
     * @throws TronException
     */
    public function balanceOf(string $address = null, bool $scaled = true): string
    {
        if(is_null($address))
            $address = $this->_tron->address['base58'];

        $addr = str_pad($this->_tron->address2HexString($address), 64, "0", STR_PAD_LEFT);
        $result = $this->trigger('balanceOf', $address, [$addr]);
        $balance = $result[0]->toString();

        if (!is_string($balance) || !preg_match('/^[0-9]+$/', $balance)) {
            throw new TRC20Exception(
                sprintf('Failed to retrieve TRC20 token balance of address "%s"', $addr)
            );
        }

        return $scaled ? $this->decimalValue($balance, $this->decimals()) : $balance;
    }

    /**
     * Send TRC20 contract
     *
     * @param string $to
     * @param string $amount
     * @param string|null $from
     * @return array
     * @throws TRC20Exception
     * @throws TronException
     */
    public function transfer(string $to, string $amount, string $from = null): array
    {
        if($from == null) {
            $from = $this->_tron->address['base58'];
        }

        $feeLimitInSun = bcmul((string)$this->feeLimit, (string)self::TRX_TO_SUN);

        if (!is_numeric($this->feeLimit) OR $this->feeLimit <= 0) {
            throw new TRC20Exception('fee_limit is required.');
        } else if($this->feeLimit > 1000) {
            throw new TRC20Exception('fee_limit must not be greater than 1000 TRX.');
        }

        $tokenAmount = bcmul($amount, bcpow("10", (string)$this->decimals(), 0), 0);

        $transfer = $this->_tron->getTransactionBuilder()
            ->triggerSmartContract(
                $this->abiData,
                $this->_tron->address2HexString($this->contractAddress),
                'transfer',
                [$this->_tron->address2HexString($to), $tokenAmount],
                $feeLimitInSun,
                $this->_tron->address2HexString($from)
            );

        $signedTransaction = $this->_tron->signTransaction($transfer);
        $response = $this->_tron->sendRawTransaction($signedTransaction);

        return array_merge($response, $signedTransaction);
    }

    /**
     *  TRC20 All transactions
     *
     * @param string $address
     * @param int $limit
     * @return array
     *
     * @throws TronException
     */
    public function getTransactions(string $address, int $limit = 100): array
    {
        return $this->_tron->getManager()
            ->request("v1/accounts/{$address}/transactions/trc20?limit={$limit}&contract_address={$this->contractAddress}", [], 'get');
    }

    /**
     * Get transaction info by contract address
     *
     * @throws TronException
     */
    public function getTransactionInfoByContract(array $options = []): array
    {
        return $this->_tron->getManager()
            ->request("v1/contracts/{$this->contractAddress}/transactions?".http_build_query($options), [],'get');
    }

    /**
     * Get TRC20 token holder balances
     *
     * @throws TronException
     */
    public function getTRC20TokenHolderBalance(array $options = []): array
    {
        return $this->_tron->getManager()
            ->request("v1/contracts/{$this->contractAddress}/tokens?".http_build_query($options), [],'get');
    }

    /**
     *  Find transaction
     *
     * @param string $transaction_id
     * @return array
     * @throws TronException
     */
    public function getTransaction(string $transaction_id): array
    {
        return $this->_tron->getManager()
            ->request('/wallet/gettransactioninfobyid', ['value' => $transaction_id], 'post');
    }

    /**
     * Config trigger
     *
     * @param $function
     * @param null $address
     * @param array $params
     * @return mixed
     * @throws TronException
     */
    private function trigger($function, $address = null, array $params = [])
    {
        $owner_address = is_null($address) ? '410000000000000000000000000000000000000000' : $this->_tron->address2HexString($address);

        return $this->_tron->getTransactionBuilder()
            ->triggerConstantContract($this->abiData, $this->_tron->address2HexString($this->contractAddress), $function, $params, $owner_address);
    }

    /**
     * @param string $int
     * @param int $scale
     * @return string
     */
    protected function decimalValue(string $int, int $scale = 18): string
    {
        return (new BcNumber($int))->divide(pow(10, $scale), $scale)->value();
    }

    /**
     * @param string $str
     * @return string
     */
    public function cleanStr(string $str): string
    {
        return preg_replace('/[^\w.-]/', '', trim($str));
    }

    /**
     * Set fee limit
     *
     * @param int $fee_limit
     * @return TRC20Contract
     */
    public function setFeeLimit(int $fee_limit) : TRC20Contract
    {
        $this->feeLimit = $fee_limit;
        return $this;
    }
}
