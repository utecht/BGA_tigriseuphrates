<?php
namespace TAE\Managers;

use TAE\Core\Game;

class Players extends \APP_DbObject {
	public static function setupNewGame($players, $options) {

		// Set the colors of the players with HTML color code
		// The default below is red/green/blue/orange/brown
		// The number of colors defined here must correspond to the maximum number of players allowed for the gams
		$gameinfos = Game::get()->getGameinfos();
		$default_colors = $gameinfos['player_colors'];

		// Create players
		// Note: if you added some extra field on "player" table in the database (dbmodel.sql), you can initialize it there.
		$sql = "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ";
		$values = array();
		foreach ($players as $player_id => $player) {
			$color = array_shift($default_colors);
			$values[] = "('" . $player_id . "','$color','" . $player['player_canal'] . "','" . addslashes($player['player_name']) . "','" . addslashes($player['player_avatar']) . "')";
		}
		$sql .= implode($values, ',');
		self::DbQuery($sql);
		Game::get()->reattributeColorsBasedOnPreferences($players, $gameinfos['player_colors']);
		Game::get()->reloadPlayersBasicInfos();

		// Initialize starting points
		$sql = "INSERT INTO point (player) VALUES ";
		$values = array();
		foreach ($players as $player_id => $player) {
			$values[] = "('" . $player_id . "')";
		}
		$sql .= implode($values, ',');
		self::DbQuery($sql);

	}
}