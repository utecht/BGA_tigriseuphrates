<?php
namespace TAE\States;

use TAE\Managers\Board;
use TAE\Managers\Kingdoms;

trait WarTrait {
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
		if (self::getGameStateValue('civilization_buildings') == CIVILIZATION_VARIANT) {
			$line_count = Board::getLineCount($board, $union_tile);
			$building = self::getObjectFromDB("select * from building where kind = '" . $union_tile['kind'] . "'");
			$num_to_beat = 2;
			if ($building['onBoard'] == '1') {
				if (Board::inLine($board, $union_tile, $building['posX'], $building['posY'])) {
					$num_to_beat = NO_ID;
				} else {
					$other = Board::getTileXY($board, $building['posX'], $building['posY']);
					$num_to_beat = Board::getLineCount($board, $other);
				}
			}
			if ($line_count > $num_to_beat) {
				self::setGameStateValue("potential_building_tile_id", $union_tile['id']);
				$this->gamestate->nextState("warCivilization");
				return;
			}
		}

		if (self::getGameStateValue('wonder_variant') == WONDER_VARIANT) {
			$wonder_built = self::getUniqueValueFromDB("select onBoard from monument where color1 = 'wonder'");
			if ($wonder_built == '0') {
				$wonder_count = Board::getWonderCount($board, $union_tile);
				if ($wonder_count > 0) {
					self::setGameStateValue("potential_monument_tile_id", $union_tile['id']);
					self::giveExtraTime($player_id);
					self::undoSavePoint();
					$this->gamestate->nextState("warMonument");
					return;
				}
			}
		}

		$monument_count = self::getUniqueValueFromDB("select count(*) from monument where onBoard = '0'");
		if (Board::getMonumentCount($board, $union_tile) > 0 && $monument_count > 0) {
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
		$kingdoms = Kingdoms::findKingdoms($board, $leaders);
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

			$attacker_board_strength = Kingdoms::calculateKingdomStrength($leaders[$attacker_id], $kingdoms);
			$defender_board_strength = Kingdoms::calculateKingdomStrength($leaders[$defender_id], $kingdoms);

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
			// tiles under buildings cannot be removed
			$building = null;
			if (self::getGameStateValue('civilization_buildings') == CIVILIZATION_VARIANT) {
				$building = self::getObjectFromDB("select * from building where kind = '" . $war_color . "' and onBoard = '1'");
			}
			foreach ($kingdoms as $kingdom) {
				if (array_key_exists($loser, $kingdom['leaders'])) {
					foreach ($kingdom['tiles'] as $tile) {
						$remove = false;
						if ($tile['kind'] === $leaders[$loser]['kind']) {
							$supported_leaders = array();
							// don't remove red that are supporting leaders
							if ($tile['kind'] == 'red') {
								$supported_leaders = Board::findNeighbors($tile['posX'], $tile['posY'], $kingdom['leaders']);
							}
							if (count($supported_leaders) == 0) {
								$remove = true;
							} else if (count($supported_leaders) == 1) {
								if ($supported_leaders[0] == $loser) {
									$remove = true;
								}
							}
						}
						// Don't remove tiles containing treasures
						if ($tile['hasTreasure'] === '1') {
							$remove = false;
						}
						// Don't remove tiles containing civilization buildings
						if ($building != null) {
							if ($tile['posX'] == $building['posX'] && $tile['posY'] == $building['posY']) {
								$remove = false;
							}
						}
						if ($remove == true) {
							$tiles_to_remove[] = $tile['id'];
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
			self::incStat($points, "war_points", $winning_player_id);

			// reset states and move to next war
			self::setGameStateValue("current_war_state", WAR_NO_WAR);
			self::setGameStateValue("current_attacker", NO_ID);
			self::setGameStateValue("current_defender", NO_ID);
			$this->gamestate->changeActivePlayer($original_player);
			self::giveExtraTime($original_player);
			$this->gamestate->nextState("nextWar");
			return;
		}

		$warring_kingdoms = Kingdoms::neighborKingdoms($union_tile['posX'], $union_tile['posY'], $kingdoms);

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
	function arg_pickWarLeader() {
		$player_id = self::getActivePlayerId();
		$board = self::getCollectionFromDB("select * from tile where state = 'board'");
		$leaders = self::getCollectionFromDB("select * from leader where onBoard = '1'");
		$kingdoms = Kingdoms::findKingdoms($board, $leaders);
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

		$warring_kingdoms = Kingdoms::neighborKingdoms($union_tile['posX'], $union_tile['posY'], $kingdoms);
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
				'strength' => Kingdoms::calculateKingdomStrength($leader, $kingdoms),
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

	function arg_showWar() {
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
}
