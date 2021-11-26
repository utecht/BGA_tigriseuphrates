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

if (!defined('NO_ID')) { // guard since this included multiple times
   define("WAR_NO_WAR", 0);
   define("WAR_ATTACKER_SUPPORT", 1);
   define("WAR_DEFENDER_SUPPORT", 2);
   define("WAR_START", 3);
   define("NO_ID", 99);
}

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
            "current_action_count" => 10,
            "current_attacker" => 11,
            "current_defender" => 12,
            "current_war_state" => 13,
            "original_player" => 14
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

        $leader_shapes = ['goat', 'lion', 'bow', 'urn'];
        shuffle($leader_shapes);
        $leader_colors = ['blue', 'green', 'red', 'black'];
        $sql = "INSERT INTO leader (id, shape, kind, owner) VALUES ";
        $values = array();
        $i = 0;
        $player_num = 0;
        foreach( $players as $player_id => $player ){
            $shape = $leader_shapes[$player_num];
            foreach( $leader_colors as $color ){
                $values[] = "('".$i."','".$shape."','".$color."','".$player_id."')";
                $i++;
            }
            $player_num++;
        }
        $sql .= implode( $values, ',' );
        self::DbQuery( $sql );
        
        /************ Start the game initialization *****/

        // Init global values with their initial values
        self::setGameStateInitialValue( 'current_action_count', 1 );
        self::setGameStateInitialValue( 'current_attacker', NO_ID );
        self::setGameStateInitialValue( 'current_defender', NO_ID );
        self::setGameStateInitialValue( 'current_war_state', WAR_NO_WAR );
        self::setGameStateInitialValue( 'original_player', NO_ID );
        
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
        $result['player'] = $current_player_id;

        $result['board'] = self::getObjectListFromDB( "select * from tile where state = 'board'" );
        $result['hand'] = self::getObjectListFromDB( "select * from tile where state = 'hand' and owner = '".$current_player_id."'");
        $result['leaders'] = self::getObjectListFromDB( "select * from leader");
  
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
    function drawTiles( $count, $player_id ){

        $next_tile = self::getUniqueValueFromDB("
            select
                min(id)
            from
                tile
            where
                state = 'bag';
            ");

        $top_tile = intval($next_tile) + $count;
        // final tile drawn, end game
        if($top_tile > 156){
            $this->gamestate->nextState("endGame");
            return;
        }

        self::DbQuery("
            update
                tile
            set
                state = 'hand',
                owner = '".$player_id."'
            where
                id >= '".$next_tile."' and
                id < '".$top_tile."'
            ");

        $new_tiles = self::getObjectListFromDB("
            select
                id, kind
            from tile
            where 
                id >= '".$next_tile."' and
                id < '".$top_tile."'
            ");

        $player_name = self::getActivePlayerName();

        self::notifyPlayer(
            $player_id,
            "drawTiles",
            clienttranslate('${player_name} drew ${count} tiles'),
            array(
                'player_name' => $player_name,
                'count' => $count,
                'tiles' => $new_tiles
            )
        );
    }

    function findNeighbors( $x, $y, $options ){
        $neighbors = array();
        $above = [ $x, $y - 1 ];
        $below = [ $x, $y + 1 ];
        $left = [ $x - 1, $y ];
        $right = [ $x + 1, $y ];
        foreach($options as $option){
            if($above[0] == $option['posX'] && $above[1] == $option['posY']){
                $neighbors[] = $option['id'];
            }
            if($below[0] == $option['posX'] && $below[1] == $option['posY']){
                $neighbors[] = $option['id'];
            }
            if($left[0] == $option['posX'] && $left[1] == $option['posY']){
                $neighbors[] = $option['id'];
            }
            if($right[0] == $option['posX'] && $right[1] == $option['posY']){
                $neighbors[] = $option['id'];
            }
        }
        return $neighbors;
    }

    function findKingdoms( $board, $leaders ){
        $kingdoms = array();
        $used_leaders = array();
        $used_tiles = array();

        foreach($leaders as $leader_id=>$leader){
            $kingdom = array(
                'leaders' => array(),
                'tiles' => array(),
                'pos' => array()
            );
            $to_test_leaders = array($leader_id);
            $to_test_tiles = array();
            while(count($to_test_leaders) > 0){
                $leader = $leaders[array_pop($to_test_leaders)];
                if(array_search($leader['id'], $used_leaders) === false){
                    $kingdom['leaders'][$leader['id']] = $leader;
                    $used_leaders[] = $leader['id'];
                    $x = $leader['posX'];
                    $y = $leader['posY'];
                    $kingdom['pos'][] = [$x, $y];
                    $potential_tiles = self::findNeighbors($x, $y, $board);
                    $potential_leaders = self::findNeighbors($x, $y, $leaders);

                    foreach($potential_tiles as $i=>$ptile){
                        if($board[$ptile]['kind'] == 'catastrophe'){
                            $potential_tiles = array_slice($potential_tiles, $i, 1);
                        }
                    }
                    $to_test_tiles = array_unique(array_merge($potential_tiles, $to_test_tiles));
                    $to_test_leaders = array_unique(array_merge($potential_leaders, $to_test_leaders));

                    while(count($to_test_tiles) > 0){
                        $tile = $board[array_pop($to_test_tiles)];
                        if(array_search($tile['id'], $used_tiles) === false){
                            $kingdom['tiles'][$tile['id']] = $tile;
                            $used_tiles[] = $tile['id'];
                            $x = $tile['posX'];
                            $y = $tile['posY'];
                            $kingdom['pos'][] = [$x, $y];
                            $potential_tiles = self::findNeighbors($x, $y, $board);
                            $potential_leaders = self::findNeighbors($x, $y, $leaders);

                            foreach($potential_tiles as $i=>$ptile){
                                if($board[$ptile]['kind'] == 'catastrophe'){
                                    $potential_tiles = array_slice($potential_tiles, $i, 1);
                                }
                            }
                            $to_test_tiles = array_unique(array_merge($potential_tiles, $to_test_tiles));
                            $to_test_leaders = array_unique(array_merge($potential_leaders, $to_test_leaders));
                        }
                    }
                }
            }
            $kingdoms[] = $kingdom;
        }
        return $kingdoms;
    }

    function neighborKingdoms($x, $y, $kingdoms){
        $neighbor_kingdoms = array();
        $above = [ $x, $y - 1 ];
        $below = [ $x, $y + 1 ];
        $left = [ $x - 1, $y ];
        $right = [ $x + 1, $y ];
        foreach($kingdoms as $i=>$kingdom){
            if(array_search($above, $kingdom['pos'])){
                if(array_search($i, $neighbor_kingdoms) == false){
                    $neighbor_kingdoms[] = $i;
                }
            }
            if(array_search($below, $kingdom['pos'])){
                if(array_search($i, $neighbor_kingdoms) == false){
                    $neighbor_kingdoms[] = $i;
                }
            }
            if(array_search($left, $kingdom['pos'])){
                if(array_search($i, $neighbor_kingdoms) == false){
                    $neighbor_kingdoms[] = $i;
                }
            }
            if(array_search($right, $kingdom['pos'])){
                if(array_search($i, $neighbor_kingdoms) == false){
                    $neighbor_kingdoms[] = $i;
                }
            }
        }
        return array_unique($neighbor_kingdoms); 
    }



//////////////////////////////////////////////////////////////////////////////
//////////// Player actions
//////////// 

    // TODO: implement
    function placeSupport( $support_ids ){
        self::checkAction('placeSupport');
        // note a pass has no support ids
        $player_id = self::getActivePlayerId();
        $hand = self::getCollectionFromDB("select * from tile where owner = '".$player_id."' and state = 'hand'");
        $war_state = self::getGameStateValue("current_war_state");
        $attacker_id = self::getGameStateValue("current_attacker");
        $defender_id = self::getGameStateValue("current_defender");

        $board = self::getCollectionFromDB("select * from tile where state = 'board'");
        $leaders = self::getCollectionFromDB("select * from leader where onBoard = '1'");

        $kingdoms = self::findKingdoms( $board, $leaders );
        $war_color = $leaders[$attacker_id]['kind'];

        // check if support ids are valid
        foreach($support_ids as $tile_id){
            if(array_key_exists($tile_id, $hand) == false){
               throw new feException("Error: Attempt to support with tiles not in hand.");
            }
            if($war_type == WAR_REVOLT and $hand[$tile_id]['kind'] != 'red'){
                throw new feException("Error: Only temples (red) may be played as support in a revolt.");
            }
            if($war_type == WAR_EXTERNAL and $hand[$tile_id]['kind'] != $war_color){
                throw new feException("Error: Only $war_color may be played as support in this war.");
            }
        }

        // update their location to support
        foreach($support_ids as $tile_id){
            self::DbQuery("
                update
                    tile
                set
                    state = 'support'
                where
                    id = '".$tile_id."'
                ");
        }
        $this->gamestate->nextState("placeSupport");
    }

    function discard( $discard_ids ){
        self::checkAction('discard');
        $player_id = self::getActivePlayerId();
        $hand = self::getCollectionFromDB("select * from tile where owner = '".$player_id."' and state = 'hand'");

        // check if discard ids are valid
        foreach($discard_ids as $tile_id){
            if(array_key_exists($tile_id, $hand) == false){
               throw new feException("Error: Attempt to discard tiles not in hand.");
           }
        }

        // TODO: make better update statement
        // discard tiles
        foreach($discard_ids as $tile_id){
            self::DbQuery("
                update
                    tile
                set
                    state = 'discard',
                    owner = NULL
                where
                    id = '".$tile_id."'
                ");
        }

        // refill hand
        $this->drawTiles($played_id, count($discard_ids));

        // move to next action
        $this->gamestate->nextState("incrementAction");

    }

    function placeTile( $tile_id, $pos_x, $pos_y ){
        self::checkAction('placeTile');
        $player_id = self::getActivePlayerId();
        $player_name = self::getActivePlayerName();

        // Check if tile is in players hand
        $hand = self::getCollectionFromDB("select * from tile where owner = '".$player_id."' and state = 'hand'");
        if(array_key_exists($tile_id, $hand) == false){
           throw new feException("Error: That tile is not in your hand.");
        }
        $kind = $hand[$tile_id]['kind'];

        $board = self::getCollectionFromDB("select * from tile where state = 'board'");
        $leaders = self::getCollectionFromDB("select * from leader where onBoard = '1'");

        // check if tile lay is valid
        foreach($board as $tile){
            if($pos_x == $tile['posX'] && $pos_y == $tile['posY']){
                if($kind != 'catastrophe'){
                   throw new feException("Error: Only a catastrophe may be played over another tile.");
                } else {
                   if($tile['hasAmulet'] == '1'){
                       throw new feException("Error: A catastrophe cannot be placed on an amulet.");
                   } 
                }
            }
        }

        foreach($leaders as $leader){
            if($pos_x == $leader['posX'] && $pos_y == $leader['posY']){
                throw new feException("Error: No tile may be placed over a leader.");
            }
        }

        $kingdoms = self::findKingdoms( $board, $leaders );
        $neighbor_kingdoms = self::neighborKingdoms($pos_x, $pos_y, $kingdoms);

        if(count($neighbor_kingdoms) > 2 and $tile['kind'] != 'catastrophe'){
            throw new feException("Error: A tile cannot join 3 kingdoms.");
        }

        $is_union = count($neighbor_kingdoms) == 2 and $tile['kind'] != 'catastrophe';
        
        if($is_union){
            self::setGameStateValue("original_player", $player_id);
            self::setGameStateValue("current_war_state", WAR_START);

            self::DbQuery("
                update
                    tile
                set
                    state = 'board',
                    owner = NULL,
                    isUnion = '1',
                    posX = '".$pos_x."',
                    posY = '".$pos_y."'
                where
                    id = '".$tile_id."';
                ");
            self::notifyAllPlayers(
                "placeTile",
                clienttranslate('${player_name} placed ${color} at ${x}x${y} and started war'),
                array(
                    'player_name' => $player_name,
                    'tile_id' => $tile_id,
                    'x' => $pos_x,
                    'y' => $pos_y,
                    'color' => 'union'
                )
            );
            $this->gamestate->nextState("warFound");
        } else {
            // discard any existing tile at pos_x, pos_y
            if($kind == 'catastrophe'){
                self::DbQuery("
                    update
                        tile
                    set
                        state = 'discard',
                        owner = NULL,
                        posX = NULL,
                        posY = NULL
                    where
                        posX = '".$pos_x."' and
                        posY = '".$pos_y."'
                    ");
            } else {
                if(count($neighbor_kingdoms) == 1){
                    $scoring_kingdom = $kingdoms[$neighbor_kingdoms[0]];
                    foreach($scoring_kingdom['leaders'] as $scoring_leader){
                        $score = false;
                        if($scoring_leader['kind'] == $kind){
                            $score = true;
                        } else if($scoring_leader['kind'] == 'black'){
                            $score = true;
                            foreach($scoring_kingdom['leaders'] as $other_leader){
                                if($other_leader['kind'] == $kind){
                                    $score = false;
                                }
                            }
                        }
                        if($score){
                            self::DbQuery("
                                update
                                    point
                                set
                                    ".$kind." = ".$kind." + 1
                                where
                                    player = '".$scoring_leader['owner']."'
                                ");
                            self::notifyAllPlayers(
                                "playerScore",
                                clienttranslate('${scorer_name} scored 1 ${color}'),
                                array(
                                    'scorer_name' => $scoring_leader['shape'],
                                    'color' => $kind
                                )
                            );
                        }
                    }
                }
            }

            self::DbQuery("
                update
                    tile
                set
                    state = 'board',
                    owner = NULL,
                    posX = '".$pos_x."',
                    posY = '".$pos_y."'
                where
                    id = '".$tile_id."';
                ");

            self::notifyAllPlayers(
                "placeTile",
                clienttranslate('${player_name} placed ${color} at ${x}x${y}'),
                array(
                    'player_name' => $player_name,
                    'tile_id' => $tile_id,
                    'x' => $pos_x,
                    'y' => $pos_y,
                    'color' => $kind
                )
            );

            // TODO: implement
            // check if monument will be possible

            // if monument
            // state -> "safeMonument"
            // else
            // state -> "safeNoMonument"

            $this->gamestate->nextState("safeNoMonument");
        }
    }

    function placeLeader( $leader_id, $pos_x, $pos_y ){
        self::checkAction('placeLeader');
        $player_id = self::getActivePlayerId();
        $player_name = self::getActivePlayerName();
        $board = self::getCollectionFromDB("select * from tile where state = 'board'");
        $leaders = self::getCollectionFromDB("select * from leader");
        $leader = $leaders[$leader_id];

        // check if leader is in hand and owned
        if($leader['owner'] != $player_id){
           throw new feException("Error: You may only play your leaders.");
        }
        if($leader['onBoard'] == '1'){
           throw new feException("Error: Leaders may only be placed from hand.");
        }

        // check if placement valid
        // leaders cannot be ontop of tiles
        foreach($board as $tile){
            if($pos_x == $tile['posX'] && $pos_y == $tile['posY']){
                throw new feException("Error: Leaders must be placed on blank space.");
            }
        }
        // leaders cannot be ontop of other leaders
        foreach($leaders as $other_leader){
            if($pos_x == $other_leader['posX'] && $pos_y == $other_leader['posY']){
                throw new feException("Error: Leaders must be placed on blank space.");
            }
        }

        // leaders must be adjacent to temples
        $x = intval($pos_x);
        $y = intval($pos_y);
        $above = [ $x, $y - 1 ];
        $below = [ $x, $y + 1 ];
        $left = [ $x - 1, $y ];
        $right = [ $x + 1, $y ];
        $valid = false;
        foreach($board as $tile){
            if($above[0] == $tile['posX'] && $above[1] == $tile['posY']){
                $valid = true;
            }
            if($below[0] == $tile['posX'] && $below[1] == $tile['posY']){
                $valid = true;
            }
            if($left[0] == $tile['posX'] && $left[1] == $tile['posY']){
                $valid = true;
            }
            if($right[0] == $tile['posX'] && $right[1] == $tile['posY']){
                $valid = true;
            }
        }
        if($valid == false){
            throw new feException("Error: Leaders must be placed adjacent to temple (red).");
        }

        $kingdoms = self::findKingdoms( $board, $leaders );
        $neighbor_kingdoms = self::neighborKingdoms($pos_x, $pos_y, $kingdoms);
        if(count($neighbor_kingdoms) > 1){
            throw new feException("Error: A leader may not join kingdoms.");
        }
        $start_revolt = false;
        if(count($neighbor_kingdoms) == 1){
            foreach($kingdoms[$neighbor_kingdoms[0]]['leaders'] as $neighbor_leader){
                if($neighbor_leader['kind'] == $leader['kind']){
                    $start_revolt = true;
                    self::setGameStateValue("current_attacker", $leader_id);
                    self::setGameStateValue("current_defender", $neighbor_leader['id']);
                }
            }
        }

        self::DbQuery("
            update
                leader
            set
                onBoard = '1',
                posX = '".$pos_x."',
                posY = '".$pos_y."'
            where
                id = '".$leader_id."';
            ");

        if($start_revolt){
            self::notifyAllPlayers(
                "placeLeader",
                clienttranslate('${player_name} placed ${color} leader at ${x}x${y} and started revolt'),
                array(
                    'player_name' => $player_name,
                    'leader_id' => $leader_id,
                    'x' => $pos_x,
                    'y' => $pos_y,
                    'color' => $leader['kind'],
                    'shape' => $leader['shape']
                )
            );
            $this->gamestate->nextState("placeRevoltSupport");
        } else {
            self::notifyAllPlayers(
                "placeLeader",
                clienttranslate('${player_name} placed ${color} leader at ${x}x${y}'),
                array(
                    'player_name' => $player_name,
                    'leader_id' => $leader_id,
                    'x' => $pos_x,
                    'y' => $pos_y,
                    'color' => $leader['kind'],
                    'shape' => $leader['shape']
                )
            );
            $this->gamestate->nextState("safeLeader");
        }
    }

    // TODO: implement
    function selectWarLeader( $leader_id ){
        self::checkAction('selectWarLeader');
        $player_id = self::getActivePlayerId();
        $board = self::getCollectionFromDB("select * from tile where state = 'board'");
        $leaders = self::getCollectionFromDB("select * from leader where onBoard = '1'");

        $kingdoms = self::findKingdoms( $board, $leaders );

        $warring_leaders = array();
        $potential_war_leaders = array_merge($kingdoms[0]['leaders'], $kingdoms[1]['leaders']);
        foreach($potential_war_leaders as $pleader){
            foreach($potential_war_leaders as $oleader){
                if($oleader['kind'] == $pleader['kind']){
                    $warring_leaders[] = $oleader;
                    $warring_leaders[] = $pleader;
                }
            }
        }
        $warring_leaders = array_unique($warring_leaders);
        $valid_leader = false;

        $attacking_leader = false;
        foreach($warring_leaders as $wleader){
            if($wleader['owner'] == $player_id && $wleader['id'] = $leader_id){
                $leader_valid = true;
                $attacking_leader = $wleader['id'];
            }
        }
        if($valid_leader === false){
            throw new feException("You must select a leader in the kingdoms currently at war");
        }
        $defending_leader = false;
        foreach($potential_war_leaders as $dleader){
            if($dleader['kind'] == $attacking_leader['kind']){
                $defending_leader = $dleader;
            }
        }
        self::setGameStateValue("current_attacker", $attacking_leader['id']);
        self::setGameStateValue("current_defender", $defending_leader['id']);
        self::setGameStateValue("current_war_state", WAR_ATTACKER_SUPPORT);
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

    function arg_playerTurn(){
        return array(
            'action_number' => self::getGameStateValue("current_action_count")
        );
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Game state actions
////////////


    function stIncrementAction(){
        $player_id = self::getActivePlayerId();
        if(self::getGameStateValue("current_action_count") == 1){
            self::setGameStateValue("current_action_count", 2);
            $this->gamestate->nextState("secondAction");
        } else {
            // TODO: Implement
            // if second
            // pickup amulets

            // award monument points

            // refill hand

            // check game-end
            // state -> "endGame"
            self::setGameStateValue("current_action_count", 1);
            $tile_count = self::getUniqueValueFromDB("
                select
                    count(*)
                from
                    tile
                where
                    owner = '".$player_id."' and
                    state = 'hand' and
                    kind != 'catastrophe';
                ");
            if($tile_count < 6){
                $this->drawTiles(6 - $tile_count, $player_id);
            }
            $this->activeNextPlayer();
            $this->gamestate->nextState("endTurn");
        }
    }

    function stRevoltProgress(){
        $player_id = self::getActivePlayerId();
        $war_state = self::getGameStateValue("current_war_state");
        $attacker_id = self::getGameStateValue("current_attacker");
        $defender_id = self::getGameStateValue("current_defender");

        $board = self::getCollectionFromDB("select * from tile where state = 'board'");
        $leaders = self::getCollectionFromDB("select * from leader where onBoard = '1'");

        if($war_state == WAR_ATTACKER_SUPPORT){
            self::setGameStateValue("current_war_state", WAR_DEFENDER_SUPPORT);
            $this->gamestate->changeActivePlayer( $leaders[$defender_id]['owner'] );
            $this->gamestate->nextState("placeSupport");
        } else {
            // TODO: finish resolving revolt
            self::setGameStateValue("current_war_state", WAR_NO_WAR);
            self::setGameStateValue("current_attacker", NO_ID);
            self::setGameStateValue("current_defender", NO_ID);
            $this->gamestate->changeActivePlayer( $leaders[$attacker_id]['owner'] );
            $this->gamestate->nextState("concludeRevolt");
        }
    }

    function stWarProgress(){
        $player_id = self::getActivePlayerId();
        $war_state = self::getGameStateValue("current_war_state");
        $attacker_id = self::getGameStateValue("current_attacker");
        $defender_id = self::getGameStateValue("current_defender");

        $board = self::getCollectionFromDB("select * from tile where state = 'board'");
        $leaders = self::getCollectionFromDB("select * from leader where onBoard = '1'");

        $kingdoms = self::findKingdoms( $board, $leaders );

        $warring_leaders = array();
        $potential_war_leaders = array_merge($kingdoms[0]['leaders'], $kingdoms[1]['leaders']);
        foreach($potential_war_leaders as $pleader){
            foreach($potential_war_leaders as $oleader){
                if($oleader['kind'] == $pleader['kind']){
                    $warring_leaders[] = $oleader;
                    $warring_leaders[] = $pleader;
                }
            }
        }
        $warring_leaders = array_unique($warring_leaders);
        $player_has_leader = false;
        $player_has_multi = false;
        $attacking_leader = false;
        foreach($warring_leaders as $wleader){
            if($wleader['owner'] == $player_id){
                if($player_has_leader){
                    $player_has_multi = true;
                } else {
                    $player_has_leader = true;
                    $attacking_leader = $wleader;
                }
            }
        }

        if($player_has_multi){
            $this->gamestate->nextState("pickLeader");
        } else if($player_has_multi == false and $player_has_leader){
            $defending_leader = false;
            foreach($potential_war_leaders as $dleader){
                if($dleader['kind'] == $attacking_leader['kind']){
                    $defending_leader = $dleader;
                }
            }
            self::setGameStateValue("current_attacker", $attacking_leader['id']);
            self::setGameStateValue("current_defender", $defending_leader['id']);
            self::setGameStateValue("current_war_state", WAR_ATTACKER_SUPPORT);
            $this->gamestate->nextState("placeSupport");
        } else if($player_has_leader == false){
            $this->activeNextPlayer();
            $this->gamestate->nextState("nextWar");
        } else if($war_state == WAR_ATTACKER_SUPPORT){
            self::setGameStateValue("current_war_state", WAR_DEFENDER_SUPPORT);
            $this->gamestate->changeActivePlayer( $leaders[$defender_id]['owner'] );
            $this->gamestate->nextState("placeSupport");
        } else if($war_state == WAR_DEFENDER_SUPPORT){
            // TODO: finish resolving war
            self::setGameStateValue("current_war_state", WAR_NO_WAR);
            self::setGameStateValue("current_attacker", NO_ID);
            self::setGameStateValue("current_defender", NO_ID);
            $this->gamestate->changeActivePlayer( $leaders[$attacker_id]['owner'] );
            $this->gamestate->nextState("nextWar");
        } else {

            // TODO: implement
            // flip union tile
            // check if monument possible
            // next action
            $original_player = self::getGameStateValue("original_player");
            $this->gamestate->changeActivePlayer( $original_player );
            $this->gamestate->nextState("noWar");
        }
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
