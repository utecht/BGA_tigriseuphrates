<?php
namespace TAE\States;

trait ScoringTrait {
	function addToLowest(&$points) {
		$colors = array('red', 'blue', 'green', 'black');
		$lowest = 999;
		$lowest_color = 'red';
		foreach ($colors as $color) {
			if ($points[$color] < $lowest) {
				$lowest = $points[$color];
				$lowest_color = $color;
			}
		}
		$points[$lowest_color]++;
	}

	function getLowest($points) {
		$colors = array('red', 'blue', 'green', 'black');
		$lowest = 99;
		$lowest_color = 'red';
		foreach ($colors as $color) {
			if ($points[$color] < $lowest) {
				$lowest = $points[$color];
				$lowest_color = $color;
			}
		}
		return $lowest_color;
	}

	function getPointArray($point) {
		return [
			$point['black'],
			$point['red'],
			$point['green'],
			$point['blue'],
		];
	}

	function breakTie($points, $tied_players) {
		$player_arrs = [];
		foreach ($tied_players as $player_id) {
			$arr = self::getPointArray($points[$player_id]);
			sort($arr);
			$player_arrs[$player_id] = $arr;
		}
		arsort($player_arrs);
		$i = count($player_arrs) + 1;
		$last = [];
		foreach ($player_arrs as $player_id => $point) {
			if ($point != $last) {
				$i--;
			}
			self::DbQuery("update player set player_score_aux = '" . $i . "' where player_id = '" . $player_id . "'");
			$last = $point;
		}
	}

	function stFinalScoring() {
		$points = self::getCollectionFromDB("select * from point");
		self::notifyAllPlayers(
			"startingFinalScores",
			clienttranslate("Final Scoring."),
			array('points' => $points)
		);
		$highest_score = -1;
		$scores = [];
		foreach ($points as $player => &$point) {
			self::setStat($point['black'], 'black_points', $player);
			self::setStat($point['red'], 'red_points', $player);
			self::setStat($point['green'], 'green_points', $player);
			self::setStat($point['blue'], 'blue_points', $player);
			self::setStat($point['treasure'], 'treasure_picked_up', $player);
			while ($point['treasure'] > 0) {
				$point['treasure']--;
				self::addToLowest($point);
			}
			$low_color = self::getLowest($point);
			$score = $point[$low_color];
			$points[$player][$low_color] = 999;
			$points[$player]['lowest'] = $score;
			$points[$player]['score'] = $score;
			if (array_key_exists($score, $scores) == false) {
				$scores[$score] = [];
			}
			$scores[$score][] = $player;
			self::DbQuery("update player set player_score = '" . $score . "' where player_id = '" . $player . "'");
			if ($score > $highest_score) {
				$highest_score = $score;
			}
		}
		self::notifyAllPlayers(
			"finalScores",
			clienttranslate("End of Game"),
			array('points' => $points)
		);

		foreach ($scores as $score => $players) {
			self::breakTie($points, $players);
		}

		$winner_no = self::getUniqueValueFromDB("select player_no from player order by player_score desc, player_score_aux desc limit 1");
		self::setStat(intVal($winner_no), 'winning_position');

		$this->gamestate->nextState("endGame");
	}
}
