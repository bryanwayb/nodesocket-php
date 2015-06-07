<?php
	require 'common.php';
	require 'client.php';
	
	$client = new NodeSocketClient(22, 'localhost');
	$power = $client->linkFunction('power');
	$client->start();
	echo "Response: " . $power(5, 2);