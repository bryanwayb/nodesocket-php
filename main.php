<?php
	require 'lib/client.php';
	require 'lib/common.php';
	
	$client = new NodeSocketClient(8080, 'localhost');
	
	$serverFunction = $client->linkFunction('serverFunction');
	
	$client->connect();
	
	$serverFunction();