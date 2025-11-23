<?php

// Constants
define('DB_HOST', 'localhost');
define('DB_USER', 'admin_instaship');
define('DB_PASSWORD', value: 'DyNxcmYbuHyDaPXZ87Vr');
define('DB_NAME', 'admin_instaship');
define('BREVO_API_KEY', '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');
define('MAILGUN_API_KEY', '93ce4fc13ac1669940790fa84d90eb39-88b1ca9f-0eff91e0');
define('JWT_SECRET', "Not Alan's Instaship App");

define('default_shiptokens_to_USD_cents_rate', 100); // 1 shiptoken = 100 USD cents

// Supported payment gateways
define('SUPPORTED_GATEWAYS', ['crypto_nowpayments']);


// Payment gateways API keys
define('NOWPAYMENTS_API_KEY', 'N4EPRWD-QM7MWXY-MXKB28Y-014Y9SP');
define('NOWPAYMENTS_API_ENDPOINT', 'https://api-sandbox.nowpayments.io/v1/');


// Debug Mode
define('DEBUG_MODE', true);
