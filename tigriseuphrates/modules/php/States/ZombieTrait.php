<?php
namespace TAE\States;

trait ZombieTrait {
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
				$kingdoms = Kingdoms::findKingdoms($board, $leaders);
				$union_tile = false;
				foreach ($board as $tile) {
					if ($tile['isUnion'] === '1') {
						$union_tile = $tile;
					}
				}

				$warring_kingdoms = Kingdoms::neighborKingdoms($union_tile['posX'], $union_tile['posY'], $kingdoms);
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
				$kingdoms = Kingdoms::findKingdoms($board, $leaders);
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
					if ($green_leader_id !== false && Kingdoms::kingdomHasTwoTreasures($kingdom)) {
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
}
