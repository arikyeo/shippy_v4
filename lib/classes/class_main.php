<?php

require_once(__DIR__ . '/env.php');

// Sanitise input data
$_GET = filter_var_array($_GET);
$_POST = filter_var_array($_POST);

$autoloadPath = realpath(__DIR__ . '/../vendor/autoload.php');

if (file_exists($autoloadPath)) {
	require_once($autoloadPath);
} else {
	die('Autoload file not found');
}

class class_main {

	/*  BEGIN PARAMS */
	/**
	 * @var object The database connection
	 */
	public $db = null;
	/**
	 * @var array Collection of error messages
	 */
	public $errors = array();
	/**
	 * @var array Collection of success / neutral messages
	 */
	public $messages = array();

	private $brevo_api_key = BREVO_API_KEY;

	/*  END PARAMS */


	// 	CREATE TABLE `users` (
	//  `id` int(11) NOT NULL AUTO_INCREMENT,
	//  `created_timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
	//  `user_hash` varchar(255) NOT NULL,
	//  `user_name_first` varchar(255) NOT NULL,
	//  `user_name_last` varchar(255) DEFAULT NULL,
	//  `user_email` varchar(255) NOT NULL,
	//  `user_handle` varchar(128) DEFAULT NULL,
	//  `user_password_hash` varchar(512) NOT NULL,
	//  `user_profile_pic_hash` varchar(255) DEFAULT NULL,
	//  `user_is_admin` tinyint(1) NOT NULL DEFAULT 0,
	//  `user_is_content_creator` tinyint(1) NOT NULL DEFAULT 0,
	//  `user_bio_caption` varchar(2048) DEFAULT NULL,
	//  `user_permissions` longtext DEFAULT NULL,
	//  `user_params` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'User params (eg subscription tiers, VIP levels, close friends, blocklist etc)' CHECK (json_valid(`user_params`)),
	//  PRIMARY KEY (`id`)
	// ) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci

	// List of current known user_permissions
	// 	- user_permissions: {
	// 		"permission_flag_can_post": true,
	// 		"permission_flag_can_comment": true,
	// 		"permission_flag_can_tip": true,
	// 		"permission_flag_can_view": true,
	// 		...
	// 	}


	public function __construct() {

		$cred_db_host = DB_HOST;
		$cred_db_user = DB_USER;
		$cred_db_passwd = DB_PASSWORD;
		$cred_db_dbname = DB_NAME;

		// Start a new session
		if (session_status() == PHP_SESSION_NONE) {
			// session has not started
			session_start();
		}

		// Set default timezone to match server timezone
		//		date_default_timezone_set('America/New_York');

		$db_options = array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8');
		try {
			$this->db = new PDO("mysql:host={$cred_db_host};dbname={$cred_db_dbname};charset=utf8", $cred_db_user, $cred_db_passwd, $db_options);
		} catch (PDOException $ex) {
			die("Failed to connect to the database: " . $ex->getMessage());
		}

		// DEBUG
		$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		// Set timezone of MYSQL to UTC to match the rest of the server
		$this->db->exec('SET @@session.time_zone="+00:00";');

		// var_dump($_SESSION); die();
	}

	public function __destruct() {
		// Set messages and errors to session

		// if ( isset( $this->messages ) ) {
		//     $_SESSION[ 'messages' ] = $this->messages;
		// }
		// if ( isset( $this->errors ) ) {
		//     $_SESSION[ 'errors' ] = $this->errors;
		// }

		// Close database connection
		$this->db = null;
	}

	public function get_messages_from_session() {
		// Retrieve cached messages/errors from previous pages
		if (isset($_SESSION['messages']) && is_array($_SESSION['messages'])) {
			foreach ($_SESSION['messages'] as $message) {
				if (!in_array($message, $this->messages)) {
					$this->messages[] = $message;
				}
			}
			$_SESSION['messages'] = array();
		} else {
			$_SESSION['messages'] = array();
		}
		return $this->messages;
	}

	public function get_errors_from_session() {
		if (isset($_SESSION['errors']) && is_array($_SESSION['errors'])) {
			foreach ($_SESSION['errors'] as $error) {
				if (!in_array($error, $this->errors)) {
					$this->errors[] = $error;
				}
			}
			$_SESSION['errors'] = array();
		} else {
			$_SESSION['errors'] = array();
		}
		return $this->errors;
	}

	// Error Logging related functions
	/* CREATE TABLE `log_errors` (
		`id` int(11) NOT NULL AUTO_INCREMENT,
		`log_level` varchar(64) DEFAULT 'info' COMMENT 'Log level, ie debug/info/warning/error',
		`log_uuid` varchar(128) DEFAULT NULL,
		`log_message` text DEFAULT NULL,
		`requestURI` text NOT NULL,
		`script_path` text DEFAULT NULL COMMENT 'Path to the currently executing script',
		`protocol` varchar(16) DEFAULT NULL COMMENT 'Protocol of request, eg GET/POST/PUT etc',
		`headers` longtext CHARACTER SET utf8mb4 COLLATE=utf8mb4_bin DEFAULT NULL CHECK (json_valid(`headers`)),
		`params_GET` longtext CHARACTER SET utf8mb4 COLLATE=utf8mb4_bin DEFAULT NULL CHECK (json_valid(`headers`)),
		`params_POST` longtext CHARACTER SET utf8mb4 COLLATE=utf8mb4_bin DEFAULT NULL CHECK (json_valid(`headers`)),
		`fp_requestID` varchar(255) DEFAULT NULL COMMENT 'Fingerprint request ID',
		`fp_visitorID` varchar(255) DEFAULT NULL COMMENT 'Fingerprint visitor ID',
		`timestamp` datetime NOT NULL DEFAULT current_timestamp(),
		`user_hash` varchar(255) DEFAULT NULL COMMENT 'User hash (if any)',
		PRIMARY KEY (`id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
	*/
	public function add_error($msg, $log_level = 'info') {
		if (isset($msg) && !empty($msg)) {
			$this->errors[] = $msg;
			$_SESSION['errors'][] = $msg;

			// Censor password-related parameters if log_level is not 'debug'
			$params_GET = $_GET;
			$params_POST = $_POST;
			if ($log_level !== 'debug' || DEBUG_MODE !== true) {
				foreach ($params_GET as $key => $value) {
					if (preg_match('/password|pwd|passwd/i', $key)) {
						$params_GET[$key] = 'REDACTED';
					}
				}
				foreach ($params_POST as $key => $value) {
					if (preg_match('/password|pwd|passwd/i', $key)) {
						$params_POST[$key] = 'REDACTED';
					}
				}
			}

			// Prepare to insert the error into the database
			$query = "INSERT INTO log_errors (log_level, log_message, requestURI, script_path, protocol, headers, params_GET, params_POST, fp_requestID, fp_visitorID, user_hash) 
					  VALUES (:log_level, :log_message, :requestURI, :script_path, :protocol, :headers, :params_GET, :params_POST, :fp_requestID, :fp_visitorID, :user_hash)";

			$query_params = array(
				':log_level' => $log_level,
				':log_message' => $msg,
				':requestURI' => $_SERVER['REQUEST_URI'] ?? '',
				':script_path' => $_SERVER['SCRIPT_FILENAME'] ?? '',
				':protocol' => $_SERVER['REQUEST_METHOD'] ?? '',
				':headers' => json_encode(getallheaders()),
				':params_GET' => json_encode($params_GET),
				':params_POST' => json_encode($params_POST),
				':fp_requestID' => $_SERVER['HTTP_X_FP_REQID'] ?? null,
				':fp_visitorID' => $_SERVER['HTTP_X_FP_VISITORID'] ?? null,
				':user_hash' => $_SESSION['user_hash'] ?? null
			);

			try {
				$stmt = $this->db->prepare($query);
				$stmt->execute($query_params);
			} catch (PDOException $ex) {
				// Handle exception if needed
				die("Failed to log error: " . $ex->getMessage());
			}

			return true;
		} else {
			return false;
		}
	}

	public function add_msg($msg) {
		if (isset($msg) && !empty($msg)) {
			$this->messages[] = $msg;
			$_SESSION['messages'][] = $msg;

			// Log the message into the database under 'info' level
			$query = "INSERT INTO log_errors (log_level, log_message, requestURI, script_path, protocol, headers, params_GET, params_POST, fp_requestID, fp_visitorID, user_hash) 
					  VALUES (:log_level, :log_message, :requestURI, :script_path, :protocol, :headers, :params_GET, :params_POST, :fp_requestID, :fp_visitorID, :user_hash)";

			$query_params = array(
				':log_level' => 'info',
				':log_message' => $msg,
				':requestURI' => $_SERVER['REQUEST_URI'] ?? '',
				':script_path' => $_SERVER['SCRIPT_FILENAME'] ?? '',
				':protocol' => $_SERVER['REQUEST_METHOD'] ?? '',
				':headers' => json_encode(getallheaders()),
				':params_GET' => json_encode($_GET),
				':params_POST' => json_encode($_POST),
				':fp_requestID' => $_SERVER['HTTP_X_FP_REQID'] ?? null,
				':fp_visitorID' => $_SERVER['HTTP_X_FP_VISITORID'] ?? null,
				':user_hash' => $_SESSION['user_hash'] ?? null
			);

			try {
				$stmt = $this->db->prepare($query);
				$stmt->execute($query_params);
			} catch (PDOException $ex) {
				// Handle exception if needed
				die("Failed to log message: " . $ex->getMessage());
			}

			return true;
		} else {
			return false;
		}
	}


	// Access Log related functions
	/* 	CREATE TABLE `log_access` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`log_uuid` varchar(128) DEFAULT NULL,
			`requestURI` text NOT NULL,
			`protocol` varchar(16) DEFAULT NULL COMMENT 'Protocol of request, eg GET/POST/PUT etc',
			`headers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`headers`)),
			`params_GET` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
			`params_POST` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
			`fp_requestID` varchar(255) DEFAULT NULL COMMENT 'Fingerprint request ID',
			`fp_visitorID` varchar(255) DEFAULT NULL COMMENT 'Fingerprint visitor ID',
			`timestamp` datetime NOT NULL DEFAULT current_timestamp(),
			`user_hash` varchar(255) DEFAULT NULL COMMENT 'User hash (if any)',
			PRIMARY KEY (`id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
	*/
	
	public function insert_access_log_entry() {
		// Log the access into the database
		$query = "INSERT INTO log_access (requestURI, protocol, headers, params_GET, params_POST, fp_requestID, fp_visitorID, user_hash) 
					VALUES (:requestURI, :protocol, :headers, :params_GET, :params_POST, :fp_requestID, :fp_visitorID, :user_hash)";

		parse_str($_SERVER['QUERY_STRING'],$output);
		$query_params = array(
			':requestURI' => $_SERVER['REQUEST_URI'] ?? '',
			':protocol' => $_SERVER['REQUEST_METHOD'] ?? '',
			':headers' => json_encode(getallheaders()),
			':params_GET' => json_encode($output) ?? null,
			':params_POST' => json_encode($_POST) ?? null,
			':fp_requestID' => $_SERVER['HTTP_X_FP_REQID'] ?? null,
			':fp_visitorID' => $_SERVER['HTTP_X_FP_VISITORID'] ?? null,
			':user_hash' => $_SESSION['user_hash'] ?? null
		);

		try {
			$stmt = $this->db->prepare($query);
			$stmt->execute($query_params);
		} catch (PDOException $ex) {
			die("Failed to log access: " . $ex->getMessage());
		}
		if ($stmt->rowCount() > 0) {
			return true;
		} else {
			return false;
		}
	}


	/**
	 * Retrieves the real IP address of the user.
	 *
	 * This method checks various server variables to determine the user's IP address.
	 * It first checks for shared internet/ISP IPs, then for IPs passed from proxies,
	 * and finally defaults to the remote address. It also validates the IP address
	 * to ensure it is a valid public IP.
	 *
	 * @return string The real IP address of the user, or 'UNKNOWN' if it cannot be determined.
	 */
	public function getRealIpAddr() {
		$ip = '';

		// Check for shared internet/ISP IP
		if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		}
		// Check for IPs passed from proxies
		elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			// Sometimes HTTP_X_FORWARDED_FOR can contain multiple IP addresses
			$ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
			foreach ($ips as $ip) {
				$ip = trim($ip);
				// Validate IP address
				if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
					return $ip;
				}
			}
		}
		// Default to REMOTE_ADDR
		elseif (!empty($_SERVER['REMOTE_ADDR'])) {
			$ip = $_SERVER['REMOTE_ADDR'];
		}

		// Validate the final IP address
		if (filter_var($ip, FILTER_VALIDATE_IP)) {
			return $ip;
		}

		return 'UNKNOWN';
	}


	/**
	 * Formats a number into a short form with a suffix (K, M, B, T) based on its size.
	 *
	 * @param float|int $n The number to format.
	 * @param int $precision The number of decimal places to include in the formatted number. Default is 1.
	 * @return string The formatted number with an appropriate suffix.
	 */
	public function number_format_short($n, $precision = 1) {
		if ($n < 900) {
			// 0 - 900
			$n_format = number_format($n, $precision);
			$suffix = '';
		} else {
			if ($n < 900000) {
				// 0.9k-850k
				$n_format = number_format($n / 1000, $precision);
				$suffix = 'K';
			} else {
				if ($n < 900000000) {
					// 0.9m-850m
					$n_format = number_format($n / 1000000, $precision);
					$suffix = 'M';
				} else {
					if ($n < 900000000000) {
						// 0.9b-850b
						$n_format = number_format($n / 1000000000, $precision);
						$suffix = 'B';
					} else {
						// 0.9t+
						$n_format = number_format($n / 1000000000000, $precision);
						$suffix = 'T';
					}
				}
			}
		}

		// Remove unecessary zeroes after decimal. "1.0" -> "1"; "1.00" -> "1"
		// Intentionally does not affect partials, eg "1.50" -> "1.50"
		if ($precision > 0) {
			$dotzero = '.' . str_repeat('0', $precision);
			$n_format = str_replace($dotzero, '', $n_format);
		}

		return $n_format . $suffix;
	}


	/**
	 * Formats a given timestamp into a human-readable elapsed time string.
	 *
	 * @param int $timestamp The timestamp to be formatted.
	 * @param bool $full Optional. Whether to return the full elapsed time string or just the largest unit. Default is false.
	 * @return string The formatted elapsed time string.
	 */
	public function time_elapsed_string($timestamp, $full = false) {
		$timenow = time();
		$diff = ($timenow > $timestamp) ? (int)$timenow - $timestamp : (int)$timestamp - $timenow;
		$over = ($timenow > $timestamp) ? 'ago' : 'left';

		$l = [
			'rel_justnow' => "Just now",
			'rel_ago' => "ago",
			'rel_left' => "left",
			'rel_less_than' => "Less than one minute",
			'rel_second_single' => "second",
			'rel_second_plural' => "seconds",
			'rel_minute_single' => "minute",
			'rel_minute_plural' => "minutes",
			'rel_day_single' => "day",
			'rel_day_plural' => "days",
			'rel_hour_single' => "hour",
			'rel_hour_plural' => "hours",
			'rel_month_single' => "month",
			'rel_month_plural' => "months",
			'rel_year_single' => "year",
			'rel_year_plural' => "years",
			'rel_format' => "{1} {2}"
		];

		$formatter = [
			'year' => 31104000,
			'month' => 2592000,
			'day' => 86400,
			'hour' => 3600,
			'minute' => 60,
			'second' => 1
		];

		$how_much = [
			'year' => 100,
			'month' => 12,
			'day' => 30,
			'hour' => 24,
			'minute' => 60,
			'second' => 60
		];

		$calc = [];
		$tostring = [];

		foreach ($formatter as $date => $overstamp) {
			$calc[$date] = floor($diff / $overstamp) % $how_much[$date];
			$sp = ($calc[$date] == 1) ? "single" : "plural";

			if ($calc[$date] == 0) {
				if ($date != 'second') {
					unset($calc[$date]);
				}
			} else {
				$tostring[$date] = $calc[$date] . " " . $l['rel_' . $date . '_' . $sp];
			}
			$reminder = $date;
		}

		if (count($calc) == 1 && $reminder == 'second') {
			if ($calc['second'] == 0) {
				$tostring[$date] = $l['rel_justnow'];
			} else {
				$tostring[$date] = $l['rel_less_than'];
			}
		} else {
			$tostring[$date] = str_replace(['{1}', '{2}'], [$calc[$date] . " " . $l['rel_' . $date . '_' . $sp], $l['rel_' . $over]], $l['rel_format']);
		}

		if ($full == false) {
			$slicedArray = array_slice($tostring, 0, 1);
			$firstElement = array_shift($slicedArray);
			$display = str_replace(['{1}', '{2}'], [$firstElement, $l['rel_' . $over]], $l['rel_format']);
		} else {
			$display = implode(", ", $tostring);
		}

		return $display;
	}
}
