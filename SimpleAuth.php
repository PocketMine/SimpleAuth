<?php

/*
__PocketMine Plugin__
name=SimpleAuth
description=Prevents people to impersonate an account, requiring registration and login when connecting.
version=0.2
author=shoghicp
class=SimpleAuth
apiversion=9,10
*/

/*

Changelog:

0.2:
* Refuse short passwords
* Added simpleauth.login, simpleauth.register, simpleauth.logout handler events

0.1:
* Initial release

*/

class SimpleAuth implements Plugin{
	private $api, $server, $config, $sessions, $lastBroadcast = 0;
	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
		$this->server = ServerAPI::request();
		$this->sessions = array();
		SimpleAuthAPI::set($this);
	}
	
	public function init(){
		$this->config = new Config($this->api->plugin->configPath($this)."config.yml", CONFIG_YAML, array(
			"allowChat" => false,
			"messageInterval" => 5,
			"timeout" => 60,
			"allowRegister" => true,
			"forceSingleSession" => true,
		));
		$this->playerFile = new Config($this->api->plugin->configPath($this)."players.yml", CONFIG_YAML, array());
		
		$this->api->addHandler("player.quit", array($this, "eventHandler"), 50);
		$this->api->addHandler("player.connect", array($this, "eventHandler"), 50);
		$this->api->addHandler("player.spawn", array($this, "eventHandler"), 50);
		$this->api->addHandler("player.respawn", array($this, "eventHandler"), 50);
		$this->api->addHandler("player.chat", array($this, "eventHandler"), 50);
		$this->api->addHandler("console.command", array($this, "eventHandler"), 50);
		$this->api->addHandler("op.check", array($this, "eventHandler"), 50);
		$this->api->schedule(20, array($this, "checkTimer"), array(), true);
		$this->api->console->register("unregister", "<password>", array($this, "commandHandler"));
		$this->api->ban->cmdWhitelist("unregister");
		console("[INFO] SimpleAuth enabled!");
	}
	
	public function commandHandler($cmd, $params, $issuer, $alias){
		$output = "";
		switch($cmd){
			case "unregister":
				if(!($issuer instanceof Player)){
					$output .= "Please run this command inside the game.\n";
					break;
				}
				if($this->sessions[$issuer->CID] !== true){
					$output .= "Please login first.\n";
					break;
				}
				$d = $this->playerFile->get($issuer->iusername);
				if($d !== false and $d["hash"] === $this->hash($issuer->iusername, implode(" ", $params))){
					$this->playerFile->remove($issuer->iusername);
					$this->logout($issuer);
					$output .= "[SimpleAuth] Unregistered correctly.\n";
				}else{
					$output .= "[SimpleAuth] Error during authentication.\n";
				}
				break;
		}
		return $output;
	}
	
	public function checkTimer(){
		if($this->config->get("allowRegister") !== false and ($this->lastBroadcast + $this->config->get("messageInterval")) <= time()){
			$broadcast = true;
			$this->lastBroadcast = time();
		}else{
			$broadcast = false;
		}
		
		if(($timeout = $this->config->get("timeout")) <= 0){
			$timeout = false;
		}
		
		foreach($this->sessions as $CID => $timer){
			if($timer !== true and $timer !== false){				
				if($broadcast === true){
					$d = $this->playerFile->get($this->server->clients[$CID]->iusername);
					if($d === false){					
						$this->server->clients[$CID]->sendChat("[SimpleAuth] You must register using /register <password>");
					}else{
						$this->server->clients[$CID]->sendChat("[SimpleAuth] You must authenticate using /login <password>");
					}
				}
				if($timeout !== false and ($timer + $timeout) <= time()){
					$this->server->clients[$CID]->close("authentication timeout");
				}
			}
		}
		
	}
	
	private function hash($salt, $password){
		return Utils::strToHex(hash("sha512", $password . $salt, true) ^ hash("whirlpool", $salt . $password, true));
	}
	
	public function checkLogin(Player $player, $password){
		$d = $this->playerFile->get($player->iusername);
		if($d !== false and $d["hash"] === $this->hash($player->iusername, $password)){
			return true;
		}
		return false;
	}
	
	public function login(Player $player){
		$d = $this->playerFile->get($player->iusername);
		if($d !== false){
			$d["logindate"] = time();
			$this->playerFile->set($player->iusername, $d);
			$this->playerFile->save();
		}
		$this->sessions[$player->CID] = true;
		$player->blocked = false;
		$player->sendChat("[SimpleAuth] You've been authenticated.");
		$this->server->handle("simpleauth.login", $player);
		return true;
	}
	
	public function logout(Player $player){
		$this->sessions[$player->CID] = time();
		$player->blocked = true;
		$this->server->handle("simpleauth.logout", $player);
	}
	
	public function register(Player $player, $password){	
		$d = $this->playerFile->get($player->iusername);
		if($d === false){
			$data = array(
				"registerdate" => time(),
				"logindate" => time(),
				"hash" => $this->hash($player->iusername, $password),
			);
			$this->playerFile->set($player->iusername, $data);
			$this->playerFile->save();
			$this->server->handle("simpleauth.register", $player);
			return true;
		}
		return false;
	}
	
	public function eventHandler($data, $event){
		switch($event){
			case "player.quit":
				unset($this->sessions[$data->CID]);
				break;
			case "player.connect":
				if($this->config->get("forceSingleSession") === true){
					$p = $this->api->player->get($data->iusername);
					if(($p instanceof Player) and $p->iusername === $data->iusername){
						return false;
					}
				}
				$this->sessions[$data->CID] = false;
				break;
			case "player.spawn":
				if($this->sessions[$data->CID] !== true){
					$this->sessions[$data->CID] = time();
					$data->blocked = true;
					$data->sendChat("[SimpleAuth] This server uses SimpleAuth to protect your account.");
					if($this->config->get("allowRegister") !== false){
						$d = $this->playerFile->get($data->iusername);
						if($d === false){					
							$data->sendChat("[SimpleAuth] You must register using /register <password>");
						}else{
							$data->sendChat("[SimpleAuth] You must authenticate using /login <password>");
						}
					}
				}
				break;
			case "console.command":
				if(($data["issuer"] instanceof Player) and $this->sessions[$data["issuer"]->CID] !== true){
					if($data["cmd"] === "login" and $this->checkLogin($data["issuer"], implode(" ", $data["parameters"])) === true){
						$this->login($data["issuer"]);
						return true;
					}elseif($data["cmd"] === "login" or $data["cmd"] === "register"){
						$data["issuer"]->sendChat("[SimpleAuth] Error during authentication.");
						return true;
					}elseif(strlen(implode(" ", $data["parameters"])) < 6){
						$data["issuer"]->sendChat("[SimpleAuth] Password is too short.");
						return true;
					}elseif($this->config->get("allowRegister") !== false and $data["cmd"] === "register" and $this->register($data["issuer"], implode(" ", $data["parameters"])) === true){
						$data["issuer"]->sendChat("[SimpleAuth] You've been sucesfully registered.");
						$this->login($data["issuer"]);
						return true;
					}
					return false;
				}
				break;
			case "player.chat":
				if($this->config->get("allowChat") !== true and $this->sessions[$data["player"]->CID] !== true){
					return false;
				}
				break;
			case "op.check":
				$p = $this->api->player->get($data);
				if(($p instanceof Player) and $this->sessions[$p->CID] !== true){
					return false;
				}
				break;
			case "player.respawn":
				if($this->sessions[$data->CID] !== true){
					$data->blocked = true;
				}
				break;
		}
	}
	
	public function __destruct(){
		$this->config->save();
		$this->playerFile->save();
	}

}

class SimpleAuthAPI{
	private static $object;
	public static function set(SimpleAuth $plugin){
		if(SimpleAuthAPI::$object instanceof SimpleAuth){
			return false;
		}
		SimpleAuthAPI::$object = $plugin;
	}
	
	public static function get(){
		return SimpleAuthAPI::$object;
	}
	
	public static function login(Player $player){
		return SimpleAuthAPI::$object->login($player);
	}
	
	public static function logout(Player $player){
		return SimpleAuthAPI::$object->logout($player);
	}
}
