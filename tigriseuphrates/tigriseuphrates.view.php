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
 * tigriseuphrates.view.php
 *
 * This is your "view" file.
 *
 * The method "build_page" below is called each time the game interface is displayed to a player, ie:
 * _ when the game starts
 * _ when a player refreshes the game page (F5)
 *
 * "build_page" method allows you to dynamically modify the HTML generated for the game interface. In
 * particular, you can set here the values of variables elements defined in tigriseuphrates_tigriseuphrates.tpl (elements
 * like {MY_VARIABLE_ELEMENT}), and insert HTML block elements (also defined in your HTML template file)
 *
 * Note: if the HTML of your game interface is always the same, you don't have to place anything here.
 *
 */

require_once APP_BASE_PATH . "view/common/game.view.php";

class view_tigriseuphrates_tigriseuphrates extends game_view {
	function getGameName() {
		return "tigriseuphrates";
	}
	function build_page($viewArgs) {
		// Get players & players number
		$players = $this->game->loadPlayersBasicInfos();
		$players_nbr = count($players);

		/*********** Place your code below:  ************/
		$this->page->begin_block("tigriseuphrates_tigriseuphrates", "kingdom");
		for ($x = 0; $x < 16; $x++) {
			for ($y = 0; $y < 11; $y++) {
				$this->page->insert_block("kingdom", array(
					'X' => $x,
					'Y' => $y,
					'LEFT' => 11 + ($x * 45),
					'TOP' => 22 + ($y * 45),
				));
			}
		}

		$this->page->begin_block("tigriseuphrates_tigriseuphrates", "space");
		for ($x = 0; $x < 16; $x++) {
			for ($y = 0; $y < 11; $y++) {
				$river_class = '';
				foreach ($this->game->rivers as $river) {
					if ($river['posX'] == $x && $river['posY'] == $y) {
						$river_class = 'river';
					}
				}
				$this->page->insert_block("space", array(
					'X' => $x,
					'Y' => $y,
					'LEFT' => 11 + ($x * 45),
					'TOP' => 22 + ($y * 45),
					'RIVER' => $river_class,
				));
			}
		}

		/*

			        // Examples: set the value of some element defined in your tpl file like this: {MY_VARIABLE_ELEMENT}

			        // Display a specific number / string
			        $this->tpl['MY_VARIABLE_ELEMENT'] = $number_to_display;

			        // Display a string to be translated in all languages:
			        $this->tpl['MY_VARIABLE_ELEMENT'] = self::_("A string to be translated");

			        // Display some HTML content of your own:
			        $this->tpl['MY_VARIABLE_ELEMENT'] = self::raw( $some_html_code );

		*/

		/*

			        // Example: display a specific HTML block for each player in this game.
			        // (note: the block is defined in your .tpl file like this:
			        //      <!-- BEGIN myblock -->
			        //          ... my HTML code ...
			        //      <!-- END myblock -->

			        $this->page->begin_block( "tigriseuphrates_tigriseuphrates", "myblock" );
			        foreach( $players as $player )
			        {
			            $this->page->insert_block( "myblock", array(
			                                                    "PLAYER_NAME" => $player['player_name'],
			                                                    "SOME_VARIABLE" => $some_value
			                                                    ...
			                                                     ) );
			        }

		*/

		/*********** Do not change anything below this line  ************/
	}
}
