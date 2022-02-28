<?php
namespace TAE\Managers;

use TAE\Core\Game;

class Board extends \APP_DbObject {
	public static function setupNewGame($players, $options) {
		// Create deck and shuffle
		$starting_temples = Game::get()->starting_temples;
		if (Game::get()->getGameStateValue('game_board') == ADVANCED_BOARD) {
			$starting_temples = Game::get()->alt_starting_temples;
		}
		$all_tiles = array();
		$all_tiles = array_merge($all_tiles, array_fill(0, STARTING_RED_TILES - count($starting_temples), 'red'));
		$all_tiles = array_merge($all_tiles, array_fill(0, STARTING_BLACK_TILES, 'black'));
		$all_tiles = array_merge($all_tiles, array_fill(0, STARTING_BLUE_TILES, 'blue'));
		$all_tiles = array_merge($all_tiles, array_fill(0, STARTING_GREEN_TILES, 'green'));
		shuffle($all_tiles);
		$sql = "INSERT INTO tile (id, state, owner, kind, posX, posY, hasTreasure) VALUES ";
		$values = array();
		// Give players catastrophes
		$i = 0;
		foreach ($players as $player_id => $player) {
			$values[] = "('" . $i . "','hand','" . $player_id . "','catastrophe',NULL,NULL,'0')";
			$i++;
			$values[] = "('" . $i . "','hand','" . $player_id . "','catastrophe',NULL,NULL,'0')";
			$i++;
		}
		// Put starting temples on board
		foreach ($starting_temples as $temple) {
			$values[] = "('" . $i . "','board',NULL,'red','" . $temple[0] . "','" . $temple[1] . "','1')";
			$i++;
		}
		// Draw starting hands
		foreach ($players as $player_id => $player) {
			for ($c = 0; $c < 6; $c++) {
				$color = array_shift($all_tiles);
				$values[] = "('" . $i . "','hand','" . $player_id . "','" . $color . "',NULL,NULL,'0')";
				$i++;
			}
		}
		// Insert remaining tiles into bag
		foreach ($all_tiles as $color) {
			$values[] = "('" . $i . "','bag',NULL,'" . $color . "',NULL,NULL,'0')";
			$i++;
		}
		$sql .= implode($values, ',');
		self::DbQuery($sql);

		// Create monuments
		$sql = "INSERT INTO monument (id, color1, color2) VALUES ";
		$values = array();
		$values[] = "('0', 'black', 'green')";
		$values[] = "('1', 'black', 'blue')";
		$values[] = "('2', 'black', 'red')";
		$values[] = "('3', 'red', 'blue')";
		$values[] = "('4', 'green', 'red')";
		$values[] = "('5', 'blue', 'green')";
		if (Game::get()->getGameStateValue('wonder_variant') == WONDER_VARIANT) {
			$values[] = "('6', 'wonder', 'wonder')";
		}
		$sql .= implode($values, ',');
		self::DbQuery($sql);

		// Create buildings
		if (Game::get()->getGameStateValue('civilization_buildings') == CIVILIZATION_VARIANT) {
			$sql = "INSERT INTO building (id, kind) VALUES ";
			$values = array();
			$values[] = "('0', 'black')";
			$values[] = "('1', 'blue')";
			$values[] = "('2', 'green')";
			$values[] = "('3', 'red')";
			$sql .= implode($values, ',');
			self::DbQuery($sql);
		}
	}

	// returns the id's of neighbors in $options of $x and $y
	public static function findNeighbors($x, $y, $options) {
		$neighbors = array();
		$above = [$x, $y - 1];
		$below = [$x, $y + 1];
		$left = [$x - 1, $y];
		$right = [$x + 1, $y];
		foreach ($options as $option) {
			if ($above[0] == $option['posX'] && $above[1] == $option['posY']) {
				$neighbors[] = $option['id'];
			}
			if ($below[0] == $option['posX'] && $below[1] == $option['posY']) {
				$neighbors[] = $option['id'];
			}
			if ($left[0] == $option['posX'] && $left[1] == $option['posY']) {
				$neighbors[] = $option['id'];
			}
			if ($right[0] == $option['posX'] && $right[1] == $option['posY']) {
				$neighbors[] = $option['id'];
			}
		}
		return $neighbors;
	}

	// returns tile from $tiles corresponding with $x and $y pos
	public static function getTileXY($tiles, $x, $y) {
		foreach ($tiles as $tile) {
			if ($tile['posX'] == $x && $tile['posY'] == $y) {
				return $tile;
			}
		}
		return false;
	}

	// Returns false if $x or $y is not a tile
	public static function isXYColor($tiles, $x, $y, $color) {
		$tile = self::getTileXY($tiles, $x, $y);
		if ($tile == false) {
			return false;
		} else {
			return $tile['kind'] == $color;
		}
	}

	// returns the number of tiles in a line for purpose of advanced civilization buildings
	public static function getLineCount($board, $tile) {
		$left_count = 0;
		$right_count = 0;
		$up_count = 0;
		$down_count = 0;

		$start_x = $tile['posX'];
		$start_y = $tile['posY'];
		$kind = $tile['kind'];

		// count up
		$y_inc = 1;
		while (self::isXYColor($board, $start_x, $start_y - $y_inc, $kind)) {
			$up_count += 1;
			$y_inc += 1;
		}
		// count down
		$y_inc = 1;
		while (self::isXYColor($board, $start_x, $start_y + $y_inc, $kind)) {
			$down_count += 1;
			$y_inc += 1;
		}
		// count left
		$x_inc = 1;
		while (self::isXYColor($board, $start_x - $x_inc, $start_y, $kind)) {
			$left_count += 1;
			$x_inc += 1;
		}
		// count right
		$x_inc = 1;
		while (self::isXYColor($board, $start_x + $x_inc, $start_y, $kind)) {
			$left_count += 1;
			$x_inc += 1;
		}

		$horizontal_count = 1 + $left_count + $right_count;
		$vertical_count = 1 + $up_count + $down_count;

		if ($horizontal_count > $vertical_count) {
			return $horizontal_count;
		} else {
			return $vertical_count;
		}
	}

	// returns the number of tiles in a line for purpose of advanced civilization buildings
	public static function inLine($board, $tile, $target_x, $target_y) {
		$start_x = $tile['posX'];
		$start_y = $tile['posY'];
		if ($start_x == $target_x && $start_y == $target_y) {
			return true;
		}
		$kind = $tile['kind'];

		if ($start_x == $target_x) {
			// check up
			$y_inc = 1;
			while (self::isXYColor($board, $start_x, $start_y - $y_inc, $kind)) {
				if ($start_y - $y_inc == $target_y) {
					return true;
				}
				$y_inc += 1;
			}
			// check down
			$y_inc = 1;
			while (self::isXYColor($board, $start_x, $start_y + $y_inc, $kind)) {
				if ($start_y + $y_inc == $target_y) {
					return true;
				}
				$y_inc += 1;
			}
		}
		if ($start_y == $target_y) {
			$x_inc = 1;
			while (self::isXYColor($board, $start_x - $x_inc, $start_y, $kind)) {
				if ($start_x - $x_inc == $target_x) {
					return true;
				}
				$x_inc += 1;
			}
			$x_inc = 1;
			while (self::isXYColor($board, $start_x + $x_inc, $start_y, $kind)) {
				if ($start_x + $x_inc == $target_x) {
					return true;
				}
				$x_inc += 1;
			}
		}

		return false;
	}

	// returns array of tiles that form an eligible monument with placed tile
	public static function getMonumentSquare($tiles, $tile) {
		$x = intval($tile['posX']);
		$y = intval($tile['posY']);

		$right = self::getTileXY($tiles, $x + 1, $y);
		$left = self::getTileXY($tiles, $x - 1, $y);
		$below = self::getTileXY($tiles, $x, $y + 1);
		$above = self::getTileXY($tiles, $x, $y - 1);
		$rightbelow = self::getTileXY($tiles, $x + 1, $y + 1);
		$leftbelow = self::getTileXY($tiles, $x - 1, $y + 1);
		$rightabove = self::getTileXY($tiles, $x + 1, $y - 1);
		$leftabove = self::getTileXY($tiles, $x - 1, $y - 1);

		if ($right !== false && $rightbelow !== false && $below !== false) {
			if ($right['kind'] == $tile['kind'] && $below['kind'] == $tile['kind'] && $rightbelow['kind'] == $tile['kind']) {
				return array($tile, $right, $rightbelow, $below);
			}
		}
		if ($right !== false && $rightabove !== false && $above !== false) {
			if ($right['kind'] == $tile['kind'] && $above['kind'] == $tile['kind'] && $rightabove['kind'] == $tile['kind']) {
				return array($tile, $right, $rightabove, $above);
			}
		}
		if ($left !== false && $leftbelow !== false && $below !== false) {
			if ($left['kind'] == $tile['kind'] && $below['kind'] == $tile['kind'] && $leftbelow['kind'] == $tile['kind']) {
				return array($tile, $left, $leftbelow, $below);
			}
		}
		if ($left !== false && $leftabove !== false && $above !== false) {
			if ($left['kind'] == $tile['kind'] && $above['kind'] == $tile['kind'] && $leftabove['kind'] == $tile['kind']) {
				return array($tile, $left, $leftabove, $above);
			}
		}
		return false;
	}

	public static function getWonderPlus($tiles, $tile) {
		$x = intval($tile['posX']);
		$y = intval($tile['posY']);
		$color = $tile['kind'];

		$right = self::getTileXY($tiles, $x + 1, $y);
		$left = self::getTileXY($tiles, $x - 1, $y);
		$below = self::getTileXY($tiles, $x, $y + 1);
		$above = self::getTileXY($tiles, $x, $y - 1);

		if (self::isWonderPossible($tiles, $tile)) {
			return array($tile, $above, $right, $left, $below);
		}
		if (self::isWonderPossible($tiles, $right)) {
			return self::getWonderPlus($tiles, $right);
		}
		if (self::isWonderPossible($tiles, $left)) {
			return self::getWonderPlus($tiles, $left);
		}
		if (self::isWonderPossible($tiles, $above)) {
			return self::getWonderPlus($tiles, $above);
		}
		if (self::isWonderPossible($tiles, $below)) {
			return self::getWonderPlus($tiles, $below);
		}
		return false;
	}

	// Returns number of possible monuments that can be built
	public static function getMonumentCount($tiles, $tile) {
		$x = intval($tile['posX']);
		$y = intval($tile['posY']);
		$color = $tile['kind'];

		$right = self::isXYColor($tiles, $x + 1, $y, $color);
		$left = self::isXYColor($tiles, $x - 1, $y, $color);
		$below = self::isXYColor($tiles, $x, $y + 1, $color);
		$above = self::isXYColor($tiles, $x, $y - 1, $color);
		$rightbelow = self::isXYColor($tiles, $x + 1, $y + 1, $color);
		$leftbelow = self::isXYColor($tiles, $x - 1, $y + 1, $color);
		$rightabove = self::isXYColor($tiles, $x + 1, $y - 1, $color);
		$leftabove = self::isXYColor($tiles, $x - 1, $y - 1, $color);

		$potential_monuments = 0;

		if ($right && $rightbelow && $below) {
			$potential_monuments++;
		}
		if ($right && $rightabove && $above) {
			$potential_monuments++;
		}
		if ($left && $leftbelow && $below) {
			$potential_monuments++;
		}
		if ($left && $leftabove && $above) {
			$potential_monuments++;
		}
		return $potential_monuments;
	}

	// Return pos of all possible wonder locations
	public static function isWonderPossible($tiles, $tile) {
		if ($tile == false) {
			return false;
		}
		$x = intval($tile['posX']);
		$y = intval($tile['posY']);
		$color = $tile['kind'];
		$tiles[$tile['id']] = $tile;

		$right = self::isXYColor($tiles, $x + 1, $y, $color);
		$left = self::isXYColor($tiles, $x - 1, $y, $color);
		$below = self::isXYColor($tiles, $x, $y + 1, $color);
		$above = self::isXYColor($tiles, $x, $y - 1, $color);

		return ($right && $left && $below && $above);
	}

	// Returns a 0 or 1 if wonder matches
	public static function matchingWonder($tiles, $tile, $color) {
		if ($tile == false) {
			return 0;
		}
		if ($tile['kind'] != $color) {
			return 0;
		}
		if (self::isWonderPossible($tiles, $tile)) {
			return 1;
		}
	}

	// Returns number of possible wonders that can be built
	public static function getWonderCount($tiles, $tile) {
		$x = intval($tile['posX']);
		$y = intval($tile['posY']);
		$color = $tile['kind'];
		$tiles[$tile['id']] = $tile;

		$right = self::getTileXY($tiles, $x + 1, $y);
		$left = self::getTileXY($tiles, $x - 1, $y);
		$below = self::getTileXY($tiles, $x, $y + 1);
		$above = self::getTileXY($tiles, $x, $y - 1);

		$potential_wonders = 0;
		$potential_wonders += self::matchingWonder($tiles, $tile, $color);
		$potential_wonders += self::matchingWonder($tiles, $right, $color);
		$potential_wonders += self::matchingWonder($tiles, $left, $color);
		$potential_wonders += self::matchingWonder($tiles, $below, $color);
		$potential_wonders += self::matchingWonder($tiles, $above, $color);
		return $potential_wonders;
	}
}