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

namespace SimpleAuth;

use pocketmine\permission\PermissionAttachment;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use SimpleAuth\event\PlayerAuthenticateEvent;
use SimpleAuth\event\PlayerDeauthenticateEvent;

class SimpleAuth extends PluginBase{

	/** @var PermissionAttachment[] */
	protected $needAuth = [];

	/** @var EventListener */
	protected $listener;

	/**
	 * @api
	 *
	 * @param Player $player
	 *
	 * @return bool
	 */
	public function isPlayerAuthenticated(Player $player){
		return !isset($this->needAuth[spl_object_hash($player)]);
	}

	/**
	 * @api
	 *
	 * @param Player $player
	 *
	 * @return bool True if call not blocked
	 */
	public function authenticatePlayer(Player $player){
		if($this->isPlayerAuthenticated($player)){
			return true;
		}

		$this->getServer()->getPluginManager()->callEvent($ev = new PlayerAuthenticateEvent($this, $player));
		if($ev->isCancelled()){
			return false;
		}

		if(isset($this->needAuth[spl_object_hash($player)])){
			$attachment = $this->needAuth[spl_object_hash($player)];
			$attachment->unsetPermission("pocketmine");
			$player->removeAttachment($attachment);
			unset($this->needAuth[spl_object_hash($player)]);
		}

		return true;
	}

	/**
	 * @api
	 *
	 * @param Player $player
	 *
	 * @return bool True if call not blocked
	 */
	public function deauthenticatePlayer(Player $player){
		if(!$this->isPlayerAuthenticated($player)){
			return true;
		}

		$this->getServer()->getPluginManager()->callEvent($ev = new PlayerDeauthenticateEvent($this, $player));
		if($ev->isCancelled()){
			return false;
		}

		$attachment = $player->addAttachment($this);
		$this->removePermissions($attachment);
		$this->needAuth[spl_object_hash($player)] = $attachment;

		return true;
	}

	/* -------------------------- Non-API part -------------------------- */

	public function closePlayer(Player $player){
		unset($this->needAuth[spl_object_hash($player)]);
	}

	public function sendAuthenticateMessage(Player $player){
		//TODO: multilang (when implemented in PocketMine-MP)
		$config = $this->getPlayer($player->getName());
		$player->sendMessage("This server uses SimpleAuth. You must authenticate to play.");
		if($config === null){
			$player->sendMessage("Register your account with: /register <password>");
		}else{
			$player->sendMessage("Log in to your account with: /login <password>");
		}
	}

	public function onEnable(){
		$this->saveDefaultConfig();
		$this->reloadConfig();
		if(!file_exists($this->getDataFolder() . "players/")){
			@mkdir($this->getDataFolder() . "players/");
		}

		$this->listener = new EventListener($this);
		$this->getServer()->getPluginManager()->registerEvents($this->listener, $this);

		foreach($this->getServer()->getOnlinePlayers() as $player){
			$this->deauthenticatePlayer($player);
		}

		$this->getLogger()->info("Everything loaded!");
	}

	protected function removePermissions(PermissionAttachment $attachment){
		$attachment->setPermission("pocketmine", false);
	}

	public function onDisable(){
		$this->saveConfig();
	}

	/**
	 * @param string $name
	 * @param Config $config
	 */
	protected function savePlayer($name, Config $config){
		$name = trim(strtolower($name));
		if($name === ""){
			return;
		}
		$path = $this->getDataFolder() . "players/".$name{0}."/";
		if(!file_exists($path)){
			@mkdir($path, 0755, true);
		}
	}

	/**
	 * @param string $name
	 *
	 * @return Config
	 */
	protected function getPlayer($name){
		$name = trim(strtolower($name));
		if($name === ""){
			return null;
		}
		$path = $this->getDataFolder() . "players/".$name{0}."/$name.yml";
		if(!file_exists($path)){
			return null;
		}else{
			return new Config($path, Config::YAML);
		}
	}
}