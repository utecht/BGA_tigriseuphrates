<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * TigrisEuphrates implementation : © Joseph Utecht <joseph@utecht.co>
 *
 * This code has been produced on the BGA studio platform for use on https://boardgamearena.com.
 * See http://en.doc.boardgamearena.com/Studio for more information.
 * -----
 *
 * tigriseuphrates.action.php
 *
 */

class action_tigriseuphrates extends APP_GameAction {
	// Constructor: please do not modify
	public function __default() {
		if (self::isArg('notifwindow')) {
			$this->view = "common_notifwindow";
			$this->viewArgs['table'] = self::getArg("table", AT_posint, true);
		} else {
			$this->view = "tigriseuphrates_tigriseuphrates";
			self::trace("Complete reinitialization of board game");
		}
	}

	public function placeTile() {
		self::setAjaxMode();

		$tile_id = self::getArg("tile_id", AT_posint, true);
		$pos_x = self::getArg("pos_x", AT_posint, true);
		$pos_y = self::getArg("pos_y", AT_posint, true);

		$this->game->placeTile($tile_id, $pos_x, $pos_y);

		self::ajaxResponse();
	}

	public function placeLeader() {
		self::setAjaxMode();

		$leader_id = self::getArg("leader_id", AT_posint, true);
		$pos_x = self::getArg("pos_x", AT_posint, true);
		$pos_y = self::getArg("pos_y", AT_posint, true);

		$this->game->placeLeader($leader_id, $pos_x, $pos_y);

		self::ajaxResponse();
	}

	public function selectMonument() {
		self::setAjaxMode();

		$monument_id = self::getArg("monument_id", AT_posint, true);

		$this->game->selectMonument($monument_id);

		self::ajaxResponse();
	}

	public function selectMonumentTile() {
		self::setAjaxMode();

		$pos_x = self::getArg("pos_x", AT_posint, true);
		$pos_y = self::getArg("pos_y", AT_posint, true);

		$this->game->selectMonumentTile($pos_x, $pos_y);

		self::ajaxResponse();
	}

	public function selectWonderTile() {
		self::setAjaxMode();

		$pos_x = self::getArg("pos_x", AT_posint, true);
		$pos_y = self::getArg("pos_y", AT_posint, true);

		$this->game->selectWonderTile($pos_x, $pos_y);

		self::ajaxResponse();
	}

	public function pickTreasure() {
		self::setAjaxMode();

		$pos_x = self::getArg("pos_x", AT_posint, true);
		$pos_y = self::getArg("pos_y", AT_posint, true);

		$this->game->pickTreasure($pos_x, $pos_y);

		self::ajaxResponse();
	}

	public function pickPoint() {
		self::setAjaxMode();

		$point_color = self::getArg("color", AT_alphanum, true);

		$this->game->pickPoint($point_color);

		self::ajaxResponse();
	}

	public function placeSupport() {
		self::setAjaxMode();

		$support_ids_raw = self::getArg("support_ids", AT_numberlist, true);
		// convert number list to array
		if (substr($support_ids_raw, -1) == ',') {
			$support_ids_raw = substr($support_ids_raw, 0, -1);
		}
		if ($support_ids_raw == '') {
			$support_ids = array();
		} else {
			$support_ids = explode(',', $support_ids_raw);
		}

		$this->game->placeSupport($support_ids);

		self::ajaxResponse();
	}

	public function discardTiles() {
		self::setAjaxMode();

		$tile_ids_raw = self::getArg("tile_ids", AT_numberlist, true);
		// convert number list to array
		if (substr($tile_ids_raw, -1) == ',') {
			$tile_ids_raw = substr($tile_ids_raw, 0, -1);
		}
		if ($tile_ids_raw == '') {
			$tile_ids = array();
		} else {
			$tile_ids = explode(',', $tile_ids_raw);
		}

		$this->game->discard($tile_ids);

		self::ajaxResponse();
	}

	public function selectWarLeader() {
		self::setAjaxMode();

		$leader_id = self::getArg("leader_id", AT_posint, true);

		$this->game->selectWarLeader($leader_id);

		self::ajaxResponse();
	}

	public function pickupLeader() {
		self::setAjaxMode();

		$leader_id = self::getArg("leader_id", AT_posint, true);

		$this->game->pickupLeader($leader_id);

		self::ajaxResponse();
	}

	public function pass() {
		self::setAjaxMode();

		$this->game->pass();

		self::ajaxResponse();
	}

	public function undo() {
		self::setAjaxMode();

		$this->game->undo();

		self::ajaxResponse();
	}

	public function confirm() {
		self::setAjaxMode();

		$this->game->confirm();

		self::ajaxResponse();
	}

	public function buildCivilizationBuilding() {
		self::setAjaxMode();

		$pos_x = self::getArg("pos_x", AT_posint, true);
		$pos_y = self::getArg("pos_y", AT_posint, true);

		$this->game->buildCivilizationBuilding($pos_x, $pos_y);

		self::ajaxResponse();
	}

	public function loadBugSQL() {
		self::setAjaxMode();
		$reportId = (int) self::getArg('report_id', AT_int, true);
		$this->game->loadBugSQL($reportId);
		self::ajaxResponse();
	}

}
