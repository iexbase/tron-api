<?php
namespace IEXBase\TronAPI;

use IEXBase\TronAPI\Exception\TronException;

// Web3 plugin
use Web3\Contracts\Ethabi;
use Web3\Contracts\Types\{Address, Boolean, Bytes, DynamicBytes, Integer, Str, Uinteger};

class TransactionBuilder
{
    /**
     * Base Tron object
     *
     * @var Tron
    */
    protected $tron;

    /**
     * Create an TransactionBuilder object
     *
     * @param Tron $tron
     */
    public function __construct(Tron $tron)
    {
        $this->tron = $tron;
    }

    /**
     * Creates a transaction of transfer.
     * If the recipient address does not exist, a corresponding account will be created on the blockchain.
     *
     * @param string $to
     * @param float $amount
     * @param string $from
     * @return array
     * @throws TronException
     */
    public function sendTrx($to, $amount, $from)
    {
        if (!is_float($amount) || $amount < 0) {
            throw new TronException('Invalid amount provided');
        }

        $to = $this->tron->address2HexString($to);
        $from = $this->tron->address2HexString($from);

        if ($from === $to) {
            throw new TronException('Cannot transfer TRX to the same account');
        }

        $response = $this->tron->getManager()->request('wallet/createtransaction', [
            'to_address' => $to,
            'owner_address' => $from,
            'amount' => $this->tron->toTron($amount),
        ]);

        return $response;
    }

    /**
     * Transfer Token
     *
     * @param string $to
     * @param int $amount
     * @param string $tokenID
     * @param string|null $from
     * @return array
     * @throws TronException
     */
    public function sendToken(string $to, int $amount, string $tokenID, string $from)
    {
        if (!is_integer($amount) or $amount <= 0) {
            throw new TronException('Invalid amount provided');
        }

        if (!is_string($tokenID)) {
            throw new TronException('Invalid token ID provided');
        }

        if ($to === $from) {
            throw new TronException('Cannot transfer tokens to the same account');
        }

        $transfer = $this->tron->getManager()->request('wallet/transferasset', [
            'owner_address' => $this->tron->address2HexString($from),
            'to_address' => $this->tron->address2HexString($to),
            'asset_name' => $this->tron->stringUtf8toHex($tokenID),
            'amount' => intval($amount)
        ]);

        if (array_key_exists('Error', $transfer)) {
            throw new TronException($transfer['Error']);
        }
        return $transfer;
    }

    /**
     * Purchase a Token
     *
     * @param $issuerAddress
     * @param $tokenID
     * @param $amount
     * @param $buyer
     * @return array
     * @throws TronException
     */
    public function purchaseToken($issuerAddress, $tokenID, $amount, $buyer)
    {
        if (!is_string($tokenID)) {
            throw new TronException('Invalid token ID provided');
        }

        if (!is_integer($amount) and $amount <= 0) {
            throw new TronException('Invalid amount provided');
        }

        $purchase = $this->tron->getManager()->request('wallet/participateassetissue', [
            'to_address' => $this->tron->address2HexString($issuerAddress),
            'owner_address' => $this->tron->address2HexString($buyer),
            'asset_name' => $this->tron->stringUtf8toHex($tokenID),
            'amount' => $this->tron->toTron($amount)
        ]);

        if (array_key_exists('Error', $purchase)) {
            throw new TronException($purchase['Error']);
        }
        return $purchase;
    }

    /**
     * Freezes an amount of TRX.
     * Will give bandwidth OR Energy and TRON Power(voting rights) to the owner of the frozen tokens.
     *
     * @param float $amount
     * @param int $duration
     * @param string $resource
     * @param string|null $address
     * @return array
     * @throws TronException
     */
    public function freezeBalance(float $amount = 0, int $duration = 3, string $resource = 'BANDWIDTH', string $address)
    {
        if (!in_array($resource, ['BANDWIDTH', 'ENERGY'])) {
            throw new TronException('Invalid resource provided: Expected "BANDWIDTH" or "ENERGY"');
        }

        if (!is_float($amount)) {
            throw new TronException('Invalid amount provided');
        }

        if(!is_integer($duration) and $duration < 3) {
            throw new TronException('Invalid duration provided, minimum of 3 days');
        }

        return $this->tron->getManager()->request('wallet/freezebalance', [
            'owner_address' => $this->tron->address2HexString($address),
            'frozen_balance' => $this->tron->toTron($amount),
            'frozen_duration' => $duration,
            'resource' => $resource
        ]);
    }

    /**
     * Unfreeze TRX that has passed the minimum freeze duration.
     * Unfreezing will remove bandwidth and TRON Power.
     *
     * @param string $resource
     * @param string $owner_address
     * @return array
     * @throws TronException
     */
    public function unfreezeBalance(string $resource = 'BANDWIDTH', string $owner_address)
    {
        if (!in_array($resource, ['BANDWIDTH', 'ENERGY'])) {
            throw new TronException('Invalid resource provided: Expected "BANDWIDTH" or "ENERGY"');
        }

        return $this->tron->getManager()->request('wallet/unfreezebalance', [
            'owner_address' =>  $this->tron->address2HexString($owner_address),
            'resource' => $resource
        ]);
    }

    /**
     * Withdraw Super Representative rewards, useable every 24 hours.
     *
     * @param string $owner_address
     * @return array
     * @throws TronException
     */
    public function withdrawBlockRewards($owner_address = null)
    {
        $withdraw =  $this->tron->getManager()->request('wallet/withdrawbalance', [
            'owner_address' =>  $this->tron->address2HexString($owner_address)
        ]);

        if (array_key_exists('Error', $withdraw)) {
            throw new TronException($withdraw['Error']);
        }
        return $withdraw;
    }

    /**
     * Update a Token's information
     *
     * @param string $description
     * @param string $url
     * @param int $freeBandwidth
     * @param int $freeBandwidthLimit
     * @param $address
     * @return array
     * @throws TronException
     */
    public function updateToken(string $description, string $url, int $freeBandwidth = 0, int $freeBandwidthLimit = 0, $address)
    {
        if (!is_integer($freeBandwidth) || $freeBandwidth < 0) {
            throw new TronException('Invalid free bandwidth amount provided');
        }

        if (!is_integer($freeBandwidthLimit) || $freeBandwidthLimit < 0 && ($freeBandwidth && !$freeBandwidthLimit)) {
            throw new TronException('Invalid free bandwidth limit provided');
        }

        return $this->tron->getManager()->request('wallet/updateasset', [
            'owner_address'      =>  $this->tron->address2HexString($address),
            'description'        =>  $this->tron->stringUtf8toHex($description),
            'url'               =>  $this->tron->stringUtf8toHex($url),
            'new_limit'         =>  intval($freeBandwidth),
            'new_public_limit'  =>  intval($freeBandwidthLimit)
        ]);
    }

    /**
     * updateEnergyLimit
     *
     * @param string $contractAddress
     * @param int $originEnergyLimit
     * @param string $ownerAddress
     * @return array
     * @throws TronException
     */
    public function updateEnergyLimit(string $contractAddress, int $originEnergyLimit, string $ownerAddress)
    {
        $contractAddress = $this->tron->address2HexString($contractAddress);
        $ownerAddress = $this->tron->address2HexString($ownerAddress);

        if($originEnergyLimit < 0 || $originEnergyLimit > 10000000) {
            throw new TronException('Invalid originEnergyLimit provided');
        }

        return $this->tron->getManager()->request('wallet/updateenergylimit', [
            'owner_address' =>  $this->tron->address2HexString($ownerAddress),
            'contract_address' => $this->tron->address2HexString($contractAddress),
            'origin_energy_limit' => $originEnergyLimit
        ]);
    }

    /**
     * updateSetting
     *
     * @param string $contractAddress
     * @param int $userFeePercentage
     * @param string $ownerAddress
     * @return array
     * @throws TronException
     */
    public function updateSetting(string $contractAddress, int $userFeePercentage, string $ownerAddress)
    {
        $contractAddress = $this->tron->address2HexString($contractAddress);
        $ownerAddress = $this->tron->address2HexString($ownerAddress);

        if($userFeePercentage < 0 || $userFeePercentage > 1000) {
            throw new TronException('Invalid userFeePercentage provided');
        }

        return $this->tron->getManager()->request('wallet/updatesetting', [
            'owner_address' =>  $this->tron->address2HexString($ownerAddress),
            'contract_address' => $this->tron->address2HexString($contractAddress),
            'consume_user_resource_percent' => $userFeePercentage
        ]);
    }

    /**
     * Triggers a contract
     *
     * @param mixed $abi
     * @param string $contract
     * @param string $function
     * @param array $params
     * @param integer $feeLimit
     * @param string $address
     * @param int $callValue
     * @param int $bandwidthLimit
     *
     * @return mixed
     * @throws TronException
     */
    public function triggerSmartContract($abi,
                                         $contract,
                                         $function,
                                         $params,
                                         $feeLimit,
                                         $address,
                                         $callValue = 0,
                                         $bandwidthLimit = 0)
    {
        $func_abi = [];
        foreach($abi as $key =>$item) {
            if($item['name'] === $function) {
                $func_abi = $item;
                break;
            }
        }

        if(count($func_abi) === 0)
            throw new TronException("Function $function not defined in ABI");

        if(!is_array($params))
            throw new TronException("Function params must be an array");

        if(count($func_abi['inputs']) !== count($params))
            throw new TronException("Count of params and abi inputs must be identical");

        if($feeLimit > 1000000000)
            throw new TronException('fee_limit must not be greater than 1000000000');


        $inputs = array_map(function($item){ return $item['type']; },$func_abi['inputs']);
        $signature = $func_abi['name'].'{';
        if(count($inputs) > 0)
            $signature .= implode(',',$inputs);
        $signature .= '}';

        $eth_abi = new Ethabi([
            'address' => new Address,
            'bool' => new Boolean,
            'bytes' => new Bytes,
            'dynamicBytes' => new DynamicBytes,
            'int' => new Integer,
            'string' => new Str,
            'uint' => new Uinteger,
        ]);
        $parameters = substr($eth_abi->encodeParameters($func_abi, $params),2);

        $result = $this->tron->getManager()->request('wallet/triggersmartcontract', [
            'contract_address' => $contract,
            'function_selector' => $signature,
            'parameter' => $parameters,
            'owner_address' =>  $address,
            'fee_limit'     =>  $feeLimit,
            'call_value'    =>  $callValue,
            'consume_user_resource_percent' =>  $bandwidthLimit,
        ]);

        if(count($func_abi['outputs']) === 0) {
            if($result['result']['result'])
                return $result['transaction'];
        }

        if(!isset($result['constant_result']))
        {
            $message = isset($result['result']['message']) ?
                $this->tron->hexString2Utf8($result['result']['message']) : '';
            throw new TronException('Failed to execute. Error:'.$message);
        }
        return $eth_abi->decodeParameters($func_abi, $result['constant_result'][0]);
    }
}
