<?php
	require_once('../../../wp-load.php');
	require_once('community-events.php');
	
	$events = $_GET['eventlist'];
	
	foreach ($events as $event)
	{
		$id = array("event_id" => $event);
		$published = array("event_published" => 'Y');
		
		$wpdb->update( $wpdb->prefix.'ce_events', $published, $id);
	}
?>