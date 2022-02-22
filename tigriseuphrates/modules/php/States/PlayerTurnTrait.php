<?php
namespace TAE\States;

use TAE\Managers\Kingdoms;

trait PlayerTurnTrait {

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
		$kingdoms = Kingdoms::findKingdoms($board, $leaders);
		foreach ($kingdoms as $kingdom) {
			$green_leader_id = false;
			foreach ($kingdom['leaders'] as $leader) {
				if ($leader['kind'] == 'green') {
					$green_leader_id = $leader['id'];
				}
			}
			if ($green_leader_id !== false && Kingdoms::kingdomHasTwoTreasures($kingdom)) {
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

		self::setGameStateValue("current_action_count", 3);
		if (self::canUndo()) {
			$this->gamestate->nextState("confirmTurn");
		} else {
			if (self::canWonderScore($kingdoms, $player_id)) {
				$this->gamestate->nextState("wonderScore");
			} else {
				$this->gamestate->nextState("endTurn");
			}
		}
	}

	function stNextPlayer() {
		$player_id = self::getActivePlayerId();
		$player_name = self::getActivePlayerName();
		// award monument points
		$board = self::getCollectionFromDB("select * from tile where state = 'board'");
		$leaders = self::getCollectionFromDB("select * from leader where onBoard = '1'");
		$kingdoms = Kingdoms::findKingdoms($board, $leaders);
		$monuments = self::getCollectionFromDB("select * from monument where onBoard = '1'");
		foreach ($monuments as $monument) {
			$pos = [$monument['posX'], $monument['posY']];
			foreach ($kingdoms as $kingdom) {
				if (in_array($pos, $kingdom['pos'])) {
					foreach ($kingdom['leaders'] as $leader) {
						if ($leader['owner'] == $player_id && $leader['kind'] == $monument['color1']) {
							self::score($monument['color1'], 1, $player_id, $player_name, 'monument', $monument['id']);
							self::incStat(1, "monument_points", $player_id);
						}
						if ($leader['owner'] == $player_id && $leader['kind'] == $monument['color2']) {
							self::score($monument['color2'], 1, $player_id, $player_name, 'monument', $monument['id']);
							self::incStat(1, "monument_points", $player_id);
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

	function arg_playerTurn() {
		$player_id = self::getActivePlayerId();
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

}