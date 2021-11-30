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
   define("NO_ID", 999);
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
            "original_player" => 14,
            "potential_monument_tile_id" => 15
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

        // Insert starting points
        $sql = "INSERT INTO point (player) VALUES ";
        $values = array();
        foreach( $players as $player_id => $player )
        {
            $values[] = "('".$player_id."')";
        }
        $sql .= implode( $values, ',' );
        self::DbQuery( $sql );

        $all_tiles = array();
        $all_tiles = array_merge($all_tiles, array_fill(0, 57 - count($this->starting_temples), 'red'));
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
        foreach($this->starting_temples as $temple ){
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

        $sql = "INSERT INTO monument (id, color1, color2) VALUES ";
        $values = array();
        $values[] = "('0', 'black', 'green')";
        $values[] = "('1', 'black', 'blue')";
        $values[] = "('2', 'black', 'red')";
        $values[] = "('3', 'red', 'blue')";
        $values[] = "('4', 'green', 'red')";
        $values[] = "('5', 'blue', 'green')";
        $sql .= implode($values, ',');
        self::DbQuery($sql);

        /************ Start the game initialization *****/

        // Init global values with their initial values
        self::setGameStateInitialValue( 'current_action_count', 1 );
        self::setGameStateInitialValue( 'current_attacker', NO_ID );
        self::setGameStateInitialValue( 'current_defender', NO_ID );
        self::setGameStateInitialValue( 'current_war_state', WAR_NO_WAR );
        self::setGameStateInitialValue( 'original_player', NO_ID );
        self::setGameStateInitialValue( 'potential_monument_tile_id', NO_ID );
        
        // Init game statistics
        // (note: statistics used in this file must be defined in your stats.inc.php file)
        //self::initStat( 'table', 'table_teststat1', 0 );    // Init a table statistics
        //self::initStat( 'player', 'player_teststat1', 0 );  // Init a player statistics (for all players)

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
        $result['support'] = self::getObjectListFromDB( "select * from tile where state = 'support'");
        $result['leaders'] = self::getObjectListFromDB( "select * from leader");
        $result['monuments'] = self::getObjectListFromDB( "select * from monument");
        foreach($result['leaders'] as $leader){
            $result['players'][$leader['owner']]['shape'] = $leader['shape'];
        }
        $result['player_status'] = self::getPlayerStatus();
        $result['points'] = self::getObjectFromDB("select * from point where player = '".$current_player_id."'");
  
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
    function getGameProgression(){
        $remaining_tiles = self::getUniqueValueFromDB("select count(*) from tile where state = 'bag'");
        $player_count = self::getPlayersNumber();
        $starting_tiles = 11 + (6 * $player_count);
        $total_tiles = (57 + 36 + 30 + 30) - $starting_tiles;

        return intval((($total_tiles - $remaining_tiles) / $total_tiles) * 100);
    }


//////////////////////////////////////////////////////////////////////////////
//////////// Utility functions
////////////    

    /*
        In this space, you can put any utility methods useful for your game logic
    */

    // TODO: combine these messages to clean up replay area
    function score($color, $points, $player_id, $player_name, $source=false, $id=false){
       self::DbQuery("
            update
                point
            set
                ".$color." = ".$color." + '".$points."'
            where
                player = '".$player_id."'
            ");
        self::notifyAllPlayers(
            "playerScore",
            clienttranslate('${scorer_name} scored ${points} ${color}'),
            array(
                'player_id' => $player_id,
                'scorer_name' => $player_name,
                'color' => $color,
                'points' => $points,
                'source' => $source,
                'id' => $id
            )
        ); 
    }

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
            clienttranslate('Drawing tiles'),
            array(
                'tiles' => $new_tiles
            )
        );

        self::notifyAllPlayers(
            "drawTilesNotif",
            clienttranslate('${player_name} drew ${count} tiles'),
            array(
                'player_name' => $player_name,
                'count' => $count
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
                if(in_array($leader['id'], $used_leaders) === false){
                    $kingdom['leaders'][$leader['id']] = $leader;
                    $used_leaders[] = $leader['id'];
                    $x = $leader['posX'];
                    $y = $leader['posY'];
                    $kingdom['pos'][] = [$x, $y];
                    $potential_tiles = self::findNeighbors($x, $y, $board);
                    $potential_leaders = self::findNeighbors($x, $y, $leaders);

                    foreach($potential_tiles as $ptile){
                        if($board[$ptile]['kind'] == 'catastrophe'){
                            $potential_tiles = array_diff($potential_tiles, array($ptile));
                        }
                        if($board[$ptile]['isUnion'] === '1'){
                            $potential_tiles = array_diff($potential_tiles, array($ptile));
                        }
                    }
                    $to_test_tiles = array_unique(array_merge($potential_tiles, $to_test_tiles));
                    $to_test_leaders = array_unique(array_merge($potential_leaders, $to_test_leaders));

                    while(count($to_test_tiles) > 0){
                        $tile = $board[array_pop($to_test_tiles)];
                        if(in_array($tile['id'], $used_tiles) === false){
                            $kingdom['tiles'][$tile['id']] = $tile;
                            $used_tiles[] = $tile['id'];
                            $x = $tile['posX'];
                            $y = $tile['posY'];
                            $kingdom['pos'][] = [$x, $y];
                            $potential_tiles = self::findNeighbors($x, $y, $board);
                            $potential_leaders = self::findNeighbors($x, $y, $leaders);

                            foreach($potential_tiles as $ptile){
                                if($board[$ptile]['kind'] == 'catastrophe'){
                                    $potential_tiles = array_diff($potential_tiles, array($ptile));
                                }
                                if($board[$ptile]['isUnion'] === '1'){
                                    $potential_tiles = array_diff($potential_tiles, array($ptile));
                                }
                            }
                            $to_test_tiles = array_unique(array_merge($potential_tiles, $to_test_tiles));
                            $to_test_leaders = array_unique(array_merge($potential_leaders, $to_test_leaders));
                        }
                    }
                }
            }
            if(count($kingdom['pos']) > 0){
                $kingdoms[] = $kingdom;
            }
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
            if(in_array($above, $kingdom['pos'])){
                $neighbor_kingdoms[] = $i;
            }
            if(in_array($below, $kingdom['pos'])){
                $neighbor_kingdoms[] = $i;
            }
            if(in_array($left, $kingdom['pos'])){
                $neighbor_kingdoms[] = $i;
            }
            if(in_array($right, $kingdom['pos'])){
                $neighbor_kingdoms[] = $i;
            }
        }
        return array_unique($neighbor_kingdoms); 
    }

    function kingdomHasTwoAmulets($kingdom){
        $hasAmulet = false;
        foreach($kingdom['tiles'] as $tile){
            if($tile['hasAmulet']){
                if($hasAmulet === true){
                    return true;
                } else {
                    $hasAmulet = true;
                }
            }
        }
        return false;
    }

    function calculateBoardStrength($leader, $board){
        $neighbors = self::findNeighbors($leader['posX'], $leader['posY'], $board);
        $strength = 0;
        foreach($neighbors as $tile_id){
            if($board[$tile_id]['kind'] == 'red'){
                $strength++;
            }
        }
        return $strength;
    }

    function calculateKingdomStrength($leader, $kingdoms){
        $strength = 0;
        foreach($kingdoms as $kingdom){
            if(array_key_exists($leader['id'], $kingdom['leaders'])){
                foreach($kingdom['tiles'] as $tile){
                    if($tile['kind'] === $leader['kind']){
                        $strength++;
                    }
                }
            }
        }
        return $strength;
    }

    function getTileXY($tiles, $x, $y){
        foreach($tiles as $tile){
            if($tile['posX'] == $x && $tile['posY'] == $y){
                return $tile;
            }
        }
        return false;
    }

    function getMonumentSquare($tiles, $tile){
        $x = intval($tile['posX']);
        $y = intval($tile['posY']);

        $right = self::getTileXY($tiles, $x + 1, $y);
        $left = self::getTileXY($tiles, $x - 1, $y);
        $below = self::getTileXY($tiles, $x, $y + 1);
        $above = self::getTileXY($tiles, $x, $y - 1);
        $rightbelow = self::getTileXY($tiles, $x + 1, $y + 1);
        $leftbelow = self::getTileXY($tiles, $x - 1, $y + 1);
        $rightabove = self::getTileXY($tiles, $x + 1, $y - 1);
        $leftabove = self::getTileXY($tiles, $x - 1, $y - 1);

        if($right !== false && $rightbelow !== false && $below !== false){
            if($right['kind'] == $tile['kind'] && $below['kind'] == $tile['kind'] && $rightbelow['kind'] == $tile['kind']){
                return array($tile, $right, $rightbelow, $below);
            }
        }
        if($right !== false && $rightabove !== false && $above !== false){
            if($right['kind'] == $tile['kind'] && $above['kind'] == $tile['kind'] && $rightabove['kind'] == $tile['kind']){
                return array($tile, $right, $rightabove, $above);
            }
        }
        if($left !== false && $leftbelow !== false && $below !== false){
            if($left['kind'] == $tile['kind'] && $below['kind'] == $tile['kind'] && $leftbelow['kind'] == $tile['kind']){
                return array($tile, $left, $leftbelow, $below);
            }
        }
        if($left !== false && $leftabove !== false && $above !== false){
            if($left['kind'] == $tile['kind'] && $above['kind'] == $tile['kind'] && $leftabove['kind'] == $tile['kind']){
                return array($tile, $left, $leftabove, $above);
            }
        }
        return false;
    }


//////////////////////////////////////////////////////////////////////////////
//////////// Player actions
//////////// 
    function buildMonument( $monument_id ){
        self::checkAction('buildMonument');
        $player_id = self::getActivePlayerId();
        $player_name = self::getActivePlayerName();
        $potential_monument_tile_id = self::getGameStateValue("potential_monument_tile_id");

        $monument = self::getObjectFromDB("select * from monument where id = '".$monument_id."'");
        $tile = self::getObjectFromDB("select * from tile where id = '".$potential_monument_tile_id."'");
        $board = self::getCollectionFromDB("select * from tile where state = 'board'");

        if($monument['color1'] == $tile['kind'] or $monument['color2'] == $tile['kind']){
            $tiles = self::getMonumentSquare($board, $tile);
            $x = 99;
            $y = 99;
            $flip_ids = array();
            foreach($tiles as $flip){
                self::DbQuery("update tile set kind = 'flipped' where id = '".$flip['id']."'");
                $flip_ids[] = $flip['id'];
                if($x > $flip['posX']){
                    $x = $flip['posX'];
                }
                if($y > $flip['posY']){
                    $y = $flip['posY'];
                }
            }
            self::DbQuery("update monument set onBoard = '1', posX = '".$x."', posY = '".$y."' where id = '".$monument_id."'");
            self::notifyAllPlayers(
                    "placeMonument",
                    clienttranslate('${player_name} placed ${color1}/${color2} monument'),
                    array(
                        'player_name' => $player_name,
                        'flip_ids' => $flip_ids,
                        'color1' => $monument['color1'],
                        'color2' => $monument['color2'],
                        'monument_id' => $monument_id,
                        'pos_x' => $x,
                        'pos_y' => $y
                    )
                );

            $leaders = self::getCollectionFromDB("select * from leader where onBoard = '1'");
            $board = self::getCollectionFromDB("select * from tile where state = 'board'");
            foreach($leaders as $leader){
                $is_safe = false;
                foreach(self::findNeighbors($leader['posX'], $leader['posY'], $board) as $neighbor_id){
                    if($board[$neighbor_id]['kind'] == 'red'){
                        $is_safe = true;
                    }
                }
                if($is_safe == false){
                    self::DbQuery("
                        update
                            leader
                        set
                            onBoard = '0',
                            posX = NULL,
                            posY = NULL
                        where
                            id = '".$leader['id']."'
                        ");
                    self::notifyAllPlayers(
                        "leaderReturned",
                        clienttranslate('Building monument returned ${color} ${shape}'),
                        array(

                            'shape' => $leader['shape'],
                            'color' => $leader['kind'],
                            'leader' => $leader
                        )
                    );
                }
            }
            self::setGameStateValue("potential_monument_tile_id", NO_ID);
            $this->gamestate->nextState("buildMonument");
        } else {
            throw new feException("Error: Must select monument of correct color");
        }
    }

    function placeSupport( $support_ids ){
        self::checkAction('placeSupport');
        // note a pass has no support ids
        $player_id = self::getActivePlayerId();
        $player_name = self::getActivePlayerName();
        $hand = self::getCollectionFromDB("select * from tile where owner = '".$player_id."' and state = 'hand'");
        $attacker_id = self::getGameStateValue("current_attacker");
        $defender_id = self::getGameStateValue("current_defender");

        $board = self::getCollectionFromDB("select * from tile where state = 'board'");
        $leaders = self::getCollectionFromDB("select * from leader where onBoard = '1'");

        $kingdoms = self::findKingdoms( $board, $leaders );
        $war_color = $leaders[$attacker_id]['kind'];
        if($this->gamestate->state()['name'] == "supportRevolt"){
            $war_color = 'red';
        }

        // check if support ids are valid
        foreach($support_ids as $tile_id){
            if(array_key_exists($tile_id, $hand) == false){
               throw new feException("Error: Attempt to support with tiles not in hand.");
            }
            if($hand[$tile_id]['kind'] != $war_color){
                throw new feException("Error: Only $war_color may be played as support in this war.");
            }
        }

        $side = 'attacker';
        if($player_id == $leaders[$defender_id]['owner']){
            $side = 'defender';
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
        self::notifyAllPlayers(
                "placeSupport",
                clienttranslate('${player_name} placed ${number} support'),
                array(
                    'player_name' => $player_name,
                    'player_id' => $player_id,
                    'tile_ids' => $support_ids,
                    'number' => count($support_ids),
                    'side' => $side,
                    'kind' => $war_color
                )
            );
        $this->gamestate->nextState("placeSupport");
    }

    function discard( $discard_ids ){
        self::checkAction('discard');
        $player_id = self::getActivePlayerId();
        $player_name = self::getActivePlayerName();
        $hand = self::getCollectionFromDB("select * from tile where owner = '".$player_id."' and state = 'hand'");

        // check if discard ids are valid
        foreach($discard_ids as $tile_id){
            if(array_key_exists($tile_id, $hand) == false){
               throw new feException("Error: Attempt to discard tiles not in hand.");
            }
            if($hand[$tile_id]['kind'] == 'catastrophe'){
               throw new feException("Error: You cannot discard catastrophe tiles.");
           }
        }

        $tile_string = implode($discard_ids, ',');
        self::DbQuery("update tile set state = 'discard', owner = NULL where id in (".$tile_string.")");

        self::notifyPlayer(
            $player_id,
            "discard",
            clienttranslate('Discarding tiles'),
            array(
                'tile_ids' => $discard_ids
            )
        );

        self::notifyAllPlayers(
            "discardNotif",
            clienttranslate('${player_name} discarded ${count} tiles'),
            array(
                'player_name' => $player_name,
                'count' => count($discard_ids)
            )
        );

        // refill hand
        $this->drawTiles(count($discard_ids), $player_id);

        // move to next action
        $this->gamestate->nextState("nextAction");

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
        $new_tile = $hand[$tile_id];
        $new_tile['posX'] = $pos_x;
        $new_tile['posY'] = $pos_y;

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
                   if($tile['kind'] == 'flipped'){
                       throw new feException("Error: A catastrophe cannot be placed on a monument.");
                   }
                }
            }
        }

        if($kind != 'catastrophe'){
            $valid_blue = $kind != 'blue';
            foreach($this->rivers as $river_tile){
                if($pos_x == $river_tile['posX'] && $pos_y == $river_tile['posY']){
                    if($kind != 'blue'){
                        throw new feException("Error: Only blue may be placed on rivers.");
                    } else {
                        $valid_blue = true;
                    }
                }
            }

            if($valid_blue === false){
                throw new feException("Error: Blue may only be placed on rivers.");
            }
        }

        foreach($leaders as $leader){
            if($pos_x == $leader['posX'] && $pos_y == $leader['posY']){
                throw new feException("Error: No tile may be placed over a leader.");
            }
        }

        if($kind == 'catastrophe'){
            $existing_tile = self::getTileXY($board, $pos_x, $pos_y);
            $removed_leaders = array();
            if($existing_tile !== false){
                // notify players to remove tile
                if($existing_tile['kind'] == 'red'){
                    foreach(self::findNeighbors($pos_x, $pos_y, $leaders) as $nl_id){
                        $neighboring_leader = $leaders[$nl_id];
                        $safe = false;
                        foreach(self::findNeighbors($neighboring_leader['posX'], $neighboring_leader['posY'], $board) as $nt_id){
                            $neighbor_tile = $board[$nt_id];
                            if($neighbor_tile['kind'] == 'red' && $neighbor_tile['id'] != $existing_tile['id']){
                                $safe = true;
                            }
                        }
                        // remove this leader
                        if($safe === false){
                            $removed_leaders[] = $neighboring_leader;
                            self::DbQuery("
                                update
                                    leader
                                set
                                    onBoard = '0',
                                    posX = NULL,
                                    posY = NULL
                                where
                                    id = '".$neighboring_leader['id']."'
                                ");
                        }
                    }
                }
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
                self::notifyAllPlayers(
                    "catastrophe",
                    clienttranslate('${player_name} placed catastrophe removing ${count} leaders.'),
                    array(
                        'player_name' => $player_name,
                        'tile_id' => $existing_tile['id'],
                        'count' => count($removed_leaders),
                        'removed_leaders' => $removed_leaders
                    )
                );
            }
        }

        $kingdoms = self::findKingdoms( $board, $leaders );
        $neighbor_kingdoms = self::neighborKingdoms($pos_x, $pos_y, $kingdoms);

        if(count($neighbor_kingdoms) > 2 and $kind != 'catastrophe'){
            throw new feException("Error: A tile cannot join 3 kingdoms.");
        }

        $is_union = count($neighbor_kingdoms) == 2 and $kind != 'catastrophe';

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

            if(count($neighbor_kingdoms) == 1 && $kind != 'catastrophe'){
                $scoring_kingdom = $kingdoms[$neighbor_kingdoms[0]];
                foreach($scoring_kingdom['leaders'] as $scoring_leader){
                    $score_id = false;
                    if($scoring_leader['kind'] == $kind){
                        $score_id = $scoring_leader['id'];
                    } else if($scoring_leader['kind'] == 'black'){
                        $score_id = $scoring_leader['id'];
                        foreach($scoring_kingdom['leaders'] as $other_leader){
                            if($other_leader['kind'] == $kind){
                                $score_id = false;
                            }
                        }
                    }
                    if($score_id !== false){
                        self::score($kind, 1, $scoring_leader['owner'], $scoring_leader['shape'], 'leader', $score_id);
                    }
                }
            }

            $monument_tiles = self::getMonumentSquare($board, $new_tile);
            $monument_count = self::getUniqueValueFromDB("select count(*) from monument where onBoard = '0'");
            if($monument_tiles !== false && $monument_count > 0){
                self::setGameStateValue("potential_monument_tile_id", $new_tile['id']);
                $this->gamestate->nextState("safeMonument");
            } else {
                $this->gamestate->nextState("safeNoMonument");
            }
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
        foreach($this->rivers as $river_tile){
            if($pos_x == $river_tile['posX'] && $pos_y == $river_tile['posY']){
                throw new feException("Error: Leaders may not be placed on rivers.");
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
            if($above[0] == $tile['posX'] && $above[1] == $tile['posY'] && $tile['kind'] == 'red'){
                $valid = true;
            }
            if($below[0] == $tile['posX'] && $below[1] == $tile['posY'] && $tile['kind'] == 'red'){
                $valid = true;
            }
            if($left[0] == $tile['posX'] && $left[1] == $tile['posY'] && $tile['kind'] == 'red'){
                $valid = true;
            }
            if($right[0] == $tile['posX'] && $right[1] == $tile['posY'] && $tile['kind'] == 'red'){
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
                    self::setGameStateValue("current_war_state", WAR_ATTACKER_SUPPORT);
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

    function pickupLeader( $leader_id ){
        self::checkAction('pickupLeader');
        $player_id = self::getActivePlayerId();
        $player_name = self::getActivePlayerName();
        $leader = self::getObjectFromDB("select * from leader where id = '".$leader_id."'");

        if($player_id != $leader['owner']){
            throw new feException("Error: You can only return your own leaders.");
        }

        if($leader['onBoard'] != '1'){
            throw new feException("Error: You can only return leaders on the board.");
        }

        self::DbQuery("update leader set onBoard='0', posX = NULL, posY = NULL where id = '".$leader_id."'");
        self::notifyAllPlayers(
            "leaderReturned",
            clienttranslate('${player_name} picked up ${color} leader'),
            array(
                'player_name' => $player_name,
                'color' => $leader['kind'],
                'leader' => $leader
            )
        );
        $this->gamestate->nextState('nextAction');
    }

    function selectWarLeader( $leader_id ){
        self::checkAction('selectWarLeader');
        $player_id = self::getActivePlayerId();
        $player_name = self::getActivePlayerName();
        $board = self::getCollectionFromDB("select * from tile where state = 'board'");
        $leaders = self::getCollectionFromDB("select * from leader where onBoard = '1'");

        $kingdoms = self::findKingdoms( $board, $leaders );

        $union_tile = false;
        foreach($board as $tile){
            if($tile['isUnion'] === '1'){
                $union_tile = $tile;
            }
        }
        if($union_tile === false){
            throw new feException("Error: Game is in bad state (no union tile), reload.");
        }
        $warring_kingdoms = self::neighborKingdoms($union_tile['posX'], $union_tile['posY'], $kingdoms);

        if(count($warring_kingdoms) != 2){
            throw new feException("Error: Game is in bad state (no warring kingdoms), reload.");
        }

        $warring_leader_ids = array();
        $potential_war_leaders = array_merge($kingdoms[array_pop($warring_kingdoms)]['leaders'], $kingdoms[array_pop($warring_kingdoms)]['leaders']);
        foreach($potential_war_leaders as $pleader){
            foreach($potential_war_leaders as $oleader){
                if($oleader['kind'] == $pleader['kind'] && $oleader['id'] != $pleader['id']){
                    $warring_leader_ids[] = $oleader['id'];
                    $warring_leader_ids[] = $pleader['id'];
                }
            }
        }
        $warring_leader_ids = array_unique($warring_leader_ids);
        $valid_leader = false;

        $attacking_leader = false;
        foreach($warring_leader_ids as $wleader_id){
            if($leaders[$wleader_id]['owner'] == $player_id && $wleader_id == $leader_id){
                $valid_leader = true;
                $attacking_leader = $leaders[$wleader_id];
            }
        }
        if($valid_leader === false){
            throw new feException("You must select a leader in the kingdoms currently at war");
        }
        $defending_leader = false;
        foreach($potential_war_leaders as $dleader){
            if($dleader['kind'] == $attacking_leader['kind'] && $dleader['id'] != $attacking_leader['id']){
                $defending_leader = $dleader;
            }
        }
        self::setGameStateValue("current_attacker", $attacking_leader['id']);
        self::setGameStateValue("current_defender", $defending_leader['id']);
        self::setGameStateValue("current_war_state", WAR_ATTACKER_SUPPORT);
        self::notifyAllPlayers(
            "leaderSelected",
            clienttranslate('${player_name} selected ${color} for war'),
            array(
                'player_name' => $player_name,
                'color' => $attacking_leader['kind']
            )
        );
        $this->gamestate->nextState('leaderSelected');
    }

    function pickAmulet($x, $y){
        self::checkAction('pickAmulet');
        $player_id = self::getActivePlayerId();
        $player_name = self::getActivePlayerName();
        $board = self::getCollectionFromDB("select * from tile where state = 'board'");
        $leaders = self::getCollectionFromDB("select * from leader where onBoard = '1'");
        $kingdoms = self::findKingdoms($board, $leaders);
        foreach($kingdoms as $kingdom){
            $green_leader_id = false;
            foreach($kingdom['leaders'] as $leader){
                if($leader['kind'] == 'green' && $leader['owner'] == $player_id){
                    $green_leader_id = $leader['id'];
                }
            }
            if($green_leader_id !== false && self::kingdomHasTwoAmulets($kingdom)){
                $has_mandatory = false;
                foreach($kingdom['tiles'] as $tile){
                    foreach($this->outerTemples as $ot){
                        if($tile['posX'] === $ot['posX'] && $tile['posY'] === $ot['posY'] && $tile['hasAmulet']){
                            $has_mandatory = true;
                        }
                    }
                }
                foreach($kingdom['tiles'] as $tile){
                    $is_mandatory = false;
                    foreach($this->outerTemples as $ot){
                        if($tile['posX'] === $ot['posX'] && $tile['posY'] === $ot['posY'] && $tile['hasAmulet']){
                            $is_mandatory = true;
                        }
                    }

                    if( $tile['posX'] === $x && $tile['posY'] === $y &&
                        $tile['hasAmulet'] &&
                        ($has_mandatory === $is_mandatory)){
                        self::DbQuery("
                            update
                                point
                            set
                                amulet = amulet + 1
                            where
                                player = '".$player_id."'
                            ");
                        $tile_id = $tile['id'];
                        self::DbQuery("
                            update
                                tile
                            set
                                hasAmulet = '0'
                            where
                                id = '".$tile_id."'
                            ");
                        self::notifyAllPlayers(
                            "pickedAmulet",
                            clienttranslate('${scorer_name} scored 1 ${color}'),
                            array(
                                'scorer_name' => $player_name,
                                'player_id' => $player_id,
                                'color' => 'amulet',
                                'tile_id' => $tile_id
                            )
                        );
                        $this->gamestate->nextState('pickAmulet');
                        return;
                    }
                }
            }
        }
        throw new feException("Error: Invalid treasure");
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

    function getPlayerStatus(){
        $player_points = array();
        $leaders = self::getObjectListFromDB( "select * from leader");
        foreach($leaders as $leader){
            $player_points[$leader['owner']]['shape'] = $leader['shape'];
        }
        $catastrophe_count = self::getCollectionFromDB("select owner, count(*) as c from tile where state = 'hand' and kind = 'catastrophe' group by owner");
        foreach($catastrophe_count as $owner=>$count){
            $player_points[$owner]['catastrophe_count'] = $count['c'];
        }
        $hand_count = self::getCollectionFromDB("select owner, count(*) as c from tile where state = 'hand' and kind != 'catastrophe' group by owner");
        foreach($hand_count as $owner=>$count){
            $player_points[$owner]['hand_count'] = $count['c'];
        }
        // add default values
        foreach($player_points as $player_id=>$status){
            if(array_key_exists("catastrophe_count", $player_points[$player_id]) === false){
                $player_points[$player_id]['catastrophe_count'] = 0;
            }
            if(array_key_exists("hand_count", $player_points[$player_id]) === false){
                $player_points[$player_id]['hand_count'] = 0;
            }
        }
        return $player_points;
    }

    function arg_playerTurn(){
        $board = self::getCollectionFromDB("select * from tile where state = 'board'");
        $leaders = self::getCollectionFromDB("select * from leader where onBoard = '1'");
        $kingdoms = self::findKingdoms($board, $leaders);
        $small_kingdoms = array();
        foreach($kingdoms as $kingdom){
            $small_kingdoms[] = $kingdom['pos'];
        }

        return array(
            'action_number' => self::getGameStateValue("current_action_count"),
            'kingdoms' => $small_kingdoms,
            'player_status' => self::getPlayerStatus()
        );
    }

    function arg_showKingdoms(){
        $board = self::getCollectionFromDB("select * from tile where state = 'board'");
        $leaders = self::getCollectionFromDB("select * from leader where onBoard = '1'");
        $kingdoms = self::findKingdoms($board, $leaders);
        $small_kingdoms = array();
        foreach($kingdoms as $kingdom){
            $small_kingdoms[] = $kingdom['pos'];
        }

        return array(
            'kingdoms' => $small_kingdoms,
            'player_status' => self::getPlayerStatus()
        );
    }

    function arg_showWar(){
        $board = self::getCollectionFromDB("select * from tile where state = 'board'");
        $leaders = self::getCollectionFromDB("select * from leader where onBoard = '1'");
        $kingdoms = self::findKingdoms($board, $leaders);
        $small_kingdoms = array();
        foreach($kingdoms as $kingdom){
            $small_kingdoms[] = $kingdom['pos'];
        }

        $attacker = $leaders[self::getGameStateValue("current_attacker")];
        $defender = $leaders[self::getGameStateValue("current_defender")];
        $attacker_board_strength = 0;
        $defender_board_strength = 0;
        foreach($kingdoms as $kingdom){
            if(array_key_exists($attacker['id'], $kingdom['leaders'])){
                foreach($kingdom['tiles'] as $tile){
                    if($tile['kind'] == $attacker['kind']){
                        $attacker_board_strength++;
                    }
                }
            }
            if(array_key_exists($defender['id'], $kingdom['leaders'])){
                foreach($kingdom['tiles'] as $tile){
                    if($tile['kind'] == $defender['kind']){
                        $defender_board_strength++;
                    }
                }
            }
        }
        $attacker_hand_strength = self::getUniqueValueFromDB("select count(*) from tile where owner = '".$attacker['owner']."' and state='support'");

        return array(
            'kingdoms' => $small_kingdoms,
            'player_status' => self::getPlayerStatus(),
            'attacker' => $attacker,
            'defender' => $defender,
            'attacker_board_strength' => $attacker_board_strength,
            'defender_board_strength' => $defender_board_strength,
            'attacker_hand_strength' => $attacker_hand_strength
        );

    }

    function arg_showRevolt(){
        $board = self::getCollectionFromDB("select * from tile where state = 'board'");
        $leaders = self::getCollectionFromDB("select * from leader where onBoard = '1'");
        $kingdoms = self::findKingdoms($board, $leaders);
        $small_kingdoms = array();
        foreach($kingdoms as $kingdom){
            $small_kingdoms[] = $kingdom['pos'];
        }

        $attacker = $leaders[self::getGameStateValue("current_attacker")];
        $defender = $leaders[self::getGameStateValue("current_defender")];
        $attacker_board_strength = 0;
        $defender_board_strength = 0;
        foreach($kingdoms as $kingdom){
            if(array_key_exists($attacker['id'], $kingdom['leaders'])){
                foreach(self::findNeighbors($attacker['posX'], $attacker['posY'], $kingdom['tiles']) as $tile_id){
                    if($board[$tile_id]['kind'] == 'red'){
                        $attacker_board_strength++;
                    }
                }
            }
            if(array_key_exists($defender['id'], $kingdom['leaders'])){
                foreach(self::findNeighbors($defender['posX'], $defender['posY'], $kingdom['tiles']) as $tile_id){
                    if($board[$tile_id]['kind'] == 'red'){
                        $defender_board_strength++;
                    }
                }
            }
        }
        $attacker_hand_strength = self::getUniqueValueFromDB("select count(*) from tile where owner = '".$attacker['owner']."' and state='support'");

        return array(
            'kingdoms' => $small_kingdoms,
            'player_status' => self::getPlayerStatus(),
            'attacker' => $attacker,
            'defender' => $defender,
            'attacker_board_strength' => $attacker_board_strength,
            'defender_board_strength' => $defender_board_strength,
            'attacker_hand_strength' => $attacker_hand_strength
        );

    }

//////////////////////////////////////////////////////////////////////////////
//////////// Game state actions
////////////


    function stIncrementAction(){
        $original_player = self::getGameStateValue("original_player");
        if($original_player != NO_ID){
            $this->gamestate->changeActivePlayer( $original_player );
        }
        $player_id = self::getActivePlayerId();
        $player_name = self::getActivePlayerName();
        if(self::getGameStateValue("current_action_count") == 1){
            self::setGameStateValue("current_action_count", 2);
            $this->gamestate->nextState("secondAction");
        } else {
            // if second
            $board = self::getCollectionFromDB("select * from tile where state = 'board'");
            $leaders = self::getCollectionFromDB("select * from leader where onBoard = '1'");
            $kingdoms = self::findKingdoms($board, $leaders);
            $monuments = self::getCollectionFromDB("select * from monument where onBoard = '1'");

            // pickup amulets
            foreach($kingdoms as $kingdom){
                $green_leader_id = false;
                foreach($kingdom['leaders'] as $leader){
                    if($leader['kind'] == 'green'){
                        $green_leader_id = $leader['id'];
                    }
                }
                if($green_leader_id !== false && self::kingdomHasTwoAmulets($kingdom)){
                    self::setGameStateValue("original_player", $player_id);
                    $this->gamestate->changeActivePlayer( $kingdom['leaders'][$green_leader_id]['owner'] );
                    $this->gamestate->nextState("pickAmulet");
                    return;
                }

                // award monument points
                foreach($monuments as $monument){
                    $pos = [$monument['posX'], $monument['posY']];
                    if(in_array($pos, $kingdom['pos'])){
                        foreach($kingdom['leaders'] as $leader){
                            if($leader['owner'] == $player_id && $leader['kind'] == $monument['color1']){
                                self::score($monument['color1'], 1, $player_id, $player_name, 'monument', $monument['id']);
                            }
                            if($leader['owner'] == $player_id && $leader['kind'] == $monument['color2']){
                                self::score($monument['color2'], 1, $player_id, $player_name, 'monument', $monument['id']);
                            }
                        }
                    }
                }
            }

            // check game-end
            $remaining_amulets = self::getUniqueValueFromDB("select count(*) from tile where hasAmulet = '1'");
            if($remaining_amulets <= 2){
                self::notifyAllPlayers(
                    "gameEnding",
                    clienttranslate('Only ${remaining_amulets} remain, game is over.'),
                    array(
                        'remaining_amulets' => $remaining_amulets
                    )
                );
                $this->gamestate->nextState("endGame");
                return;
            }

            // refill hand
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
            // move to next player
            $this->activeNextPlayer();
            self::setGameStateValue("original_player", NO_ID);
            self::setGameStateValue("current_action_count", 1);
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
            $attacking_player_id = $leaders[$attacker_id]['owner'];
            $defending_player_id = $leaders[$defender_id]['owner'];

            $attacker_support = self::getUniqueValueFromDB("
                select count(*) from tile where owner = '".$attacking_player_id."' and state = 'support' and kind = 'red'
                ");
            $defender_support = self::getUniqueValueFromDB("
                select count(*) from tile where owner = '".$defending_player_id."' and state = 'support' and kind = 'red'
                ");

            $attacker_board_strength = self::calculateBoardStrength($leaders[$attacker_id], $board);
            $defender_board_strength = self::calculateBoardStrength($leaders[$defender_id], $board);

            $attacker_strength = intval($attacker_support) + $attacker_board_strength;
            $defender_strength = intval($defender_support) + $defender_board_strength;

            $winning_side = 'defender';
            $winner = $defender_id;
            $loser = $attacker_id;
            $winner_strength = $defender_strength;
            $loser_strength = $attacker_strength;
            $winning_player_id = $defending_player_id;
            if($attacker_strength > $defender_strength){
                $winning_side = 'attacker';
                $winner = $attacker_id;
                $loser = $defender_id;
                $winner_strength = $attacker_strength;
                $loser_strength = $defender_strength;
                $winning_player_id = $attacking_player_id;
            }

            self::DbQuery("update leader set posX = NULL, posY = NULL, onBoard = '0' where id = '".$loser."'");
            $winner_name = self::getPlayerNameById($leaders[$winner]['owner']);
            $loser_name = self::getPlayerNameById($leaders[$loser]['owner']);

            self::notifyAllPlayers(
                "revoltConcluded",
                clienttranslate('${winner}(${winner_strength}) removed ${loser}(${loser_strength}) in a revolt'),
                array(
                    'winner' => $winner_name,
                    'loser' => $loser_name,
                    'winner_strength' => $winner_strength,
                    'loser_strength' => $loser_strength,
                    'loser_id' => $loser,
                    'losing_player_id' => $leaders[$loser]['owner'],
                    'loser_shape' => $leaders[$loser]['shape'],
                    'kind' => $leaders[$loser]['kind'],
                    'winning_side' => $winning_side
                )
            );
            self::score('amulet', 1, $leaders[$winner]['owner'], $leaders[$winner]['shape']);

            self::DbQuery("
                update tile set owner = NULL, state = 'discard' where state = 'support'
                ");

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

        if($war_state == WAR_ATTACKER_SUPPORT){
            self::setGameStateValue("current_war_state", WAR_DEFENDER_SUPPORT);
            $this->gamestate->changeActivePlayer( $leaders[$defender_id]['owner'] );
            $this->gamestate->nextState("placeSupport");
            return;
        }

        $kingdoms = self::findKingdoms( $board, $leaders );
        $union_tile = false;
        foreach($board as $tile){
            if($tile['isUnion'] === '1'){
                $union_tile = $tile;
            }
        }
        if($union_tile === false){
            throw new feException("Error: Game is in bad state (no union tile), reload.");
        }
        $warring_kingdoms = self::neighborKingdoms($union_tile['posX'], $union_tile['posY'], $kingdoms);

        if(count($warring_kingdoms) > 2){
            throw new feException("Error: A war may only be started between two kingdoms.");
        }

        $warring_leader_ids = array();
        if(count($warring_kingdoms) < 2){
            self::DbQuery("update tile set isUnion = '0' where isUnion = '1'");

            self::notifyAllPlayers(
                "allWarsEnded",
                clienttranslate('All wars concluded'),
                array(
                    'tile_id' => $union_tile['id'],
                    'pos_x' => $union_tile['posX'],
                    'pos_y' => $union_tile['posY'],
                    'tile_color' => $union_tile['kind']
                )
            );

            self::setGameStateValue("current_war_state", WAR_NO_WAR);
            self::setGameStateValue("current_attacker", NO_ID);
            self::setGameStateValue("current_defender", NO_ID);
            // next action
            $original_player = self::getGameStateValue("original_player");
            $this->gamestate->changeActivePlayer( $original_player );
            $monument_count = self::getUniqueValueFromDB("select count(*) from monument where onBoard = '0'");
            if(self::getMonumentSquare($board, $union_tile) && $monument_count > 0){
                self::setGameStateValue("potential_monument_tile_id", $union_tile['id']);
                $this->gamestate->nextState("warMonument");
            } else {
                $this->gamestate->nextState("noWar");
            }
            return;
        }
        $potential_war_leaders = array_merge($kingdoms[array_pop($warring_kingdoms)]['leaders'], $kingdoms[array_pop($warring_kingdoms)]['leaders']);
        foreach($potential_war_leaders as $pleader){
            foreach($potential_war_leaders as $oleader){
                if($oleader['kind'] == $pleader['kind'] && $oleader['id'] != $pleader['id']){
                    $warring_leader_ids[] = $oleader['id'];
                    $warring_leader_ids[] = $pleader['id'];
                }
            }
        }
        $warring_leader_ids = array_unique($warring_leader_ids);
        $player_has_leader = false;
        $player_has_multi = false;
        $attacking_leader = false;
        foreach($warring_leader_ids as $wleader_id){
            if($leaders[$wleader_id]['owner'] == $player_id){
                if($player_has_leader){
                    $player_has_multi = true;
                } else {
                    $player_has_leader = true;
                    $attacking_leader = $leaders[$wleader_id];
                }
            }
        }

        if(count($warring_leader_ids) < 2){
            // flip union tile
            self::DbQuery("update tile set isUnion = '0' where isUnion = '1'");

            self::notifyAllPlayers(
                "allWarsEnded",
                clienttranslate('All wars concluded'),
                array(
                    'tile_id' => $union_tile['id'],
                    'pos_x' => $union_tile['posX'],
                    'pos_y' => $union_tile['posY'],
                    'tile_color' => $union_tile['kind']
                )
            );


            self::setGameStateValue("current_war_state", WAR_NO_WAR);
            self::setGameStateValue("current_attacker", NO_ID);
            self::setGameStateValue("current_defender", NO_ID);
            // next action
            $original_player = self::getGameStateValue("original_player");
            $this->gamestate->changeActivePlayer( $original_player );
            $monument_count = self::getUniqueValueFromDB("select count(*) from monument where onBoard = '0'");
            if(self::getMonumentSquare($board, $union_tile) && $monument_count > 0){
                self::setGameStateValue("potential_monument_tile_id", $union_tile['id']);
                $this->gamestate->nextState("warMonument");
            } else {
                $this->gamestate->nextState("noWar");
            }
            return;
        }

        if($war_state == WAR_DEFENDER_SUPPORT){
            $attacking_player_id = $leaders[$attacker_id]['owner'];
            $defending_player_id = $leaders[$defender_id]['owner'];
            $war_color = $leaders[$attacker_id]['kind'];

            $attacker_support = self::getUniqueValueFromDB("
                select count(*) from tile where owner = '".$attacking_player_id."' and state = 'support' and kind = '".$war_color."'
                ");
            $defender_support = self::getUniqueValueFromDB("
                select count(*) from tile where owner = '".$defending_player_id."' and state = 'support' and kind = '".$war_color."'
                ");

            $attacker_board_strength = self::calculateKingdomStrength($leaders[$attacker_id], $kingdoms);
            $defender_board_strength = self::calculateKingdomStrength($leaders[$defender_id], $kingdoms);

            $attacker_strength = intval($attacker_support) + $attacker_board_strength;
            $defender_strength = intval($defender_support) + $defender_board_strength;

            $winner = $defender_id;
            $loser = $attacker_id;
            $winner_strength = $defender_strength;
            $loser_strength = $attacker_strength;
            $winning_player_id = $defending_player_id;
            $winning_side = 'defender';
            if($attacker_strength > $defender_strength){
                $winner = $attacker_id;
                $loser = $defender_id;
                $winner_strength = $attacker_strength;
                $loser_strength = $defender_strength;
                $winning_player_id = $attacking_player_id;
                $winning_side = 'attacker';
            }

            $tiles_to_remove = array();
            foreach($kingdoms as $kingdom){
                if(array_key_exists($loser, $kingdom['leaders'])){
                    foreach($kingdom['tiles'] as $tile){
                        if($tile['kind'] === $leaders[$loser]['kind'] && $tile['hasAmulet'] === '0'){
                            $supported_leaders = array();
                            if($tile['kind'] == 'red'){
                                $supported_leaders = self::findNeighbors($tile['posX'], $tile['posY'], $kingdom['leaders']);
                            }
                            if(count($supported_leaders) == 0){
                                $tiles_to_remove[] = $tile['id'];
                            } else if(count($supported_leaders) == 1){
                                if($supported_leaders[0] == $loser){
                                    $tiles_to_remove[] = $tile['id'];
                                }
                            }
                        }
                    }
                }
            }

            self::DbQuery("update leader set posX = NULL, posY = NULL, onBoard = '0' where id = '".$loser."'");

            if(count($tiles_to_remove) > 0){
                $tile_string = implode($tiles_to_remove, ',');
                self::DbQuery("update tile set posX = NULL, posY = NULL, state = 'discard', isUnion = '0' where id in (".$tile_string.")");
            }

            self::notifyAllPlayers(
                "warConcluded",
                clienttranslate('${winner}(${winner_strength}) removed ${loser_shape}(${loser_strength}) and ${tiles_removed_count} tiles in war'),
                array(
                    'winner' => $leaders[$winner]['shape'],
                    'loser_shape' => $leaders[$loser]['shape'],
                    'winner_strength' => $winner_strength,
                    'loser_strength' => $loser_strength,
                    'loser_id' => $loser,
                    'losing_player_id' => $leaders[$loser]['owner'],
                    'kind' => $leaders[$loser]['kind'],
                    'tiles_removed_count' => count($tiles_to_remove),
                    'tiles_removed' => $tiles_to_remove,
                    'winning_side' => $winning_side
                )
            );

            $points = count($tiles_to_remove) + 1;
            self::score($war_color, $points, $winning_player_id, $leaders[$winner]['shape']);

            // discard support
            self::DbQuery("
                update tile set owner = NULL, state = 'discard' where state = 'support'
                ");

            self::setGameStateValue("current_war_state", WAR_NO_WAR);
            self::setGameStateValue("current_attacker", NO_ID);
            self::setGameStateValue("current_defender", NO_ID);
            $this->gamestate->changeActivePlayer( $leaders[$attacker_id]['owner'] );
            $this->gamestate->nextState("nextWar");
            return;
        } else if($player_has_multi){
            $this->gamestate->nextState("pickLeader");
        } else if($player_has_multi == false and $player_has_leader){
            $defending_leader = false;
            foreach($potential_war_leaders as $dleader){
                if($dleader['kind'] == $attacking_leader['kind'] && $dleader['id'] !== $attacking_leader['id']){
                    $defending_leader = $dleader;
                }
            }
            self::setGameStateValue("current_attacker", $attacking_leader['id']);
            self::setGameStateValue("current_defender", $defending_leader['id']);
            self::setGameStateValue("current_war_state", WAR_ATTACKER_SUPPORT);
            $this->gamestate->nextState("placeSupport");
        } else if($player_has_leader == false && count($warring_leader_ids) >= 2){
            $this->activeNextPlayer();
            $this->gamestate->nextState("nextWar");
        }
    }

    function addToLowest($points){
        $colors = array('red', 'blue', 'green', 'black');
        $lowest = 999;
        $lowest_color = 'red';
        foreach($colors as $color){
            if($points[$color] < $lowest){
                $lowest = $points[$color];
                $lowest_color = $color;
            }
        }
        $points[$lowest_color]++;
        return $points;
    }

    function getLowest($points){
        $colors = array('red', 'blue', 'green', 'black');
        $lowest = 99;
        $lowest_color = 'red';
        foreach($colors as $color){
            if($points[$color] < $lowest){
                $lowest = $points[$color];
                $lowest_color = $color;
            }
        }
        return $lowest_color;
    }

    function stFinalScoring(){
        $points = self::getCollectionFromDB("select * from point");
        self::notifyAllPlayers(
            "finalScores",
            clienttranslate("Final Scores..."),
            array('points' => $points)
        );
        $highest_score = -1;
        foreach($points as $player=>$point){
            while($point['amulet'] > 0){
                $point['amulet']--;
                $point = self::addToLowest($point);
            }
            $low_color = self::getLowest($point);
            $score = $point[$low_color];
            $points[$player][$low_color] = 999;
            $points[$player]['lowest'] = $score;
            self::DbQuery("update player set player_score = '".$score."' where player_id = '".$player."'");
            if($score > $highest_score){
                $highest_score = $score;
            }
        }

        $winner = false;
        $i = 0;
        while($winner === false && $i < 4){
            $num_tie = 0;
            foreach($points as $player=>$point){
                if($highest_score == $point['lowest']){
                    $num_tie++;
                    $winner = $player;
                }
            }
            if($num_tie > 1){
                $winner = false;
                $high_score = -1;
                foreach($points as $player=>$point){
                    $low_color = self::getLowest($point);
                    $score = $point[$low_color];
                    $points[$player][$low_color] = 999;
                    $points[$player]['lowest'] = $score;
                    if($score > $highest_score){
                        $highest_score = $score;
                    }
                }
            } else if($i > 0){
                self::DbQuery("update player set player_score_aux = '".$high_score."' where player_id = '".$winner."'");
            }
            $i++;
        }

        $this->gamestate->nextState("endGame"); 
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
                case 'warLeader':
                    $board = self::getCollectionFromDB("select * from tile where state = 'board'");
                    $leaders = self::getCollectionFromDB("select * from leader where onBoard = '1'");
                    $kingdoms = self::findKingdoms( $board, $leaders );
                    $union_tile = false;
                    foreach($board as $tile){
                        if($tile['isUnion'] === '1'){
                            $union_tile = $tile;
                        }
                    }

                    $warring_kingdoms = self::neighborKingdoms($union_tile['posX'], $union_tile['posY'], $kingdoms);
                    $potential_war_leaders = array_merge($kingdoms[array_pop($warring_kingdoms)]['leaders'], $kingdoms[array_pop($warring_kingdoms)]['leaders']);
                    $attacking_color = false;
                    foreach($potential_war_leaders as $pleader){
                        foreach($potential_war_leaders as $oleader){
                            if($oleader['kind'] == $pleader['kind'] && $oleader['id'] != $pleader['id']){
                                if($oleader['owner'] == $active_player){
                                    self::setGameStateValue("current_attacker", $oleader['id']);
                                    self::setGameStateValue("current_defender", $pleader['id']);
                                    $attacking_color = $oleader['kind'];
                                }
                                if($pleader['owner'] == $active_player){
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
                        clienttranslate('${player_name} selected ${color} for war'),
                        array(
                            'player_name' => 'ZombiePlayer',
                            'color' => $attacking_color
                        )
                    );
                    $this->gamestate->nextState( "zombiePass" );
                    break;
                case 'pickAmulet':
                    $board = self::getCollectionFromDB("select * from tile where state = 'board'");
                    $leaders = self::getCollectionFromDB("select * from leader where onBoard = '1'");
                    $kingdoms = self::findKingdoms($board, $leaders);
                    foreach($kingdoms as $kingdom){
                        $green_leader_id = false;
                        foreach($kingdom['leaders'] as $leader){
                            if($leader['kind'] == 'green' && $leader['owner'] == $active_player){
                                $green_leader_id = $leader['id'];
                            }
                        }
                        if($green_leader_id !== false && self::kingdomHasTwoAmulets($kingdom)){
                            $has_mandatory = false;
                            foreach($kingdom['tiles'] as $tile){
                                foreach($this->outerTemples as $ot){
                                    if($tile['posX'] === $ot['posX'] && $tile['posY'] === $ot['posY'] && $tile['hasAmulet']){
                                        $has_mandatory = true;
                                    }
                                }
                            }
                            foreach($kingdom['tiles'] as $tile){
                                if($has_mandatory === false && $tile['hasAmulet']){
                                   self::DbQuery("
                                        update
                                            point
                                        set
                                            amulet = amulet + 1
                                        where
                                            player = '".$active_player."'
                                        ");
                                    $tile_id = $tile['id'];
                                    self::DbQuery("
                                        update
                                            tile
                                        set
                                            hasAmulet = '0'
                                        where
                                            id = '".$tile_id."'
                                        ");
                                    self::notifyAllPlayers(
                                        "pickedAmulet",
                                        clienttranslate('${scorer_name} scored 1 ${color}'),
                                        array(
                                            'scorer_name' => 'ZombiePlayer',
                                            'player_id' => $active_player,
                                            'color' => 'amulet',
                                            'tile_id' => $tile['id']
                                        )
                                    ); 
                                    $this->gamestate->nextState( "zombiePass" );
                                    break;
                                }
                                foreach($this->outerTemples as $ot){
                                    if($tile['posX'] === $ot['posX'] && $tile['posY'] === $ot['posY'] && $tile['hasAmulet']){
                                        self::DbQuery("
                                            update
                                                point
                                            set
                                                amulet = amulet + 1
                                            where
                                                player = '".$active_player."'
                                            ");
                                        $tile_id = $tile['id'];
                                        self::DbQuery("
                                            update
                                                tile
                                            set
                                                hasAmulet = '0'
                                            where
                                                id = '".$tile_id."'
                                            ");
                                        self::notifyAllPlayers(
                                            "pickedAmulet",
                                            clienttranslate('${scorer_name} scored 1 ${color}'),
                                            array(
                                                'scorer_name' => 'ZombiePlayer',
                                                'player_id' => $active_player,
                                                'color' => 'amulet',
                                                'tile_id' => $tile['id']
                                            )
                                        ); 
                                        $this->gamestate->nextState( "zombiePass" );
                                        break;
                                    }
                                }
                            }
                        }
                    }
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
