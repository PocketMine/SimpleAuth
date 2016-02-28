<?php

/*
 * SimpleAuth plugin for PocketMine-MP
 * Copyright (C) 2015 PocketMine Team <https://github.com/PocketMine/SimpleAuth>
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
namespace SimpleAuth\task;

use pocketmine\scheduler\PluginTask;
use SimpleAuth\SimpleAuth;

/**
 * Allows the creation of simple callbacks with extra data
 * The last parameter in the callback will be this object
 *
 */
class TimeoutTask extends PluginTask{

	/** @var player */
	protected $player;

	public function __construct(SimpleAuth $plugin,$player){
		parent::__construct($plugin);
		$this->player = $player->getName();
	}
	/**
	 * @return SimpleAuth
	 */
	public function getPlugin(){
		return $this->owner;
	}

	public function onRun($currentTicks){
		$plugin = $this->getPlugin();
		if($plugin->isDisabled()){
			return;
		}
		$player = $plugin->getServer()->getPlayer($this->player);
		if($player !== null){
			if (!$plugin->isPlayerAuthenticated($player)) {
				$player->kick($plugin->getMessage("login.error.timeout"));
			}
		}
	}
}
