<?php
	require_once('../../../wp-load.php');
	require_once('community-events.php');
    
    $year = $_GET['year'];
	$outlook = $_GET['outlook'];
	$showdate = $_GET['showdate'];
	$maxevents = $_GET['maxevents'];
	$moderateevents = $_GET['moderateevents'];
    $searchstring = $_GET['searchstring'];
	
	$options = get_option('CE_PP');
    
	echo $my_community_events_plugin->eventlist($year, $_GET['dayofyear'], $outlook, $showdate, $maxevents, $moderateevents, $searchstring, $options['fullscheduleurl'],
		 $options['addeventurl'], $options['allowuserediting'], $options['displayendtimefield']);
	
?>