<?php

require_once(realpath(__DIR__ . '/../vendor/autoload.php'));
require_once(realpath(__DIR__ . '/class_main.php'));

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\MultipartStream;

/**
 * Class for interacting with the Shippo API
 */
class Shippo extends class_main {

	// In production, this will be replaced with rotating set of API keys in db
	private $api_key = 'shippo_test_9ac4116db71f8b70508b76713abe5d5e88351ae7'; 
	private $api_base_url = 'https://api.goshippo.com/';

	public function __construct($api_key) {
		parent::__construct();
		$this->api_key = $api_key;
	}
	public function __destruct() {
		parent::__destruct();
	}

	// Additional methods for interacting with the Shippo API would go here
	
}