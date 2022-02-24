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

$swdNamespaceAutoload = function ($class) {
	$classParts = explode('\\', $class);
	if ($classParts[0] == 'TAE' && in_array('Testing', $classParts) == false) {
		array_shift($classParts);
		$file = dirname(__FILE__) . '/modules/php/' . implode(DIRECTORY_SEPARATOR, $classParts) . '.php';
		if (file_exists($file)) {
			require_once $file;
		} else {
			var_dump('Cannot find file : ' . $file);
		}
	}
};
spl_autoload_register($swdNamespaceAutoload, true, true);

require_once APP_GAMEMODULE_PATH . 'module/table/table.game.php';

use TAE\Managers\Board;
use TAE\Managers\Kingdoms;
use TAE\Managers\Leaders;
use TAE\Managers\Players;
use TAE\Notifications\Score;

class TigrisEuphrates extends Table {
	use TAE\States\PlayerTurnTrait;
	use TAE\States\RevoltTrait;
	use TAE\States\WarTrait;
	use TAE\States\ScoringTrait;
	use TAE\States\ZombieTrait;
	use TAE\LoadBugTrait;
	use TAE\LoadBugTrait;

	public static $instance = null;
	function __construct() {
		// Your global variables labels:
		//  Here, you can assign labels to global variables you are using for this game.
		//  You can use any number of global variables with IDs between 10 and 99.
		//  If your game has options (variants), you also have to associate here a label to
		//  the corresponding ID in gameoptions.inc.php.
		// Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
		parent::__construct();
		self::$instance = $this;

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
			"potential_building_tile_id" => 28,
			"game_board" => GAME_BOARD,
			"scoring" => SCORING_STYLE,
			"english_variant" => WAR_SUPPORT,
			"wonder_variant" => MONUMENT_VARIANT,
			"civilization_buildings" => ADVANCED_GAME_RULES,
			//    "my_second_global_variable" => 11,
			//      ...
			//    "my_first_game_variant" => 100,
			//    "my_second_game_variant" => 101,
			//      ...
		));
	}

	public static function get() {
		return self::$instance;
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
		Players::setupNewGame($players, $options);
		Board::setupNewGame($players, $options);
		Leaders::setupNewGame($players, $options);

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
		self::setGameStateInitialValue('potential_building_tile_id', NO_ID);

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
		self::initStat('player', 'war_points', 0);
		self::initStat('player', 'revolt_points', 0);
		self::initStat('player', 'monument_points', 0);

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
		$result['buildings'] = [];
		if (self::getGameStateValue('civilization_buildings') == CIVILIZATION_VARIANT) {
			$result['buildings'] = self::getObjectListFromDB("select * from building");
		}
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
		$starting_temple_count = count($this->starting_temples);
		if (self::getGameStateValue('game_board') == ADVANCED_BOARD) {
			$starting_temple_count = count($this->alt_starting_temples);
		}
		return self::drawnFromBagPercent($player_count, $remaining_tiles, $starting_temple_count);
	}

//////////////////////////////////////////////////////////////////////////////
	//////////// Utility functions
	////////////

	/*
		        In this space, you can put any utility methods useful for your game logic
	*/

	function drawnFromBagPercent($player_count, $tiles_in_bag, $starting_temple_count) {
		// How many tiles from bag started game on board or in players hands
		$starting_tiles = $starting_temple_count + (6 * $player_count);
		// Progress should start at 0% and end at 100% so we subtract out tiles that start out of bag
		$total_tiles = (STARTING_RED_TILES + STARTING_BLUE_TILES + STARTING_GREEN_TILES + STARTING_BLACK_TILES) - $starting_tiles;
		// intval to round off percents and somewhat obfuscate result
		return intval(ceil(($total_tiles - $tiles_in_bag) / $total_tiles * 100));
	}

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
		Score::playerScore($color, $points, $player_id, $player_name, $source, $id, $animate);
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
			self::DbQuery("
	            update
	                tile
	            set
	                state = 'hand',
	                owner = '" . $player_id . "'
	            where
	                state = 'bag'
	            ");
			self::notifyAllPlayers(
				"lastTileDrawn",
				clienttranslate('${player_name} has ended the game by attempting to draw from an empty bag.'),
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

	// find any leaders not standing next to temples and return them to owner
	function exileOrphanedLeaders($board, $leaders) {
		foreach ($leaders as $leader) {
			$is_safe = false;
			foreach (Board::findNeighbors($leader['posX'], $leader['posY'], $board) as $neighbor_id) {
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
		$tiles = Board::getMonumentSquare($board, $tile);
		if ($tiles == false) {
			throw new BgaUserException(self::_("Must select the correct shape of monument"));
		}
		// find the top left tile and flip over tiles in DB
		$x = NO_ID;
		$y = NO_ID;
		$flip_ids = array();
		$building = false;
		if (self::getGameStateValue('civilization_buildings') == CIVILIZATION_VARIANT) {
			$building = self::getObjectFromDB("select * from building where kind = '" . $tile['kind'] . "'");
		}
		foreach ($tiles as $flip) {
			self::DbQuery("update tile set kind = 'flipped' where id = '" . $flip['id'] . "'");
			$flip_ids[] = $flip['id'];
			if ($x > $flip['posX']) {
				$x = $flip['posX'];
			}
			if ($y > $flip['posY']) {
				$y = $flip['posY'];
			}
			if (self::getGameStateValue('civilization_buildings') == CIVILIZATION_VARIANT) {
				if ($building['posX'] == $flip['posX'] && $building['posY'] == $flip['posY']) {
					self::DbQuery("
						update
							building
						set
		                    onBoard = '0',
		                    posX = NULL,
		                    posY = NULL
		                where
		                    id = '" . $building['id'] . "'
						");
					self::notifyAllPlayers(
						"removeCivilizationBuilding",
						clienttranslate('${player_name} returned the ${kind} civilization building by building a monument.'),
						array(
							'building' => $building,
							'kind' => $building['kind'],
							'player_name' => $player_name,
						)
					);
				}
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

	function buildWonder($wonder, $board, $tile) {
		$player_name = self::getActivePlayerName();
		$player_id = self::getActivePlayerId();
		$wonder_id = $wonder['id'];
		$tiles = Board::getWonderPlus($board, $tile);
		if ($tiles == false) {
			throw new BgaUserException(self::_("Must select the correct shape of monument"));
		}
		$x = $tiles[0]['posX'];
		$y = $tiles[0]['posY'];
		$flip_ids = array();
		$building = false;
		if (self::getGameStateValue('civilization_buildings') == CIVILIZATION_VARIANT) {
			$building = self::getObjectFromDB("select * from building where kind = '" . $tile['kind'] . "'");
		}
		foreach ($tiles as $flip) {
			self::DbQuery("update tile set kind = 'flipped' where id = '" . $flip['id'] . "'");
			$flip_ids[] = $flip['id'];
			if (self::getGameStateValue('civilization_buildings') == CIVILIZATION_VARIANT) {
				if ($building['posX'] == $flip['posX'] && $building['posY'] == $flip['posY']) {
					self::DbQuery("
						update
							building
						set
		                    onBoard = '0',
		                    posX = NULL,
		                    posY = NULL
		                where
		                    id = '" . $building['id'] . "'
						");
					self::notifyAllPlayers(
						"removeCivilizationBuilding",
						clienttranslate('${player_name} returned the ${kind} civilization building by building a wonder.'),
						array(
							'building' => $building,
							'kind' => $building['kind'],
							'player_name' => $player_name,
						)
					);
				}
			}
		}
		self::disableUndo();
		self::setGameStateValue('db_undo', DB_UNDO_YES);
		// update the monument DB and notify all players
		self::DbQuery("update monument set onBoard = '1', posX = '" . $x . "', posY = '" . $y . "' where id = '" . $wonder_id . "'");
		self::incStat(1, 'monuments_built', $player_id);
		self::notifyAllPlayers(
			"placeWonder",
			clienttranslate('${player_name} placed wonder'),
			array(
				'player_name' => $player_name,
				'flip_ids' => $flip_ids,
				'wonder_id' => $wonder_id,
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
		$this->gamestate->nextState("buildWonder");

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
		if ($monument['onBoard'] == '1') {
			throw new BgaVisibleSystemException(self::_("Attempt to place monument already on board, reload"));
		}
		$tile = self::getObjectFromDB("select * from tile where id = '" . $potential_monument_tile_id . "'");
		$board = self::getCollectionFromDB("select * from tile where state = 'board'");

		if (self::getGameStateValue('wonder_variant') == WONDER_VARIANT && $monument['color1'] == 'wonder') {
			$wonder_count = Board::getWonderCount($board, $tile);
			if ($wonder_count > 1) {
				self::setGameStateValue('current_monument', $monument_id);
				$this->gamestate->nextState("multiWonder");
			} else {
				self::buildWonder($monument, $board, $tile);
			}
			return;
		}

		// check if monument is correct color
		if ($monument['color1'] != $tile['kind'] and $monument['color2'] != $tile['kind']) {
			throw new BgaUserException(clienttranslate("Must select a monument of the correct color"));
		}

		$monument_count = Board::getMonumentCount($board, $tile);
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
		$tile = Board::getTileXY($board, $pos_x, $pos_y);
		if ($tile === false) {
			throw new BgaUserException(self::_("Must select a tile"));
		}
		$potential_monument_tile_id = self::getGameStateValue("potential_monument_tile_id");

		$monument_square = Board::getMonumentSquare($board, $tile);
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

	function selectWonderTile($pos_x, $pos_y) {
		self::checkAction('selectWonderTile');
		$player_id = self::getActivePlayerId();
		$player_name = self::getActivePlayerName();

		$monument_id = self::getGameStateValue('current_monument');
		$monument = self::getObjectFromDB("select * from monument where id = '" . $monument_id . "'");
		$board = self::getCollectionFromDB("select * from tile where state = 'board'");
		$tile = Board::getTileXY($board, $pos_x, $pos_y);
		if ($tile === false) {
			throw new BgaUserException(self::_("Must select a tile"));
		}
		$potential_monument_tile_id = self::getGameStateValue("potential_monument_tile_id");

		$wonder_plus = Board::getWonderPlus($board, $tile);
		if ($wonder_plus == false) {
			throw new BgaUserException(self::_("Must pick tile that can form a wonder shape."));
		}
		$center_x = $wonder_plus[0]['posX'];
		$center_y = $wonder_plus[0]['posY'];
		$valid = false;
		foreach ($wonder_plus as $square) {
			if ($square['id'] == $potential_monument_tile_id) {
				$valid = true;
			}
		}
		if ($valid === false) {
			throw new BgaUserException(self::_("Must pick a valid tile for building momnument"));
		}
		if ($pos_x != $center_x || $pos_y != $center_y) {
			throw new BgaUserException(self::_("Must pick the center tile"));
		}
		self::buildWonder($monument, $board, $tile);
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

		$new_tile_count = count($support_ids);
		if (self::getGameStateValue('english_variant') == ENGLISH_VARIANT && $new_tile_count > 0) {
			$attacker_strength = 0;
			$defender_strength = 0;
			$board = self::getCollectionFromDB("select * from tile where state = 'board'");
			$leaders = self::getCollectionFromDB("select * from leader where onBoard = '1'");
			$defender_id = self::getGameStateValue("current_defender");
			$attacking_player_id = $leaders[$attacker_id]['owner'];
			if ($this->gamestate->state()['name'] == "supportWar") {
				$kingdoms = Kingdoms::findKingdoms($board, $leaders);
				$war_color = $leaders[$attacker_id]['kind'];

				// calculate strength
				$attacker_support = self::getUniqueValueFromDB("
	                select count(*) from tile where owner = '" . $attacking_player_id . "' and state = 'support' and kind = '" . $war_color . "'
	                ");

				$attacker_board_strength = Kingdoms::calculateKingdomStrength($leaders[$attacker_id], $kingdoms);
				$defender_board_strength = Kingdoms::calculateKingdomStrength($leaders[$defender_id], $kingdoms);
				$attacker_strength = intval($attacker_support) + $attacker_board_strength;
				$defender_strength = $defender_board_strength;
			} else {
				$attacker_support = self::getUniqueValueFromDB("
		            select count(*) from tile where owner = '" . $attacking_player_id . "' and state = 'support' and kind = 'red'
		            ");

				$attacker_board_strength = Leaders::calculateBoardStrength($leaders[$attacker_id], $board);
				$defender_board_strength = Leaders::calculateBoardStrength($leaders[$defender_id], $board);

				$attacker_strength = intval($attacker_support) + $attacker_board_strength;
				$defender_strength = $defender_board_strength;
			}
			if ($side == 'attacker') {
				if ($new_tile_count > 0 && $attacker_strength + $new_tile_count <= $defender_strength) {
					throw new BgaUserException(self::_("In English Variant, you must increase support to surpass defender strength."));
				}
			} else {
				if ($new_tile_count > 0 && $defender_strength + $new_tile_count != $attacker_strength) {
					throw new BgaUserException(self::_("In English Variant, you may only increase support to match attacker strength."));
				}
			}
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
			$existing_tile = Board::getTileXY($board, $pos_x, $pos_y);
			if (self::getGameStateValue('civilization_buildings') == CIVILIZATION_VARIANT) {
				$building = self::getObjectFromDB("select * from building where posX = '" . $pos_x . "' and posY = '" . $pos_y . "'");
				if ($building != null) {
					throw new BgaUserException(self::_("A catastrophe may not be placed under a civilization building"));
				}
			}
			$removed_leaders = array();
			if ($existing_tile !== false) {
				// notify players to remove tile
				if ($existing_tile['kind'] == 'red') {
					foreach (Board::findNeighbors($pos_x, $pos_y, $leaders) as $nl_id) {
						$neighboring_leader = $leaders[$nl_id];
						$safe = false;
						foreach (Board::findNeighbors($neighboring_leader['posX'], $neighboring_leader['posY'], $board) as $nt_id) {
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
		$kingdoms = Kingdoms::findKingdoms($board, $leaders);
		$neighbor_kingdoms = Kingdoms::neighborKingdoms($pos_x, $pos_y, $kingdoms);

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
				clienttranslate('${player_name} placed <span style="color:${war_color}">${tile_name}</span> at ${coords} uniting two kingdoms. No points will be scored.'),
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
			$scoring_leader = false;
			foreach ($scoring_kingdom['leaders'] as $kingdom_leader) {
				if ($kingdom_leader['kind'] == $kind) {
					$scoring_leader = $kingdom_leader;
				} else if ($kingdom_leader['kind'] == 'black' && $scoring_leader == false) {
					$scoring_leader = $kingdom_leader;
				}
			}
			if ($scoring_leader !== false) {
				$scorer_name = self::getPlayerNameById($scoring_leader['owner']);
				if (self::getGameStateValue('civilization_buildings') == CIVILIZATION_VARIANT) {
					$building = self::getObjectFromDB('select * from building where kind = "' . $kind . '"');
					if ($building['onBoard'] == '1') {
						$pos = [$building['posX'], $building['posY']];
						if (in_array($pos, $scoring_kingdom['pos'])) {
							self::score($kind, 1, $scoring_leader['owner'], $scorer_name, 'building', $building['id']);
						}
					}
				}
				self::score($kind, 1, $scoring_leader['owner'], $scorer_name, 'leader', $scoring_leader['id']);
			}
		}

		if (self::getGameStateValue('civilization_buildings') == CIVILIZATION_VARIANT) {
			$line_count = Board::getLineCount($board, $new_tile);
			$building = self::getObjectFromDB("select * from building where kind = '" . $new_tile['kind'] . "'");
			$num_to_beat = 2;
			if ($building['onBoard'] == '1') {
				$other = Board::getTileXY($board, $building['posX'], $building['posY']);
				if (Board::inLine($board, $new_tile, $building['posX'], $building['posY'])) {
					$num_to_beat = NO_ID;
				} else {
					$num_to_beat = Board::getLineCount($board, $other);
				}
			}
			if ($line_count > $num_to_beat) {
				self::setGameStateValue("potential_building_tile_id", $new_tile['id']);
				$this->gamestate->nextState("buildCivilizationBuilding");
				return;
			}
		}

		if (self::getGameStateValue('wonder_variant') == WONDER_VARIANT) {
			$wonder_built = self::getUniqueValueFromDB("select onBoard from monument where color1 = 'wonder'");
			if ($wonder_built == '0') {
				$wonder_count = Board::getWonderCount($board, $new_tile);
				if ($wonder_count > 0) {
					self::setGameStateValue("potential_monument_tile_id", $new_tile['id']);
					self::giveExtraTime($player_id);
					self::undoSavePoint();
					$this->gamestate->nextState("safeMonument");
					return;
				}
			}
		}

		$monument_count = Board::getMonumentCount($board, $new_tile);
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
		if (self::getGameStateValue('game_board') == ADVANCED_BOARD) {
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
		$kingdoms = Kingdoms::findKingdoms($board, $leaders);
		$neighbor_kingdoms = Kingdoms::neighborKingdoms($pos_x, $pos_y, $kingdoms);
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

		$kingdoms = Kingdoms::findKingdoms($board, $leaders);

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
		$warring_kingdoms = Kingdoms::neighborKingdoms($union_tile['posX'], $union_tile['posY'], $kingdoms);

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
			$this->disableUndo();
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
		$kingdoms = Kingdoms::findKingdoms($board, $leaders);

		// find all kingdoms with green leaders
		$outer_temple = false;
		$outer_temples = $this->outerTemples;
		if (self::getGameStateValue('game_board') == ADVANCED_BOARD) {
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
			if ($green_leader_id !== false && Kingdoms::kingdomHasTwoTreasures($kingdom)) {
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

	function pickPoint($color) {
		if ($color != 'red' && $color != 'black' && $color != 'green' && $color != 'blue') {
			throw new BgaUserException(self::_("Invalid point color, reload"));
		}
		$player_id = self::getActivePlayerId();
		$player_name = self::getActivePlayerName();
		$board = self::getCollectionFromDB("select * from tile where state = 'board'");
		$wonder_id = self::getUniqueValueFromDB("select id from monument where color1 = 'wonder'");
		self::score($color, 1, $player_id, $player_name, 'monument', $wonder_id, true);
		self::incStat(1, "monument_points", $player_id);
		$this->gamestate->nextState('next');
	}

	function buildCivilizationBuilding($pos_x, $pos_y) {
		self::checkAction('buildCivilizationBuilding');
		$last_tile_id = self::getGameStateValue('potential_building_tile_id');
		if ($last_tile_id == NO_ID) {
			throw new BgaVisibleSystemException(self::_("Building civilization is in an unexpected state. Please report bug and then pass. Sorry"));
		}
		$player_id = self::getActivePlayerId();
		$player_name = self::getActivePlayerName();
		$board = self::getCollectionFromDB("select * from tile where state = 'board'");
		$last_tile = $board[$last_tile_id];
		$tile = Board::getTileXY($board, $pos_x, $pos_y);
		if ($tile == false) {
			throw new BgaUserException(self::_("Must select a tile to place building on."));
		}
		if ($tile['kind'] != $last_tile['kind']) {
			throw new BgaUserException(self::_("Tile selected must be same color as tile placed"));
		}
		if (Board::inLine($board, $tile, $last_tile['posX'], $last_tile['posY']) == false) {
			throw new BgaUserException(self::_("Building must be placed in-line with previous tile"));
		}
		$line_count = Board::getLineCount($board, $tile);
		$building = self::getObjectFromDB("select * from building where kind = '" . $tile['kind'] . "'");
		$num_to_beat = 2;
		if ($building['onBoard'] == '1') {
			$other = Board::getTileXY($board, $building['posX'], $building['posY']);
			$num_to_beat = Board::getLineCount($board, $other);
		}
		if ($line_count > $num_to_beat) {
			// TODO: probably re-enable this
			self::disableUndo();
			self::DbQuery("
				update
					building
				set
                    onBoard = '1',
                    posX = '" . $tile['posX'] . "',
                    posY = '" . $tile['posY'] . "'
                where
                    id = '" . $building['id'] . "'
				");
			$building['posX'] = $tile['posX'];
			$building['posY'] = $tile['posY'];
			$building['onBoard'] = '1';
			self::notifyAllPlayers(
				"buildCivilizationBuilding",
				clienttranslate('${player_name} built the ${kind} civilization building.'),
				array(
					'building' => $building,
					'kind' => $building['kind'],
					'player_name' => $player_name,
				)
			);
		}

		if (self::getGameStateValue('wonder_variant') == WONDER_VARIANT) {
			$wonder_built = self::getUniqueValueFromDB("select onBoard from monument where color1 = 'wonder'");
			if ($wonder_built == '0') {
				$wonder_count = Board::getWonderCount($board, $tile);
				if ($wonder_count > 0) {
					self::setGameStateValue("potential_monument_tile_id", $tile['id']);
					self::giveExtraTime($player_id);
					self::undoSavePoint();
					$this->gamestate->nextState("monumentFound");
					return;
				}
			}
		}

		$monument_count = Board::getMonumentCount($board, $tile);
		$remaining_monuments = self::getUniqueValueFromDB("select count(*) from monument where onBoard = '0'");
		if ($remaining_monuments > 0 && $monument_count > 0) {
			self::setGameStateValue("potential_monument_tile_id", $tile['id']);
			self::giveExtraTime($player_id);
			self::undoSavePoint();
			$this->gamestate->nextState("monumentFound");
		} else {
			$this->gamestate->nextState("noMonument");
		}
	}

	function pass() {
		self::checkAction('pass');
		self::disableUndo();
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
			$kingdoms = Kingdoms::findKingdoms($board, $leaders);
			$neighbor_kingdoms = Kingdoms::neighborKingdoms($tile['posX'], $tile['posY'], $kingdoms);
			// claw back scoring
			if (count($neighbor_kingdoms) == 1 && $tile['kind'] != 'catastrophe') {
				$scoring_kingdom = $kingdoms[$neighbor_kingdoms[0]];
				$scoring_leader = false;
				foreach ($scoring_kingdom['leaders'] as $kingdom_leader) {
					if ($kingdom_leader['kind'] == $tile['kind']) {
						$scoring_leader = $kingdom_leader;
					} else if ($kingdom_leader['kind'] == 'black' && $scoring_leader == false) {
						$scoring_leader = $kingdom_leader;
					}
				}
				if ($scoring_leader !== false) {
					$scorer_name = self::getPlayerNameById($scoring_leader['owner']);
					self::score($tile['kind'], -1, $scoring_leader['owner'], $scorer_name, 'leader', $scoring_leader['id']);
					if (self::getGameStateValue('civilization_buildings') == CIVILIZATION_VARIANT) {
						$building = self::getObjectFromDB('select * from building where kind = "' . $tile['kind'] . '"');
						if ($building['onBoard'] == '1') {
							$pos = [$building['posX'], $building['posY']];
							if (in_array($pos, $scoring_kingdom['pos'])) {
								self::score($tile['kind'], -1, $scoring_leader['owner'], $scorer_name, 'building', $building['id']);
							}
						}
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

	function canWonderScore($kingdoms, $player_id) {
		if (self::getGameStateValue('wonder_variant') == WONDER_VARIANT) {
			$wonder = self::getObjectFromDB("select * from monument where color1 = 'wonder'");
			if ($wonder['onBoard'] == '1') {
				$pos = [$wonder['posX'], $wonder['posY']];
				foreach ($kingdoms as $kingdom) {
					if (in_array($pos, $kingdom['pos'])) {
						foreach ($kingdom['leaders'] as $leader) {
							if ($leader['kind'] == 'black' && $leader['owner'] == $player_id) {
								return true;
							}
						}
					}
				}
			}
		}
		return false;
	}

	function confirm() {
		self::checkAction('confirm');

		if (self::getGameStateValue('wonder_variant') == WONDER_VARIANT) {
			$player_id = self::getActivePlayerId();
			$board = self::getCollectionFromDB("select * from tile where state = 'board'");
			$leaders = self::getCollectionFromDB("select * from leader where onBoard = '1'");
			$kingdoms = Kingdoms::findKingdoms($board, $leaders);
			if (self::canWonderScore($kingdoms, $player_id)) {
				$this->gamestate->nextState("wonderScore");
				return;
			}
		}
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

	function arg_pickTreasure() {
		$player_id = self::getActivePlayerId();
		$board = self::getCollectionFromDB("select * from tile where state = 'board'");
		$leaders = self::getCollectionFromDB("select * from leader where onBoard = '1'");
		$kingdoms = Kingdoms::findKingdoms($board, $leaders);
		$small_kingdoms = array();
		foreach ($kingdoms as $kingdom) {
			$small_kingdoms[] = $kingdom['pos'];
		}

		$treasures = array();
		$mandatory_treasures = array();
		$outer_temples = $this->outerTemples;
		if (self::getGameStateValue('game_board') == ADVANCED_BOARD) {
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
			if ($green_leader_id !== false && Kingdoms::kingdomHasTwoTreasures($kingdom)) {
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
				'strength' => Kingdoms::calculateKingdomStrength($leader, $kingdoms),
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

	function arg_showKingdoms() {
		$board = self::getCollectionFromDB("select * from tile where state = 'board'");
		$leaders = self::getCollectionFromDB("select * from leader where onBoard = '1'");
		$kingdoms = Kingdoms::findKingdoms($board, $leaders);
		$small_kingdoms = array();
		foreach ($kingdoms as $kingdom) {
			$small_kingdoms[] = $kingdom['pos'];
		}

		$leader_strengths = [];
		foreach ($leaders as $leader) {
			$leader_strengths[] = [
				'strength' => Kingdoms::calculateKingdomStrength($leader, $kingdoms),
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
