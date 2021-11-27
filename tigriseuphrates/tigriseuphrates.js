/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * TigrisEuphrates implementation : © Joseph Utecht <joseph@utecht.co>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * tigriseuphrates.js
 *
 * TigrisEuphrates user interface script
 * 
 * In this file, you are describing the logic of your user interface, in Javascript language.
 *
 */

define([
    "dojo","dojo/_base/declare", "dojo/dom-construct",
    "ebg/core/gamegui",
    "ebg/counter"
],
function (dojo, declare) {
    return declare("bgagame.tigriseuphrates", ebg.core.gamegui, {
        constructor: function(){
            console.log('tigriseuphrates constructor');
              
            // Here, you can init the global variables of your user interface
            // Example:
            // this.myGlobalValue = 0;

        },
        
        /*
            setup:
            
            This method must set up the game user interface according to current game situation specified
            in parameters.
            
            The method is called each time the game interface is displayed to a player, ie:
            _ when the game starts
            _ when a player refreshes the game page (F5)
            
            "gamedatas" argument contains all datas retrieved by your "getAllDatas" PHP method.
        */
        
        setup: function( gamedatas )
        {
            console.log( "Starting game setup" );
            
            // Setting up player boards
            for( var player_id in gamedatas.players )
            {
                var player = gamedatas.players[player_id];
                         
                // TODO: Setting up players boards if needed
            }
            
            // TODO: Set up your game interface here, according to "gamedatas"
            for(var tile of gamedatas.board){
                if(tile.isUnion == '1'){
                    this.addTokenOnBoard(tile.posX, tile.posY, 'union', tile.id);
                } else {
                    this.addTokenOnBoard(tile.posX, tile.posY, tile.kind, tile.id);
                }
            }

            for(var leader of gamedatas.leaders){
                if(leader.onBoard == '1'){
                    this.addLeaderOnBoard(leader.posX, leader.posY, leader.shape, leader.kind, leader.id);
                } else if(leader.owner == gamedatas.player){
                    dojo.place( this.format_block( 'jstpl_leader_hand', {
                        color: leader.kind,
                        id: leader.id,
                        shape: leader.shape
                    }), 'hand' );
                }
            }

            for(var tile of gamedatas.hand){
                dojo.place( this.format_block( 'jstpl_hand', {
                    color: tile.kind,
                    id: tile.id
                }), 'hand' );
            }

            for(var tile of gamedatas.support){
                dojo.place( this.format_block( 'jstpl_hand', {
                    color: tile.kind,
                    id: tile.id
                }), 'support' );
            }

            dojo.query('.space').connect('onclick', this, 'onSpaceClick');
            dojo.query('#hand .tile').connect('onclick', this, 'onHandClick');
            dojo.query('#hand .leader').connect('onclick', this, 'onHandLeaderClick');
            
 
            // Setup game notifications to handle (see "setupNotifications" method below)
            this.setupNotifications();

            console.log( "Ending game setup" );
        },
       

        ///////////////////////////////////////////////////
        //// Game & client states
        
        // onEnteringState: this method is called each time we are entering into a new game state.
        //                  You can use this method to perform some user interface changes at this moment.
        //
        onEnteringState: function( stateName, args )
        {
            console.log( 'Entering state: '+stateName );
            
            switch( stateName )
            {
            case 'dummmy':
                break;
            }
        },

        // onLeavingState: this method is called each time we are leaving a game state.
        //                 You can use this method to perform some user interface changes at this moment.
        //
        onLeavingState: function( stateName )
        {
            console.log( 'Leaving state: '+stateName );
            
            switch( stateName )
            {
            case 'dummmy':
                break;
            }               
        }, 

        // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
        //                        action status bar (ie: the HTML links in the status bar).
        //        
        onUpdateActionButtons: function( stateName, args )
        {
            console.log( 'onUpdateActionButtons: '+stateName );
                      
            if( this.isCurrentPlayerActive() )
            {            
                switch( stateName )
                {

                case 'playerTurn':
                    this.addActionButton( 'start_discard', _('Discard'), 'onDiscardClick' ); 
                    break;

                case 'supportRevolt':
                    this.addActionButton( 'send_support', _('Send Revolt (red) Support'), 'sendSupportClick' ); 
                    break;

                case 'supportWar':
                    this.addActionButton( 'send_support', _('Send War Support'), 'sendSupportClick' ); 
                    break;
                }
            }
        },        

        ///////////////////////////////////////////////////
        //// Utility methods
        
        addTokenOnBoard: function(x, y, color, id){
            dojo.destroy('tile_'+id);

            dojo.place( this.format_block( 'jstpl_tile', {
                color: color,
                left: 12 + (parseInt(x) * 45),
                top: 22 + (parseInt(y) * 45),
                id: id
            }), 'tiles' );
        },
        
        addLeaderOnBoard: function(x, y, shape, kind, id){
            dojo.destroy('leader_'+id);
            dojo.place( this.format_block( 'jstpl_leader', {
                color: kind,
                id: id,
                shape: shape,
                left: 12 + (parseInt(x) * 45),
                top: 22 + (parseInt(y) * 45)
            }), 'tiles' );
        },

        clearSelection: function(){
            dojo.query('.selected').removeClass('selected');
        },


        ///////////////////////////////////////////////////
        //// Player's action
        
        /*
        
            Here, you are defining methods to handle player's action (ex: results of mouse click on 
            game objects).
            
            Most of the time, these methods:
            _ check the action is possible at this game state.
            _ make a call to the game server
        
        */

        onSpaceClick: function( evt ){
            dojo.stopEvent( evt );

            let coords = evt.currentTarget.id.split('_');
            let x = coords[1];
            let y = coords[2];

            let selected = dojo.query('.selected');
            if(selected.length > 1){
                this.showMessage(_("You can only place 1 tile or leader at a time."), "error");
                this.clearSelection();
                return;
            } else if(selected.length == 0){
                return
            }

            let kind = selected[0].id.split('_')[0];
            let id = selected[0].id.split('_')[1];
            if(kind == 'leader'){
                if( this.checkAction( 'placeTile' ) )  {            
                    this.ajaxcall( "/tigriseuphrates/tigriseuphrates/placeLeader.html", {
                        leader_id:id,
                        pos_x:x,
                        pos_y:y
                    }, this, function( result ) {} );
                }
            } else if(kind == 'tile') {
                if( this.checkAction( 'placeTile' ) )  {            
                    this.ajaxcall( "/tigriseuphrates/tigriseuphrates/placeTile.html", {
                        tile_id:id,
                        pos_x:x,
                        pos_y:y
                    }, this, function( result ) {} );
                }            
            }
            this.clearSelection();
        },

        onHandLeaderClick: function( evt ){
            dojo.stopEvent(evt);
            this.onHandClick(evt);
        },

        onHandClick: function( evt ){
            dojo.stopEvent( evt );
            let id = evt.currentTarget.id.split('_')[1];
            dojo.toggleClass(evt.currentTarget.id, 'selected');
        },

        onDiscardClick: function( evt ){
            dojo.stopEvent(evt);
            this.checkAction('discard');
            let ids = dojo.query('.tile.selected').map((t)=>t.id.split('_')[1]);
            if(ids.length == 0){
                this.showMessage(_("You must discard at least 1 tile."), "error");
                return;
            }
            this.ajaxcall( "/tigriseuphrates/tigriseuphrates/discardTiles.html", {
                tile_ids:ids.join(',')
            }, this, function( result ) {} );
            this.clearSelection();

        },

        sendSupportClick: function( evt ){
            dojo.stopEvent(evt);
            this.checkAction('placeSupport');
            let ids = dojo.query('.tile.selected').map((t)=>t.id.split('_')[1]);
            this.ajaxcall( "/tigriseuphrates/tigriseuphrates/placeSupport.html", {
                support_ids:ids.join(',')
            }, this, function( result ) {} );
            this.clearSelection();
        },
        
        /* Example:

        onMyMethodToCall1: function( evt )
        {
            console.log( 'onMyMethodToCall1' );
            
            // Preventing default browser reaction
            dojo.stopEvent( evt );

            // Check that this action is possible (see "possibleactions" in states.inc.php)
            if( ! this.checkAction( 'myAction' ) )
            {   return; }

            this.ajaxcall( "/tigriseuphrates/tigriseuphrates/myAction.html", { 
                                                                    lock: true, 
                                                                    myArgument1: arg1, 
                                                                    myArgument2: arg2,
                                                                    ...
                                                                 }, 
                         this, function( result ) {
                            
                            // What to do after the server call if it succeeded
                            // (most of the time: nothing)
                            
                         }, function( is_error) {

                            // What to do after the server call in anyway (success or failure)
                            // (most of the time: nothing)

                         } );        
        },        
        
        */

        
        ///////////////////////////////////////////////////
        //// Reaction to cometD notifications

        /*
            setupNotifications:
            
            In this method, you associate each of your game notifications with your local method to handle it.
            
            Note: game notification names correspond to "notifyAllPlayers" and "notifyPlayer" calls in
                  your tigriseuphrates.game.php file.
        
        */
        setupNotifications: function()
        {
            console.log( 'notifications subscriptions setup' );
            
            // TODO: here, associate your game notifications with local methods
            dojo.subscribe( 'placeTile', this, 'notif_placeTile');
            dojo.subscribe( 'placeLeader', this, 'notif_placeLeader');
            dojo.subscribe( 'drawTiles', this, 'notif_drawTiles');

            // Example 1: standard notification handling
            // dojo.subscribe( 'cardPlayed', this, "notif_cardPlayed" );
            
            // Example 2: standard notification handling + tell the user interface to wait
            //            during 3 seconds after calling the method in order to let the players
            //            see what is happening in the game.
            // dojo.subscribe( 'cardPlayed', this, "notif_cardPlayed" );
            // this.notifqueue.setSynchronous( 'cardPlayed', 3000 );
            // 
        },  
        
        // TODO: from this point and below, you can write your game notifications handling methods
        notif_placeTile: function( notif ){
            this.addTokenOnBoard(notif.args.x, notif.args.y, notif.args.color, notif.args.tile_id);
        },

        notif_placeLeader: function( notif ){
            this.addLeaderOnBoard(notif.args.x, notif.args.y, notif.args.shape, notif.args.color, notif.args.leader_id);
        },

        notif_drawTiles: function( notif ){
            for(var tile of notif.args.tiles){
                dojo.place( this.format_block( 'jstpl_hand', {
                    color: tile.kind,
                    id: tile.id
                }), 'hand' );
            }
            dojo.query('#hand .tile').connect('onclick', this, 'onHandClick');
        },
        
        /*
        Example:
        
        notif_cardPlayed: function( notif )
        {
            console.log( 'notif_cardPlayed' );
            console.log( notif );
            
            // Note: notif.args contains the arguments specified during you "notifyAllPlayers" / "notifyPlayer" PHP call
            
            // TODO: play the card in the user interface.
        },    
        
        */
   });             
});
