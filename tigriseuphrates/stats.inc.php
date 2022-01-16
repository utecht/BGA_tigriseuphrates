<?php

/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * TigrisEuphrates implementation : © Joseph Utecht <joseph@utecht.co>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * stats.inc.php
 *
 * TigrisEuphrates game statistics description
 *
 */

/*
In this file, you are describing game statistics, that will be displayed at the end of the
game.

!! After modifying this file, you must use "Reload  statistics configuration" in BGA Studio backoffice
("Control Panel" / "Manage Game" / "Your Game")

There are 2 types of statistics:
_ table statistics, that are not associated to a specific player (ie: 1 value for each game).
_ player statistics, that are associated to each players (ie: 1 value for each player in the game).

Statistics types can be "int" for integer, "float" for floating point values, and "bool" for boolean

Once you defined your statistics there, you can start using "initStat", "setStat" and "incStat" method
in your game logic, using statistics names defined below.

!! It is not a good idea to modify this file when a game is running !!

If your game is already public on BGA, please read the following before any change:
http://en.doc.boardgamearena.com/Post-release_phase#Changes_that_breaks_the_games_in_progress

Notes:
 * Statistic index is the reference used in setStat/incStat/initStat PHP method
 * Statistic index must contains alphanumerical characters and no space. Example: 'turn_played'
 * Statistics IDs must be >=10
 * Two table statistics can't share the same ID, two player statistics can't share the same ID
 * A table statistic can have the same ID than a player statistics
 * Statistics ID is the reference used by BGA website. If you change the ID, you lost all historical statistic data. Do NOT re-use an ID of a deleted statistic
 * Statistic name is the English description of the statistic as shown to players

 */

$stats_type = array(

	// Statistics global to table
	"table" => array(

		"turns_number" => array("id" => 10,
			"name" => totranslate("Number of turns"),
			"type" => "int"),
		"winning_position" => array("id" => 11,
			"name" => totranslate("Winning player table order"),
			"type" => "int"),
	),

	// Statistics existing for each player
	"player" => array(

		"turns_number" => array("id" => 10,
			"name" => totranslate("Number of turns"),
			"type" => "int"),
		"revolts_won_attacker" => array("id" => 11,
			"name" => totranslate("Revolts won as attacker"),
			"type" => "int"),
		"revolts_won_defender" => array("id" => 12,
			"name" => totranslate("Revolts won as defender"),
			"type" => "int"),
		"revolts_lost_attacker" => array("id" => 13,
			"name" => totranslate("Revolts lost as attacker"),
			"type" => "int"),
		"revolts_lost_defender" => array("id" => 14,
			"name" => totranslate("Revolts lost as defender"),
			"type" => "int"),
		"wars_won_attacker" => array("id" => 15,
			"name" => totranslate("Wars won as attacker"),
			"type" => "int"),
		"wars_won_defender" => array("id" => 16,
			"name" => totranslate("Wars won as defender"),
			"type" => "int"),
		"wars_lost_attacker" => array("id" => 17,
			"name" => totranslate("Wars lost as attacker"),
			"type" => "int"),
		"wars_lost_defender" => array("id" => 18,
			"name" => totranslate("Wars lost as defender"),
			"type" => "int"),
		"treasure_picked_up" => array("id" => 19,
			"name" => totranslate("Treasures picked up"),
			"type" => "int"),
		"monuments_built" => array("id" => 20,
			"name" => totranslate("Monuments built"),
			"type" => "int"),
		"catastrophes_placed" => array("id" => 21,
			"name" => totranslate("Catastrophes placed"),
			"type" => "int"),
		"black_points" => array("id" => 22,
			"name" => totranslate("Black points"),
			"type" => "int"),
		"red_points" => array("id" => 23,
			"name" => totranslate("Red points"),
			"type" => "int"),
		"blue_points" => array("id" => 24,
			"name" => totranslate("Blue points"),
			"type" => "int"),
		"green_points" => array("id" => 25,
			"name" => totranslate("Green points"),
			"type" => "int"),
	),

);
