<?php
// Set headers
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 1000");
header("Access-Control-Allow-Headers: X-Requested-With, Content-Type, Origin, Cache-Control, Pragma, Authorization, Accept, Accept-Encoding");
header("Access-Control-Allow-Methods: PUT, POST, GET, OPTIONS, DELETE");

// Require necessary files
require_once(__DIR__ . '/classes/class_main.php');
require_once(__DIR__ . '/classes/class_auth.php');
require_once(__DIR__ . '/classes/class_user.php');
require_once(__DIR__ . '/classes/class_user_wallet.php');
require_once(__DIR__ . '/classes/class_gateway_crypto_nowpayments.php');

// Initialize classes
$class_main = new class_main();
$class_auth = new class_auth();
$class_user = new class_user();
$class_user_wallet = new class_user_wallet();
$class_gateway_crypto_nowpayments = new class_gateway_crypto_nowpayments();

// Start a new session if it is not already started
if (session_status() == PHP_SESSION_NONE) {
	// session has not started
	session_start();
}

// Insert an access log entry
$class_main->insert_access_log_entry();

// Default return values
$return_http_response_code = 404;
$return_array = array(
	'success' => false,
	'errors' => array(),
	'messages' => array()
);

// Some flags & variables
$flag_is_user_logged_in = $class_auth->isUserLoggedIn();

// User hash
$user_hash = null;
if ($flag_is_user_logged_in) {
	// It may be safely assumed that the user hash is set AND $_SESSION is already started
	$user_hash = $_SESSION['user_hash'];
}

// Get the request method and URI
$request_method = $_SERVER['REQUEST_METHOD'];
$request_URI = $_SERVER['REQUEST_URI'];
// Remove the base path (/api.php) from the request URI
if (strpos($request_URI, $_SERVER['SCRIPT_NAME']) >= 0) {
	$request_URI = substr($request_URI, strlen($_SERVER['SCRIPT_NAME']));
}


// Switch / case for the request method and URI
switch ($request_method | $request_URI) {
		/* 	Request Method: POST
		Request URI: /auth/login
		Description: Login a user

		Request Headers:
			Content-Type: form-data
			X-Fp-Reqid: A fingerprint request ID for additional verification
			X-Fp-Hash: A fingerprint hash for additional verification

		Request Parameters:
			form_user_login: An empty parameter representing the user login form
			form_login_username: The username (i.e email) of the user
			form_login_password: The password of the user


		Returns:
			- HTTP Response Code: 200 on success, 400 for bad request, 401 for unauthorized access, 418 for invalid request method.
			- JSON Response:
				- success: Boolean indicating the success of the operation.
				- messages: Array of success messages.
				- errors: Array of error messages.
				- bearer_token: A JWT bearer token for the user

		Example Response:
			{
				"success": true,
				"messages": ["Login successful"],
				"errors": []
			}
	*/
	case $request_method == 'POST' && $request_URI == '/auth/login':
		if (!isset($_POST['form_user_login'])) {
			$return_http_response_code = 400;
			$return_array['success'] = false;
			// $return_array['errors'][] = "No login data provided";
			$class_main->add_error('No login data provided', 'warning');
			break;
		}

		$login_result = $class_auth->doLoginWithPOSTData();
		if ($login_result) {
			$return_http_response_code = 200;
			$return_array['success'] = true;
			$class_main->add_msg("Login successful");
			$return_array['user_hash'] = $_SESSION['user_hash'];
			$return_array['bearer_token'] = $class_auth->generate_jwt_token();
		} else {
			$return_http_response_code = 401;
			$return_array['success'] = false;
			$class_main->add_error("Login failed", 'warning');
		}
		break;



		/* 	Request Method: POST
		Request URI: /auth/register
		Description: Register a new user

		Request Headers:
			Content-Type: form-data
			X-Fp-Reqid: A fingerprint request ID for additional verification
			X-Fp-Hash: A fingerprint hash for additional verification

		Request Parameters:
			form_user_register: An empty parameter representing the user registration form
			form_user_register_first_name: The first name of the user. Must not be empty.
			form_user_register_last_name: The last name of the user. Must not be empty.
			form_user_register_email: The email of the user attempting to register. Must not be empty.
			form_user_register_password: The password of the user attempting to register. Must not be empty.
			form_user_register_password_confirm: The confirmation of the password. Must match the password.

		Returns:
			- HTTP Response Code: 200 on success, 400 for bad request, 401 for unauthorized access, 418 for invalid request method.
			- JSON Response:
				- success: Boolean indicating the success of the operation.
				- messages: Array of success messages.
				- errors: Array of error messages.
	*/
	case $request_method == 'POST' && $request_URI == '/auth/register':
		echo 'test1';
		$register_result = $class_auth->user_create_request();
		if ($register_result) {
			$return_http_response_code = 200;
			$return_array['success'] = true;
			$class_main->add_msg("If the email address you entered is correct, you will receive an email shortly with a link to verify your account. Please check your spam folder if you do not see it in your inbox.");
		} else {
			$return_http_response_code = 400;
			$return_array['success'] = false;
			$class_main->add_error("Registration failed", 'error');
		}
		break;



		/* 	Request Method: POST
		Request URI: /auth/verify_email
		Description: Verify a user's email address

		Request Headers:
			Content-Type: form-data
			X-Fp-Reqid: A fingerprint request ID for additional verification
			X-Fp-Hash: A fingerprint hash for additional verification

		Request Parameters:
			form_emailVerify: An empty parameter representing the email verification form
			form_emailVerify_hash: The unique hash sent to the user's email for verification.
			form_emailVerify_password: The password associated with the user's account.

		Returns:
			- HTTP Response Code: 200 on success, 400 for bad request, 401 for unauthorized access, 418 for invalid request method.
			- JSON Response:
				- success: Boolean indicating the success of the operation.
				- messages: Array of success messages.
				- errors: Array of error messages.
	*/
	case $request_method == 'POST' && $request_URI == '/verify_email':
		if (!isset($_POST['form_emailVerify'])) {
			$return_http_response_code = 400;
			$return_array['success'] = false;
			$class_main->add_error("No verification data provided", 'warning');
			break;
		}

		// The user should not be logged in yet
		if ($flag_is_user_logged_in) {
			$return_http_response_code = 400;
			$return_array['success'] = false;
			$class_user->add_error('You are already logged in', 'warning');
			break;
		}

		$verify_email_result = $class_auth->user_email_verify();
		if ($verify_email_result) {
			$return_http_response_code = 200;
			$return_array['success'] = true;
			$class_main->add_msg("Email verified successfully");
		} else {
			$return_http_response_code = 400;
			$return_array['success'] = false;
			$class_main->add_error("Email verification failed", 'error');
		}
		break;



		/* 	Request Method: GET
		Request URI: /auth/logout
		Description: Logout a user

		Request Headers:
			Authorization: Bearer <JWT token>
			X-Fp-Reqid: A fingerprint request ID for additional verification
			X-Fp-Hash: A fingerprint hash for additional verification

		Returns:
			- HTTP Response Code: 200 on success, 400 for bad request, 401 for unauthorized access, 418 for invalid request method.
			- JSON Response:
				- success: Boolean indicating the success of the operation.
				- messages: Array of success messages.
				- errors: Array of error messages.
	*/
	case $request_method == 'GET' && $request_URI == '/auth/logout':
		// The user should be logged in
		if (!$flag_is_user_logged_in) {
			$return_http_response_code = 400;
			$return_array['success'] = false;
			$class_main->add_error("You are not logged in", 'warning');
			break;
		}

		// Verify the JWT token
		$jwt_token_verified = $class_auth->verify_jwt_token();
		if (!$jwt_token_verified) {
			$return_http_response_code = 401;
			$return_array['success'] = false;
			$class_main->add_error("Invalid JWT token", 'warning');
			break;
		}
		$logout_result = $class_auth->doLogout();
		if ($logout_result) {
			$return_http_response_code = 200;
			$return_array['success'] = true;
			$class_main->add_msg("Logout successful");
		} else {
			$return_http_response_code = 400;
			$return_array['success'] = false;
			$class_main->add_error("Logout failed", 'error');
		}
		break;

		/* 	Request Method: GET
		Request URI: /wallet/balance
		Description: Get the user's wallet balance

		Request Headers:
			Authorization: Bearer <JWT token>
			X-Fp-Reqid: A fingerprint request ID for additional verification
			X-Fp-Hash: A fingerprint hash for additional verification

		Returns:
			- HTTP Response Code: 200 on success, 400 for bad request, 401 for unauthorized access, 418 for invalid request method.
			- JSON Response:
				- success: Boolean indicating the success of the operation.
				- messages: Array of success messages.
				- errors: Array of error messages.
				- balance: The user's wallet balance
	*/
	case $request_method == 'GET' && $request_URI == '/wallet/balance':
		// Verify the JWT token
		$jwt_token_verified = $class_auth->verify_jwt_token();
		if (!$jwt_token_verified) {
			$return_http_response_code = 401;
			$return_array['success'] = false;
			$class_main->add_error("Invalid JWT token", 'warning');
			break;
		}
		$balance_result = $class_user_wallet->get_user_wallet_balance_amt_shiptokens($user_hash);

		// $balance_result should be a float or integer
		if (is_numeric($balance_result)) {
			$return_http_response_code = 200;
			$return_array['success'] = true;
			$class_main->add_msg("Balance retrieved successfully");
			$return_array['balance'] = $balance_result;
		} else {
			$return_http_response_code = 400;
			$return_array['success'] = false;
			$class_main->add_error("Balance retrieval failed", 'error');
		}
		break;


		/**
		 * API Endpoint: /wallet/details
		 *
		 * Method: GET
		 *
		 * Description:
		 *   Retrieves the user's wallet balance details, including available, pending, and withdrawable balances.
		 *
		 * Request Headers:
		 *   Authorization: Bearer <JWT token>
		 *   X-Fp-Reqid: A fingerprint request ID for additional verification
		 *   X-Fp-Hash: A fingerprint hash for additional verification
		 *
		 * Returns:
		 *   - HTTP Response Code: 200 on success, 400 for bad request, 401 for unauthorized access, 418 for invalid request method.
		 *   - JSON Response:
		 *     - success: Boolean indicating the success of the operation.
		 *     - messages: Array of success messages.
		 *     - errors: Array of error messages.
		 *     - details: An object containing the user's wallet balance details.
		 *       - available_balance: The user's available balance for spending.
		 *       - pending_balance: The user's pending balance.
		 *       - withdrawable_balance: The user's withdrawable balance.
		 */
	case $request_method == 'GET' && $request_URI == '/wallet/details':
		// The user must be logged in
		if (!$flag_is_user_logged_in) {
			$return_http_response_code = 400;
			$return_array['success'] = false;
			$class_main->add_error('You are not logged in', 'warning');
			break;
		}

		// Get the user's wallet balance details
		$details_result = $class_user_wallet->get_user_wallet_balance_details($user_hash);
		if ($details_result) {
			$return_http_response_code = 200;
			$return_array['success'] = true;
			$class_main->add_msg("Wallet balance details retrieved successfully");
			$return_array['details'] = $details_result;
		} else {
			$return_http_response_code = 400;
			$return_array['success'] = false;
			$class_main->add_error("Wallet balance details retrieval failed", 'error');
		}
		break;


		/**
		 * Request Method: GET
		 * Request URI: /wallet/get_transactions
		 * Description: Get the user's wallet transaction history.
		 *
		 * Request Headers:
		 *   Authorization: Bearer <JWT token>
		 *   X-Fp-Reqid: A fingerprint request ID for additional verification
		 *   X-Fp-Hash: A fingerprint hash for additional verification
		 *
		 * Returns:
		 *   - HTTP Response Code: 200 on success, 400 for bad request, 401 for unauthorized access, 418 for invalid request method.
		 *   - JSON Response:
		 *     - success: Boolean indicating the success of the operation.
		 *     - messages: Array of success messages.
		 *     - errors: Array of error messages.
		 *     - transactions: Array of the user's transaction history.
		 */
	case $request_method == 'GET' && $request_URI == '/wallet/get_transactions':

		// The user must be logged in
		if (!$flag_is_user_logged_in) {
			$return_http_response_code = 400;
			$return_array['success'] = false;
			$class_main->add_error('You are not logged in', 'warning');
			break;
		}

		// Get the user's wallet transactions
		$transactions_result = $class_user_wallet->get_transaction_history_by_user_hash($user_hash);
		if ($transactions_result) {
			$return_http_response_code = 200;
			$return_array['success'] = true;
			$class_main->add_msg("Transactions retrieved successfully");
			$return_array['transactions'] = $transactions_result;
		} else {
			$return_http_response_code = 400;
			$return_array['success'] = false;
			$class_main->add_error("Transactions retrieval failed", 'error');
		}
		break;


		/*
		Request Method: POST
		Request URI: /wallet/topup
		Description: Create a top-up transaction request for the user's wallet.

		Request Headers:
			Authorization: Bearer <JWT token>
			X-Fp-Reqid: A fingerprint request ID for additional verification
			X-Fp-Hash: A fingerprint hash for additional verification

		Request Body (POST):
			- amount_shiptokens: The amount of shiptokens to top up, in cents.
			- payment_method: The payment method to use for the top-up.

		Returns:
			- HTTP Response Code: 200 on success, 400 for bad request, 401 for unauthorized access, 418 for invalid request method.
			- JSON Response:
				- success: Boolean indicating the success of the operation.
				- messages: Array of success messages.
				- errors: Array of error messages.
				- topup_hash: The hash of the top-up transaction.
	*/
	case $request_method == 'POST' && $request_URI == '/wallet/topup':

		// Check if required POST parameters are set
		if (!isset($_POST['amount_shiptokens']) || !isset($_POST['payment_method'])) {
			$return_http_response_code = 400;
			$return_array['success'] = false;
			$class_main->add_error("Missing required parameters", 'warning');
			break;
		}

		// Convert the amount of shiptokens to USD cents
		$amt_USD_cents = $_POST['amount_shiptokens'] * default_shiptokens_to_USD_cents_rate;

		// The user must be logged in
		if (!$flag_is_user_logged_in) {
			$return_http_response_code = 400;
			$return_array['success'] = false;
			$class_main->add_error('You are not logged in', 'warning');
			break;
		}

		// Switch case for the payment method
		switch ($_POST['payment_method']) {
			case 'crypto_nowpayments':
				$topup_result = $class_user_wallet->create_topup_transaction_request($_POST['amount_shiptokens'], $amt_USD_cents, 'crypto_nowpayments');
				if ($topup_result) {
					$return_http_response_code = 200;
					$return_array['success'] = true;
					$class_main->add_msg("Topup request created successfully");
					$return_array['topup_hash'] = $topup_result;
				} else {
					$return_http_response_code = 400;
					$return_array['success'] = false;
					$class_main->add_error("Topup request failed", 'error');
				}
				break;
			default:
				$return_http_response_code = 400;
				$return_array['success'] = false;
				$class_main->add_error("Invalid payment method", 'warning');
				break;
		}

		break;

		/**
		 * Request Method: POST
		 * Request URI: /wallet/topup/gateway_crypto_nowpayments/get_available_currencies
		 * Description: Get the available payment currencies for the crypto_nowpayments gateway.
		 *
		 * Request Headers:
		 *   Authorization: Bearer <JWT token>
		 *   X-Fp-Reqid: A fingerprint request ID for additional verification
		 *   X-Fp-Hash: A fingerprint hash for additional verification
		 *
		 * Request Body:
		 *   topup_hash: The hash of the top-up transaction.
		 *
		 * Returns:
		 *   - HTTP Response Code: 200 on success, 400 for bad request, 401 for unauthorized access, 418 for invalid request method.
		 *   - JSON Response:
		 *     - success: Boolean indicating the success of the operation.
		 *     - messages: Array of success messages.
		 *     - errors: Array of error messages.
		 *     - available_currencies: An array of available currencies for the specified amount.
		 */
	case $request_method == 'POST' && $request_URI == '/wallet/topup/gateway_crypto_nowpayments/get_available_currencies':
		// Check if required POST parameters are set
		if (!isset($_POST['topup_hash'])) {
			$return_http_response_code = 400;
			$return_array['success'] = false;
			$class_main->add_error("Missing required parameter: topup_hash", 'warning');
			break;
		}

		// Get the topup transaction request
		$topup_transaction_request = $class_user_wallet->get_topup_details_by_topup_hash($_POST['topup_hash']);
		if (!$topup_transaction_request) {
			$return_http_response_code = 400;
			$return_array['success'] = false;
			$class_main->add_error("Topup transaction request not found", 'warning');
			break;
		}

		// Get the amount in USD cents
		$amt_USD_cents = (float) $topup_transaction_request['txn-amt_USD_cents'];
		$amt_USD = $amt_USD_cents / 100;

		// Get the available currencies
		$available_currencies_result = $class_gateway_crypto_nowpayments->get_available_payment_currencies($amt_USD);
		if ($available_currencies_result) {
			$return_http_response_code = 200;
			$return_array['success'] = true;
			$class_main->add_msg("Available currencies retrieved successfully");
			$return_array['available_currencies'] = $available_currencies_result;
		} else {
			$return_http_response_code = 400;
			$return_array['success'] = false;
			$class_main->add_error("Available currencies retrieval failed", 'error');
		}
		break;


		/**
		 * API Endpoint: /wallet/topup/gateway_crypto_nowpayments/selected_coin
		 *
		 * Method: POST
		 *
		 * Description:
		 *   Creates a new payment order with NOWPayments for a selected cryptocurrency.
		 *
		 * Request Headers:
		 *   X-Fp-Reqid: A fingerprint request ID for additional verification
		 *   X-Fp-Hash: A fingerprint hash for additional verification
		 *
		 * Request Body:
		 *   topup_hash: The hash of the top-up transaction.
		 *   selected_coin: The cryptocurrency selected for payment.
		 *
		 * Returns:
		 *   - HTTP Response Code: 200 on success, 400 for bad request, 401 for unauthorized access, 418 for invalid request method.
		 *   - JSON Response:
		 *     - success: Boolean indicating the success of the operation.
		 *     - messages: Array of success messages.
		 *     - errors: Array of error messages.
		 *     - pay_address: The payment address for the selected cryptocurrency.
		 *     - pay_amount: The amount to pay in the selected cryptocurrency.
		 *     - pay_currency: The currency of the payment.
		 *     - pay_expires_at: The expiration time of the payment address.
		 */
	case $request_method == 'POST' && $request_URI == '/wallet/topup/gateway_crypto_nowpayments/selected_coin':
		// Check if required POST parameters are set
		if (!isset($_POST['topup_hash']) || !isset($_POST['selected_coin'])) {
			$return_http_response_code = 400;
			$return_array['success'] = false;
			$class_main->add_error("Missing required parameters: topup_hash and selected_coin", 'warning');
			break;
		}

		// Get the topup transaction request
		$topup_transaction_request = $class_user_wallet->get_topup_details_by_topup_hash($_POST['topup_hash']);
		if (!$topup_transaction_request) {
			$return_http_response_code = 400;
			$return_array['success'] = false;
			$class_main->add_error("Topup transaction request not found", 'warning');
			break;
		}

		// Create new NOWPayments order
		$new_order_result = $class_gateway_crypto_nowpayments->create_payment_order(
			$topup_transaction_request['txn-amt_USD_cents'] / 100,
			$_POST['selected_coin'],
			$topup_transaction_request['txn-hash'],
			"shiptokensBet Topup for " . $topup_transaction_request['txn-amt_shiptokens'] . " shiptokens"
		);

		if ($new_order_result) {
			$return_http_response_code = 200;
			$return_array['success'] = true;
			$class_main->add_msg("New NOWPayments order created successfully. Order ID: " . $new_order_result['payment_id']);

			// Check if payment details are available
			if (isset($new_order_result['pay_address'])) {
				$return_array['pay_address'] = $new_order_result['pay_address'];
				$return_array['pay_amount'] = $new_order_result['pay_amount'];
				$return_array['pay_currency'] = $new_order_result['pay_currency'];
				$return_array['pay_expires_at'] = $new_order_result['expiration_date'];
				$return_array['pay_NOWPayments_ref'] = $new_order_result['payment_id'];
				$return_array['pay_NOWPayments_status'] = $new_order_result['payment_status'];

				// Update the topup transaction request with the new order details
				$class_user_wallet->update_topup_transaction_gateway_params($topup_transaction_request['txn-hash'], json_encode($new_order_result));

				// Update status to 'PENDING_PAYMENT'
				$class_user_wallet->update_topup_transaction_status($topup_transaction_request['txn-hash'], 'PENDING_PAYMENT', 'Please make the payment to the address provided before expiry.');

				$return_array['success'] = true;
				$return_http_response_code = 200;
			} else {
				$return_http_response_code = 400;
				$return_array['success'] = false;
				$class_main->add_error("Payment details missing from NOWPayments order response", 'error');
			}
		} else {
			$return_http_response_code = 400;
			$return_array['success'] = false;
			$class_main->add_error("New NOWPayments order creation failed", 'error');
		}
		break;

		/**
		 * API Endpoint: /wallet/topup/gateway_crypto_nowpayments/mark_as_paid
		 *
		 * Method: POST
		 *
		 * Description:
		 *   Allows a user to mark a NOWPayments top-up transaction as paid.
		 *   It updates the transaction status to 'PENDING_CONFIRMATION' and checks the order status with NOWPayments.
		 *
		 * Request Headers:
		 *   X-Fp-Reqid: A fingerprint request ID for additional verification
		 *   X-Fp-Hash: A fingerprint hash for additional verification
		 *
		 * Request Body:
		 *   topup_hash: The unique hash of the top-up transaction.
		 *
		 * Returns:
		 *   - HTTP Response Code: 200 on success, 400 for bad request, 401 for unauthorized access, 418 for invalid request method.
		 *   - JSON Response:
		 *     - success: Boolean indicating the success of the operation.
		 *     - messages: Array of success messages.
		 *     - errors: Array of error messages.
		 *     - order_status: An object containing the order status details from NOWPayments.
		 */
	case $request_method == 'POST' && $request_URI == '/wallet/topup/gateway_crypto_nowpayments/mark_as_paid':
		// Check if required POST parameters are set
		if (!isset($_POST['topup_hash'])) {
			$return_http_response_code = 400;
			$return_array['success'] = false;
			$class_main->add_error("Missing required parameter: topup_hash", 'warning');
			break;
		}

		// Get the topup transaction request
		$topup_transaction_request = $class_user_wallet->get_topup_details_by_topup_hash($_POST['topup_hash']);
		if (!$topup_transaction_request) {
			$return_http_response_code = 400;
			$return_array['success'] = false;
			$class_main->add_error("Topup transaction request not found", 'warning');
			break;
		}

		// Append the latest transaction status in gateway_response
		$class_user_wallet->append_topup_transaction_gateway_response($topup_transaction_request['txn-hash'], 'User marked as paid. Awaiting confirmation.');

		// Update status to 'PENDING_CONFIRMATION'
		$class_user_wallet->update_topup_transaction_status($topup_transaction_request['txn-hash'], 'PENDING_CONFIRMATION', 'Awaiting confirmation from NOWPayments.');

		// Check the NOWPayments order status
		$order_status_result = $class_gateway_crypto_nowpayments->check_payment_order_status($_POST['topup_hash']);
		if ($order_status_result) {
			$return_array['order_status'] = $order_status_result;
			$return_array['success'] = true;
			$return_http_response_code = 200;
		} else {
			$return_http_response_code = 400;
			$return_array['success'] = false;
			$class_main->add_error("NOWPayments order status check failed", 'error');
		}
		break;


	case $request_method == 'POST' && $request_URI == '/wallet/topup/gateway_crypto_nowpayments/user_cancelled_payment':
		// Check if required POST parameters are set
		if (!isset($_POST['topup_hash'])) {
			$return_http_response_code = 400;
			$return_array['success'] = false;
			$class_main->add_error("Missing required parameter: topup_hash", 'warning');
			break;
		}

		// Initiate cancellation of the NOWPayments order
		$cancellation_result = $class_user_wallet->user_aborted_payment($_POST['topup_hash']);
		if ($cancellation_result) {
			$return_http_response_code = 200;
			$return_array['success'] = true;
			$class_main->add_msg("User aborted payment successfully");
		} else {
			$return_http_response_code = 400;
			$return_array['success'] = false;
			$class_main->add_error("User aborted payment failed", 'error');
		}
		break;

		/**
		 * API Endpoint: /wallet/topup/gateway_crypto_nowpayments/get_order_status
		 *
		 * Method: POST
		 *
		 * Description:
		 *   Retrieves the status of a payment order from NOWPayments.
		 *
		 * Request Headers:
		 *   X-Fp-Reqid: A fingerprint request ID for additional verification
		 *   X-Fp-Hash: A fingerprint hash for additional verification
		 *
		 * Request Body:
		 *   topup_hash: The ID of the order to check.
		 *
		 * Returns:
		 *   - HTTP Response Code: 200 on success, 400 for bad request, 401 for unauthorized access, 418 for invalid request method.
		 *   - JSON Response:
		 *     - success: Boolean indicating the success of the operation.
		 *     - messages: Array of success messages.
		 *     - errors: Array of error messages.
		 *     - order_status: An object containing the order status details from NOWPayments.
		 */
	case $request_method == 'POST' && $request_URI == '/wallet/topup/gateway_crypto_nowpayments/get_order_status':
		// Check if required POST parameters are set
		if (!isset($_POST['topup_hash'])) {
			$return_http_response_code = 400;
			$return_array['success'] = false;
			$class_main->add_error("Missing required parameter: topup_hash", 'warning');
			break;
		}

		// Get the order status from NOWPayments
		$order_status_result = $class_gateway_crypto_nowpayments->check_payment_order_status($_POST['topup_hash']);
		if ($order_status_result) {
			$return_http_response_code = 200;
			$return_array['success'] = true;
			$class_main->add_msg("Order status retrieved successfully");
			$return_array['order_status'] = $order_status_result;
		} else {
			$return_http_response_code = 400;
			$return_array['success'] = false;
			$class_main->add_error("Order status retrieval failed", 'error');
		}
		break;













		/**
		 * API Endpoint: /ship/validate_address
		 * 
		 * METHOD: POST
		 * 
		 * Description:
		 * Validates addresses and checks for validity.
		 * 
		 * TODO
		 * */







		/**
		 * API Endpoint: /ship/get_rates
		 * 
		 * METHOD: POST
		 * 
		 * Description:
		 *   Submits surveyJS data and fetches rates from Shippo / Easyship.
		 *
		 * Request Headers:
		 *   X-Fp-Reqid: A fingerprint request ID for additional verification
		 *   X-Fp-Hash: A fingerprint hash for additional verification
		 *
		 * Request Body:
		 * 
		 * */





		// Handle other cases
	default:
		$return_http_response_code = 418;
		$return_array['success'] = false;
		$class_main->add_error("I'm a teapot", 'error');
		break;
}



// Get messages and errors from the session
$return_array['messages'] = $class_main->get_messages_from_session();
$return_array['errors'] = $class_main->get_errors_from_session();

// Return the response
http_response_code($return_http_response_code);
echo json_encode($return_array);
