# SimpleAuth

Plugin for PocketMine-MP that prevents people to impersonate an account, requering registration and login when connecting.

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


## Commands


* `/login <password>`
* `/register <password>`
* `/unregister <password>` (TODO)
* For OPs: `/simpleauth <command: help|unregister> [parameters...]` (TODO)


## Configuration

You can modify the _SimpleAuth/config.yml_ file on the _plugins_ directory once the plugin has been run for at least one time.

| Configuration | Type | Default | Description |
| :---: | :---: | :---: | :--- |
| timeout | integer | 60 | Unauthenticated players will be kicked after this period of time. Set it to 0 to disable. (TODO) |
| forceSingleSession | boolean | true | New players won't kick an authenticated player if using the same name. |
| minPasswordLength | integer | 6 | Minimum length of the register password. |
| authenticateByLastIP | boolean | false | Enables authentication by last IP. |
| dataProvider | string | yaml | Selects the provider to get the data from (yaml, sqlite3, mysql, none) |
| dataProviderSettings | array | Sets the settings for the chosen dataProvider |
| disableRegister | boolean | false | Will set all the permissions for simleauth.command.register to false |
| disableLogin | boolean | false | Will set all the permissions for simleauth.command.login to false |

## Permissions

| Permission | Default | Description |
| :---: | :---: | :--- |
| simpleauth.chat | false | Allows using the chat while not being authenticated |
| simpleauth.move | false | Allows moving while not being authenticated |
| simpleauth.lastip | notop | Allows authenticating using the lastIP when enabled in the config |
| simpleauth.command.register | true | Allows registering an account |
| simpleauth.command.login | true | Allows logging into an account |

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

