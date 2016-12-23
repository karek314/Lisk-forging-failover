<?php
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
?>