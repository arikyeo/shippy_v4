CREATE TABLE `log_access` (
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`log_uuid` varchar(128) DEFAULT NULL,
	`requestURI` text NOT NULL,
	`protocol` varchar(16) DEFAULT NULL COMMENT 'Protocol of request, eg GET/POST/PUT etc',
	`headers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`headers`)),
	`fp_requestID` varchar(255) DEFAULT NULL COMMENT 'Fingerprint request ID',
	`fp_visitorID` varchar(255) DEFAULT NULL COMMENT 'Fingerprint visitor ID',
	`timestamp` datetime NOT NULL DEFAULT current_timestamp(),
	`user_hash` varchar(255) DEFAULT NULL COMMENT 'User hash (if any)',
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `log_errors` (
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`log_level` varchar(64) DEFAULT 'info' COMMENT 'Log level, ie debug/info/warning/error',
	`log_uuid` varchar(128) DEFAULT NULL,
	`log_message` text DEFAULT NULL,
	`requestURI` text NOT NULL,
	`script_path` text DEFAULT NULL COMMENT 'Path to the currently executing script',
	`protocol` varchar(16) DEFAULT NULL COMMENT 'Protocol of request, eg GET/POST/PUT etc',
	`headers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`headers`)),
	`params_GET` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`params_GET`)),
	`params_POST` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`params_POST`)),
	`fp_requestID` varchar(255) DEFAULT NULL COMMENT 'Fingerprint request ID',
	`fp_visitorID` varchar(255) DEFAULT NULL COMMENT 'Fingerprint visitor ID',
	`timestamp` datetime NOT NULL DEFAULT current_timestamp(),
	`user_hash` varchar(255) DEFAULT NULL COMMENT 'User hash (if any)',
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `users` (
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `users_unverified` (
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`created_timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
	`user_unverified_hash` varchar(255) NOT NULL,
	`user_unverified_fp_hash` varchar(255) DEFAULT NULL,
	`user_unverified_name_first` varchar(255) NOT NULL,
	`user_unverified_name_last` varchar(255) NOT NULL,
	`user_unverified_email` varchar(255) NOT NULL,
	`user_unverified_password_hash` varchar(1024) NOT NULL,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `log_wallet_topup` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `txn-hash` varchar(128) NOT NULL COMMENT 'Txn hash of current log entry',
 `txn-gateway` varchar(64) DEFAULT NULL,
 `txn-status_current` varchar(64) DEFAULT NULL COMMENT 'Current status of txn per log time',
 `txn-gateway_data_request` text DEFAULT NULL,
 `txn-gateway_data_response` text DEFAULT NULL,
 `txn-status_current_text` varchar(512) NOT NULL COMMENT 'Current status text of txn per log time',
 `time_created` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Log entry timestamp',
 `time_updated_last` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Timestamp of last update for current log entry',
 `comments` text NOT NULL COMMENT 'Comments / Remarks (if any)',
 PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `wallet_topups` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `txn-hash` varchar(128) NOT NULL COMMENT 'Transaction hash (for internal reference)',
 `txn-hash_user` varchar(128) DEFAULT NULL COMMENT 'User hash attributed to the transaction',
 `txn-status` varchar(255) NOT NULL,
 `txn-status_text` mediumtext NOT NULL,
 `txn-amt_clovers` float NOT NULL DEFAULT 0 COMMENT 'Amt in clovers (virtual token currency)',
 `txn-amt_USD_cents` decimal(10,2) NOT NULL,
 `txn-time_created` timestamp NOT NULL DEFAULT current_timestamp(),
 `txn-time_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
 `txn-gateway` varchar(128) NOT NULL,
 `txn-gateway_response` longtext NOT NULL CHECK (json_valid(`txn-gateway_response`)),
 `txn-gateway_params` longtext DEFAULT NULL COMMENT 'Storage for params generated by gateway for retrieval (flexible)',
 `txn-comments` text DEFAULT NULL COMMENT 'Comments/remarks (if any)',
 PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `wallet_transactions` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `txn-hash` varchar(128) NOT NULL COMMENT 'Transaction hash',
 `txn-hash_user` varchar(128) DEFAULT NULL COMMENT 'User hash attributed to the transaction',
 `txn-ref_id` varchar(255) DEFAULT NULL COMMENT 'Ref id of txn (e.g NOWPayments payment id, bet deduction, payout etc)',
 `txn-time_created` timestamp NOT NULL DEFAULT current_timestamp(),
 `txn-time_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
 `txn-status_latest` varchar(64) NOT NULL DEFAULT 'TXN_INITIATED',
 `txn-status_latest_text` varchar(512) DEFAULT NULL,
 `txn-amt_USD_cents` float NOT NULL DEFAULT 0 COMMENT 'Amount in cents, USD',
 `txn-amt_clovers` float NOT NULL DEFAULT 0 COMMENT 'Amt in clovers (virtual token currency)',
 `txn-comments` text DEFAULT NULL COMMENT 'Comments/remarks (if any)',
 PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `bets` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `bet_hash` varchar(128) DEFAULT NULL,
 `bet_players` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of players and their roles' CHECK (json_valid(`bet_players`)),
 `bet_date_created` timestamp NOT NULL DEFAULT current_timestamp(),
 `bet_date_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
 `bet_server_hashes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of per-user unique server hashes' CHECK (json_valid(`bet_server_hashes`)),
 `bet_user_hashes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of per-user unique client side generated hashes' CHECK (json_valid(`bet_user_hashes`)),
 `bet_nonces` varchar(255) DEFAULT NULL COMMENT 'Nonce generated for current bet session',
 `bet_params` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Any special betting parameters' CHECK (json_valid(`bet_params`)),
 `bet_ledger_log` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`bet_ledger_log`)),
 `bet_amount` float NOT NULL DEFAULT 0,
 `bet_outcomes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of bet outcomes per player' CHECK (json_valid(`bet_outcomes`)),
 `bet_payouts` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of bet payouts per player' CHECK (json_valid(`bet_payouts`)),
 `bet_comments` text DEFAULT NULL COMMENT 'bet comments (if any)',
 `bet_game` varchar(255) NOT NULL,
 PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;





CREATE TABLE `bets_coinflip` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `bet_hash` varchar(128) DEFAULT NULL,
 `bet_player_hash` varchar(128) DEFAULT NULL,
 `bet_date_created` timestamp NOT NULL DEFAULT current_timestamp(),
 `bet_date_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
 `bet_server_hash` varchar(128) DEFAULT NULL,
 `bet_user_hash` varchar(128) DEFAULT NULL,
 `bet_nonce` varchar(255) DEFAULT NULL COMMENT 'Nonce generated for current bet session',
 `bet_params` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Any special betting parameters' CHECK (json_valid(`bet_params`)),
 `bet_ledger_log` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`bet_ledger_log`)),
 `bet_amount` float NOT NULL DEFAULT 0,
 `bet_outcomes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of bet outcomes' CHECK (json_valid(`bet_outcomes`)),
 `bet_payouts` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of bet payouts per player' CHECK (json_valid(`bet_payouts`)),
 `bet_comments` text DEFAULT NULL COMMENT 'bet comments (if any)',
 PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;