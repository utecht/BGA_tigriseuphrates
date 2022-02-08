<?php
namespace TAE\Managers;

use TAE\Managers\Board;

class Kingdoms {

	// returns a kingdom array for $board and $leaders
	public static function findKingdoms($board, $leaders) {
		$kingdoms = array();
		$used_leaders = array();
		$used_tiles = array();

		foreach ($leaders as $leader_id => $leader) {
			$kingdom = array(
				'leaders' => array(),
				'tiles' => array(),
				'pos' => array(),
			);
			$to_test_leaders = array($leader_id);
			$to_test_tiles = array();
			while (count($to_test_leaders) > 0) {
				$leader = $leaders[array_pop($to_test_leaders)];
				if (in_array($leader['id'], $used_leaders) === false) {
					$kingdom['leaders'][$leader['id']] = $leader;
					$used_leaders[] = $leader['id'];
					$x = $leader['posX'];
					$y = $leader['posY'];
					$kingdom['pos'][] = [$x, $y];
					$potential_tiles = Board::findNeighbors($x, $y, $board);
					$potential_leaders = Board::findNeighbors($x, $y, $leaders);

					foreach ($potential_tiles as $ptile) {
						if ($board[$ptile]['kind'] == 'catastrophe') {
							$potential_tiles = array_diff($potential_tiles, array($ptile));
						}
						if ($board[$ptile]['isUnion'] === '1') {
							$potential_tiles = array_diff($potential_tiles, array($ptile));
						}
					}
					$to_test_tiles = array_unique(array_merge($potential_tiles, $to_test_tiles));
					$to_test_leaders = array_unique(array_merge($potential_leaders, $to_test_leaders));

					while (count($to_test_tiles) > 0) {
						$tile = $board[array_pop($to_test_tiles)];
						if (in_array($tile['id'], $used_tiles) === false) {
							$kingdom['tiles'][$tile['id']] = $tile;
							$used_tiles[] = $tile['id'];
							$x = $tile['posX'];
							$y = $tile['posY'];
							$kingdom['pos'][] = [$x, $y];
							$potential_tiles = Board::findNeighbors($x, $y, $board);
							$potential_leaders = Board::findNeighbors($x, $y, $leaders);

							foreach ($potential_tiles as $ptile) {
								if ($board[$ptile]['kind'] == 'catastrophe') {
									$potential_tiles = array_diff($potential_tiles, array($ptile));
								}
								if ($board[$ptile]['isUnion'] === '1') {
									$potential_tiles = array_diff($potential_tiles, array($ptile));
								}
							}
							$to_test_tiles = array_unique(array_merge($potential_tiles, $to_test_tiles));
							$to_test_leaders = array_unique(array_merge($potential_leaders, $to_test_leaders));
						}
					}
				}
			}
			if (count($kingdom['pos']) > 0) {
				$kingdoms[] = $kingdom;
			}
		}
		return $kingdoms;
	}

	// returns the index of neighboring kingdoms in $kingdoms to x and y
	public static function neighborKingdoms($x, $y, $kingdoms) {
		$neighbor_kingdoms = array();
		$above = [$x, $y - 1];
		$below = [$x, $y + 1];
		$left = [$x - 1, $y];
		$right = [$x + 1, $y];
		foreach ($kingdoms as $i => $kingdom) {
			if (in_array($above, $kingdom['pos'])) {
				$neighbor_kingdoms[] = $i;
			}
			if (in_array($below, $kingdom['pos'])) {
				$neighbor_kingdoms[] = $i;
			}
			if (in_array($left, $kingdom['pos'])) {
				$neighbor_kingdoms[] = $i;
			}
			if (in_array($right, $kingdom['pos'])) {
				$neighbor_kingdoms[] = $i;
			}
		}
		return array_unique($neighbor_kingdoms);
	}

	// returns true if kingdom has more than one treasure
	public static function kingdomHasTwoTreasures($kingdom) {
		$hasTreasure = false;
		foreach ($kingdom['tiles'] as $tile) {
			if ($tile['hasTreasure']) {
				if ($hasTreasure === true) {
					return true;
				} else {
					$hasTreasure = true;
				}
			}
		}
		return false;
	}

	// calculates the war board strength of $leader
	public static function calculateKingdomStrength($leader, $kingdoms) {
		$strength = 0;
		foreach ($kingdoms as $kingdom) {
			if (array_key_exists($leader['id'], $kingdom['leaders'])) {
				foreach ($kingdom['tiles'] as $tile) {
					if ($tile['kind'] === $leader['kind']) {
						$strength++;
					}
				}
			}
		}
		return $strength;
	}

}