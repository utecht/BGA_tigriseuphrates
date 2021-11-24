<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * TigrisEuphrates implementation : © Joseph Utecht <joseph@utecht.co>
 *
 * This code has been produced on the BGA studio platform for use on https://boardgamearena.com.
 * See http://en.doc.boardgamearena.com/Studio for more information.
 * -----
 * 
 * tigriseuphrates.action.php
 *
 */
  
  
  class action_tigriseuphrates extends APP_GameAction
  { 
    // Constructor: please do not modify
   	public function __default()
  	{
  	    if( self::isArg( 'notifwindow') )
  	    {
            $this->view = "common_notifwindow";
  	        $this->viewArgs['table'] = self::getArg( "table", AT_posint, true );
  	    }
  	    else
  	    {
            $this->view = "tigriseuphrates_tigriseuphrates";
            self::trace( "Complete reinitialization of board game" );
      }
  	} 
  	

    public function placeTile(){
        self::setAjaxMode();     

        $tile_id = self::getArg( "tile_id", AT_posint, true );
        $pos_x = self::getArg( "pos_x", AT_posint, true );
        $pos_y = self::getArg( "pos_y", AT_posint, true );

        $this->game->placeTile( $tile_id, $pos_x, $pos_y );

        self::ajaxResponse( );
    }

    public function placeLeader(){
        self::setAjaxMode();     

        $leader_id = self::getArg( "leader_id", AT_posint, true );
        $pos_x = self::getArg( "pos_x", AT_posint, true );
        $pos_y = self::getArg( "pos_y", AT_posint, true );

        $this->game->placeLeader( $leader_id, $pos_x, $pos_y );

        self::ajaxResponse( );
    }
    
    public function placeMonument(){
        self::setAjaxMode();     

        $monument_id = self::getArg( "monument_id", AT_posint, true );
        $pos_x = self::getArg( "pos_x", AT_posint, true );
        $pos_y = self::getArg( "pos_y", AT_posint, true );

        $this->game->placeMonument( $monument_id, $pos_x, $pos_y );

        self::ajaxResponse( );
    }

    public function placeSupport(){
        self::setAjaxMode();     

        $support_ids = self::getArg( "support_ids", AT_numberlist, true );

        $this->game->placeSupport( $support_ids );

        self::ajaxResponse( );
    }

    public function selectWarLeader(){
        self::setAjaxMode();     

        $leader_id = self::getArg( "leader_id", AT_posint, true );

        $this->game->selectWarLeader( $leader_id );

        self::ajaxResponse( );
    }

    public function pass(){
        self::setAjaxMode();     

        $this->game->pass();

        self::ajaxResponse();
    }

  }
  

