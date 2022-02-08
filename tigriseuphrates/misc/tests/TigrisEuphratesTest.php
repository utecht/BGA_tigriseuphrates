<?php
error_reporting(E_ALL ^ E_DEPRECATED);
define("APP_GAMEMODULE_PATH", "./tigriseuphrates/misc/"); // include path to mocks, this defined "Table" and other classes

use PHPUnit\Framework\TestCase;

class TigrisEuphratesMocker extends TigrisEuphrates {

	function __construct() {
		include 'tigriseuphrates/material.inc.php';
		parent::__construct();
		$this->resources = array();
	}

	function getGameStateValue($var) {
		if ($var == 'game_board') {
			return 1;
		}
		return 0;
	}
}

final class TigrisEuphratesTest extends TestCase {
	private static $te;
	public static function setUpBeforeClass(): void{
		self::$te = new TigrisEuphratesMocker();
	}

	public function testExists() {
		$this->assertInstanceOf(TigrisEuphratesMocker::class, self::$te);
	}

	public function testCords() {
		$this->assertEquals('A1', self::$te->toCoords(0, 0));
	}

	public function testDrawnFromBagPercent() {
		$starting_tiles = 153;
		$starting_temple_counts = [count(self::$te->starting_temples), count(self::$te->alt_starting_temples)];
		foreach ($starting_temple_counts as $starting_temple_count) {
			for ($player_count = 2; $player_count <= 4; $player_count++) {
				$in_bag = $starting_tiles - ($player_count * 6) - $starting_temple_count;
				// test no tiles drawn
				$this->assertEquals(0, self::$te->drawnFromBagPercent($player_count, $in_bag, $starting_temple_count));
				// test all tiles drawn
				$this->assertEquals(100, self::$te->drawnFromBagPercent($player_count, 0, $starting_temple_count));
				// test rounding down with 1 tile remaining
				$this->assertEquals(99, self::$te->drawnFromBagPercent($player_count, 1, $starting_temple_count));
				// test rounding down with -1 tile remaining
				$this->assertEquals(0, self::$te->drawnFromBagPercent($player_count, $in_bag - 1, $starting_temple_count));
				// test halfway
				$this->assertEquals(50, self::$te->drawnFromBagPercent($player_count, intval($in_bag / 2), $starting_temple_count));
				// test quarters
				$this->assertEquals(75, self::$te->drawnFromBagPercent($player_count, intval($in_bag / 4), $starting_temple_count));
				$this->assertEquals(25, self::$te->drawnFromBagPercent($player_count, intval(($in_bag / 4) * 3), $starting_temple_count));
			}
		}
	}
}
