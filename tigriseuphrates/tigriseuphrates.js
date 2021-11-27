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
            this.pickAmulet = false;

        },
        
        setup: function( gamedatas ){
            console.log( "Starting game setup" );
            
            // Setting up player boards
            for( var player_id in gamedatas.players ){
                var player = gamedatas.players[player_id];
                dojo.place( this.format_block( 'jstpl_player_symbol', {
                    player_shape: player['shape'] 
                }), 'player_board_'+player_id );
            }
            
            for(var tile of gamedatas.board){
                if(tile.isUnion == '1'){
                    this.addTokenOnBoard(tile.posX, tile.posY, 'union', tile.id);
                } else {
                    this.addTokenOnBoard(tile.posX, tile.posY, tile.kind, tile.id);
                }
                if(tile.hasAmulet == '1'){
                    console.log('placing amulet ' + tile.id);
                    dojo.place( this.format_block( 'jstpl_amulet', {
                        id: tile.id
                    }), 'tile_'+tile.id );
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
            
            if(gamedatas.gamestate.name == 'pickAmulet'){
                dojo.query('.space').style('display', 'block');
                this.pickAmulet = true;
            }
 
            // Setup game notifications to handle (see "setupNotifications" method below)
            this.setupNotifications();

            console.log( "Ending game setup" );
        },
       

        ///////////////////////////////////////////////////
        //// Game & client states
        
        onEnteringState: function( stateName, args ){
            console.log( 'Entering state: '+stateName );
            
            switch( stateName )
            {
            case 'warLeader':
                dojo.query('#board .leader').connect('onclick', this, 'onWarLeaderClick');
                break;
            case 'pickAmulet':
                dojo.query('.space').style('display', 'block');
                this.pickAmulet = true;
                break;
            case 'dummmy':
                break;
            }
        },

        onLeavingState: function( stateName ){
            console.log( 'Leaving state: '+stateName );
            
            switch( stateName )
            {
            case 'pickAmulet':
                dojo.query('.space').style('display', 'none');
                this.pickAmulet = false;
                break;
            case 'dummmy':
                break;
            }               
        }, 

        onUpdateActionButtons: function( stateName, args ){
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
            dojo.query('.space').style('display', 'none');
        },

        refreshConnections: function(){
            console.log('refreshing connections');
            this.disconnectAll();
            dojo.query('.space').connect('onclick', this, 'onSpaceClick');
            dojo.query('#hand .tile').connect('onclick', this, 'onHandClick');
            dojo.query('#hand .leader').connect('onclick', this, 'onHandLeaderClick');
        },


        ///////////////////////////////////////////////////
        //// Player's action

        onSpaceClick: function( evt ){
            dojo.stopEvent( evt );

            let coords = evt.currentTarget.id.split('_');
            let x = coords[1];
            let y = coords[2];
            if(this.pickAmulet){
                if( this.checkAction( 'pickAmulet' ) )  {            
                    this.ajaxcall( "/tigriseuphrates/tigriseuphrates/pickAmulet.html", {
                        pos_x:x,
                        pos_y:y
                    }, this, function( result ) {} );
                }        
                return;
            }

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

        onWarLeaderClick: function( evt ){
            dojo.stopEvent(evt);
            if(this.checkAction('selectWarLeader')){
                let leader_id = evt.currentTarget.id.split('_')[1];
                this.ajaxcall( "/tigriseuphrates/tigriseuphrates/selectWarLeader.html", {
                    leader_id:leader_id
                }, this, function( result ) {} );
            }
        },

        onHandClick: function( evt ){
            dojo.stopEvent( evt );
            let id = evt.currentTarget.id.split('_')[1];
            dojo.toggleClass(evt.currentTarget.id, 'selected');
            dojo.query('.space').style('display', 'block');
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
        
        ///////////////////////////////////////////////////
        //// Reaction to cometD notifications

        setupNotifications: function(){
            console.log( 'notifications subscriptions setup' );
            
            dojo.subscribe( 'placeTile', this, 'notif_placeTile' );
            dojo.subscribe( 'placeLeader', this, 'notif_placeLeader' );
            dojo.subscribe( 'drawTiles', this, 'notif_drawTiles' );
            dojo.subscribe( 'discard', this, 'notif_discard' );
            dojo.subscribe( 'placeSupport', this, 'notif_placeSupport' );
            dojo.subscribe( 'revoltConcluded', this, 'notif_revoltConcluded' );
            dojo.subscribe( 'warConcluded', this, 'notif_warConcluded' );
            dojo.subscribe( 'pickedAmulet', this, 'notif_pickedAmulet' );
        },  
        
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
            this.refreshConnections(); 
        },

        notif_discard: function( notif ){
            for(let tile_id of notif.args.tile_ids){
                dojo.destroy('tile_'+tile_id);
            }
        },

        notif_pickedAmulet: function( notif ){
            dojo.destroy('amulet_'+notif.args.tile_id);
        },

        notif_placeSupport: function( notif ){
            for(let tile_id of notif.args.tile_ids){
                dojo.destroy('tile_'+tile_id);
                dojo.place( this.format_block( 'jstpl_hand', {
                    color: notif.args.kind,
                    id: tile_id
                }), 'support' );
            }
        },

        notif_revoltConcluded: function( notif ){
            dojo.empty('support');
            dojo.destroy('leader_'+notif.args.loser_id);
            if(this.player_id == notif.args.losing_player_id){
                // add leader back to hand
                dojo.place( this.format_block( 'jstpl_leader_hand', {
                        color: notif.args.kind,
                        id: notif.args.loser_id,
                        shape: notif.args.loser_shape
                    }), 'hand' );
            }
        },

        notif_warConcluded: function( notif ){
            dojo.empty('support');
            dojo.destroy('leader_'+notif.args.loser_id);
            if(this.player_id == notif.args.losing_player_id){
                // add leader back to hand
                dojo.place( this.format_block( 'jstpl_leader_hand', {
                        color: notif.args.kind,
                        id: notif.args.loser_id,
                        shape: notif.args.loser_shape
                    }), 'hand' );
            }
            for(let tile_id of notif.args.tiles_to_remove){
                dojo.destroy('tile_'+tile_id);
            }
        },
   });             
});
