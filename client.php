<?php

set_time_limit(0);

$client = new SearsClient();

Helpers::printPre($client->putInventoryItem(735078018625));

// Helpers::printPre($client->getItemClasses());

exit();

class SearsClient
{
	protected $base_api_url = "https://seller.marketplace.sears.com/SellerPortal/api/";

	protected $seller_id;
	protected $email_address;
	protected $secret_key;

	protected static $valid_request_methods = [
		'put',
		'get',
		'post',
	];

	function __construct()
	{
		$config = self::getConfig('config.php');

		$this->seller_id = $config['seller_id'];
		$this->email_address = $config['email_address'];
		$this->secret_key = $config['secret'];
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

	protected function makeAPICall($uri, $method, $headers = [])
	{
		// TODO - Put this in its own class

		if(!in_array(strtolower($method), self::$valid_request_methods))
		{
			throw new Exception("Invalid request method called.");
		}

		$headers[] = 'authorization:' . $this->genAuthHeader();

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->base_api_url . $uri);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		// curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($filedata));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response  = curl_exec($ch);

		if(!empty(curl_error($ch)))
		{
			throw new Exception(curl_error($ch));
		}

		curl_close($ch);

		return $response;
	}

	public function getInventory()
	{
		$q = http_build_query([
			'sellerId' => $this->seller_id,

		]);

		$uri = 'inventory/fbm-lmp/v7?' . $q;

		return $this->makeAPICall($uri, 'PUT');
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

	public function getItemClasses()
	{
		$document = new DOMDocument();
		$document->preserveWhiteSpace = FALSE;
		$file_directory = __dir__ . '/xml';
		$file_location = $file_directory . '/item_class_library.xml';

		if(!file_exists($file_location))
		{
			if(!is_dir($file_directory))
			{
				mkdir($file_directory, 0777, true);
			}

			$document->loadXML( $this->getItemClassLibrary() );
			$document->save($save_location . '/item_class_library.xml');
		}
		else
		{
			$document->load($file_location);
		}

		$classes = Helpers::arrayFromXML( $document->saveXML() );

		$classes_list = [];

		foreach($classes['item-class'] as $class)
		{
			$id = $class['@attributes']['id'];
			$name = $class['name'];

			if( Helpers::stringContains(['do not use', 'retired'], $name) )
			{
				continue;
			}

			$classes_list[] = [
				'id' => $id,
				'name' => $name,
			];
		}

		return $classes_list;
	}

	protected function getItemClassLibrary()
	{
		$q = http_build_query([
			'sellerId' => $this->seller_id,
		]);

		$uri = 'itemclasses/v2?' . $q;

		return $this->makeAPICall($uri, 'GET');
	}

	public function getItemAttributes($class_id)
	{
		$q = http_build_query([
			'itemClassId' => $class_id,
			'sellerId' => $this->seller_id,
		]);

		$uri = 'attributes/v4/?' . $q;

		$attributes = Helpers::arrayFromXML($this->makeAPICall($uri, 'GET'))['attributes']['attribute'];

		$item_attributes = [];

		foreach($attributes as $attribute)
		{

		}



		return $attributes;
	}

	public function putInventoryItem($upc)
	{
		$xml = new SimpleXMLElement('<?xml version="1.0"?><catalog-feed/>');

		$xml->addChild('fbm-catalog')
				->addChild('items')
					->addChild('item')
						->addChild('key-identifier')
							->addChild('brand-mmn-upc')
								->addChild('upc', $upc);
					// ->addAttribute('item-id', $upc);




		// return $xml->asXML();

		$q = http_build_query([
			'sellerId' => $this->seller_id,
		]);

		$uri = 'catalog/skinny-fbm/v1?' . $q;

		return $this->makeAPICall($uri, 'PUT');
	}
}

class Helpers
{
	private function __construct() {}

	public static function jsonFromXML($xml)
	{
		$xml = simplexml_load_string($xml);
		$json = json_encode($xml);
		return json_decode($json, true);
	}

	public static function printPre($output)
	{
		echo '<pre>';
		print_r($output);
		echo '</pre>';
	}

	public static function stringContains($needle, $haystack)
	{
		if(is_array($needle))
		{
			foreach($needle as $n)
			{
				if(stripos($haystack, $n) !== false)
				{
					return true;
				}
			}

			return false;
		}
		else
		{
			return (stripos($haystack, $needle) !== false);
		}
	}

	public static function arrayFromXML($xmlstring)
	{
		$xml = simplexml_load_string( $xmlstring , null , LIBXML_NOCDATA );
		$json = json_encode($xml);
		return json_decode($json, true);
	}
}