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


require_once( APP_GAMEMODULE_PATH.'module/table/table.game.php' );


class TigrisEuphrates extends Table
{
	function __construct( )
	{
        // Your global variables labels:
        //  Here, you can assign labels to global variables you are using for this game.
        //  You can use any number of global variables with IDs between 10 and 99.
        //  If your game has options (variants), you also have to associate here a label to
        //  the corresponding ID in gameoptions.inc.php.
        // Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
        parent::__construct();
        
        self::initGameStateLabels( array( 
            //    "my_first_global_variable" => 10,
            //    "my_second_global_variable" => 11,
            //      ...
            //    "my_first_game_variant" => 100,
            //    "my_second_game_variant" => 101,
            //      ...
        ) );        
	}
	
    protected function getGameName( )
    {
		// Used for translations and stuff. Please do not modify.
        return "tigriseuphrates";
    }	

    /*
        setupNewGame:
        
        This method is called only once, when a new game is launched.
        In this method, you must setup the game according to the game rules, so that
        the game is ready to be played.
    */
    protected function setupNewGame( $players, $options = array() )
    {    
        // Set the colors of the players with HTML color code
        // The default below is red/green/blue/orange/brown
        // The number of colors defined here must correspond to the maximum number of players allowed for the gams
        $gameinfos = self::getGameinfos();
        $default_colors = $gameinfos['player_colors'];
 
        // Create players
        // Note: if you added some extra field on "player" table in the database (dbmodel.sql), you can initialize it there.
        $sql = "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ";
        $values = array();
        foreach( $players as $player_id => $player )
        {
            $color = array_shift( $default_colors );
            $values[] = "('".$player_id."','$color','".$player['player_canal']."','".addslashes( $player['player_name'] )."','".addslashes( $player['player_avatar'] )."')";
        }
        $sql .= implode( $values, ',' );
        self::DbQuery( $sql );
        self::reattributeColorsBasedOnPreferences( $players, $gameinfos['player_colors'] );
        self::reloadPlayersBasicInfos();

        $starting_temples = array(
            [1, 1],
            [10, 0],
            [5, 2],
            [15, 1],
            [13, 4],
            [8, 6],
            [1, 7],
            [14, 8],
            [5, 9],
            [10, 10]
        );
        $all_tiles = array();
        $all_tiles = array_merge($all_tiles, array_fill(0, 57 - count($starting_temples), 'red'));
        $all_tiles = array_merge($all_tiles, array_fill(0, 30, 'black'));
        $all_tiles = array_merge($all_tiles, array_fill(0, 36, 'blue'));
        $all_tiles = array_merge($all_tiles, array_fill(0, 30, 'green'));
        shuffle($all_tiles);
        $sql = "INSERT INTO tile (id, state, owner, kind, posX, posY, hasAmulet) VALUES ";
        $values = array();
        $i = 0;
        foreach( $players as $player_id => $player ){
            $values[] = "('".$i."','hand','".$player_id."','catastrophe',NULL,NULL,'0')";
            $i++;
            $values[] = "('".$i."','hand','".$player_id."','catastrophe',NULL,NULL,'0')";
            $i++;
        }
        foreach( $starting_temples as $temple ){
            $values[] = "('".$i."','board',NULL,'red','".$temple[0]."','".$temple[1]."','1')";
            $i++;
        }
        foreach( $players as $player_id => $player ){
            for($c = 0; $c < 6; $c++){
                $color = array_shift($all_tiles);
                $values[] = "('".$i."','hand','".$player_id."','".$color."',NULL,NULL,'0')";
                $i++;
            }
        }
        foreach( $all_tiles as $color ){
            $values[] = "('".$i."','bag',NULL,'".$color."',NULL,NULL,'0')";
            $i++;
        }
        $sql .= implode( $values, ',' );
        self::DbQuery( $sql );
        
        /************ Start the game initialization *****/

        // Init global values with their initial values
        //self::setGameStateInitialValue( 'my_first_global_variable', 0 );
        
        // Init game statistics
        // (note: statistics used in this file must be defined in your stats.inc.php file)
        //self::initStat( 'table', 'table_teststat1', 0 );    // Init a table statistics
        //self::initStat( 'player', 'player_teststat1', 0 );  // Init a player statistics (for all players)

        // TODO: setup the initial game situation here
       

        // Activate first player (which is in general a good idea :) )
        $this->activeNextPlayer();

        /************ End of the game initialization *****/
    }

    /*
        getAllDatas: 
        
        Gather all informations about current game situation (visible by the current player).
        
        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refreshes the game page (F5)
    */
    protected function getAllDatas()
    {
        $result = array();
    
        $current_player_id = self::getCurrentPlayerId();    // !! We must only return informations visible by this player !!
    
        // Get information about players
        // Note: you can retrieve some extra field you added for "player" table in "dbmodel.sql" if you need it.
        $sql = "SELECT player_id id, player_score score FROM player ";
        $result['players'] = self::getCollectionFromDb( $sql );

        $result['board'] = self::getObjectListFromDB( "select * from tile where state = 'board'" );
        $result['hand'] = self::getObjectListFromDB( "select * from tile where state = 'hand' and owner = '".$current_player_id."'");
  
        // TODO: Gather all information about current game situation (visible by player $current_player_id).
  
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
    function getGameProgression()
    {
        // TODO: compute and return the game progression

        return 0;
    }


//////////////////////////////////////////////////////////////////////////////
//////////// Utility functions
////////////    

    /*
        In this space, you can put any utility methods useful for your game logic
    */



//////////////////////////////////////////////////////////////////////////////
//////////// Player actions
//////////// 

    // TODO: implement
    function placeSupport( $support_ids ){
        self::checkAction('placeSupport');
        // note a pass has no support ids


        // check if valid IDs

        // update their location to support

        // if attacker
        // calculate if opponent needs to respond

        // pass turn to opponent and state -> "placeSupport"

        // if defender
        // resolve revolt
        // change active back to attacker
        // state -> "revoltConcluded"
    }

    // TODO: implement
    function discard( $discard_ids ){
        self::checkAction('discard');
        $player_id = self::getActivePlayerId();

        // check if discard ids are valid

        // refill hand

        // check if game over
        // state -> "endGame"

        // move to next action
        // state -> "incrementAction"

    }

    // TODO: implement
    function placeCatastrophe( $catastrophe_id, $pos_x, $pos_y ){
        self::checkAction('placeCatastrophe');
        $player_id = self::getActivePlayerId();

        // check if tile and placement are valid

        // remove existing tile if present

        // if removed red, check surrounding tiles, for orphaned leaders and return

        $this->gamestate->nextState("incrementAction");
    }

    // TOOD: implement
    function placeTile( $tile_id, $pos_x, $pos_y ){
        self::checkAction('placeTile');
        $player_id = self::getActivePlayerId();

        // check if tile lay is valid

        // check if union

        // check if monument will be possible

        // if multiwar
        // state -> "multiWarFound"
        // elif single war
        // state -> "warFound"

        // if no wars
        // award points

        // if monument
        // state -> "safeMonument"
        // else
        // state -> "safeNoMonument"

    }

    // TODO: implement
    function placeLeader( $leader_id, $pos_x, $pos_y ){
        self::checkAction('placeLeader');
        $player_id = self::getActivePlayerId();

        // check if placement valid

        // check for revolt
        // mark leader as attacker and opponent as defender
        // state -> "placeRevoltSupport"
        // else
        // state -> "safeLeader"
    }

    // TODO: implement
    function selectWarLeader( $leader_id ){
        self::checkAction('selectWarLeader');
        $player_id = self::getActivePlayerId();

        // check if leader is valid

        $this->gamestate->nextState('leaderSelected');
    }

    function pass(){
        self::checkAction('pass');
        $this->gamestate->nextState('pass');
    }

    
//////////////////////////////////////////////////////////////////////////////
//////////// Game state arguments
////////////

    /*
        Here, you can create methods defined as "game state arguments" (see "args" property in states.inc.php).
        These methods function is to return some additional information that is specific to the current
        game state.
    */

    /*
    
    Example for game state "MyGameState":
    
    function argMyGameState()
    {
        // Get some values from the current game situation in database...
    
        // return values:
        return array(
            'variable1' => $value1,
            'variable2' => $value2,
            ...
        );
    }    
    */

//////////////////////////////////////////////////////////////////////////////
//////////// Game state actions
////////////


    // TODO: Implement
    function stIncrementAction(){
        // if first action, increment to two
        // reset values
        // state -> "secondAction"

        // if second

        // pickup amulets

        // award monument points

        // refill hand

        // check game-end
        // state -> "endGame"

        // activate next player
        // state -> "nextPlayer"
    }

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

    function zombieTurn( $state, $active_player )
    {
    	$statename = $state['name'];
    	
        if ($state['type'] === "activeplayer") {
            switch ($statename) {
                default:
                    $this->gamestate->nextState( "zombiePass" );
                	break;
            }

            return;
        }

        if ($state['type'] === "multipleactiveplayer") {
            // Make sure player is in a non blocking status for role turn
            $this->gamestate->setPlayerNonMultiactive( $active_player, '' );
            
            return;
        }

        throw new feException( "Zombie mode not supported at this game state: ".$statename );
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
    
    function upgradeTableDb( $from_version )
    {
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
