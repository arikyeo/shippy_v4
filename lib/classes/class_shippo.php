<?php

require_once(realpath(__DIR__ . '/../vendor/autoload.php'));
require_once(realpath(__DIR__ . '/class_main.php'));

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\MultipartStream;

/**
 * Class for interacting with the Shippo API
 */


/* CREATE TABLE `address_lookup` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `address_hash` varchar(64) NOT NULL,
  `record_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `record_updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `lookup_provider` varchar(50) NOT NULL,
  `address_line_1` varchar(255) NOT NULL,
  `address_line_2` varchar(255) DEFAULT NULL,
  `address_line_3` varchar(255) DEFAULT NULL,
  `city_locality` varchar(100) NOT NULL,
  `state_province` varchar(100) NOT NULL,
  `postal_code` varchar(20) NOT NULL,
  `country_code` varchar(2) NOT NULL,
  `address_type` varchar(20) DEFAULT NULL,
  `lookup_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`lookup_data`)),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

*/

// Require main class
require_once(realpath(__DIR__ . '/class_main.php'));

// Require user class
require_once(realpath(__DIR__ . '/class_user.php'));


class Shippo extends class_main {

	// In production, this will be replaced with rotating set of API keys in db
	private $api_key = 'shippo_test_9ac4116db71f8b70508b76713abe5d5e88351ae7';
	private $api_base_url = 'https://api.goshippo.com/';

	private $class_user;

	public function __construct($api_key) {
		parent::__construct();
		$this->api_key = $api_key;
		$this->class_user = new class_user();
	}
	public function __destruct() {
		parent::__destruct();
	}

	// Additional methods for interacting with the Shippo API would go here

	/* Address data should be an associative array with keys eg:
		{
			"name": "Wilson",
			"organization": "Shippo",
			"email": "user@shippo.com",
			"phone": "+1-4155550132",
			"address_line_1": "731 Market Street",
			"address_line_2": "#200",
			"city_locality": "San Francisco",
			"state_province": "CA",
			"postal_code": "94103",
			"country_code": "US",
			"address_type": "residential"
		}
	*/

	/*  Validate an address using Shippo's address validation endpoint
		https://docs.goshippo.com/docs/addressapi/address_validate/

		On success, returns JSON similar to:
		
		{
			"original_address": {
				... (original address fields) ...
			},
			"analysis": {
				"validation_result": {
					"value": "valid",
					"reasons": [
						{
							"code": "address_found",
							"type": "info",
							"description": "The entire address is present in the database."
						}
					]
				},
				"address_type": "commercial"
			},
			"geo": {
				"latitude": 37.76486,
				"longitude": -122.43199
			}
		}


		Else, returns similar to:
		{
			"original_address": {
				... (original address fields) ...
			},
			"analysis": {
				"validation_result": {
					"value": "invalid",
					"reasons": [
						{
							"code": "address_not_found",
							"type": "error",
							"description": "Address is not present in the database."
						}
					]
				},
				"address_type": "unknown"
			}
		}

		If any corrections were made,

		{
			"original_address": {
				"address_line_1": "Privada Unión 10",
				"address_line_2": "",
				"city_locality": "CIUDAD DE MÉXICO",
				"state_province": "CDMEX",
				"postal_code": "",
				"country_code": "MX",
				"name": "Test",
				"organization": "Test Company"
			},
			"recommended_address": {
				"address_line_1": "Privada Unión 10, Agricola Pantitlan",
				"city_locality": "Ciudad de México",
				"state_province": "CDMX",
				"postal_code": "08100",
				"country_code": "MX",
				"name": "Test",
				"organization": "Test Company",
				"complete_address": "Test, Test Company;Privada Unión 10;Agricola Pantitlan;08100 Iztacalco, CDMX;MEXICO",
				"confidence_result": {
					"score": "high",
					"code": "postal_data_match",
					"description": "The address has been completely verified to the most granular level possible."
				}
			},
			"analysis": {
				"validation_result": {
					"value": "partially_valid",
					"reasons": [
						{
							"code": "state_province_corrected",
							"type": "correction",
							"description": "The state/province has been corrected."
						},
						{
							"code": "zip_post_code_corrected",
							"type": "correction",
							"description": "The zip code has been corrected to a different ZIP Code."
						}
					]
				},
				"address_type": "unknown",
				"changed_attributes": [
					"address_line_2",
					"address_line_1",
					"state_province",
					"postal_code",
					"city_locality"
				]
			},
			"geo": {
				"latitude": 19.417,
				"longitude": -99.0578
			}
		}

	*/

	// Store address validation result in the address_lookup table
	private function store_address_lookup($address_data, $lookup_provider, $lookup_data) {
		$address_hash = hash(
			'xxh128',
			trim(strtoupper($address_data['address_line_1'] . '|' .
				(isset($address_data['address_line_2']) ? $address_data['address_line_2'] : '') . '|' .
				(isset($address_data['address_line_3']) ? $address_data['address_line_3'] : '') . '|' .
				$address_data['city_locality'] . '|' .
				$address_data['state_province'] . '|' .
				$address_data['postal_code'] . '|' .
				$address_data['country_code']))
		);

		$query_insert_lookup = "INSERT INTO `address_lookup` (
			`address_hash`,
			`lookup_provider`,
			`address_line_1`,
			`address_line_2`,
			`address_line_3`,
			`city_locality`,
			`state_province`,
			`postal_code`,
			`country_code`,
			`lookup_data`
		) VALUES (
			:address_hash,
			:lookup_provider,
			:address_line_1,
			:address_line_2,
			:address_line_3,
			:city_locality,
			:state_province,
			:postal_code,
			:country_code,
			:lookup_data
		)";

		// Prepare the query_params (replace with blank strings if not set)
		$query_params = [
			':address_hash' => $address_hash,
			':lookup_provider' => $lookup_provider,
			':address_line_1' => $address_data['address_line_1'],
			':address_line_2' => isset($address_data['address_line_2']) ? $address_data['address_line_2'] : '',
			':address_line_3' => isset($address_data['address_line_3']) ? $address_data['address_line_3'] : '',
			':city_locality' => $address_data['city_locality'],
			':state_province' => $address_data['state_province'],
			':postal_code' => $address_data['postal_code'],
			':country_code' => $address_data['country_code'],
			':lookup_data' => json_encode($lookup_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
		];

		// Execute the query
		try {
			$stmt = $this->db->prepare($query_insert_lookup);
			$stmt->execute($query_params);
		} catch (PDOException $e) {
			// Handle exception (log it, rethrow it, etc.)
			error_log('Database error in store_address_lookup: ' . $e->getMessage());
		}

		// verify no errors and return true or false
		if ($stmt->errorCode() === '00000') {
			return true;
		} else {
			return false;
		}
	}



	// SHIPPO API KEYS
	/*  CREATE TABLE `api_keys_shippo` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`created_timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
			`api_key` varchar(512) NOT NULL,
			`status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1 - active, 0 - inactive',
			`usage_count` int(11) NOT NULL DEFAULT '0' COMMENT 'Number of times this API key has been used',
			`last_used` timestamp NULL DEFAULT NULL COMMENT 'Last time this API key was used',
			PRIMARY KEY (`id`)
		) ENGINE = InnoDB AUTO_INCREMENT = 1 DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
	*/

	// Checks if the shippo API key appears valid
	private function is_api_key_valid($api_key) {
		$client = new Client([
			'base_uri' => $this->api_base_url,
			'headers' => [
				'Authorization' => 'ShippoToken ' . $api_key,
				'Content-Type' => 'application/json',
			]
		]);

		// Make a request to fetch user info (shippo-accounts endpoint)
		try {
			$response = $client->get('/shippo-accounts/');
			$body = $response->getBody();
			$data = json_decode($body, true);

			// Check if the API key is valid
			if (isset($data['results'])) {
				return true;
			}
		} catch (RequestException $e) {
			// Handle request exception
			error_log('Shippo API request error: ' . $e->getMessage());
		}

		return false;
	}

	// Get transaction counnt for the given Shippo API key
	private function get_api_key_transaction_count($api_key) {
		$client = new Client([
			'base_uri' => $this->api_base_url,
			'headers' => [
				'Authorization' => 'ShippoToken ' . $api_key,
				'Content-Type' => 'application/json',
			]
		]);

		// Make a request to fetch transactions
		try {
			$response = $client->get('/transactions/');
			$body = $response->getBody();
			$data = json_decode($body, true);

			// Check if the response contains transaction data
			if (isset($data['results'])) {
				return (int)$data['results'];
			}
		} catch (RequestException $e) {
			// Handle request exception
			error_log('Shippo API request error: ' . $e->getMessage());
		}
		return false;
	}

	// Fetch best available Shippo API key
	// Try to use API keys with lowest usage count first and then rank by last used timestamp (grouped within a range of 5 days), then by created timestamp (oldest first) (also grouped within a range of 5 days)

	public function get_best_api_key() {
		// Fetch all API keys from the database
		$query_fetch_keys = "SELECT * FROM `api_keys_shippo` WHERE `status` = 1";
		$stmt = $this->db->prepare($query_fetch_keys);
		$stmt->execute();
		$api_keys = $stmt->fetchAll(PDO::FETCH_ASSOC);

		// Verify each API key's validity
		foreach ($api_keys as $index => $api_key_record) {
			$is_valid = $this->is_api_key_valid($api_key_record['api_key']);
			if (!$is_valid) {
				// Invalidate the API key in the database
				$update_query = "UPDATE `api_keys_shippo` SET `status` = 0 WHERE `id` = :id";
				$update_stmt = $this->db->prepare($update_query);
				$update_stmt->execute([':id' => $api_key_record['id']]);
				// Remove from the local array
				unset($api_keys[$index]);
			}
		}

		// Update usage counts for remaining valid API keys
		foreach ($api_keys as $index => $api_key_record) {
			$transaction_count = $this->get_api_key_transaction_count($api_key_record['api_key']);
			if ($transaction_count !== false) {
				// Update the usage count in the database
				$update_query = "UPDATE `api_keys_shippo` SET `usage_count` = :usage_count, `last_used` = NOW() WHERE `id` = :id";
				$update_stmt = $this->db->prepare($update_query);
				$update_stmt->execute([
					':usage_count' => $transaction_count,
					':id' => $api_key_record['id']
				]);
				// Update the local array
				$api_keys[$index]['usage_count'] = $transaction_count;
				$api_keys[$index]['last_used'] = date('Y-m-d H:i:s'); // Set to now
			} else {
				// If unable to fetch transaction count, set usage count to a high value to deprioritize
				$api_keys[$index]['usage_count'] = PHP_INT_MAX;
			}
		}

		// Filter and sort API keys based on usage count, last used, and created timestamp
		usort($api_keys, function($a, $b) {
			// Compare by usage count (ascending)
			if ($a['usage_count'] !== $b['usage_count']) {
				return $a['usage_count'] - $b['usage_count'];
			}
			
			// If usage count is the same, compare by last used (descending) within 5-day ranges
			$last_used_a = $a['last_used'] ? strtotime($a['last_used']) : 0;
			$last_used_b = $b['last_used'] ? strtotime($b['last_used']) : 0;
			$five_days_seconds = 5 * 24 * 60 * 60; // 5 days in seconds
			
			// Group timestamps into 5-day buckets (0 means never used)
			$last_used_bucket_a = $last_used_a > 0 ? floor($last_used_a / $five_days_seconds) : -1;
			$last_used_bucket_b = $last_used_b > 0 ? floor($last_used_b / $five_days_seconds) : -1;
			
			if ($last_used_bucket_a !== $last_used_bucket_b) {
				return $last_used_bucket_b - $last_used_bucket_a; // More recent buckets first (descending)
			}
			
			// If last used is in the same 5-day range, compare by created timestamp (ascending) within 5-day ranges
			$created_a = strtotime($a['created_timestamp']);
			$created_b = strtotime($b['created_timestamp']);
			
			// Group created timestamps into 5-day buckets
			$created_bucket_a = floor($created_a / $five_days_seconds);
			$created_bucket_b = floor($created_b / $five_days_seconds);
			
			if ($created_bucket_a !== $created_bucket_b) {
				return $created_bucket_a - $created_bucket_b; // Older buckets first (ascending)
			}
			
			// If both timestamps are in the same 5-day ranges, fall back to exact comparison
			// Prioritize more recently used keys within the same bucket
			if ($last_used_a !== $last_used_b) {
				return $last_used_b - $last_used_a; // More recent first
			}
			
			// Finally, prioritize older created timestamps within the same bucket
			return $created_a - $created_b; // Older first
		});

		// Return the best API key (first in the sorted array)
		return !empty($api_keys) ? $api_keys[0] : null;
	}

	public function validate_address($address_data) {
		$client = new Client([
			'base_uri' => $this->api_base_url,
			'headers' => [
				'Authorization' => 'ShippoToken ' . $this->api_key,
				'Content-Type' => 'application/json',
			]
		]);

		// Verify that required fields are present in $address_data
		$required_fields = [
			'name',
			'address_line_1',
			'city_locality',
			'state_province',
			'postal_code',
			'country_code'
		];
		foreach ($required_fields as $field) {
			if (!isset($address_data[$field])) {
				throw new InvalidArgumentException("Missing required address field: $field");
			}
		}

		// Validate the address
		try {
			$response = $client->get('v2/addresses/validate', [
				'name' => $address_data['name'],
				'organization' => $address_data['organization'],
				'address_line_1' => $address_data['address_line_1'],
				'address_line_2' => $address_data['address_line_2'],
				'city_locality' => $address_data['city_locality'],
				'state_province' => $address_data['state_province'],
				'postal_code' => $address_data['postal_code'],
				'country_code' => $address_data['country_code'],
			]);
			$body = $response->getBody();
			$data = json_decode($body, true);

			// Parse the response
			// Check 'analysis' -> 'validation_result' -> 'value'
			if (isset($data['analysis']['validation_result']['value'])) {
				switch ($data['analysis']['validation_result']['value']) {
					case 'valid':
						return [
							'status' => 'valid',
							'data' => $data
						];
					case 'partially_valid':
						return [
							'status' => 'partially_valid',
							'data' => $data
						];
					case 'invalid':
						return [
							'status' => 'invalid',
							'data' => $data
						];
					default:
						return [
							'status' => 'unknown',
							'data' => $data
						];
				}

				#TODO: Store the lookup result in the address_lookup table
			} else {
				// Unexpected response format
				error_log('Shippo API unexpected response format: ' . $body);
				return null;
			}
		} catch (RequestException $e) {
			// Handle request exception
			error_log('Shippo API request error: ' . $e->getMessage());
			return null;
		}
	}

	// Create an address record in Shippo

	public function create_address($address_data) {
		$client = new Client([
			'base_uri' => $this->api_base_url,
			'headers' => [
				'Authorization' => 'ShippoToken ' . $this->api_key,
				'Content-Type' => 'application/json',
			]
		]);

		// Create the address
		try {
			$response = $client->post('v2/addresses', [
				'json' => $address_data
			]);
			$body = $response->getBody();
			$data = json_decode($body, true);

			// Check for errors in the response
			if (isset($data['id'])) {
				// Return the created address's ID
				return $data['id'];
			} else {
				error_log('Shippo API error: ' . $data['detail'] ?? 'Unknown error');
				return false;
			}
		} catch (RequestException $e) {
			// Handle request exception
			error_log('Shippo API request error: ' . $e->getMessage());
			return false;
		}
	}
}
