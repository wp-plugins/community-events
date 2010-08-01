<?php
	require_once('../../../wp-load.php');
	require_once('community-events.php');
	
	$currentyear = $_GET['currentyear'];
	$currentday = $_GET['currentday'];
	$page = $_GET['page'];
	
	echo print_event_table($currentyear, $currentday, $page);
?>