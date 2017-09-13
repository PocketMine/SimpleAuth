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

namespace SimpleAuth\provider;

use pocketmine\IPlayer;
use pocketmine\OfflinePlayer;
use pocketmine\Player;
use pocketmine\utils\Config;
use SimpleAuth\SimpleAuth;

class YAMLDataProvider implements DataProvider{

    /** @var SimpleAuth */
    protected $plugin;

    public function __construct(SimpleAuth $plugin){
        $this->plugin = $plugin;
        if(!file_exists($this->plugin->getDataFolder() . "players/")){
            @mkdir($this->plugin->getDataFolder() . "players/");
        }
    }

    public function getPlayerData(string $name){
        $name = trim(strtolower($name));
        if($name === ""){
            return null;
        }
        $path = $this->plugin->getDataFolder() . "players/" . $name{0} . "/$name.yml";
        if(!file_exists($path)){
            return null;
        }else{
            $config = new Config($path, Config::YAML);
            return $config->getAll();
        }
    }

    public function isPlayerRegistered(IPlayer $player){
        $name = trim(strtolower($player->getName()));

        return file_exists($this->plugin->getDataFolder() . "players/" . $name{0} . "/$name.yml");
    }

    public function unregisterPlayer(IPlayer $player){
        $name = trim(strtolower($player->getName()));
        @unlink($this->plugin->getDataFolder() . "players/" . $name{0} . "/$name.yml");
    }

    public function registerPlayer(IPlayer $player, $hash){
        $name = trim(strtolower($player->getName()));
        @mkdir($this->plugin->getDataFolder() . "players/" . $name{0} . "/");
        $data = new Config($this->plugin->getDataFolder() . "players/" . $name{0} . "/$name.yml", Config::YAML);
        $data->set("registerdate", time());
        $data->set("logindate", time());
        $data->set("hash", $hash);
        $data->save();

        return $data->getAll();
    }

    public function savePlayer(string $name, array $config){
        $name = trim(strtolower($name));
        $data = new Config($this->plugin->getDataFolder() . "players/" . $name{0} . "/$name.yml", Config::YAML);
        $data->setAll($config);
        $data->save();
    }

    public function updatePlayer(IPlayer $player, string $lastIP = null, string $ip = null, int $loginDate = null, string $skinhash = null, int $pin = null, string $linkedign = null) : bool{
        $data = $this->getPlayerData($player->getName());
        if($data !== null){
            if($ip !== null){
                $data["ip"] = $ip;
            }
            if($lastIP !== null){
                $data["lastip"] = $lastIP;
            }
            if($loginDate !== null){
                $data["logindate"] = $loginDate;
            }
            if($skinhash !== null){
                $data["skinhash"] = $skinhash;
            }
            if($pin !== null){
                $data["pin"] = $pin;
            }
            if($linkedign !== null){
                $data["linkedign"] = $linkedign;
            }
            if(isset($pin) && $pin === 0){
                unset($data["pin"]);
            }

            $this->savePlayer($player->getName(), $data);
        }
        return true;
    }

    public function getLinked(string $name){
        $name = trim(strtolower($name));
        $data = $this->getPlayerData($name);
        if(isset($data["linkedign"]) && $data["linkedign"] !== ""){
            return $data["linkedign"];
        }
        return null;
    }

    public function linkXBL(Player $sender, OfflinePlayer $oldPlayer, string $oldIGN){
        $success = $this->updatePlayer($sender, null, null, null, null, null, $oldIGN);
        $success = $success && $this->updatePlayer($oldPlayer, null, null, null, null, null, $sender->getName());
        return $success;
    }

    public function unlinkXBL(Player $player){
        $xblIGN = $this->getLinked($player->getName());
        $pmIGN = $this->getLinked($xblIGN);

        $xbldata = $this->getPlayerData($xblIGN);
        $pmdata = $this->getPlayerData($pmIGN);

        if(isset($xblIGN) && $xblIGN !== "" && isset($xbldata) && isset($pmdata)){
            unset($pmdata["linkedign"]);
            $this->savePlayer($pmIGN, $pmdata);
            unset($xbldata["linkedign"]);
            $this->savePlayer($xblIGN, $xbldata);
            return $xblIGN;
        }else return null;
    }

    public function isDBLinkingReady() : bool{
        return true;
    }

    public function close(){

    }
}