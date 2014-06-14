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

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\IPlayer;
use pocketmine\permission\PermissionAttachment;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use SimpleAuth\event\PlayerAuthenticateEvent;
use SimpleAuth\event\PlayerDeauthenticateEvent;
use SimpleAuth\event\PlayerRegisterEvent;
use SimpleAuth\event\PlayerUnregisterEvent;
use SimpleAuth\provider\DataProvider;
use SimpleAuth\provider\DummyDataProvider;
use SimpleAuth\provider\SQLite3DataProvider;
use SimpleAuth\provider\YAMLDataProvider;

class SimpleAuth extends PluginBase{

	/** @var PermissionAttachment[] */
	protected $needAuth = [];

	/** @var EventListener */
	protected $listener;

	/** @var DataProvider */
	protected $provider;

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
	 * @param IPlayer $player
	 *
	 * @return bool
	 */
	public function isPlayerRegistered(IPlayer $player){
		return $this->provider->isPlayerRegistered($player);
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

		$player->sendMessage(TextFormat::GREEN . "You have been authenticated.");

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

		$player->sendMessage(TextFormat::RED . "You are not authenticated anymore.");

		return true;
	}

	/**
	 * @api
	 *
	 * @param IPlayer $player
	 * @param string  $password
	 *
	 * @return bool
	 */
	public function registerPlayer(IPlayer $player, $password){
		if(!$this->isPlayerRegistered($player)){
			$this->getServer()->getPluginManager()->callEvent($ev = new PlayerRegisterEvent($this, $player));
			if($ev->isCancelled()){
				return false;
			}
			$this->provider->registerPlayer($player, $this->hash($player->getName(), $password));
			return true;
		}
		return false;
	}

	/**
	 * @api
	 *
	 * @param IPlayer $player
	 *
	 * @return bool
	 */
	public function unregisterPlayer(IPlayer $player){
		if($this->isPlayerRegistered($player)){
			$this->getServer()->getPluginManager()->callEvent($ev = new PlayerUnregisterEvent($this, $player));
			if($ev->isCancelled()){
				return false;
			}
			$this->provider->unregisterPlayer($player);
		}

		return true;
	}

	public function setDataProvider(DataProvider $provider){
		$this->provider = $provider;
	}

	/* -------------------------- Non-API part -------------------------- */

	public function closePlayer(Player $player){
		unset($this->needAuth[spl_object_hash($player)]);
	}

	public function sendAuthenticateMessage(Player $player){
		//TODO: multilang (when implemented in PocketMine-MP)
		$config = $this->provider->getPlayer($player);
		$player->sendMessage("This server uses SimpleAuth. You must authenticate to play.");
		if($config === null){
			$player->sendMessage("Register your account with: /register <password>");
		}else{
			$player->sendMessage("Log in to your account with: /login <password>");
		}
	}

	public function onCommand(CommandSender $sender, Command $command, $label, array $args){
		switch($command->getName()){
			case "login":
				if($sender instanceof Player){
					if(!$this->isPlayerRegistered($sender) or ($data = $this->provider->getPlayer($sender)) === null){
						$sender->sendMessage(TextFormat::RED . "This account is not registered.");

						return true;
					}
					if(count($args) !== 1){
						$sender->sendMessage(TextFormat::RED . "Usage: " . $command->getUsage());

						return true;
					}

					$password = implode(" ", $args);

					if($this->hash(strtolower($sender->getName()), $password) === $data["hash"] and $this->authenticatePlayer($sender)){
						return true;
					}else{
						$sender->sendMessage(TextFormat::RED . "Error during authentication.");

						return true;
					}
				}else{
					$sender->sendMessage(TextFormat::RED . "This command only works in-game.");

					return true;
				}
				break;
			case "register":
				if($sender instanceof Player){
					if($this->isPlayerRegistered($sender)){
						$sender->sendMessage(TextFormat::RED . "This account is already registered.");

						return true;
					}

					if($this->getConfig()->get("allowRegister") === false){
						$sender->sendMessage("Registrations are disabled.");
						return true;
					}

					$password = implode(" ", $args);
					if(strlen($password) < $this->getConfig()->get("minPasswordLength")){
						$sender->sendMessage("Your password is too short.");
						return true;
					}

					if($this->registerPlayer($sender, $password) and $this->authenticatePlayer($sender)){
						return true;
					}else{
						$sender->sendMessage(TextFormat::RED . "Error during authentication.");
						return true;
					}
				}else{
					$sender->sendMessage(TextFormat::RED . "This command only works in-game.");

					return true;
				}
				break;
		}

		return false;
	}

	public function onEnable(){
		$this->saveDefaultConfig();
		$this->reloadConfig();

		$provider = $this->getConfig()->get("dataProvider");
		switch(strtolower($provider)){
			case "yaml":
				$this->getLogger()->debug("Using YAML data provider");
				$this->provider = new YAMLDataProvider($this);
				break;
			case "sqlite3":
				$this->getLogger()->debug("Using SQLite3 data provider");
				$this->provider = new SQLite3DataProvider($this);
				break;
			case "mysql":
				//TODO
			case "none":
			default:
				$this->provider = new DummyDataProvider($this);
				break;
		}

		$this->listener = new EventListener($this);
		$this->getServer()->getPluginManager()->registerEvents($this->listener, $this);

		foreach($this->getServer()->getOnlinePlayers() as $player){
			$this->deauthenticatePlayer($player);
		}

		$this->getLogger()->info("Everything loaded!");
	}

	public function onDisable(){
		$this->getServer()->getPluginManager();
		$this->provider->close();
	}

	protected function removePermissions(PermissionAttachment $attachment){
		$attachment->setPermission("pocketmine", false);
		$attachment->setPermission("pocketmine.command.help", true);

		//Do this because of permision manager plugins
		$attachment->setPermission("simpleauth.command.login", true);
		$attachment->setPermission("simpleauth.command.register", true);
	}

	/**
	 * Uses SHA-512 [http://en.wikipedia.org/wiki/SHA-2] an Whirlpool [http://en.wikipedia.org/wiki/Whirlpool_(cryptography)]
	 *
	 * Both of them have an output of 512 bits. Even if one of them is broken in the future, you have to break both of them
	 * at the same time due to being hashed separately and then XORed to mix their results equally.
	 *
	 * @param string $salt
	 * @param string $password
	 *
	 * @return string[1024] hex 512-bit hash
	 */
	private function hash($salt, $password){
		return bin2hex(hash("sha512", $password . $salt, true) ^ hash("whirlpool", $salt . $password, true));
	}
}