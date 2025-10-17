<?php

require_once(realpath(__DIR__ . '/../vendor/autoload.php'));
require_once(realpath(__DIR__ . '/class_main.php'));

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\MultipartStream;

/**
 * Class for handling media processing tasks such as image uploads, processing, and retrieval.
 */
class class_media_processing extends class_main {

	/*  BEGIN PARAMS */
	public string|false $images_uploads_url;
	private string $imagga_api_credentials = "YWNjXzQ0MzA3MzQ3NDhiMGE0MDpkOTA3MmIyOTU0ZGNjM2ViNzAzZmQ3ZWIxYTNhZjQyZQ==";

	private string $cloudflare_account_id = "aa17a98d1d0dc1d9202fae5bb0ed0970";
	private string $cloudflare_stream_bearer_token = "PhYKpquLUF50FA4PAKtyGfNA62hXkGjpwVpQeDGi";
	private string $cloudflare_image_private_key = "jX6Quww0LlZbSpp2vV9eTHhsLtMnd55Q";

	// 10MB being the max allowable filesize for Cloudflare Image CDN
	private int $max_file_size = 10000000;

	/* END PARAMS */

	public function __construct() {
		parent::__construct();

		// Set the path to the images uploads directory
		$this->images_uploads_url = realpath(__DIR__ . '/../uploads/images');
	}

	public function __destruct() {
		parent::__destruct();
	}

	// This function handles the image upload
	public function handle_image_upload($image_file, $image_scope_variant = "default") {
		$error = '';

		/* $image_scope_variant determines if the image is a profile picture, cover photo, etc.
				* Determines if the image should be hidden from public in Cloudflare CDN (i.e require signed URL to access the image)
				* The image_scope_variant is also used to determine the aspect ratio of the image
				* E.g If the image_scope_variant is 'avatarImage', the aspect ratio of the image is 1:1 and the image should be publicly accessible
				* This will be expanded to include other image_scope_variants in the future
			*/

		$require_signed_url = true;   // Default
		$image_desired_width = 2000;  // Default image width
		$image_desired_height = 2000; // Default image height
		if ($image_scope_variant == 'avatarImage') {
			$require_signed_url = false;
			$image_desired_width = 2000;  // Avatar image width
			$image_desired_height = 2000; // Avatar image height
		} else {
			if ($image_scope_variant == 'postImage') {
				$require_signed_url = true;   // Post image should require signed URL
				$image_desired_width = 2000;  // Post image width
				$image_desired_height = 2000; // Post image height
			}
		}

		if ($image_file['error'] != 0) {
			$error = 'Error uploading image';
			return array(
				'error'   => $error,
				'success' => false,
			);
		}


		$image_file_name = $image_file['name'];
		$image_file_tmp_name = $image_file['tmp_name'];

		// Ensure that the uploaded image is within the allowed file size
		if ($image_file['size'] > $this->max_file_size) {
			$error = 'Image file size is too large';
			return array(
				'error'   => $error,
				'success' => false,
			);
		}

		// Check if the uploaded image is valid
		if ($this->validate_uploaded_image($image_file_tmp_name)) {

			// Upload image to Cloudflare Image CDN
			$cloudflare_stream_upload_response = $this->cloudflare_stream_upload_image($image_file_tmp_name, $require_signed_url);

			if ($cloudflare_stream_upload_response !== false) {

				// Get the image ID from the Cloudflare Image CDN response
				$image_id = $cloudflare_stream_upload_response->result->id;

				// Create new directory for the image
				$image_directory = $this->images_uploads_url . "/" . $image_id;
				if (!file_exists($image_directory)) {
					mkdir($image_directory . "/", 0777, true);
				}

				// Build a manifest.json file for the image
				$image_manifest = array(
					'cloudflare_image_id'   => $image_id,
					'cloudflare_image_data' => $cloudflare_stream_upload_response,
				);
				file_put_contents($image_directory . '/manifest.json', json_encode($image_manifest));

				// Download the image from the Cloudflare Image CDN to the new directory
				$cloudflare_stream_download_image_response = $this->cloudflare_stream_download_image($image_id);

				if ($cloudflare_stream_download_image_response !== false) {

					// Return the image ID
					return array(
						'image_id' => $image_id,
						'success'  => true,
					);
				} else {
					$error = 'Error downloading image';
					return array(
						'error'   => $error,
						'success' => false,
					);
				}
			}
		} else {
			$error = 'Invalid image file';
			return array(
				'error'   => $error,
				'success' => false,
			);
		}
	}

	// This function validates the uploaded image
	public function validate_uploaded_image($path_to_image) {
		// $error = '';

		if (
			!exif_imagetype($path_to_image) &&
			!in_array(pathinfo($path_to_image, PATHINFO_EXTENSION), array('jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp')) &&
			!in_array(mime_content_type($path_to_image), array('image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp')) &&
			!getimagesize($path_to_image) &&
			!@imagecreatefromgif($path_to_image) &&
			!@imagecreatefromjpeg($path_to_image) &&
			!@imagecreatefrompng($path_to_image) &&
			!@imagecreatefrombmp($path_to_image) &&
			!@imagecreatefromwebp($path_to_image)
		) {
			return false;
		} else {
			return true;
		}
	}

	// This function uploads the image to Cloudflare Image CDN
	public function cloudflare_stream_upload_image($image_file_path, $require_signed_url = true) {

		// Get the image file hash
		$image_file_hash = hash_file('sha256', $image_file_path);

		// Use Guzzle to upload image to Cloudflare Image CDN
		try {
			$client = new Client([
				'timeout' => 30,
			]);
			$response = $client->Request('POST', 'https://api.cloudflare.com/client/v4/accounts/' . $this->cloudflare_account_id . '/images/v1', [
				'headers'   => [
					'Authorization' => 'Bearer ' . $this->cloudflare_stream_bearer_token,
				],
				'multipart' => [
					[
						'name'     => 'file',
						'contents' => fopen(realpath($image_file_path), 'r'),
					],
					[
						'name'     => 'metadata',
						'contents' => json_encode([
							'file_hash_sha256' => $image_file_hash,
						]),
					],
					[
						'name'     => 'requireSignedURLs',
						'contents' => $require_signed_url
					]
				],
			]);

			if ($response->getStatusCode() == 200) {
				$json_response = json_decode($response->getBody());
				if (isset($json_response->result->id) && ($json_response->success == true)) {
					return $json_response;
				} else {
					return false;
				}
			} else {
				return false;
			}
		} catch (RequestException $e) {
			// Handle exception or return false
			// echo $e->getMessage();
			return false;
		}
	}

	// This function downloads the image from Cloudflare Image CDN
	public function cloudflare_stream_download_image($image_id, $request_image_format = 'image/webp') {

		$image_directory = $this->images_uploads_url . "/" . $image_id;

		// Use Guzzle to get image from Cloudflare Image CDN
		try {
			$client = new Client(
				[
					'timeout' => 30,
					'sink'    => $image_directory . '/original_blob',
				]
			);
			$headers = [
				'Authorization' => 'Bearer ' . $this->cloudflare_stream_bearer_token,
				'Accept'        => $request_image_format,
			];
			$response = $client->Request('GET', 'https://api.cloudflare.com/client/v4/accounts/' . $this->cloudflare_account_id . '/images/v1/' . $image_id . '/blob', [
				'headers' => [
					'Authorization' => 'Bearer ' . $this->cloudflare_stream_bearer_token,
				],
			]);

			if ($response->getStatusCode() == 200) {
				// Get image content-type from response headers
				$image_content_type = $response->getHeader('Content-Type')[0];

				// Rename the downloaded image file to the appropriate extension based on the image content-type
				$image_save_path = $image_directory . '/original.' . explode('/', $image_content_type)[1];

				// Rename the downloaded image file
				$rename_status = rename($image_directory . '/original_blob', $image_save_path);
				if ($rename_status) {
					return true;
				} else {
					return false;
				}
			} else {
				return false;
			}
		} catch (RequestException $e) {
			// Handle exception or return false
			return false;
		}
	}



	// Imagga API related functions
	public function imagga_get_upload_id_from_image($image_file_path) {
		// Use Guzzle to upload image to Imagga

		$client = new Client([
			'base_uri' => 'https://api.imagga.com/v2',
		]);

		try {
			$multipart = new MultipartStream([
				[
					'name'     => 'image',
					'contents' => fopen(realpath($image_file_path), 'r')
				]
			]);

			$response = $client->request('POST', '/uploads', [
				'headers' => [
					'Authorization' => 'Basic ' . $this->imagga_api_credentials,
				],
				'body'    => $multipart
			]);

			if ($response->getStatusCode() == 200) {
				$json_response = json_decode($response->getBody());
				if (isset($json_response->result->upload_id) && ($json_response->status->type == 'success')) {
					return $json_response->result->upload_id;
				} else {
					return false;
				}
			} else {
				return false;
			}
		} catch (RequestException $e) {
			return false;
		}
	}

	public function imagga_get_crop_suggestions($imagga_image_id, $crop_aspect_ratio_array) {
		// Use Guzzle to get crop suggestions from Imagga

		$client = new Client([
			'base_uri' => 'https://api.imagga.com/v2',
		]);

		try {
			$response = $client->request('GET', '/croppings', [
				'headers' => [
					'Authorization' => 'Basic ' . $this->imagga_api_credentials,
				],
				'json'    => [
					'content'   => [
						'id' => $imagga_image_id,
					],
					'croppings' => $crop_aspect_ratio_array,
				],
			]);

			if ($response->getStatusCode() == 200) {
				$json_response = json_decode($response->getBody());
				if (isset($json_response->result->croppings) && ($json_response->status->type == 'success')) {
					return $json_response->result->croppings;
				} else {
					return false;
				}
			} else {
				return false;
			}
		} catch (RequestException $e) {
			return false;
		}
	}

	public function imagga_get_tags_from_image($image_file_path) {
		// Use Guzzle to get tags from Imagga

		$client = new Client([
			'base_uri' => 'https://api.imagga.com/v2',
		]);

		try {
			$multipart = new MultipartStream([
				[
					'name'     => 'image',
					'contents' => fopen(realpath($image_file_path), 'r')
				]
			]);

			$response = $client->request('POST', '/tags', [
				'headers' => [
					'Authorization' => 'Basic ' . $this->imagga_api_credentials,
				],
				'body'    => $multipart
			]);

			if ($response->getStatusCode() == 200) {
				$json_response = json_decode($response->getBody());
				if (isset($json_response->result->tags) && ($json_response->status->type == 'success')) {
					return $json_response->result->tags;
				} else {
					return false;
				}
			} else {
				return false;
			}
		} catch (RequestException $e) {
			return false;
		}
	}














	// Cloudflare Stream API related functions


	// Upload image to Cloudflare Image CDN



	// Get original image from Cloudflare Image CDN

	public function get_image_by_variant($image_id, $image_variant_name, $request_image_format = 'image/webp') {

		// Load manifest.json file for the image
		$image_manifest_path = realpath($this->images_uploads_url . '/' . $image_id . '/manifest.json');
		if (file_exists($image_manifest_path)) {
			$image_manifest = json_decode(file_get_contents($image_manifest_path));
			$cloudflare_image_id = $image_manifest->cloudflare_image_id;
			$cloudflare_image_variant_urls = $image_manifest->cloudflare_image_data->result->variants;

			$variantUrl = '';
			// Check if the image variant exists
			foreach ($cloudflare_image_variant_urls as $cloudflare_image_variant_url) {
				if (preg_match("/\b" . preg_quote($image_variant_name, '/') . "\b/", $cloudflare_image_variant_url) === 1) {
					$variantUrl = $cloudflare_image_variant_url;
					break; // Stop the loop once the variant is found
				}
			}

			if ($variantUrl !== '') {
				// Variant URL found
				return $variantUrl;
			} else {
				// Variant not found, attempt to update the variants from Cloudflare Image CDN
				$cloudflare_image_variant_urls = $this->cloudflare_stream_get_image_variants($cloudflare_image_id);
				if ($cloudflare_image_variant_urls !== false) {
					// Update the manifest.json file with the new variants
					$image_manifest->cloudflare_image_data->result->variants = $cloudflare_image_variant_urls;
					file_put_contents($image_manifest_path, json_encode($image_manifest));

					// Check if the image variant exists
					foreach ($cloudflare_image_variant_urls as $cloudflare_image_variant_url) {
						if (preg_match("/\b" . preg_quote($image_variant_name, '/') . "\b/", $cloudflare_image_variant_url) === 1) {
							$variantUrl = $cloudflare_image_variant_url;
							break; // Stop the loop once the variant is found
						}
					}

					if ($variantUrl !== '') {
						// Variant URL found
						return $variantUrl;
					} else {
						// Variant URL still not found, return fallback image from local storage
						$image_directory = realpath($this->images_uploads_url . '/' . $image_id);
						$image_file_path = $this->findFileByName($image_directory, 'original');
						if ($image_file_path !== false) {
							return $image_file_path;
						} else {
							return false;
						}
					}
				}
			}
		} else {
			// The image may have disappeared locally, try to retrieve it from Cloudflare Image CDN
			$image_details = $this->cloudflare_stream_get_image_details($image_id);
			if ($image_details !== false) {

				// Create new directory for the image
				$image_directory = $this->images_uploads_url . "/" . $image_id;
				if (!file_exists($image_directory)) {
					mkdir($image_directory . "/", 0777, true);
				}

				// Build a manifest.json file for the image
				$image_manifest = array(
					'cloudflare_image_id'   => $image_id,
					'cloudflare_image_data' => $image_details,
				);
				file_put_contents($image_directory . '/manifest.json', json_encode($image_manifest));

				// Download the image from the Cloudflare Image CDN to the new directory
				$cloudflare_stream_download_image_response = $this->cloudflare_stream_download_image($image_id);

				// Try again
				return $this->get_image_by_variant($image_id, $image_variant_name);
			} else return false;
		}
	}


	// Get image variants from Cloudflare Image CDN

	public function cloudflare_stream_get_image_variants($image_id) {

		// If the image ID is not provided, return false
		if ($image_id == '') {
			return false;
		}

		// Use Guzzle to get image variants from Cloudflare Image CDN
		try {
			$client = new Client([
				'timeout' => 30,
			]);
			$response = $client->Request('GET', 'https://api.cloudflare.com/client/v4/accounts/' . $this->cloudflare_account_id . '/images/v1/' . $image_id, [
				'headers' => [
					'Authorization' => 'Bearer ' . $this->cloudflare_stream_bearer_token,
				],
			]);

			if ($response->getStatusCode() == 200) {
				$json_response = json_decode($response->getBody());
				if (isset($json_response->result->variants) && ($json_response->success == true)) {
					return $json_response->result->variants;
				} else {
					return false;
				}
			} else {
				return false;
			}
		} catch (RequestException $e) {
			// Handle exception or return false
			return false;
		}
	}


	// Get image variants from Cloudflare Image CDN

	private function findFileByName($directory, $fileName) {
		$pattern = $directory . '/*' . $fileName . '*.*'; // Pattern to match file names containing $fileName
		$files = glob($pattern);                          // Search for files matching the pattern

		if (!empty($files)) {
			// Assuming you want the first match
			return $files[0]; // Return the first matched file
		} else {
			return false; // No file found
		}
	}


	// Get image by variant from Cloudflare Image CDN / local storage

	public function cloudflare_stream_get_image_details($image_id) {

		// If the image ID is not provided, return false
		if ($image_id == '') {
			return false;
		}

		// Use Guzzle to get image variants from Cloudflare Image CDN
		try {
			$client = new Client([
				'timeout' => 30,
			]);
			$response = $client->Request('GET', 'https://api.cloudflare.com/client/v4/accounts/' . $this->cloudflare_account_id . '/images/v1/' . $image_id, [
				'headers' => [
					'Authorization' => 'Bearer ' . $this->cloudflare_stream_bearer_token,
				],
			]);

			if ($response->getStatusCode() == 200) {
				$json_response = json_decode($response->getBody());
				if (isset($json_response->result->variants) && ($json_response->success == true)) {
					return $json_response;
				} else {
					return false;
				}
			} else {
				return false;
			}
		} catch (RequestException $e) {
			// Handle exception or return false
			return false;
		}
	}

	public function generate_signed_image_url($url, $expiry_time = 3600) {

		// Ensure that the image delivery URL is a valid URL
		if (!filter_var($url, FILTER_VALIDATE_URL)) {
			return false;
		}

		$secretKeyData = mb_convert_encoding($this->cloudflare_image_private_key, "UTF-8", mb_detect_encoding($this->cloudflare_image_private_key));
		$key = openssl_digest($secretKeyData, 'SHA256', true);

		$expiry = time() + $expiry_time;
		$urlParts = parse_url($url);

		// Initialize query if not present
		if (!isset($urlParts['query'])) {
			$urlParts['query'] = '';
		}

		parse_str($urlParts['query'], $query);
		$query['exp'] = $expiry;
		$urlParts['query'] = http_build_query($query);
		$signedUrl = $urlParts['scheme'] . '://' . $urlParts['host'] . $urlParts['path'] . (isset($urlParts['query']) ? '?' . $urlParts['query'] : '');

		$stringToSign = $urlParts['path'] . (isset($urlParts['query']) ? '?' . $urlParts['query'] : '');
		$mac = hash_hmac('sha256', $stringToSign, $key);
		$sig = $this->bufferToHex($mac);

		$urlParts['query'] .= (empty($urlParts['query']) ? '' : '&') . 'sig=' . $sig;
		$signedUrl = $urlParts['scheme'] . '://' . $urlParts['host'] . $urlParts['path'] . (isset($urlParts['query']) ? '?' . $urlParts['query'] : '');

		return $signedUrl;
	}

	// Generate signed image URL for Cloudflare Image CDN

	private function bufferToHex($buffer) {
		return implode('', array_map(function ($x) {
			return str_pad(dechex($x), 2, '0', STR_PAD_LEFT);
		}, unpack('C*', $buffer)));
	}


	// Cloudflare Stream Video: Retrieve video details

	public function cloudflare_stream_check_video_ready_to_stream($video_hash) {
		// Attempt to get video details from the database
		$video_details = $this->cloudflare_stream_get_video_details_from_db($video_hash);

		// If not found, fetch from Cloudflare Stream and retry fetching from the database
		if ($video_details == false || $video_details['video_ready_to_stream'] == false) {
			$this->cloudflare_stream_store_video_details($video_hash);
			$video_details = $this->cloudflare_stream_get_video_details_from_db($video_hash);
		}

		// Return the video ready status or false if details are still not found
		return $video_details != false ? $video_details['video_ready_to_stream'] : false;
	}




	// Cloudflare Stream Video: Store video details into database
	/*		CREATE TABLE `video_uploads` (
										`id` int(11) NOT NULL AUTO_INCREMENT,
										`video_hash` varchar(255) NOT NULL,
										`video_uploader_user_hash` varchar(64) DEFAULT NULL,
										`video_accessible_flag` tinyint(1) NOT NULL DEFAULT 0,
										`video_ready_to_stream` tinyint(1) NOT NULL DEFAULT 0,
										`video_alt_title` varchar(255) DEFAULT NULL COMMENT 'Title Description',
										`video_desc` varchar(1024) DEFAULT NULL COMMENT 'Description of Video',
										`video_cloudflare_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Cloudflare video details' CHECK (json_valid(`video_cloudflare_details`)),
										`video_time_uploaded` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
										`video_time_updated` timestamp NOT NULL DEFAULT current_timestamp(),
										PRIMARY KEY (`id`)
										) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci */

	public function cloudflare_stream_get_video_details_from_db($video_hash) {

		// Get video details from database
		$query = "SELECT * FROM video_uploads WHERE video_hash = :video_hash";
		$query_params = array(
			':video_hash' => $video_hash
		);

		try {
			$stmt = $this->db->prepare($query);
			$result = $stmt->execute($query_params);
		} catch (PDOException $ex) {
			return false;
		}

		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if ($row) {
			return $row;
		} else {
			return false;
		}
	}


	// Cloudflare Stream Video: Retrieve video details from database

	public function cloudflare_stream_store_video_details($video_hash, $video_accessible_flag = true, $video_uploader_user_hash = '', $video_alt_title = '', $video_desc = '') {

		// Get video details from Cloudflare Stream
		$video_details = $this->cloudflare_stream_get_video_details($video_hash);

		// Default video_uploader_user_hash to currently logged in user
		if ($video_uploader_user_hash == '') {
			$video_uploader_user_hash = $_SESSION['user_hash'];
		}

		// Check if video details were successfully retrieved
		if ($video_details != false && $video_details['success'] == true) {

			// Get ready to stream status
			$video_ready_to_stream = false;
			$video_ready_to_stream = $video_details['result']['readyToStream'];

			// Store video details into database, UPDATE if video_hash already exists
			$query = "INSERT INTO video_uploads (
                                        video_hash,
                                        video_uploader_user_hash,
                                        video_accessible_flag,
                                        video_ready_to_stream,
                                        video_alt_title,
                                        video_desc,
                                        video_cloudflare_details
                                    ) VALUES (
                                        :video_hash,
                                        :video_uploader_user_hash,
                                        :video_accessible_flag,
                                        :video_ready_to_stream,
                                        :video_alt_title,
                                        :video_desc,
                                        :video_cloudflare_details
                                    ) ON DUPLICATE KEY UPDATE
                                        video_uploader_user_hash = :video_uploader_user_hash,
                                        video_accessible_flag = :video_accessible_flag,
                                        video_ready_to_stream = :video_ready_to_stream,
                                        video_alt_title = :video_alt_title,
                                        video_desc = :video_desc,
                                        video_cloudflare_details = :video_cloudflare_details";

			// Prepare the query
			$query_params = array(
				':video_hash'               => $video_hash,
				':video_uploader_user_hash' => $video_uploader_user_hash,
				':video_accessible_flag'    => $video_accessible_flag,
				':video_ready_to_stream'    => $video_ready_to_stream,
				':video_alt_title'          => $video_alt_title,
				':video_desc'               => $video_desc,
				':video_cloudflare_details' => json_encode($video_details),
			);


			try {
				$stmt = $this->db->prepare($query);
				$result = $stmt->execute($query_params);
				return true;
			} catch (PDOException $ex) {
				echo $ex->getMessage();
				return false;
			}
		} else {
			return false;
		}
	}


	// Cloudflare Stream Video: Check if video is ready to stream

	public function cloudflare_stream_get_video_details($video_hash) {

		// Use Guzzle to get video details from Cloudflare Stream
		try {
			$client = new Client([
				'timeout' => 30,
			]);
			$response = $client->Request('GET', 'https://api.cloudflare.com/client/v4/accounts/' . $this->cloudflare_account_id . '/stream/' . $video_hash, [
				'headers' => [
					'Authorization' => 'Bearer ' . $this->cloudflare_stream_bearer_token,
				],
			]);

			if ($response->getStatusCode() == 200) {
				$json_response = json_decode($response->getBody(), true);
				if (isset($json_response['result']) && $json_response['success'] == true) {
					return $json_response;
				} else {
					return false;
				}
			} else {
				return false;
			}
		} catch (RequestException $e) {
			// Handle exception or return false
			return false;
		}
	}


	// Cloudflare Stream Video: Get signed video token

	public function cloudflare_stream_get_signed_video_url($video_hash, $video_format, $expiry_time = 300, $nbf_time = 0, $video_is_downloadable = false) {

		// For now, $video_format supports HLS and DASH
		// Verify that the video format is supported
		// if ($video_format !== null && !in_array($video_format, array('hls', 'dash'))) {
		// 	return false;
		// }

		// Get video details from database
		$video_details = $this->cloudflare_stream_get_video_details_from_db($video_hash);

		// Check if video details were successfully retrieved, and if the video is ready to stream and the video is accessible
		if ($video_details !== false && $video_details['video_ready_to_stream'] == true && $video_details['video_accessible_flag'] == true) {
			$video_details = json_decode($video_details['video_cloudflare_details'], true);

			// Get video URL from $video_details based on requested $video_format
			$video_url = $video_details['result']['playback'][$video_format];

			// If the video URL is not empty AND appears to be a valid URL, get the signed token
			if ($video_url !== '' && filter_var($video_url, FILTER_VALIDATE_URL)) {
				$signed_video_token = $this->cloudflare_stream_get_signed_video_token_local($video_hash, $expiry_time, $nbf_time, $video_is_downloadable);

				// If the signed video token is not empty, replace all instances of $video_hash with $signed_video_token
				if ($signed_video_token !== false) {
					$signed_video_url = str_replace($video_hash, $signed_video_token, $video_url);
					return $signed_video_url;
				}
			}
		}

		// Default return false
		return false;
	}


	// Cloudflare Stream Video: Get signed video/thumbnail token
	public function cloudflare_stream_get_signed_video_token_local($video_hash, $expiry_time = 300, $nbf_time = 0, $video_is_downloadable = false) {
		// nbf_time is the time before which the token must not be accepted
		// expiry_time is the time after which the token must not be accepted
		// expiry and nbf are in seconds since the Unix epoch
		// Convert expiry and nbf to Unix epoch time from current time
		$expiry_time = time() + $expiry_time;
		$nbf_time = time() + $nbf_time;

		// Get video details from Cloudflare Stream
		$video_details = $this->cloudflare_stream_get_video_details_from_db($video_hash);

		// Check if video details were successfully retrieved
		if ($video_details !== false) {
			$video_details = json_decode($video_details['video_cloudflare_details'], true);

			// Generate signed token locally
			$key_array = [
				"id" => "61d70fa93edc31a42da07abb8a760f00",
				"pem" => "LS0tLS1CRUdJTiBSU0EgUFJJVkFURSBLRVktLS0tLQpNSUlFb3dJQkFBS0NBUUVBdEVnWFhLR29IeTltWms0NUpocVNQL00wb0ZYdWhUbFR4Mk5sZ3JwSVpDNU5ZQkppCmlKdEUyZmxVbkVORFNoUnE5MjdjOG15QjB2QmplOEhwdWg5L050Y2xtNEU5L0M5UGFDZmdGRlhUNFJBM0oyTEwKUWEzRnZodnFvS3R5blVqbGcrUGZUZ3V3aTMxaVR3cnMwd0hsYWJZYW9sb3J0cXVMRElPeFVUakpOZnV0TDZNUwpROW9Td1RSSWVyOC8wNk5QN3BiOERONjBOQlN4Zk9tV3NmbVAzNlhjemFhQ1JnekdZSGRCUUNUK2RJenRGZUNZClljcjd1TU4zNzR6UmFWYUdBTlBkemV6dWtaZlN3S0M5QldNNHBpcUo0M1QrbCtScE1DSTFOOXZ1Uy9xZ0xpL00Kc3orL3hDZGxNRDNIT0U1ekJabjIvY3lHcGljKzFwMFB4dnU1VHdJREFRQUJBb0lCQUVDcFFzZlVxNHBUNC9SNQp4Z0dtc2lJQnh6UmkzZVFteGFmVVBNcUFxZ3BVbVNnR01CVXpLNlRLeXRBcFIrOUFGNFdiMjkrUGo1anE0Nk8xCnozRVVidnBxZkhDa0VHbHRScHZqQVhvSnRxOFlzOCtLbmNCMjVWL2tmMmtWVlV5WW9zbUZaOGlFWk5RREtzbVMKNzVKWE1jT1NyMGJmOUtIV01VOVJ4TDJQRW93cm1Ba24wN2pPQkJPenRWbWtTb0pMbi9OWU9JQXl1VlJiS2tzcApVeWVMcndSUHdUeWsrWXRuK0hoQnYwc3Q5RGphVm16eGN6UTFwckZOd2J1WjVRYmh3RWZ3Z0NqcUZQM09RU0RrCjczdEx2M0dMNWJlOXNXQXlFaFQwOS9hV0IwNlZjSzB5NFRIVDllRy8waDR4UC9STTNHdUlVWlFDWFpvRnR2YXgKdkdNWXFORUNnWUVBN0ZXempkOWhlL2F4cUowOU5WU2N2OFZ0NC9ycU9meFFESnBJVmNHWGJYYldpMzV0cEkyNwo4Tnlua0FQdGFDUUdzL0hVNzZpS0RYV2U0bkJyS05EVXc3ZWVNSXF2NWZ2MlVJZHZPaHcyTDFPS2dnQ0trd0MzCnlBY2FRQURwVG5naEFvc2dpZ0krTWJWdldMY3c4NDZvOG1ySzJMR29QNGY5TU9QamR5VTZQSE1DZ1lFQXcwaGUKeExUMUNqY1lIMWEwZ2VzeEpNUStnZERLN0ZIODVpbFF5ZW9aNE9QandQVTlYM0tONlVTMUdnOXhOTW9KeFVVbgpzTVBKWWhDbDZZbU1FN1drYkRWSzcwUjZNbzhheDJPMTVYTFlNRkhUalc4TEt2R1ltU2U4Q2VsK0FhUng2OWtWClB3WS9PTlEzYzRBQlhqaUdmOHRsV3pJR3JrV0gvUFE0WmJnU0ZMVUNnWUJxN2N1em9TSW1TRlBSaW5Nck1nRkoKOHpYcE5Kbk5hbzk5WkVEZUxCMHJkZDFVZC90N3ZIVVFZWVdlNzJmMituWGQ3TWovTmk1Z01KdVdzRzZMcFJEZgpETEVTSFczQWpPUEJROFhiY1BCRE1YVTFwTEVPR2dFTkM4bWdzOGpickJhalkvZHcrZHJSK3RsS05uaDdla3lPCmdpc05LRFNMcWlld2V5dHJ1UGhFYVFLQmdRQ1JsWEJoUVc2MDBPSUE2d2pqR2sybTFVNnNSTllqVy9Rb09vRHEKSnNab0xEenM2MmQzc3RVdEpIWEhHZUFSdE5XWDViaHpSV0xxNHZKdHFvZHRZaXRVS0Y3WEJidjcyVWZqZ2VobgpTRGozdk9qME5lYWplejJDWUdjRkZMZEZ6aXpINFN5L3NZNk1kVmxwbC9KdEpjTFBudmpQQmZxSkRYa1dFWlBCCjhYbzVTUUtCZ0ZYN2pCWEZvMjR2SEg4MUpyYkZ0UUZaSFdHN1p5ckh6NkRzaDlGNWt3RHQzQWdVOWQ3eTRBN2QKUDdmTGErZEwxWldKU0NKZjI5ZXVQYzJORE53OUhSL2VQVlNEMStyN1JNK3pxVDkwVmNHYVFYeVdoTk1uYmJIdgpKUzBoTitvZWZVck1NMTZTWUx5UUh4QkUvaVJXaTF2cTh6Q0o2b0JPMEhjOVlRNEJrTWZICi0tLS0tRU5EIFJTQSBQUklWQVRFIEtFWS0tLS0tCg==",
				"jwk" => "eyJ1c2UiOiJzaWciLCJrdHkiOiJSU0EiLCJraWQiOiI2MWQ3MGZhOTNlZGMzMWE0MmRhMDdhYmI4YTc2MGYwMCIsImFsZyI6IlJTMjU2IiwibiI6InRFZ1hYS0dvSHk5bVprNDVKaHFTUF9NMG9GWHVoVGxUeDJObGdycElaQzVOWUJKaWlKdEUyZmxVbkVORFNoUnE5MjdjOG15QjB2QmplOEhwdWg5X050Y2xtNEU5X0M5UGFDZmdGRlhUNFJBM0oyTExRYTNGdmh2cW9LdHluVWpsZy1QZlRndXdpMzFpVHdyczB3SGxhYllhb2xvcnRxdUxESU94VVRqSk5mdXRMNk1TUTlvU3dUUkllcjhfMDZOUDdwYjhETjYwTkJTeGZPbVdzZm1QMzZYY3phYUNSZ3pHWUhkQlFDVC1kSXp0RmVDWVljcjd1TU4zNzR6UmFWYUdBTlBkemV6dWtaZlN3S0M5QldNNHBpcUo0M1QtbC1ScE1DSTFOOXZ1U19xZ0xpX01zei1feENkbE1EM0hPRTV6QlpuMl9jeUdwaWMtMXAwUHh2dTVUdyIsImUiOiJBUUFCIiwiZCI6IlFLbEN4OVNyaWxQajlIbkdBYWF5SWdISE5HTGQ1Q2JGcDlROHlvQ3FDbFNaS0FZd0ZUTXJwTXJLMENsSDcwQVhoWnZiMzQtUG1PcmpvN1hQY1JSdS1tcDhjS1FRYVcxR20tTUJlZ20ycnhpeno0cWR3SGJsWC1SX2FSVlZUSmlpeVlWbnlJUmsxQU1xeVpMdmtsY3h3NUt2UnRfMG9kWXhUMUhFdlk4U2pDdVlDU2ZUdU00RUU3TzFXYVJLZ2t1ZjgxZzRnREs1VkZzcVN5bFRKNHV2QkVfQlBLVDVpMmY0ZUVHX1N5MzBPTnBXYlBGek5EV21zVTNCdTVubEJ1SEFSX0NBS09vVV9jNUJJT1R2ZTB1X2NZdmx0NzJ4WURJU0ZQVDM5cFlIVHBWd3JUTGhNZFAxNGJfU0hqRV85RXpjYTRoUmxBSmRtZ1cyOXJHOFl4aW8wUSIsInAiOiI3Rld6amQ5aGVfYXhxSjA5TlZTY3Y4VnQ0X3JxT2Z4UURKcElWY0dYYlhiV2kzNXRwSTI3OE55bmtBUHRhQ1FHc19IVTc2aUtEWFdlNG5CcktORFV3N2VlTUlxdjVmdjJVSWR2T2h3MkwxT0tnZ0NLa3dDM3lBY2FRQURwVG5naEFvc2dpZ0ktTWJWdldMY3c4NDZvOG1ySzJMR29QNGY5TU9QamR5VTZQSE0iLCJxIjoidzBoZXhMVDFDamNZSDFhMGdlc3hKTVEtZ2RESzdGSDg1aWxReWVvWjRPUGp3UFU5WDNLTjZVUzFHZzl4Tk1vSnhVVW5zTVBKWWhDbDZZbU1FN1drYkRWSzcwUjZNbzhheDJPMTVYTFlNRkhUalc4TEt2R1ltU2U4Q2VsLUFhUng2OWtWUHdZX09OUTNjNEFCWGppR2Y4dGxXeklHcmtXSF9QUTRaYmdTRkxVIiwiZHAiOiJhdTNMczZFaUpraFQwWXB6S3pJQlNmTTE2VFNaeldxUGZXUkEzaXdkSzNYZFZIZjdlN3gxRUdHRm51OW45dnAxM2V6SV96WXVZRENibHJCdWk2VVEzd3l4RWgxdHdJemp3VVBGMjNEd1F6RjFOYVN4RGhvQkRRdkpvTFBJMjZ3V28yUDNjUG5hMGZyWlNqWjRlM3BNam9JckRTZzBpNm9uc0hzcmE3ajRSR2siLCJkcSI6ImtaVndZVUZ1dE5EaUFPc0k0eHBOcHRWT3JFVFdJMXYwS0RxQTZpYkdhQ3c4N090bmQ3TFZMU1IxeHhuZ0ViVFZsLVc0YzBWaTZ1THliYXFIYldJclZDaGUxd1c3LTlsSDQ0SG9aMGc0OTd6bzlEWG1vM3M5Z21CbkJSUzNSYzRzeC1Fc3Y3R09qSFZaYVpmeWJTWEN6NTc0endYNmlRMTVGaEdUd2ZGNk9VayIsInFpIjoiVmZ1TUZjV2piaThjZnpVbXRzVzFBVmtkWWJ0bktzZlBvT3lIMFhtVEFPM2NDQlQxM3ZMZ0R0MF90OHRyNTB2VmxZbElJbF9iMTY0OXpZME0zRDBkSDk0OVZJUFg2dnRFejdPcFAzUlZ3WnBCZkphRTB5ZHRzZThsTFNFMzZoNTlTc3d6WHBKZ3ZKQWZFRVQtSkZhTFctcnpNSW5xZ0U3UWR6MWhEZ0dReDhjIn0="
			];

			$signed_video_token = $this->cf_stream_helper_signToken($video_hash, $key_array, $expiry_time, $nbf_time);

			// If the signed video token is not empty, return the signed video token
			if ($signed_video_token !== false) {
				return $signed_video_token;
			}
		} else {
			return false;
		}
	}
	/**
	 * Signs a url token for the stream reproduction
	 *
	 * @param string $uid The stream uid.
	 * @param array $key The key id and pem used for the signing.
	 * @param string $exp Expiration; a unix epoch timestamp after which the token will not be accepted.
	 * @param string $nbf notBefore; a unix epoch timestamp before which the token will not be accepted.
	 *
	 * https://dev.to/robdwaller/how-to-create-a-json-web-token-using-php-3gml
	 * https://developers.cloudflare.com/stream/viewing-videos/securing-your-stream#creating-a-signing-key
	 *
	 */
	public static function cf_stream_helper_signToken(string $uid, array $key, string $exp = null, string $nbf = null) {
		$privateKey = base64_decode($key['pem']);

		$header = ['alg' => 'RS256', 'kid' => $key['id']];
		$payload = ['sub' => $uid, 'kid' => $key['id']];

		if ($exp) {
			$payload['exp'] = $exp;
		}

		if ($nbf) {
			$payload['nbf'] = $nbf;
		}

		$encodedHeader = self::base64Url(json_encode($header));
		$encodedPayload = self::base64Url(json_encode($payload));

		openssl_sign("$encodedHeader.$encodedPayload", $signature, $privateKey, 'RSA-SHA256');

		$encodedSignature = self::base64Url($signature);

		return "$encodedHeader.$encodedPayload.$encodedSignature";
	}

	protected static function base64Url(string $data) {
		return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
	}

	public function cloudflare_stream_get_signed_video_token_from_server($video_hash, $expiry_time = 300, $nbf_time = 0, $video_is_downloadable = false) {
		// nbf_time is the time before which the token must not be accepted
		// expiry_time is the time after which the token must not be accepted
		// expiry and nbf are in seconds since the Unix epoch
		// Convert expiry and nbf to Unix epoch time from current time
		$expiry_time = time() + $expiry_time;
		$nbf_time = time() + $nbf_time;

		// Get video details from Cloudflare Stream
		$video_details = $this->cloudflare_stream_get_video_details_from_db($video_hash);

		// Check if video details were successfully retrieved
		if ($video_details !== false) {
			$video_details = json_decode($video_details['video_cloudflare_details'], true);

			// Get video token from Cloudflare Stream endpoint
			try {
				$client = new Client([
					'timeout' => 30,
				]);
				$response = $client->Request('POST', 'https://api.cloudflare.com/client/v4/accounts/' . $this->cloudflare_account_id . '/stream/' . $video_hash . '/token', [
					'headers' => [
						'Authorization' => 'Bearer ' . $this->cloudflare_stream_bearer_token,
					],
					'json'    => [
						'downloadable' => $video_is_downloadable,
						'expiry'       => $expiry_time,
						'nbf'          => $nbf_time,
					],
				]);

				if ($response->getStatusCode() == 200) {
					$json_response = json_decode($response->getBody());
					if (isset($json_response->result->token) && ($json_response->success == true)) {
						return $json_response->result->token;
					} else {
						return false;
					}
				} else {
					return false;
				}
			} catch (RequestException $e) {
				// Handle exception or return false
				return false;
			}
		} else {
			return false;
		}
	}

	public function cloudflare_stream_get_signed_video_thumbnail_url($video_hash, $expiry_time = 86400, $nbf_time = 0, $width = 0, $height = 0) {
		// Get video details from database
		$video_details = $this->cloudflare_stream_get_video_details_from_db($video_hash);

		// Check if video details were successfully retrieved, and if the video is ready to stream and the video is accessible
		if ($video_details !== false && $video_details['video_ready_to_stream'] == true && $video_details['video_accessible_flag'] == true) {
			$video_details = json_decode($video_details['video_cloudflare_details'], true);

			// Get thumbnail URL from $video_details
			$thumbnail_url = $video_details['result']['thumbnail'];

			// If the thumbnail URL is not empty AND appears to be a valid URL, get the signed token
			if ($thumbnail_url !== '' && filter_var($thumbnail_url, FILTER_VALIDATE_URL)) {
				$signed_thumbnail_token = $this->cloudflare_stream_get_signed_video_token_local($video_hash, $expiry_time, $nbf_time);

				// If the signed thumbnail token is not empty, replace all instances of $video_hash with $signed_thumbnail_token
				if ($signed_thumbnail_token !== false) {
					$signed_thumbnail_url = str_replace($video_hash, $signed_thumbnail_token, $thumbnail_url);

					// If width and height are provided, append them to the signed thumbnail URL
					if ($width > 0 || $height > 0) {
						// Get query string from the signed thumbnail URL if it exists
						$query_string = parse_url($signed_thumbnail_url, PHP_URL_QUERY);
						if ($query_string !== null) {
							$signed_thumbnail_url .= '&width=' . $width . '&height=' . $height;
						} else {
							$signed_thumbnail_url .= '?width=' . $width . '&height=' . $height;
						}
					}
					return $signed_thumbnail_url;
				}
			}
		}

		// Default return false
		return false;
	}
}
