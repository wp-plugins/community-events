<?php
require_once( '../../../wp-load.php' );
require_once( 'community-events.php' );

$year           = intval( $_GET['year'] );
$outlook        = $_GET['outlook'];
$showdate       = $_GET['showdate'];
$maxevents      = intval( $_GET['maxevents'] );
$moderateevents = $_GET['moderateevents'];
$searchstring   = $_GET['searchstring'];
$filterbyuser   = $_GET['filterbyuser'];

$options = get_option( 'CE_PP' );

if ( 'current' == $filterbyuser ) {
	$current_user = wp_get_current_user();
	$filterbyuser = $current_user->user_login;
}

if ( !empty( $filterbyuser ) ) {
	$filterbyuser = apply_filters( 'community_events_filter_user', $filterbyuser );
}

echo $my_community_events_plugin->eventlist( $year, $_GET['dayofyear'], $outlook, $showdate, $maxevents, $moderateevents, $searchstring, $options['fullscheduleurl'],
	$options['addeventurl'], $options['allowuserediting'], $options['displayendtimefield'], $filterbyuser );

?>