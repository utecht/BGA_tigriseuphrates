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
require_once 'modules/php/constants.inc.php';

$game_options = array(
	GAME_BOARD => array(
		'name' => totranslate('Game Board'),
		'values' => array(
			STANDARD_BOARD => array('name' => totranslate('Standard Board')),
			ADVANCED_BOARD => array('name' => totranslate('Advanced Board')),
		),
		'default' => STANDARD_BOARD,
	),
	WAR_SUPPORT => array(
		'name' => totranslate('War support'),
		'values' => array(
			STANDARD_RULES => array('name' => totranslate('Standard Rules')),
			ENGLISH_VARIANT => array('name' => totranslate('English Variant')),
		),
		'default' => STANDARD_RULES,
	),
	MONUMENT_VARIANT => array(
		'name' => totranslate('Monuments'),
		'values' => array(
			STANDARD_RULES => array('name' => totranslate('Standard Monuments')),
			WONDER_VARIANT => array('name' => totranslate('Wonder Variant')),
		),
		'default' => STANDARD_RULES,
	),
	ADVANCED_GAME_RULES => array(
		'name' => totranslate('Advanced Game Rules'),
		'values' => array(
			STANDARD_RULES => array('name' => totranslate('No Civilization Buildings')),
			CIVILIZATION_VARIANT => array('name' => totranslate('Civilization Buildings')),
		),
		'default' => STANDARD_RULES,
	),
	SCORING_STYLE => array(
		'name' => totranslate('Scoring'),
		'values' => array(
			STANDARD_RULES => array('name' => totranslate('Hidden Scoring')),
			OPEN_SCORING => array('name' => totranslate('Open Scoring')),
		),
		'default' => STANDARD_RULES,
	),

);

$game_preferences = array(
	TILE_COLORS => array(
		'name' => totranslate('Tile colors'),
		'needReload' => true, // after user changes this preference game interface would auto-reload
		'values' => array(
			STANDARD_COLOR => array('name' => totranslate('Standard'), 'cssPref' => 'standard_tiles'),
			HIGH_SATURATION => array('name' => totranslate('High Saturation'), 'cssPref' => 'high_saturation'),
		),
		'default' => STANDARD_COLOR,
	),
	COORDINATE_OVERLAY => array(
		'name' => totranslate('Coordinate Overlay'),
		'needReload' => true, // after user changes this preference game interface would auto-reload
		'values' => array(
			SHOW => array('name' => totranslate('Show'), 'cssPref' => 'show_overlay'),
			HIDE => array('name' => totranslate('Hide'), 'cssPref' => 'hide_overlay'),
		),
		'default' => SHOW,
	),
	KINGDOM_HIGHLIGHTING => array(
		'name' => totranslate('Kingdom Highlighting'),
		'needReload' => true, // after user changes this preference game interface would auto-reload
		'values' => array(
			FAINT_KINGDOMS => array('name' => totranslate('Faint Kingdoms'), 'cssPref' => 'show_kingdoms'),
			STRONG_KINGDOMS => array('name' => totranslate('Strong Kingdoms'), 'cssPref' => 'bright_kingdoms'),
			HIDE_KINGDOMS => array('name' => totranslate('Hide Kingdoms'), 'cssPref' => 'hide_kingdoms'),
		),
		'default' => FAINT_KINGDOMS,
	),
	LEADER_STRENGTHS => array(
		'name' => totranslate('Leader Strengths'),
		'needReload' => true, // after user changes this preference game interface would auto-reload
		'values' => array(
			SHOW => array('name' => totranslate('Show'), 'cssPref' => 'show_strength'),
			HIDE => array('name' => totranslate('Hide'), 'cssPref' => 'hide_strength'),
		),
		'default' => SHOW,
	),
	END_OF_TURN_CONFIRM => array(
		'name' => totranslate('End of turn confirmation'),
		'needReload' => false, // after user changes this preference game interface would auto-reload
		'values' => array(
			THREE_SECOND => array('name' => totranslate('3 second auto-confirm')),
			TEN_SECOND => array('name' => totranslate('10 second auto-confirm')),
			THIRTY_SECOND => array('name' => totranslate('30 second auto-confirm')),
			MANUAL_CONFIRM => array('name' => totranslate('Manual confirm')),
		),
		'default' => TEN_SECOND,
	),
	LEADER_CIRCLES => array(
		'name' => totranslate('Leader Circles'),
		'needReload' => true, // after user changes this preference game interface would auto-reload
		'values' => array(
			HIDE => array('name' => totranslate('Hide'), 'cssPref' => 'hide_leader_circles'),
			SHOW => array('name' => totranslate('Show'), 'cssPref' => 'show_leader_circles'),
		),
		'default' => HIDE,
	),
);
