# lisk-best-forger
Simple background script to forge on the currently the best synced node.<br>
Clear and simple approach to achieve 99% and better productivity while forging in Lisk DPoS, written in PHP.

# Installation
<pre>
git clone https://github.com/karek314/lisk-best-forger/
cd lisk-best-forger
</pre>

# Configuration
Configure first passphrase in this config if you are not using it along with [karek314/liskpool](https://github.com/karek314/liskpool)
Then add trusted nodes and it's ports. Each specified server needs to have whitelisted IP address of server which will be used to run this script. As described [here](https://lisk.io/documentation?i=lisk-docs/BinaryInstall).
<pre>
nano config.php
</pre>

config.php
```php
$lisknodes = array(0 => 'localhost',1 => 'another',2 => 'another',3 => 'another');
$liskports = array(0 => '8001',1 => '8001',2 => '8001',3 => '8001');
$config = include('../../config.php');
$secret1 = $config['secret'];
if (strlen($secret1) < 2) {
	//Set forging delegate passphrase here in case script is not running along with https://github.com/karek314/liskpool
	$secret1 = '';
}
return array(
	'lisk_host' => $lisknodes,
	'lisk_port' => $liskports,
	'protocol' => 'https',
	'daemon_interval' => '0.25',
	'secret' => $secret1,
);
```
This should be used only over SSL.
# Usage
<pre>
screen -dmS bestforger php daemon.php
</pre>

Process can be accessed with
<pre>
screen -x bestforger
</pre>

And detached with <b>CTRL+A+D</b>
