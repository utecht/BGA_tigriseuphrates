<?php
error_reporting(E_ALL ^ E_DEPRECATED);

use PHPUnit\Framework\TestCase;
use TAE\Managers\Board;
use TAE\Testing\Utils;

final class BoardTest extends TestCase {

	public function testMakeTile() {
		$tile = Utils::makeTile(0, 'blue', 1, 2);
		$this->assertArrayHasKey('kind', $tile);
		$this->assertEquals('2', $tile['posY']);
		$this->assertEquals('1', $tile['onBoard']);

	}

	public function testBuildBoard() {
		$board = Utils::buildBoard(<<<'EOD'
			. b . .
			. b b .
			. . . .
			. . . .
			EOD);
		$tile = Utils::makeTile(0, 'b', 0, 1);
		$this->assertEquals($tile, $board[0]);
		$this->assertEquals(3, count($board));
	}

	public function testInLine() {
		$board = Utils::buildBoard(<<<'EOD'
			. b . .
			. r b .
			b . b .
			. . b .
			EOD);
		$tile = Utils::makeTile(0, 'b', 1, 2);
		$this->assertEquals(true, Board::inLine($board, $tile, 1, 2));
		$this->assertEquals(true, Board::inLine($board, $tile, 2, 2));
		$this->assertEquals(false, Board::inLine($board, $tile, 2, 0));
	}
}
