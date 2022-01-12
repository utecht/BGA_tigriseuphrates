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

//    !! It is not a good idea to modify this file when a game is running !!

if (!defined('STATE_END_GAME')) {
	// guard since this included multiple times
	define("STATE_PLAYER_TURN", 2);
	define("STATE_INCREMENT_ACTION", 4);
	define("STATE_WAR_SUPPORT", 6);
	define("STATE_REVOLT_SUPPORT", 7);
	define("STATE_SELECT_WAR_LEADER", 9);
	define("STATE_BUILD_MONUMENT", 10);
	define("STATE_WAR_PROGRESS", 11);
	define("STATE_REVOLT_PROGRESS", 12);
	define("STATE_PICK_TREASURE", 13);
	define("STATE_FINAL_SCORING", 14);
	define("STATE_MULTI_MONUMENT", 15);
	define("STATE_END_TURN_CONFIRM", 16);
	define("STATE_NEXT_PLAYER", 17);
	define("STATE_END_GAME", 99);
}

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
		"possibleactions" => array("placeLeader", "placeTile", "discard", "pickupLeader", "undo"),
		"transitions" => array(
			// leaders and revolts
			"placeRevoltSupport" => STATE_REVOLT_SUPPORT, "safeLeader" => STATE_INCREMENT_ACTION,
			// tiles war
			"warFound" => STATE_WAR_PROGRESS,
			// tiles no war
			"safeNoMonument" => STATE_INCREMENT_ACTION, "safeMonument" => STATE_BUILD_MONUMENT,
			// discard
			"nextAction" => STATE_INCREMENT_ACTION, "endGame" => STATE_FINAL_SCORING,
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
		"transitions" => array("placeSupport" => STATE_WAR_PROGRESS, "zombiePass" => STATE_WAR_PROGRESS, "undo" => STATE_PLAYER_TURN),
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
		"transitions" => array("pickLeader" => STATE_SELECT_WAR_LEADER, "placeSupport" => STATE_WAR_SUPPORT, "nextWar" => STATE_WAR_PROGRESS, "warMonument" => STATE_BUILD_MONUMENT, "noWar" => STATE_INCREMENT_ACTION),
	),

	STATE_BUILD_MONUMENT => array(
		"name" => "buildMonument",
		"description" => clienttranslate('${actplayer} may build monument'),
		"descriptionmyturn" => clienttranslate('${you} may build monument'),
		"type" => "activeplayer",
		"args" => "arg_showKingdoms",
		"possibleactions" => array("selectMonument", "pass", "undo"),
		"transitions" => array("buildMonument" => STATE_INCREMENT_ACTION, "pass" => STATE_INCREMENT_ACTION, "zombiePass" => STATE_INCREMENT_ACTION, "undo" => STATE_PLAYER_TURN, "multiMonument" => STATE_MULTI_MONUMENT),
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
		"transitions" => array("endTurn" => STATE_NEXT_PLAYER, "zombiePass" => STATE_NEXT_PLAYER, "undo" => STATE_PLAYER_TURN),
	),

	STATE_INCREMENT_ACTION => array(
		"name" => "incrementAction",
		"description" => clienttranslate('Incrementing Action'),
		"type" => "game",
		"updateGameProgression" => true,
		"action" => "stIncrementAction",
		"transitions" => array("pickTreasure" => STATE_PICK_TREASURE, "endTurn" => STATE_END_TURN_CONFIRM, "secondAction" => STATE_PLAYER_TURN, "endGame" => STATE_FINAL_SCORING),
	),

	STATE_NEXT_PLAYER => array(
		"name" => "nextPlayer",
		"description" => clienttranslate('Next Player'),
		"type" => "game",
		"updateGameProgression" => true,
		"action" => "stNextPlayer",
		"transitions" => array("nextPlayer" => STATE_PLAYER_TURN),
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
