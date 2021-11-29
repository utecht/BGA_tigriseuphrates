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
            
            this.points = gamedatas.points;
            this.updatePoints();
            
            // Setting up player boards
            for( var player_id in gamedatas.players ){
                var player = gamedatas.players[player_id];
                dojo.place( this.format_block( 'jstpl_player_status', {
                    player_id: player_id,
                    player_shape: player['shape'],
                    catastrophe_count: player.catastrophe_count,
                    hand_count: player.hand_count
                }), 'player_board_'+player_id );
            }
            
            for(var tile of gamedatas.board){
                if(tile.isUnion == '1'){
                    this.addTokenOnBoard(tile.posX, tile.posY, 'union', tile.id);
                } else {
                    this.addTokenOnBoard(tile.posX, tile.posY, tile.kind, tile.id);
                }
                if(tile.hasAmulet == '1'){
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

            for(var monument of gamedatas.monuments){
                if(monument.onBoard === '0'){
                    dojo.place( this.format_block( 'jstpl_monument', {
                        id: monument.id,
                        color1: monument.color1,
                        color2: monument.color2,
                        position: 'relative',
                        left: 0,
                        top: 0
                    }), 'unbuilt_monuments' );
                } else {
                    this.addMonumentOnBoard(monument.posX, monument.posY, monument.id, monument.color1, monument.color2);
                }
            }

            dojo.query('.space').connect('onclick', this, 'onSpaceClick');
            dojo.query('#hand .tile').connect('onclick', this, 'onHandClick');
            dojo.query('#hand .leader').connect('onclick', this, 'onHandLeaderClick');
            dojo.query('#unbuilt_monuments .monument').connect('onclick', this, 'onMonumentClick');
            
            if(gamedatas.gamestate.name == 'pickAmulet'){
                dojo.query('.space').style('display', 'block');
                this.pickAmulet = true;
            }

            this.stateName = gamedatas.gamestate.name;

 
            // Setup game notifications to handle (see "setupNotifications" method below)
            this.setupNotifications();

            console.log( "Ending game setup" );
        },
       

        ///////////////////////////////////////////////////
        //// Game & client states
        
        onEnteringState: function( stateName, args ){
            console.log( 'Entering state: '+stateName );
            this.stateName = stateName;
            if('args' in args && args.args !== null && 'kingdoms' in args.args){
                this.addKingdoms(args.args.kingdoms);
            }

            if('args' in args && args.args !== null && 'player_points' in args.args){
                console.log(args.args.player_points);
                for( var player_id in args.args.player_points ){
                    let player = args.args.player_points[player_id];
                    dojo.destroy('player_status_'+player_id);
                    dojo.place( this.format_block( 'jstpl_player_status', {
                        player_id: player_id,
                        player_shape: player.shape,
                        catastrophe_count: player.catastrophe_count,
                        hand_count: player.hand_count
                    }), 'player_board_'+player_id );
                }
            }
            
            switch( stateName )
            {
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

                case 'buildMonument':
                    this.addActionButton( 'send_pass', _('Pass'), 'sendPassClick' );
                    break;
                }
            }
        },        

        ///////////////////////////////////////////////////
        //// Utility methods

        addKingdoms: function(kingdoms){
            for(let i = 1; i <= 16; i++){
                dojo.query('.kingdom_'+i).removeClass('kingdom_'+i);
            }
            let i = 0;
            for(let kingdom of kingdoms){
                i += 1;
                for(let pos of kingdom){
                    dojo.addClass('kingdom_'+pos[0]+'_'+pos[1], 'kingdom_'+i);
                }
            }
        },

        addMonumentOnBoard: function(x, y, id, color1, color2, animate=false){
            dojo.destroy('monument_'+id);
            let tx = 12 + (parseInt(x) * 45);
            let ty = 22 + (parseInt(y) * 45);
            dojo.place( this.format_block( 'jstpl_monument', {
                        id: id,
                        color1: color1,
                        color2: color2,
                        position: 'absolute',
                        left: tx,
                        top: ty
                    }), 'monuments' );
            if(animate){
                this.placeOnObject( 'monument_'+id, 'unbuilt_monuments' );
                this.slideToObjectPos('monument_'+id, 'monuments', tx, ty).play();
            }
        },
        
        addTokenOnBoard: function(x, y, color, id, animate=false){
            dojo.destroy('tile_'+id);
            let tx = 12 + (parseInt(x) * 45);
            let ty = 22 + (parseInt(y) * 45);

            dojo.place( this.format_block( 'jstpl_tile', {
                color: color,
                left: tx,
                top: ty,
                id: id
            }), 'tiles' );
            if(animate){
                this.placeOnObject( 'tile_'+id, 'player_boards' );
                this.slideToObjectPos('tile_'+id, 'tiles', tx, ty).play();
            }
        },
        
        addLeaderOnBoard: function(x, y, shape, kind, id, animate=false){
            dojo.destroy('leader_'+id);
            let tx = 12 + (parseInt(x) * 45);
            let ty = 22 + (parseInt(y) * 45);
            dojo.place( this.format_block( 'jstpl_leader', {
                color: kind,
                id: id,
                shape: shape,
                left: tx,
                top: ty
            }), 'tiles' );
            dojo.query('#leader_'+id).connect('onclick', this, 'onLeaderClick');
            if(animate){
                this.placeOnObject( 'leader_'+id, 'player_boards' );
                this.slideToObjectPos('leader_'+id, 'tiles', tx, ty).play();
            }
        },

        clearSelection: function(){
            dojo.query('.selected').removeClass('selected');
            dojo.query('.space').style('display', 'none');
        },

        updatePoints: function(){
            dojo.destroy('points_'+this.points.player);
            dojo.place( this.format_block( 'jstpl_points', {
                player_id: this.points.player,
                red: this.points.red,
                black: this.points.black,
                blue: this.points.blue,
                green: this.points.green,
                amulet: this.points.amulet
            }),'player_board_'+this.points.player );
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

        onLeaderClick: function( evt ){
            dojo.stopEvent(evt);
            if(this.stateName == 'warLeader'){
                if(this.checkAction('selectWarLeader')){
                    let leader_id = evt.currentTarget.id.split('_')[1];
                    this.ajaxcall( "/tigriseuphrates/tigriseuphrates/selectWarLeader.html", {
                        leader_id:leader_id
                    }, this, function( result ) {} );
                }
            } else {
                if(this.checkAction('pickupLeader')){
                    let leader_id = evt.currentTarget.id.split('_')[1];
                    this.ajaxcall( "/tigriseuphrates/tigriseuphrates/pickupLeader.html", {
                        leader_id:leader_id
                    }, this, function( result ) {} );
                }
            }
        },

        onMonumentClick: function( evt ){
            dojo.stopEvent(evt);
            if(this.checkAction('buildMonument')){
                let monument_id = evt.currentTarget.id.split('_')[1];
                this.ajaxcall( "/tigriseuphrates/tigriseuphrates/buildMonument.html", {
                    monument_id:monument_id
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

        sendPassClick: function( evt ){
            dojo.stopEvent(evt);
            this.checkAction('pass');
            this.ajaxcall( "/tigriseuphrates/tigriseuphrates/pass.html", {}, this, function( result ) {} );
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
            dojo.subscribe( 'allWarsEnded', this, 'notif_allWarsEnded' );
            dojo.subscribe( 'pickedAmulet', this, 'notif_pickedAmulet' );
            dojo.subscribe( 'playerScore', this, 'notif_playerScore' );
            dojo.subscribe( 'placeMonument', this, 'notif_placeMonument' );
            dojo.subscribe( 'catastrophe', this, 'notif_catastrophe' );
            dojo.subscribe( 'leaderReturned', this, 'notif_leaderReturned' );
            dojo.subscribe( 'finalScores', this, 'notif_finalScores' );
        },  
        
        // TODO: don't animate your own tile placement
        notif_placeTile: function( notif ){
            this.addTokenOnBoard(notif.args.x, notif.args.y, notif.args.color, notif.args.tile_id, true);
        },

        notif_placeLeader: function( notif ){
            this.addLeaderOnBoard(notif.args.x, notif.args.y, notif.args.shape, notif.args.color, notif.args.leader_id, true);
        },

        notif_drawTiles: function( notif ){
            for(var tile of notif.args.tiles){
                dojo.place( this.format_block( 'jstpl_hand', {
                    color: tile.kind,
                    id: tile.id
                }), 'hand' );
                dojo.query('#tile_'+tile.id).connect('onclick', this, 'onHandClick');
            }
        },

        notif_discard: function( notif ){
            for(let tile_id of notif.args.tile_ids){
                dojo.destroy('tile_'+tile_id);
            }
        },

        notif_pickedAmulet: function( notif ){
            this.slideToObjectAndDestroy( 'amulet_'+notif.args.tile_id, 'player_boards');
            if(notif.args.player_id == this.points.player){
                this.points[notif.args.color] = 1 + toint(this.points[notif.args.color]);
            }
            this.updatePoints();
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
                dojo.query('#leader_'+notif.args.loser_id).connect('onclick', this, 'onHandLeaderClick');
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
                dojo.query('#leader_'+notif.args.loser_id).connect('onclick', this, 'onHandLeaderClick');
            }
            for(let tile_id of notif.args.tiles_removed){
                this.fadeOutAndDestroy( 'tile_'+tile_id);
            }
        },

        notif_allWarsEnded: function( notif ){
            dojo.destroy('tile_'+notif.args.tile_id);
            this.addTokenOnBoard(notif.args.pos_x, notif.args.pos_y, notif.args.tile_color, notif.args.tile_id);
        },

        notif_playerScore: function( notif ){
            if(notif.args.player_id == this.points.player){
                this.points[notif.args.color] = toint(notif.args.points) + toint(this.points[notif.args.color]);
            }
            let temp_point = this.format_block( 'jstpl_point', {
                    color: notif.args.color,
                });
            this.slideTemporaryObject( temp_point, 'board', 'board', 'player_boards' ).play();
            this.updatePoints();
        },

        notif_placeMonument: function( notif ){
            this.addMonumentOnBoard(notif.args.pos_x, notif.args.pos_y, notif.args.monument_id, notif.args.color1, notif.args.color2, true);
            for(let tile_id of notif.args.flip_ids){
                dojo.removeClass('tile_'+tile_id, 'tile_red');
                dojo.removeClass('tile_'+tile_id, 'tile_black');
                dojo.removeClass('tile_'+tile_id, 'tile_blue');
                dojo.removeClass('tile_'+tile_id, 'tile_union');
                dojo.removeClass('tile_'+tile_id, 'tile_green');
                dojo.addClass('tile_'+tile_id, 'tile_flipped');
            }
        },

        notif_catastrophe: function( notif ){
            dojo.destroy('tile_'+notif.args.tile_id);
            for(let leader of notif.args.removed_leaders){
                dojo.destroy('leader_'+leader.id);
                if(this.player_id == leader.owner){
                    // add leader back to hand
                    dojo.place( this.format_block( 'jstpl_leader_hand', {
                            color: leader.kind,
                            id: leader.id,
                            shape: leader.shape
                        }), 'hand' );
                    dojo.query('#leader_'+notif.args.loser_id).connect('onclick', this, 'onHandLeaderClick');
                }
            }
        },

        notif_leaderReturned: function( notif ){
            dojo.destroy('leader_'+notif.args.leader.id);
            if(this.player_id == notif.args.leader.owner){
                // add leader back to hand
                dojo.place( this.format_block( 'jstpl_leader_hand', {
                        color: notif.args.leader.kind,
                        id: notif.args.leader.id,
                        shape: notif.args.leader.shape
                    }), 'hand' );
                dojo.query('#leader_'+notif.args.leader.id).connect('onclick', this, 'onHandLeaderClick');
            }
        },

        notif_finalScores: function( notif ){
            let points = notif.args.points;
            for(let player_id of Object.keys(points)){
                let point = points[player_id];
                dojo.destroy('points_'+player_id);
                dojo.place( this.format_block( 'jstpl_points', {
                    player_id: player_id,
                    red: point.red,
                    black: point.black,
                    blue: point.blue,
                    green: point.green,
                    amulet: point.amulet
                }),'player_board_'+player_id );
            }
        },
   });             
});
