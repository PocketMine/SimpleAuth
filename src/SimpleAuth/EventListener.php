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

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\inventory\InventoryOpenEvent;
use pocketmine\event\inventory\InventoryPickupItemEvent;
use pocketmine\event\Listener;
use pocketmine\nbt\tag\StringTag;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\Player;
use pocketmine\Server;

class EventListener implements Listener{
    /** @var SimpleAuth */
    private $plugin;
    private $perms;

    public function __construct(SimpleAuth $plugin){
        $this->plugin = $plugin;
    }

    /**
     * @param PlayerJoinEvent $event
     *
     * @priority LOWEST
     */
    public function onPlayerJoin(PlayerJoinEvent $event){
        if($this->plugin->getConfig()->get("authenticateByLastUniqueId") === true and $event->getPlayer()->hasPermission("simpleauth.lastid")){
            $config = $this->plugin->getDataProvider()->getPlayerData($event->getPlayer());
            if($config !== null and $config["lastip"] === $event->getPlayer()->getUniqueId()->toString()){
                $this->plugin->authenticatePlayer($event->getPlayer());
                return;
            }
        }
        $this->plugin->deauthenticatePlayer($event->getPlayer());
    }

    /**
     * @param PlayerPreLoginEvent $event
     *
     * @priority HIGHEST
     */
    public function onPlayerPreLogin(PlayerPreLoginEvent $event){
        if($this->plugin->getConfig()->get("forceSingleSession") !== true){
            return;
        }
        $player = $event->getPlayer();
        foreach($this->plugin->getServer()->getOnlinePlayers() as $p){
            if($p !== $player and strtolower($player->getName()) === strtolower($p->getName())){
                if($this->plugin->isPlayerAuthenticated($p)){
                    $event->setCancelled(true);
                    $player->kick("already logged in");
                    return;
                } //if other non logged in players are there leave it to the default behaviour
            }
        }
        if(!$this->plugin->getConfig()->get("allowLinking")){
            return true;
        }
        $linkedPlayerName = $this->plugin->getDataProvider()->getLinked($event->getPlayer()->getName());
        if(isset($linkedPlayerName)){
            $pmdata = $this->plugin->getDataProvider()->getPlayerData($linkedPlayerName);
            if(isset($pmdata)){
                $player = $event->getPlayer();
                $player->namedtag = Server::getInstance()->getOfflinePlayerData($linkedPlayerName);
                if(!isset($player->namedtag->NameTag)){
                    $player->namedtag->NameTag = new StringTag("NameTag", $linkedPlayerName);
                }else{
                    $player->namedtag["NameTag"] = $linkedPlayerName;
                }
                $player->setDisplayName($linkedPlayerName);
                $player->setNameTag($linkedPlayerName);
                if(method_exists($player, 'setName')){
                    $player->setName($linkedPlayerName);
                }
            }
        }

    }

    /**
     * @param PlayerRespawnEvent $event
     *
     * @priority MONITOR
     */
    public function onPlayerRespawn(PlayerRespawnEvent $event){
        if(!$this->plugin->isPlayerAuthenticated($event->getPlayer())){
            $this->plugin->sendAuthenticateMessage($event->getPlayer());
        }
    }

    /**
     * @param PlayerCommandPreprocessEvent $event
     *
     * @priority MONITOR
     */
    public function onPlayerCommand(PlayerCommandPreprocessEvent $event){
        if(!$this->plugin->isPlayerAuthenticated($event->getPlayer())){
            $message = $event->getMessage();
            if($message{0} === "/"){ //Command
                $event->setCancelled(true);
                $command = substr($message, 1);
                $args = explode(" ", $command);
                if($args[0] === "register" or $args[0] === "login" or $args[0] === "help"){
                    $this->plugin->getServer()->dispatchCommand($event->getPlayer(), $command);
                    if (!$this->plugin->getConfig()->get("disableRegister") && $args[0] === "register") {
                        $this->forcePerms($event->getPlayer());
                    }elseif (!$this->plugin->getConfig()->get("disableLogin") && $args[0] === "login") {
                        $this->forcePerms($event->getPlayer());
                    }
                }else{
                    $this->plugin->sendAuthenticateMessage($event->getPlayer());
                }
            }elseif(!$event->getPlayer()->hasPermission("simpleauth.chat")){
                $event->setCancelled(true);
            }
        }
    }

    //Borrowed from SimpleAuthHelper
    private function checkPerm(Player $pl, $perm) {
        if ($pl->hasPermission($perm)) return;
        $n = strtolower($pl->getName());
        $this->plugin->getLogger()->debug("Fixing %1% for %2%", $perm, $n);
        if (!isset($this->perms[$n])) $this->perms[$n] = $pl->addAttachment($this->plugin);
        $this->perms[$n]->setPermission($perm,true);
        $pl->recalculatePermissions();
    }

    public function forcePerms(Player $player) {
        if ($this->plugin->isPlayerAuthenticated($player)) {
            $this->resetPerms($player);
            return;
        }
        if (!$this->plugin->isPlayerRegistered($player)) {
            $this->checkPerm($player,"simpleauth.command.register");
            return;
        }
        $this->checkPerm($player,"simpleauth.command.login");
    }

    public function resetPerms(Player $pl) {
        $n = strtolower($pl->getName());
        if (isset($this->perms[$n])) {
            $attach = $this->perms[$n];
            unset($this->perms[$n]);
            $pl->removeAttachment($attach);
            $pl->recalculatePermissions();
        }
    }

    /**
     * @param PlayerMoveEvent $event
     *
     * @priority MONITOR
     */
    public function onPlayerMove(PlayerMoveEvent $event){
        if(!$this->plugin->isPlayerAuthenticated($event->getPlayer())){
            if(!$event->getPlayer()->hasPermission("simpleauth.move")){
                $event->setCancelled(true);
                $event->getPlayer()->onGround = true;
            }
        }
    }

    /**
     * @param PlayerInteractEvent $event
     *
     * @priority MONITOR
     */
    public function onPlayerInteract(PlayerInteractEvent $event){
        if(!$this->plugin->isPlayerAuthenticated($event->getPlayer())){
            $event->setCancelled(true);
        }
    }

    /**
     * @param PlayerDropItemEvent $event
     *
     * @priority MONITOR
     */
    public function onPlayerDropItem(PlayerDropItemEvent $event){
        if(!$this->plugin->isPlayerAuthenticated($event->getPlayer())){
            $event->setCancelled(true);
        }
    }

    /**
     * @param PlayerQuitEvent $event
     *
     * @priority MONITOR
     */
    public function onPlayerQuit(PlayerQuitEvent $event){
        $this->plugin->closePlayer($event->getPlayer());
    }

    /**
     * @param PlayerItemConsumeEvent $event
     *
     * @priority MONITOR
     */
    public function onPlayerItemConsume(PlayerItemConsumeEvent $event){
        if(!$this->plugin->isPlayerAuthenticated($event->getPlayer())){
            $event->setCancelled(true);
        }
    }

    /**
     * @param EntityDamageEvent $event
     *
     * @priority MONITOR
     */
    public function onEntityDamage(EntityDamageEvent $event){
        if($event->getEntity() instanceof Player and !$this->plugin->isPlayerAuthenticated($event->getEntity())){
            $event->setCancelled(true);
        }
    }

    /**
     * @param BlockBreakEvent $event
     *
     * @priority MONITOR
     */
    public function onBlockBreak(BlockBreakEvent $event){
        if($event->getPlayer() instanceof Player and !$this->plugin->isPlayerAuthenticated($event->getPlayer())){
            $event->setCancelled(true);
        }
    }

    /**
     * @param BlockPlaceEvent $event
     *
     * @priority MONITOR
     */
    public function onBlockPlace(BlockPlaceEvent $event){
        if($event->getPlayer() instanceof Player and !$this->plugin->isPlayerAuthenticated($event->getPlayer())){
            $event->setCancelled(true);
        }
    }

    /**
     * @param InventoryOpenEvent $event
     *
     * @priority MONITOR
     */
    public function onInventoryOpen(InventoryOpenEvent $event){
        if(!$this->plugin->isPlayerAuthenticated($event->getPlayer())){
            $event->setCancelled(true);
        }
    }

    /**
     * @param InventoryPickupItemEvent $event
     *
     * @priority MONITOR
     */
    public function onPickupItem(InventoryPickupItemEvent $event){
        $player = $event->getInventory()->getHolder();
        if($player instanceof Player and !$this->plugin->isPlayerAuthenticated($player)){
            $event->setCancelled(true);
        }
    }
}
