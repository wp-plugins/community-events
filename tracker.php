<?php
	require_once('../../../wp-load.php');
	require_once('community-events.php');
	
	$event_id = intval($_POST['id']);
	echo "Received ID is: " . $event_id;
	
	global $wpdb;
	
	$ceeventtable = $wpdb->get_blog_prefix() . "ce_events";	
	$ceeventdataquery = "select * from " . $wpdb->get_blog_prefix() . "ce_events where event_id = " . $event_id;
	$eventdata = $wpdb->get_row($ceeventdataquery, ARRAY_A);
	
	if ($eventdata)
	{
		$newcount = $eventdata['event_click_count'] + 1;
		$wpdb->update( $ceeventtable, array( 'event_click_count' => $newcount ), array( 'event_id' => $event_id ));
		echo "Updated row";
	}
	else
	{
		$wpdb->insert( $ceeventtable, array( 'event_id' => $link_id, 'event_click_count' => 1 ));
		echo "Inserted new row";
	}
	
?>