<?php
namespace TAE\Core;
use tigriseuphrates;

/*
 * Game: a wrapper over table object to allow more generic modules
 */
class Game {
	public static function get() {
		return tigriseuphrates::get();
	}

	public static function getStateName() {
		return tigriseuphrates::get()->gamestate->state()['name'];
	}
}
