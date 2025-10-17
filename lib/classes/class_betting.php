<?php

$autoloadPath = realpath(__DIR__ . '/../vendor/autoload.php');

if (file_exists($autoloadPath)) {
	require_once($autoloadPath);
} else {
	die('Autoload file not found');
}

// Require main class
require_once(realpath(__DIR__ . '/class_main.php'));

// Require user class
require_once(realpath(__DIR__ . '/class_user.php'));

/* 	CREATE TABLE `bet_ledger` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `bet_game` varchar(255) NOT NULL,
 `bet_hash` varchar(128) DEFAULT NULL,
 `bet_player_hash` varchar(128) DEFAULT NULL,
 `bet_date_created` timestamp NOT NULL DEFAULT current_timestamp(),
 `bet_date_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
 `bet_amount` float NOT NULL DEFAULT 0,
 `bet_outcome_win` boolean DEFAULT NULL,
 `bet_payout` float NOT NULL DEFAULT 0,
 `bet_comments` text DEFAULT NULL COMMENT 'bet comments (if any)',
 PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

*/


class class_betting extends class_main {

	// Initialize classes
	private $class_user;

	public function __construct() {
		parent::__construct();
		$this->class_user = new class_user();
	}

	public function __destruct() {
		parent::__destruct();
	}

	public function generate_server_seed() {
		return bin2hex(random_bytes(32));
	}

	// Store the server seed in the database (under user_params)
	public function store_server_seed($user_hash, $server_seed) {

		// Ensure user hash is valid
		if (!$this->class_user->check_user_hash_exists($user_hash)) {
			$this->add_error('User hash not found', 'warning');
			return false;
		}

		// Update the user_params table with the server seed
		$this->class_user->update_user_params_by_key($user_hash, 'server_seed', $server_seed);

		return true;
	}


	// Get the server seed for a user
	public function get_server_seed($user_hash) {
		$server_seed = $this->class_user->fetch_user_params_by_key($user_hash, 'server_seed');

		// If server seed is not found, generate a new one
		if ($server_seed == 'not_found') {
			$server_seed = $this->generate_server_seed();
			$this->store_server_seed($user_hash, $server_seed);
		}

		return $server_seed;
	}



	// Get player's betting history list from ledger
	public function get_player_betting_history($user_hash) {

		// Ensure user hash is valid
		if (!$this->class_user->check_user_hash_exists($user_hash)) {
			$this->add_error('User hash not found', 'warning');
			return false;
		}

		// Get the bets from the ledger (db table bets)
		$query_bets = "SELECT * FROM bets WHERE bet_user_hashes LIKE '%$user_hash%'";

		// The query params
		$query_params_bets = array(
			':userhash' => $user_hash,
		);

		// Execute the query
		try {
			$stmt = $this->db->prepare($query_bets);
			$stmt->execute($query_params_bets);
		} catch (PDOException $e) {
			$this->add_error($e->getMessage(), 'error');
			return false;
		}

		// Fetch the bets
		$bet_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

		return $bet_history;
	}



	// Get player's betting stats
	// Allow param to filter by game
	// Stats to track (grouped to all time, last 24 hours, last 7 days, last 30 days, last 10 bets, last 100 bets):

	// Total bet count
	// Total bet amount (clovers)

	// Total win count
	// Total win amount (clovers)

	// Total loss count
	// Total loss amount (clovers)

	// Total payout amount (clovers)

	// Win rate
	// Loss rate

	// Average bet amount (clovers)
	// Average win amount (clovers)
	// Average loss amount (clovers)

	public function get_player_betting_stats_from_ledger($user_hash, $game = null) {

		// Build the return array
		$return_array = array(
			"all_time" => [
				'total_bet_count' => 0,
				'total_bet_amount' => 0,
				'total_win_count' => 0,
				'total_win_amount' => 0,
				'total_loss_count' => 0,
				'total_loss_amount' => 0,
				'total_payout_amount' => 0,
				'win_rate' => 0,
				'loss_rate' => 0,
				'average_bet_amount' => 0,
				'average_win_amount' => 0,
				'average_loss_amount' => 0
			],
			"last_24_hours" => [
				'total_bet_count' => 0,
				'total_bet_amount' => 0,
				'total_win_count' => 0,
				'total_win_amount' => 0,
				'total_loss_count' => 0,
				'total_loss_amount' => 0,
				'total_payout_amount' => 0,
				'win_rate' => 0,
				'loss_rate' => 0,
				'average_bet_amount' => 0,
				'average_win_amount' => 0,
				'average_loss_amount' => 0
			],
			"last_7_days" => [
				'total_bet_count' => 0,
				'total_bet_amount' => 0,
				'total_win_count' => 0,
				'total_win_amount' => 0,
				'total_loss_count' => 0,
				'total_loss_amount' => 0,
				'total_payout_amount' => 0,
				'win_rate' => 0,
				'loss_rate' => 0,
				'average_bet_amount' => 0,
				'average_win_amount' => 0,
				'average_loss_amount' => 0
			],
			"last_30_days" => [
				'total_bet_count' => 0,
				'total_bet_amount' => 0,
				'total_win_count' => 0,
				'total_win_amount' => 0,
				'total_loss_count' => 0,
				'total_loss_amount' => 0,
				'total_payout_amount' => 0,
				'win_rate' => 0,
				'loss_rate' => 0,
				'average_bet_amount' => 0,
				'average_win_amount' => 0,
				'average_loss_amount' => 0
			],
			"last_10_bets" => [
				'total_bet_count' => 0,
				'total_bet_amount' => 0,
				'total_win_count' => 0,
				'total_win_amount' => 0,
				'total_loss_count' => 0,
				'total_loss_amount' => 0,
				'total_payout_amount' => 0,
				'win_rate' => 0,
				'loss_rate' => 0,
				'average_bet_amount' => 0,
				'average_win_amount' => 0,
				'average_loss_amount' => 0
			],
			"last_100_bets" => [
				'total_bet_count' => 0,
				'total_bet_amount' => 0,
				'total_win_count' => 0,
				'total_win_amount' => 0,
				'total_loss_count' => 0,
				'total_loss_amount' => 0,
				'total_payout_amount' => 0,
				'win_rate' => 0,
				'loss_rate' => 0,
				'average_bet_amount' => 0,
				'average_win_amount' => 0,
				'average_loss_amount' => 0
			]
		);

		// Get the stats from the database
		$query_stats = "SELECT * FROM bet_ledger
						WHERE bet_player_hash = :user_hash";

		// If game is specified, add a WHERE clause to the query
		if ($game) {
			$query_stats .= " AND bet_game = :game";
		}

		$query_stats .= " ORDER BY bet_date_created DESC";


		// The query params
		$query_params_stats = array(
			':user_hash' => $user_hash,
		);
		if ($game) {
			$query_params_stats[':game'] = $game;
		}

		// Execute the query
		try {
			$stmt = $this->db->prepare($query_stats);
			$stmt->execute($query_params_stats);
		} catch (PDOException $e) {
			$this->add_error($e->getMessage(), 'error');
			return false;
		}

		// Fetch the stats
		$stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

		// Process the stats
		foreach ($stats as $stat) {
			// Get the bet amount
			$bet_amount = $stat['bet_amount'];

			// Get the bet outcome win/loss
			$bet_outcome_win = $stat['bet_outcome_win'];

			// Get the bet payout
			$bet_payout = $stat['bet_payout'];

			// Get the bet date created
			$bet_date_created = $stat['bet_date_created'];


			// Add the bet amount to the total bet amount
			$return_array['all_time']['total_bet_amount'] += $bet_amount;
			$return_array['all_time']['total_bet_count']++;


			// If the bet outcome is win
			if ($bet_outcome_win) {


				// Add the bet amount to the total win amount
				$return_array['all_time']['total_win_amount'] += $bet_amount;
				$return_array['all_time']['total_win_count']++;

				// Add the payout to the total payout amount
				$return_array['all_time']['total_payout_amount'] += $bet_payout;

				// Populate the last 100 bets array if the bet is within the last 100 bet count
				if ($return_array['all_time']['total_bet_count'] <= 100) {
					$return_array['last_100_bets']['total_bet_amount'] += $bet_amount;
					$return_array['last_100_bets']['total_bet_count']++;
					$return_array['last_100_bets']['total_win_amount'] += $bet_amount;
					$return_array['last_100_bets']['total_win_count']++;
					$return_array['last_100_bets']['total_payout_amount'] += $bet_payout;
				}

				// Populate the last 10 bets array if the bet is within the last 10 bet count
				if ($return_array['all_time']['total_bet_count'] <= 10) {
					$return_array['last_10_bets']['total_bet_amount'] += $bet_amount;
					$return_array['last_10_bets']['total_bet_count']++;
					$return_array['last_10_bets']['total_win_amount'] += $bet_amount;
					$return_array['last_10_bets']['total_win_count']++;
					$return_array['last_10_bets']['total_payout_amount'] += $bet_payout;
				}

				// Populate the last 24 hours array if the bet is within the last 24 hours
				if ($bet_date_created >= strtotime('-24 hours')) {
					$return_array['last_24_hours']['total_bet_amount'] += $bet_amount;
					$return_array['last_24_hours']['total_bet_count']++;
					$return_array['last_24_hours']['total_win_amount'] += $bet_amount;
					$return_array['last_24_hours']['total_win_count']++;
					$return_array['last_24_hours']['total_payout_amount'] += $bet_payout;
				}

				// Populate the last 7 days array if the bet is within the last 7 days
				if ($bet_date_created >= strtotime('-7 days')) {
					$return_array['last_7_days']['total_bet_amount'] += $bet_amount;
					$return_array['last_7_days']['total_bet_count']++;
					$return_array['last_7_days']['total_win_amount'] += $bet_amount;
					$return_array['last_7_days']['total_win_count']++;
					$return_array['last_7_days']['total_payout_amount'] += $bet_payout;
				}

				// Populate the last 30 days array if the bet is within the last 30 days
				if ($bet_date_created >= strtotime('-30 days')) {
					$return_array['last_30_days']['total_bet_amount'] += $bet_amount;
					$return_array['last_30_days']['total_bet_count']++;
					$return_array['last_30_days']['total_win_amount'] += $bet_amount;
					$return_array['last_30_days']['total_win_count']++;
					$return_array['last_30_days']['total_payout_amount'] += $bet_payout;
				}
			}

			// If the bet outcome is loss
			if (!$bet_outcome_win) {

				// Add the bet amount to the total loss amount
				$return_array['all_time']['total_loss_amount'] += $bet_amount;
				$return_array['all_time']['total_loss_count']++;

				// Add the payout to the total payout amount
				$return_array['all_time']['total_payout_amount'] += $bet_payout;

				// Populate the last 100 bets array if the bet is within the last 100 bet count
				if ($return_array['all_time']['total_bet_count'] <= 100) {
					$return_array['last_100_bets']['total_bet_amount'] += $bet_amount;
					$return_array['last_100_bets']['total_bet_count']++;
					$return_array['last_100_bets']['total_loss_amount'] += $bet_amount;
					$return_array['last_100_bets']['total_loss_count']++;
					$return_array['last_100_bets']['total_payout_amount'] += $bet_payout;
				}

				// Populate the last 10 bets array if the bet is within the last 10 bet count
				if ($return_array['all_time']['total_bet_count'] <= 10) {
					$return_array['last_10_bets']['total_bet_amount'] += $bet_amount;
					$return_array['last_10_bets']['total_bet_count']++;
					$return_array['last_10_bets']['total_loss_amount'] += $bet_amount;
					$return_array['last_10_bets']['total_loss_count']++;
					$return_array['last_10_bets']['total_payout_amount'] += $bet_payout;
				}

				// Populate the last 24 hours array if the bet is within the last 24 hours
				if ($bet_date_created >= strtotime('-24 hours')) {
					$return_array['last_24_hours']['total_bet_amount'] += $bet_amount;
					$return_array['last_24_hours']['total_bet_count']++;
					$return_array['last_24_hours']['total_loss_amount'] += $bet_amount;
					$return_array['last_24_hours']['total_loss_count']++;
					$return_array['last_24_hours']['total_payout_amount'] += $bet_payout;
				}

				// Populate the last 7 days array if the bet is within the last 7 days
				if ($bet_date_created >= strtotime('-7 days')) {
					$return_array['last_7_days']['total_bet_amount'] += $bet_amount;
					$return_array['last_7_days']['total_bet_count']++;
					$return_array['last_7_days']['total_loss_amount'] += $bet_amount;
					$return_array['last_7_days']['total_loss_count']++;
					$return_array['last_7_days']['total_payout_amount'] += $bet_payout;
				}

				// Populate the last 30 days array if the bet is within the last 30 days
				if ($bet_date_created >= strtotime('-30 days')) {
					$return_array['last_30_days']['total_bet_amount'] += $bet_amount;
					$return_array['last_30_days']['total_bet_count']++;
					$return_array['last_30_days']['total_loss_amount'] += $bet_amount;
					$return_array['last_30_days']['total_loss_count']++;
					$return_array['last_30_days']['total_payout_amount'] += $bet_payout;
				}
			}
		}

		// Calculate the win rate for all time, last 24 hours, last 7 days, last 30 days, last 10 bets, last 100 bets
		$return_array['all_time']['win_rate'] = ($return_array['all_time']['total_win_count'] / $return_array['all_time']['total_bet_count']) * 100;
		$return_array['last_24_hours']['win_rate'] = ($return_array['last_24_hours']['total_win_count'] / $return_array['last_24_hours']['total_bet_count']) * 100;
		$return_array['last_7_days']['win_rate'] = ($return_array['last_7_days']['total_win_count'] / $return_array['last_7_days']['total_bet_count']) * 100;
		$return_array['last_30_days']['win_rate'] = ($return_array['last_30_days']['total_win_count'] / $return_array['last_30_days']['total_bet_count']) * 100;
		$return_array['last_10_bets']['win_rate'] = ($return_array['last_10_bets']['total_win_count'] / $return_array['last_10_bets']['total_bet_count']) * 100;
		$return_array['last_100_bets']['win_rate'] = ($return_array['last_100_bets']['total_win_count'] / $return_array['last_100_bets']['total_bet_count']) * 100;

		// Calculate the loss rate for all time, last 24 hours, last 7 days, last 30 days, last 10 bets, last 100 bets
		$return_array['all_time']['loss_rate'] = ($return_array['all_time']['total_loss_count'] / $return_array['all_time']['total_bet_count']) * 100;
		$return_array['last_24_hours']['loss_rate'] = ($return_array['last_24_hours']['total_loss_count'] / $return_array['last_24_hours']['total_bet_count']) * 100;
		$return_array['last_7_days']['loss_rate'] = ($return_array['last_7_days']['total_loss_count'] / $return_array['last_7_days']['total_bet_count']) * 100;
		$return_array['last_30_days']['loss_rate'] = ($return_array['last_30_days']['total_loss_count'] / $return_array['last_30_days']['total_bet_count']) * 100;
		$return_array['last_10_bets']['loss_rate'] = ($return_array['last_10_bets']['total_loss_count'] / $return_array['last_10_bets']['total_bet_count']) * 100;
		$return_array['last_100_bets']['loss_rate'] = ($return_array['last_100_bets']['total_loss_count'] / $return_array['last_100_bets']['total_bet_count']) * 100;

		// Calculate the average bet amount for all time, last 24 hours, last 7 days, last 30 days, last 10 bets, last 100 bets
		$return_array['all_time']['average_bet_amount'] = $return_array['all_time']['total_bet_amount'] / $return_array['all_time']['total_bet_count'];
		$return_array['last_24_hours']['average_bet_amount'] = $return_array['last_24_hours']['total_bet_amount'] / $return_array['last_24_hours']['total_bet_count'];
		$return_array['last_7_days']['average_bet_amount'] = $return_array['last_7_days']['total_bet_amount'] / $return_array['last_7_days']['total_bet_count'];
		$return_array['last_30_days']['average_bet_amount'] = $return_array['last_30_days']['total_bet_amount'] / $return_array['last_30_days']['total_bet_count'];
		$return_array['last_10_bets']['average_bet_amount'] = $return_array['last_10_bets']['total_bet_amount'] / $return_array['last_10_bets']['total_bet_count'];
		$return_array['last_100_bets']['average_bet_amount'] = $return_array['last_100_bets']['total_bet_amount'] / $return_array['last_100_bets']['total_bet_count'];

		// Calculate the average win amount for all time, last 24 hours, last 7 days, last 30 days, last 10 bets, last 100 bets
		$return_array['all_time']['average_win_amount'] = $return_array['all_time']['total_win_amount'] / $return_array['all_time']['total_win_count'];
		$return_array['last_24_hours']['average_win_amount'] = $return_array['last_24_hours']['total_win_amount'] / $return_array['last_24_hours']['total_win_count'];
		$return_array['last_7_days']['average_win_amount'] = $return_array['last_7_days']['total_win_amount'] / $return_array['last_7_days']['total_win_count'];
		$return_array['last_30_days']['average_win_amount'] = $return_array['last_30_days']['total_win_amount'] / $return_array['last_30_days']['total_win_count'];
		$return_array['last_10_bets']['average_win_amount'] = $return_array['last_10_bets']['total_win_amount'] / $return_array['last_10_bets']['total_win_count'];
		$return_array['last_100_bets']['average_win_amount'] = $return_array['last_100_bets']['total_win_amount'] / $return_array['last_100_bets']['total_win_count'];

		// Calculate the average loss amount for all time, last 24 hours, last 7 days, last 30 days, last 10 bets, last 100 bets
		$return_array['all_time']['average_loss_amount'] = $return_array['all_time']['total_loss_amount'] / $return_array['all_time']['total_loss_count'];
		$return_array['last_24_hours']['average_loss_amount'] = $return_array['last_24_hours']['total_loss_amount'] / $return_array['last_24_hours']['total_loss_count'];
		$return_array['last_7_days']['average_loss_amount'] = $return_array['last_7_days']['total_loss_amount'] / $return_array['last_7_days']['total_loss_count'];
		$return_array['last_30_days']['average_loss_amount'] = $return_array['last_30_days']['total_loss_amount'] / $return_array['last_30_days']['total_loss_count'];
		$return_array['last_10_bets']['average_loss_amount'] = $return_array['last_10_bets']['total_loss_amount'] / $return_array['last_10_bets']['total_loss_count'];
		$return_array['last_100_bets']['average_loss_amount'] = $return_array['last_100_bets']['total_loss_amount'] / $return_array['last_100_bets']['total_loss_count'];

		// Return the stats
		return $return_array;
	}


	// Store bet record in ledger
	public function store_bet_record_in_ledger($bet_hash, $bet_game, $bet_player_hash, $bet_amount, $bet_outcome_win, $bet_payout, $bet_comments = '') {

		// Ensure user hash is valid
		if (!$this->class_user->check_user_hash_exists($bet_player_hash)) {
			$this->add_error('User hash not found', 'warning');
			return false;
		}

		// Build the query to store the bet record in the ledger
		$query_bet_record = "INSERT INTO bet_ledger (
								bet_hash,
								bet_game,
								bet_player_hash,
								bet_amount,
								bet_outcome_win,
								bet_payout,
								bet_comments
							) VALUES (
								:bet_hash,
								:bet_game,
								:bet_player_hash,
								:bet_amount,
								:bet_outcome_win,
								:bet_payout,
								:bet_comments
							)";

		// The query params
		$query_params_bet_record = array(
			':bet_hash' => $bet_hash,
			':bet_game' => $bet_game,
			':bet_player_hash' => $bet_player_hash,
			':bet_amount' => $bet_amount,
			':bet_outcome_win' => $bet_outcome_win,
			':bet_payout' => $bet_payout,
			':bet_comments' => $bet_comments
		);

		// Execute the query
		try {
			$stmt = $this->db->prepare($query_bet_record);
			$stmt->execute($query_params_bet_record);
		} catch (PDOException $e) {
			$this->add_error($e->getMessage(), 'error');
			return false;
		}

		return true;
	}
}
