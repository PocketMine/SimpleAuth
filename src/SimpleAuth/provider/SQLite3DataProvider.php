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
    }

    public function getPlayer(string $name){
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
        return $this->getPlayer($player) !== null;
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
        $prepare = $this->database->prepare("UPDATE players SET registerdate = :registerdate, logindate = :logindate, lastip = :lastip, hash = :hash, ip = :ip, skinhash = :skinhash, pin = :pin WHERE name = :name");
        $prepare->bindValue(":name", $name, SQLITE3_TEXT);
        $prepare->bindValue(":registerdate", $config["registerdate"], SQLITE3_INTEGER);
        $prepare->bindValue(":logindate", $config["logindate"], SQLITE3_INTEGER);
        $prepare->bindValue(":lastip", $config["lastip"], SQLITE3_TEXT);
        $prepare->bindValue(":hash", $config["hash"], SQLITE3_TEXT);
        $prepare->bindValue(":ip", $config["ip"], SQLITE3_TEXT);
        $prepare->bindValue(":skinhash", $config["skinhash"], SQLITE3_TEXT);
        $prepare->bindValue(":pin", $config["pin"], SQLITE3_INTEGER);
        $prepare->execute();
    }

    public function updatePlayer(IPlayer $player, string $lastIP = null, string $ip = null, int $loginDate = null, string $skinhash = null, int $pin = null, string $linkedIGN = null) : bool {
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
        if($linkedIGN !== null){
            $prepare = $this->database->prepare("UPDATE players SET linkedign = :$linkedIGN WHERE name = :name");
            $prepare->bindValue(":name", $name, SQLITE3_TEXT);
            $prepare->bindValue(":linkedign", $linkedIGN, SQLITE3_TEXT);
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
        if(count($this->database->query("SELECT * FROM information_schema.COLUMNS WHERE COLUMN_NAME = 'linkedign'")->fetch_assoc()) === 0){
            return null;
        }
        $name = trim(strtolower($name));
        $linked = $this->database->query("SELECT linkedign FROM simpleauth_players WHERE name = '" . $this->database->escape_string($name) . "'")->fetchArray();
        return isset($linked["linkedign"]) ? $linked["linkedign"] : null;
    }

    public function linkXBL(Player $sender, OfflinePlayer $oldPlayer, string $oldIGN){
        $success = $this->updatePlayer($sender, null, null, null, null, null, $oldIGN);
        $success = $success && $this->updatePlayer($oldPlayer, null, null, null, null, null, $sender->getName());
        return $success;
    }

    public function unlinkXBL(Player $player){
        $xblIGN = $this->getLinked($player->getName());
        $xblPlayer = Server::getInstance()->getOfflinePlayer($xblIGN);
        if($xblPlayer instanceof OfflinePlayer){
            $this->updatePlayer($player, null, null, null, null, null, "");
            $this->updatePlayer($xblPlayer, null, null, null, null, null, "");
            return $xblIGN;
        }else{
            return null;
        }
    }

    public function isDBLinkingReady() : bool {
        return count($this->database->query("SELECT * FROM information_schema.COLUMNS WHERE COLUMN_NAME = 'linkedign'")->fetch_assoc()) > 0;
    }

    public function close(){
        $this->database->close();
    }
}