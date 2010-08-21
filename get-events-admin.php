<?php
	require_once('../../../wp-load.php');
	require_once('community-events.php');
	
	$currentyear = $_GET['currentyear'];
	$currentday = $_GET['currentday'];
	$page = $_GET['page'];
	$moderate = $_GET['moderate'];
	
	if ($moderate == 'true')
		$moderate = true;
	else
		$moderate = false;
	
	echo print_event_table($currentyear, $currentday, $page, $moderate);
?>