<?php

class Model
{

	protected $config = null;

	protected $conn = null;


	public function __construct()
	{
		$config = $GLOBALS['config'];
		$this->config = $config;
	}


	public function fromBlockchain($method,$params=array())
	{
		$data['id']      = rand(0,42);
		$data['jsonrpc'] = '2.0';
		$data['method']  = $method;
		if(!empty($params))
		{
			$data['params']  = $params;
		}

		$data_string = json_encode($data);
		$ch = curl_init($this->config['server']);
		curl_setopt($ch, CURLOPT_USERAGENT, 'SHIFTexplorer');
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'Content-Length: ' . strlen($data_string))
		);
		$result = curl_exec($ch);
		$error = curl_error($ch);
		curl_close($ch);

		if(!empty($error))
		{
			//return $error; //turned off untill better curl search filter
		}

		$decodedResult = json_decode($result,true);
		if (array_key_exists('result', $decodedResult)) {
			$finalResult = $decodedResult['result'];
			return $finalResult;
		}
	}


/**
 * calculate supply
 *
 * @todo  this is wrong, it doesn't count uncle rewards. planned solution is a richlist api call in shift.
 * 
 * @return int
 */
	public function getSupply()
	{
		$blockNumber = $this->fromBlockchain($this->config['prefix'].'_blockNumber');
		$supply = 7837571; // 6651571+(593000*2)
		$supply += (hexdec($blockNumber) - 593000);
		
		return $supply;
	}

	public function getHashrate($blocks = 25)
	{
		$latestBlock = $this->fromBlockchain($this->config['prefix'].'_getBlockByNumber',array('latest',true));
		$totalDiff = hexdec($latestBlock['difficulty']);
		$currentTime = hexdec($latestBlock['timestamp']);
		$totalTime = 0;
		for($i = 1; $i < $blocks; $i++)
		{
			$hexBlock = hexdec($latestBlock['number']) - $i;
			$checkBlock = $this->fromBlockchain($this->config['prefix'].'_getBlockByNumber',array("0x".dechex($hexBlock),false));
			$totalDiff += hexdec($checkBlock['difficulty']);
			$totalTime += ($currentTime - hexdec($checkBlock['timestamp']));
			$currentTime = hexdec($checkBlock['timestamp']);
		}
		
		$blockTime = $totalTime / $blocks;
		$avgDiff = $totalDiff / $blocks;
		$hashrate = $avgDiff / $blockTime;
		return $hashrate;
	}

	public function hex2str($hex)
	{
		$str = '';
    	for($i=0;$i<strlen($hex);$i+=2)
    	{
    		$str .= chr(hexdec(substr($hex,$i,2)));
    	}
    	
    	return $str;
	}


}