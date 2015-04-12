<?php
	require_once('../../../wp-load.php');
	require_once('community-events.php');
	
	$currentyear = intval( $_GET['currentyear'] );
	$currentday = intval( $_GET['currentday'] );
	$page = intval( $_GET['page'] );
	$moderate = $_GET['moderate'];
	
	if ($moderate == 'true')
		$moderate = true;
	else
		$moderate = false;
	
	echo $my_community_events_plugin->print_event_table($currentyear, $currentday, $page, $moderate);
?>