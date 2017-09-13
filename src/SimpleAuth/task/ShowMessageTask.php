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

namespace SimpleAuth\task;

use pocketmine\Player;
use pocketmine\scheduler\PluginTask;
use pocketmine\utils\TextFormat;
use SimpleAuth\SimpleAuth;
use pocketmine\plugin\Plugin;

class ShowMessageTask extends PluginTask{

	/** @var Player[] */
	private $playerList = [];

	public function __construct(SimpleAuth $plugin){
		parent::__construct($plugin);
	}

	/**
	 * @return SimpleAuth
	 */
	public function getPlugin() : Plugin{
		return $this->owner;
	}

	public function addPlayer(Player $player){
		$this->playerList[$player->getUniqueId()->toString()] = $player;
	}

	public function removePlayer(Player $player){
	if (null !== $player->getUniqueId()){
		unset($this->playerList[$player->getUniqueId()->toString()]);
		}
	}

	public function onRun(int $currentTick){
		$plugin = $this->getPlugin();
		if($plugin->isDisabled()){
			return;
		}

		foreach($this->playerList as $player){
			if($player==null){
				continue;
			}
			$player->sendPopup(TextFormat::ITALIC . TextFormat::GRAY . $this->getPlugin()->getMessage("join.popup"));
		}
	}

}