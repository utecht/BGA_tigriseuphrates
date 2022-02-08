<?php
error_reporting(E_ALL ^ E_DEPRECATED);
define("APP_GAMEMODULE_PATH", "./"); // include path to mocks, this defined "Table" and other classes
require_once '../tigriseuphrates.game.php'; // include real game class

class TigrisEuphratesCheck extends TigrisEuphrates {

	function __construct() {
		parent::__construct();
		include '../material.inc.php';
		$this->resources = array();
	}
	// override methods here that access db and stuff

	function getGameStateValue($var) {
		if ($var == 'round') {
			return 3;
		}

		return 0;
	}
}

$t = new TigrisEuphratesCheck();

$a = [12, 10, 5, 4];
$b = [14, 7, 4, 4];
$c = [8, 5, 5, 4];
$d = [6, 5, 5, 4];

sort($a);
sort($b);
sort($c);
sort($d);

$points = [
	'a' => $a,
	'b' => $b,
	'c' => $c,
	'd' => $d,
];
asort($points);
var_dump($points);