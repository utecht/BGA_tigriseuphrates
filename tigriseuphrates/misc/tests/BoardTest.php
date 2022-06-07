<?php
error_reporting(E_ALL ^ E_DEPRECATED);

use PHPUnit\Framework\TestCase;
use TAE\Managers\Board;
use TAE\Testing\Utils;

final class BoardTest extends TestCase {

	public function testMakeTile() {
		$tile = Utils::makeTile(0, 'u', 1, 2);
		$this->assertArrayHasKey('kind', $tile);
		$this->assertEquals('2', $tile['posY']);
		$this->assertEquals('1', $tile['onBoard']);
		$this->assertEquals('blue', $tile['kind']);
	}

	public function testBuildBoard() {
		$board = Utils::buildBoard(<<<'EOD'
			. b . . .
			. b b . .
			. . . . .
			. . . . r
			EOD);
		// var_dump($board);
		$tile = Utils::makeTile(0, 'b', 1, 0);
		$this->assertEquals($tile, $board[0]);
		$this->assertEquals(4, count($board));
		$this->assertEquals(true, Board::isXYColor($board, 1, 1, 'black'));
		$this->assertEquals(true, Board::isXYColor($board, 1, 0, 'black'));
		$this->assertEquals(true, Board::isXYColor($board, 2, 1, 'black'));
		$this->assertEquals(true, Board::isXYColor($board, 4, 3, 'red'));
		$this->assertEquals(false, Board::isXYColor($board, 0, 0, 'black'));
	}

	public function testInLine() {
		$board = Utils::buildBoard(<<<'EOD'
			. b . .
			. r b .
			b . b .
			. . b .
			EOD);
		$tile = Utils::makeTile(0, 'b', 2, 1);
		$this->assertEquals(true, Board::inLine($board, $tile, 2, 1));
		$this->assertEquals(true, Board::inLine($board, $tile, 2, 2));
		$this->assertEquals(false, Board::inLine($board, $tile, 0, 2));
	}

	public function testLineCount() {
		$board = Utils::buildBoard(<<<'EOD'
			. r . . .
			r . . . .
			. . b b .
			. . . b .
			. . . . .
			EOD);
		$tile = Utils::makeTile(1, 'b', 1, 2);
		$this->assertEquals(3, Board::getLineCount($board, $tile));
		$tile = Utils::makeTile(0, 'r', 0, 0);
		$this->assertEquals(2, Board::getLineCount($board, $tile));
	}
}
