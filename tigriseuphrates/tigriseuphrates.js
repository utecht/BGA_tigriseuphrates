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
            this.board_tiles = Array(16).fill(0).map(x => Array(11).fill(0));
        },
        
        setup: function( gamedatas ){
            console.log( "Starting game setup" );
            
            // Setting up player boards
            for( var player_id in gamedatas.players ){
                var player = gamedatas.players[player_id];
            }
            
            for(var tile of gamedatas.board){
                if(tile.isUnion == '1'){
                    this.addTokenOnBoard(tile.posX, tile.posY, 'union', tile.id);
                } else {
                    this.addTokenOnBoard(tile.posX, tile.posY, tile.kind, tile.id);
                }
                if(tile.hasAmulet == '1'){
                    let ix = parseInt(tile.posX);
                    let iy = parseInt(tile.posY);
                    let tx = 12 + (ix * 45);
                    let ty = 22 + (iy * 45);
                    dojo.place( this.format_block( 'jstpl_amulet', {
                        id: tile.id,
                        left: tx,
                        top: ty,
                        x: ix,
                        y: iy
                    }), 'amulets' );
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
                    }), 'hand_leaders' );
                }
            }

            for(var tile of gamedatas.hand){
                dojo.place( this.format_block( 'jstpl_hand', {
                    color: tile.kind,
                    id: tile.id
                }), 'hand_tiles' );
            }

            for(var monument of gamedatas.monuments){
                if(monument.onBoard === '0'){
                    dojo.place( this.format_block( 'jstpl_mini_monument', {
                        id: monument.id,
                        color1: monument.color1,
                        color2: monument.color2
                    }), 'unbuilt_monuments' );
                } else {
                    this.addMonumentOnBoard(monument.posX, monument.posY, monument.id, monument.color1, monument.color2);
                }
            }

            dojo.query('.space').connect('onclick', this, 'onSpaceClick');
            dojo.query('#hand_tiles .mini_tile').connect('onclick', this, 'onHandClick');
            dojo.query('#hand_leaders .mini_leader_token').connect('onclick', this, 'onHandLeaderClick');
            dojo.query('#unbuilt_monuments .mini_monument').connect('onclick', this, 'onMonumentClick');
            
            if(gamedatas.gamestate.name == 'pickAmulet'){
                dojo.query('.space').style('display', 'block');
                this.pickAmulet = true;
            }

            this.stateName = gamedatas.gamestate.name;

            this.updatePlayerStatus(gamedatas.player_status);

            dojo.place( this.format_block('jstpl_toggle_kingdoms', {}), 'right-side-first-part');
            dojo.query('#toggle_kingdoms').connect('onclick', this, 'onToggleKingdoms');

            dojo.place( this.format_block('jstpl_toggle_monuments', {}), 'right-side-first-part');
            dojo.query('#toggle_monuments').connect('onclick', this, 'onToggleMonuments');

            this.points = gamedatas.points;
            this.updatePoints();

 
            // Setup game notifications to handle (see "setupNotifications" method below)
            this.setupNotifications();

            console.log( "Ending game setup" );
        },
       

        ///////////////////////////////////////////////////
        //// Game & client states

        placeConflictStatus(args, kind){
            dojo.destroy('conflict_status');
            dojo.place( this.format_block( 'jstpl_conflict_status', {
                conflict_type: kind,
                attacker_color: args.attacker.kind,
                attacker_shape: args.attacker.shape,
                defender_color: args.defender.kind,
                defender_shape: args.defender.shape
            }), 'support' );
            let tile_color = args.attacker.kind;
            if(kind == 'Revolt'){
                tile_color = 'red';
            }
            for(var i = 0; i < parseInt(args.attacker_board_strength); i++){
                console.log('adding attacking support');
                dojo.place( this.format_block( 'jstpl_tile_fake', {
                    color: tile_color 
                }), 'attacker_board_support' );
            }
            for(var i = 0; i < parseInt(args.defender_board_strength); i++){
                dojo.place( this.format_block( 'jstpl_tile_fake', {
                    color: tile_color
                }), 'defender_board_support' );
            }
            for(var i = 0; i < parseInt(args.attacker_hand_strength); i++){
                dojo.place( this.format_block( 'jstpl_tile_fake', {
                    color: tile_color
                }), 'attacker_hand_support' );
            }

        },
        
        onEnteringState: function( stateName, args ){
            console.log( 'Entering state: '+stateName );
            this.stateName = stateName;
            if('args' in args && args.args !== null && 'kingdoms' in args.args){
                this.addKingdoms(args.args.kingdoms);
            }

            if('args' in args && args.args !== null && 'player_status' in args.args){
                this.updatePlayerStatus(args.args.player_status);
            }
            
            switch( stateName )
            {
            case 'pickAmulet':
                dojo.query('.space').style('display', 'block');
                this.pickAmulet = true;
                break;
            case 'supportRevolt':
                this.placeConflictStatus(args.args, 'Revolt');
                break;
            case 'supportWar':
                this.placeConflictStatus(args.args, 'War');
                break;
            case 'warLeader':
                dojo.destroy('conflict_status');
                break;
            case 'playerTurn':
                dojo.destroy('conflict_status');
                break;
            case 'buildMonument':
                dojo.removeClass('monumentbox', 'hidden');
                this.passConfirm = false;
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
                      
            if( this.isCurrentPlayerActive() )
            {            
                switch( stateName )
                {

                case 'playerTurn':
                    this.addActionButton( 'start_discard', _('Discard'), 'onDiscardClick' ); 
                    if(args.can_undo){
                        this.addActionButton( 'start_undo', _('Undo'), 'onUndoClick' ); 
                    }
                    break;

                case 'supportRevolt':
                    this.addActionButton( 'send_support', _('Send Revolt (red) Support'), 'sendSupportClick' ); 
                    if(args.can_undo){
                        this.addActionButton( 'start_undo', _('Undo'), 'onUndoClick' ); 
                    }
                    break;

                case 'supportWar':
                    this.addActionButton( 'send_support', _('Send War Support'), 'sendSupportClick' ); 
                    if(args.can_undo){
                        this.addActionButton( 'start_undo', _('Undo'), 'onUndoClick' ); 
                    }
                    break;

                case 'buildMonument':
                    this.addActionButton( 'send_pass', _('Pass'), 'sendPassClick' );
                    if(args.can_undo){
                        this.addActionButton( 'start_undo', _('Undo'), 'onUndoClick' ); 
                    }
                    break;

                case 'pickAmulet':
                    if(args.can_undo){
                        this.addActionButton( 'start_undo', _('Undo'), 'onUndoClick' ); 
                    }
                    break;
                }
            }
        },        

        onScreenWidthChange: function(){
            console.log('screen width changed....');
            let m = this.getMargins();
            console.log(m.board_width, m.board_height);

            dojo.style('board', 'width', toint(m.board_width)+'px');
            dojo.style('board', 'background-size', toint(m.board_width)+'px');
            dojo.style('board', 'height', toint(m.board_height)+'px');
            this.addStyleToClass('tile', 'width', toint(m.tile_size - m.tile_padding)+'px');
            this.addStyleToClass('tile', 'height', toint(m.tile_size - m.tile_padding)+'px');
            this.addStyleToClass('kingdom', 'width', toint(m.tile_size)+'px');
            this.addStyleToClass('kingdom', 'height', toint(m.tile_size)+'px');
            this.addStyleToClass('space', 'width', toint(m.tile_size)+'px');
            this.addStyleToClass('space', 'height', toint(m.tile_size)+'px');
            this.addStyleToClass('monument', 'width', toint(m.tile_size * 2)+'px');
            this.addStyleToClass('monument', 'height', toint(m.tile_size * 2)+'px');
            this.addStyleToClass('monument_lower', 'width', toint(m.tile_size)+'px');
            this.addStyleToClass('monument_lower', 'height', toint(m.tile_size)+'px');
            this.addStyleToClass('monument_upper', 'width', toint(m.tile_size/2)+'px');
            this.addStyleToClass('monument_upper', 'height', toint(m.tile_size/2)+'px');
            this.addStyleToClass('leader_token', 'height', toint(m.tile_size)+'px');
            this.addStyleToClass('leader_token', 'width', toint(m.tile_size)+'px');
            this.addStyleToClass('amulet', 'width', toint(m.tile_size)+'px');
            this.addStyleToClass('amulet', 'height', toint(m.tile_size)+'px');
            this.addStyleToClass('leader', 'height', toint(m.tile_size)+'px');
            this.addStyleToClass('leader', 'width', toint(m.tile_size)+'px');
            this.addStyleToClass('leader', 'backgroundSize', toint(4 * m.tile_size)+'px');
            this.addStyleToClass('leader_bow', 'backgroundPosition', '-'+toint(0 * m.tile_size)+'px, 0px');
            this.addStyleToClass('leader_goat', 'backgroundPosition', '-'+toint(1 * m.tile_size)+'px, 0px');
            this.addStyleToClass('leader_lion', 'backgroundPosition', '-'+toint(2 * m.tile_size)+'px, 0px');
            this.addStyleToClass('leader_urn', 'backgroundPosition', '-'+toint(3 * m.tile_size)+'px, 0px');
            this.addStyleToClass('tile', 'backgroundSize', toint(7 * m.tile_size)+'px');
            this.addStyleToClass('tile_flipped', 'backgroundPosition', '-'+toint(0 * m.tile_size)+'px, 0px');
            this.addStyleToClass('tile_black', 'backgroundPosition', '-'+toint(1 * m.tile_size)+'px, 0px');
            this.addStyleToClass('tile_catastrophe', 'backgroundPosition', '-'+toint(2 * m.tile_size)+'px, 0px');
            this.addStyleToClass('tile_green', 'backgroundPosition', '-'+toint(3 * m.tile_size)+'px, 0px');
            this.addStyleToClass('tile_red', 'backgroundPosition', '-'+toint(4 * m.tile_size)+'px, 0px');
            this.addStyleToClass('tile_union', 'backgroundPosition', '-'+toint(5 * m.tile_size)+'px, 0px');
            this.addStyleToClass('tile_blue', 'backgroundPosition', '-'+toint(6 * m.tile_size)+'px, 0px');
            dojo.query('#board .tile').forEach(function(tile){
                let x = toint(tile.dataset.x);
                let y = toint(tile.dataset.y);
                let left = (x * m.tile_size) + m.margin_width + toint(m.tile_padding / 2);
                let top = (y * m.tile_size) + m.margin_height + toint(m.tile_padding / 2);
                dojo.style(tile.id, 'top', toint(top)+'px');
                dojo.style(tile.id, 'left', toint(left)+'px');
            });
            dojo.query('#kingdoms .kingdom').forEach(function(kingdom){
                let x = toint(kingdom.id.split('_')[1]);
                let y = toint(kingdom.id.split('_')[2]);
                let left = (x * m.tile_size) + m.margin_width;
                let top = (y * m.tile_size) + m.margin_height;
                dojo.style(kingdom.id, 'top', toint(top)+'px');
                dojo.style(kingdom.id, 'left', toint(left)+'px');
            });
            dojo.query('#spaces .space').forEach(function(space){
                let x = toint(space.id.split('_')[1]);
                let y = toint(space.id.split('_')[2]);
                let left = (x * m.tile_size) + m.margin_width;
                let top = (y * m.tile_size) + m.margin_height;
                dojo.style(space.id, 'top', toint(top)+'px');
                dojo.style(space.id, 'left', toint(left)+'px');
            });
            dojo.query('#amulets .amulet').forEach(function(amulet){
                let x = toint(amulet.dataset.x);
                let y = toint(amulet.dataset.y);
                let left = (x * m.tile_size) + m.margin_width;
                let top = (y * m.tile_size) + m.margin_height;
                dojo.style(amulet.id, 'top', toint(top)+'px');
                dojo.style(amulet.id, 'left', toint(left)+'px');
            });
            dojo.query('#tiles .leader_token').forEach(function(leader){
                let x = toint(leader.dataset.x);
                let y = toint(leader.dataset.y);
                let left = (x * m.tile_size) + m.margin_width;
                let top = (y * m.tile_size) + m.margin_height;
                dojo.style(leader.id, 'top', toint(top)+'px');
                dojo.style(leader.id, 'left', toint(left)+'px');
            });
            dojo.query('#monuments .monument').forEach(function(monument){
                let x = toint(monument.dataset.x);
                let y = toint(monument.dataset.y);
                let left = (x * m.tile_size) + m.margin_width;
                let top = (y * m.tile_size) + m.margin_height;
                dojo.style(monument.id, 'top', toint(top)+'px');
                dojo.style(monument.id, 'left', toint(left)+'px');
            });
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

        getMargins: function(){
            let board_width = 3307;
            let board_height = 2410;
            let margin_width = 52;
            let margin_height = 102;
            let tile_size = 200;

            let gamePlayArea = dojo.byId('my_game_area');
            let rect = gamePlayArea.getBoundingClientRect();
            console.log(rect);
            let window_height = window.innerHeight;
            let window_width = window.innerWidth;

            let target_height = window_height - rect.top - 50;
            let target_ratio = target_height / board_height;
            let target_width = target_ratio * board_width;

            // account for smaller screens
            if(window_width < 1350 || target_height < 539){
                target_height = 539;
                target_ratio = target_height / board_height;
                target_width = target_ratio * board_width;
            // account for tall screens
            } else if(target_width > rect.width - 300){
                target_width = rect.width - 300;
                target_ratio = target_width / board_width;
                target_height = target_ratio * board_height;
            }


            let scaled_tile = tile_size * target_ratio;
            let tile_padding = toint(scaled_tile * .05);

            let target_margin_width = margin_width * target_ratio;
            let target_margin_height = margin_height * target_ratio;

            return {
                tile_size: scaled_tile,
                board_width: target_width,
                board_height: target_height,
                margin_width: target_margin_width,
                margin_height: target_margin_height,
                target_ratio: target_ratio,
                tile_padding: tile_padding
            }
        },

        getLeftTop: function(x, y){
            let m = this.getMargins();

            let left = (x * m.tile_size) + m.margin_width;
            let top = (y * m.tile_size) + m.margin_height;

            return {left: toint(left), top: toint(top)};
        },

        addMonumentOnBoard: function(x, y, id, color1, color2, animate=false){
            dojo.destroy('monument_'+id);
            let ix = parseInt(x);
            let iy = parseInt(y);
            dojo.place( this.format_block( 'jstpl_monument', {
                        id: id,
                        color1: color1,
                        color2: color2,
                        position: 'absolute',
                        left: 0,
                        top: 0,
                        x: ix,
                        y: iy
                    }), 'monuments' );
            this.onScreenWidthChange();
            let tile_id = this.board_tiles[ix][iy];
            dojo.addClass('tile_'+tile_id, 'rotate_top_left');
            tile_id = this.board_tiles[ix + 1][iy];
            dojo.addClass('tile_'+tile_id, 'rotate_top_right');
            tile_id = this.board_tiles[ix][iy + 1];
            dojo.addClass('tile_'+tile_id, 'rotate_bottom_left');
            if(animate){
                this.placeOnObject( 'monument_'+id, 'unbuilt_monuments' );
                this.slideToObjectPos('monument_'+id, 'monuments', left, top).play();
            }
        },
        
        addTokenOnBoard: function(x, y, color, id, animate=false){
            let ix = parseInt(x);
            let iy = parseInt(y);
            this.board_tiles[ix][iy] = id;
            let my_tile = dojo.query('#tile_'+id).length > 0;
            dojo.destroy('tile_'+id);

            dojo.place( this.format_block( 'jstpl_tile', {
                color: color,
                left: 0,
                top: 0,
                id: id,
                x: x,
                y: y
            }), 'tiles' );
            this.onScreenWidthChange();
            if(animate){
                if(my_tile){
                    this.placeOnObject( 'tile_'+id, 'hand_tiles' );
                } else {
                    this.placeOnObject( 'tile_'+id, 'player_boards' );
                }
                this.slideToObjectPos('tile_'+id, 'tiles', left, top).play();
            }
        },
        
        addLeaderOnBoard: function(x, y, shape, kind, id, animate=false){
            let my_leader = dojo.query('#leader'+id).length > 0;
            dojo.destroy('leader_'+id);
            dojo.place( this.format_block( 'jstpl_leader', {
                color: kind,
                id: id,
                shape: shape,
                left: 0,
                top: 0,
                x: x,
                y: y
            }), 'tiles' );
            this.onScreenWidthChange();
            if(animate){
                if(my_leader){
                    this.placeOnObject( 'leader_'+id, 'hand_leaders' );
                } else {
                    this.placeOnObject( 'leader_'+id, 'player_boards' );
                }
                this.slideToObjectPos('leader_'+id, 'tiles', left, top).play();
            }
            dojo.query('#leader_'+id).connect('onclick', this, 'onLeaderClick');
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

        updatePlayerStatus: function(player_status){
            for( var player_id in player_status ){
                let player = player_status[player_id];
                dojo.destroy('player_status_'+player_id);
                dojo.place( this.format_block( 'jstpl_player_status', {
                    player_id: player_id,
                    player_shape: player.shape,
                    catastrophe_count: player.catastrophe_count,
                    hand_count: player.hand_count
                }), 'player_board_'+player_id );
            }

        },


        ///////////////////////////////////////////////////
        //// Player's action

        onToggleKingdoms: function( evt ){
            dojo.stopEvent( evt );
            dojo.toggleClass('kingdoms', 'hidden');
        },

        onToggleMonuments: function( evt ){
            dojo.stopEvent( evt );
            dojo.toggleClass('monumentbox', 'hidden');
        },

        onSpaceClick: function( evt ){
            dojo.stopEvent( evt );

            let coords = evt.currentTarget.id.split('_');
            let x = coords[1];
            let y = coords[2];
            if(this.pickAmulet){
                if( this.checkAction( 'pickAmulet' ) )  {            
                    this.ajaxcall( "/tigriseuphrates/tigriseuphrates/pickAmulet.html", {
                        lock: true,
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
                        lock: true,
                        leader_id:id,
                        pos_x:x,
                        pos_y:y
                    }, this, function( result ) {} );
                }
            } else if(kind == 'tile') {
                if( this.checkAction( 'placeTile' ) )  {            
                    this.ajaxcall( "/tigriseuphrates/tigriseuphrates/placeTile.html", {
                        lock: true,
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
                        lock: true,
                        leader_id:leader_id
                    }, this, function( result ) {} );
                }
            } else {
                if(this.checkAction('pickupLeader')){
                    let leader_id = evt.currentTarget.id.split('_')[1];
                    this.ajaxcall( "/tigriseuphrates/tigriseuphrates/pickupLeader.html", {
                        lock: true,
                        leader_id:leader_id
                    }, this, function( result ) {} );
                }
            }
        },

        onMonumentClick: function( evt ){
            dojo.stopEvent(evt);
            this.passConfirm = false;
            $('send_pass').innerHTML = _("Pass");
            if(this.checkAction('buildMonument')){
                let monument_id = evt.currentTarget.id.split('_')[1];
                this.ajaxcall( "/tigriseuphrates/tigriseuphrates/buildMonument.html", {
                    lock: true,
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
            let ids = dojo.query('.mini_tile.selected').map((t)=>t.id.split('_')[1]);
            if(ids.length == 0){
                this.showMessage(_("You must discard at least 1 tile."), "error");
                return;
            }
            this.ajaxcall( "/tigriseuphrates/tigriseuphrates/discardTiles.html", {
                lock: true,
                tile_ids:ids.join(',')
            }, this, function( result ) {} );
            this.clearSelection();

        },

        sendSupportClick: function( evt ){
            dojo.stopEvent(evt);
            this.checkAction('placeSupport');
            let ids = dojo.query('.mini_tile.selected').map((t)=>t.id.split('_')[1]);
            this.ajaxcall( "/tigriseuphrates/tigriseuphrates/placeSupport.html", {
                lock: true,
                support_ids:ids.join(',')
            }, this, function( result ) {} );
            this.clearSelection();
        },

        sendPassClick: function( evt ){
            dojo.stopEvent(evt);
            this.checkAction('pass');
            if(this.passConfirm === true){
                this.passConfirm = false;
                this.ajaxcall( "/tigriseuphrates/tigriseuphrates/pass.html", {lock: true}, this, function( result ) {} );
                $('send_pass').innerHTML = _("Pass");
            } else {
                $('send_pass').innerHTML = _("Are you sure?");
                this.passConfirm = true;
            }
        },

        onUndoClick: function( evt ){
            dojo.stopEvent(evt);
            this.checkAction('undo');
            this.ajaxcall( "/tigriseuphrates/tigriseuphrates/undo.html", {lock: true}, this, function( result ) {} );
        },
        
        ///////////////////////////////////////////////////
        //// Reaction to cometD notifications

        setupNotifications: function(){
            console.log( 'notifications subscriptions setup' );
            
            dojo.subscribe( 'placeTile', this, 'notif_placeTile' );
            this.notifqueue.setSynchronous( 'placeTile', 500 );
            dojo.subscribe( 'placeLeader', this, 'notif_placeLeader' );
            this.notifqueue.setSynchronous( 'placeLeader', 500 );
            dojo.subscribe( 'drawTiles', this, 'notif_drawTiles' );
            dojo.subscribe( 'discard', this, 'notif_discard' );
            dojo.subscribe( 'placeSupport', this, 'notif_placeSupport' );
            this.notifqueue.setSynchronous( 'placeSupport', 1500 );
            dojo.subscribe( 'revoltConcluded', this, 'notif_revoltConcluded' );
            this.notifqueue.setSynchronous( 'revoltConcluded', 1500 );
            dojo.subscribe( 'warConcluded', this, 'notif_warConcluded' );
            this.notifqueue.setSynchronous( 'warConcluded', 1500 );
            dojo.subscribe( 'allWarsEnded', this, 'notif_allWarsEnded' );
            dojo.subscribe( 'pickedAmulet', this, 'notif_pickedAmulet' );
            this.notifqueue.setSynchronous( 'pickedAmulet', 500 );
            dojo.subscribe( 'playerScore', this, 'notif_playerScore' );
            this.notifqueue.setSynchronous( 'playerScore', 500 );
            dojo.subscribe( 'placeMonument', this, 'notif_placeMonument' );
            dojo.subscribe( 'catastrophe', this, 'notif_catastrophe' );
            this.notifqueue.setSynchronous( 'catastrophe', 500 );
            dojo.subscribe( 'leaderReturned', this, 'notif_leaderReturned' );
            this.notifqueue.setSynchronous( 'leaderReturned', 500 );
            dojo.subscribe( 'finalScores', this, 'notif_finalScores' );
            this.notifqueue.setSynchronous( 'finalScores', 5000 );
            dojo.subscribe( 'tileReturned', this, 'notif_tileReturned' );
            this.notifqueue.setSynchronous( 'tileReturned', 500 );
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
                }), 'hand_tiles' );
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
                }), notif.args.side+'_hand_support' );
            }
        },

        notif_revoltConcluded: function( notif ){
            if(notif.args.winning_side == 'attacker'){
                dojo.addClass('conflict_attacker', 'winner');
                dojo.addClass('conflict_defender', 'loser');
            } else {
                dojo.addClass('conflict_defender', 'winner');
                dojo.addClass('conflict_attacker', 'loser');
            }
            dojo.destroy('leader_'+notif.args.loser_id);
            if(this.player_id == notif.args.losing_player_id){
                // add leader back to hand
                dojo.place( this.format_block( 'jstpl_leader_hand', {
                        color: notif.args.kind,
                        id: notif.args.loser_id,
                        shape: notif.args.loser_shape
                    }), 'hand_leaders' );
                dojo.query('#leader_'+notif.args.loser_id).connect('onclick', this, 'onHandLeaderClick');
            }
        },

        notif_warConcluded: function( notif ){
            if(notif.args.winning_side == 'attacker'){
                dojo.addClass('conflict_attacker', 'winner');
                dojo.addClass('conflict_defender', 'loser');
            } else {
                dojo.addClass('conflict_defender', 'winner');
                dojo.addClass('conflict_attacker', 'loser');
            }
            dojo.destroy('leader_'+notif.args.loser_id);
            if(this.player_id == notif.args.losing_player_id){
                // add leader back to hand
                dojo.place( this.format_block( 'jstpl_leader_hand', {
                        color: notif.args.kind,
                        id: notif.args.loser_id,
                        shape: notif.args.loser_shape
                    }), 'hand_leaders' );
                dojo.query('#leader_'+notif.args.loser_id).connect('onclick', this, 'onHandLeaderClick');
            }
            for(let tile_id of notif.args.tiles_removed){
                this.fadeOutAndDestroy( 'tile_'+tile_id);
            }
        },

        notif_allWarsEnded: function( notif ){
            dojo.destroy('tile_'+notif.args.tile_id);
            dojo.destroy('conflict_status');
            this.addTokenOnBoard(notif.args.pos_x, notif.args.pos_y, notif.args.tile_color, notif.args.tile_id);
        },

        notif_playerScore: function( notif ){
            if(notif.args.player_id == this.points.player){
                this.points[notif.args.color] = toint(notif.args.points) + toint(this.points[notif.args.color]);
            }
            let temp_point = this.format_block( 'jstpl_point', {
                    color: notif.args.color,
                });
            let source = 'board';
            let target = 'player_boards'
            if(notif.args.source != false){
                source = notif.args.source+'_'+notif.args.id;
            }
            if(toint(notif.args.points) < 0){
                let t = source;
                source = target;
                target = t;
            }
            this.slideTemporaryObject( temp_point, 'board', source, target ).play();
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
            this.onScreenWidthChange();
        },

        notif_catastrophe: function( notif ){
            if(notif.args.removed_tile){
                this.fadeOutAndDestroy('tile_'+notif.args.removed_tile.id);
            }
            let catastrophe = notif.args.catastrophe;
            for(let leader of notif.args.removed_leaders){
                dojo.destroy('leader_'+leader.id);
                if(this.player_id == leader.owner){
                    // add leader back to hand
                    dojo.place( this.format_block( 'jstpl_leader_hand', {
                            color: leader.kind,
                            id: leader.id,
                            shape: leader.shape
                        }), 'hand_leaders' );
                    dojo.query('#leader_'+leader.id).connect('onclick', this, 'onHandLeaderClick');
                }
            }
            this.addTokenOnBoard(catastrophe.posX, catastrophe.posY, 'catastrophe', catastrophe.id, true);
        },

        notif_leaderReturned: function( notif ){
            dojo.destroy('leader_'+notif.args.leader.id);
            if(this.player_id == notif.args.leader.owner){
                // add leader back to hand
                dojo.place( this.format_block( 'jstpl_leader_hand', {
                        color: notif.args.leader.kind,
                        id: notif.args.leader.id,
                        shape: notif.args.leader.shape
                    }), 'hand_leaders' );
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
                this.scoreCtrl[player_id].setValue(point.lowest);
            }
        },

        notif_tileReturned: function( notif ){
            this.fadeOutAndDestroy('tile_'+notif.args.tile_id);
        },
   });             
});
