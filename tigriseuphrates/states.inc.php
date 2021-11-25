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
   define("STATE_REVOLT_SUPPORT", 6);
   define("STATE_WAR_SUPPORT", 8);
   define("STATE_SELECT_WAR_LEADER", 9);
   define("STATE_BUILD_MONUMENT", 10);
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
    		"description" => clienttranslate('${actplayer} must take an action'),
    		"descriptionmyturn" => clienttranslate('${you} must take an action'),
    		"type" => "activeplayer",
    		"possibleactions" => array( "placeLeader", "placeTile", "placeCatastrophe", "discard" ),
    		"transitions" => array(
                 // leaders and revolts
                 "placeRevoltSupport" => STATE_REVOLT_SUPPORT, "safeLeader" => STATE_INCREMENT_ACTION,
                 // tiles war
                 "warFound" => STATE_WAR_SUPPORT, "multiWarFound" => STATE_SELECT_WAR_LEADER,
                 // tiles no war
                 "safeNoMonument" => STATE_INCREMENT_ACTION, "safeMonument" => STATE_BUILD_MONUMENT,
                 // discard
                 "discard" => STATE_INCREMENT_ACTION
             )
    ),

    STATE_REVOLT_SUPPORT => array(
            "name" => "supportRevolt",
            "description" => clienttranslate('${actplayer} may support revolt'),
            "descriptionmyturn" => clienttranslate('${you} may support revolt'),
            "type" => "activeplayer",
            "possibleactions" => array( "placeSupport" ),
            "transitions" => array( "placeSupport" => STATE_REVOLT_SUPPORT, "revoltConcluded" => STATE_INCREMENT_ACTION )
    ),

    STATE_WAR_SUPPORT => array(
            "name" => "supportWar",
            "description" => clienttranslate('${actplayer} may support war'),
            "descriptionmyturn" => clienttranslate('${you} may support war'),
            "type" => "activeplayer",
            "possibleactions" => array( "placeSupport" ),
            "transitions" => array(
                // war continions
                "placeSupport" => STATE_WAR_SUPPORT,
                // war over more wars
                "nextWar" => STATE_SELECT_WAR_LEADER,
                // war over monument
                "warsConcludedMonument" => STATE_BUILD_MONUMENT,
                // war over no monument
                "warsConcludedNoMonument" => STATE_INCREMENT_ACTION
            )
    ),

    STATE_SELECT_WAR_LEADER => array(
            "name" => "warLeader",
            "description" => clienttranslate('${actplayer} must select war leader'),
            "descriptionmyturn" => clienttranslate('${you} must select war leader'),
            "type" => "activeplayer",
            "possibleactions" => array( "selectWarLeader" ),
            "transitions" => array( "leaderSelected" => STATE_WAR_SUPPORT )
    ),

    STATE_BUILD_MONUMENT => array(
            "name" => "buildMonument",
            "description" => clienttranslate('${actplayer} may build monument'),
            "descriptionmyturn" => clienttranslate('${you} may build monument'),
            "type" => "activeplayer",
            "possibleactions" => array( "buildMonument", "pass" ),
            "transitions" => array( "buildMonument" => STATE_INCREMENT_ACTION, "pass" => STATE_INCREMENT_ACTION )
    ),

    STATE_INCREMENT_ACTION => array(
            "name" => "incrementAction",
            "description" => clienttranslate('Incrementing Action'),
            "type" => "game",
            "updateGameProgression" => true,
            "action" => "stIncrementAction",
            "transitions" => array( "endTurn" => STATE_PLAYER_TURN, "secondAction" => STATE_PLAYER_TURN, "endGame" => STATE_END_GAME )
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



