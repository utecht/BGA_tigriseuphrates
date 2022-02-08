<?php
namespace TAE\States;

use TAE\Managers\Board;
use TAE\Managers\Kingdoms;
use TAE\Managers\Leaders;

trait RevoltTrait {
	function arg_showRevolt() {
		$board = self::getCollectionFromDB("select * from tile where state = 'board'");
		$leaders = self::getCollectionFromDB("select * from leader where onBoard = '1'");
		$kingdoms = Kingdoms::findKingdoms($board, $leaders);
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
				foreach (Board::findNeighbors($attacker['posX'], $attacker['posY'], $kingdom['tiles']) as $tile_id) {
					if ($board[$tile_id]['kind'] == 'red') {
						$attacker_board_strength++;
					}
				}
			}
			if (array_key_exists($defender['id'], $kingdom['leaders'])) {
				foreach (Board::findNeighbors($defender['posX'], $defender['posY'], $kingdom['tiles']) as $tile_id) {
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
				'strength' => Kingdoms::calculateKingdomStrength($leader, $kingdoms),
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

		$attacker_board_strength = Leaders::calculateBoardStrength($leaders[$attacker_id], $board);
		$defender_board_strength = Leaders::calculateBoardStrength($leaders[$defender_id], $board);

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
		self::incStat(1, 'revolt_points', $leaders[$winner]['owner']);

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
}