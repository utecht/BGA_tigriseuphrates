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
 * material.inc.php
 *
 * TigrisEuphrates game material description
 *
 * Here, you can describe the material of your game with PHP variables.
 *
 * This file is loaded in your game logic class constructor, ie these variables
 * are available everywhere in your game logic code.
 *
 */

require_once 'modules/php/constants.inc.php';

$this->rivers = array(
	array('posX' => '1', 'posY' => '3'),
	array('posX' => '2', 'posY' => '3'),
	array('posX' => '5', 'posY' => '0'),
	array('posX' => '6', 'posY' => '0'),
	array('posX' => '14', 'posY' => '3'),
	array('posX' => '0', 'posY' => '3'),
	array('posX' => '3', 'posY' => '3'),
	array('posX' => '3', 'posY' => '2'),
	array('posX' => '4', 'posY' => '2'),
	array('posX' => '4', 'posY' => '1'),
	array('posX' => '4', 'posY' => '0'),
	array('posX' => '12', 'posY' => '1'),
	array('posX' => '8', 'posY' => '8'),
	array('posX' => '13', 'posY' => '2'),
	array('posX' => '8', 'posY' => '0'),
	array('posX' => '7', 'posY' => '0'),
	array('posX' => '12', 'posY' => '0'),
	array('posX' => '12', 'posY' => '2'),
	array('posX' => '13', 'posY' => '3'),
	array('posX' => '15', 'posY' => '3'),
	array('posX' => '15', 'posY' => '4'),
	array('posX' => '14', 'posY' => '4'),
	array('posX' => '14', 'posY' => '5'),
	array('posX' => '14', 'posY' => '6'),
	array('posX' => '10', 'posY' => '8'),
	array('posX' => '12', 'posY' => '6'),
	array('posX' => '11', 'posY' => '8'),
	array('posX' => '13', 'posY' => '6'),
	array('posX' => '12', 'posY' => '7'),
	array('posX' => '12', 'posY' => '8'),
	array('posX' => '9', 'posY' => '8'),
	array('posX' => '7', 'posY' => '8'),
	array('posX' => '6', 'posY' => '8'),
	array('posX' => '5', 'posY' => '7'),
	array('posX' => '6', 'posY' => '7'),
	array('posX' => '1', 'posY' => '6'),
	array('posX' => '0', 'posY' => '6'),
	array('posX' => '3', 'posY' => '7'),
	array('posX' => '4', 'posY' => '7'),
	array('posX' => '3', 'posY' => '6'),
	array('posX' => '2', 'posY' => '6'),
);

$this->alt_rivers = array(
	array('posX' => '2', 'posY' => '0'),
	array('posX' => '3', 'posY' => '0'),
	array('posX' => '15', 'posY' => '0'),
	array('posX' => '2', 'posY' => '1'),
	array('posX' => '3', 'posY' => '1'),
	array('posX' => '15', 'posY' => '1'),
	array('posX' => '2', 'posY' => '2'),
	array('posX' => '3', 'posY' => '2'),
	array('posX' => '15', 'posY' => '2'),
	array('posX' => '2', 'posY' => '3'),
	array('posX' => '12', 'posY' => '3'),
	array('posX' => '13', 'posY' => '3'),
	array('posX' => '14', 'posY' => '3'),
	array('posX' => '15', 'posY' => '3'),
	array('posX' => '2', 'posY' => '4'),
	array('posX' => '12', 'posY' => '4'),
	array('posX' => '2', 'posY' => '5'),
	array('posX' => '12', 'posY' => '5'),
	array('posX' => '2', 'posY' => '6'),
	array('posX' => '3', 'posY' => '6'),
	array('posX' => '12', 'posY' => '6'),
	array('posX' => '13', 'posY' => '6'),
	array('posX' => '3', 'posY' => '7'),
	array('posX' => '4', 'posY' => '7'),
	array('posX' => '5', 'posY' => '7'),
	array('posX' => '13', 'posY' => '7'),
	array('posX' => '5', 'posY' => '8'),
	array('posX' => '6', 'posY' => '8'),
	array('posX' => '7', 'posY' => '8'),
	array('posX' => '13', 'posY' => '8'),
	array('posX' => '6', 'posY' => '9'),
	array('posX' => '7', 'posY' => '9'),
	array('posX' => '8', 'posY' => '9'),
	array('posX' => '9', 'posY' => '9'),
	array('posX' => '10', 'posY' => '9'),
	array('posX' => '11', 'posY' => '9'),
	array('posX' => '12', 'posY' => '9'),
	array('posX' => '13', 'posY' => '9'),
	array('posX' => '6', 'posY' => '10'),
);

$this->starting_temples = array(
	[1, 1],
	[10, 0],
	[5, 2],
	[15, 1],
	[13, 4],
	[8, 6],
	[1, 7],
	[14, 8],
	[5, 9],
	[10, 10],
);

$this->alt_starting_temples = array(
	[4, 0],
	[9, 0],
	[14, 1],
	[1, 2],
	[6, 3],
	[11, 3],
	[3, 5],
	[15, 5],
	[0, 7],
	[7, 7],
	[12, 7],
	[4, 9],
	[9, 10],
	[14, 10],
);

$this->outerTemples = array(
	array('posX' => '1', 'posY' => '1'),
	array('posX' => '15', 'posY' => '1'),
	array('posX' => '14', 'posY' => '8'),
	array('posX' => '1', 'posY' => '7'),
);

$this->alt_outerTemples = array(
	array('posX' => '1', 'posY' => '2'),
	array('posX' => '15', 'posY' => '5'),
	array('posX' => '0', 'posY' => '7'),
	array('posX' => '4', 'posY' => '9'),
	array('posX' => '8', 'posY' => '10'),
	array('posX' => '14', 'posY' => '10'),
);

$this->leaderNames = array(
	'red' => 'Priest',
	'blue' => 'Farmer',
	'green' => 'Trader',
	'black' => 'King',
);

$this->tileNames = array(
	'red' => 'Temple',
	'blue' => 'Farm',
	'green' => 'Market',
	'black' => 'Settlement',
	'catastrophe' => 'Catastrophe',
);
