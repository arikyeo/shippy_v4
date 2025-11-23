<?php

require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\MultipartStream;

/**
 * Class for interacting with the Easyship API
 */

// Require main class
require_once(realpath(__DIR__ . '/class_main.php'));

// Require user class
require_once(realpath(__DIR__ . '/class_user.php'));

// Require shippo class
require_once(realpath(__DIR__ . '/class_shippo.php'));

class class_easyship extends class_main {
	// Class implementation goes here

	// Easyship credentials
	private $easyship_api_key = 'prod_ZQypYgksA/9SjJIeeK1BsEYi8bWBZJzswPlNGVT3MvE=';
	private $easyship_api_base_url = 'https://api.easyship.com/';


	private $class_user;
	private $class_shippo;


	public function __construct() {

		global $easyship_api_key;
		parent::__construct();
		$this->easyship_api_key = $easyship_api_key;


		$this->class_user = new class_user();
		$this->class_shippo = new class_shippo();
	}
	public function __destruct() {
		parent::__destruct();
	}

	// Additional methods for interacting with the Easyship API would go here


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

	// Request rates from Easyship
	// https://developers.easyship.com/reference/rates_request

	/* 
		API endpoint: /rates
	{
		"origin_address": {
			"line_1": "Kennedy Town",
			"line_2": "Block 3",
			"state": "Yuen Long",
			"city": "Hong Kong",
			"postal_code": "0000",
			"country_alpha2": "HK",
			"contact_name": "Foo Bar",
			"company_name": "test 1",
			"contact_phone": "1234567890",
			"contact_email": "asd@asd.com"
		},
		"destination_address": {
			... (see above)
		},
		"incoterms": "DDU",
		"insurance": {
			"is_insured": false
		},
		"courier_settings": {
			"show_courier_logo_url": true,
			"apply_shipping_rules": true
		},
		"shipping_settings": {
			"units": {
				"weight": "kg",
				"dimensions": "cm"
			}
		},
		"parcels": [
			{
				"box": null,
				"items": [
					{
						"quantity": 2,
						"dimensions": {
							"length": 1,
							"width": 2,
							"height": 3
						},
						"description": "item",
						"category": "fashion",
						"sku": "sku",
						"origin_country_alpha2": "HK",
						"actual_weight": 10,
						"declared_currency": "USD",
						"declared_customs_value": 20
					}
				],
				"total_actual_weight": 1
			}
		]
	}
	*/

	public function request_rates($address_data_from, $address_data_to, $parcel_data) {

		// $address_data_from and $address_data_to should be associative arrays with address data
		// Required fields: address_line_1, address_line_2, city_locality, state_province, postal_code, country_code, contact_name, contact_email, contact_phone

		/* Parcel_data should be an array of parcel details (associative array) eg:
			[{
				"weight": "2",
				"length": "2",
				"width": "2",
				"height": "2",
				"distance_unit": "in",
				"mass_unit": "lb"
			}]
		*/

		// Verify required fields in $address_data_from and $address_data_to
		$required_fields = [
			'address_line_1',
			'address_line_2',
			'city_locality',
			'state_province',
			'postal_code',
			'country_code',
			'contact_name',
			'contact_email',
			'contact_phone'
		];
		foreach ($required_fields as $field) {
			if (!isset($address_data_from[$field]) || !isset($address_data_to[$field])) {
				throw new Exception("Missing required field '$field' in address data");
			}
		}

		// Verify parcel data
		if (empty($parcel_data) || !is_array($parcel_data)) {
			throw new Exception("Parcel data must be a non-empty array");
		}


		// Prepare request payload
		$payload = [
			'origin_address' => [
				'line_1' => $address_data_from['address_line_1'],
				'line_2' => $address_data_from['address_line_2'],
				'city' => $address_data_from['city_locality'],
				'state' => $address_data_from['state_province'],
				'postal_code' => $address_data_from['postal_code'],
				'country_alpha2' => $address_data_from['country_code'],
				'contact_name' => $address_data_from['contact_name'],
				'contact_email' => $address_data_from['contact_email'],
				'contact_phone' => $address_data_from['contact_phone']
			],
			'destination_address' => [
				'line_1' => $address_data_to['address_line_1'],
				'line_2' => $address_data_to['address_line_2'],
				'city' => $address_data_to['city_locality'],
				'state' => $address_data_to['state_province'],
				'postal_code' => $address_data_to['postal_code'],
				'country_alpha2' => $address_data_to['country_code'],
				'contact_name' => $address_data_to['contact_name'],
				'contact_email' => $address_data_to['contact_email'],
				'contact_phone' => $address_data_to['contact_phone']
			],
			'incoterms' => 'DDU',
			'insurance' => [
				'is_insured' => false
			],
			'courier_settings' => [
				'show_courier_logo_url' => true,
				'apply_shipping_rules' => true
			],
			'shipping_settings' => [
				'units' => [
					'weight' => 'kg',
					'dimensions' => 'cm'
				]
			],
		];

		// Add parcels to payload
		foreach ($parcel_data as $parcel) {
			$payload['parcels'][] = [
				'box' => null,
				'items' => [
					[
						'quantity' => 1,
						'dimensions' => [
							'length' => $parcel['length'],
							'width' => $parcel['width'],
							'height' => $parcel['height']
						],
						'description' => 'item',
						'category' => 'general',
						'sku' => 'sku',
						'origin_country_alpha2' => $address_data_from['country_code'],
						'actual_weight' => $parcel['weight'],
						'declared_currency' => 'USD',
						'declared_customs_value' => 10
					]
				],
				'total_actual_weight' => $parcel['weight']
			];
		}

		// Send request to Easyship API via Guzzle
		$client = new Client([
			'base_uri' => $this->easyship_api_base_url,
			'headers' => [
				'Authorization' => 'Bearer ' . $this->easyship_api_key,
				'Content-Type' => 'application/json'
			]
		]);

		$response = $client->request('POST', 'rates', [
			'json' => $payload
		]);

		try {
			$response = json_decode($response->getBody(), true);

			// Get response code
			$response_code = $response['status_code'] ?? null;

			// It should be 200 for success
			if ($response_code === 200) {
				// Return rates data
				return $response['rates'];
			} else {
				// Handle error

				/* Example error response:
					{
						"error": {
							"code": "invalid_content",
							"details": [
							"The request does not comply with the OpenAPI Specification.",
							"#/components/schemas/RateRequest missing required parameters: destination_address"
							],
							"links": [
							{
								"kind": "documentation",
								"name": "Errors",
								"url": "https://developers.easyship.com/reference/errors"
							}
							],
							"message": "The request body content is not valid.",
							"request_id": "01563646-58c1-4607-8fe0-cae3e92c4477",
							"type": "invalid_request_error"
						}
					}
				*/

				// Log error details
				$error_message = $response['error']['message'] ?? 'Unknown error';
				throw new \Exception($error_message, $response_code);
			}
		} catch (\Exception $e) {
			// Log exception
			error_log('Easyship API request error: ' . $e->getMessage());
			throw $e;
		}
	}
}
