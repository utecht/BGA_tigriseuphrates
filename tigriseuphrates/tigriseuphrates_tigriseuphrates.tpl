{OVERALL_GAME_HEADER}

<!-- 
--------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- TigrisEuphrates implementation : © Joseph Utecht <joseph@utecht.co>
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-------

    tigriseuphrates_tigriseuphrates.tpl
    
    This is the HTML template of your game.
    
    Everything you are writing in this file will be displayed in the HTML page of your game user interface,
    in the "main game zone" of the screen.
    
    You can use in this template:
    _ variables, with the format {MY_VARIABLE_ELEMENT}.
    _ HTML block, with the BEGIN/END format
    
    See your "view" PHP file to check how to set variables and control blocks
    
    Please REMOVE this comment before publishing your game on BGA
-->

<div id="my_game_area">
    <div id="handbox" class="whiteblock playerbox">
        <h2 style="text-align: center;margin-top:5px">Hand</h2>
        <div id="hand">
            <div id="hand_leaders">
            </div>
            <div id="hand_tiles">
            </div>
        </div>
    </div>

    <div id="board_center">
        <div id="support"></div>
        <div id="board">
            <div id="overlay"></div>
            <div id="kingdoms">
            <!-- BEGIN kingdom -->
                <div id="kingdom_{X}_{Y}" class="kingdom" style="left: {LEFT}px; top: {TOP}px;"></div>
            <!-- END kingdom -->
            </div>
            <div id="tiles"></div>
            <div id="monuments"></div>
            <div id="treasures"></div>
            <div id="spaces">
            <!-- BEGIN space -->
                <div id="space_{X}_{Y}" class="space {RIVER}" style="left: {LEFT}px; top: {TOP}px;"></div>
            <!-- END space -->
            </div>
        </div>
    </div>


    <div id="monumentbox" class="whiteblock playerbox">
        <h2 style="text-align: center;margin-top:5px">Unbuilt Monuments</h2>
        <div id="unbuilt_monuments"></div>
    </div>
</div>



<script type="text/javascript">

// Javascript HTML templates

var jstpl_tile='<div class="tile tile_${color}" id="tile_${id}" data-x="${x}" data-y="${y}" style="position:absolute; left: ${left}px; top: ${top}px"></div>';
var jstpl_hand='<div class="mini_tile mini_tile_${color}" id="tile_${id}"></div>';
var jstpl_tile_fake='<div class="mini_tile mini_tile_${color}"></div>';

var jstpl_leader='<div class="leader_token board_leader_${color}" id="leader_${id}" data-x="${x}" data-y="${y}" style="position:absolute; left: ${left}px; top: ${top}px"><div class="leader leader_${shape} leader_${color}"></div><span id="leader_${id}_strength" class="leader_strength"></span></div>';
var jstpl_leader_hand='<div class="mini_leader_token hand_leader_${color}" id="leader_${id}"><div class="mini_leader mini_leader_${shape} leader_${color}"></div></div>';

var jstpl_treasure='<div class="treasure" id="treasure_${id}" data-x="${x}" data-y="${y}" style="left: ${left}px; top: ${top}px"><div class="treasure_inner""></div></div>';
var jstpl_monument='<div id="monument_${id}" class="monument" data-x="${x}" data-y="${y}" style="position: ${position}; left: ${left}px; top: ${top}px"><div class="monument_lower monument_${color1}"><div class="monument_upper monument_${color2}"></div></div></div>';
var jstpl_mini_monument='<div id="monument_${id}" class="mini_monument"><div class="mini_monument_lower monument_${color1}"><div class="mini_monument_upper monument_${color2}"></div></div></div>';

var jstpl_player_status='<div id="player_status_${player_id}" class="my_player_status"><div class="mini_leader mini_leader_${player_shape} leader_black"></div><div class="flexy"><div id="${player_id}_catastrophe_count" class="mini_tile mini_tile_catastrophe hand_catastrophe"></div><span style="align-self:center">x ${catastrophe_count}</span></div><div class="flexy"><div id="${player_id}_hand_count" class="mini_tile mini_tile_flipped rotate_top_left hand_tile_count"></div><span style="align-self:center">x ${hand_count}</span></div></div>';
var jstpl_points='<div class="points" id="points_${player_id}"><div id="${player_id}_red_point_target" class="point red_point"></div><span>${red}</span><div id="${player_id}_black_point_target" class="point black_point"></div><span>${black}</span><div id="${player_id}_green_point_target" class="point green_point"></div><span>${green}</span><div id="${player_id}_blue_point_target" class="point blue_point"></div><span>${blue}</span><div id="${player_id}_treasure_point_target" class="point treasure_point"></div><span>${treasure}</span></div>';

var jstpl_point='<div class="point ${color}_point"></div>';

var jstpl_my_side='<div id="my_side_bar"></div>';
var jstpl_force_resize='<div id="size_buttons"><button class="toggle_button" id="size_decrease"><i class="fa fa-search-minus"></i></button><button class="toggle_button" id="force_resize">Reset Board Size</button><button class="toggle_button" id="size_increase"><i class="fa fa-search-plus"></i></button></div>';

var jstpl_bag='<div class="player-board" id="bag_progress"><h4 style="text-align:center;margin-top:5px">Tiles Remaining</h4><div id="tae_progress_back"><span id="tae_progress_percent">100%</span><div id="tae_progress_bar"></div></div></div>';

var jstpl_conflict_status='<div id="conflict_status" class="conflict_status whiteblock"><div class="conflict_sides"><div id="conflict_attacker" class="side conflict_attacker"><div class="side_name"><h4>Attacker</h4><h4 style="order:-1" id="attacker_strength"></h4><div class="mini_leader_token"><div class="mini_leader leader_${attacker_color} mini_leader_${attacker_shape}"></div></div></div><div><h4>Board Strength</h4><div id="attacker_board_support" class="board_support"></div></div><div><h4>Support</h4><div id="attacker_hand_support" class="hand_support"></div></div></div><h3>${conflict_type}</h3><div id="conflict_defender" class="side conflict_defender"><div class="side_name"><h4 style="order:-5">Defender</h4><h4 style="order:-3" id="defender_strength"></h4><div class="mini_leader_token"><div class="mini_leader leader_${defender_color} mini_leader_${defender_shape}"></div></div></div><div class="defender"><h4>Board Strength</h4><div id="defender_board_support" class="board_support"></div></div><div class="defender"><h4>Support</h4><div id="defender_hand_support" class="hand_support"></div></div></div></div></div>';

</script>  

{OVERALL_GAME_FOOTER}
