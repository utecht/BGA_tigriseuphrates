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

);
