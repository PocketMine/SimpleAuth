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
use pocketmine\Server;
use SimpleAuth\SimpleAuth;

class SQLite3DataProvider implements DataProvider{

    /** @var SimpleAuth */
    protected $plugin;

    /** @var \SQLite3 */
    protected $database;

    /** @var bool */
    private $linkingready;


    public function __construct(SimpleAuth $plugin){
        $this->plugin = $plugin;
        if(!file_exists($this->plugin->getDataFolder() . "players.db")){
            $this->database = new \SQLite3($this->plugin->getDataFolder() . "players.db", SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
            $resource = $this->plugin->getResource("sqlite3.sql");
            $this->database->exec(stream_get_contents($resource));
            fclose($resource);
        }else{
            $this->database = new \SQLite3($this->plugin->getDataFolder() . "players.db", SQLITE3_OPEN_READWRITE);
        }
        try{ // not great I know... if you can check if columns exist in sqlite, please tell me
            $prepare = $this->database->query("SELECT linkedign FROM players WHERE linkedign = 'shoghicp'");
            $this->linkingready = true;
        } catch(\Exception $e){
            $this->linkingready = false;
        }
    }

    public function getPlayerData(string $name){
        $name = trim(strtolower($name));
        $prepare = $this->database->prepare("SELECT * FROM players WHERE name = :name");
        $prepare->bindValue(":name", $name, SQLITE3_TEXT);
        $result = $prepare->execute();
        if($result instanceof \SQLite3Result){
            $data = $result->fetchArray(SQLITE3_ASSOC);
            $result->finalize();
            if(isset($data["name"]) and $data["name"] === $name){
                unset($data["name"]);
                $prepare->close();
                return $data;
            }
        }
        $prepare->close();
        return null;
    }

    public function isPlayerRegistered(IPlayer $player){
        return $this->getPlayerData($player->getName()) !== null;
    }

    public function unregisterPlayer(IPlayer $player){
        $name = trim(strtolower($player->getName()));
        $prepare = $this->database->prepare("DELETE FROM players WHERE name = :name");
        $prepare->bindValue(":name", $name, SQLITE3_TEXT);
        $prepare->execute();
    }

    public function registerPlayer(IPlayer $player, $hash){
        $name = trim(strtolower($player->getName()));
        $data = [
            "registerdate" => time(),
            "logindate" => time(),
            "hash" => $hash
        ];
        $prepare = $this->database->prepare("INSERT INTO players (name, registerdate, logindate, hash) VALUES (:name, :registerdate, :logindate, :hash)");
        $prepare->bindValue(":name", $name, SQLITE3_TEXT);
        $prepare->bindValue(":registerdate", $data["registerdate"], SQLITE3_INTEGER);
        $prepare->bindValue(":logindate", $data["logindate"], SQLITE3_INTEGER);
        $prepare->bindValue(":hash", $hash, SQLITE3_TEXT);
        $prepare->execute();

        return $data;
    }

    public function savePlayer(string $name, array $config){
        $name = trim(strtolower($name));
        $prepare = $this->database->prepare("UPDATE players SET registerdate = :registerdate, logindate = :logindate, lastip = :lastip, hash = :hash, ip = :ip, skinhash = :skinhash, pin = :pin, linkedign = :linkedign WHERE name = :name");
        $prepare->bindValue(":name", $name, SQLITE3_TEXT);
        $prepare->bindValue(":registerdate", $config["registerdate"], SQLITE3_INTEGER);
        $prepare->bindValue(":logindate", $config["logindate"], SQLITE3_INTEGER);
        $prepare->bindValue(":lastip", $config["lastip"], SQLITE3_TEXT);
        $prepare->bindValue(":hash", $config["hash"], SQLITE3_TEXT);
        $prepare->bindValue(":ip", $config["ip"], SQLITE3_TEXT);
        $prepare->bindValue(":linkedign", $config["linkedign"], SQLITE3_TEXT);
        $prepare->bindValue(":skinhash", $config["skinhash"], SQLITE3_TEXT);
        $prepare->bindValue(":pin", $config["pin"], SQLITE3_INTEGER);
        $prepare->execute();
    }

    public function updatePlayer(IPlayer $player, string $lastIP = null, string $ip = null, int $loginDate = null, string $skinhash = null, int $pin = null, string $linkedign = null) : bool {
        $name = trim(strtolower($player->getName()));

        if($lastIP !== null){
            $prepare = $this->database->prepare("UPDATE players SET lastip = :lastip WHERE name = :name");
            $prepare->bindValue(":name", $name, SQLITE3_TEXT);
            $prepare->bindValue(":lastip", $lastIP, SQLITE3_TEXT);
            $prepare->execute();
        }
        if($loginDate !== null){
            $prepare = $this->database->prepare("UPDATE players SET logindate = :logindate WHERE name = :name");
            $prepare->bindValue(":name", $name, SQLITE3_TEXT);
            $prepare->bindValue(":logindate", $loginDate, SQLITE3_INTEGER);
            $prepare->execute();
        }
        if($ip !== null){
            $prepare = $this->database->prepare("UPDATE players SET ip = :ip WHERE name = :name");
            $prepare->bindValue(":name", $name, SQLITE3_TEXT);
            $prepare->bindValue(":ip", $ip, SQLITE3_TEXT);
            $prepare->execute();
        }
        if($skinhash !== null){
            $prepare = $this->database->prepare("UPDATE players SET skinhash = :skinhash WHERE name = :name");
            $prepare->bindValue(":name", $name, SQLITE3_TEXT);
            $prepare->bindValue(":skinhash", $skinhash, SQLITE3_TEXT);
            $prepare->execute();
        }
        if($pin !== null){
            $prepare = $this->database->prepare("UPDATE players SET pin = :pin WHERE name = :name");
            $prepare->bindValue(":name", $name, SQLITE3_TEXT);
            $prepare->bindValue(":pin", $pin, SQLITE3_INTEGER);
            $prepare->execute();
        }
        if($linkedign !== null){
            $prepare = $this->database->prepare("UPDATE players SET linkedign = :linkedign WHERE name = :name");
            $prepare->bindValue(":name", $name, SQLITE3_TEXT);
            $prepare->bindValue(":linkedign", $linkedign, SQLITE3_TEXT);
            $prepare->execute();
        }
        if($pin === 0){
            $prepare = $this->database->prepare("UPDATE players SET pin = :pin WHERE name = :name");
            $prepare->bindValue(":name", $name, SQLITE3_TEXT);
            $prepare->bindValue(":pin", NULL, SQLITE3_INTEGER);
            $prepare->execute();
        }
        return true;
    }

    public function getLinked(string $name){
        $name = trim(strtolower($name));
        $prepare = $this->database->prepare("SELECT linkedign FROM players WHERE name = :name");
        $prepare->bindValue(":name", $name, SQLITE3_TEXT);
        $result = $prepare->execute();
        $data = [];
        if($result instanceof \SQLite3Result){
            $data = $result->fetchArray(SQLITE3_ASSOC);
            $result->finalize();
            $prepare->close();
        }
        return isset($data["linkedign"]) ? $data["linkedign"] : null;
    }

    public function linkXBL(Player $sender, OfflinePlayer $oldPlayer, string $oldIGN){
        $success = $this->updatePlayer($sender, null, null, null, null, null, $oldIGN);
        $success = $success && $this->updatePlayer($oldPlayer, null, null, null, null, null, $sender->getName());
        return $success;
    }

    public function unlinkXBL(Player $player){
        $xblIGN = $this->getLinked($player->getName());
        $pmIGN = $this->getLinked($xblIGN);
        if(!isset($xblIGN)){
            return null;
        }
        $xbldata = $this->getPlayerData($xblIGN);
        if(isset($xblIGN) && isset($xbldata)){
            $xbldata["linkedign"] = "";
            $this->savePlayer($xblIGN, $xbldata);
        }
        if(isset($pmIGN)){
            $pmdata = $this->getPlayerData($pmIGN);
            if(isset($pmdata)){
                $pmdata["linkedign"] = "";
                $this->savePlayer($pmIGN, $pmdata);
            }
        }
        return $xblIGN;
    }

    public function isDBLinkingReady() : bool {
       return $this->linkingready;
    }

    public function close(){
        $this->database->close();
    }
}