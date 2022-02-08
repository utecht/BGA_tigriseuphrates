<?php
error_reporting(E_ALL ^ E_DEPRECATED);

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
