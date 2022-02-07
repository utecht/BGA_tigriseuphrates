<?php
error_reporting(E_ALL ^ E_DEPRECATED);
define("APP_GAMEMODULE_PATH", "./"); // include path to mocks, this defined "Table" and other classes
require_once '../tigriseuphrates.game.php'; // include real game class
use PHPUnit\Framework\TestCase;

class TigrisEuphratesTest extends TigrisEuphrates {

	function __construct() {
		parent::__construct();
		include '../material.inc.php';
		$this->resources = array();
	}
}

final class TETest extends TestCase {
	private static $te;
	public static function setUpBeforeClass(): void{
		self::$te = new TigrisEuphratesTest();
	}

	public static function makeTile($id, $color, $posX, $posY) {
		$c = null;
		$isUnion = '0';
		switch ($color) {
		case 'w':
			$c = 'union';
			$isUnion = '1';
			break;
		case 'f':
			$c = 'flipped';
			break;
		case 'c':
			$c = 'catastrophe';
			break;
		case 'g':
			$c = 'green';
			break;
		case 'u':
			$c = 'blue';
			break;
		case 'b':
			$c = 'black';
			break;
		}
		return [
			'id' => $id,
			'kind' => $c,
			'posX' => strval($posX),
			'posY' => strval($posY),
			'onBoard' => '1',
			'isUnion' => $isUnion,
		];
	}

	public static function makeLeader($id, $val, $posX, $posY) {
		$kind = $val[0];
		$color = $val[1];
		$c = null;
		switch ($color) {
		case 'w':
			$c = 'union';
			break;
		case 'f':
			$c = 'flipped';
			break;
		case 'c':
			$c = 'catastrophe';
			break;
		case 'g':
			$c = 'green';
			break;
		case 'u':
			$c = 'blue';
			break;
		case 'b':
			$c = 'black';
			break;
		}
		$k = null;
		$o = null;
		switch ($kind) {
		case 'b':
			$k = 'bull';
			$o = 1;
			break;
		case 'u':
			$k = 'urn';
			$o = 2;
			break;
		case 'o':
			$k = 'bow';
			$o = 3;
			break;
		case 'l':
			$k = 'lion';
			$o = 4;
			break;
		}
		return [
			'id' => $id,
			'color' => $c,
			'kind' => $k,
			'owner' => $o,
			'posX' => strval($posX),
			'posY' => strval($posY),
			'onBoard' => '1',
		];
	}

	public static function buildBoard($str) {
		$board = array();
		$id = 0;
		$x = 0;
		foreach (explode("\n", $str) as $row) {
			$y = 0;
			foreach (explode(" ", $row) as $val) {
				if ($val != '.') {
					$board[$id] = self::makeTile($id, $val, $x, $y);
					$id++;
				}
				$y++;
			}
			$x++;
		}
		return $board;
	}

	public static function buildLeaders($str) {
		$leaders = array();
		$id = 0;
		$x = 0;
		foreach (explode("\n", $str) as $row) {
			$y = 0;
			foreach (explode(" ", $row) as $val) {
				if ($val != '..') {
					$leaders[$id] = self::makeLeader($id, $val, $x, $y);
					$id++;
				}
				$y++;
			}
			$x++;
		}
		return $leaders;
	}

	public function testExists() {
		$this->assertInstanceOf(TigrisEuphratesTest::class, self::$te);
	}

	public function testCords() {
		$this->assertEquals('A1', self::$te::toCoords(0, 0));
	}

	public function testMakeTile() {
		$tile = self::makeTile(0, 'blue', 1, 2);
		$this->assertArrayHasKey('kind', $tile);
		$this->assertEquals('2', $tile['posY']);
		$this->assertEquals('1', $tile['onBoard']);

	}

	public function testBuildBoard() {
		$board = self::buildBoard(<<<'EOD'
			. b . .
			. b b .
			. . . .
			. . . .
			EOD);
		$tile = self::makeTile(0, 'b', 0, 1);
		$this->assertEquals($tile, $board[0]);
		$this->assertEquals(3, count($board));
	}

	public function testBuildLeaders() {
		$leaders = self::buildLeaders(<<<'EOD'
			.. .. .. bb
			.. .. .. rl
			EOD);
		$leader = self::makeLeader(0, 'bb', 0, 3);
		$this->assertEquals($leader, $leaders[0]);
		$this->assertEquals(2, count($leaders));
	}

	public function testFindKingdoms() {
		$board = self::buildBoard(<<<'EOD'
			. b . .
			. r b .
			. . . .
			. . r .
			EOD);
		$leaders = self::buildLeaders(<<<'EOD'
			.. .. .. ..
			bu .. .. ..
			.. .. .. ..
			.. .. .. rl
			EOD);
		$kingdoms = TigrisEuphratesTest::findKingdoms($board, $leaders);
		$this->assertEquals(2, count($kingdoms));
	}

	public function testInLine() {
		$board = self::buildBoard(<<<'EOD'
			. b . .
			. r b .
			b . b .
			. . b .
			EOD);
		$tile = self::makeTile(0, 'b', 1, 2);
		$this->assertEquals(true, TigrisEuphratesTest::inLine($board, $tile, 1, 2));
		$this->assertEquals(true, TigrisEuphratesTest::inLine($board, $tile, 2, 2));
		$this->assertEquals(false, TigrisEuphratesTest::inLine($board, $tile, 2, 0));
	}
}
