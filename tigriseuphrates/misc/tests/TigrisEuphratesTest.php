<?php
error_reporting(E_ALL ^ E_DEPRECATED);
define("APP_GAMEMODULE_PATH", "./tigriseuphrates/misc/"); // include path to mocks, this defined "Table" and other classes

use PHPUnit\Framework\TestCase;

class TigrisEuphratesMocker extends TigrisEuphrates {

	function __construct() {
		parent::__construct();
		include 'tigriseuphrates/material.inc.php';
		$this->resources = array();
	}
}

final class TigrisEuphratesTest extends TestCase {
	private static $te;
	public static function setUpBeforeClass(): void{
		self::$te = new TigrisEuphratesMocker();
	}

	public function testExists() {
		$this->assertInstanceOf(TigrisEuphratesMocker::class, self::$te);
	}

	public function testCords() {
		$this->assertEquals('A1', self::$te::toCoords(0, 0));
	}

}
