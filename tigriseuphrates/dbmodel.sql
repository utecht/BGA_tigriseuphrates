
-- ------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- TigrisEuphrates implementation : © Joseph Utecht <joseph@utecht.co>
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-- -----

-- dbmodel.sql

CREATE TABLE `tile` (
	`id` INT(11) NOT NULL ,
	`state` ENUM('board','bag','discard','hand','support') NOT NULL ,
	`owner` int(11) DEFAULT NULL,
	`kind` ENUM('green','blue','black','red','catastrophe') NOT NULL ,
	`posX` INT(11) DEFAULT NULL,
	`posY` INT(11) DEFAULT NULL,
	`flipped` TINYINT(1) NOT NULL DEFAULT '0' ,
	`hasAmulet` TINYINT(1) NOT NULL DEFAULT '0' ,
	`isUnion` TINYINT(1) NOT NULL DEFAULT '0' ,
PRIMARY KEY (`id`)) ENGINE = InnoDB;

CREATE TABLE `leader` (
	`id` INT(11) NOT NULL,
	`shape` ENUM('goat','urn','lion','bow') NOT NULL,
	`kind` ENUM('green','blue','black','red') NOT NULL,
	`owner` int(11) DEFAULT NULL,
	`posX` INT(11) DEFAULT NULL,
	`posY` INT(11) DEFAULT NULL,
	`onBoard` TINYINT(1) NOT NULL DEFAULT '0' ,
PRIMARY KEY (`id`)) ENGINE = InnoDB;

CREATE TABLE `momnument` (
	`id` INT(11) NOT NULL ,
	`color1` ENUM('green','blue','black','red') NOT NULL ,
	`color2` ENUM('green','blue','black','red') NOT NULL ,
	`posX` INT(11) DEFAULT NULL,
	`posY` INT(11) DEFAULT NULL,
	`onBoard` TINYINT(1) NOT NULL DEFAULT '0' ,
PRIMARY KEY (`id`)) ENGINE = InnoDB;

CREATE TABLE `point` (
	`player` INT(11) NOT NULL ,
	`green` INT(11) DEFAULT NULL,
	`blue` INT(11) DEFAULT NULL,
	`black` INT(11) DEFAULT NULL,
	`red` INT(11) DEFAULT NULL,
	`amulet` INT(11) DEFAULT NULL,
PRIMARY KEY (`player`)) ENGINE = InnoDB;
