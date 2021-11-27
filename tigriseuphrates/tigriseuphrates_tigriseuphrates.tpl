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

<div id="support">
</div>

<div id="board">
    <div id="kingdoms">
    <!-- BEGIN kingdom -->
        <div id="kingdom_{X}_{Y}" class="kingdom" style="left: {LEFT}px; top: {TOP}px;"></div>
    <!-- END kingdom -->
    </div>
    <div id="tiles"></div>
    <div id="spaces">
    <!-- BEGIN space -->
        <div id="space_{X}_{Y}" class="space" style="left: {LEFT}px; top: {TOP}px;"></div>
    <!-- END space -->
    </div>
</div>



<script type="text/javascript">

// Javascript HTML templates

var jstpl_tile='<div class="tile tile_${color}" id="tile_${id}" style="position:absolute; left: ${left}px; top: ${top}px"></div>';
var jstpl_leader='<div class="leader leader_${shape} leader_${color}" id="leader_${id}" style="position:absolute; left: ${left}px; top: ${top}px"></div>';
var jstpl_hand='<div class="tile tile_${color}" id="tile_${id}"></div>';
var jstpl_leader_hand='<div class="leader leader_${shape} leader_${color}" id="leader_${id}"></div>';

</script>  

{OVERALL_GAME_FOOTER}
