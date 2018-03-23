<?php

class SearsClient
{
	protected $seller_id;
	protected $email_address;
	protected $secret_key;
	protected $guzzle;

	function __construct()
	{
		$config = self::getConfig('config.php');

		$this->seller_id = $config['seller_id'];
		$this->email_address = $config['email_address'];
		$this->secret_key = $config['secret'];

		$this->guzzle = new Guzzle([
			'base_uri' => 'https://seller.marketplace.sears.com/SellerPortal/api/',
		]);
	}

	protected static function getConfig($filename)
	{
		$config = include $filename;
		return $config;
	}

	protected function getTimestamp()
	{
		return (new DateTime('now', new DateTimeZone('UTC')))
			->format('Y-m-d\TH:i:s\Z');
	}

	protected function genAuthHeader()
	{
		$seller_id = $this->seller_id;
		$email_address = $this->email_address;
		$timestamp = $this->getTimestamp();

		$string_to_sign = "$seller_id:$email_address:$timestamp";
		$signed_string = hash_hmac('sha256', $string_to_sign, $this->secret_key);

		return "HMAC-SHA256 emailaddress=$email_address,timestamp=$timestamp,signature=$signed_string";
	}

	protected function makeAPICall($uri, $method)
	{
		$method = strtoupper($method);
		$methods = [
			'GET',
			'PUT'
		];

		if(!in_array($method, $methods))
		{
			throw new Exception('Invalid request method.');
		}

		try {
			$response = $this->guzzle->request(
				$method,
				$uri,
				[
					'headers' => [
						'authorization' => $this->genAuthHeader()
					]
				]
			);
		} catch (Exception $e) {
			echo $e->getMessage();
			exit();
		}

		$xml = simplexml_load_string($response->getBody());
		return json_encode($xml);
	}

	public function getInventory()
	{
		$q = http_build_query([
			'sellerId' => $this->seller_id,

		]);

		$uri = 'inventory/v5?' . $q;

		return $this->makeAPICall($uri, 'GET');
	}

	public function getOrders()
	{
		$q = http_build_query([
			'sellerId' => $this->seller_id,
			'status' => 'New',
			'status' => 'Open',
			'status' => 'Closed',
		]);

		$uri = 'oms/purchaseorder/v13?' . $q;

		return $this->makeAPICall($uri, 'GET');
	}

	public function putInventoryItem()
	{

	}
}