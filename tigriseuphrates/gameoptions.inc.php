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
 * gameoptions.inc.php
 *
 * TigrisEuphrates game options description
 *
 * In this file, you can define your game options (= game variants).
 *
 * Note: If your game has no variant, you don't have to modify this file.
 *
 * Note²: All options defined in this file should have a corresponding "game state labels"
 *        with the same ID (see "initGameStateLabels" in tigriseuphrates.game.php)
 *
 * !! It is not a good idea to modify this file when a game is running !!
 *
 */

$game_options = array(
	100 => array(
		'name' => totranslate('Game Board'),
		'values' => array(
			1 => array('name' => totranslate('Standard Board')),
			2 => array('name' => totranslate('Advanced Board')),
		),
		'default' => 1,
	),
	// 101 => array(
	// 	'name' => totranslate('War support'),
	// 	'values' => array(
	// 		1 => array('name' => totranslate('Standard Rules')),
	// 		2 => array('name' => totranslate('English Variant')),
	// 	),
	// 	'default' => 1,
	// ),
	// 102 => array(
	// 	'name' => totranslate('Monuments'),
	// 	'values' => array(
	// 		1 => array('name' => totranslate('Standard Monuments')),
	// 		2 => array('name' => totranslate('Wonder Variant')),
	// 	),
	// 	'default' => 1,
	// ),
	// 103 => array(
	// 	'name' => totranslate('Advanced Game Rules'),
	// 	'values' => array(
	// 		1 => array('name' => totranslate('No Civilazation Buildings')),
	// 		2 => array('name' => totranslate('Civilazation Buildings')),
	// 	),
	// 	'default' => 1,
	// ),
	104 => array(
		'name' => totranslate('Scoring'),
		'values' => array(
			1 => array('name' => totranslate('Hidden Scoring')),
			2 => array('name' => totranslate('Open Scoring')),
		),
		'default' => 1,
	),

);

$game_preferences = array(
	100 => array(
		'name' => totranslate('Tile colors'),
		'needReload' => true, // after user changes this preference game interface would auto-reload
		'values' => array(
			1 => array('name' => totranslate('Standard'), 'cssPref' => 'standard_tiles'),
			2 => array('name' => totranslate('High Saturation'), 'cssPref' => 'high_saturation'),
		),
		'default' => 1,
	),
	101 => array(
		'name' => totranslate('Coordinate Overlay'),
		'needReload' => true, // after user changes this preference game interface would auto-reload
		'values' => array(
			1 => array('name' => totranslate('Show'), 'cssPref' => 'show_overlay'),
			2 => array('name' => totranslate('Hide'), 'cssPref' => 'hide_overlay'),
		),
		'default' => 1,
	),
	102 => array(
		'name' => totranslate('Kingdom Highlighting'),
		'needReload' => true, // after user changes this preference game interface would auto-reload
		'values' => array(
			1 => array('name' => totranslate('Show'), 'cssPref' => 'show_kingdoms'),
			2 => array('name' => totranslate('Hide'), 'cssPref' => 'hide_kingdoms'),
		),
		'default' => 1,
	),
);
