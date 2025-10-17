<?php

// Constants
define('DB_HOST', 'localhost');
define('DB_USER', 'admin_clovers');
define('DB_PASSWORD', value: 'NkT984nSMDy8bFtsjDWD');
define('DB_NAME', 'admin_clovers');
define('BREVO_API_KEY', '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');
define('MAILGUN_API_KEY', 'ce94e80a5c48e2c958a780a619a4caf4-7113c52e-2f7e0dc9');
define('JWT_SECRET', "Not Alan's Casino App");

define('default_clovers_to_USD_cents_rate', 100); // 1 clovers = 100 USD cents

// Supported payment gateways
define('SUPPORTED_GATEWAYS', ['crypto_nowpayments']);


// Payment gateways API keys
define('NOWPAYMENTS_API_KEY', 'N4EPRWD-QM7MWXY-MXKB28Y-014Y9SP');
define('NOWPAYMENTS_API_ENDPOINT', 'https://api-sandbox.nowpayments.io/v1/');


// Debug Mode
define('DEBUG_MODE', true);
