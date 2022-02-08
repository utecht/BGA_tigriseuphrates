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
 * states.inc.php
 *
 * TigrisEuphrates game states description
 *
 */

$machinestates = array(

	// The initial state. Please do not modify.
	1 => array(
		"name" => "gameSetup",
		"description" => "",
		"type" => "manager",
		"action" => "stGameSetup",
		"transitions" => array("" => STATE_PLAYER_TURN),
	),

	// Note: ID=2 => your first state

	STATE_PLAYER_TURN => array(
		"name" => "playerTurn",
		"description" => clienttranslate('${actplayer} must take action ${action_number} of 2'),
		"descriptionmyturn" => clienttranslate('${you} must take action ${action_number} of 2'),
		"type" => "activeplayer",
		"args" => "arg_playerTurn",
		"possibleactions" => array("placeLeader", "placeTile", "discard", "pickupLeader", "undo", "pass"),
		"transitions" => array(
			// leaders and revolts
			"placeRevoltSupport" => STATE_REVOLT_SUPPORT, "safeLeader" => STATE_INCREMENT_ACTION,
			// tiles war
			"warFound" => STATE_WAR_PROGRESS,
			// tiles no war
			"safeNoMonument" => STATE_INCREMENT_ACTION, "safeMonument" => STATE_BUILD_MONUMENT,
			// civilization tile
			"buildCivilizationBuilding" => STATE_BUILD_CIVILIZATION_BUILDING,
			// discard
			"nextAction" => STATE_INCREMENT_ACTION, "endGame" => STATE_FINAL_SCORING,
			// pass
			"pass" => STATE_INCREMENT_ACTION,
			// zombie
			"zombiePass" => STATE_INCREMENT_ACTION, "undo" => STATE_PLAYER_TURN,
		),
	),

	STATE_WAR_SUPPORT => array(
		"name" => "supportWar",
		"description" => clienttranslate('${actplayer} may send support'),
		"descriptionmyturn" => clienttranslate('${you} may send support'),
		"type" => "activeplayer",
		"args" => "arg_showWar",
		"possibleactions" => array("placeSupport", "undo"),
		"transitions" => array("placeSupport" => STATE_WAR_PROGRESS, "zombiePass" => STATE_WAR_PROGRESS, "undo" => STATE_PLAYER_TURN, "unpickLeader" => STATE_SELECT_WAR_LEADER),
	),

	STATE_REVOLT_SUPPORT => array(
		"name" => "supportRevolt",
		"description" => clienttranslate('${actplayer} may send revolt (red) support'),
		"descriptionmyturn" => clienttranslate('${you} may send revolt (red) support'),
		"type" => "activeplayer",
		"args" => "arg_showRevolt",
		"possibleactions" => array("placeSupport", "undo"),
		"transitions" => array("placeSupport" => STATE_REVOLT_PROGRESS, "zombiePass" => STATE_REVOLT_PROGRESS, "undo" => STATE_PLAYER_TURN),
	),

	STATE_SELECT_WAR_LEADER => array(
		"name" => "warLeader",
		"description" => clienttranslate('${actplayer} must select war leader'),
		"descriptionmyturn" => clienttranslate('${you} must select war leader'),
		"type" => "activeplayer",
		"args" => "arg_pickWarLeader",
		"possibleactions" => array("selectWarLeader", "undo"),
		"transitions" => array("placeSupport" => STATE_WAR_SUPPORT, "leaderSelected" => STATE_WAR_PROGRESS, "zombiePass" => STATE_WAR_SUPPORT, "undo" => STATE_PLAYER_TURN),
	),

	STATE_REVOLT_PROGRESS => array(
		"name" => "revoltProgress",
		"description" => clienttranslate('Progressing revolt'),
		"type" => "game",
		"action" => "stRevoltProgress",
		"transitions" => array("placeSupport" => STATE_REVOLT_SUPPORT, "concludeRevolt" => STATE_INCREMENT_ACTION),
	),

	STATE_WAR_PROGRESS => array(
		"name" => "warProgress",
		"description" => clienttranslate('Progressing war'),
		"type" => "game",
		"action" => "stWarProgress",
		"transitions" => array("pickLeader" => STATE_SELECT_WAR_LEADER, "placeSupport" => STATE_WAR_SUPPORT, "nextWar" => STATE_WAR_PROGRESS, "warMonument" => STATE_BUILD_MONUMENT, "noWar" => STATE_INCREMENT_ACTION, "warCivilization" => STATE_BUILD_CIVILIZATION_BUILDING),
	),

	STATE_BUILD_MONUMENT => array(
		"name" => "buildMonument",
		"description" => clienttranslate('${actplayer} may build monument'),
		"descriptionmyturn" => clienttranslate('${you} may build monument'),
		"type" => "activeplayer",
		"args" => "arg_showKingdoms",
		"possibleactions" => array("selectMonument", "pass", "undo"),
		"transitions" => array("buildMonument" => STATE_INCREMENT_ACTION, "pass" => STATE_INCREMENT_ACTION, "zombiePass" => STATE_INCREMENT_ACTION, "undo" => STATE_PLAYER_TURN, "multiMonument" => STATE_MULTI_MONUMENT, "multiWonder" => STATE_MULTI_WONDER, "buildWonder" => STATE_INCREMENT_ACTION),
	),

	STATE_BUILD_CIVILIZATION_BUILDING => array(
		"name" => "buildCivilizationBuilding",
		"description" => clienttranslate('${actplayer} may pick tile to build a civilization building'),
		"descriptionmyturn" => clienttranslate('${you} may pick tile to build a civilization building'),
		"type" => "activeplayer",
		"args" => "arg_showKingdoms",
		"possibleactions" => array("buildCivilizationBuilding", "pass", "undo"),
		"transitions" => array("noMonument" => STATE_INCREMENT_ACTION, "pass" => STATE_INCREMENT_ACTION, "zombiePass" => STATE_INCREMENT_ACTION, "undo" => STATE_PLAYER_TURN, "monumentFound" => STATE_BUILD_MONUMENT),
	),

	STATE_MULTI_WONDER => array(
		"name" => "multiWonder",
		"description" => clienttranslate('${actplayer} must pick center for wonder'),
		"descriptionmyturn" => clienttranslate('${you} must pick center for wonder'),
		"type" => "activeplayer",
		"args" => "arg_showKingdoms",
		"possibleactions" => array("selectWonderTile"),
		"transitions" => array("buildWonder" => STATE_INCREMENT_ACTION, "zombiePass" => STATE_INCREMENT_ACTION),
	),

	STATE_MULTI_MONUMENT => array(
		"name" => "multiMonument",
		"description" => clienttranslate('${actplayer} must pick top left tile for monument'),
		"descriptionmyturn" => clienttranslate('${you} must pick top left tile for monument'),
		"type" => "activeplayer",
		"args" => "arg_showKingdoms",
		"possibleactions" => array("selectMonumentTile"),
		"transitions" => array("buildMonument" => STATE_INCREMENT_ACTION, "zombiePass" => STATE_INCREMENT_ACTION),
	),

	STATE_PICK_TREASURE => array(
		"name" => "pickTreasure",
		"description" => clienttranslate('${actplayer} must take treasure'),
		"descriptionmyturn" => clienttranslate('${you} must take treasure'),
		"type" => "activeplayer",
		"args" => "arg_pickTreasure",
		"possibleactions" => array("pickTreasure", "undo"),
		"transitions" => array("pickTreasure" => STATE_INCREMENT_ACTION, "zombiePass" => STATE_INCREMENT_ACTION, "undo" => STATE_PLAYER_TURN),
	),

	STATE_END_TURN_CONFIRM => array(
		"name" => "endTurnConfirm",
		"description" => clienttranslate('${actplayer} must confirm turn'),
		"descriptionmyturn" => clienttranslate('${you} must confirm turn'),
		"type" => "activeplayer",
		"args" => "arg_playerTurn",
		"possibleactions" => array("undo", "confirm"),
		"transitions" => array("endTurn" => STATE_NEXT_PLAYER, "zombiePass" => STATE_NEXT_PLAYER, "undo" => STATE_PLAYER_TURN, "wonderScore" => STATE_WONDER_SCORE),
	),

	STATE_INCREMENT_ACTION => array(
		"name" => "incrementAction",
		"description" => clienttranslate('Incrementing Action'),
		"type" => "game",
		"updateGameProgression" => true,
		"action" => "stIncrementAction",
		"transitions" => array("pickTreasure" => STATE_PICK_TREASURE, "confirmTurn" => STATE_END_TURN_CONFIRM, "secondAction" => STATE_PLAYER_TURN, "endGame" => STATE_FINAL_SCORING, "endTurn" => STATE_NEXT_PLAYER, "wonderScore" => STATE_WONDER_SCORE),
	),

	STATE_WONDER_SCORE => array(
		"name" => "wonderScore",
		"description" => clienttranslate('${actplayer} must pick point color from wonder'),
		"descriptionmyturn" => clienttranslate('${you} must pick point color from wonder'),
		"type" => "activeplayer",
		"args" => "arg_showKingdoms",
		"possibleactions" => array("pickPoint", "undo"),
		"transitions" => array("next" => STATE_NEXT_PLAYER, "zombiePass" => STATE_NEXT_PLAYER, "undo" => STATE_PLAYER_TURN),
	),

	STATE_NEXT_PLAYER => array(
		"name" => "nextPlayer",
		"description" => clienttranslate('Next Player'),
		"type" => "game",
		"updateGameProgression" => true,
		"action" => "stNextPlayer",
		"transitions" => array("nextPlayer" => STATE_PLAYER_TURN, "endGame" => STATE_FINAL_SCORING),
	),

	STATE_FINAL_SCORING => array(
		"name" => "finalScoring",
		"description" => clienttranslate('Final Scoring'),
		"type" => "game",
		"updateGameProgression" => true,
		"action" => "stFinalScoring",
		"transitions" => array("endGame" => STATE_END_GAME),
	),

	// Final state.
	// Please do not modify (and do not overload action/args methods).
	STATE_END_GAME => array(
		"name" => "gameEnd",
		"description" => clienttranslate("End of game"),
		"type" => "manager",
		"action" => "stGameEnd",
		"args" => "argGameEnd",
	),

);
