<?php

$nodes = array('127.0.0.1:4009','123.101.120.123:4009');
$nodes = array_reverse($nodes);

return array(
	'nodes' => $nodes,
	'protocol' => 'https',
	'daemon_interval' => '5',
	'PublicKey' => "e08ed949ecf5ddc3eea05e6c0258d4a942e93c28fb456716ab08087330e21435",
	'DecryptionPhrase' => "test123456",
);
?>
