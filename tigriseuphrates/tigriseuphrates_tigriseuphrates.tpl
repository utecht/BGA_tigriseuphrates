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

<div id="hand">
</div>

<div id="support"></div>

<div id="board">
    <div id="kingdoms">
    <!-- BEGIN kingdom -->
        <div id="kingdom_{X}_{Y}" class="kingdom" style="left: {LEFT}px; top: {TOP}px;"></div>
    <!-- END kingdom -->
    </div>
    <div id="tiles"></div>
    <div id="monuments"></div>
    <div id="spaces">
    <!-- BEGIN space -->
        <div id="space_{X}_{Y}" class="space" style="left: {LEFT}px; top: {TOP}px;"></div>
    <!-- END space -->
    </div>
</div>

<div id="unbuilt_monuments"></div>



<script type="text/javascript">

// Javascript HTML templates

var jstpl_tile='<div class="tile tile_${color}" id="tile_${id}" style="position:absolute; left: ${left}px; top: ${top}px"></div>';
var jstpl_leader='<div class="leader_token" id="leader_${id}" style="position:absolute; left: ${left}px; top: ${top}px"><div class="leader leader_${shape} leader_${color}"></div></div>';
var jstpl_hand='<div class="tile tile_${color}" id="tile_${id}"></div>';
var jstpl_leader_hand='<div class="leader leader_${shape} leader_${color}" id="leader_${id}"></div>';

var jstpl_amulet='<div class="amulet" id="amulet_${id}"></div>';

var jstpl_monument='<div id="monument_${id}" class="monument monument_${color1}" style="position: ${position}; left: ${left}px; top: ${top}px"><div class="monument_upper monument_${color2}"></div></div>';

var jstpl_player_status='<div id="player_status_${player_id}" class="my_player_status"><div class="leader leader_${player_shape} leader_black"></div><div class="flexy"><div class="tile tile_catastrophe"></div><span style="align-self:center">x ${catastrophe_count}</span></div><div class="flexy"><div class="tile tile_flipped"></div><span style="align-self:center">x ${hand_count}</span></div></div>';
var jstpl_points='<div class="points" id="points_${player_id}"><div class="point red_point"></div><span>${red}</span><div class="point black_point"></div><span>${black}</span><div class="point green_point"></div><span>${green}</span><div class="point blue_point"></div><span>${blue}</span><div class="point amulet_point"></div><span>${amulet}</span></div>';

var jstpl_point='<div class="point ${color}_point"></div>';

</script>  

{OVERALL_GAME_FOOTER}
