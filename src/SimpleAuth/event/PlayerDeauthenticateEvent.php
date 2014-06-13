<?php

/*
 * SimpleAuth plugin for PocketMine-MP
 * Copyright (C) 2014 PocketMine Team <https://github.com/PocketMine/SimpleAuth>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
*/

namespace SimpleAuth\event;

use pocketmine\event\Cancellable;
use pocketmine\Player;
use SimpleAuth\SimpleAuth;

class PlayerDeauthenticateEvent extends SimpleAuthEvent implements Cancellable{
	public static $handlerList = null;


	/** @var Player */
	private $player;

	/**
	 * @param SimpleAuth $plugin
	 * @param Player     $player
	 */
	public function __construct(SimpleAuth $plugin, Player $player){
		$this->player = $player;
		parent::__construct($plugin);
	}

	/**
	 * @return Player
	 */
	public function getPlayer(){
		return $this->player;
	}
}