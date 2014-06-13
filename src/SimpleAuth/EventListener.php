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

use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityMoveEvent;
use pocketmine\event\inventory\InventoryOpenEvent;
use pocketmine\event\inventory\InventoryPickupItemEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\Player;

class EventListener implements Listener{
	/** @var SimpleAuth */
	private $plugin;
	public function __construct(SimpleAuth $plugin){
		$this->plugin = $plugin;
	}

	/**
	 * @param PlayerJoinEvent $event
	 *
	 * @priority MONITOR
	 */
	public function onPlayerJoin(PlayerJoinEvent $event){
		$this->plugin->deauthenticatePlayer($event->getPlayer());
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
				//TODO: parse special commands
			}elseif(!$event->getPlayer()->hasPermission("simpleauth.chat")){
				$event->setCancelled(true);
			}
		}
	}

	/**
	 * @param EntityMoveEvent $event
	 *
	 * @priority MONITOR
	 */
	public function onPlayerMove(EntityMoveEvent $event){
		$player = $event->getEntity();
		if($player instanceof Player){
			if(!$this->plugin->isPlayerAuthenticated($player)){
				if(!$player->hasPermission("simpleauth.move")){
					$event->setCancelled(true);
				}
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