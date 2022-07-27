<?php

namespace TAE\Notifications;
use TAE\Core\Notifications;

class Score extends \TAE\Core\Notifications {
	public static function playerScore($color, $points, $player_id, $player_name, $source, $id, $animate) {
		self::notifyAll(
			"playerScore",
			'${player_name} scored ${points} <div class="point ${color}_point"><span class="log_point">${color}</span></div>',
			array(
				'player_id' => $player_id,
				'player_name' => $player_name,
				'color' => $color,
				'points' => $points,
				'source' => $source,
				'id' => $id,
				'animate' => $animate,
			)
		);
	}
}