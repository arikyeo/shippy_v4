<?php

$autoloadPath = realpath(__DIR__ . '/../vendor/autoload.php');

if (file_exists($autoloadPath)) {
	require_once($autoloadPath);
} else {
	die('Autoload file not found');
}

// Require main class
require_once(realpath(__DIR__ . '/class_main.php'));
// Require auth class
require_once(realpath(__DIR__ . '/class_auth.php'));
// Require media processing class
require_once(realpath(__DIR__ . '/class_media_processing.php'));

// Require from autoload
use Mailgun\Mailgun;

class class_user extends class_main {

	private $class_auth;
	private $class_media_processing;

	public function __construct() {
		parent::__construct();

		$this->class_auth = new class_auth();
		$this->class_media_processing = new class_media_processing();
	}

	public function __destruct() {
		parent::__destruct();
	}






	/**
	 * Fetches the profile data of a user from the database.
	 *
	 * @param string $user_hash The unique hash identifier of the user.
	 * @param bool $sanitise_for_public Optional. If set to true, sensitive data such as email, password hash, 
	 *                                  admin status, and content creator status will be excluded from the result. 
	 *                                  Default is false.
	 * @return array|false Returns an associative array containing the user's profile data if successful, 
	 *                     or false if the query fails or no data is found.
	 */
	public function fetch_user_profile_data($user_hash, $sanitise_for_public = false) {
		// Function that fetches user profile data from database
		$query = "SELECT
					user_hash,
					user_name_first,
					user_name_last,
					user_handle,
					user_profile_pic_hash,
					user_bio_caption";
		if ($sanitise_for_public == false) {
			$query .= ", 
					user_email,
					user_password_hash,
					user_is_admin";
		}
		$query .= " 
				FROM users
				WHERE user_hash = :userhash
				";

		// The parameter values
		$query_params = array(
			':userhash' => $user_hash,
		);
		try {
			// Execute the query against the database
			$stmt = $this->db->prepare($query);
			$result = $stmt->execute($query_params);
		} catch (PDOException $ex) {
			//die("Failed to run query. Check log for details.");
			// die( "Failed to run query: " . $ex->getMessage() );
			return false;
		}

		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if ($row) {
			// Update $_SESSION with user data if $user_hash = $_SESSION[ 'user_hash' ]
			if (isset($_SESSION['user_hash']) && $user_hash == $_SESSION['user_hash']) {
				$_SESSION['user_name_first'] = $row['user_name_first'] ?? null;
				$_SESSION['user_name_last'] = $row['user_name_last'] ?? null;
				$_SESSION['user_email'] = $row['user_email'] ?? null;
				$_SESSION['user_handle'] = $row['user_handle'] ?? null;
				$_SESSION['user_profile_pic_hash'] = $row['user_profile_pic_hash'] ?? null;
				$_SESSION['user_is_admin'] = $row['user_is_admin'] ?? null;
				$_SESSION['user_bio_caption'] = $row['user_bio_caption'] ?? null;
			}

			return $row;
		} else {
			return false;
		}
	}



	/**
	 * Fetches the user hash from the database based on the provided user handle.
	 *
	 * This function removes any leading '@' from the user handle if it exists,
	 * then queries the database to retrieve the corresponding user hash.
	 *
	 * @param string $user_handle The handle of the user whose hash is to be fetched.
	 * @return string|false The user hash if found, or false if the query fails or no matching user is found.
	 */
	public function get_user_hash_by_user_handle($user_handle) {
		// Function that fetches user hash from database

		// Remove any leading @ if it exists
		$user_handle = ltrim($user_handle, '@');

		$query = "SELECT
					user_hash
				FROM users
				WHERE user_handle = :userhandle
				";

		// The parameter values
		$query_params = array(
			':userhandle' => $user_handle,
		);
		try {
			// Execute the query against the database
			$stmt = $this->db->prepare($query);
			$result = $stmt->execute($query_params);
		} catch (PDOException $ex) {
			//die("Failed to run query. Check log for details.");
			// die( "Failed to run query: " . $ex->getMessage() );
			return false;
		}

		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if ($row) {
			return $row['user_hash'];
		} else {
			return false;
		}
	}



	/**
	 * Checks if a user hash exists in the database.
	 *
	 * This function executes a query to check if the provided user hash exists
	 * in the 'users' table of the database.
	 *
	 * @param string $user_hash The user hash to check in the database.
	 * @return bool Returns true if the user hash exists, false otherwise.
	 */
	public function check_user_hash_exists($user_hash) {

		$query = "SELECT
					user_hash
				FROM users
				WHERE user_hash = :userhash
				";

		// The parameter values
		$query_params = array(
			':userhash' => $user_hash,
		);
		try {
			// Execute the query against the database
			$stmt = $this->db->prepare($query);
			$result = $stmt->execute($query_params);
		} catch (PDOException $ex) {
			//die("Failed to run query. Check log for details.");
			// die( "Failed to run query: " . $ex->getMessage() );
			return false;
		}

		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if ($row) {
			return true;
		} else {
			return false;
		}
	}






		/**
	 * Retrieves the avatar image URL for a user based on their user hash.
	 *
	 * This function queries the database to fetch the user's profile picture hash.
	 * If the user has a profile picture, it processes the image to get the avatar variant.
	 * If the user does not have a profile picture or if there is an error during the query,
	 * it returns the URL of a default avatar image.
	 *
	 * @param string $user_hash The hash of the user whose avatar image URL is to be retrieved.
	 * @return string The URL of the user's avatar image or the default avatar image.
	 */
	public function get_avatar_image_url_by_user_hash($user_hash) {
		// Function that fetches user avatar from database

		$default_avatar_URL = '/assets/img/avatar_default.webp';

		$query = "SELECT
					user_profile_pic_hash
				FROM users
				WHERE user_hash = :userhash
				";

		// The parameter values
		$query_params = array(
			':userhash' => $user_hash,
		);
		try {
			// Execute the query against the database
			$stmt = $this->db->prepare($query);
			$result = $stmt->execute($query_params);
		} catch (PDOException $ex) {
			//die("Failed to run query. Check log for details.");
			// die( "Failed to run query: " . $ex->getMessage() );
			return $default_avatar_URL;
		}

		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if ($row && !empty($row['user_profile_pic_hash'])) {
			// return $row[ 'user_profile_pic_hash' ];
			require_once(__DIR__ . '/class_media_processing.php');
			$image_processing = new class_media_processing();
			return $image_processing->get_image_by_variant($row['user_profile_pic_hash'], 'avatarImage');
		} else {
			// return link to default avatar
			return $default_avatar_URL;
		}
	}



	// Custom User Permissions (user_permissions) related functions

	/**
	 * Fetches all user permissions from the database for a given user hash.
	 *
	 * @param string $user_hash The hash of the user whose permissions are to be fetched.
	 * @return array|false An associative array of user permissions if found, an empty array if no permissions are found, or false if the query fails.
	 */
	public function fetch_user_permissions_all($user_hash) {

		$query = "SELECT
					user_permissions
				FROM users
				WHERE user_hash = :userhash
				";

		// The parameter values
		$query_params = array(
			':userhash' => $user_hash,
		);
		try {
			// Execute the query against the database
			$stmt = $this->db->prepare($query);
			$result = $stmt->execute($query_params);
		} catch (PDOException $ex) {
			return false;
		}

		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if ($row) {
			return json_decode($row['user_permissions'], true);
		} else {
			return array();
		}
	}

	/**
	 * Fetches user permissions by a specific key from the database.
	 *
	 * This function retrieves user permissions stored in the database for a given user hash.
	 * It decodes the JSON-encoded user permissions and returns the value associated with the specified key.
	 *
	 * @param string $user_hash The unique hash identifying the user.
	 * @param string $key The key for which the permission value is to be fetched.
	 * @return mixed The value associated with the specified key, or 'not_found' if the key does not exist.
	 * @throws PDOException If there is an error executing the query.
	 */
	public function fetch_user_permissions_by_key($user_hash, $key) {

		$query = "SELECT
					user_permissions
				FROM users
				WHERE user_hash = :userhash
				";

		// The parameter values
		$query_params = array(
			':userhash' => $user_hash,
		);
		try {
			// Execute the query against the database
			$stmt = $this->db->prepare($query);
			$result = $stmt->execute($query_params);
		} catch (PDOException $ex) {
			return false;
		}

		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if ($row) {
			$user_permissions = json_decode($row['user_permissions'], true);
			if (isset($user_permissions[$key])) {
				return $user_permissions[$key];
			} else {
				return 'not_found';
			}
		} else {
			return 'not_found';
		}
	}

	/**
	 * Updates user permissions in the database by a specific key.
	 * If the key does not exist, it will be created.
	 *
	 * @param string $user_hash The unique hash identifier for the user.
	 * @param string $key The key of the user permission to update.
	 * @param mixed $value The value to set for the specified key.
	 * @return bool Returns true if the update was successful, false otherwise.
	 */
	public function update_user_permissions_by_key($user_hash, $key, $value) {

		// Fetch user permissions
		$user_permissions = $this->fetch_user_permissions_all($user_hash);

		// Update user permissions
		$user_permissions[$key] = $value;

		$query = "UPDATE users
				SET
					user_permissions = :userpermissions
				WHERE user_hash = :userhash
				";

		// The parameter values
		$query_params = array(
			':userpermissions' => json_encode($user_permissions),
			':userhash'        => $user_hash,
		);

		try {
			// Execute the query against the database
			$stmt = $this->db->prepare($query);
			$result = $stmt->execute($query_params);

			// Should have updated successfully
			return true;
		} catch (PDOException $ex) {
			return false;
		}
	}

	// Custom User Params (user_params) related functions
	/**
	 * Fetches all user parameters from the database for a given user hash.
	 *
	 * @param string $user_hash The hash of the user whose parameters are to be fetched.
	 * @return array|false An associative array of user parameters if found, an empty array if no parameters are found, or false if the query fails.
	 */
	public function fetch_user_params_all($user_hash) {

		$query = "SELECT
					user_params
				FROM users
				WHERE user_hash = :userhash
				";

		// The parameter values
		$query_params = array(
			':userhash' => $user_hash,
		);
		try {
			// Execute the query against the database
			$stmt = $this->db->prepare($query);
			$result = $stmt->execute($query_params);
		} catch (PDOException $ex) {
			return false;
		}

		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if ($row) {
			return json_decode($row['user_params'], true);
		} else {
			return array();
		}
	}

	/**
	 * Fetches user parameters by a specific key from the database.
	 *
	 * This function retrieves user parameters stored in the database for a given user hash.
	 * It decodes the JSON-encoded user parameters and returns the value associated with the specified key.
	 *
	 * @param string $user_hash The unique hash identifying the user.
	 * @param string $key The key for which the parameter value is to be fetched.
	 * @return mixed The value associated with the specified key, or 'not_found' if the key does not exist.
	 * @throws PDOException If there is an error executing the query.
	 */
	public function fetch_user_params_by_key($user_hash, $key) {

		$query = "SELECT
					user_params
				FROM users
				WHERE user_hash = :userhash
				";

		// The parameter values
		$query_params = array(
			':userhash' => $user_hash,
		);
		try {
			// Execute the query against the database
			$stmt = $this->db->prepare($query);
			$result = $stmt->execute($query_params);
		} catch (PDOException $ex) {
			return false;
		}

		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if ($row) {
			$user_params = json_decode($row['user_params'], true);
			if (isset($user_params[$key])) {
				return $user_params[$key];
			} else {
				return 'not_found';
			}
		} else {
			return 'not_found';
		}
	}


	/**
	 * Updates user parameters in the database by a specific key.
	 * If the key does not exist, it will be created.
	 *
	 * @param string $user_hash The unique hash identifier for the user.
	 * @param string $key The key of the user parameter to update.
	 * @param mixed $value The value to set for the specified key.
	 * @return bool Returns true if the update was successful, false otherwise.
	 */
	public function update_user_params_by_key($user_hash, $key, $value) {

		// Fetch user params
		$user_params = $this->fetch_user_params_all($user_hash);

		// Update user params
		$user_params[$key] = $value;

		$query = "UPDATE users
				SET
					user_params = :userparams
				WHERE user_hash = :userhash
				";

		// The parameter values
		$query_params = array(
			':userparams' => json_encode($user_params),
			':userhash'   => $user_hash,
		);

		try {
			// Execute the query against the database
			$stmt = $this->db->prepare($query);
			$result = $stmt->execute($query_params);

			// Should have updated successfully
			return true;
		} catch (PDOException $ex) {
			return false;
		}
	}







		/**
	 * Updates user profile information using POST data.
	 *
	 * This function modifies user profile details based on the provided POST parameters.
	 *
	 * Required POST Parameters:
	 * - form_edit_profile_user_handle: The user's username.
	 * - form_edit_profile_first_name: The user's first name.
	 * - form_edit_profile_last_name: The user's last name.
	 * - form_edit_profile_gender: The user's gender. Currently, only 'male' or 'female' are accepted.
	 * - form_edit_profile_bio_multiline: The user's biography.
	 * - form_edit_password_existing: The user's current password for verification.
	 * - form_edit_profile_interests_tags: An array of tags representing the user's interests.
	 * 
	 * Procedure:
	 * - Checks for the presence of necessary fields.
	 * - Fetches the user's current profile data from the database.
	 * - Confirms the provided password matches the stored password.
	 * - Updates the user's profile data in the database.
	 * - Returns true if the update is successful, otherwise returns false.
	 *
	 * @return bool True if the profile update is successful, false otherwise.
	 */
	public function user_edit_profile() {
		// Modify user profile data

		// Fetch current user profile data
		$user_profile_data = $this->fetch_user_profile_data($_SESSION['user_hash']);

		// Validate password
		// Password must be provided and must match the stored password
		if (empty($_POST['form_edit_password_existing'])) {
			$this->add_error("Password field was empty.", 'warning');
			return false;
		}

		if (!empty($_POST['form_edit_password_existing']) && !password_verify($_POST['form_edit_password_existing'], $user_profile_data['user_password_hash'])) {
			$this->add_error("Password incorrect. Please try again.", 'warning');
			return false;
		}

		// Prepare SQL query components
		$query_parts = [];
		$query_params = [':userhash' => $_SESSION['user_hash']];
		$result = false; // Initialize $result to avoid undefined variable warning

		if (!empty($_POST['form_edit_profile_user_handle'])) {
			// Remove leading @ if present
			$_POST['form_edit_profile_user_handle'] = ltrim($_POST['form_edit_profile_user_handle'], '@');

			// Verify if user handle is already in use
			$user_handle_exists = $this->class_auth->check_user_handle_exists($_POST['form_edit_profile_user_handle']);
			if ($user_handle_exists) {
				$this->add_error("User handle already exists. Please try a different one.", 'warning');
				return false;
			}

			$query_parts[] = "user_handle = :userhandle";
			$query_params[':userhandle'] = $_POST['form_edit_profile_user_handle'];
		}

		if (!empty($_POST['form_edit_profile_first_name'])) {
			$query_parts[] = "user_name_first = :userfirstname";
			$query_params[':userfirstname'] = $_POST['form_edit_profile_first_name'];
		}

		if (!empty($_POST['form_edit_profile_last_name'])) {
			$query_parts[] = "user_name_last = :userlastname";
			$query_params[':userlastname'] = $_POST['form_edit_profile_last_name'];
		}

		if (!empty($_POST['form_edit_profile_gender'])) {
			$query_parts[] = "user_gender = :usergender";
			// Currently, only 'male' or 'female' are accepted
			if ($_POST['form_edit_profile_gender'] == 'male' || $_POST['form_edit_profile_gender'] == 'female') {
				$query_params[':usergender'] = $_POST['form_edit_profile_gender'];
			} else {
				$this->add_error("Invalid gender", 'warning');
				return false;
			}
		}

		if (!empty($_POST['form_edit_profile_bio_multiline'])) {
			$query_parts[] = "user_bio_caption = :userbiocaption";
			$query_params[':userbiocaption'] = $_POST['form_edit_profile_bio_multiline'];
		}

		// Execute update if there are changes
		if (!empty($query_parts)) {
			$query = "UPDATE users SET " . implode(", ", $query_parts) . " WHERE user_hash = :userhash";

			try {
				// Execute the update query
				$stmt = $this->db->prepare($query);
				$result = $stmt->execute($query_params);
			} catch (PDOException $ex) {
				return false;
			}
		}

		// Update user parameters if any
		if (!empty($_POST['form_edit_profile_interests_tags'])) {
			// form_edit_profile_interests_tags is an array of tags

			// Sanitize all interest tags in the array
			$user_interests_tags_unfiltered = $_POST['form_edit_profile_interests_tags'];
			foreach ($user_interests_tags_unfiltered as $tag) {
				$user_interests_tags[] = filter_var($tag, FILTER_SANITIZE_SPECIAL_CHARS);
			}

			// Update user_interests_tags parameter
			$update_user_interests_tags_status = $this->update_user_params_by_key($_SESSION['user_hash'], 'user_interests_tags', $user_interests_tags);
		} else {
			$update_user_interests_tags_status = true;
		}

		if (
			(!empty($query_parts) && $result === true)
			|| ($update_user_interests_tags_status === true)
		) {
			$this->add_msg("Profile updated successfully!");
			return true;
		} else {
			$this->add_error("Failed to update profile.", 'error');
			return false;
		}
	}

	/**
	 * Update the user's avatar with the latest ID.
	 *
	 * This function updates the user's profile picture hash in the database.
	 * If the user hash is not provided, it will use the user hash from the session.
	 *
	 * @param string $avatar_ID The new avatar ID to be set for the user.
	 * @param string|null $user_hash (Optional) The hash of the user whose avatar is to be updated. Defaults to null.
	 * 
	 * @return bool Returns true if the avatar was updated successfully, false otherwise.
	 */
	public function user_edit_profile_update_avatar_ID($avatar_ID, $user_hash = null) {

		if (!isset($user_hash)) {
			$user_hash = $_SESSION['user_hash'];
		}

		// Verify all fields are correct before proceeding
		if (empty($avatar_ID)) {
			$this->add_error("Empty Avatar Hash", 'warning');
		} elseif (empty($user_hash)) {
			$this->add_error("Empty User Hash", 'warning');
		} elseif (
			!empty($avatar_ID) &&
			!empty($user_hash)
		) {
			// Update user profile data
			$query = "UPDATE users
				SET
					user_profile_pic_hash = :user_profile_pic_hash
				WHERE user_hash = :userhash
				";

			// The parameter values
			$query_params = array(
				':user_profile_pic_hash' => $avatar_ID,
				':userhash'              => $_SESSION['user_hash'],
			);
			try {
				// Execute the query against the database
				$stmt = $this->db->prepare($query);
				$result = $stmt->execute($query_params);

				// Should have updated successfully
				$this->add_msg("Profile Avatar updated successfully!");
				return true;
			} catch (PDOException $ex) {
				//die("Failed to run query. Check log for details.");
				// die( "Failed to run query: " . $ex->getMessage() );
				return false;
			}
		} else {
			$this->add_error("An unknown error occurred.", 'error');
		}

		// default return
		// die("An unknown error occurred.");
		return false;
	}
}
