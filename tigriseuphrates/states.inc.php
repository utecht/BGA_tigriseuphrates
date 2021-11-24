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

/*
   Game state machine is a tool used to facilitate game developpement by doing common stuff that can be set up
   in a very easy way from this configuration file.

   Please check the BGA Studio presentation about game state to understand this, and associated documentation.

   Summary:

   States types:
   _ activeplayer: in this type of state, we expect some action from the active player.
   _ multipleactiveplayer: in this type of state, we expect some action from multiple players (the active players)
   _ game: this is an intermediary state where we don't expect any actions from players. Your game logic must decide what is the next game state.
   _ manager: special type for initial and final state

   Arguments of game states:
   _ name: the name of the GameState, in order you can recognize it on your own code.
   _ description: the description of the current game state is always displayed in the action status bar on
                  the top of the game. Most of the time this is useless for game state with "game" type.
   _ descriptionmyturn: the description of the current game state when it's your turn.
   _ type: defines the type of game states (activeplayer / multipleactiveplayer / game / manager)
   _ action: name of the method to call when this game state become the current game state. Usually, the
             action method is prefixed by "st" (ex: "stMyGameStateName").
   _ possibleactions: array that specify possible player actions on this step. It allows you to use "checkAction"
                      method on both client side (Javacript: this.checkAction) and server side (PHP: self::checkAction).
   _ transitions: the transitions are the possible paths to go from a game state to another. You must name
                  transitions in order to use transition names in "nextState" PHP method, and use IDs to
                  specify the next game state for each transition.
   _ args: name of the method to call to retrieve arguments for this gamestate. Arguments are sent to the
           client side to be used on "onEnteringState" or to set arguments in the gamestate description.
   _ updateGameProgression: when specified, the game progression is updated (=> call to your getGameProgression
                            method).
*/

//    !! It is not a good idea to modify this file when a game is running !!

if (!defined('STATE_END_GAME')) { // guard since this included multiple times
   define("STATE_PLAYER_TURN", 2);
   define("STATE_CHECK_REVOLT", 3);
   define("STATE_INCREMENT_ACTION", 4);
   define("STATE_TURN_CLEANUP", 5);
   define("STATE_REVOLT_SUPPORT", 6);
   define("STATE_CHECK_WAR", 7);
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
    		"transitions" => array( "placeLeader" => STATE_CHECK_REVOLT, "placeTile" => STATE_CHECK_WAR, "placeCatastrophe" => STATE_INCREMENT_ACTION, "discard" => STATE_INCREMENT_ACTION)
    ),

    STATE_CHECK_REVOLT => array(
            "name" => "checkRevolt",
            "description" => clienttranslate('Checking for a revolt'),
            "type" => "game",
            "updateGameProgression" => false,
            "transitions" => array( "revoltFound" => STATE_REVOLT_SUPPORT, "safe" => STATE_INCREMENT_ACTION )
    ),

    STATE_REVOLT_SUPPORT => array(
            "name" => "supportRevolt",
            "description" => clienttranslate('${actplayer} may support revolt'),
            "descriptionmyturn" => clienttranslate('${you} may support revolt'),
            "type" => "activeplayer",
            "possibleactions" => array( "placeSupport", "pass" ),
            "transitions" => array( "placeSupport" => STATE_REVOLT_SUPPORT, "pass" => STATE_REVOLT_SUPPORT, "revoltConcluded" => STATE_INCREMENT_ACTION )
    ),

    STATE_CHECK_WAR => array(
            "name" => "checkWar",
            "description" => clienttranslate('Checking for a war'),
            "type" => "game",
            "updateGameProgression" => false,
            "transitions" => array( "warFound" => STATE_SUPPORT_WAR, "multiWarFound" => STATE_SELECT_WAR_LEADER, "safeNoMonument" => STATE_INCREMENT_ACTION, "safeMonument" => STATE_BUILD_MONUMENT )
    ),

    STATE_WAR_SUPPORT => array(
            "name" => "supportWar",
            "description" => clienttranslate('${actplayer} may support war'),
            "descriptionmyturn" => clienttranslate('${you} may support war'),
            "type" => "activeplayer",
            "possibleactions" => array( "placeSupport", "pass" ),
            "transitions" => array( "placeSupport" => STATE_WAR_SUPPORT, "pass" => STATE_WAR_SUPPORT, "warConcluded" => STATE_CHECK_WAR )
    ),

    STATE_SELECT_WAR_LEADER => array(
            "name" => "warLeader",
            "description" => clienttranslate('${actplayer} must select war leader'),
            "descriptionmyturn" => clienttranslate('${you} must select war leader'),
            "type" => "activeplayer",
            "possibleactions" => array( "selectWarLeader" ),
            "transitions" => array( "selectWarLeader" => STATE_WAR_SUPPORT )
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
            "transitions" => array( "endTurn" => STATE_TURN_CLEANUP, "secondAction" => STATE_PLAYER_TURN )
    ),

    STATE_TURN_CLEANUP => array(
            "name" => "turnCleanup",
            "description" => clienttranslate('Ending turn for ${actplayer}'),
            "type" => "game",
            "updateGameProgression" => true,
            "transitions" => array( "nextPlayer" => STATE_PLAYER_TURN, "endGame" => STATE_END_GAME )
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



