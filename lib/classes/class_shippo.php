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
	private $api_key;
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