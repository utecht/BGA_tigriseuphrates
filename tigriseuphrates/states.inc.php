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

if (!defined('STATE_END_GAME')) { // guard since this included multiple times
   define("STATE_PLAYER_TURN", 2);
   define("STATE_INCREMENT_ACTION", 4);
   define("STATE_WAR_SUPPORT", 6);
   define("STATE_REVOLT_SUPPORT", 7);
   define("STATE_SELECT_WAR_LEADER", 9);
   define("STATE_BUILD_MONUMENT", 10);
   define("STATE_WAR_PROGRESS", 11);
   define("STATE_REVOLT_PROGRESS", 12);
   define("STATE_PICK_AMULET", 13);
   define("STATE_END_GAME", 99);
}

 
$machinestates = array(

    // The initial state. Please do not modify.
    1 => array(
        "name" => "gameSetup",
        "description" => "",
        "type" => "manager",
        "action" => "stGameSetup",
        "transitions" => array( "" => STATE_PLAYER_TURN )
    ),
    
    // Note: ID=2 => your first state

    STATE_PLAYER_TURN => array(
    		"name" => "playerTurn",
    		"description" => clienttranslate('${actplayer} must take an action ${action_number} of 2'),
    		"descriptionmyturn" => clienttranslate('${you} must take an action ${action_number} of 2'),
    		"type" => "activeplayer",
            "args" => "arg_playerTurn",
    		"possibleactions" => array( "placeLeader", "placeTile", "discard" ),
    		"transitions" => array(
                 // leaders and revolts
                 "placeRevoltSupport" => STATE_REVOLT_SUPPORT, "safeLeader" => STATE_INCREMENT_ACTION,
                 // tiles war
                 "warFound" => STATE_WAR_PROGRESS,
                 // tiles no war
                 "safeNoMonument" => STATE_INCREMENT_ACTION, "safeMonument" => STATE_BUILD_MONUMENT,
                 // discard
                 "nextAction" => STATE_INCREMENT_ACTION, "endGame" => STATE_END_GAME
             )
    ),

    STATE_WAR_SUPPORT => array(
            "name" => "supportWar",
            "description" => clienttranslate('${actplayer} may send support'),
            "descriptionmyturn" => clienttranslate('${you} may send support'),
            "type" => "activeplayer",
            "possibleactions" => array( "placeSupport" ),
            "transitions" => array( "placeSupport" => STATE_WAR_PROGRESS)
    ),

    STATE_REVOLT_SUPPORT => array(
            "name" => "supportRevolt",
            "description" => clienttranslate('${actplayer} may send revolt (red) support'),
            "descriptionmyturn" => clienttranslate('${you} may send revolt (red) support'),
            "type" => "activeplayer",
            "possibleactions" => array( "placeSupport" ),
            "transitions" => array( "placeSupport" => STATE_REVOLT_PROGRESS)
    ),

    STATE_SELECT_WAR_LEADER => array(
            "name" => "warLeader",
            "description" => clienttranslate('${actplayer} must select war leader'),
            "descriptionmyturn" => clienttranslate('${you} must select war leader'),
            "type" => "activeplayer",
            "possibleactions" => array( "selectWarLeader" ),
            "transitions" => array( "leaderSelected" => STATE_WAR_SUPPORT )
    ),

    STATE_REVOLT_PROGRESS => array(
            "name" => "revoltProgress",
            "description" => clienttranslate('Progressing revolt'),
            "type" => "game",
            "updateGameProgression" => true,
            "action" => "stRevoltProgress",
            "transitions" => array( "placeSupport" => STATE_REVOLT_SUPPORT, "concludeRevolt" => STATE_INCREMENT_ACTION )
    ),

    STATE_WAR_PROGRESS => array(
            "name" => "warProgress",
            "description" => clienttranslate('Progressing war'),
            "type" => "game",
            "updateGameProgression" => true,
            "action" => "stWarProgress",
            "transitions" => array( "pickLeader" => STATE_SELECT_WAR_LEADER, "placeSupport" => STATE_WAR_SUPPORT, "nextWar" => STATE_WAR_PROGRESS, "warMonument" => STATE_BUILD_MONUMENT, "noWar" => STATE_INCREMENT_ACTION )
    ),

    STATE_BUILD_MONUMENT => array(
            "name" => "buildMonument",
            "description" => clienttranslate('${actplayer} may build monument'),
            "descriptionmyturn" => clienttranslate('${you} may build monument'),
            "type" => "activeplayer",
            "possibleactions" => array( "buildMonument", "pass" ),
            "transitions" => array( "buildMonument" => STATE_INCREMENT_ACTION, "pass" => STATE_INCREMENT_ACTION )
    ),

    STATE_PICK_AMULET => array(
            "name" => "pickAmulet",
            "description" => clienttranslate('${actplayer} must take amulet'),
            "descriptionmyturn" => clienttranslate('${you} must take amulet'),
            "type" => "activeplayer",
            "possibleactions" => array( "pickAmulet" ),
            "transitions" => array( "pickAmulet" => STATE_INCREMENT_ACTION )
    ),

    STATE_INCREMENT_ACTION => array(
            "name" => "incrementAction",
            "description" => clienttranslate('Incrementing Action'),
            "type" => "game",
            "updateGameProgression" => true,
            "action" => "stIncrementAction",
            "transitions" => array( "pickAmulet" => STATE_PICK_AMULET, "endTurn" => STATE_PLAYER_TURN, "secondAction" => STATE_PLAYER_TURN, "endGame" => STATE_END_GAME )
    ),
    
    // Final state.
    // Please do not modify (and do not overload action/args methods).
    STATE_END_GAME => array(
        "name" => "gameEnd",
        "description" => clienttranslate("End of game"),
        "type" => "manager",
        "action" => "stGameEnd",
        "args" => "argGameEnd"
    )

);



