# SimpleAuth

Plugin for PocketMine-MP that prevents people to impersonate an account, requering registration and login when connecting.


## Commands


* `/login <password>`
* `/register <password>`
* `/unregister <password>`

## Configuration

You can modify the _SimpleAuth/config.yml_ file on the _plugins_ directory once the plugin has been run for at least one time.

| Configuration | Type | Default | Description |
| :---: | :---: | :---: | :--- |
| allowChat | boolean | false | Allows unauthenticated players to use the chat. They won't be able to use any command. |
| messageInterval | integer | 5 | Timelapse, in seconds, between the login/register message broadcast to unauthenticated players. |
| timeout | integer | 60 | Unauthenticated players will be kicked after this period of time. Set it to 0 to disable. |
| allowRegister | boolean | true | Allows registering and log in through commands. |
| forceSingleSession | boolean | true | New players won't kick an authenticated player if using the same name. |


## For developers


You can use the _login()_ method in the API to authenticate the player directly.
`boolean SimpleAuthAPI::login(Player object)`


More:

`boolean SimpleAuthAPI::logout(Player object)`

`object SimpleAuth SimpleAuthAPI::get()`
