<?php
namespace TAE;

trait LoadBugTrait {
	public function loadBug($reportId) {
		$db = explode('_', self::getUniqueValueFromDB("SELECT SUBSTRING_INDEX(DATABASE(), '_', -2)"));
		$game = $db[0];
		$tableId = $db[1];
		self::notifyAllPlayers('loadBug', "Trying to load <a href='https://boardgamearena.com/bug?id=$reportId' target='_blank'>bug report $reportId</a>", [
			'urls' => [
				// Emulates "load bug report" in control panel
				"https://studio.boardgamearena.com/admin/studio/getSavedGameStateFromProduction.html?game=$game&report_id=$reportId&table_id=$tableId",

				// Emulates "load 1" at this table
				"https://studio.boardgamearena.com/table/table/loadSaveState.html?table=$tableId&state=1",

				// Calls the function below to update SQL
				"https://studio.boardgamearena.com/1/$game/$game/loadBugSQL.html?table=$tableId&report_id=$reportId",

				// Emulates "clear PHP cache" in control panel
				// Needed at the end because BGA is caching player info
				"https://studio.boardgamearena.com/admin/studio/clearGameserverPhpCache.html?game=$game",
			],
		]);
	}

	public function loadBugSQL($reportId) {
		$studioPlayer = self::getCurrentPlayerId();
		$players = self::getObjectListFromDb("SELECT player_id FROM player", true);

		// Change for your game
		// We are setting the current state to match the start of a player's turn if it's already game over
		$sql = [
			"UPDATE global SET global_value=2 WHERE global_id=1 AND global_value=99",
		];
		foreach ($players as $pId) {
			// All games can keep this SQL
			$sql[] = "UPDATE player SET player_id=$studioPlayer WHERE player_id=$pId";
			$sql[] = "UPDATE global SET global_value=$studioPlayer WHERE global_value=$pId";
			$sql[] = "UPDATE stats SET stats_player_id=$studioPlayer WHERE stats_player_id=$pId";
			$sql[] = "UPDATE gamelog SET gamelog_player=$studioPlayer WHERE gamelog_player=$pId";
			$sql[] = "UPDATE gamelog SET gamelog_current_player=$studioPlayer WHERE gamelog_current_player=$pId";
			$sql[] = "UPDATE gamelog SET gamelog_notification=REPLACE(gamelog_notification, $pId, $studioPlayer)";

			// Add game-specific SQL update the tables for your game
			$sql[] = "UPDATE tile SET owner=$studioPlayer WHERE owner=$pId";
			$sql[] = "UPDATE leader SET owner=$studioPlayer WHERE owner=$pId";
			$sql[] = "UPDATE point SET player=$studioPlayer WHERE player=$pId";

			// This could be improved, it assumes you had sequential studio accounts before loading
			// e.g., quietmint0, quietmint1, quietmint2, etc. are at the table
			$studioPlayer++;
		}
		$msg = "<b>Loaded <a href='https://boardgamearena.com/bug?id=$reportId' target='_blank'>bug report $reportId</a></b><hr><ul><li>" . implode(';</li><li>', $sql) . ';</li></ul>';
		self::warn($msg);
		self::notifyAllPlayers('message', $msg, []);

		foreach ($sql as $q) {
			self::DbQuery($q);
		}
		self::reloadPlayersBasicInfos();
		$this->gamestate->reloadState();
	}
}