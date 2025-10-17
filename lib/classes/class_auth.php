<?php

$autoloadPath = realpath(__DIR__ . '/../vendor/autoload.php');

if (file_exists($autoloadPath)) {
	require_once($autoloadPath);
} else {
	die('Autoload file not found');
}

// Require main class
require_once(realpath(__DIR__ . '/class_main.php'));

// Require from autoload
use Mailgun\Mailgun;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/* 	CREATE TABLE `users` (
		`id` int(11) NOT NULL AUTO_INCREMENT,
		`created_timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
		`user_hash` varchar(255) NOT NULL,
		`user_name_first` varchar(255) NOT NULL,
		`user_name_last` varchar(255) DEFAULT NULL,
		`user_email` varchar(255) NOT NULL,
		`user_handle` varchar(128) DEFAULT NULL,
		`user_gender` varchar(20) DEFAULT NULL COMMENT 'Gender (i.e male / female)',
		`user_password_hash` varchar(512) NOT NULL,
		`user_profile_pic_hash` varchar(255) DEFAULT NULL,
		`user_is_admin` tinyint(1) NOT NULL DEFAULT 0,
		`user_is_content_creator` tinyint(1) NOT NULL DEFAULT 0,
		`user_bio_caption` varchar(2048) DEFAULT NULL,
		`user_permissions` longtext DEFAULT NULL,
		`user_params` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'User params (eg subscription tiers, VIP levels, close friends, blocklist etc)' CHECK (json_valid(`user_params`)),
		PRIMARY KEY (`id`)
	) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
*/

class class_auth extends class_main {
	public function __construct() {
		// Call parent constructor
		parent::__construct();
	}
	
	public function __destruct() {
		// Call parent destructor
		parent::__destruct();
	}

	/**
	 * JWT Helper Function: Generate a JWT token for the user
	 * 
	 * @param string $user_hash The user hash to use for the JWT token, defaults to 'unset'
	 * @param int $expiration_time The expiration time of the JWT token in seconds, defaults to 3600 (1 hour)
	 * @param int $nbf The not before time of the JWT token in seconds, defaults to 3000ms (3 seconds)
	 * @param array $additional_payload Additional payload to include in the JWT token, defaults to an empty array
	 * 
	 * @return string The JWT token
	 */
	public function generate_jwt_token($user_hash = null, $expiration_time = 3600, $nbf = 3, $additional_payload = array()) {
		if ($user_hash == null) {
			$user_hash = isset($_SESSION['user_hash']) ? $_SESSION['user_hash'] : 'unset';
		}

		$payload = array(
			'user_hash' => $user_hash,
			'iat' => time(),
			'exp' => time() + $expiration_time,
			'nbf' => time() + $nbf
		);

		// Merge additional payload with the default payload
		$payload = array_merge($payload, $additional_payload);

		return JWT::encode($payload, JWT_SECRET, 'HS512');
	}

	/**
	 * JWT Helper Function: Decode a JWT token
	 * 
	 * @param string $jwt_token The JWT token to decode
	 * @return array The decoded JWT token
	 */
	public function decode_jwt_token($jwt_token) {
		return JWT::decode($jwt_token, new Key(JWT_SECRET, 'HS512'));
	}

	/**
	 * JWT Helper Function: Verify a JWT token
	 * 
	 * @param string $jwt_token The JWT token to verify. If null, the JWT token from the request headers will be used.
	 * @param string $expected_user_hash The expected user hash to verify against. If null, the SESSION user hash will be used.
	 * @return bool True if the token is valid, false otherwise
	 */
	public function verify_jwt_token($jwt_token = null, $expected_user_hash = null) {
		if ($jwt_token == null) {
			if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
				$jwt_token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
			}
		}

		// If the JWT token is still null, return false
		if ($jwt_token == null) {
			return false;
		}

		// If the expected user hash is null, use the SESSION user hash
		if ($expected_user_hash == null) {
			$expected_user_hash = $_SESSION['user_hash'];
		}

		try {
			$JWT_decoded = (array) JWT::decode($jwt_token, new Key(JWT_SECRET, 'HS512'));

			// Check if the token is expired
			if ($JWT_decoded['exp'] < time()) {
				$this->add_error("JWT token expired", 'warning');
				return false;
			}

			// Check if the token is not before the current time
			if ($JWT_decoded['nbf'] > time()) {
				$this->add_error("JWT token not before the current time", 'warning');
				return false;
			}

			// Check that the token init time is before the current time AND the nbf time
			if ($JWT_decoded['iat'] > time() || $JWT_decoded['nbf'] > time()) {
				$this->add_error("JWT token not before the current time", 'warning');
				return false;
			}

			// If an expected user hash is provided, check if it matches the decoded user hash
			if ($expected_user_hash) {
				if ($JWT_decoded['user_hash'] !== $expected_user_hash) {
					$this->add_error("JWT token user hash does not match expected user hash", 'warning');
					return false;
				}
			} else {
				// If no expected user hash is provided, ensure it is 'unset'
				if ($JWT_decoded['user_hash'] !== 'unset') {
					$this->add_error("JWT token user hash is not 'unset'", 'warning');
					return false;
				}
			}

			return true;
		} catch (Exception $e) {
			$this->add_error("JWT token verification failed: " . $e->getMessage(), 'warning');
			return false;
		}
	}

	/**
	 * Generate a random user handle string.
	 *
	 * This method generates a random user handle string. If a seed is provided, it uses that seed to generate the handle.
	 * Otherwise, it generates a random seed. The method uses the Guzzle HTTP client to fetch a random user handle from
	 * the Random User API. If the API request fails, it falls back to generating a handle using a hash function.
	 *
	 * @param int|null $seed Optional. A seed value for generating the user handle. If not provided, a random seed is used.
	 * @return string The generated user handle.
	 * @throws \GuzzleHttp\Exception\ClientException If the API request fails.
	 */
	public function generate_user_handle($seed = null) {

		if ($seed == null) {
			// Random seed
			$seed = mt_rand();
		}

		// Use Guzzle to get random user handle from API https://randomuser.me/api/?inc=login&seed=41d4d79b5273e6658bc5bd9f757f5060&noinfo
		$client = new \GuzzleHttp\Client();
		try {
			$res = $client->request('GET', 'https://randomuser.me/api/?inc=login&seed=' . $seed . '&noinfo');
			$body = json_decode($res->getBody());

			$user_handle = $body->results[0]->login->username;

			// Check if user handle already exists
			if ($this->check_user_handle_exists($user_handle)) {
				// If it does, generate a new handle
				$user_handle = $this->generate_user_handle($seed . $seed);
			}

			return $user_handle;
		} catch (\GuzzleHttp\Exception\ClientException $e) {
			$this->add_error("Failed to fetch user handle from API: " . $e->getMessage(), 'error');
			// Fallback to generating a handle using a hash function
			$user_handle = substr(hash('xxh128', uniqid(mt_rand(), true)), 0, 8);
		}
		return $user_handle;
	}

	/**
	 * Checks if a user handle already exists in the database.
	 *
	 * This function queries the database to determine if a user handle is already in use.
	 *
	 * @param string $user_handle The user handle to check.
	 * @return bool Returns true if the user handle exists, false otherwise.
	 */
	public function check_user_handle_exists($user_handle) {
		$query = "SELECT * FROM users WHERE user_handle = :userhandle";
		$query_params = array(':userhandle' => $user_handle);
		$stmt = $this->db->prepare($query);
		$stmt->execute($query_params);
		return $stmt->rowCount() > 0;
	}

	/**
	 * Checks if a user email already exists in the database.
	 *
	 * This function queries the database to determine if an email address is already in use.
	 *
	 * @param string $user_email The email address to check.
	 * @return bool Returns true if the email address exists, false otherwise.
	 */
	public function check_user_email_exists($user_email) {
		$query = "SELECT * FROM users WHERE user_email = :useremail";
		$query_params = array(':useremail' => $user_email);
		$stmt = $this->db->prepare($query);
		$stmt->execute($query_params);
		return $stmt->rowCount() > 0;
	}


	/**
	 * Handles the creation of an unverified user account.
	 *
	 * This function processes the registration request by validating the input fields,
	 * checking for existing email addresses in the database, and inserting a new record
	 * into the `users_unverified` table. Upon successful registration, a verification
	 * email is sent to the user.
	 *
	 * Expected POST Parameters:
	 * - form_user_register_first_name: The first name of the user. Must not be empty.
	 * - form_user_register_last_name: The last name of the user. Must not be empty.
	 * - form_user_register_email: The email address of the user. Must be a valid email format and not exceed 128 characters.
	 * - form_user_register_password: The password for the user account. Must be at least 8 characters long.
	 * - form_user_register_password_confirm: The confirmation of the password. Must match the password.
	 * - fp_hash (optional): A fingerprint hash for additional verification.
	 *
	 * @return bool Returns true if the registration is successful and a verification email is sent, false otherwise.
	 */
	public function user_create_request() {
		// Validate input fields
		if (empty($_POST['form_user_register_first_name'])) {
			$this->add_error("Empty First Name", 'warning');
		} elseif (empty($_POST['form_user_register_last_name'])) {
			$this->add_error("Empty Last Name", 'warning');
		} elseif (empty($_POST['form_user_register_password'])) {
			$this->add_error("Empty Password", 'warning');
		} elseif ($_POST['form_user_register_password'] !== $_POST['form_user_register_password_confirm']) {
			$this->add_error("Password and password repeat are not the same", 'warning');
		} elseif (strlen($_POST['form_user_register_password']) < 8) {
			$this->add_error("Password has a minimum length of 8 characters", 'warning');
		} elseif (empty($_POST['form_user_register_email'])) {
			$this->add_error("Email cannot be empty", 'warning');
		} elseif (strlen($_POST['form_user_register_email']) > 128) {
			$this->add_error("Email cannot be longer than 128 characters", 'warning');
		} elseif (!filter_var($_POST['form_user_register_email'], FILTER_VALIDATE_EMAIL)) {
			$this->add_error("Your email address is not in a valid email format", 'warning');
		} else {
			// Check if email already exists in the database
			if ($this->check_user_email_exists($_POST['form_user_register_email'])) {
				$this->add_error("This email is already in use", 'info');
				return false;
			}

			// Insert new unverified user record
			$query = "INSERT INTO users_unverified(
							user_unverified_hash,
							user_unverified_fp_hash,
							user_unverified_name_first,
							user_unverified_name_last,
							user_unverified_email,
							user_unverified_password_hash
						) VALUES (
							:userunverifiedhash, 
							:userunverifiedfphash,
							:userunverifiednamefirst, 
							:userunverifiednamelast, 
							:userunverifiedemail, 
							:userunverifiedpasswordhash
						)";

			$user_hash = hash('xxh128', $_POST['form_user_register_first_name'] . $_POST['form_user_register_last_name'] . $_POST['form_user_register_email'] . $_POST['form_user_register_password']);
			$user_password_hash = password_hash($_POST['form_user_register_password'], PASSWORD_DEFAULT);

			$query_params = array(
				':userunverifiedhash'         => $user_hash,
				':userunverifiedfphash'       => isset($_POST['fp_hash']) ? $_POST['fp_hash'] : 'unset',
				':userunverifiednamefirst'    => $_POST['form_user_register_first_name'],
				':userunverifiednamelast'     => $_POST['form_user_register_last_name'],
				':userunverifiedemail'        => $_POST['form_user_register_email'],
				':userunverifiedpasswordhash' => $user_password_hash
			);

			try {
				$stmt = $this->db->prepare($query);
				$result = $stmt->execute($query_params);
			} catch (PDOException $ex) {
				$this->add_error("Failed to run query: " . $ex->getMessage(), 'error');
				return false;
			}

			$this->add_msg("If the email address you entered is correct, you will receive an email shortly with a link to verify your account. Please check your spam folder if you do not see it in your inbox.");

			// Send verification email
			$email_to = $_POST['form_user_register_email'];
			$email_subject = "Verify your Account";
			$email_txt = wordwrap("Click this link to verify your Account: https://" . $_SERVER['HTTP_HOST'] . "/verify_email?verify_email=" . $user_hash, 70);

			// Send verification email via mailgun
			$mg = Mailgun::create(MAILGUN_API_KEY);

			try {
				$mg->messages()->send('mail.clovers.bet', [
					'from'    => 'noreply@mail.clovers.bet',
					'to'      => $email_to,
					'subject' => $email_subject,
					'text'    => $email_txt,
				]);
			} catch (\Exception $e) {
				$this->add_error("Failed to send verification email: " . $e->getMessage(), 'error');
				return false;
			}

			return true;
		}

		$this->add_error("An unknown error occurred.", 'error');
		return false;
	}

	/**
	 * Verifies the user's email and password to ensure they are valid, and then creates the account.
	 * User should only be created when email has been verified with a unique link. Until then, nothing should be created.
	 *
	 * This function performs the following steps:
	 * 1. Checks if the email verification hash is set in the POST request.
	 * 2. Checks if a user is already registered with the given hash.
	 * 3. Verifies the hash and password combination by fetching the row from the unverified users table.
	 * 4. Checks if the email verification link is still valid (within one hour).
	 * 5. Creates a new user in the users table if the verification is successful.
	 *
	 * Expected POST Parameters:
	 * - form_emailVerify_hash: The unique hash sent to the user's email for verification.
	 * - form_emailVerify_password: The password associated with the user's account.
	 *
	 * @return bool Returns true if the user is successfully verified and created, false otherwise.
	 */
	public function user_email_verify() {

		if (!isset($_POST['form_emailVerify_hash'])) {
			$this->add_error("An unknown error occurred.", 'error');
			return false;
		}

		// Check if user is already registered by the hash
		$query = "SELECT * FROM users WHERE user_hash = :userhash";
		$query_params = array(':userhash' => $_POST['form_emailVerify_hash']);
		try {
			$stmt = $this->db->prepare($query);
			$result = $stmt->execute($query_params);
		} catch (PDOException $ex) {
			$this->add_error("Failed to run query: " . $ex->getMessage(), 'error');
			return false;
		}

		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if ($row) {
			$this->add_error("User already exists. Please login.", 'info');
			return false;
		}

		// Verify hash and password combination matches
		$query = "SELECT * FROM users_unverified WHERE user_unverified_hash = :userunverifiedhash ORDER BY created_timestamp DESC LIMIT 1";
		$query_params = array(':userunverifiedhash' => $_POST['form_emailVerify_hash']);
		try {
			$stmt = $this->db->prepare($query);
			$result = $stmt->execute($query_params);
		} catch (PDOException $ex) {
			$this->add_error("Failed to run query: " . $ex->getMessage(), 'error');
			return false;
		}

		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if ($row && password_verify($_POST['form_emailVerify_password'], $row['user_unverified_password_hash'])) {
			$time_userCreation = strtotime($row['created_timestamp']);
			$time_current = time();

			if ($time_current - $time_userCreation < 3600) {
				// Email link valid. Now let's create the user in the actual users database
				$user_handle_rand = $this->generate_user_handle($row['user_unverified_hash']);
				$query = "INSERT INTO users(
							user_hash,
							user_handle,
							user_name_first,
							user_name_last,
							user_email,
							user_password_hash
						) VALUES (
							:userhash, 
							:userhandle,
							:usernamefirst, 
							:usernamelast, 
							:useremail, 
							:userpasswordhash
						)";

				$query_params = array(
					':userhash'         => $row['user_unverified_hash'],
					':userhandle'       => $user_handle_rand,
					':usernamefirst'    => $row['user_unverified_name_first'],
					':usernamelast'     => $row['user_unverified_name_last'],
					':useremail'        => $row['user_unverified_email'],
					':userpasswordhash' => $row['user_unverified_password_hash']
				);

				try {
					$stmt = $this->db->prepare($query);
					$result = $stmt->execute($query_params);
				} catch (PDOException $ex) {
					$this->add_error("Failed to run query: " . $ex->getMessage(), 'error');
					return false;
				}

				$this->add_msg("User Verified! You may now login.");
				return true;
			}
		} else {
			$this->add_error("Password incorrect. Please try again.", 'warning');
		}

		return false;
	}


	/**
	 * Handles the login process using POST data.
	 * 
	 * This function checks if the user-provided login form data is valid. If valid, it queries the database
	 * to verify the user's credentials. If the credentials are correct, it populates the user's details
	 * into the session.
	 * 
	 * Expected POST Parameters:
	 * - form_login_username: The username or email of the user attempting to log in.
	 * - form_login_password: The password associated with the username or email.
	 * 
	 * @return bool Returns true if login is successful, otherwise false.
	 * 
	 * @throws PDOException If there is an error executing the database query.
	 */
	public function doLoginWithPOSTData() {
		// Function that checks if user is valid. Then populates user details in _SESSION

		// check login form contents
		if (empty($_POST['form_login_username'])) {
			$this->add_error("Username field was empty.", 'warning');
		} elseif (empty($_POST['form_login_password'])) {
			$this->add_error("Password field was empty.", 'warning');
		} elseif (!empty($_POST['form_login_username']) && !empty($_POST['form_login_password'])) {

			$query = "SELECT
						user_hash,
						user_name_first,
						user_name_last,
						user_email,
						user_password_hash,
						user_is_admin
					FROM users
					WHERE user_email = :useremail
					";

			// The parameter values
			$query_params = array(
				':useremail' => $_POST['form_login_username'],
			);
			try {
				// Execute the query against the database
				$stmt = $this->db->prepare($query);
				$result = $stmt->execute($query_params);
			} catch (PDOException $ex) {
				$this->add_error("Failed to run query: " . $ex->getMessage(), 'error');
				return false;
			}

			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			if ($row) {
				if (password_verify($_POST['form_login_password'], $row['user_password_hash'])) {

					//session_regenerate_id(true);
					// write user data into PHP SESSION (a file on your server)
					$_SESSION['user_hash'] = $row['user_hash'];
					$_SESSION['user_name_first'] = $row['user_name_first'];
					$_SESSION['user_name_last'] = $row['user_name_last'];
					$_SESSION['user_email'] = $row['user_email'];
					$_SESSION['user_loginStatus'] = 1;


					$this->add_msg("Logged in successfully!");
					return true;
				} else {
					$this->add_error("Wrong login credentials. Please try again. pw", 'warning');
				}
			} else {
				$this->add_error("Wrong login credentials. Please try again. user", 'warning');
			}
		}

		// default return
		return false;
	}

	/**
	 * Checks if the user is logged in.
	 *
	 * This function verifies if the user is logged in by checking the session variable
	 * 'user_loginStatus'. If the session variable is set and equals 1, the user is considered
	 * logged in.
	 *
	 * @return bool Returns true if the user is logged in, false otherwise.
	 */
	public function isUserLoggedIn() {
		if (isset($_SESSION['user_loginStatus']) && $_SESSION['user_loginStatus'] == 1) {
			return true;
		}
		// default return
		return false;
	}

	/**
	 * Logs out the current user by destroying the session and providing feedback messages.
	 *
	 * This method performs the following actions:
	 * - Clears the current session data.
	 * - Destroys the session.
	 * - Adds a feedback message indicating the user has been logged out.
	 * - Starts a new session if the previous session was destroyed.
	 * - Sets a disclaimer acceptance flag in the new session.
	 * - Transfers any messages from the current object to the session.
	 * - Transfers any errors from the current object to the session.
	 *
	 * @return bool Always returns true.
	 */
	public function doLogout() { //

		// delete the session of the user
		$_SESSION = array();
		session_destroy();
		// return a little feeedback message
		$this->add_msg("You have been logged out.");

		if (session_status() == PHP_SESSION_NONE) {
			// session has not started
			session_start();
			$_SESSION['disclaimer_accepted'] = true;
		}


		if (isset($this->messages)) {
			//$_SESSION[ 'messages' ][] = $this->messages;
			foreach ($this->messages as $message) {
				$_SESSION['messages'][] = $message;
			}
		}
		if (isset($this->errors)) {
			foreach ($this->errors as $error) {
				$_SESSION['errors'][] = $error;
			}
		}

		return true;
	}
}
