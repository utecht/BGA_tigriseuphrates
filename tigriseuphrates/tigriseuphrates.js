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
    "dojo","dojo/_base/declare", "dojo/dom-construct", "dojo/NodeList-traverse",
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
            this.pickTreasure = false;
            this.board_tiles = Array(16).fill(0).map(x => Array(11).fill(0));
            this.preferredHeight = null;
            this.selectMonumentTile = false;
            this.stateName = null;
            this.stateArgs = null;
            this.multiselect = false;
            this.finishDiscard = false;
            this.isLoadingComplete = false;
        },
        
        setup: function( gamedatas ){
            console.log( "Starting game setup" );
            
            // Setting up player boards
            for( var player_id in gamedatas.players ){
                var player = gamedatas.players[player_id];
            }
            
            for(var tile of gamedatas.board){
                if(tile.isUnion == '1'){
                    this.addTokenOnBoard(tile.posX, tile.posY, 'union', tile.id, null);
                } else {
                    this.addTokenOnBoard(tile.posX, tile.posY, tile.kind, tile.id, null);
                }
                if(tile.hasTreasure == '1'){
                    let ix = parseInt(tile.posX);
                    let iy = parseInt(tile.posY);
                    let tx = 12 + (ix * 45);
                    let ty = 22 + (iy * 45);
                    dojo.place( this.format_block( 'jstpl_treasure', {
                        id: tile.id,
                        left: tx,
                        top: ty,
                        x: ix,
                        y: iy
                    }), 'treasures' );
                }
            }

            for(var leader of gamedatas.leaders){
                if(leader.onBoard == '1'){
                    this.addLeaderOnBoard(leader.posX, leader.posY, leader.shape, leader.kind, leader.id, leader.owner);
                } else if(leader.owner == gamedatas.player){
                    dojo.place( this.format_block( 'jstpl_leader_hand', {
                        color: leader.kind,
                        id: leader.id,
                        shape: leader.shape
                    }), 'hand_leaders' );
                }
            }

            for(var tile of gamedatas.hand){
                let location = 'hand_tiles';
                if(tile.kind == 'catastrophe'){
                    location = 'hand_catastrophes';
                }
                dojo.place( this.format_block( 'jstpl_hand', {
                    color: tile.kind,
                    id: tile.id
                }), location );
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
            dojo.query('#hand_catastrophes .mini_tile').connect('onclick', this, 'onHandClick');
            dojo.query('#hand_tiles .mini_tile').connect('onclick', this, 'onHandClick');
            dojo.query('#hand_leaders .mini_leader_token').connect('onclick', this, 'onHandLeaderClick');
            dojo.query('#unbuilt_monuments .mini_monument').connect('onclick', this, 'onMonumentClick');
            
            if(gamedatas.gamestate.name == 'pickTreasure'){
                dojo.query('.space').style('display', 'block');
                this.pickTreasure = true;
            }


            dojo.destroy('my_side_bar');
            dojo.place( this.format_block('jstpl_my_side', {}), 'right-side-first-part');
            dojo.place( this.format_block('jstpl_bag', {}), 'my_side_bar');
            this.updateBagCounter(gamedatas.gamestate.updateGameProgression);

            dojo.place( this.format_block('jstpl_force_resize', {}), 'my_side_bar');
            dojo.query('#size_decrease').connect('onclick', this, 'onSizeDecrease');
            dojo.query('#force_resize').connect('onclick', this, 'onSizeReset');
            dojo.query('#size_increase').connect('onclick', this, 'onSizeIncrease');

            if(gamedatas.game_board == 2){
                dojo.addClass('board', 'alt_board');
            } else {
                dojo.addClass('board', 'standard_board');
            }

            this.stateName = gamedatas.gamestate.name;

            this.points = gamedatas.points;

            this.updatePlayerStatus(gamedatas.player_status);
 
            // Setup game notifications to handle (see "setupNotifications" method below)
            this.setupNotifications();

            this.setupPreference();

            console.log( "Ending game setup" );
        },

        setupPreference: function () {
            // Extract the ID and value from the UI control
            var _this = this;
            function onchange(e) {
                var match = e.target.id.match(/^preference_[cf]ontrol_(\d+)$/);
                if (!match) {
                    return;
                }
                var prefId = +match[1];
                var prefValue = +e.target.value;
                _this.prefs[prefId].value = prefValue;
                _this.onPreferenceChange(prefId, prefValue);
            }

            // Call onPreferenceChange() when any value changes
            dojo.query(".preference_control").connect("onchange", onchange);

            // Call onPreferenceChange() now
            dojo.forEach(
                dojo.query("#ingame_menu_content .preference_control"),
                function (el) {
                    onchange({ target: el });
                }
            );
        },

        onPreferenceChange: function (prefId, prefValue) {
            console.log("Preference changed", prefId, prefValue);
            // your code here to handle the change
            if(prefId == 104){
                this.skipButton = false;
                this.buttonTime = 3;
                if(prefValue == 2){
                    this.buttonTime = 10;
                }
                if(prefValue == 3){
                    this.buttonTime = 30;
                }
                if(prefValue == 4){
                    this.skipButton = true;
                }
            }
        },

        setLoader(value, max) {
            this.inherited(arguments);
            if (!this.isLoadingComplete && value >= 100) {
                this.isLoadingComplete = true;
                this.onLoadingComplete();
            }
        },

        onLoadingComplete() {
            this.preferredHeight = null;
            this.updateTooltips();
            this.onScreenWidthChange();
        },

        updateTooltips(){
            this.addTooltipToClass('hand_leader_black', _('King, scores black points for black tiles placed in kingdom, also scores points for absent leaders'), '', 500);
            this.addTooltipToClass('hand_leader_green', _('Trader, scores green points for green tiles placed in kingdom, also picks up treasures when two are present in kingdom'), '', 500);
            this.addTooltipToClass('hand_leader_red', _('Priest, scores red points for red tiles placed in kingdom'), '', 500);
            this.addTooltipToClass('hand_leader_blue', _('Farmer, scores blue points for blue tiles placed in kingdom'), '', 500);
            this.addTooltipToClass('mini_tile_catastrophe', _('Catastrophe, destroys tile, cannot be part of kingdom'), '', 500);
            this.addTooltipToClass('mini_tile_blue', _('Farm, when placed in kingdom Farmer will score blue point. Can only be played on rivers.'), '', 500);
            this.addTooltipToClass('mini_tile_black', _('Settlement, when placed in kingdom King will score black point'), '', 500);
            this.addTooltipToClass('mini_tile_green', _('Market, when placed in kingdom Trader will score green point'), '', 500);
            this.addTooltipToClass('mini_tile_red', _('Temple, when placed in kingdom Priest will score red point, leaders must be adjacent to a temple at all times'), '', 500);
            this.addTooltipToClass('mini_monument', _("Monument, active player's matching leaders score point at end of turn"), '', 500);
            this.addTooltipToClass('hand_catastrophe', _('Remaining catastrohpes'), '');
            this.addTooltipToClass('hand_tile_count', _('Tiles in hand'), '');
            this.addTooltipToClass('red_point', _('Red points'), '');
            this.addTooltipToClass('blue_point', _('Blue points'), '');
            this.addTooltipToClass('black_point', _('Black points'), '');
            this.addTooltipToClass('green_point', _('Green points'), '');
            this.addTooltipToClass('treasure_point', _('Treasure, wild points'), '');
            this.addTooltipToClass('treasure', _('Treasure, when a kingdom joins 2 treasures, the Trader will collect 1. Counts as wild point'), '', 500);
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
            dojo.byId("attacker_strength").innerHTML = (parseInt(args.attacker_hand_strength) || 0) + (parseInt(args.attacker_board_strength) || 0);
            dojo.byId("defender_strength").innerHTML = (parseInt(args.defender_hand_strength) || 0) + (parseInt(args.defender_board_strength) || 0);
            this.onScreenWidthChange();
        },

        getKindName: function(kind){
            switch(kind){
                case 'red':
                    return 'Priest';
                    break;
                case 'black':
                    return 'King';
                    break;
                case 'blue':
                    return 'Farmer';
                    break;
                case 'green':
                    return 'Trader';
                    break;
            }
            return 'Error';
        },
        
        onEnteringState: function( stateName, args ){
            console.log( 'Entering state: '+stateName );

            this.stateName = stateName;
            this.stateArgs = args;
            if('args' in args && args.args !== null && 'kingdoms' in args.args){
                this.addKingdoms(args.args.kingdoms);
            }

            if('args' in args && args.args !== null && 'player_status' in args.args){
                this.updatePlayerStatus(args.args.player_status);
            }

            if('args' in args && args.args !== null && 'leader_strengths' in args.args){
                for(let leader of args.args.leader_strengths){
                    let kind_name = this.getKindName(leader.kind);
                    this.addTooltip( `leader_${leader.id}`, _(`${leader.owner} ${kind_name}: ${leader.strength} board strength`), '', 500 );
                    dojo.byId(`leader_${leader.id}_strength`).innerHTML = leader.strength;
                }
            }

            if('updateGameProgression' in args){
                this.updateBagCounter(args.updateGameProgression);
            }

            this.resetStatePotentialMoves();
            
            switch( stateName )
            {
            case 'pickTreasure':
                this.pickTreasure = true;
                break;
            case 'supportRevolt':
                this.placeConflictStatus(args.args, 'Revolt');
                break;
            case 'supportWar':
                this.placeConflictStatus(args.args, 'War');
                break;
            case 'warLeader':
                //dojo.destroy('conflict_status');
                break;
            case 'playerTurn':
                //dojo.destroy('conflict_status');

                let selected = dojo.query('.selected');
                if(selected.length > 0){
                   dojo.query('.space').style('display', 'block');
                } else {
                   dojo.query('.space').style('display', 'none');
                }
                this.onScreenWidthChange();
                break;
            case 'buildMonument':
                this.passConfirm = false;
                break;
            case 'multiMonument':
                dojo.query('.space').style('display', 'block');
                this.selectMonumentTile = true;
                break;
            case 'dummmy':
                break;
            }
        },

        onLeavingState: function( stateName ){
            console.log( 'Leaving state: '+stateName );
            
            switch( stateName )
            {
            case 'pickTreasure':
                dojo.query('.space').style('display', 'none');
                this.pickTreasure = false;
                break;
            case 'multiMonument':
                dojo.query('.space').style('display', 'none');
                this.selectMonumentTile = false;
                break;
            case 'dummmy':
                break;
            }               
        }, 

        onUpdateActionButtons: function( stateName, args ){
                      
            if( this.isCurrentPlayerActive() ){            
                if(args.can_undo){
                    this.addActionButton( 'start_undo', _('Undo'), 'onUndoClick', null, false, 'red' ); 
                }
                switch( stateName )
                {

                case 'playerTurn':
                    this.addActionButton( 'pickup_leader', _('Pickup Leader'), 'onPickupLeaderClick' ); 
                    this.addActionButton( 'start_discard', _('Start Discard'), 'onDiscardClick' ); 
                    this.addActionButton( 'send_pass', _('Pass'), 'sendPassClick' );
                    break;

                case 'supportRevolt':
                    this.addActionButton( 'send_support', _('Send 0 Revolt (red) Support'), 'sendSupportClick' ); 
                    break;

                case 'supportWar':
                    this.addActionButton( 'send_support', _('Send 0 War Support'), 'sendSupportClick' ); 
                    break;

                case 'buildMonument':
                    this.addActionButton( 'send_pass', _('Pass'), 'sendPassClick' );
                    break;

                case 'pickTreasure':
                    break;

                case 'warLeader':
                    break;

                case 'endTurnConfirm':
                    this.addActionButton( 'send_confirm', _('Confirm Turn'), 'sendConfirmClick' );
                    if(this.skipButton == false){
                        this.startActionTimer('send_confirm', this.buttonTime, null, true);
                    }
                    break;
                }
            }
        },

        resetStatePotentialMoves(){
            dojo.query('.tae_possible_move').removeClass('tae_possible_move');
            dojo.query('.tae_possible_space').removeClass('tae_possible_space');
            this.multiselect = false;
            
            switch( this.stateName )
            {
            case 'pickTreasure':
                dojo.query('.space').style('display', 'block');
                if(this.isCurrentPlayerActive()){
                    for(let treasure_id of this.stateArgs.args.valid_treasures){
                        dojo.query(`#treasure_${treasure_id} .treasure_inner`).addClass('tae_possible_move');
                    }
                }
                break;
            case 'supportRevolt':
                if(this.isCurrentPlayerActive()){
                    this.multiselect = true;
                    dojo.query('#hand_tiles .mini_tile_red').addClass('tae_possible_move');
                }
                break;
            case 'supportWar':
                if(this.isCurrentPlayerActive()){
                    this.multiselect = true;
                    dojo.query(`#hand_tiles .mini_tile_${this.stateArgs.args.attacker.kind}`).addClass('tae_possible_move');
                }
                break;
            case 'warLeader':
                if(this.isCurrentPlayerActive()){
                    for(let id of this.stateArgs.args.potential_leaders){
                        dojo.addClass(`leader_${id}`, 'tae_possible_move');
                    }
                }
                break;
            case 'playerTurn':
                if(this.isCurrentPlayerActive()){
                    dojo.query('.space').style('display', 'none');
                    dojo.addClass('pickup_leader', 'disabled');
                    dojo.removeClass('start_discard', 'disabled');
                    dojo.query('#hand_leaders .mini_leader_token').addClass('tae_possible_move');
                    dojo.query('#hand_catastrophes .mini_tile').addClass('tae_possible_move');
                    dojo.query('#hand_tiles .mini_tile').addClass('tae_possible_move');
                    dojo.query(`#tiles .leader_${this.stateArgs.args.player_shape}`).parent().addClass('tae_possible_move');
                }

                break;
            case 'buildMonument':
                if(this.isCurrentPlayerActive()){
                    dojo.query('.mini_monument_lower').addClass('tae_possible_move');
                }
                break;
            case 'multiMonument':
                dojo.query('.space').style('display', 'block');
                break;
            case 'dummmy':
                break;
            }
        },

        startActionTimer(buttonId, time, pref, autoclick = false) {
          var button = dojo.byId(buttonId);
          if (button == null || pref == 2) {
            return;
          }

          // If confirm disabled, click on button
          if (pref == 0) {
            if (autoclick) button.click();
            return;
          }

          this._actionTimerLabel = button.innerHTML;
          this._actionTimerSeconds = time;
          this._actionTimerFunction = () => {
            var button = dojo.byId(buttonId);
            if (button == null) {
              this.stopActionTimer();
            } else if (this._actionTimerSeconds-- > 1) {
              button.innerHTML = this._actionTimerLabel + ' (' + this._actionTimerSeconds + ')';
            } else {
              button.click();
            }
          };
          this._actionTimerFunction();
          this._actionTimerId = window.setInterval(this._actionTimerFunction, 1000);
        },

        stopActionTimer() {
          if (this._actionTimerId != null) {
            window.clearInterval(this._actionTimerId);
            delete this._actionTimerId;
          }
        },

        onScreenWidthChange: function(){
            let m = this.getMargins();
            this.scaleGameArea(m);
            this.scaleBoard(m);
            this.scaleTiles(m);
            this.scaleLeaders(m);
            this.scaleMonuments(m);
            this.scaleTreasures(m);
        },

        scaleGameArea: function(m){
            if(m.column_mode){
                // remove height style
                dojo.style('my_game_area', 'height', null);
            } else {
                dojo.style('my_game_area', 'height', m.game_area_height+'px');
            }
        },

        scaleBoard: function(m){
            dojo.style('board', 'width', toint(m.board_width)+'px');
            dojo.style('board', 'background-size', toint(m.board_width)+'px');
            dojo.style('board', 'height', toint(m.board_height)+'px');
            dojo.style('overlay', 'width', toint(m.board_width)+'px');
            dojo.style('overlay', 'background-size', toint(m.board_width)+'px');
            dojo.style('overlay', 'height', toint(m.board_height)+'px');
            this.addStyleToClass('kingdom', 'width', toint(m.tile_size)+'px');
            this.addStyleToClass('kingdom', 'height', toint(m.tile_size)+'px');
            this.addStyleToClass('space', 'width', toint(m.tile_size)+'px');
            this.addStyleToClass('space', 'height', toint(m.tile_size)+'px');
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
        },

        scaleTiles: function(m){
            this.addStyleToClass('tile', 'width', toint(m.reduced_tile_size)+'px');
            this.addStyleToClass('tile', 'height', toint(m.reduced_tile_size)+'px');
            this.addStyleToClass('tile', 'backgroundSize', toint(7 * m.reduced_tile_size)+'px');
            this.addStyleToClass('tile_flipped', 'backgroundPosition', '0px, 0px');
            this.addStyleToClass('tile_black', 'backgroundPosition', '-'+toint(1 * m.reduced_tile_size)+'px, 0px');
            this.addStyleToClass('tile_catastrophe', 'backgroundPosition', '-'+toint(2 * m.reduced_tile_size)+'px, 0px');
            this.addStyleToClass('tile_green', 'backgroundPosition', '-'+toint(3 * m.reduced_tile_size)+'px, 0px');
            this.addStyleToClass('tile_red', 'backgroundPosition', '-'+toint(4 * m.reduced_tile_size)+'px, 0px');
            this.addStyleToClass('tile_union', 'backgroundPosition', '-'+toint(5 * m.reduced_tile_size)+'px, 0px');
            this.addStyleToClass('tile_blue', 'backgroundPosition', '-'+toint(6 * m.reduced_tile_size)+'px, 0px');
            dojo.query('#board .tile').forEach(function(tile){
                let x = toint(tile.dataset.x);
                let y = toint(tile.dataset.y);
                let left = (x * m.tile_size) + m.margin_width + toint(m.tile_padding / 2);
                let top = (y * m.tile_size) + m.margin_height + toint(m.tile_padding / 2);
                dojo.style(tile.id, 'top', toint(top)+'px');
                dojo.style(tile.id, 'left', toint(left)+'px');
            });
        },

        scaleLeaders: function(m){
            this.addStyleToClass('leader_token', 'height', toint(m.reduced_tile_size)+'px');
            this.addStyleToClass('leader_token', 'width', toint(m.reduced_tile_size)+'px');
            this.addStyleToClass('leader', 'height', toint(m.reduced_tile_size)+'px');
            this.addStyleToClass('leader', 'width', toint(m.reduced_tile_size)+'px');
            this.addStyleToClass('leader', 'backgroundSize', toint(4 * m.reduced_tile_size)+'px');
            this.addStyleToClass('leader_bow', 'backgroundPosition', '0px, 0px');
            this.addStyleToClass('leader_bull', 'backgroundPosition', '-'+toint(1 * m.reduced_tile_size)+'px, 0px');
            this.addStyleToClass('leader_lion', 'backgroundPosition', '-'+toint(2 * m.reduced_tile_size)+'px, 0px');
            this.addStyleToClass('leader_urn', 'backgroundPosition', '-'+toint(3 * m.reduced_tile_size)+'px, 0px');
            dojo.query('#tiles .leader_token').forEach(function(leader){
                let x = toint(leader.dataset.x);
                let y = toint(leader.dataset.y);
                let left = (x * m.tile_size) + m.margin_width + toint(m.tile_padding / 2);
                let top = (y * m.tile_size) + m.margin_height + toint(m.tile_padding / 2);
                dojo.style(leader.id, 'top', toint(top)+'px');
                dojo.style(leader.id, 'left', toint(left)+'px');
            });
        },

        scaleMonuments: function(m){
            this.addStyleToClass('monument', 'width', toint(m.tile_size * 2)+'px');
            this.addStyleToClass('monument', 'height', toint(m.tile_size * 2)+'px');
            this.addStyleToClass('monument_lower', 'width', toint(m.tile_size)+'px');
            this.addStyleToClass('monument_lower', 'height', toint(m.tile_size)+'px');
            this.addStyleToClass('monument_upper', 'width', toint(m.tile_size/2)+'px');
            this.addStyleToClass('monument_upper', 'height', toint(m.tile_size/2)+'px');
            dojo.query('#monuments .monument').forEach(function(monument){
                let x = toint(monument.dataset.x);
                let y = toint(monument.dataset.y);
                let left = (x * m.tile_size) + m.margin_width;
                let top = (y * m.tile_size) + m.margin_height;
                dojo.style(monument.id, 'top', toint(top)+'px');
                dojo.style(monument.id, 'left', toint(left)+'px');
            });
        },

        scaleTreasures: function(m){
            this.addStyleToClass('treasure', 'width', toint(m.tile_size)+'px');
            this.addStyleToClass('treasure', 'height', toint(m.tile_size)+'px');
            dojo.query('#treasures .treasure').forEach(function(treasure){
                let x = toint(treasure.dataset.x);
                let y = toint(treasure.dataset.y);
                let left = (x * m.tile_size) + m.margin_width;
                let top = (y * m.tile_size) + m.margin_height;
                dojo.style(treasure.id, 'top', toint(top)+'px');
                dojo.style(treasure.id, 'left', toint(left)+'px');
            });
        },

        ///////////////////////////////////////////////////
        //// Utility methods

        addKingdoms: function(kingdoms){
            for(let i = 1; i <= 16; i++){
                dojo.query(`.kingdom_${i}`).removeClass(`kingdom_${i}`);
            }
            let i = 0;
            for(let kingdom of kingdoms){
                i += 1;
                for(let pos of kingdom){
                    dojo.addClass(`kingdom_${pos[0]}_${pos[1]}`, `kingdom_${i}`);
                }
            }
        },

        getMargins: function(){
            let board_width = 3304;
            let board_height = 2415;
            let margin_width = 52;
            let margin_height = 104;
            let tile_size = 200;

            let gamePlayArea = dojo.byId('my_game_area');
            let rect = gamePlayArea.getBoundingClientRect();
            let window_height = window.innerHeight;
            let window_width = window.innerWidth;
            let boardCenter = dojo.byId('board_center');
            let board_rect = boardCenter.getBoundingClientRect();

            let game_area_height = window_height - rect.top;

            if(this.preferredHeight !== null){
                game_area_height = this.preferredHeight;
            }

            let target_height = game_area_height;
            let target_ratio = target_height / board_height;
            let target_width = target_ratio * board_width;


            let column_mode = window_width < 1300;

            // account for smaller screens
            if(window_width < 1300 || target_height < 539){
                target_height = 539;
                target_ratio = target_height / board_height;
                target_width = target_ratio * board_width;
            // account for tall screens
            } else if(target_width > rect.width - 200){
                target_width = rect.width - 200;
                target_ratio = target_width / board_width;
                target_height = target_ratio * board_height;
            }

            if(this.preferredHeight == null && target_height > 0){
                this.preferredHeight = target_height;
            }

            let scaled_tile = tile_size * target_ratio;
            let tile_padding = toint(scaled_tile * .1);
            // tile padding needs to be even, and should increase to get there
            if(tile_padding % 2 != 0){
                tile_padding += 1;
            }

            let target_margin_width = margin_width * target_ratio;
            let target_margin_height = margin_height * target_ratio;

            if(board_rect.height > target_height){
                game_area_height = board_rect.height;
            } else {
                game_area_height = target_height;
            }

            return {
                tile_size: scaled_tile,
                board_width: target_width,
                board_height: target_height,
                margin_width: target_margin_width,
                margin_height: target_margin_height,
                target_ratio: target_ratio,
                tile_padding: tile_padding,
                game_area_height: game_area_height,
                column_mode: column_mode,
                reduced_tile_size: scaled_tile - tile_padding
            }
        },

        getLeftTop: function(x, y){
            let m = this.getMargins();

            let left = (x * m.tile_size) + m.margin_width;
            let top = (y * m.tile_size) + m.margin_height;

            return {left: toint(left), top: toint(top)};
        },

        addMonumentOnBoard: function(x, y, id, color1, color2, animate=false){
            dojo.destroy(`monument_${id}`);
            let ix = parseInt(x);
            let iy = parseInt(y);
            let m = this.getMargins();
            let left = (x * m.tile_size) + m.margin_width;
            let top = (y * m.tile_size) + m.margin_height;
            dojo.place( this.format_block( 'jstpl_monument', {
                        id: id,
                        color1: color1,
                        color2: color2,
                        position: 'absolute',
                        left: left,
                        top: top,
                        x: ix,
                        y: iy
                    }), 'monuments' );
            this.scaleMonuments(m);
            let tile_id = this.board_tiles[ix][iy];
            dojo.addClass(`tile_${tile_id}`, 'rotate_top_left');
            tile_id = this.board_tiles[ix + 1][iy];
            dojo.addClass(`tile_${tile_id}`, 'rotate_top_right');
            tile_id = this.board_tiles[ix][iy + 1];
            dojo.addClass(`tile_${tile_id}`, 'rotate_bottom_left');
            if(animate){
                this.placeOnObject( `monument_${id}`, 'unbuilt_monuments' );
                this.slideToObjectPos(`monument_${id}`, 'monuments', left, top).play();
            }
        },
        
        addTokenOnBoard: function(x, y, color, id, owner, animate=false){
            let ix = parseInt(x);
            let iy = parseInt(y);
            this.board_tiles[ix][iy] = id;
            let my_tile = dojo.query(`#tile_${id}`).length > 0;
            dojo.destroy(`tile_${id}`);
            let m = this.getMargins();
            let left = (x * m.tile_size) + m.margin_width + toint(m.tile_padding/2);
            let top = (y * m.tile_size) + m.margin_height + toint(m.tile_padding/2);

            dojo.place( this.format_block( 'jstpl_tile', {
                color: color,
                left: left,
                top: top,
                id: id,
                x: x,
                y: y
            }), 'tiles' );
            this.onScreenWidthChange();
            if(animate){
                if(my_tile){
                    if(color == 'catastrophe'){
                        this.placeOnObject( `tile_${id}`, 'hand_catastrophes' );
                    } else {
                        this.placeOnObject( `tile_${id}`, 'hand_tiles' );
                    }
                } else {
                    this.placeOnObject( `tile_${id}`, `player_board_${owner}` );
                }
                this.slideToObjectPos(`tile_${id}`, 'tiles', left, top).play();
            }
        },
        
        addLeaderOnBoard: function(x, y, shape, kind, id, owner, moved=false, animate=false){
            let my_leader = dojo.query(`#leader_${id}`).length > 0;
            let m = this.getMargins();
            let left = (x * m.tile_size) + m.margin_width + toint(m.tile_padding/2);
            let top = (y * m.tile_size) + m.margin_height + toint(m.tile_padding/2);
            if(moved == false){
                dojo.destroy(`leader_${id}`);
                dojo.place( this.format_block( 'jstpl_leader', {
                    color: kind,
                    id: id,
                    shape: shape,
                    left: left,
                    top: top,
                    x: x,
                    y: y
                }), 'tiles' );
                this.onScreenWidthChange();
            } else {
                dojo.style(`leader_${id}`, 'left', left);
                dojo.style(`leader_${id}`, 'top', top);
                dojo.attr(`leader_${id}`, 'data-x', x);
                dojo.attr(`leader_${id}`, 'data-y', y);
            }
            if(animate){
                if(!moved){
                    if(my_leader){
                        this.placeOnObject( `leader_${id}`, 'hand_leaders' );
                    } else {
                        this.placeOnObject( `leader_${id}`, `player_board_${owner}` );
                    }
                }
                this.slideToObjectPos(`leader_${id}`, 'tiles', left, top).play();
            }
            if(!moved){
                dojo.query(`#leader_${id}`).connect('onclick', this, 'onLeaderClick');
            }
        },

        clearSelection: function(){
            dojo.query('.selected').removeClass('selected');
            dojo.query('.space').style('display', 'none');
        },

        calculateScore: function(points){
            let wilds = points.treasure;
            let point_arr = [parseInt(points.red), parseInt(points.black), parseInt(points.blue), parseInt(points.green)];
            while(wilds > 0){
                let low_index = -1;
                let lowest = 9999;
                for(let i in point_arr){
                    if(point_arr[i] < lowest){
                        lowest = point_arr[i];
                        low_index = i;
                    }
                }
                point_arr[low_index] += 1;
                wilds -= 1;
            }
            let lowest = 9999;
            for(let v of point_arr){
                if(v < lowest){
                    lowest = v;
                }
            }
            return lowest;
        },

        updatePoints: function(){
            if(this.points == undefined) {
                return;
            }
            for(let player_id of Object.keys(this.points)){
                let points = this.points[player_id];
                dojo.destroy(`points_${player_id}`);
                dojo.place( this.format_block( 'jstpl_points', {
                    player_id: player_id,
                    red: points.red,
                    black: points.black,
                    blue: points.blue,
                    green: points.green,
                    treasure: points.treasure
                }),`player_board_${player_id}` );
            }
            for(let player_id of Object.keys(this.gamedatas.players)){
                if(player_id in this.points){
                    // update points
                    if(this.scoreCtrl[player_id] !== undefined){
                        this.scoreCtrl[player_id].setValue(this.calculateScore(this.points[player_id]));
                    }
                } else {
                    // put a ?
                    dojo.byId(`player_score_${player_id}`).innerHTML = '?';
                }

            }
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
            this.updatePoints();
        },

        updateBagCounter: function(progress){
            let real_prog = 100 - progress;

            $('tae_progress_percent').innerHTML = real_prog+"%";
            dojo.style('tae_progress_bar', 'width', real_prog+'%');
        },

        updatePotentialMoves: function(){
            dojo.query('.tae_possible_move').removeClass('tae_possible_move');
            dojo.query('.tae_possible_space').removeClass('tae_possible_space');
            switch( this.stateName )
            {
            case 'pickTreasure':
                dojo.query('.space').style('display', 'block');
                if(this.isCurrentPlayerActive()){
                    dojo.query('.treasure_inner').addClass('tae_possible_move');
                }
                break;
            case 'supportRevolt':
                if(this.isCurrentPlayerActive()){
                    this.multiselect = true;
                    dojo.query('#hand_tiles .mini_tile_red').addClass('tae_possible_move');
                }
                break;
            case 'supportWar':
                if(this.isCurrentPlayerActive()){
                    this.multiselect = true;
                    dojo.query(`#hand_tiles .mini_tile_${this.stateArgs.args.attacker.kind}`).addClass('tae_possible_move');
                }
                break;
            case 'warLeader':
                break;
            case 'playerTurn':
                if(this.isCurrentPlayerActive()){
                    if(this.multiselect){
                        dojo.query('#hand_tiles .mini_tile').addClass('tae_possible_move');
                        return;
                    }
                    let selected = dojo.query('.selected');
                    if(selected.length === 0){
                        this.resetStatePotentialMoves();
                        return;
                    }
                    selected = selected[0];
                    let selected_type = selected.id.split('_')[0];
                    if(selected_type === 'tile'){
                        dojo.query('.space').style('display', 'block');
                        dojo.query('.space').addClass('tae_possible_space');
                        if(dojo.hasClass(selected.id, 'mini_tile_blue')){
                            dojo.query('.tae_possible_space').removeClass('tae_possible_space');
                            dojo.query('.river').addClass('tae_possible_space');
                        } else {
                            dojo.query('.river').removeClass('tae_possible_space');
                        }
                        for(let spot of dojo.query('#tiles > div')){
                            let x = spot.dataset.x;
                            let y = spot.dataset.y;
                            dojo.removeClass(`space_${x}_${y}`, 'tae_possible_space');
                        }
                        this.addTooltipToClass('tae_possible_space', '', _('Place tile on the board'), 500);
                        if(dojo.hasClass(selected.id, 'mini_tile_catastrophe')){
                            dojo.query('.tae_possible_space').removeClass('tae_possible_space');
                            dojo.query('.space').addClass('tae_possible_space');
                            for(let spot of dojo.query('#tiles .leader_token')){
                                let x = spot.dataset.x;
                                let y = spot.dataset.y;
                                dojo.removeClass(`space_${x}_${y}`, 'tae_possible_space');
                            }
                            for(let spot of dojo.query('#treasures .treasure')){
                                let x = spot.dataset.x;
                                let y = spot.dataset.y;
                                dojo.removeClass(`space_${x}_${y}`, 'tae_possible_space');
                            }
                            for(let spot of dojo.query('#monuments .monument')){
                                let x = parseInt(spot.dataset.x);
                                let y = parseInt(spot.dataset.y);
                                dojo.removeClass(`space_${x}_${y}`, 'tae_possible_space');
                                dojo.removeClass(`space_${(x+1)}_${(y+1)}`, 'tae_possible_space');
                                dojo.removeClass(`space_${x}_${(y+1)}`, 'tae_possible_space');
                                dojo.removeClass(`space_${(x+1)}_${y}`, 'tae_possible_space');
                            }
                            this.addTooltipToClass('tae_possible_space', '', _('Place catastrophe over this tile'), 500);
                        }
                        return;
                    }
                    if(selected_type === 'leader'){
                        dojo.query('.space').style('display', 'block');
                        for(let spot of dojo.query('#tiles .tile_red')){
                            let x = parseInt(spot.dataset.x);
                            let y = parseInt(spot.dataset.y);
                            if(dojo.query(`#space_${(x+1)}_${y}`).length > 0){
                                dojo.addClass(`space_${(x+1)}_${y}`, 'tae_possible_space');
                            }
                            if(dojo.query(`#space_${(x-1)}_${y}`).length > 0){
                                dojo.addClass(`space_${(x-1)}_${y}`, 'tae_possible_space');
                            }
                            if(dojo.query(`#space_${x}_${(y+1)}`).length > 0){
                                dojo.addClass(`space_${x}_${(y+1)}`, 'tae_possible_space');
                            }
                            if(dojo.query(`#space_${x}_${(y-1)}`).length > 0){
                                dojo.addClass(`space_${x}_${(y-1)}`, 'tae_possible_space');
                            }
                        }
                        dojo.query('.river').removeClass('tae_possible_space');
                        for(let spot of dojo.query('#tiles > div')){
                            let x = spot.dataset.x;
                            let y = spot.dataset.y;
                            dojo.removeClass(`space_${x}_${y}`, 'tae_possible_space');
                        }
                        this.addTooltipToClass('tae_possible_space', '', _('Place leader in this kingdom'), 500);
                        return;
                    }
                }

                break;
            case 'buildMonument':
                if(this.isCurrentPlayerActive()){
                    dojo.query('.mini_monument_lower').addClass('tae_possible_move');
                }
                break;
            case 'multiMonument':
                dojo.query('.space').style('display', 'block');
                break;
            case 'dummmy':
                break;
            }

        },


        ///////////////////////////////////////////////////
        //// Player's action

        onSizeReset: function( evt ){
            this.preferredHeight = null;
            this.onScreenWidthChange();
        },

        onSizeIncrease: function( evt ){
            this.preferredHeight += 50;
            this.onScreenWidthChange();
        },

        onSizeDecrease: function( evt ){
            this.preferredHeight -= 50;
            this.onScreenWidthChange();
        },

        onSpaceClick: function( evt ){
            dojo.stopEvent( evt );

            let coords = evt.currentTarget.id.split('_');
            let x = coords[1];
            let y = coords[2];
            if(this.pickTreasure){
                if( this.checkAction( 'pickTreasure' ) )  {            
                    this.ajaxcall( "/tigriseuphrates/tigriseuphrates/pickTreasure.html", {
                        lock: true,
                        pos_x:x,
                        pos_y:y
                    }, this, function( result ) {} );
                }        
                return;
            }
            if(this.selectMonumentTile){
                if( this.checkAction( 'selectMonumentTile' ) )  {            
                    this.ajaxcall( "/tigriseuphrates/tigriseuphrates/selectMonumentTile.html", {
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
                if( this.checkAction( 'placeLeader' ) )  {            
                    let lx = selected[0].dataset.x;
                    let ly = selected[0].dataset.y;
                    if(lx == x && ly == y){
                        this.clearSelection();
                        this.resetStatePotentialMoves();
                        return;
                    }
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
            if(this.isCurrentPlayerActive() == false){
                return;
            }
            if(this.stateName == 'warLeader'){
                if(this.checkAction('selectWarLeader')){
                    let leader_id = evt.currentTarget.id.split('_')[1];
                    this.ajaxcall( "/tigriseuphrates/tigriseuphrates/selectWarLeader.html", {
                        lock: true,
                        leader_id:leader_id
                    }, this, function( result ) {} );
                }
            } else {
                dojo.toggleClass(evt.currentTarget.id, 'selected');
                dojo.query('.space').style('display', 'block');
                dojo.removeClass('pickup_leader', 'disabled');
                dojo.addClass('start_discard', 'disabled');
                this.updatePotentialMoves();
            }
        },

        onPickupLeaderClick: function( evt ){
            let selected = dojo.query('.selected');
            if(selected.length > 1){
                this.showMessage(_("You can only pickup 1 leader at a time."), "error");
                this.clearSelection();
                return;
            } else if(selected.length == 0){
                this.showMessage(_("Select the leader you want to pickup first."), "info");
                return
            }
            if(this.checkAction('pickupLeader')){
                let leader_id = selected[0].id.split('_')[1];
                this.ajaxcall( "/tigriseuphrates/tigriseuphrates/pickupLeader.html", {
                    lock: true,
                    leader_id:leader_id
                }, this, function( result ) {} );
                this.clearSelection();
            }
        },

        onMonumentClick: function( evt ){
            dojo.stopEvent(evt);
            if(this.checkAction('selectMonument')){
                this.passConfirm = false;
                $('send_pass').innerHTML = _("Pass");
                let monument_id = evt.currentTarget.id.split('_')[1];
                this.ajaxcall( "/tigriseuphrates/tigriseuphrates/selectMonument.html", {
                    lock: true,
                    monument_id:monument_id
                }, this, function( result ) {} );
            }
        },

        updateSupportButton(){
            let selectCount = dojo.query('.selected').length;
            if(this.stateName == 'supportRevolt'){
                $('send_support').innerHTML = _(`Send ${selectCount} Revolt (red) Support`);
            }
            if(this.stateName == 'supportWar'){
                $('send_support').innerHTML = _(`Send ${selectCount} War Support`);
            }
        },

        onHandClick: function( evt ){
            dojo.stopEvent( evt );
            if(dojo.hasClass(evt.currentTarget.id, 'selected')){
                dojo.toggleClass(evt.currentTarget.id, 'selected');
                this.updatePotentialMoves();
                this.updateSupportButton();
                return;
            }
            let selectCount = dojo.query('.selected').length;
            if(selectCount > 0 && this.multiselect === false){
                dojo.query('.selected').removeClass('selected');
            }
            this.updatePotentialMoves();
            if(dojo.hasClass(evt.currentTarget.id, 'tae_possible_move') || dojo.hasClass(evt.currentTarget.id, 'selected')){
                dojo.toggleClass(evt.currentTarget.id, 'selected');
            }
            this.updatePotentialMoves();
            this.updateSupportButton();
        },

        onDiscardClick: function( evt ){
            dojo.stopEvent(evt);
            this.checkAction('discard');
            this.multiselect = true;
            if(this.finishDiscard){
                this.sendDiscard();
            } else {
                $('start_discard').innerHTML = _("Confirm Discard");
                this.addActionButton( 'cancel_discard', _('Cancel Discard'), 'onCancelClick' ); 
                dojo.query('#hand_leaders .mini_leader_token').removeClass('tae_possible_move');
                dojo.query('#hand_catastrophes .mini_tile').removeClass('tae_possible_move');
                this.finishDiscard = true;
            }
        },

        onCancelClick: function( evt ){
            dojo.stopEvent(evt);
            this.multiselect = false;
            this.finishDiscard = false;
            $('start_discard').innerHTML = _("Start Discard");
            dojo.destroy('cancel_discard');
            dojo.query('.selected').removeClass('selected');
            this.resetStatePotentialMoves();
        },

        sendDiscard: function( evt ){
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
            this.finishDiscard = false;
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
            this.updateSupportButton();
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
            this.stopActionTimer();
            this.checkAction('undo');
            this.ajaxcall( "/tigriseuphrates/tigriseuphrates/undo.html", {lock: true}, this, function( result ) {} );
        },

        sendConfirmClick: function( evt ){
            dojo.stopEvent(evt);
            this.checkAction('confirm');
            this.ajaxcall( "/tigriseuphrates/tigriseuphrates/confirm.html", {lock: true}, this, function( result ) {} );
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
            dojo.subscribe( 'pickedTreasure', this, 'notif_pickedTreasure' );
            this.notifqueue.setSynchronous( 'pickedTreasure', 500 );
            dojo.subscribe( 'playerScore', this, 'notif_playerScore' );
            this.notifqueue.setSynchronous( 'playerScore', 500 );
            dojo.subscribe( 'placeMonument', this, 'notif_placeMonument' );
            dojo.subscribe( 'catastrophe', this, 'notif_catastrophe' );
            this.notifqueue.setSynchronous( 'catastrophe', 500 );
            dojo.subscribe( 'leaderReturned', this, 'notif_leaderReturned' );
            this.notifqueue.setSynchronous( 'leaderReturned', 500 );
            dojo.subscribe( 'startingFinalScores', this, 'notif_startingFinalScores' );
            this.notifqueue.setSynchronous( 'finalScores', 2000 );
            dojo.subscribe( 'finalScores', this, 'notif_finalScores' );
            this.notifqueue.setSynchronous( 'finalScores', 3000 );
            dojo.subscribe( 'tileReturned', this, 'notif_tileReturned' );
            this.notifqueue.setSynchronous( 'tileReturned', 500 );
            dojo.subscribe('leaderUnSelected', this, 'notif_leaderUnSelected');
            // Load production bug report handler
            dojo.subscribe("loadBug", this, function loadBug(n) {
                function fetchNextUrl() {
                    var url = n.args.urls.shift();
                    console.log("Fetching URL", url);
                    dojo.xhrGet({
                        url: url,
                        load: function (success) {
                            console.log("Success for URL", url, success);
                            if (n.args.urls.length > 0) {
                                fetchNextUrl();
                            } else {
                                console.log("Done, reloading page");
                                window.location.reload();
                            }
                        },
                    });
                }
                console.log("Notif: load bug", n.args);
                fetchNextUrl();
            });
        },  
        
        notif_placeTile: function( notif ){
            this.addTokenOnBoard(notif.args.x, notif.args.y, notif.args.color, notif.args.tile_id, notif.args.player_id, true);
            this.updateTooltips();
        },

        notif_placeLeader: function( notif ){
            if(notif.args.undo == true){
                dojo.destroy('conflict_status');
            }
            this.addLeaderOnBoard(notif.args.x, notif.args.y, notif.args.shape, notif.args.color, notif.args.leader_id, notif.args.player_id, notif.args.moved, true);
            this.updateTooltips();
        },

        notif_drawTiles: function( notif ){
            for(var tile of notif.args.tiles){
                dojo.place( this.format_block( 'jstpl_hand', {
                    color: tile.kind,
                    id: tile.id
                }), 'hand_tiles' );
                dojo.query(`#tile_${tile.id}`).connect('onclick', this, 'onHandClick');
            }
        },

        notif_discard: function( notif ){
            for(let tile_id of notif.args.tile_ids){
                dojo.destroy(`tile_${tile_id}`);
            }
        },

        notif_pickedTreasure: function( notif ){
            let target = `player_board_${notif.args.player_id}`
            if(notif.args.player_id in this.points){
                target = `${notif.args.player_id}_${notif.args.color}_point_target`;
                this.points[notif.args.player_id][notif.args.color] = 1 + toint(this.points[notif.args.player_id][notif.args.color]);
            }
            this.slideToObjectAndDestroy( `treasure_${notif.args.tile_id}`, target);
            this.updatePoints();
        },

        notif_placeSupport: function( notif ){
            for(let tile_id of notif.args.tile_ids){
                dojo.destroy(`tile_${tile_id}`);
                dojo.place( this.format_block( 'jstpl_hand', {
                    color: notif.args.kind,
                    id: tile_id
                }), `${notif.args.side}_hand_support` );
            }
            if(notif.args.side == 'attacker'){
                dojo.byId("attacker_strength").innerHTML = parseInt(dojo.byId("attacker_strength").innerHTML) + notif.args.number;
            } else {
                dojo.byId("defender_strength").innerHTML = parseInt(dojo.byId("defender_strength").innerHTML) + notif.args.number;
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
            let temp_point = this.format_block( 'jstpl_point', {
                    color: 'red',
                });
            let target = `player_board_${notif.args.winning_player_id}`
            if(notif.args.winning_player_id in this.points){
                target = `${notif.args.winning_player_id}_red_point_target`;
            }
            this.slideTemporaryObject(temp_point, 'board', `leader_${notif.args.loser_id}`, target, 500, 0);
            let anim_id = dojo.fadeOut({node:dojo.byId(`leader_${notif.args.loser_id}`), duration: 500, delay: 0});
            dojo.connect(anim_id, 'onEnd',
                dojo.hitch(
                    this,
                    'replaceLeader',
                    { losing_player_id: notif.args.losing_player_id,
                      color: notif.args.kind, 
                      loser_shape: notif.args.loser_shape,
                      loser_id: notif.args.loser_id }
                 )
            );
            anim_id.play();
            this.fadeOutAndDestroy('conflict_status', 500, 500);
        },

        replaceLeader(args){
            dojo.destroy(`leader_${args.loser_id}`);
            if(parseInt(this.player_id) == parseInt(args.losing_player_id)){
                dojo.place( this.format_block( 'jstpl_leader_hand', {
                        color: args.color,
                        id: args.loser_id,
                        shape: args.loser_shape
                    }), 'hand_leaders' );
                dojo.query(`#leader_${args.loser_id}`).connect('onclick', this, 'onHandLeaderClick');
            }
        },

        notif_warConcluded: function( notif ){
            const color = notif.args.kind;
            let delay = 0;
            let temp_point = this.format_block( 'jstpl_point', {
                    color: color,
                });
            if(notif.args.winning_side == 'attacker'){
                dojo.addClass('conflict_attacker', 'winner');
                dojo.addClass('conflict_defender', 'loser');
            } else {
                dojo.addClass('conflict_defender', 'winner');
                dojo.addClass('conflict_attacker', 'loser');
            }
            let target = `player_board_${notif.args.winning_player_id}`
            if(notif.args.winning_player_id in this.points){
                target = `${notif.args.winning_player_id}_${color}_point_target`;
            }
            this.slideTemporaryObject(temp_point, 'board', `leader_${notif.args.loser_id}`, target, 500, delay);
            let anim_id = dojo.fadeOut({node:dojo.byId(`leader_${notif.args.loser_id}`), duration: 500, delay: delay});
            delay += 500;
            dojo.connect(anim_id, 'onEnd',
                dojo.hitch(
                    this,
                    'replaceLeader',
                    { losing_player_id: notif.args.losing_player_id,
                      color: color, 
                      loser_shape: notif.args.loser_shape,
                      loser_id: notif.args.loser_id }
                 )
            );
            anim_id.play();
            for(let tile_id of notif.args.tiles_removed){
                this.fadeOutAndDestroy( `tile_${tile_id}`, 500, delay);
                delay += 500;
                this.slideTemporaryObject(temp_point, 'board', `tile_${tile_id}`, target, 500, delay);
            }
            this.fadeOutAndDestroy('conflict_status', 500, delay);
        },

        notif_allWarsEnded: function( notif ){
            dojo.destroy(`tile_${notif.args.tile_id}`);
            //dojo.destroy('conflict_status');
            this.addTokenOnBoard(notif.args.pos_x, notif.args.pos_y, notif.args.tile_color, notif.args.tile_id, notif.args.player_id);
        },

        notif_playerScore: function( notif ){
            if(notif.args.player_id in this.points){
                this.points[notif.args.player_id][notif.args.color] = toint(notif.args.points) + toint(this.points[notif.args.player_id][notif.args.color]);
            }
            if(notif.args.animate){
                let temp_point = this.format_block( 'jstpl_point', {
                        color: notif.args.color,
                    });
                let source = 'board';
                let target = `player_board_${notif.args.player_id}`
                if(notif.args.player_id in this.points){
                    target = `${notif.args.player_id}_${notif.args.color}_point_target`;
                }
                if(notif.args.source != false){
                    source = notif.args.source+'_'+notif.args.id;
                }
                if(toint(notif.args.points) < 0){
                    let t = source;
                    source = target;
                    target = t;
                }
                this.slideTemporaryObject( temp_point, 'board', source, target ).play();
            }
            this.updatePoints();
        },

        notif_placeMonument: function( notif ){
            this.addMonumentOnBoard(notif.args.pos_x, notif.args.pos_y, notif.args.monument_id, notif.args.color1, notif.args.color2, true);
            for(let tile_id of notif.args.flip_ids){
                dojo.removeClass(`tile_${tile_id}`, 'tile_red');
                dojo.removeClass(`tile_${tile_id}`, 'tile_black');
                dojo.removeClass(`tile_${tile_id}`, 'tile_blue');
                dojo.removeClass(`tile_${tile_id}`, 'tile_union');
                dojo.removeClass(`tile_${tile_id}`, 'tile_green');
                dojo.addClass(`tile_${tile_id}`, 'tile_flipped');
            }
            let m = this.getMargins();
            this.scaleMonuments(m);
            this.scaleTiles(m);
        },

        notif_catastrophe: function( notif ){
            if(notif.args.removed_tile){
                this.fadeOutAndDestroy('tile_'+notif.args.removed_tile.id);
            }
            let catastrophe = notif.args.catastrophe;
            for(let leader of notif.args.removed_leaders){
                dojo.destroy(`leader_${leader.id}`);
                if(this.player_id == leader.owner){
                    // add leader back to hand
                    dojo.place( this.format_block( 'jstpl_leader_hand', {
                            color: leader.kind,
                            id: leader.id,
                            shape: leader.shape
                        }), 'hand_leaders' );
                    dojo.query(`#leader_${leader.id}`).connect('onclick', this, 'onHandLeaderClick');
                }
            }
            this.addTokenOnBoard(catastrophe.posX, catastrophe.posY, 'catastrophe', catastrophe.id, notif.args.player_id, true);
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
                dojo.query(`#leader_${notif.args.leader.id}`).connect('onclick', this, 'onHandLeaderClick');
            }
            if(notif.args.undo === true){
                dojo.destroy('conflict_status');
            }
            this.updateTooltips();
        },

        notif_startingFinalScores: function( notif ){
            this.points = notif.args.points;
            this.updatePoints();
        },

        notif_finalScores: function( notif ){
            let points = notif.args.points;
            for(let player_id of Object.keys(points)){
                let point = points[player_id];
                this.scoreCtrl[player_id].setValue(point.score);
            }
        },

        notif_tileReturned: function( notif ){
            this.fadeOutAndDestroy(`tile_${notif.args.tile_id}`);
            dojo.destroy('conflict_status');
            this.updateTooltips();
        },

        notif_leaderUnSelected: function( notif ){
            dojo.destroy('conflict_status');
        },
   });             
});
