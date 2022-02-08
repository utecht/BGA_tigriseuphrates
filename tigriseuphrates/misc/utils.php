<?php

namespace TAE\Testing;

final class Utils {
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
		case 'r':
			$c = 'red';
			break;
		}
		return [
			'id' => $id,
			'kind' => $c,
			'posX' => strval($posX),
			'posY' => strval($posY),
			'onBoard' => '1',
			'isUnion' => $isUnion,
			'hasTreasure' => '0',
		];
	}

	public static function makeLeader($id, $val, $posX, $posY) {
		$kind = $val[0];
		$color = $val[1];
		$c = null;
		switch ($color) {
		case 'g':
			$c = 'green';
			break;
		case 'u':
			$c = 'blue';
			break;
		case 'b':
			$c = 'black';
			break;
		case 'r':
			$c = 'red';
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
			'kind' => $c,
			'shape' => $k,
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
}
