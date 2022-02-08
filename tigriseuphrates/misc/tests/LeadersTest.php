<?php
error_reporting(E_ALL ^ E_DEPRECATED);
define("APP_GAMEMODULE_PATH", "./"); // include path to mocks, this defined "Table" and other classes
require_once '../tigriseuphrates.game.php'; // include real game class
require_once './utils.php';
use PHPUnit\Framework\TestCase;
use TAE\Managers\Leaders;
use TAE\Testing\Utils;

final class LeadersTest extends TestCase {

	public function testBuildLeaders() {
		$leaders = Utils::buildLeaders(<<<'EOD'
			.. .. .. bb
			.. .. .. rl
			EOD);
		$leader = Utils::makeLeader(0, 'bb', 0, 3);
		$this->assertEquals($leader, $leaders[0]);
		$this->assertEquals(2, count($leaders));
	}

	public function testCalculateBoardStrength() {
		$board = Utils::buildBoard(<<<'EOD'
			. b . .
			. r b .
			. . . .
			. . r .
			EOD);
		$leader = Utils::makeLeader(0, 'bb', 1, 0);
		$this->assertEquals(1, Leaders::calculateBoardStrength($leader, $board));
		$board = Utils::buildBoard(<<<'EOD'
			r b . .
			. r b .
			r . . .
			. . r .
			EOD);
		$this->assertEquals(3, Leaders::calculateBoardStrength($leader, $board));
	}

}
