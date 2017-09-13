# SimpleAuth 2.x - Shoghicp

#### Automatic Hack protection using IP/UUID/SKIN, user PIN codes and /link, /unlink by Awzaw

#### IMPORTANT
You no longer need to set "hack login" and "hack register" perms with SimpleAuthHelper.
ACCOUNT MAPPING WITH /LINK REQUIRES A PATCHED VERSION OF PMMP POCKETMINE 1.7dev (see below)
You must also update the database if you use MySQL or SQLite:

MySQL:

* `ALTER TABLE simpleauth.simpleauth_players ADD linkedign VARCHAR(16);`

SQLITE:

* `ALTER TABLE simpleauth.simpleauth_players ADD linkedign TEXT;`

TO UPDATE AN EXISTING MySQL DATABASE FOR ANTIHACK PLEASE RUN THE FOLLOWING QUERIES. STOP YOUR SERVER AND BACKUP THE DATABASE FIRST:

* `ALTER TABLE simpleauth.simpleauth_players ADD ip VARCHAR(50);`
* `ALTER TABLE simpleauth.simpleauth_players ADD skinhash VARCHAR(60);`
* `ALTER TABLE simpleauth.simpleauth_players ADD pin INT;`

TO UPDATE AN EXISTING SQLITE DATABASE:

* `ALTER TABLE simpleauth.simpleauth_players ADD ip TEXT;`
* `ALTER TABLE simpleauth.simpleauth_players ADD skinhash TEXT;`
* `ALTER TABLE simpleauth.simpleauth_players ADD pin INTEGER;`

Plugin for PocketMine-MP that prevents people from impersonating an account, requiring registration and login when connecting.

	 SimpleAuth plugin for PocketMine-MP
     Copyright (C) 2014 PocketMine Team <https://github.com/PocketMine/SimpleAuth>

     This program is free software: you can redistribute it and/or modify
     it under the terms of the GNU Lesser General Public License as published by
     the Free Software Foundation, either version 3 of the License, or
     (at your option) any later version.

     This program is distributed in the hope that it will be useful,
     but WITHOUT ANY WARRANTY; without even the implied warranty of
     MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
     GNU General Public License for more details.


## What's New?

This version of SimpleAuth adds automatic hack detection/protection to SimpleAuth.

When users register (or log in the first time after install or upgrade) they will be given a 4 digit PIN code.

If any player tries to login to an account with 2 or more changes to the previously recorded IP, UUID or SKIN, then they will
need to login with `/login <password> <PIN>`, for example, `/login dadada 1234`. They will then receive a new PIN.

If a user only changes IP, SKIN or UUID the PIN is not required, and the players security info is updated for the new IP/UUID/SKIN (not the PIN).

If a player forgets their PIN, and cannot login because they joined with a new SKIN + IP, SKIN + UUID, IP + UUID or SKIN + UUID
their security info can be reset on CONSOLE with `login <player>`. They will then get a new PIN code next time they login.

Players logging in normally will see a reminder on their PIN code.

Players can change their pin code by typing /login when already logged in.

Warnings are displayed on Console when players try to join with >= 2 changes to the security info (IP, UUID, SKIN).

SimpleAuth2 is compatible with SimpleAuthHelper, and works with any provider: MySQL (tested), YAML (tested) and SQLITE (untested)

## Commands


* `/login <password>`
* `/login <password> <PIN>` (If 2 changes detected for a players IP, SKIN or UUID since last login)
* `/register <password>`
* `/unregister <password>` (TODO)
* `/link <otherIGN> <otherpassword>`
* `/unlink`
* For OPs: `/simpleauth <command: help|unregister> [parameters...]` (TODO)
* For Console: `/login <player>` to reset hack detection data for a player
* For Players: `/login` when logged in to get a new PIN code

## Configuration

You can modify the _SimpleAuth/config.yml_ file on the _plugins_ directory once the plugin has been run at least once.

| Configuration | Type | Default | Description |
| :---: | :---: | :---: | :--- |
| timeout | integer | 60 | Unauthenticated players will be kicked after this period of time. Set it to 0 to disable. (TODO) |
| forceSingleSession | boolean | true | New players won't kick an authenticated player if using the same name. |
| minPasswordLength | integer | 6 | Minimum length of the register password. |
| blockAfterFail | integer | 6 | Block clients after several failed attempts |
| authenticateByLastUniqueId | boolean | false | Enables authentication by last unique id. |
| dataProvider | string | yaml | Selects the provider to get the data from (yaml, sqlite3, mysql, none) |
| dataProviderSettings | array | Sets the settings for the chosen dataProvider |
| disableRegister | boolean | false | Will set all the permissions for simleauth.command.register to false |
| disableLogin | boolean | false | Will set all the permissions for simleauth.command.login to false |

## AntiHack Configuration

You can modify the _SimpleAuth/antihack.yml_ file on the _plugins_ directory once the plugin has been run at least once.

| Configuration | Type | Default | Description |
| :---: | :---: | :---: | :--- |
| enabled | boolean | true | Enable AntiHack features |
| protectsuperadmins | boolean | true | Enable LOGIN protection ONLY for PurePerms SuperAdmin ranks (and OP if enabled) |
| protectops | boolean | true | Enable LOGIN protection for OPs |
| threat | integer | 2 | How many out of IP, UUID and SKIN must be the same to allow unchecked login |

## Permissions

| Permission | Default | Description |
| :---: | :---: | :--- |
| simpleauth.chat | false | Allows using the chat while not being authenticated |
| simpleauth.move | false | Allows moving while not being authenticated |
| simpleauth.lastip | true | Allows authenticating using the lastIP when enabled in the config |
| simpleauth.command.register | true | Allows registering an account |
| simpleauth.command.login | true | Allows logging into an account |
| simpleauth.command.link | true | Allows linking an account |
| simpleauth.command.unlink | true | Allows unlinking an account |

## For developers

### Events

* SimpleAuth\event\PlayerAuthenticateEvent
* SimpleAuth\event\PlayerDeauthenticateEvent
* SimpleAuth\event\PlayerRegisterEvent
* SimpleAuth\event\PlayerUnregisterEvent

### Plugin API methods

All methods are available through the main plugin object

* bool isPlayerAuthenticated(pocketmine\Player $player)
* bool isPlayerRegistered(pocketmine\IPlayer $player
* bool authenticatePlayer(pocketmine\Player $player)
* bool deauthenticatePlayer(pocketmine\Player $player)
* bool registerPlayer(pocketmine\IPlayer $player, $password)
* bool unregisterPlayer(pocketmine\IPlayer $player)
* void setDataProvider(SimpleAuth\provider\DataProvider $provider)
* SimpleAuth\provider\DataProvider getDataProvider(void)

### Implementing your own DataProvider

You can register an instantiated object that implements SimpleAuth\provider\DataProvider to the plugin using the _setDataProvider()_ method

### LINKING ACCOUNTS

You need to add to Player.php, after the function getName() for example:

    /**
     * Gets the username
     * @param string $username
     */
     
    public function setName(string $username) {
        $this->username = $username;
    }

    