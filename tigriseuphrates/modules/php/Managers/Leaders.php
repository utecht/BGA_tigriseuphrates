<?php
namespace TAE\Managers;

use TAE\Managers\Board;

class Leaders extends \APP_DbObject {
	public static function setupNewGame($players, $options) {
		// Assign players random starting leader
		$leader_shapes = ['bull', 'lion', 'bow', 'urn'];
		shuffle($leader_shapes);
		$leader_colors = ['blue', 'green', 'red', 'black'];
		$sql = "INSERT INTO leader (id, shape, kind, owner) VALUES ";
		$values = array();
		$i = 0;
		$player_num = 0;
		// Give each player one of each leader
		foreach ($players as $player_id => $player) {
			$shape = $leader_shapes[$player_num];
			foreach ($leader_colors as $color) {
				$values[] = "('" . $i . "','" . $shape . "','" . $color . "','" . $player_id . "')";
				$i++;
			}
			$player_num++;
		}
		$sql .= implode($values, ',');
		self::DbQuery($sql);
	}

	// calculates the revolt board strength of $leader
	public static function calculateBoardStrength($leader, $board) {
		$neighbors = Board::findNeighbors($leader['posX'], $leader['posY'], $board);
		$strength = 0;
		foreach ($neighbors as $tile_id) {
			if ($board[$tile_id]['kind'] == 'red') {
				$strength++;
			}
		}
		return $strength;
	}

}