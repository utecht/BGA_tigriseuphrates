<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * TigrisEuphrates implementation : © Joseph Utecht <joseph@utecht.co>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * tigriseuphrates.game.php
 *
 * This is the main file for your game logic.
 *
 * In this PHP file, you are going to defines the rules of the game.
 *
 */

require_once APP_GAMEMODULE_PATH . 'module/table/table.game.php';

if (!defined('NO_ID')) {
	// guard since this included multiple times
	define("WAR_NO_WAR", 0);
	define("WAR_ATTACKER_SUPPORT", 1);
	define("WAR_DEFENDER_SUPPORT", 2);
	define("WAR_START", 3);
	define("NO_ID", 999);
	define("DB_UNDO_YES", 1);
	define('AWAITING_SELECTION', 0);
	define('PICK_SAME_PLAYER', 1);
	define('PICK_DIFFERENT_PLAYER', 2);
	define("OPEN_SCORING", 2);
}

class TigrisEuphrates extends Table {
	function __construct() {
		// Your global variables labels:
		//  Here, you can assign labels to global variables you are using for this game.
		//  You can use any number of global variables with IDs between 10 and 99.
		//  If your game has options (variants), you also have to associate here a label to
		//  the corresponding ID in gameoptions.inc.php.
		// Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
		parent::__construct();

		self::initGameStateLabels(array(
			"current_action_count" => 10,
			"current_attacker" => 11,
			"current_defender" => 12,
			"current_war_state" => 13,
			"original_player" => 14,
			"potential_monument_tile_id" => 15,
			"last_tile_id" => 16,
			"last_leader_id" => 17,
			"current_monument" => 18,
			"first_action_tile_id" => 19,
			"first_action_leader_id" => 20,
			"leader_x" => 21,
			"leader_y" => 22,
			"first_leader_x" => 23,
			"first_leader_y" => 24,
			"db_undo" => 25,
			"last_unification" => 26,
			"leader_selection_state" => 27,
			"game_board" => 100,
			"scoring" => 104,
			// "english_variant" = 101,
			//    "my_second_global_variable" => 11,
			//      ...
			//    "my_first_game_variant" => 100,
			//    "my_second_game_variant" => 101,
			//      ...
		));
	}

	protected function getGameName() {
		// Used for translations and stuff. Please do not modify.
		return "tigriseuphrates";
	}

	/*
		        setupNewGame:

		        This method is called only once, when a new game is launched.
		        In this method, you must setup the game according to the game rules, so that
		        the game is ready to be played.
	*/
	protected function setupNewGame($players, $options = array()) {
		// Set the colors of the players with HTML color code
		// The default below is red/green/blue/orange/brown
		// The number of colors defined here must correspond to the maximum number of players allowed for the gams
		$gameinfos = self::getGameinfos();
		$default_colors = $gameinfos['player_colors'];

		// Create players
		// Note: if you added some extra field on "player" table in the database (dbmodel.sql), you can initialize it there.
		$sql = "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ";
		$values = array();
		foreach ($players as $player_id => $player) {
			$color = array_shift($default_colors);
			$values[] = "('" . $player_id . "','$color','" . $player['player_canal'] . "','" . addslashes($player['player_name']) . "','" . addslashes($player['player_avatar']) . "')";
		}
		$sql .= implode($values, ',');
		self::DbQuery($sql);
		self::reattributeColorsBasedOnPreferences($players, $gameinfos['player_colors']);
		self::reloadPlayersBasicInfos();

		// Initialize starting points
		$sql = "INSERT INTO point (player) VALUES ";
		$values = array();
		foreach ($players as $player_id => $player) {
			$values[] = "('" . $player_id . "')";
		}
		$sql .= implode($values, ',');
		self::DbQuery($sql);

		// Create deck and shuffle
		$starting_temples = $this->starting_temples;
		if (self::getGameStateValue('game_board') == 2) {
			$starting_temples = $this->alt_starting_temples;
		}
		$all_tiles = array();
		$all_tiles = array_merge($all_tiles, array_fill(0, 57 - count($starting_temples), 'red'));
		$all_tiles = array_merge($all_tiles, array_fill(0, 30, 'black'));
		$all_tiles = array_merge($all_tiles, array_fill(0, 36, 'blue'));
		$all_tiles = array_merge($all_tiles, array_fill(0, 30, 'green'));
		shuffle($all_tiles);
		$sql = "INSERT INTO tile (id, state, owner, kind, posX, posY, hasTreasure) VALUES ";
		$values = array();
		// Give players catastrophes
		$i = 0;
		foreach ($players as $player_id => $player) {
			$values[] = "('" . $i . "','hand','" . $player_id . "','catastrophe',NULL,NULL,'0')";
			$i++;
			$values[] = "('" . $i . "','hand','" . $player_id . "','catastrophe',NULL,NULL,'0')";
			$i++;
		}
		// Put starting temples on board
		foreach ($starting_temples as $temple) {
			$values[] = "('" . $i . "','board',NULL,'red','" . $temple[0] . "','" . $temple[1] . "','1')";
			$i++;
		}
		// Draw starting hands
		foreach ($players as $player_id => $player) {
			for ($c = 0; $c < 6; $c++) {
				$color = array_shift($all_tiles);
				$values[] = "('" . $i . "','hand','" . $player_id . "','" . $color . "',NULL,NULL,'0')";
				$i++;
			}
		}
		// Insert remaining tiles into bag
		foreach ($all_tiles as $color) {
			$values[] = "('" . $i . "','bag',NULL,'" . $color . "',NULL,NULL,'0')";
			$i++;
		}
		$sql .= implode($values, ',');
		self::DbQuery($sql);

		// Assign players random starting leader
		$leader_shapes = ['bull', 'lion', 'bow', 'urn'];
		shuffle($leader_shapes);
		$leader_colors = ['blue', 'green', 'red', 'black'];
		$sql = "INSERT INTO leader (id, shape, kind, owner) VALUES ";
		$values = array();
		$i = 0;
		$player_num = 0;
		// Give each player one of each leader
		foreach ($players as $player_id => $player) {
			$shape = $leader_shapes[$player_num];
			foreach ($leader_colors as $color) {
				$values[] = "('" . $i . "','" . $shape . "','" . $color . "','" . $player_id . "')";
				$i++;
			}
			$player_num++;
		}
		$sql .= implode($values, ',');
		self::DbQuery($sql);

		// Create monuments
		$sql = "INSERT INTO monument (id, color1, color2) VALUES ";
		$values = array();
		$values[] = "('0', 'black', 'green')";
		$values[] = "('1', 'black', 'blue')";
		$values[] = "('2', 'black', 'red')";
		$values[] = "('3', 'red', 'blue')";
		$values[] = "('4', 'green', 'red')";
		$values[] = "('5', 'blue', 'green')";
		$sql .= implode($values, ',');
		self::DbQuery($sql);

		/************ Start the game initialization *****/

		// Init global values with their initial values
		self::setGameStateInitialValue('current_action_count', 1);
		self::setGameStateInitialValue('current_attacker', NO_ID);
		self::setGameStateInitialValue('current_defender', NO_ID);
		self::setGameStateInitialValue('current_war_state', WAR_NO_WAR);
		self::setGameStateInitialValue('original_player', NO_ID);
		self::setGameStateInitialValue('potential_monument_tile_id', NO_ID);
		self::setGameStateInitialValue('last_tile_id', NO_ID);
		self::setGameStateInitialValue('last_leader_id', NO_ID);
		self::setGameStateInitialValue('first_action_tile_id', NO_ID);
		self::setGameStateInitialValue('first_action_leader_id', NO_ID);
		self::setGameStateInitialValue('leader_x', NO_ID);
		self::setGameStateInitialValue('leader_y', NO_ID);
		self::setGameStateInitialValue('first_leader_x', NO_ID);
		self::setGameStateInitialValue('first_leader_y', NO_ID);
		self::setGameStateInitialValue('last_unification', NO_ID);
		self::setGameStateInitialValue('current_monument', NO_ID);
		self::setGameStateInitialValue('leader_selection_state', NO_ID);
		self::setGameStateInitialValue('db_undo', NO_ID);

		// Init game statistics
		// (note: statistics used in this file must be defined in your stats.inc.php file)
		self::initStat('table', 'turns_number', 0); // Init a table statistics
		self::initStat('table', 'winning_position', 0); // Init a table statistics
		self::initStat('player', 'turns_number', 0); // Init a player statistics (for all players)
		self::initStat('player', 'revolts_won_attacker', 0);
		self::initStat('player', 'revolts_won_defender', 0);
		self::initStat('player', 'revolts_lost_attacker', 0);
		self::initStat('player', 'revolts_lost_defender', 0);
		self::initStat('player', 'wars_won_attacker', 0);
		self::initStat('player', 'wars_won_defender', 0);
		self::initStat('player', 'wars_lost_attacker', 0);
		self::initStat('player', 'wars_lost_defender', 0);
		self::initStat('player', 'monuments_built', 0);
		self::initStat('player', 'treasure_picked_up', 0);
		self::initStat('player', 'catastrophes_placed', 0);
		self::initStat('player', 'black_points', 0);
		self::initStat('player', 'red_points', 0);
		self::initStat('player', 'blue_points', 0);
		self::initStat('player', 'green_points', 0);

		// Activate first player (which is in general a good idea :) )
		$this->activeNextPlayer();
		$player_id = self::getActivePlayerId();
		self::incStat(1, 'turns_number', $player_id);
		self::incStat(1, 'turns_number');
		self::undoSavePoint();

		/************ End of the game initialization *****/
	}

	/*
		        getAllDatas:

		        Gather all informations about current game situation (visible by the current player).

		        The method is called each time the game interface is displayed to a player, ie:
		        _ when the game starts
		        _ when a player refreshes the game page (F5)
	*/
	protected function getAllDatas() {
		$result = array();

		$current_player_id = self::getCurrentPlayerId(); // !! We must only return informations visible by this player !!

		// Get information about players
		// Note: you can retrieve some extra field you added for "player" table in "dbmodel.sql" if you need it.
		$sql = "SELECT player_id id, player_score score FROM player ";
		$result['players'] = self::getCollectionFromDb($sql);
		$result['player'] = $current_player_id;

		$result['board'] = self::getObjectListFromDB("select * from tile where state = 'board'");
		$result['hand'] = self::getObjectListFromDB("select * from tile where state = 'hand' and owner = '" . $current_player_id . "'");
		$result['support'] = self::getObjectListFromDB("select * from tile where state = 'support'");
		$result['leaders'] = self::getObjectListFromDB("select * from leader");
		$result['monuments'] = self::getObjectListFromDB("select * from monument");
		foreach ($result['leaders'] as $leader) {
			$result['players'][$leader['owner']]['shape'] = $leader['shape'];
		}
		$result['player_status'] = self::getPlayerStatus();
		$result['game_board'] = self::getGameStateValue("game_board");
		$result['scoring'] = self::getGameStateValue("scoring");
		$state = $this->gamestate->state();
		if ($result['scoring'] == OPEN_SCORING || $state['name'] == 'gameEnd') {
			$result['points'] = self::getCollectionFromDb("select * from point");
		} else {
			$result['points'] = self::getCollectionFromDb("select * from point where player = '" . $current_player_id . "'");
		}

		return $result;
	}

	/*
		        getGameProgression:

		        Compute and return the current game progression.
		        The number returned must be an integer beween 0 (=the game just started) and
		        100 (= the game is finished or almost finished).

		        This method is called each time we are in a game state with the "updateGameProgression" property set to true
		        (see states.inc.php)
	*/
	function getGameProgression() {
		$remaining_tiles = self::getUniqueValueFromDB("select count(*) from tile where state = 'bag'");
		$player_count = self::getPlayersNumber();
		$starting_temples = $this->starting_temples;
		if (self::getGameStateValue('game_board') == 2) {
			$starting_temples = $this->alt_starting_temples;
		}
		$starting_tiles = count($starting_temples) + (6 * $player_count);
		$total_tiles = (57 + 36 + 30 + 30) - $starting_tiles;

		return intval((($total_tiles - $remaining_tiles) / $total_tiles) * 100);
	}

//////////////////////////////////////////////////////////////////////////////
	//////////// Utility functions
	////////////

	/*
		        In this space, you can put any utility methods useful for your game logic
	*/

	function toCoords($x, $y) {
		$ix = intval($x);
		$iy = intval($y);
		$alphabet = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'Y', 'Z'];
		return $alphabet[$ix] . strval($iy + 1);
	}

	function score($color, $points, $player_id, $player_name, $source = false, $id = false, $animate = true) {
		self::DbQuery("
            update
                point
            set
                " . $color . " = " . $color . " + '" . $points . "'
            where
                player = '" . $player_id . "'
            ");
		self::notifyAllPlayers(
			"playerScore",
			clienttranslate('${player_name} scored ${points} <div class="point ${color}_point"></div>'),
			array(
				'player_id' => $player_id,
				'player_name' => $player_name,
				'color' => $color,
				'points' => $points,
				'source' => $source,
				'id' => $id,
				'animate' => $animate,
			)
		);
	}

	function drawTiles($count, $player_id) {

		$next_tile = self::getUniqueValueFromDB("
            select
                min(id)
            from
                tile
            where
                state = 'bag';
            ");

		$max_tile = self::getUniqueValueFromDB("select max(id) from tile");
		$top_tile = intval($next_tile) + $count;
		// final tile drawn, end game
		if ($top_tile > $max_tile) {
			self::notifyAllPlayers(
				"lastTileDrawn",
				clienttranslate('${player_name} has ended the game by drawing the last tile.'),
				array(
					'player_name' => self::getPlayerNameById($player_id),
				)
			);
			$this->gamestate->nextState("endGame");
			return;
		}

		self::DbQuery("
            update
                tile
            set
                state = 'hand',
                owner = '" . $player_id . "'
            where
                id >= '" . $next_tile . "' and
                id < '" . $top_tile . "'
            ");

		$new_tiles = self::getObjectListFromDB("
            select
                id, kind
            from tile
            where
                id >= '" . $next_tile . "' and
                id < '" . $top_tile . "'
            ");

		$player_name = self::getPlayerNameById($player_id);

		self::notifyPlayer(
			$player_id,
			"drawTiles",
			'',
			array(
				'tiles' => $new_tiles,
			)
		);
		self::disableUndo();
		self::setGameStateValue('db_undo', NO_ID);

		self::notifyAllPlayers(
			"drawTilesNotif",
			clienttranslate('${player_name} drew ${count} tiles'),
			array(
				'player_name' => $player_name,
				'count' => $count,
			)
		);
	}

	// returns the id's of neighbors in $options of $x and $y
	function findNeighbors($x, $y, $options) {
		$neighbors = array();
		$above = [$x, $y - 1];
		$below = [$x, $y + 1];
		$left = [$x - 1, $y];
		$right = [$x + 1, $y];
		foreach ($options as $option) {
			if ($above[0] == $option['posX'] && $above[1] == $option['posY']) {
				$neighbors[] = $option['id'];
			}
			if ($below[0] == $option['posX'] && $below[1] == $option['posY']) {
				$neighbors[] = $option['id'];
			}
			if ($left[0] == $option['posX'] && $left[1] == $option['posY']) {
				$neighbors[] = $option['id'];
			}
			if ($right[0] == $option['posX'] && $right[1] == $option['posY']) {
				$neighbors[] = $option['id'];
			}
		}
		return $neighbors;
	}

	// returns a kingdom array for $board and $leaders
	function findKingdoms($board, $leaders) {
		$kingdoms = array();
		$used_leaders = array();
		$used_tiles = array();

		foreach ($leaders as $leader_id => $leader) {
			$kingdom = array(
				'leaders' => array(),
				'tiles' => array(),
				'pos' => array(),
			);
			$to_test_leaders = array($leader_id);
			$to_test_tiles = array();
			while (count($to_test_leaders) > 0) {
				$leader = $leaders[array_pop($to_test_leaders)];
				if (in_array($leader['id'], $used_leaders) === false) {
					$kingdom['leaders'][$leader['id']] = $leader;
					$used_leaders[] = $leader['id'];
					$x = $leader['posX'];
					$y = $leader['posY'];
					$kingdom['pos'][] = [$x, $y];
					$potential_tiles = self::findNeighbors($x, $y, $board);
					$potential_leaders = self::findNeighbors($x, $y, $leaders);

					foreach ($potential_tiles as $ptile) {
						if ($board[$ptile]['kind'] == 'catastrophe') {
							$potential_tiles = array_diff($potential_tiles, array($ptile));
						}
						if ($board[$ptile]['isUnion'] === '1') {
							$potential_tiles = array_diff($potential_tiles, array($ptile));
						}
					}
					$to_test_tiles = array_unique(array_merge($potential_tiles, $to_test_tiles));
					$to_test_leaders = array_unique(array_merge($potential_leaders, $to_test_leaders));

					while (count($to_test_tiles) > 0) {
						$tile = $board[array_pop($to_test_tiles)];
						if (in_array($tile['id'], $used_tiles) === false) {
							$kingdom['tiles'][$tile['id']] = $tile;
							$used_tiles[] = $tile['id'];
							$x = $tile['posX'];
							$y = $tile['posY'];
							$kingdom['pos'][] = [$x, $y];
							$potential_tiles = self::findNeighbors($x, $y, $board);
							$potential_leaders = self::findNeighbors($x, $y, $leaders);

							foreach ($potential_tiles as $ptile) {
								if ($board[$ptile]['kind'] == 'catastrophe') {
									$potential_tiles = array_diff($potential_tiles, array($ptile));
								}
								if ($board[$ptile]['isUnion'] === '1') {
									$potential_tiles = array_diff($potential_tiles, array($ptile));
								}
							}
							$to_test_tiles = array_unique(array_merge($potential_tiles, $to_test_tiles));
							$to_test_leaders = array_unique(array_merge($potential_leaders, $to_test_leaders));
						}
					}
				}
			}
			if (count($kingdom['pos']) > 0) {
				$kingdoms[] = $kingdom;
			}
		}
		return $kingdoms;
	}

	// returns the index of neighboring kingdoms in $kingdoms to x and y
	function neighborKingdoms($x, $y, $kingdoms) {
		$neighbor_kingdoms = array();
		$above = [$x, $y - 1];
		$below = [$x, $y + 1];
		$left = [$x - 1, $y];
		$right = [$x + 1, $y];
		foreach ($kingdoms as $i => $kingdom) {
			if (in_array($above, $kingdom['pos'])) {
				$neighbor_kingdoms[] = $i;
			}
			if (in_array($below, $kingdom['pos'])) {
				$neighbor_kingdoms[] = $i;
			}
			if (in_array($left, $kingdom['pos'])) {
				$neighbor_kingdoms[] = $i;
			}
			if (in_array($right, $kingdom['pos'])) {
				$neighbor_kingdoms[] = $i;
			}
		}
		return array_unique($neighbor_kingdoms);
	}

	// returns true if kingdom has more than one treasure
	function kingdomHasTwoTreasures($kingdom) {
		$hasTreasure = false;
		foreach ($kingdom['tiles'] as $tile) {
			if ($tile['hasTreasure']) {
				if ($hasTreasure === true) {
					return true;
				} else {
					$hasTreasure = true;
				}
			}
		}
		return false;
	}

	// calculates the revolt board strength of $leader
	function calculateBoardStrength($leader, $board) {
		$neighbors = self::findNeighbors($leader['posX'], $leader['posY'], $board);
		$strength = 0;
		foreach ($neighbors as $tile_id) {
			if ($board[$tile_id]['kind'] == 'red') {
				$strength++;
			}
		}
		return $strength;
	}

	// calculates the war board strength of $leader
	function calculateKingdomStrength($leader, $kingdoms) {
		$strength = 0;
		foreach ($kingdoms as $kingdom) {
			if (array_key_exists($leader['id'], $kingdom['leaders'])) {
				foreach ($kingdom['tiles'] as $tile) {
					if ($tile['kind'] === $leader['kind']) {
						$strength++;
					}
				}
			}
		}
		return $strength;
	}

	// returns tile from $tiles corresponding with $x and $y pos
	function getTileXY($tiles, $x, $y) {
		foreach ($tiles as $tile) {
			if ($tile['posX'] == $x && $tile['posY'] == $y) {
				return $tile;
			}
		}
		return false;
	}

	// returns array of tiles that form an eligible monument with placed tile
	function getMonumentSquare($tiles, $tile) {
		$x = intval($tile['posX']);
		$y = intval($tile['posY']);

		$right = self::getTileXY($tiles, $x + 1, $y);
		$left = self::getTileXY($tiles, $x - 1, $y);
		$below = self::getTileXY($tiles, $x, $y + 1);
		$above = self::getTileXY($tiles, $x, $y - 1);
		$rightbelow = self::getTileXY($tiles, $x + 1, $y + 1);
		$leftbelow = self::getTileXY($tiles, $x - 1, $y + 1);
		$rightabove = self::getTileXY($tiles, $x + 1, $y - 1);
		$leftabove = self::getTileXY($tiles, $x - 1, $y - 1);

		if ($right !== false && $rightbelow !== false && $below !== false) {
			if ($right['kind'] == $tile['kind'] && $below['kind'] == $tile['kind'] && $rightbelow['kind'] == $tile['kind']) {
				return array($tile, $right, $rightbelow, $below);
			}
		}
		if ($right !== false && $rightabove !== false && $above !== false) {
			if ($right['kind'] == $tile['kind'] && $above['kind'] == $tile['kind'] && $rightabove['kind'] == $tile['kind']) {
				return array($tile, $right, $rightabove, $above);
			}
		}
		if ($left !== false && $leftbelow !== false && $below !== false) {
			if ($left['kind'] == $tile['kind'] && $below['kind'] == $tile['kind'] && $leftbelow['kind'] == $tile['kind']) {
				return array($tile, $left, $leftbelow, $below);
			}
		}
		if ($left !== false && $leftabove !== false && $above !== false) {
			if ($left['kind'] == $tile['kind'] && $above['kind'] == $tile['kind'] && $leftabove['kind'] == $tile['kind']) {
				return array($tile, $left, $leftabove, $above);
			}
		}
		return false;
	}

	// Returns number of possible monuments that can be built
	function getMonumentCount($tiles, $tile) {
		$x = intval($tile['posX']);
		$y = intval($tile['posY']);

		$right = self::getTileXY($tiles, $x + 1, $y);
		$left = self::getTileXY($tiles, $x - 1, $y);
		$below = self::getTileXY($tiles, $x, $y + 1);
		$above = self::getTileXY($tiles, $x, $y - 1);
		$rightbelow = self::getTileXY($tiles, $x + 1, $y + 1);
		$leftbelow = self::getTileXY($tiles, $x - 1, $y + 1);
		$rightabove = self::getTileXY($tiles, $x + 1, $y - 1);
		$leftabove = self::getTileXY($tiles, $x - 1, $y - 1);

		$potential_monuments = 0;

		if ($right !== false && $rightbelow !== false && $below !== false) {
			if ($right['kind'] == $tile['kind'] && $below['kind'] == $tile['kind'] && $rightbelow['kind'] == $tile['kind']) {
				$potential_monuments++;
			}
		}
		if ($right !== false && $rightabove !== false && $above !== false) {
			if ($right['kind'] == $tile['kind'] && $above['kind'] == $tile['kind'] && $rightabove['kind'] == $tile['kind']) {
				$potential_monuments++;
			}
		}
		if ($left !== false && $leftbelow !== false && $below !== false) {
			if ($left['kind'] == $tile['kind'] && $below['kind'] == $tile['kind'] && $leftbelow['kind'] == $tile['kind']) {
				$potential_monuments++;
			}
		}
		if ($left !== false && $leftabove !== false && $above !== false) {
			if ($left['kind'] == $tile['kind'] && $above['kind'] == $tile['kind'] && $leftabove['kind'] == $tile['kind']) {
				$potential_monuments++;
			}
		}
		return $potential_monuments;
	}

	// find any leaders not standing next to temples and return them to owner
	function exileOrphanedLeaders($board, $leaders) {
		foreach ($leaders as $leader) {
			$is_safe = false;
			foreach (self::findNeighbors($leader['posX'], $leader['posY'], $board) as $neighbor_id) {
				if ($board[$neighbor_id]['kind'] == 'red') {
					$is_safe = true;
				}
			}
			if ($is_safe === false) {
				self::DbQuery("
                    update
                        leader
                    set
                        onBoard = '0',
                        posX = NULL,
                        posY = NULL
                    where
                        id = '" . $leader['id'] . "'
                    ");
				self::notifyAllPlayers(
					"leaderReturned",
					clienttranslate('Building monument returned <span style="color:${color}">${leader_name}</span> ${shape}'),
					array(

						'shape' => $leader['shape'],
						'color' => $leader['kind'],
						'leader' => $leader,
						'leader_name' => $this->leaderNames[$leader['kind']],
					)
				);
			}
		}
	}

	function disableUndo() {
		self::setGameStateValue('last_tile_id', NO_ID);
		self::setGameStateValue('last_leader_id', NO_ID);
		self::setGameStateValue('first_action_tile_id', NO_ID);
		self::setGameStateValue('first_action_leader_id', NO_ID);
		self::setGameStateValue("leader_x", NO_ID);
		self::setGameStateValue("leader_y", NO_ID);
		self::setGameStateValue("first_leader_x", NO_ID);
		self::setGameStateValue("first_leader_y", NO_ID);
		self::setGameStateValue("last_unification", NO_ID);
	}

	function buildMonument($monument, $board, $tile) {
		$player_name = self::getActivePlayerName();
		$player_id = self::getActivePlayerId();
		$monument_id = $monument['id'];
		$tiles = self::getMonumentSquare($board, $tile);
		// find the top left tile and flip over tiles in DB
		$x = NO_ID;
		$y = NO_ID;
		$flip_ids = array();
		foreach ($tiles as $flip) {
			self::DbQuery("update tile set kind = 'flipped' where id = '" . $flip['id'] . "'");
			$flip_ids[] = $flip['id'];
			if ($x > $flip['posX']) {
				$x = $flip['posX'];
			}
			if ($y > $flip['posY']) {
				$y = $flip['posY'];
			}
		}
		self::disableUndo();
		self::setGameStateValue('db_undo', DB_UNDO_YES);
		// update the monument DB and notify all players
		self::DbQuery("update monument set onBoard = '1', posX = '" . $x . "', posY = '" . $y . "' where id = '" . $monument_id . "'");
		self::incStat(1, 'monuments_built', $player_id);
		self::notifyAllPlayers(
			"placeMonument",
			clienttranslate('${player_name} placed ${color1}/${color2} monument'),
			array(
				'player_name' => $player_name,
				'flip_ids' => $flip_ids,
				'color1' => $monument['color1'],
				'color2' => $monument['color2'],
				'monument_id' => $monument_id,
				'pos_x' => $x,
				'pos_y' => $y,
			)
		);

		// check for orphaned leaders and exile them
		$leaders = self::getCollectionFromDB("select * from leader where onBoard = '1'");
		$board = self::getCollectionFromDB("select * from tile where state = 'board'");
		self::exileOrphanedLeaders($board, $leaders);
		self::setGameStateValue('current_monument', NO_ID);
		self::setGameStateValue("potential_monument_tile_id", NO_ID);
		$this->gamestate->nextState("buildMonument");

	}

//////////////////////////////////////////////////////////////////////////////
	//////////// Player actions
	////////////
	function selectMonument($monument_id) {
		self::checkAction('selectMonument');
		$player_id = self::getActivePlayerId();
		$player_name = self::getActivePlayerName();
		$potential_monument_tile_id = self::getGameStateValue("potential_monument_tile_id");

		$monument = self::getObjectFromDB("select * from monument where id = '" . $monument_id . "'");
		$tile = self::getObjectFromDB("select * from tile where id = '" . $potential_monument_tile_id . "'");
		$board = self::getCollectionFromDB("select * from tile where state = 'board'");

		// check if monument is correct color
		if ($monument['color1'] != $tile['kind'] and $monument['color2'] != $tile['kind']) {
			throw new BgaUserException(clienttranslate("Must select a monument of the correct color"));
		}

		$monument_count = self::getMonumentCount($board, $tile);
		if ($monument_count > 1) {
			self::setGameStateValue('current_monument', $monument_id);
			$this->gamestate->nextState("multiMonument");
		} else {
			self::buildMonument($monument, $board, $tile);
		}
	}

	function selectMonumentTile($pos_x, $pos_y) {
		self::checkAction('selectMonumentTile');
		$player_id = self::getActivePlayerId();
		$player_name = self::getActivePlayerName();

		$monument_id = self::getGameStateValue('current_monument');
		$monument = self::getObjectFromDB("select * from monument where id = '" . $monument_id . "'");
		$board = self::getCollectionFromDB("select * from tile where state = 'board'");
		$tile = self::getTileXY($board, $pos_x, $pos_y);
		if ($tile === false) {
			throw new BgaUserException(self::_("Must select a tile"));
		}
		$potential_monument_tile_id = self::getGameStateValue("potential_monument_tile_id");

		$monument_square = self::getMonumentSquare($board, $tile);
		$valid = false;
		$low_x = NO_ID;
		$low_y = NO_ID;
		if ($monument_square !== false) {
			foreach ($monument_square as $square) {
				if ($square['id'] == $potential_monument_tile_id) {
					$valid = true;
				}
				if ($square['posX'] < $low_x) {
					$low_x = $square['posX'];
				}
				if ($square['posY'] < $low_y) {
					$low_y = $square['posY'];
				}
			}
		}
		if ($valid === false) {
			throw new BgaUserException(self::_("Must pick a valid tile for building momnument"));
		}
		if ($pos_x != $low_x || $pos_y != $low_y) {
			throw new BgaUserException(self::_("Must pick the top left tile"));
		}
		self::buildMonument($monument, $board, $tile);
	}

	function placeSupport($support_ids) {
		self::checkAction('placeSupport');
		// note a pass has no support ids
		$player_id = self::getActivePlayerId();
		$player_name = self::getActivePlayerName();
		$hand = self::getCollectionFromDB("select * from tile where owner = '" . $player_id . "' and state = 'hand'");
		$attacker_id = self::getGameStateValue("current_attacker");
		$attacker = self::getObjectFromDB("select * from leader where id = '" . $attacker_id . "'");

		// determine correct support color
		$war_color = $attacker['kind'];
		if ($this->gamestate->state()['name'] == "supportRevolt") {
			$war_color = 'red';
		}

		// check if support ids are valid
		foreach ($support_ids as $tile_id) {
			if (array_key_exists($tile_id, $hand) == false) {
				throw new BgaVisibleSystemException(self::_("Attempt to support with tiles not in hand, reload"));
			}
			if ($hand[$tile_id]['kind'] != $war_color) {
				throw new BgaUserException(self::_("Support must match color of leader"));
			}
		}

		// determine which side the support is being played on
		$side = 'defender';
		if ($player_id == $attacker['owner']) {
			$side = 'attacker';
		}

		self::disableUndo();
		self::setGameStateValue("leader_selection_state", AWAITING_SELECTION);

		// update their location to support
		foreach ($support_ids as $tile_id) {
			self::DbQuery("
                update
                    tile
                set
                    state = 'support'
                where
                    id = '" . $tile_id . "'
                ");
		}
		self::notifyAllPlayers(
			"placeSupport",
			clienttranslate('${player_name} placed ${number} support'),
			array(
				'player_name' => $player_name,
				'player_id' => $player_id,
				'tile_ids' => $support_ids,
				'number' => count($support_ids),
				'side' => $side,
				'kind' => $war_color,
			)
		);
		$this->gamestate->nextState("placeSupport");
	}

	function discard($discard_ids) {
		self::checkAction('discard');
		$player_id = self::getActivePlayerId();
		$player_name = self::getActivePlayerName();
		$hand = self::getCollectionFromDB("select * from tile where owner = '" . $player_id . "' and state = 'hand'");

		// check if discard ids are valid
		foreach ($discard_ids as $tile_id) {
			if (array_key_exists($tile_id, $hand) == false) {
				throw new BgaVisibleSystemException(self::_("Attempt to discard tiles not in hand, reload"));
			}
			if ($hand[$tile_id]['kind'] == 'catastrophe') {
				throw new BgaUserException(self::_("You cannot discard catastrophe tiles"));
			}
		}

		// update DB and notify players
		$tile_string = implode($discard_ids, ',');
		self::DbQuery("update tile set state = 'discard', owner = NULL where id in (" . $tile_string . ")");

		self::notifyPlayer(
			$player_id,
			"discard",
			clienttranslate('Discarding tiles'),
			array(
				'tile_ids' => $discard_ids,
			)
		);

		self::notifyAllPlayers(
			"discardNotif",
			clienttranslate('${player_name} discarded ${count} tiles'),
			array(
				'player_name' => $player_name,
				'count' => count($discard_ids),
			)
		);

		// refill hand
		$this->drawTiles(count($discard_ids), $player_id);

		// move to next action
		$this->gamestate->nextState("nextAction");

	}

	function placeTile($tile_id, $pos_x, $pos_y) {
		self::checkAction('placeTile');
		$player_id = self::getActivePlayerId();
		$player_name = self::getActivePlayerName();

		// Check if tile is in players hand
		$hand = self::getCollectionFromDB("select * from tile where owner = '" . $player_id . "' and state = 'hand'");
		if (array_key_exists($tile_id, $hand) == false) {
			throw new BgaVisibleSystemException(self::_("Attempt to place tile not currently in hand, reload"));
		}
		$kind = $hand[$tile_id]['kind'];
		$new_tile = $hand[$tile_id];
		$new_tile['posX'] = $pos_x;
		$new_tile['posY'] = $pos_y;

		$board = self::getCollectionFromDB("select * from tile where state = 'board'");
		$leaders = self::getCollectionFromDB("select * from leader where onBoard = '1'");

		// check if tile lay is valid
		foreach ($board as $tile) {
			if ($pos_x == $tile['posX'] && $pos_y == $tile['posY']) {
				if ($kind != 'catastrophe') {
					throw new BgaUserException(self::_("Only a catastrophe may be played over another tile"));
				} else {
					if ($tile['hasTreasure'] == '1') {
						throw new BgaUserException(self::_("A catastrophe cannot be placed on an Treasure"));
					}
					if ($tile['kind'] == 'flipped') {
						throw new BgaUserException(self::_("A catastrophe cannot be placed on a monument"));
					}
				}
			}
		}
		foreach ($leaders as $leader) {
			if ($pos_x == $leader['posX'] && $pos_y == $leader['posY']) {
				throw new BgaUserException(self::_("No tile may be placed over a leader"));
			}
		}

		// Check for blue validity
		if ($kind != 'catastrophe') {
			$valid_blue = $kind != 'blue';
			$rivers = $this->rivers;
			if (self::getGameStateValue('game_board') == 2) {
				$rivers = $this->alt_rivers;
			}
			foreach ($rivers as $river_tile) {
				if ($pos_x == $river_tile['posX'] && $pos_y == $river_tile['posY']) {
					if ($kind != 'blue') {
						throw new BgaUserException(self::_("Only blue may be placed on rivers"));
					} else {
						$valid_blue = true;
					}
				}
			}
			if ($valid_blue === false) {
				throw new BgaUserException(self::_("Blue may only be placed on rivers"));
			}
		}

		self::setGameStateValue('db_undo', NO_ID);
		// handle catastrophe and return
		if ($kind == 'catastrophe') {
			$existing_tile = self::getTileXY($board, $pos_x, $pos_y);
			$removed_leaders = array();
			if ($existing_tile !== false) {
				// notify players to remove tile
				if ($existing_tile['kind'] == 'red') {
					foreach (self::findNeighbors($pos_x, $pos_y, $leaders) as $nl_id) {
						$neighboring_leader = $leaders[$nl_id];
						$safe = false;
						foreach (self::findNeighbors($neighboring_leader['posX'], $neighboring_leader['posY'], $board) as $nt_id) {
							$neighbor_tile = $board[$nt_id];
							if ($neighbor_tile['kind'] == 'red' && $neighbor_tile['id'] != $existing_tile['id']) {
								$safe = true;
							}
						}
						// remove this leader
						if ($safe === false) {
							$removed_leaders[] = $neighboring_leader;
							self::DbQuery("
                                update
                                    leader
                                set
                                    onBoard = '0',
                                    posX = NULL,
                                    posY = NULL
                                where
                                    id = '" . $neighboring_leader['id'] . "'
                                ");
						}
					}
				}
				self::DbQuery("
                    update
                        tile
                    set
                        state = 'discard',
                        owner = NULL,
                        posX = NULL,
                        posY = NULL
                    where
                        posX = '" . $pos_x . "' and
                        posY = '" . $pos_y . "'
                    ");
			}
			self::DbQuery("
                update
                    tile
                set
                    state = 'board',
                    owner = NULL,
                    posX = '" . $pos_x . "',
                    posY = '" . $pos_y . "'
                where
                    id = '" . $tile_id . "'
                ");
			self::incStat(1, 'catastrophes_placed', $player_id);
			self::notifyAllPlayers(
				"catastrophe",
				clienttranslate('${player_name} placed <span style="color:gold">Catastrophe</span> at ${coords} exiling ${count} leaders'),
				array(
					'player_name' => $player_name,
					'player_id' => $player_id,
					'x' => $pos_x,
					'y' => $pos_y,
					'coords' => self::toCoords($pos_x, $pos_y),
					'removed_tile' => $existing_tile,
					'count' => count($removed_leaders),
					'removed_leaders' => $removed_leaders,
					'catastrophe' => $new_tile,
				)
			);
			self::disableUndo();
			self::setGameStateValue('db_undo', DB_UNDO_YES);
			$this->gamestate->nextState("safeNoMonument");
			return;
		}

		// check for wars
		$kingdoms = self::findKingdoms($board, $leaders);
		$neighbor_kingdoms = self::neighborKingdoms($pos_x, $pos_y, $kingdoms);

		if (count($neighbor_kingdoms) > 2 and $kind != 'catastrophe') {
			throw new BgaUserException(self::_("A tile cannot join 3 kingdoms"));
		}

		$is_union = count($neighbor_kingdoms) == 2 and $kind != 'catastrophe';
		if ($is_union) {
			self::setGameStateValue("original_player", $player_id);
			self::setGameStateValue("current_war_state", WAR_START);
			self::setGameStateValue('last_tile_id', $tile_id);
			self::setGameStateValue('last_leader_id', NO_ID);
			self::setGameStateValue("last_unification", $tile_id);

			self::DbQuery("
                update
                    tile
                set
                    state = 'board',
                    owner = NULL,
                    isUnion = '1',
                    posX = '" . $pos_x . "',
                    posY = '" . $pos_y . "'
                where
                    id = '" . $tile_id . "';
                ");
			self::notifyAllPlayers(
				"placeTile",
				clienttranslate('${player_name} placed <span style="color:${war_color}">${tile_name}</span> at ${coords} uniting two kingdoms'),
				array(
					'player_name' => $player_name,
					'player_id' => $player_id,
					'tile_id' => $tile_id,
					'x' => $pos_x,
					'y' => $pos_y,
					'coords' => self::toCoords($pos_x, $pos_y),
					'color' => 'union',
					'war_color' => $kind,
					'tile_name' => $this->tileNames[$kind],
				)
			);
			$this->gamestate->nextState("warFound");
			return;
		}

		// Place tile in DB and notify players
		self::DbQuery("
            update
                tile
            set
                state = 'board',
                owner = NULL,
                posX = '" . $pos_x . "',
                posY = '" . $pos_y . "'
            where
                id = '" . $tile_id . "';
            ");

		self::notifyAllPlayers(
			"placeTile",
			clienttranslate('${player_name} placed <span style="color:${color}">${tile_name}</span> at ${coords}'),
			array(
				'player_name' => $player_name,
				'player_id' => $player_id,
				'tile_id' => $tile_id,
				'x' => $pos_x,
				'y' => $pos_y,
				'coords' => self::toCoords($pos_x, $pos_y),
				'color' => $kind,
				'tile_name' => $this->tileNames[$kind],
			)
		);

		self::setGameStateValue('last_tile_id', $tile_id);
		self::setGameStateValue('last_leader_id', NO_ID);

		if (count($neighbor_kingdoms) == 1 && $kind != 'catastrophe') {
			$scoring_kingdom = $kingdoms[$neighbor_kingdoms[0]];
			foreach ($scoring_kingdom['leaders'] as $scoring_leader) {
				$score_id = false;
				if ($scoring_leader['kind'] == $kind) {
					$score_id = $scoring_leader['id'];
				} else if ($scoring_leader['kind'] == 'black') {
					$score_id = $scoring_leader['id'];
					foreach ($scoring_kingdom['leaders'] as $other_leader) {
						if ($other_leader['kind'] == $kind) {
							$score_id = false;
						}
					}
				}
				if ($score_id !== false) {
					$scorer_name = self::getPlayerNameById($scoring_leader['owner']);
					self::score($kind, 1, $scoring_leader['owner'], $scorer_name, 'leader', $score_id);
				}
			}
		}

		$monument_count = self::getMonumentCount($board, $new_tile);
		$remaining_monuments = self::getUniqueValueFromDB("select count(*) from monument where onBoard = '0'");
		if ($remaining_monuments > 0 && $monument_count > 0) {
			self::setGameStateValue("potential_monument_tile_id", $new_tile['id']);
			self::giveExtraTime($player_id);
			self::undoSavePoint();
			$this->gamestate->nextState("safeMonument");
		} else {
			$this->gamestate->nextState("safeNoMonument");
		}
	}

	function placeLeader($leader_id, $pos_x, $pos_y) {
		self::checkAction('placeLeader');
		$player_id = self::getActivePlayerId();
		$player_name = self::getActivePlayerName();
		$board = self::getCollectionFromDB("select * from tile where state = 'board'");
		$leaders = self::getCollectionFromDB("select * from leader");
		$leader = $leaders[$leader_id];

		// check if leader is in hand and owned
		if ($leader['owner'] != $player_id) {
			throw new BgaVisibleSystemException(self::_("Attempt to play a leader you don't own, reload"));
		}
		// This rule is incorrect
		/*
	        if($leader['onBoard'] == '1'){
	           throw new BgaVisibleSystemException(self::_("Attempt to play leader not in hand, reload"));
	        }
*/
		if ($leader['posX'] == $pos_x && $leader['posY'] == $pos_y) {
			throw new BgaVisibleSystemException(self::_("You must move a leader somewhere else"));
		}

		$moved = $leader['onBoard'] == '1';
		if ($moved) {
			self::setGameStateValue('leader_x', $leader['posX']);
			self::setGameStateValue('leader_y', $leader['posY']);
		} else {
			self::setGameStateValue('leader_x', NO_ID);
			self::setGameStateValue('leader_y', NO_ID);
		}

		// check if placement valid
		// leaders cannot be ontop of tiles
		foreach ($board as $tile) {
			if ($pos_x == $tile['posX'] && $pos_y == $tile['posY']) {
				throw new BgaUserException(self::_("Leaders must be placed on a blank space"));
			}
		}
		// leaders cannot be ontop of other leaders
		foreach ($leaders as $other_leader) {
			if ($pos_x == $other_leader['posX'] && $pos_y == $other_leader['posY']) {
				throw new BgaUserException(self::_("Leaders must be placed on a blank space"));
			}
		}
		// leaders cannot be in rivers
		$rivers = $this->rivers;
		if (self::getGameStateValue('game_board') == 2) {
			$rivers = $this->alt_rivers;
		}
		foreach ($rivers as $river_tile) {
			if ($pos_x == $river_tile['posX'] && $pos_y == $river_tile['posY']) {
				throw new BgaUserException(self::_("Leaders may not be placed on rivers"));
			}
		}

		// leaders must be adjacent to temples
		$x = intval($pos_x);
		$y = intval($pos_y);
		$above = [$x, $y - 1];
		$below = [$x, $y + 1];
		$left = [$x - 1, $y];
		$right = [$x + 1, $y];
		$valid = false;
		foreach ($board as $tile) {
			if ($above[0] == $tile['posX'] && $above[1] == $tile['posY'] && $tile['kind'] == 'red') {
				$valid = true;
			}
			if ($below[0] == $tile['posX'] && $below[1] == $tile['posY'] && $tile['kind'] == 'red') {
				$valid = true;
			}
			if ($left[0] == $tile['posX'] && $left[1] == $tile['posY'] && $tile['kind'] == 'red') {
				$valid = true;
			}
			if ($right[0] == $tile['posX'] && $right[1] == $tile['posY'] && $tile['kind'] == 'red') {
				$valid = true;
			}
		}
		if ($valid == false) {
			throw new BgaUserException(self::_("Leaders must be placed adjacent to temple (red)"));
		}

		$leaders[$leader_id]['onBoard'] = 0;
		$leaders[$leader_id]['posX'] = NO_ID;
		$leaders[$leader_id]['posY'] = NO_ID;
		// leaders cannot join kingdoms
		$kingdoms = self::findKingdoms($board, $leaders);
		$neighbor_kingdoms = self::neighborKingdoms($pos_x, $pos_y, $kingdoms);
		if (count($neighbor_kingdoms) > 1) {
			throw new BgaUserException(self::_("A leader may not join kingdoms"));
		}

		// check for revolt
		$start_revolt = false;
		if (count($neighbor_kingdoms) == 1) {
			foreach ($kingdoms[$neighbor_kingdoms[0]]['leaders'] as $neighbor_leader) {
				if ($neighbor_leader['kind'] == $leader['kind'] && $neighbor_leader['id'] != $leader['id']) {
					$start_revolt = true;
					self::setGameStateValue("current_attacker", $leader_id);
					self::setGameStateValue("current_defender", $neighbor_leader['id']);
					self::setGameStateValue("current_war_state", WAR_ATTACKER_SUPPORT);
				}
			}
		}

		self::setGameStateValue('db_undo', NO_ID);
		self::setGameStateValue('last_tile_id', NO_ID);
		self::setGameStateValue('last_leader_id', $leader_id);

		// update leader in DB
		self::DbQuery("
            update
                leader
            set
                onBoard = '1',
                posX = '" . $pos_x . "',
                posY = '" . $pos_y . "'
            where
                id = '" . $leader_id . "';
            ");

		// notify players
		if ($start_revolt) {
			self::notifyAllPlayers(
				"placeLeader",
				clienttranslate('${player_name} placed <span style="color:${color}">${leader_name}</span> at ${coords} and started revolt'),
				array(
					'player_name' => $player_name,
					'player_id' => $player_id,
					'leader_id' => $leader_id,
					'x' => $pos_x,
					'y' => $pos_y,
					'coords' => self::toCoords($pos_x, $pos_y),
					'color' => $leader['kind'],
					'shape' => $leader['shape'],
					'moved' => $moved,
					'leader_name' => $this->leaderNames[$leader['kind']],
				)
			);
			self::giveExtraTime($player_id);
			$this->gamestate->nextState("placeRevoltSupport");
		} else {
			self::notifyAllPlayers(
				"placeLeader",
				clienttranslate('${player_name} placed <span style="color:${color}">${leader_name}</span> at ${coords}'),
				array(
					'player_name' => $player_name,
					'player_id' => $player_id,
					'leader_id' => $leader_id,
					'x' => $pos_x,
					'y' => $pos_y,
					'coords' => self::toCoords($pos_x, $pos_y),
					'color' => $leader['kind'],
					'shape' => $leader['shape'],
					'moved' => $moved,
					'leader_name' => $this->leaderNames[$leader['kind']],
				)
			);
			$this->gamestate->nextState("safeLeader");
		}
	}

	function pickupLeader($leader_id) {
		self::checkAction('pickupLeader');
		$player_id = self::getActivePlayerId();
		$player_name = self::getActivePlayerName();
		$leader = self::getObjectFromDB("select * from leader where id = '" . $leader_id . "'");

		// check if leader owned and on board
		if ($player_id != $leader['owner']) {
			throw new BgaVisibleSystemException(self::_("Attempt to return leader you do not own, reload"));
		}
		if ($leader['onBoard'] != '1') {
			throw new BgaUserException(self::_("Attempt to return leader not on board"));
		}

		self::setGameStateValue('last_tile_id', NO_ID);
		self::setGameStateValue('last_leader_id', $leader['id']);
		self::setGameStateValue('leader_x', $leader['posX']);
		self::setGameStateValue('leader_y', $leader['posY']);

		// update DB and notify players
		self::DbQuery("update leader set onBoard='0', posX = NULL, posY = NULL where id = '" . $leader_id . "'");
		self::notifyAllPlayers(
			"leaderReturned",
			clienttranslate('${player_name} picked up ${leader_name}'),
			array(
				'player_name' => $player_name,
				'color' => $leader['kind'],
				'leader' => $leader,
				'leader_name' => $this->leaderNames[$leader['kind']],
			)
		);
		$this->gamestate->nextState('nextAction');
	}

	function selectWarLeader($leader_id) {
		self::checkAction('selectWarLeader');
		$player_id = self::getActivePlayerId();
		$player_name = self::getActivePlayerName();
		$board = self::getCollectionFromDB("select * from tile where state = 'board'");
		$leaders = self::getCollectionFromDB("select * from leader where onBoard = '1'");

		$kingdoms = self::findKingdoms($board, $leaders);

		// find warring kingdoms
		$union_tile = false;
		foreach ($board as $tile) {
			if ($tile['isUnion'] === '1') {
				$union_tile = $tile;
			}
		}
		if ($union_tile === false) {
			throw new BgaVisibleSystemException(self::_("Game is in bad state (no union tile), reload"));
		}
		$warring_kingdoms = self::neighborKingdoms($union_tile['posX'], $union_tile['posY'], $kingdoms);

		if (count($warring_kingdoms) != 2) {
			throw new BgaVisibleSystemException(self::_("Game is in bad state (no warring kingdoms), reload"));
		}

		// find all potential leaders
		$warring_leader_ids = array();
		$potential_war_leaders = array_merge($kingdoms[array_pop($warring_kingdoms)]['leaders'], $kingdoms[array_pop($warring_kingdoms)]['leaders']);
		foreach ($potential_war_leaders as $pleader) {
			foreach ($potential_war_leaders as $oleader) {
				if ($oleader['kind'] == $pleader['kind'] && $oleader['id'] != $pleader['id']) {
					$warring_leader_ids[] = $oleader['id'];
					$warring_leader_ids[] = $pleader['id'];
				}
			}
		}
		$warring_leader_ids = array_unique($warring_leader_ids);
		$valid_leader = false;

		// make sure leader is a potential warring leader
		$attacking_leader = false;
		foreach ($warring_leader_ids as $wleader_id) {
			if ($wleader_id == $leader_id) {
				$valid_leader = true;
				$attacking_leader = $leaders[$wleader_id];
			}
		}
		if ($valid_leader === false) {
			throw new BgaUserException(self::_("You must select a leader in the kingdoms currently at war"));
		}
		// find the opposing leader
		$defending_leader = false;
		foreach ($potential_war_leaders as $dleader) {
			if ($dleader['kind'] == $attacking_leader['kind'] && $dleader['id'] != $attacking_leader['id']) {
				$defending_leader = $dleader;
			}
		}
		// active played clicked on opposing leader to start war
		if ($defending_leader['owner'] == $player_id) {
			$swap = $attacking_leader;
			$attacking_leader = $defending_leader;
			$defending_leader = $swap;
		}

		self::setGameStateValue('db_undo', NO_ID);
		// notify players
		self::notifyAllPlayers(
			"leaderSelected",
			clienttranslate('${player_name} selected ${leader_name} for war'),
			array(
				'player_name' => $player_name,
				'color' => $attacking_leader['kind'],
				'leader_name' => $this->leaderNames[$attacking_leader['kind']],
			)
		);
		// update the state values
		self::setGameStateValue("current_attacker", $attacking_leader['id']);
		self::setGameStateValue("current_defender", $defending_leader['id']);
		if ($attacking_leader['owner'] == $player_id) {
			self::setGameStateValue("current_war_state", WAR_ATTACKER_SUPPORT);
			self::setGameStateValue("leader_selection_state", PICK_SAME_PLAYER);
			$this->gamestate->nextState('placeSupport');
		} else {
			self::setGameStateValue("current_war_state", WAR_START);
			self::setGameStateValue("leader_selection_state", PICK_DIFFERENT_PLAYER);
			$this->gamestate->nextState('leaderSelected');
		}
	}

	function pickTreasure($x, $y) {
		self::checkAction('pickTreasure');
		$player_id = self::getActivePlayerId();
		$player_name = self::getActivePlayerName();
		$board = self::getCollectionFromDB("select * from tile where state = 'board'");
		$leaders = self::getCollectionFromDB("select * from leader where onBoard = '1'");
		$kingdoms = self::findKingdoms($board, $leaders);

		// find all kingdoms with green leaders
		$outer_temple = false;
		$outer_temples = $this->outerTemples;
		if (self::getGameStateValue('game_board') == 2) {
			$outer_temples = $this->alt_outerTemples;
		}
		foreach ($kingdoms as $kingdom) {
			$green_leader_id = false;
			foreach ($kingdom['leaders'] as $leader) {
				if ($leader['kind'] == 'green' && $leader['owner'] == $player_id) {
					$green_leader_id = $leader['id'];
				}
			}
			// make sure said kingdom has two treasures
			if ($green_leader_id !== false && self::kingdomHasTwoTreasures($kingdom)) {
				$has_mandatory = false;
				// check to see if any treasure are on outer tiles
				foreach ($kingdom['tiles'] as $tile) {
					foreach ($outer_temples as $ot) {
						if ($tile['posX'] === $ot['posX'] && $tile['posY'] === $ot['posY'] && $tile['hasTreasure']) {
							$has_mandatory = true;
							$outer_temple = true;
						}
					}
				}
				// if kingdom had mandatory tile, make sure player selected it
				foreach ($kingdom['tiles'] as $tile) {
					$is_mandatory = false;
					foreach ($outer_temples as $ot) {
						if ($tile['posX'] === $ot['posX'] && $tile['posY'] === $ot['posY'] && $tile['hasTreasure']) {
							$is_mandatory = true;
						}
					}

					// for now disable undo in that state
					self::setGameStateValue("last_tile_id", NO_ID);
					self::setGameStateValue("last_leader_id", NO_ID);

					// update db and notify players
					if ($tile['posX'] === $x && $tile['posY'] === $y &&
						$tile['hasTreasure'] &&
						($has_mandatory === $is_mandatory)) {
						self::DbQuery("
                            update
                                point
                            set
                                treasure = treasure + 1
                            where
                                player = '" . $player_id . "'
                            ");
						$tile_id = $tile['id'];
						self::DbQuery("
                            update
                                tile
                            set
                                hasTreasure = '0'
                            where
                                id = '" . $tile_id . "'
                            ");
						self::incStat(1, 'treasure_picked_up', $player_id);
						self::notifyAllPlayers(
							"pickedTreasure",
							clienttranslate('${player_name} scored 1 <div class="point ${color}_point"></div>'),
							array(
								'player_name' => $player_name,
								'player_id' => $player_id,
								'color' => 'treasure',
								'tile_id' => $tile_id,
							)
						);
						$this->gamestate->nextState('pickTreasure');
						return;
					}
				}
			}
		}
		// throw exception for outer temple not selected
		if ($outer_temple === true) {
			throw new BgaUserException(self::_("Must take treasures from outer temples first"));
		}
		throw new BgaUserException(self::_("Not a valid treasure"));
	}

	function pass() {
		self::checkAction('pass');
		$this->gamestate->nextState('pass');
	}

	function undoLeader($player_id, $last_leader_id) {
		$leader = self::getObjectFromDB("select * from leader where id = '" . $last_leader_id . "'");
		$last_leader_x = self::getGameStateValue('leader_x');
		$last_leader_y = self::getGameStateValue('leader_y');
		if ($last_leader_x == NO_ID) {
			self::DbQuery("
                update
                    leader
                set
                    onBoard = '0',
                    posX = NULL,
                    posY = NULL
                where
                    id = '" . $leader['id'] . "'
                ");
			self::notifyAllPlayers(
				"leaderReturned",
				clienttranslate('Undo previous leader placement'),
				array(
					'shape' => $leader['shape'],
					'color' => $leader['kind'],
					'leader' => $leader,
					'undo' => true,
				)
			);
		} else {
			$moved = $leader['onBoard'] == '1';
			$existing_leader = self::getObjectFromDB("select * from leader where posX = '" . $last_leader_x . "' and posY = '" . $last_leader_y . "'");
			if ($existing_leader != null) {
				throw new BgaVisibleSystemException(self::_("Undo is in bad state (leader stacking), reload"));
			}
			self::DbQuery("
                update
                    leader
                set
                    onBoard = '1',
                    posX = '" . $last_leader_x . "',
                    posY = '" . $last_leader_y . "'
                where
                    id = '" . $leader['id'] . "'
                ");
			self::notifyAllPlayers(
				"placeLeader",
				clienttranslate('Undo previous leader movement'),
				array(
					'player_id' => $player_id,
					'leader_id' => $leader['id'],
					'x' => $last_leader_x,
					'y' => $last_leader_y,
					'color' => $leader['kind'],
					'shape' => $leader['shape'],
					'moved' => $moved,
					'leader_name' => $leader['kind'],
					'undo' => true,
				)
			);
		}
	}

	function undoTile($player_id, $last_tile_id) {
		$tile = self::getObjectFromDB("select * from tile where id='" . $last_tile_id . "'");

		// if tile is not a union, claw back all scoring
		if ($tile['isUnion'] == '0' && self::getGameStateValue('last_unification') != $last_tile_id) {
			$board = self::getCollectionFromDB("select * from tile where state = 'board'");
			$leaders = self::getCollectionFromDB("select * from leader where onBoard = '1'");
			$kingdoms = self::findKingdoms($board, $leaders);
			$neighbor_kingdoms = self::neighborKingdoms($tile['posX'], $tile['posY'], $kingdoms);
			// claw back scoring
			if (count($neighbor_kingdoms) == 1 && $tile['kind'] != 'catastrophe') {
				$scoring_kingdom = $kingdoms[$neighbor_kingdoms[0]];
				foreach ($scoring_kingdom['leaders'] as $scoring_leader) {
					$score_id = false;
					if ($scoring_leader['kind'] == $tile['kind']) {
						$score_id = $scoring_leader['id'];
					} else if ($scoring_leader['kind'] == 'black') {
						$score_id = $scoring_leader['id'];
						foreach ($scoring_kingdom['leaders'] as $other_leader) {
							if ($other_leader['kind'] == $tile['kind']) {
								$score_id = false;
							}
						}
					}
					if ($score_id !== false) {
						$scorer_name = self::getPlayerNameById($scoring_leader['owner']);
						self::score($tile['kind'], -1, $scoring_leader['owner'], $scorer_name, 'leader', $score_id);
					}
				}
			}
		}
		self::DbQuery("
            update
                tile
            set
                state = 'hand',
                posX = NULL,
                posY = NULL,
                owner = '" . $player_id . "',
                isUnion = '0'
            where
                id = '" . $last_tile_id . "'
            ");
		self::notifyAllPlayers(
			"tileReturned",
			clienttranslate('Undo previous tile placement'),
			array(
				'tile_id' => $last_tile_id,
			)
		);
		self::notifyPlayer(
			$player_id,
			"drawTiles",
			clienttranslate('Returning previous tile to hand'),
			array(
				'tiles' => array($tile),
			)
		);
	}

	// attempts to undo the previous action, if possible
	function undo() {
		self::checkAction('undo');
		$player_id = self::getActivePlayerId();
		if (self::getGameStateValue('db_undo') == DB_UNDO_YES) {
			self::undoRestorePoint();
			return;
		}

		if (self::getGameStateValue("leader_selection_state") == PICK_SAME_PLAYER) {
			self::setGameStateValue("leader_selection_state", AWAITING_SELECTION);
			self::notifyAllPlayers(
				"leaderUnSelected",
				clienttranslate('${player_name} unselected leader'),
				array(
					'player_name' => self::getPlayerNameById($player_id),
				)
			);
			// update the state values
			self::setGameStateValue("current_war_state", WAR_START);
			self::setGameStateValue("current_attacker", NO_ID);
			self::setGameStateValue("current_defender", NO_ID);
			$this->gamestate->nextState("unpickLeader");
			return;
		}

		$last_leader_id = self::getGameStateValue('last_leader_id');
		if ($last_leader_id != NO_ID) {
			self::undoLeader($player_id, $last_leader_id);
		}

		$last_tile_id = self::getGameStateValue('last_tile_id');
		if ($last_tile_id != NO_ID) {
			self::undoTile($player_id, $last_tile_id);
		}

		$action_count = self::getGameStateValue('current_action_count');

		$first_leader = self::getGameStateValue("first_action_leader_id");
		$first_tile = self::getGameStateValue("first_action_tile_id");
		$first_x = self::getGameStateValue("first_leader_x");
		$first_y = self::getGameStateValue("first_leader_y");
		self::setGameStateValue("last_tile_id", $first_tile);
		self::setGameStateValue("last_leader_id", $first_leader);
		self::setGameStateValue("leader_x", $first_x);
		self::setGameStateValue("leader_y", $first_y);

		if ($this->gamestate->state()['name'] == "playerTurn" && $action_count == 2) {
			self::setGameStateValue("current_action_count", 1);
			self::disableUndo();
		}
		if ($action_count == 3) {
			self::setGameStateValue("current_action_count", 2);
		}

		self::setGameStateValue("current_war_state", WAR_NO_WAR);
		self::setGameStateValue("current_attacker", NO_ID);
		self::setGameStateValue("current_defender", NO_ID);
		$this->gamestate->nextState('undo');
	}

	function confirm() {
		$this->gamestate->nextState("endTurn");
	}

//////////////////////////////////////////////////////////////////////////////
	//////////// Game state arguments
	////////////

	/*
		        Here, you can create methods defined as "game state arguments" (see "args" property in states.inc.php).
		        These methods function is to return some additional information that is specific to the current
		        game state.
	*/

	function getPlayerStatus() {
		$player_points = array();
		$leaders = self::getObjectListFromDB("select * from leader");
		foreach ($leaders as $leader) {
			$player_points[$leader['owner']]['shape'] = $leader['shape'];
		}
		$catastrophe_count = self::getCollectionFromDB("select owner, count(*) as c from tile where state = 'hand' and kind = 'catastrophe' group by owner");
		foreach ($catastrophe_count as $owner => $count) {
			$player_points[$owner]['catastrophe_count'] = $count['c'];
		}
		$hand_count = self::getCollectionFromDB("select owner, count(*) as c from tile where state = 'hand' and kind != 'catastrophe' group by owner");
		foreach ($hand_count as $owner => $count) {
			$player_points[$owner]['hand_count'] = $count['c'];
		}
		// add default values
		foreach ($player_points as $player_id => $status) {
			if (array_key_exists("catastrophe_count", $player_points[$player_id]) === false) {
				$player_points[$player_id]['catastrophe_count'] = 0;
			}
			if (array_key_exists("hand_count", $player_points[$player_id]) === false) {
				$player_points[$player_id]['hand_count'] = 0;
			}
		}
		return $player_points;
	}

	function canUndo() {
		return (self::getGameStateValue('last_tile_id') != NO_ID ||
			self::getGameStateValue('last_leader_id') != NO_ID ||
			self::getGameStateValue('db_undo') == DB_UNDO_YES ||
			self::getGameStateValue('leader_selection_state') == PICK_SAME_PLAYER);
	}

	function arg_pickWarLeader() {
		$player_id = self::getActivePlayerId();
		$board = self::getCollectionFromDB("select * from tile where state = 'board'");
		$leaders = self::getCollectionFromDB("select * from leader where onBoard = '1'");
		$kingdoms = self::findKingdoms($board, $leaders);
		$small_kingdoms = array();
		foreach ($kingdoms as $kingdom) {
			$small_kingdoms[] = $kingdom['pos'];
		}

		$union_tile = false;
		foreach ($board as $tile) {
			if ($tile['isUnion'] === '1') {
				$union_tile = $tile;
			}
		}

		$warring_kingdoms = self::neighborKingdoms($union_tile['posX'], $union_tile['posY'], $kingdoms);
		$warring_leader_ids = array();
		$potential_war_leaders = array_merge($kingdoms[array_pop($warring_kingdoms)]['leaders'], $kingdoms[array_pop($warring_kingdoms)]['leaders']);
		foreach ($potential_war_leaders as $pleader) {
			foreach ($potential_war_leaders as $oleader) {
				if ($oleader['kind'] == $pleader['kind'] && $oleader['id'] != $pleader['id']) {
					$warring_leader_ids[] = $oleader['id'];
					$warring_leader_ids[] = $pleader['id'];
				}
			}
		}
		$warring_leader_ids = array_unique($warring_leader_ids);

		$leader_strengths = [];
		foreach ($leaders as $leader) {
			$leader_strengths[] = [
				'strength' => self::calculateKingdomStrength($leader, $kingdoms),
				'id' => $leader['id'],
				'kind' => $leader['kind'],
				'owner' => self::getPlayerNameById($leader['owner']),
			];
		}

		return array(
			'action_number' => self::getGameStateValue("current_action_count"),
			'kingdoms' => $small_kingdoms,
			'player_status' => self::getPlayerStatus(),
			'can_undo' => self::canUndo(),
			'potential_leaders' => $warring_leader_ids,
			'leader_strengths' => $leader_strengths,
		);

	}

	function arg_pickTreasure() {
		$player_id = self::getActivePlayerId();
		$board = self::getCollectionFromDB("select * from tile where state = 'board'");
		$leaders = self::getCollectionFromDB("select * from leader where onBoard = '1'");
		$kingdoms = self::findKingdoms($board, $leaders);
		$small_kingdoms = array();
		foreach ($kingdoms as $kingdom) {
			$small_kingdoms[] = $kingdom['pos'];
		}

		$treasures = array();
		$mandatory_treasures = array();
		$outer_temples = $this->outerTemples;
		if (self::getGameStateValue('game_board') == 2) {
			$outer_temples = $this->alt_outerTemples;
		}
		foreach ($kingdoms as $kingdom) {
			$green_leader_id = false;
			foreach ($kingdom['leaders'] as $leader) {
				if ($leader['kind'] == 'green' && $leader['owner'] == $player_id) {
					$green_leader_id = $leader['id'];
				}
			}
			// make sure said kingdom has two treasures
			if ($green_leader_id !== false && self::kingdomHasTwoTreasures($kingdom)) {
				// check to see if any treasures are on outer tiles
				foreach ($kingdom['tiles'] as $tile) {
					foreach ($outer_temples as $ot) {
						if ($tile['posX'] === $ot['posX'] && $tile['posY'] === $ot['posY'] && $tile['hasTreasure']) {
							$mandatory_treasures[] = $tile['id'];
						}
					}
					if ($tile['hasTreasure']) {
						$treasures[] = $tile['id'];
					}
				}
			}
		}

		if (count($mandatory_treasures) > 0) {
			$treasures = $mandatory_treasures;
		}

		$leader_strengths = [];
		foreach ($leaders as $leader) {
			$leader_strengths[] = [
				'strength' => self::calculateKingdomStrength($leader, $kingdoms),
				'id' => $leader['id'],
				'kind' => $leader['kind'],
				'owner' => self::getPlayerNameById($leader['owner']),
			];
		}

		return array(
			'kingdoms' => $small_kingdoms,
			'player_status' => self::getPlayerStatus(),
			'can_undo' => self::canUndo(),
			'valid_treasures' => $treasures,
			'leader_strengths' => $leader_strengths,
		);

	}

	function arg_playerTurn() {
		$player_id = self::getActivePlayerId();
		$board = self::getCollectionFromDB("select * from tile where state = 'board'");
		$leaders = self::getCollectionFromDB("select * from leader where onBoard = '1'");
		$kingdoms = self::findKingdoms($board, $leaders);
		$small_kingdoms = array();
		foreach ($kingdoms as $kingdom) {
			$small_kingdoms[] = $kingdom['pos'];
		}

		$leader_strengths = [];
		foreach ($leaders as $leader) {
			$leader_strengths[] = [
				'strength' => self::calculateKingdomStrength($leader, $kingdoms),
				'id' => $leader['id'],
				'kind' => $leader['kind'],
				'owner' => self::getPlayerNameById($leader['owner']),
			];
		}

		$player_shape = self::getUniqueValueFromDB("select shape from leader where kind = 'black' and owner = '" . $player_id . "'");

		return array(
			'action_number' => self::getGameStateValue("current_action_count"),
			'kingdoms' => $small_kingdoms,
			'player_status' => self::getPlayerStatus(),
			'can_undo' => self::canUndo(),
			'player_shape' => $player_shape,
			'leader_strengths' => $leader_strengths,
		);
	}

	function arg_showKingdoms() {
		$board = self::getCollectionFromDB("select * from tile where state = 'board'");
		$leaders = self::getCollectionFromDB("select * from leader where onBoard = '1'");
		$kingdoms = self::findKingdoms($board, $leaders);
		$small_kingdoms = array();
		foreach ($kingdoms as $kingdom) {
			$small_kingdoms[] = $kingdom['pos'];
		}

		$leader_strengths = [];
		foreach ($leaders as $leader) {
			$leader_strengths[] = [
				'strength' => self::calculateKingdomStrength($leader, $kingdoms),
				'id' => $leader['id'],
				'kind' => $leader['kind'],
				'owner' => self::getPlayerNameById($leader['owner']),
			];
		}

		return array(
			'kingdoms' => $small_kingdoms,
			'player_status' => self::getPlayerStatus(),
			'can_undo' => self::canUndo(),
			'leader_strengths' => $leader_strengths,
		);
	}

	function arg_showWar() {
		$board = self::getCollectionFromDB("select * from tile where state = 'board'");
		$leaders = self::getCollectionFromDB("select * from leader where onBoard = '1'");
		$kingdoms = self::findKingdoms($board, $leaders);
		$small_kingdoms = array();
		foreach ($kingdoms as $kingdom) {
			$small_kingdoms[] = $kingdom['pos'];
		}

		$attacker = $leaders[self::getGameStateValue("current_attacker")];
		$defender = $leaders[self::getGameStateValue("current_defender")];
		$attacker_board_strength = 0;
		$defender_board_strength = 0;
		foreach ($kingdoms as $kingdom) {
			if (array_key_exists($attacker['id'], $kingdom['leaders'])) {
				foreach ($kingdom['tiles'] as $tile) {
					if ($tile['kind'] == $attacker['kind']) {
						$attacker_board_strength++;
					}
				}
			}
			if (array_key_exists($defender['id'], $kingdom['leaders'])) {
				foreach ($kingdom['tiles'] as $tile) {
					if ($tile['kind'] == $defender['kind']) {
						$defender_board_strength++;
					}
				}
			}
		}
		$attacker_hand_strength = self::getUniqueValueFromDB("select count(*) from tile where owner = '" . $attacker['owner'] . "' and state='support'");

		$leader_strengths = [];
		foreach ($leaders as $leader) {
			$leader_strengths[] = [
				'strength' => self::calculateKingdomStrength($leader, $kingdoms),
				'id' => $leader['id'],
				'kind' => $leader['kind'],
				'owner' => self::getPlayerNameById($leader['owner']),
			];
		}

		return array(
			'kingdoms' => $small_kingdoms,
			'player_status' => self::getPlayerStatus(),
			'attacker' => $attacker,
			'defender' => $defender,
			'attacker_board_strength' => $attacker_board_strength,
			'defender_board_strength' => $defender_board_strength,
			'attacker_hand_strength' => $attacker_hand_strength,
			'can_undo' => self::canUndo(),
			'leader_strengths' => $leader_strengths,
		);

	}

	function arg_showRevolt() {
		$board = self::getCollectionFromDB("select * from tile where state = 'board'");
		$leaders = self::getCollectionFromDB("select * from leader where onBoard = '1'");
		$kingdoms = self::findKingdoms($board, $leaders);
		$small_kingdoms = array();
		foreach ($kingdoms as $kingdom) {
			$small_kingdoms[] = $kingdom['pos'];
		}

		$attacker = $leaders[self::getGameStateValue("current_attacker")];
		$defender = $leaders[self::getGameStateValue("current_defender")];
		$attacker_board_strength = 0;
		$defender_board_strength = 0;
		foreach ($kingdoms as $kingdom) {
			if (array_key_exists($attacker['id'], $kingdom['leaders'])) {
				foreach (self::findNeighbors($attacker['posX'], $attacker['posY'], $kingdom['tiles']) as $tile_id) {
					if ($board[$tile_id]['kind'] == 'red') {
						$attacker_board_strength++;
					}
				}
			}
			if (array_key_exists($defender['id'], $kingdom['leaders'])) {
				foreach (self::findNeighbors($defender['posX'], $defender['posY'], $kingdom['tiles']) as $tile_id) {
					if ($board[$tile_id]['kind'] == 'red') {
						$defender_board_strength++;
					}
				}
			}
		}
		$attacker_hand_strength = self::getUniqueValueFromDB("select count(*) from tile where owner = '" . $attacker['owner'] . "' and state='support'");

		$leader_strengths = [];
		foreach ($leaders as $leader) {
			$leader_strengths[] = [
				'strength' => self::calculateKingdomStrength($leader, $kingdoms),
				'id' => $leader['id'],
				'kind' => $leader['kind'],
				'owner' => self::getPlayerNameById($leader['owner']),
			];
		}

		return array(
			'kingdoms' => $small_kingdoms,
			'player_status' => self::getPlayerStatus(),
			'attacker' => $attacker,
			'defender' => $defender,
			'attacker_board_strength' => $attacker_board_strength,
			'defender_board_strength' => $defender_board_strength,
			'attacker_hand_strength' => $attacker_hand_strength,
			'can_undo' => self::canUndo(),
			'leader_strengths' => $leader_strengths,
		);

	}

//////////////////////////////////////////////////////////////////////////////
	//////////// Game state actions
	////////////

	function stIncrementAction() {
		$original_player = self::getGameStateValue("original_player");
		if ($original_player != NO_ID) {
			$this->gamestate->changeActivePlayer($original_player);
		}
		$player_id = self::getActivePlayerId();
		$player_name = self::getActivePlayerName();

		// pickup treasures
		$board = self::getCollectionFromDB("select * from tile where state = 'board'");
		$leaders = self::getCollectionFromDB("select * from leader where onBoard = '1'");
		$kingdoms = self::findKingdoms($board, $leaders);
		foreach ($kingdoms as $kingdom) {
			$green_leader_id = false;
			foreach ($kingdom['leaders'] as $leader) {
				if ($leader['kind'] == 'green') {
					$green_leader_id = $leader['id'];
				}
			}
			if ($green_leader_id !== false && self::kingdomHasTwoTreasures($kingdom)) {
				self::setGameStateValue("original_player", $player_id);
				if ($player_id != $kingdom['leaders'][$green_leader_id]['owner']) {
					self::disableUndo();
				}
				$this->gamestate->changeActivePlayer($kingdom['leaders'][$green_leader_id]['owner']);
				self::giveExtraTime($kingdom['leaders'][$green_leader_id]['owner']);
				$this->gamestate->nextState("pickTreasure");
				return;
			}
		}

		// check game-end
		$remaining_treasures = self::getUniqueValueFromDB("select count(*) from tile where hasTreasure = '1'");
		if ($remaining_treasures <= 2) {
			self::notifyAllPlayers(
				"gameEnding",
				clienttranslate('Only ${remaining_treasures} treasures remain, game is over.'),
				array(
					'remaining_treasures' => $remaining_treasures,
				)
			);
			$this->gamestate->nextState("endGame");
			return;
		}

		if (self::getGameStateValue('db_undo') != DB_UNDO_YES) {
			self::undoSavePoint();
		}

		if (self::getGameStateValue("current_action_count") == 1) {
			self::setGameStateValue("current_action_count", 2);
			self::giveExtraTime($player_id);
			$first_tile = self::getGameStateValue("last_tile_id");
			$first_leader = self::getGameStateValue("last_leader_id");
			self::setGameStateValue("first_action_tile_id", $first_tile);
			self::setGameStateValue("first_action_leader_id", $first_leader);
			$leader_x = self::getGameStateValue("leader_x");
			$leader_y = self::getGameStateValue("leader_y");
			self::setGameStateValue("first_leader_x", $leader_x);
			self::setGameStateValue("first_leader_y", $leader_y);
			$this->gamestate->nextState("secondAction");
			return;
		}

		self::setGameStateValue("current_action_count", 3);
		if (self::canUndo()) {
			$this->gamestate->nextState("confirmTurn");
		} else {
			$this->gamestate->nextState("endTurn");
		}
	}

	function stNextPlayer() {
		$player_id = self::getActivePlayerId();
		$player_name = self::getActivePlayerName();
		// award monument points
		$board = self::getCollectionFromDB("select * from tile where state = 'board'");
		$leaders = self::getCollectionFromDB("select * from leader where onBoard = '1'");
		$kingdoms = self::findKingdoms($board, $leaders);
		$monuments = self::getCollectionFromDB("select * from monument where onBoard = '1'");
		foreach ($monuments as $monument) {
			$pos = [$monument['posX'], $monument['posY']];
			foreach ($kingdoms as $kingdom) {
				if (in_array($pos, $kingdom['pos'])) {
					foreach ($kingdom['leaders'] as $leader) {
						if ($leader['owner'] == $player_id && $leader['kind'] == $monument['color1']) {
							self::score($monument['color1'], 1, $player_id, $player_name, 'monument', $monument['id']);
						}
						if ($leader['owner'] == $player_id && $leader['kind'] == $monument['color2']) {
							self::score($monument['color2'], 1, $player_id, $player_name, 'monument', $monument['id']);
						}
					}
				}
			}
		}

		// refill hands
		$players = $this->loadPlayersBasicInfos();
		foreach ($players as $draw_player_id => $info) {
			$tile_count = self::getUniqueValueFromDB("
                select
                    count(*)
                from
                    tile
                where
                    owner = '" . $draw_player_id . "' and
                    state = 'hand' and
                    kind != 'catastrophe';
                ");
			if ($tile_count < 6) {
				$this->drawTiles(6 - $tile_count, $draw_player_id);
			}
		}

		// move to next player
		$this->activeNextPlayer();
		$player_id = self::getActivePlayerId();
		self::incStat(1, 'turns_number', $player_id);
		self::incStat(1, 'turns_number');
		self::giveExtraTime($player_id);
		self::setGameStateValue("original_player", NO_ID);
		self::setGameStateValue("current_action_count", 1);
		self::disableUndo();
		self::setGameStateValue('db_undo', NO_ID);
		self::undoSavePoint();
		$this->gamestate->nextState("nextPlayer");
	}

	function stRevoltProgress() {
		$player_id = self::getActivePlayerId();
		$war_state = self::getGameStateValue("current_war_state");
		$attacker_id = self::getGameStateValue("current_attacker");
		$defender_id = self::getGameStateValue("current_defender");

		$board = self::getCollectionFromDB("select * from tile where state = 'board'");
		$leaders = self::getCollectionFromDB("select * from leader where onBoard = '1'");

		// if attacker just placed support, change to defender and return
		if ($war_state == WAR_ATTACKER_SUPPORT) {
			self::setGameStateValue("current_war_state", WAR_DEFENDER_SUPPORT);
			$this->gamestate->changeActivePlayer($leaders[$defender_id]['owner']);
			self::giveExtraTime($leaders[$defender_id]['owner']);
			$this->gamestate->nextState("placeSupport");
			return;
		}

		// calculate strengths
		$attacking_player_id = $leaders[$attacker_id]['owner'];
		$defending_player_id = $leaders[$defender_id]['owner'];

		$attacker_support = self::getUniqueValueFromDB("
            select count(*) from tile where owner = '" . $attacking_player_id . "' and state = 'support' and kind = 'red'
            ");
		$defender_support = self::getUniqueValueFromDB("
            select count(*) from tile where owner = '" . $defending_player_id . "' and state = 'support' and kind = 'red'
            ");

		$attacker_board_strength = self::calculateBoardStrength($leaders[$attacker_id], $board);
		$defender_board_strength = self::calculateBoardStrength($leaders[$defender_id], $board);

		$attacker_strength = intval($attacker_support) + $attacker_board_strength;
		$defender_strength = intval($defender_support) + $defender_board_strength;

		// determine winner and set variables
		$winning_side = 'defender';
		$winner = $defender_id;
		$loser = $attacker_id;
		$winner_strength = $defender_strength;
		$loser_strength = $attacker_strength;
		$winning_player_id = $defending_player_id;
		if ($attacker_strength > $defender_strength) {
			$winning_side = 'attacker';
			$winner = $attacker_id;
			$loser = $defender_id;
			$winner_strength = $attacker_strength;
			$loser_strength = $defender_strength;
			$winning_player_id = $attacking_player_id;
		}
		$winner_name = self::getPlayerNameById($leaders[$winner]['owner']);
		$loser_name = self::getPlayerNameById($leaders[$loser]['owner']);

		// inc stats
		if ($winning_side == 'attacker') {
			self::incStat(1, "revolts_won_attacker", $leaders[$winner]['owner']);
			self::incStat(1, "revolts_lost_defender", $leaders[$loser]['owner']);
		} else {
			self::incStat(1, "revolts_lost_attacker", $leaders[$loser]['owner']);
			self::incStat(1, "revolts_won_defender", $leaders[$winner]['owner']);
		}

		// return losing leader and notify players
		$winner_name = self::getPlayerNameById($leaders[$winner]['owner']);
		$loser_name = self::getPlayerNameById($leaders[$loser]['owner']);
		self::DbQuery("update leader set posX = NULL, posY = NULL, onBoard = '0' where id = '" . $loser . "'");
		self::notifyAllPlayers(
			"revoltConcluded",
			clienttranslate('${player_name}(${winner_strength}) removed ${player_name2}(${loser_strength}) in a revolt'),
			array(
				'player_name' => $winner_name,
				'player_name2' => $loser_name,
				'winner' => $winner_name,
				'loser' => $loser_name,
				'winner_strength' => $winner_strength,
				'loser_strength' => $loser_strength,
				'loser_id' => $loser,
				'winning_player_id' => $leaders[$winner]['owner'],
				'losing_player_id' => $leaders[$loser]['owner'],
				'loser_shape' => $leaders[$loser]['shape'],
				'kind' => $leaders[$loser]['kind'],
				'winning_side' => $winning_side,
			)
		);

		// score red for winner
		$scorer_name = self::getPlayerNameById($leaders[$winner]['owner']);
		self::score('red', 1, $leaders[$winner]['owner'], $scorer_name, false, false, false);

		// discard support
		self::DbQuery("
            update tile set owner = NULL, state = 'discard' where state = 'support'
            ");

		// go back to attacker as active player and move on
		self::setGameStateValue("current_war_state", WAR_NO_WAR);
		self::setGameStateValue("current_attacker", NO_ID);
		self::setGameStateValue("current_defender", NO_ID);
		self::disableUndo();
		self::setGameStateValue('db_undo', NO_ID);
		$this->gamestate->changeActivePlayer($leaders[$attacker_id]['owner']);
		$this->gamestate->nextState("concludeRevolt");
	}

	function allWarsEnded($union_tile, $board) {
		self::DbQuery("update tile set isUnion = '0' where isUnion = '1'");

		self::notifyAllPlayers(
			"allWarsEnded",
			clienttranslate('All wars concluded'),
			array(
				'tile_id' => $union_tile['id'],
				'pos_x' => $union_tile['posX'],
				'pos_y' => $union_tile['posY'],
				'tile_color' => $union_tile['kind'],
			)
		);

		self::setGameStateValue("current_war_state", WAR_NO_WAR);
		self::setGameStateValue("current_attacker", NO_ID);
		self::setGameStateValue("current_defender", NO_ID);
		self::disableUndo();
		self::setGameStateValue('db_undo', NO_ID);
		// next action
		$original_player = self::getGameStateValue("original_player");
		$this->gamestate->changeActivePlayer($original_player);
		$monument_count = self::getUniqueValueFromDB("select count(*) from monument where onBoard = '0'");
		if (self::getMonumentCount($board, $union_tile) > 0 && $monument_count > 0) {
			self::setGameStateValue("potential_monument_tile_id", $union_tile['id']);
			self::giveExtraTime($original_player);
			self::undoSavePoint();
			$this->gamestate->nextState("warMonument");
		} else {
			$this->gamestate->nextState("noWar");
		}
	}

	function stWarProgress() {
		$player_id = self::getActivePlayerId();
		$war_state = self::getGameStateValue("current_war_state");
		$attacker_id = self::getGameStateValue("current_attacker");
		$defender_id = self::getGameStateValue("current_defender");
		$original_player = self::getGameStateValue("original_player");

		$leaders = self::getCollectionFromDB("select * from leader where onBoard = '1'");

		// if non-active player is attacker in war, set them to active player and move on
		if ($war_state == WAR_START && $attacker_id != NO_ID) {
			if ($leaders[$attacker_id]['owner'] == $player_id) {
				self::setGameStateValue("current_war_state", WAR_ATTACKER_SUPPORT);
				$this->gamestate->nextState("placeSupport");
				return;
			}
			// Swap attacker and defender and go to support
			if ($leaders[$defender_id]['owner'] == $player_id) {
				self::setGameStateValue("current_attacker", $defender_id);
				self::setGameStateValue("current_defender", $attacker_id);
				self::setGameStateValue("current_war_state", WAR_ATTACKER_SUPPORT);
				$this->gamestate->nextState("placeSupport");
				return;
			}
			$this->activeNextPlayer();
			$this->gamestate->nextState("nextWar");
			return;
		}

		// if attacker just placed support, change to defender and move on
		if ($war_state == WAR_ATTACKER_SUPPORT) {
			self::disableUndo();
			self::setGameStateValue('db_undo', NO_ID);
			self::setGameStateValue("current_war_state", WAR_DEFENDER_SUPPORT);
			$this->gamestate->changeActivePlayer($leaders[$defender_id]['owner']);
			self::giveExtraTime($leaders[$defender_id]['owner']);
			$this->gamestate->nextState("placeSupport");
			return;
		}

		// find warring kingdoms and leaders
		$board = self::getCollectionFromDB("select * from tile where state = 'board'");
		$kingdoms = self::findKingdoms($board, $leaders);
		$union_tile = false;
		foreach ($board as $tile) {
			if ($tile['isUnion'] === '1') {
				$union_tile = $tile;
			}
		}
		if ($union_tile === false) {
			throw new BgaVisibleSystemException(self::_("Game is in bad state (no union tile), reload"));
		}

		// If defender supported resolve war and return
		if ($war_state == WAR_DEFENDER_SUPPORT) {
			$attacking_player_id = $leaders[$attacker_id]['owner'];
			$defending_player_id = $leaders[$defender_id]['owner'];
			$war_color = $leaders[$attacker_id]['kind'];

			// calculate strength
			$attacker_support = self::getUniqueValueFromDB("
                select count(*) from tile where owner = '" . $attacking_player_id . "' and state = 'support' and kind = '" . $war_color . "'
                ");
			$defender_support = self::getUniqueValueFromDB("
                select count(*) from tile where owner = '" . $defending_player_id . "' and state = 'support' and kind = '" . $war_color . "'
                ");

			$attacker_board_strength = self::calculateKingdomStrength($leaders[$attacker_id], $kingdoms);
			$defender_board_strength = self::calculateKingdomStrength($leaders[$defender_id], $kingdoms);

			$attacker_strength = intval($attacker_support) + $attacker_board_strength;
			$defender_strength = intval($defender_support) + $defender_board_strength;

			// determine winner and set variables
			$winner = $defender_id;
			$loser = $attacker_id;
			$winner_strength = $defender_strength;
			$loser_strength = $attacker_strength;
			$winning_player_id = $defending_player_id;
			$winning_side = 'defender';
			if ($attacker_strength > $defender_strength) {
				$winner = $attacker_id;
				$loser = $defender_id;
				$winner_strength = $attacker_strength;
				$loser_strength = $defender_strength;
				$winning_player_id = $attacking_player_id;
				$winning_side = 'attacker';
			}

			// remove tiles from losing kingdom
			$tiles_to_remove = array();
			foreach ($kingdoms as $kingdom) {
				if (array_key_exists($loser, $kingdom['leaders'])) {
					foreach ($kingdom['tiles'] as $tile) {
						if ($tile['kind'] === $leaders[$loser]['kind'] && $tile['hasTreasure'] === '0') {
							$supported_leaders = array();
							// don't remove red that are supporting leaders
							if ($tile['kind'] == 'red') {
								$supported_leaders = self::findNeighbors($tile['posX'], $tile['posY'], $kingdom['leaders']);
							}
							if (count($supported_leaders) == 0) {
								$tiles_to_remove[] = $tile['id'];
							} else if (count($supported_leaders) == 1) {
								if ($supported_leaders[0] == $loser) {
									$tiles_to_remove[] = $tile['id'];
								}
							}
						}
					}
				}
			}

			// return losing leader
			self::DbQuery("update leader set posX = NULL, posY = NULL, onBoard = '0' where id = '" . $loser . "'");

			// discard removed tiles
			if (count($tiles_to_remove) > 0) {
				$tile_string = implode($tiles_to_remove, ',');
				self::DbQuery("update tile set posX = NULL, posY = NULL, state = 'discard', isUnion = '0' where id in (" . $tile_string . ")");
			}

			// set stats
			if ($winning_side == 'attacker') {
				self::incStat(1, "wars_won_attacker", $leaders[$winner]['owner']);
				self::incStat(1, "wars_lost_defender", $leaders[$loser]['owner']);
			} else {
				self::incStat(1, "wars_lost_attacker", $leaders[$loser]['owner']);
				self::incStat(1, "wars_won_defender", $leaders[$winner]['owner']);
			}

			// discard support
			self::DbQuery("
                update tile set owner = NULL, state = 'discard' where state = 'support'
                ");

			$winner_name = self::getPlayerNameById($leaders[$winner]['owner']);
			$loser_name = self::getPlayerNameById($leaders[$loser]['owner']);
			//notify all players
			self::notifyAllPlayers(
				"warConcluded",
				clienttranslate('${player_name}(${winner_strength}) removed ${player_name2}(${loser_strength}) and ${tiles_removed_count} tiles in war'),
				array(
					'player_name' => $winner_name,
					'player_name2' => $loser_name,
					'winner' => $leaders[$winner]['shape'],
					'loser_shape' => $leaders[$loser]['shape'],
					'winner_strength' => $winner_strength,
					'loser_strength' => $loser_strength,
					'loser_id' => $loser,
					'winning_player_id' => $leaders[$winner]['owner'],
					'losing_player_id' => $leaders[$loser]['owner'],
					'kind' => $leaders[$loser]['kind'],
					'tiles_removed_count' => count($tiles_to_remove),
					'tiles_removed' => $tiles_to_remove,
					'winning_side' => $winning_side,
				)
			);

			// score points and notify players
			$points = count($tiles_to_remove) + 1;
			$scorer_name = self::getPlayerNameById($leaders[$winner]['owner']);
			self::score($war_color, $points, $winning_player_id, $scorer_name, false, false, false);

			// reset states and move to next war
			self::setGameStateValue("current_war_state", WAR_NO_WAR);
			self::setGameStateValue("current_attacker", NO_ID);
			self::setGameStateValue("current_defender", NO_ID);
			$this->gamestate->changeActivePlayer($original_player);
			self::giveExtraTime($original_player);
			$this->gamestate->nextState("nextWar");
			return;
		}

		$warring_kingdoms = self::neighborKingdoms($union_tile['posX'], $union_tile['posY'], $kingdoms);

		// if no warring kingdoms remain, notify players wars and resolved and remove union marker then return
		if (count($warring_kingdoms) < 2) {
			self::allWarsEnded($union_tile, $board);
			return;
		}

		// find all potential warring leaders
		$warring_leader_ids = array();
		$potential_war_leaders = [];
		foreach ($warring_kingdoms as $warring_kingdom) {
			$potential_war_leaders = array_merge($potential_war_leaders, $kingdoms[$warring_kingdom]['leaders']);
		}
		foreach ($potential_war_leaders as $pleader) {
			foreach ($potential_war_leaders as $oleader) {
				if ($oleader['kind'] == $pleader['kind'] && $oleader['id'] != $pleader['id']) {
					$warring_leader_ids[] = $oleader['id'];
					$warring_leader_ids[] = $pleader['id'];
				}
			}
		}
		$warring_leader_ids = array_unique($warring_leader_ids);

		// if no warring leaders remain, notify players and return
		if (count($warring_leader_ids) < 2) {
			self::allWarsEnded($union_tile, $board);
			return;
		} else if (count($warring_leader_ids) > 2) {
			$this->gamestate->changeActivePlayer($original_player);
			self::giveExtraTime($original_player);
			$this->gamestate->nextState("pickLeader");
			return;
		}

		$player_has_leader = false;
		$attacking_leader = false;
		$players_leader = false;
		foreach ($warring_leader_ids as $wleader_id) {
			if ($leaders[$wleader_id]['owner'] == $player_id) {
				$player_has_leader = true;
				$players_leader = $leaders[$wleader_id];
			}
			$attacking_leader = $leaders[$wleader_id];
		}
		if ($player_has_leader) {
			$attacking_leader = $players_leader;
		}
		$defending_leader = false;
		foreach ($potential_war_leaders as $dleader) {
			if ($dleader['kind'] == $attacking_leader['kind'] && $dleader['id'] !== $attacking_leader['id']) {
				$defending_leader = $dleader;
			}
		}

		self::setGameStateValue("current_attacker", $attacking_leader['id']);
		self::setGameStateValue("current_defender", $defending_leader['id']);

		// player is the defender in an existing war
		if ($player_has_leader) {
			self::setGameStateValue("current_war_state", WAR_ATTACKER_SUPPORT);
			self::giveExtraTime($player_id);
			$this->gamestate->nextState("placeSupport");
			return;
		} else {
			// move to next player to find attacker
			self::disableUndo();
			self::setGameStateValue("current_war_state", WAR_START);
			$this->activeNextPlayer();
			$this->gamestate->nextState("nextWar");
			return;
		}
	}

	function addToLowest(&$points) {
		$colors = array('red', 'blue', 'green', 'black');
		$lowest = 999;
		$lowest_color = 'red';
		foreach ($colors as $color) {
			if ($points[$color] < $lowest) {
				$lowest = $points[$color];
				$lowest_color = $color;
			}
		}
		$points[$lowest_color]++;
	}

	function getLowest($points) {
		$colors = array('red', 'blue', 'green', 'black');
		$lowest = 99;
		$lowest_color = 'red';
		foreach ($colors as $color) {
			if ($points[$color] < $lowest) {
				$lowest = $points[$color];
				$lowest_color = $color;
			}
		}
		return $lowest_color;
	}

	function getPointArray($point) {
		return [
			$point['black'],
			$point['red'],
			$point['green'],
			$point['blue'],
		];
	}

	function breakTie($points, $tied_players) {
		$player_arrs = [];
		foreach ($tied_players as $player_id) {
			$arr = self::getPointArray($points[$player_id]);
			sort($arr);
			$player_arrs[$player_id] = $arr;
		}
		arsort($player_arrs);
		$i = count($player_arrs);
		foreach ($player_arrs as $player_id => $points) {
			self::DbQuery("update player set player_score_aux = '" . $i . "' where player_id = '" . $player_id . "'");
			$i--;
		}
	}

	function stFinalScoring() {
		$points = self::getCollectionFromDB("select * from point");
		self::notifyAllPlayers(
			"startingFinalScores",
			clienttranslate("Final Scoring."),
			array('points' => $points)
		);
		$highest_score = -1;
		$scores = [];
		foreach ($points as $player => &$point) {
			self::setStat($point['black'], 'black_points', $player);
			self::setStat($point['red'], 'red_points', $player);
			self::setStat($point['green'], 'green_points', $player);
			self::setStat($point['blue'], 'blue_points', $player);
			self::setStat($point['treasure'], 'treasure_picked_up', $player);
			while ($point['treasure'] > 0) {
				$point['treasure']--;
				self::addToLowest($point);
			}
			$low_color = self::getLowest($point);
			$score = $point[$low_color];
			$points[$player][$low_color] = 999;
			$points[$player]['lowest'] = $score;
			$points[$player]['score'] = $score;
			if (array_key_exists($score, $scores) == false) {
				$scores[$score] = [];
			}
			$scores[$score][] = $player;
			self::DbQuery("update player set player_score = '" . $score . "' where player_id = '" . $player . "'");
			if ($score > $highest_score) {
				$highest_score = $score;
			}
		}
		self::notifyAllPlayers(
			"finalScores",
			clienttranslate("End of Game"),
			array('points' => $points)
		);

		foreach ($scores as $score => $players) {
			self::breakTie($points, $players);
		}

		$winner_no = self::getUniqueValueFromDB("select player_no from player order by player_score desc, player_score_aux desc limit 1");
		self::setStat(intVal($winner_no), 'winning_position');

		$this->gamestate->nextState("endGame");
	}

//////////////////////////////////////////////////////////////////////////////
	//////////// Zombie
	////////////

	/*
		        zombieTurn:

		        This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
		        You can do whatever you want in order to make sure the turn of this player ends appropriately
		        (ex: pass).

		        Important: your zombie code will be called when the player leaves the game. This action is triggered
		        from the main site and propagated to the gameserver from a server, not from a browser.
		        As a consequence, there is no current player associated to this action. In your zombieTurn function,
		        you must _never_ use getCurrentPlayerId() or getCurrentPlayerName(), otherwise it will fail with a "Not logged" error message.
	*/

	function zombieTurn($state, $active_player) {
		$statename = $state['name'];

		if ($state['type'] === "activeplayer") {
			switch ($statename) {
			case 'warLeader':
				$board = self::getCollectionFromDB("select * from tile where state = 'board'");
				$leaders = self::getCollectionFromDB("select * from leader where onBoard = '1'");
				$kingdoms = self::findKingdoms($board, $leaders);
				$union_tile = false;
				foreach ($board as $tile) {
					if ($tile['isUnion'] === '1') {
						$union_tile = $tile;
					}
				}

				$warring_kingdoms = self::neighborKingdoms($union_tile['posX'], $union_tile['posY'], $kingdoms);
				$potential_war_leaders = array_merge($kingdoms[array_pop($warring_kingdoms)]['leaders'], $kingdoms[array_pop($warring_kingdoms)]['leaders']);
				$attacking_color = false;
				foreach ($potential_war_leaders as $pleader) {
					foreach ($potential_war_leaders as $oleader) {
						if ($oleader['kind'] == $pleader['kind'] && $oleader['id'] != $pleader['id']) {
							if ($oleader['owner'] == $active_player) {
								self::setGameStateValue("current_attacker", $oleader['id']);
								self::setGameStateValue("current_defender", $pleader['id']);
								$attacking_color = $oleader['kind'];
							}
							if ($pleader['owner'] == $active_player) {
								self::setGameStateValue("current_attacker", $pleader['id']);
								self::setGameStateValue("current_defender", $oleader['id']);
								$attacking_color = $pleader['kind'];
							}
						}
					}
				}
				self::setGameStateValue("current_war_state", WAR_ATTACKER_SUPPORT);
				self::notifyAllPlayers(
					"leaderSelected",
					clienttranslate('${player_name} selected <span style="color:${color}">${leader_name}</span> for war'),
					array(
						'player_name' => 'ZombiePlayer',
						'color' => $attacking_color,
						'leader_name' => $this->leaderNames[$attacking_color],
					)
				);
				$this->gamestate->nextState("zombiePass");
				break;
			case 'pickTreasure':
				$board = self::getCollectionFromDB("select * from tile where state = 'board'");
				$leaders = self::getCollectionFromDB("select * from leader where onBoard = '1'");
				$kingdoms = self::findKingdoms($board, $leaders);
				$outer_temples = $this->outerTemples;
				if (self::getGameStateValue('game_board') == 2) {
					$outer_temples = $this->alt_outerTemples;
				}
				foreach ($kingdoms as $kingdom) {
					$green_leader_id = false;
					foreach ($kingdom['leaders'] as $leader) {
						if ($leader['kind'] == 'green' && $leader['owner'] == $active_player) {
							$green_leader_id = $leader['id'];
						}
					}
					if ($green_leader_id !== false && self::kingdomHasTwoTreasures($kingdom)) {
						$has_mandatory = false;
						foreach ($kingdom['tiles'] as $tile) {
							foreach ($outer_temples as $ot) {
								if ($tile['posX'] === $ot['posX'] && $tile['posY'] === $ot['posY'] && $tile['hasTreasure']) {
									$has_mandatory = true;
								}
							}
						}
						foreach ($kingdom['tiles'] as $tile) {
							if ($has_mandatory === false && $tile['hasTreasure']) {
								self::DbQuery("
                                        update
                                            point
                                        set
                                            treasure = treasure + 1
                                        where
                                            player = '" . $active_player . "'
                                        ");
								$tile_id = $tile['id'];
								self::DbQuery("
                                        update
                                            tile
                                        set
                                            hasTreasure = '0'
                                        where
                                            id = '" . $tile_id . "'
                                        ");
								self::notifyAllPlayers(
									"pickedTreasure",
									clienttranslate('${player_name} scored 1 <div class="point ${color}_point"></div>'),
									array(
										'player_name' => 'ZombiePlayer',
										'player_id' => $active_player,
										'color' => 'treasure',
										'tile_id' => $tile['id'],
									)
								);
								$this->gamestate->nextState("zombiePass");
								break;
							}
							foreach ($outer_temples as $ot) {
								if ($tile['posX'] === $ot['posX'] && $tile['posY'] === $ot['posY'] && $tile['hasTreasure']) {
									self::DbQuery("
                                            update
                                                point
                                            set
                                                treasure = treasure + 1
                                            where
                                                player = '" . $active_player . "'
                                            ");
									$tile_id = $tile['id'];
									self::DbQuery("
                                            update
                                                tile
                                            set
                                                hasTreasure = '0'
                                            where
                                                id = '" . $tile_id . "'
                                            ");
									self::notifyAllPlayers(
										"pickedTreasure",
										clienttranslate('${player_name} scored 1 <div class="point ${color}_point"></div>'),
										array(
											'player_name' => 'ZombiePlayer',
											'player_id' => $active_player,
											'color' => 'treasure',
											'tile_id' => $tile['id'],
										)
									);
									$this->gamestate->nextState("zombiePass");
									break;
								}
							}
						}
					}
				}
			default:
				$this->gamestate->nextState("zombiePass");
				break;
			}

			return;
		}

		if ($state['type'] === "multipleactiveplayer") {
			// Make sure player is in a non blocking status for role turn
			$this->gamestate->setPlayerNonMultiactive($active_player, '');

			return;
		}

		throw new BgaVisibleSystemException("Zombie mode not supported at this game state: " . $statename);
	}

	public function loadBug($reportId) {
		$db = explode('_', self::getUniqueValueFromDB("SELECT SUBSTRING_INDEX(DATABASE(), '_', -2)"));
		$game = $db[0];
		$tableId = $db[1];
		self::notifyAllPlayers('loadBug', "Trying to load <a href='https://boardgamearena.com/bug?id=$reportId' target='_blank'>bug report $reportId</a>", [
			'urls' => [
				// Emulates "load bug report" in control panel
				"https://studio.boardgamearena.com/admin/studio/getSavedGameStateFromProduction.html?game=$game&report_id=$reportId&table_id=$tableId",

				// Emulates "load 1" at this table
				"https://studio.boardgamearena.com/table/table/loadSaveState.html?table=$tableId&state=1",

				// Calls the function below to update SQL
				"https://studio.boardgamearena.com/1/$game/$game/loadBugSQL.html?table=$tableId&report_id=$reportId",

				// Emulates "clear PHP cache" in control panel
				// Needed at the end because BGA is caching player info
				"https://studio.boardgamearena.com/admin/studio/clearGameserverPhpCache.html?game=$game",
			],
		]);
	}

	// For bug reports in studio, replace all references to users with my user ids.
	function loadBugSQL($reportId) {
		$studioPlayer = self::getCurrentPlayerId();
		$players = self::getObjectListFromDb("SELECT player_id FROM player", true);

		// Change for your game
		// We are setting the current state to match the start of a player's turn if it's already game over
		$sql = [
			"UPDATE global SET global_value=2 WHERE global_id=1 AND global_value=99",
		];
		foreach ($players as $pId) {
			// All games can keep this SQL
			$sql[] = "UPDATE player SET player_id=$studioPlayer WHERE player_id=$pId";
			$sql[] = "UPDATE global SET global_value=$studioPlayer WHERE global_value=$pId";
			$sql[] = "UPDATE stats SET stats_player_id=$studioPlayer WHERE stats_player_id=$pId";

			// Add game-specific SQL update the tables for your game
			$sql[] = "UPDATE tile SET owner=$studioPlayer WHERE owner=$pId";
			$sql[] = "UPDATE leader SET owner=$studioPlayer WHERE owner=$pId";
			$sql[] = "UPDATE point SET player=$studioPlayer WHERE player=$pId";

			// This could be improved, it assumes you had sequential studio accounts before loading
			// e.g., quietmint0, quietmint1, quietmint2, etc. are at the table
			$studioPlayer++;
		}
		$msg = "<b>Loaded <a href='https://boardgamearena.com/bug?id=$reportId' target='_blank'>bug report $reportId</a></b><hr><ul><li>" . implode(';</li><li>', $sql) . ';</li></ul>';
		self::warn($msg);
		self::notifyAllPlayers('message', $msg, []);

		foreach ($sql as $q) {
			self::DbQuery($q);
		}
		self::reloadPlayersBasicInfos();
	}

///////////////////////////////////////////////////////////////////////////////////:
	////////// DB upgrade
	//////////

	/*
		        upgradeTableDb:

		        You don't have to care about this until your game has been published on BGA.
		        Once your game is on BGA, this method is called everytime the system detects a game running with your old
		        Database scheme.
		        In this case, if you change your Database scheme, you just have to apply the needed changes in order to
		        update the game database and allow the game to continue to run with your new version.

	*/

	function upgradeTableDb($from_version) {
		// $from_version is the current version of this game database, in numerical form.
		// For example, if the game was running with a release of your game named "140430-1345",
		// $from_version is equal to 1404301345

		// Example:
		//        if( $from_version <= 1404301345 )
		//        {
		//            // ! important ! Use DBPREFIX_<table_name> for all tables
		//
		//            $sql = "ALTER TABLE DBPREFIX_xxxxxxx ....";
		//            self::applyDbUpgradeToAllDB( $sql );
		//        }
		//        if( $from_version <= 1405061421 )
		//        {
		//            // ! important ! Use DBPREFIX_<table_name> for all tables
		//
		//            $sql = "CREATE TABLE DBPREFIX_xxxxxxx ....";
		//            self::applyDbUpgradeToAllDB( $sql );
		//        }
		//        // Please add your future database scheme changes here
		//
		//

	}
}
