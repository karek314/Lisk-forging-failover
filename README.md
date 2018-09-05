# Lisk-forging-failover
Simple way to achieve perfect productivity while avoiding double forging in Lisk DPoS, written in PHP.
It's designed with safe practices in mind. Script evaluate most stable and synced node from provided list and make sure only this one is forging. Additionally it will ensure terminating forging if script will be closed or terminal connection hang up. (Upon signals - <b>SIGTERM,SIGHUP,SIGINT</b>). It's also using secure forging with AES256-GCM introduced in Lisk 1.0.0.

It's compatible with <b>Lisk 1.0.0 Core and up</b>, if you are looking for previous versions please check legacy branch.

# Installation
Designed for and tested on Ubuntu 16.04
```sh
git clone https://github.com/karek314/Lisk-forging-failover
cd Lisk-forging-failover
bash setup.sh
```

# Configuration
Configuration guide will be very detailed, if you understand exactly how to enable forging, skim it.<br>
### Lisk-PHP
Script <b>setup.sh</b> will install lisk-php in upper directory from <b>Lisk-forging-failover</b>.<br>
From this directory navigate to lisk-php
```sh
cd ..
cd lisk-php
php lisk-cli.php help
```
More details in [lisk-php](https://github.com/karek314/lisk-php) repository.
### Encrypt passphrase
We now need to encrypt passphrase in order to place AES256-GCM encrypted string in each lisk core node configuration file.<br>
First parameter is your first delegate account passphrase, second is password or phrase used to decrypt passphrase upon request. Longer better.
```sh
php lisk-cli.php EncryptPassphrase "Passphrase" "password" OptionalNumberOfSaltIterations
```
Example with default salting iterations
```sh
php lisk-cli.php EncryptPassphrase "coyote cancel access fresh soccer club subject salad veteran sheriff laundry square" "VeryHardAndLongPassword128319239123"
```
Example with custom salting iterations, for more info see: [this issue on lisk-php repository](https://github.com/karek314/lisk-php/issues/4).
```sh
php lisk-cli.php EncryptPassphrase "coyote cancel access fresh soccer club subject salad veteran sheriff laundry square" "VeryHardAndLongPassword128319239123" 2500
```
It will return prepared json string
```sh
Json->
{"publicKey":"a570aa6d9bfe8aa2ada142053426d07e0e8da28a6d0cb3a1d856d7f17156ae0b","encryptedPassphrase":"iterations=1&salt=0d1574a91e45fde38967153052fdb748&cipherText=5bada3a9fefba2e8a067c1ef761622a15ca092f17fead0aa3b1fdffee59422ffbae120cfbe59693f82f6075e38123884b5c3fa959fd34a8d6306c5a67a4caf0a17094024349880ca8825ef048d3641ee5a7f85&iv=787c6e0d160d95b3867a79c1&tag=6f44977c659a00d2f3dad383167501ea&version=1"}
```
### Configuring config.php
Now we need to configure servers which will be forging, navigate to <b>Lisk-forging-failover</b> and edit ```config.php``` first server in this config will always have forging priority and other servers can be considered as fallback. Please also change <b>PublicKey</b> and <b>DecryptionPhrase</b>.
```php
$nodes = array('127.0.0.1:4009','123.101.120.123:4009');
$nodes = array_reverse($nodes);

return array(
	'nodes' => $nodes,
	'protocol' => 'https',
	'daemon_interval' => '5',
	'PublicKey' => "a570aa6d9bfe8aa2ada142053426d07e0e8da28a6d0cb3a1d856d7f17156ae0b",
	'DecryptionPhrase' => "VeryHardAndLongPassword128319239123",
);
```

Now let's configure Lisk Core nodes. Important sections of <b>config.json</b> are <b>forging</b>, <b>ssl</b> and <b>api</b>.

### Configuring config.json in Lisk directory
Make sure to stop lisk
```sh
bash lisk.sh stop
```
Either make <b>api</b> public or better, whitelist ip of server running <b>Lisk-forging-failover</b>.
```json
"api": {
        "enabled": true,
         "access": {
                    "public": true,
                    "whiteList": ["127.0.0.1","123.123.123.123"]
                   },
		
```
<b>Forging</b> section, paste json string into key secret between brackets []<br>
Then replace ip <b>123.123.123.123</b> with ip of server running <b>Lisk-forging-failover</b>.
```json
"forging": {
             "force": false,
             "secret": [{"publicKey":"a570aa6d9bfe8aa2ada142053426d07e0e8da28a6d0cb3a1d856d7f17156ae0b","encryptedPassphrase":"iterations=1&salt=0d1574a91e45fde38967153052fdb748&cipherText=5bada3a9fefba2e8a067c1ef761622a15ca092f17fead0aa3b1fdffee59422ffbae120cfbe59693f82f6075e38123884b5c3fa959fd34a8d6306c5a67a4caf0a17094024349880ca8825ef048d3641ee5a7f85&iv=787c6e0d160d95b3867a79c1&tag=6f44977c659a00d2f3dad383167501ea&version=1"}
],
              "access": {
                    	"whiteList": ["127.0.0.1","123.123.123.123"]
                }
```
<b>ssl</b> section, set <b>enabled</b> to <b>true</b>, change <b>port</b> to <b>4009</b> and make sure that paths in <b>key</b> and <b>cert</b> contain two dots <b>"../ssl/"</b>
```json
   "ssl": {
           "enabled": true,
           "options": {
                       "port": 4009,
                       "address": "0.0.0.0",
                       "key": "../ssl/lisk.key",
                       "cert": "../ssl/lisk.crt"
                }
        }
```
### Generate certificate for ssl settings
Leave Lisk directory
```sh
cd ..
mkdir ssl
cd ssl
openssl req -x509 -nodes -days 9999 -newkey rsa:2048 -keyout lisk.key -out lisk.crt
```
Then start Lisk core and make sure it started correctly,
```sh
bash lisk.sh start && bash lisk.sh logs
```
### Repeat
Repeat Lisk core configuration procedure in as many nodes you need, but it's good to keep it around 3. There is not really a need for more than one master and 2 fallback servers.

# Usage
Testing and starting script
```sh
cd Lisk-forging-failover
php forge.php
```
Essentially output should look similar to
```sh
[0] Forging failover script starts...
[1] Primary forging node: 127.0.0.1:4009
[1] Forging Nodes count:3
[1] Forging not yet enabled!
[1] https://250.250.250.250:4009/ -> Height:24747 Consensus:0%
[1] https://193.193.192.193:4009/ -> Height:34144 Consensus:100%
[1] https://127.0.0.1:4009/ -> Height:34144 Consensus:0%
[1] Best Height id:1 with value:34144
[1] Best Consensus id:1 with value:100
[1] After evaluation best node to forging appears to be: https://193.193.192.193:4009/ with id:1
[1] Checking if node is forging already
[1] Forging disabled on this node, as precaution lets make sure all other nodes are not forging as well.
[1] https://250.250.250.250:4009/ -> IsForging: no
[1] https://193.193.192.193:4009/ -> IsForging: no
[1] https://127.0.0.1:4009/ -> IsForging: no
[1] Finally enabling forging on selected node.
[1] IsPredictedNodeForging: yes
[1] Took:2 sleep:3
Sleeping 0s [50/50(0.06s)] [###################################################] 100%
```
If <b>IsPredictedNodeForging</b> will stuck with <b>no</b> loop or there will be no height or consensus in log, it means Lisk config.json configuration is incorrect.

If everything works you can run script in background using <b>screen</b>
```sh
screen -dmS forge php forge.php
```
Listing all active screens
```sh
screen ls
```
Accessing screen session
```sh
screen -x forge
```
To leave active session, <b>Ctrl-A-D</b> to detach, <b>Ctrl-D</b> to terminate. This script can be added to crontab to ensure autostart.

# Safety
To ensure forging safety, if you unintentionally close script or when ssh connection hangs up, script will automatically terminate forging on all nodes before exiting. However it's worth to clarify that ssh connection hang up, will only affect current task executed in current session, meaning ``` php forge.php ```, but if you will run script in background using ``` screen -dmS forge php forge.php ``` connection hang up will not affect forging, script will continue executing in background.

Below example of <b>Ctrl+C</b>
```sh
[28] After evaluation best node to forging appears to be: https://127.0.0.1:4009/ with id:2
[28] Doing nothing, predicted node is the same as currently forging
[28] Took:0 sleep:5
Sleeping 3s [16/50(0.1s)] [#################                                  ] 32%^C
Caught SIGINT, terminating forging on all nodes and exiting this script...
[0] https://250.250.250.250:4009/ -> IsForging: no
[0] https://193.193.192.193:4009/ -> IsForging: no
[0] https://127.0.0.1:4009/ -> IsForging: yes
[0] Disable forging
[0] IsForging: no
```

# Contributions
If you find any issue or have any idea for improvement, feel free to either open issue or submit pull request. When script malfunction, double forge or act unexpectedly please attach logs from date of event and provide as much details as possible.
Logs can be found in <b>Lisk-forging-failover/logs<b>

# License
MIT<br>
Lisk-PHP MIT
