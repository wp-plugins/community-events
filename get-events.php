<?php
	require_once('../../../wp-load.php');
	require_once('community-events.php');
	
	$dayofyear = $_GET['dayofyear'];
	$year = $_GET['year'];
	$outlook = $_GET['outlook'];
	$showdate = $_GET['showdate'];
	$maxevents = $_GET['maxevents'];
	$moderateevents = $_GET['moderateevents'];
	
	echo venuelist($year, $dayofyear, $outlook, $showdate, $maxevents, $moderateevents);
?>