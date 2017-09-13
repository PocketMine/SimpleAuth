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
use pocketmine\Player;
use pocketmine\OfflinePlayer;
use pocketmine\utils\Config;
use SimpleAuth\SimpleAuth;

class DummyDataProvider implements DataProvider{

    /** @var SimpleAuth */
    protected $plugin;

    public function __construct(SimpleAuth $plugin){
        $this->plugin = $plugin;
    }

    public function getPlayerData(string $player){
        return null;
    }

    public function isPlayerRegistered(IPlayer $player){
        return false;
    }

    public function registerPlayer(IPlayer $player, $hash){
        return null;
    }

    public function unregisterPlayer(IPlayer $player){

    }

    public function savePlayer(string $name, array $config){

    }

    public function updatePlayer(IPlayer $player, string $lastIP = null, string $ip = null, int $loginDate = null, string $skinhash = null, int $pin = null, string $linkedIGN = null) : bool{
        return false;
    }

    public function getLinked(string $name){

    }

    public function linkXBL(Player $sender, OfflinePlayer $oldPlayer, string $oldIGN){

    }

    public function unlinkXBL(Player $player){

    }

    public function isDBLinkingReady() : bool{
        return false;
    }

    public function close(){

    }
}