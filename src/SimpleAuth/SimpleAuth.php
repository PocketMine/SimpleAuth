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
use pocketmine\utils\Config;
use pocketmine\permission\PermissionAttachment;
use pocketmine\permission\Permission;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use SimpleAuth\event\PlayerAuthenticateEvent;
use SimpleAuth\event\PlayerDeauthenticateEvent;
use SimpleAuth\event\PlayerRegisterEvent;
use SimpleAuth\event\PlayerUnregisterEvent;
use SimpleAuth\provider\DataProvider;
use SimpleAuth\provider\DummyDataProvider;
use SimpleAuth\provider\MySQLDataProvider;
use SimpleAuth\provider\SQLite3DataProvider;
use SimpleAuth\provider\YAMLDataProvider;

class SimpleAuth extends PluginBase{

	/** @var PermissionAttachment[] */
	protected $needAuth = [];

	/** @var EventListener */
	protected $listener;

	/** @var DataProvider */
	protected $provider;

	/** @var string[] */
	protected $messages = [];

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
			$player->removeAttachment($attachment);
			unset($this->needAuth[spl_object_hash($player)]);
		}
		$this->provider->updatePlayer($player, $player->getAddress(), time());
		$player->sendMessage(TextFormat::GREEN . $this->getMessage("login.success"));

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

		$this->sendAuthenticateMessage($player);

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
			$this->provider->registerPlayer($player, $this->hash(strtolower($player->getName()), $password));
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

	/**
	 * @api
	 *
	 * @param DataProvider $provider
	 */
	public function setDataProvider(DataProvider $provider){
		$this->provider = $provider;
	}

	/**
	 * @api
	 *
	 * @return DataProvider
	 */
	public function getDataProvider(){
		return $this->provider;
	}

	/* -------------------------- Non-API part -------------------------- */

	public function closePlayer(Player $player){
		unset($this->needAuth[spl_object_hash($player)]);
	}

	public function sendAuthenticateMessage(Player $player){
		$config = $this->provider->getPlayer($player);
		$player->sendMessage($this->getMessage("join.message"));
		if($config === null){
			$player->sendMessage($this->getMessage("join.register"));
		}else{
			$player->sendMessage($this->getMessage("join.login"));
		}
	}

	public function onCommand(CommandSender $sender, Command $command, $label, array $args){
		switch($command->getName()){
			case "login":
				if($sender instanceof Player){
					if(!$this->isPlayerRegistered($sender) or ($data = $this->provider->getPlayer($sender)) === null){
						$sender->sendMessage(TextFormat::RED . $this->getMessage("login.error.registered"));

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
						$sender->sendMessage(TextFormat::RED . $this->getMessage("login.error.password"));

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
						$sender->sendMessage(TextFormat::RED . $this->getMessage("register.error.registered"));

						return true;
					}

					$password = implode(" ", $args);
					if(strlen($password) < $this->getConfig()->get("minPasswordLength")){
						$sender->sendMessage($this->getMessage("register.error.password"));
						return true;
					}

					if($this->registerPlayer($sender, $password) and $this->authenticatePlayer($sender)){
						return true;
					}else{
						$sender->sendMessage(TextFormat::RED . $this->getMessage("register.error.general"));
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

	private function parseMessages(array $messages){
		$result = [];
		foreach($messages as $key => $value){
			if(is_array($value)){
				foreach($this->parseMessages($value) as $k => $v){
					$result[$key . "." . $k] = $v;
				}
			}else{
				$result[$key] = $value;
			}
		}

		return $result;
	}

	public function getMessage($key){
		return isset($this->messages[$key]) ? $this->messages[$key] : $key;
	}

	public function onEnable(){
		$this->saveDefaultConfig();
		$this->reloadConfig();

		$this->saveResource("messages.yml", false);

		$messages = (new Config($this->getDataFolder() . "messages.yml"))->getAll();

		$this->messages = $this->parseMessages($messages);

		$registerCommand = $this->getCommand("register");
		$registerCommand->setUsage($this->getMessage("register.usage"));
		$registerCommand->setDescription($this->getMessage("register.description"));
		$registerCommand->setPermissionMessage($this->getMessage("register.permission"));

		$loginCommand = $this->getCommand("login");
		$loginCommand->setUsage($this->getMessage("login.usage"));
		$loginCommand->setDescription($this->getMessage("login.description"));
		$loginCommand->setPermissionMessage($this->getMessage("login.permission"));

		$provider = $this->getConfig()->get("dataProvider");
		unset($this->provider);
		switch(strtolower($provider)){
			case "yaml":
				$this->getLogger()->debug("Using YAML data provider");
				$provider = new YAMLDataProvider($this);
				break;
			case "sqlite3":
				$this->getLogger()->debug("Using SQLite3 data provider");
				$provider = new SQLite3DataProvider($this);
				break;
			case "mysql":
				$this->getLogger()->debug("Using MySQL data provider");
				$provider = new MySQLDataProvider($this);
				break;
			case "none":
			default:
				$provider = new DummyDataProvider($this);
				break;
		}

		if(!isset($this->provider) or !($this->provider instanceof DataProvider)){ //Fix for getting a Dummy provider
			$this->provider = $provider;
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

	public static function orderPermissionsCallback($perm1, $perm2){
		if(self::isChild($perm1, $perm2)){
			return -1;
		}elseif(self::isChild($perm2, $perm1)){
			return 1;
		}else{
			return 0;
		}
	}

	public static function isChild($perm, $name){
		$perm = explode(".", $perm);
		$name = explode(".", $name);

		foreach($perm as $k => $component){
			if(!isset($name[$k])){
				return false;
			}elseif($name[$k] !== $component){
				return false;
			}
		}

		return true;
	}

	protected function removePermissions(PermissionAttachment $attachment){
		$permissions = [];
		foreach($this->getServer()->getPluginManager()->getPermissions() as $permission){
			$permissions[$permission->getName()] = false;
		}

		$permissions["pocketmine.command.help"] = true;
		$permissions[Server::BROADCAST_CHANNEL_USERS] = true;
		$permissions[Server::BROADCAST_CHANNEL_ADMINISTRATIVE] = false;

		unset($permissions["simpleauth.chat"]);
		unset($permissions["simpleauth.move"]);
		unset($permissions["simpleauth.lastip"]);

		//Do this because of permission manager plugins
		if($this->getConfig()->get("disableRegister") === true){
			$permissions["simpleauth.command.register"] = false;
		}else{
			$permissions["simpleauth.command.register"] = true;
		}

		if($this->getConfig()->get("disableLogin") === true){
			$permissions["simpleauth.command.register"] = false;
		}else{
			$permissions["simpleauth.command.login"] = true;
		}

		uksort($permissions, [SimpleAuth::class, "orderPermissionsCallback"]); //Set them in the correct order

		$attachment->setPermissions($permissions);
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
	 * @return string[128] hex 512-bit hash
	 */
	private function hash($salt, $password){
		return bin2hex(hash("sha512", $password . $salt, true) ^ hash("whirlpool", $salt . $password, true));
	}
}
