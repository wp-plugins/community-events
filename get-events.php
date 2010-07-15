<?php
	require_once('../../../wp-load.php');
	
	$dayofyear = $_GET['dayofyear'];
	$year = $_GET['year'];
	
	global $wpdb;
	
	$eventquery = "SELECT * from " . $wpdb->prefix . "ce_events e LEFT JOIN " . $wpdb->prefix . "ce_venues v ON e.event_venue = v.ce_venue_id ";
	$eventquery .= "where YEAR(event_date) = " . $year . " and DAYOFYEAR(DATE(event_date)) = " . $dayofyear;
	$eventquery .= " order by e.event_name";
	
	$events = $wpdb->get_results($eventquery, ARRAY_A);
	
	$output = "<table class='ce-7day-innertable' id='ce-7day-innertable'>\n";

	if ($events)
	{	
		foreach($events as $event)
		{
			$output .= "\t\t<tr><td><span class='ce-event-name'>";
			if ($event['event_url'] != '')
				$output .= "<a href='" . $event['event_url'] . "'>";

			$output .= $event['event_name'];

			if ($event['event_url'] != '')
				$output .= "</a>";
			
			$output .= "</span> ";
			
			if ($event['event_time'] != "")
				$output .= "<span class='ce-event-time'>" . $event['event_time'] . "</span>. ";
				
			if ($event['ce_venue_name'] != "")
				$output .= "<span>" . $event['ce_venue_name'] . "</span></td>\n";
		}	
	}
	else
		$output .= "\n\n<tr><td>No events for this date.</td></tr>\n";
	
	$output .= "\t</table>";
	
	echo $output;	
?>