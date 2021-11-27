<?php
error_reporting(E_ALL ^ E_DEPRECATED);
define( "APP_GAMEMODULE_PATH", "./" ); // include path to mocks, this defined "Table" and other classes
require_once ('../tigriseuphrates.game.php'); // include real game class

class TigrisEuphratesTest extends TigrisEuphrates {

    function __construct() {
        parent::__construct();
        include '../material.inc.php';
        $this->resources = array ();
    }
    // override methods here that access db and stuff
    
    function getGameStateValue($var) {
        if ($var=='round') return 3;
        return 0;
    }
}

$t = new TigrisEuphratesTest();

$a = array('21');
$a = array_diff($a, array('21'));
var_dump($a);

$tiles_to_remove = array("25", "28");
$b = "update tile set posX = NULL, posY = NULL, state = 'discard', isUnion = '0' where id in (".implode($tiles_to_remove, ',').")";
var_dump($b);

var_dump('1' == 1);
