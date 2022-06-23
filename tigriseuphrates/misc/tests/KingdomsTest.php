<?php
error_reporting(E_ALL ^ E_DEPRECATED);

use PHPUnit\Framework\TestCase;
use TAE\Managers\Kingdoms;
use TAE\Testing\Utils;

final class KingdomsTest extends TestCase {

	public function testFindKingdoms() {
		$board = Utils::buildBoard(<<<'EOD'
			. b . .
			. r b .
			. . . .
			. . r .
			EOD);
		$leaders = Utils::buildLeaders(<<<'EOD'
			.. .. .. ..
			bu .. .. ..
			.. .. .. ..
			.. .. .. rl
			EOD);
		$kingdoms = Kingdoms::findKingdoms($board, $leaders);
		$this->assertEquals(2, count($kingdoms));
	}

	public function testCalculateKingdomStrength() {
		$board = Utils::buildBoard(<<<'EOD'
			. b g .
			. r b .
			. . . .
			. . r .
			EOD);
		$leaders = Utils::buildLeaders(<<<'EOD'
			.. .. .. ..
			bb .. .. ..
			.. .. .. ..
			.. .. .. ..
			EOD);
		$kingdoms = Kingdoms::findKingdoms($board, $leaders);
		$leader = $kingdoms[0]['leaders'][0];
		$this->assertEquals(2, Kingdoms::calculateKingdomStrength($leader, $kingdoms));
	}

	public function testCountNeighboringKingdoms() {
		$board = Utils::buildBoard(<<<'EOD'
			. r r .
			. . . .
			. r . .
			. . r .
			EOD);
		$leaders = Utils::buildLeaders(<<<'EOD'
			.. .. .. ..
			.. .. bb ..
			ub .. .. ..
			.. .. .. rl
			EOD);
		$kingdoms = Kingdoms::findKingdoms($board, $leaders);
		$this->assertEquals(3, count($kingdoms));
		$neighbor_kingdoms = Kingdoms::neighborKingdoms(2, 2, $kingdoms);
		$this->assertEquals(3, count($neighbor_kingdoms));

	}
}
