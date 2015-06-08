<?php
	require 'lib/client.php';
	require 'lib/common.php';
	
	$client = new NodeSocketClient(22, 'localhost');
	$power = $client->linkFunction('power');
	$client->start();
	echo "Response: " . $power(5, 2);