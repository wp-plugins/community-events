<?php
/*Plugin Name: Community Events
Plugin URI: http://yannickcorner.nayanna.biz/wordpress-plugins/community-events
Description: A plugin used to create a page with a list of TV shows
Version: 0.2.2
Author: Yannick Lefebvre
Author URI: http://yannickcorner.nayanna.biz   
Copyright 2010  Yannick Lefebvre  (email : ylefebvre@gmail.com)    

This program is free software; you can redistribute it and/or modify   
it under the terms of the GNU General Public License as published by    
the Free Software Foundation; either version 2 of the License, or    
(at your option) any later version.    

This program is distributed in the hope that it will be useful,    
but WITHOUT ANY WARRANTY; without even the implied warranty of    
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the    
GNU General Public License for more details.    

You should have received a copy of the GNU General Public License    
along with this program; if not, write to the Free Software    
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA*/

if (is_file(trailingslashit(ABSPATH.PLUGINDIR).'community-events.php')) {
	define('CE_FILE', trailingslashit(ABSPATH.PLUGINDIR).'community-events.php');
}
else if (is_file(trailingslashit(ABSPATH.PLUGINDIR).'community-events/community-events.php')) {
	define('CE_FILE', trailingslashit(ABSPATH.PLUGINDIR).'community-events/community-events.php');
}

$cepluginpath = WP_CONTENT_URL.'/plugins/'.plugin_basename(dirname(__FILE__)).'/';

function ce_install() {
	global $wpdb;

	$charset_collate = '';
	if ( version_compare(mysql_get_server_info(), '4.1.0', '>=') ) {
		if (!empty($wpdb->charset)) {
			$charset_collate .= " DEFAULT CHARACTER SET $wpdb->charset";
		}
		if (!empty($wpdb->collate)) {
			$charset_collate .= " COLLATE $wpdb->collate";
		}
	}
	
	$wpdb->ceevents = $wpdb->prefix.'ce_events';
	
	$result = $wpdb->query("
			CREATE TABLE IF NOT EXISTS `$wpdb->ceevents` (
			  `event_id` bigint(20) NOT NULL AUTO_INCREMENT,
			  `event_name` varchar(255) DEFAULT NULL,
			  `event_date` date DEFAULT NULL,
			  `event_time` varchar(16) DEFAULT NULL,
			  `event_description` varchar(140) DEFAULT NULL,
			  `event_url` varchar(255) DEFAULT NULL,
			  `event_ticket_url` varchar(256) DEFAULT NULL,
			  `event_venue` int(11) DEFAULT NULL,
			  `event_category` int(11) DEFAULT NULL,
			  PRIMARY KEY (`event_id`)
				) $charset_collate") ;

	$wpdb->cecats = $wpdb->prefix.'ce_category';
	
	$result = $wpdb->query("
			CREATE TABLE IF NOT EXISTS `$wpdb->cecats` (
				`event_cat_id` bigint(20) NOT NULL AUTO_INCREMENT,
				`event_cat_name` varchar(255) DEFAULT NULL,
				PRIMARY KEY (`event_cat_id`)
				) $charset_collate");
		
	$catsresult = $wpdb->query("SELECT * from `$wpdb->cecats`");
			
	if (!$catsresult)
		$result = $wpdb->query("
			INSERT INTO `$wpdb->cecats` (`event_cat_name`) VALUES
			('Default')");

	$wpdb->cevenues = $wpdb->prefix.'ce_venues';
					
	$result = $wpdb->query("
		CREATE TABLE IF NOT EXISTS `$wpdb->cevenues` (
			  `ce_venue_id` bigint(20) NOT NULL AUTO_INCREMENT,
			  `ce_venue_name` varchar(256) DEFAULT NULL,
			  `ce_venue_address` varchar(256) DEFAULT NULL,
			  `ce_venue_city` varchar(256) DEFAULT NULL,
			  `ce_venue_zipcode` varchar(256) DEFAULT NULL,
			  `ce_venue_phone` varchar(256) DEFAULT NULL,
			  `ce_venue_email` varchar(256) DEFAULT NULL,
			  `ce_venue_url` varchar(256) DEFAULT NULL,
			  PRIMARY KEY (`ce_venue_id`)		
				) $charset_collate");
				
	$venuesresult = $wpdb->query("SELECT * from `$wpdb->cevenues`");
	
	if (!$venuesresult)
		$result = $wpdb->query("
			INSERT INTO `$wpdb->cevenues` (`ce_venue_name`) VALUES
			('Default')");	
	
	$options = get_option('CE_PP');

	if ($options == false) {
		$options['fullscheduleurl'] = '';
		$options['addeventurl'] = '';
		$options['columns'] = 2;
		$options['addeventreqlogin'] = true;
		$options['addneweventmsg'] = __('Add new event', 'community-events');
		$options['eventnamelabel'] = __('Event Name', 'community-events');
		$options['eventcatlabel'] = __('Event Category', 'community-events');
		$options['eventvenuelabel'] = __('Event Venue', 'community-events');
		$options['eventdesclabel'] = __('Event Description', 'community-events');
		$options['eventaddrlabel'] = __('Event Web Address', 'community-events');
		$options['eventticketaddrlabel'] = __('Event Ticket Purchase Link', 'community-events');
		$options['eventdatelabel'] = __('Event Date', 'community-events');
		$options['eventtimelabel'] = __('Event Time', 'community-events');
		$options['addeventbtnlabel'] = __('Add Event', 'community-events');
		$options['outlook'] = true;
		$options['emailnewevent'] = true;
		
		update_option('CE_PP',$options);
	}
	
	wp_schedule_event(current_time('timestamp'), 'daily', 'ce_daily_event');

}

function ce_uninstall() {
	wp_clear_scheduled_hook('ce_daily_event');
}

register_activation_hook(CE_FILE, 'ce_install');
register_deactivation_hook(CE_FILE, 'ce_uninstall');

function print_event_table($currentyear, $currentday, $page) {

	global $wpdb;
	
	$start = ($page - 1) * 10;
	$eventquery = "SELECT * from " . $wpdb->prefix . "ce_events where YEAR(event_date) = " . $currentyear . " and DAYOFYEAR(DATE(event_date)) >= " . $currentday . " ORDER by event_date, event_name LIMIT " . $start . ", 10";
	
	$events = $wpdb->get_results($eventquery, ARRAY_A);	
		
	$output = "<table id='ce-event-list' class='widefat' style='clear:none;width:500px;background: #DFDFDF url(/wp-admin/images/gray-grad.png) repeat-x scroll left top;'>\n";
	$output .= "\t<thead>\n";
	$output .= "\t\t<tr>\n";
	$output .= "\t\t\t<th scope='col' style='width: 50px' id='id' class='manage-column column-id' >ID</th>\n";
	$output .= "\t\t\t<th scope='col' id='name' class='manage-column column-name' style=''>Name</th>\n";
	$output .= "\t\t\t<th scope='col' id='day' class='manage-column column-day' style='text-align: right'>Date</th>\n";
	$output .= "\t\t\t<th scope='col' style='width: 50px;text-align: right' id='starttime' class='manage-column column-items' style=''>Time</th>\n";
	$output .= "\t\t\t<th style='width: 30px'></th>\n";
	$output .= "\t\t</tr>\n";
	$output .= "\t</thead>\n";
	$output .= "\t<tbody class='list:link-cat'>\n";
				
	if ($events)
	{
		foreach($events as $event) {
			$output .= "\t\t<tr>\n";
			$output .= "\t\t\t<td class='name column-name' style='background: #FFF'>" . $event['event_id'] . "</td>\n";
			$output .= "\t\t\t<td style='background: #FFF'><a href='?page=community-events.php&amp;editevent=" . $event['event_id'] . "&pagecount=" . $page . "'><strong>" . $event['event_name'] . "</strong></a></td>\n";
			$output .= "\t\t\t<td style='background: #FFF;text-align:right'>" . $event['event_date'] . "</td>\n";
			$output .= "\t\t\t<td style='background: #FFF;text-align:right'></td>\n";
			$output .= "\t\t\t<td style='background:#FFF'><a href='?page=community-events.php&amp;deleteevent=" . $event['event_id'] . "&pagecount=" . $page . "'\n";
			$output .= "\t\t\tonclick=\"if ( confirm('" . esc_js(sprintf( __("You are about to delete the event '%s'\n  'Cancel' to stop, 'OK' to delete."), $event['event_name'] )) . "') ) { return true;}return false;\"><img src='" . $cepluginpath . "/icons/delete.png' /></a></td>\n";
			$output .= "\t\t\t</tr>\n";
		}
			}
	else
	{
		$output .= "<tr>No events found.</tr>\n";
	}
	
	$output .= "\t</tbody>\n";
	$output .= "</table>\n";
	
	return $output;
}

if ( ! class_exists( 'CE_Admin' ) ) {
	class CE_Admin {		
		function add_config_page() {
			global $wpdb;
			if ( function_exists('add_submenu_page') ) {
				add_options_page('Community Events for Wordpress', 'Community Events', 9, basename(__FILE__), array('CE_Admin','config_page'));
				add_filter( 'plugin_action_links', array( 'CE_Admin', 'filter_plugin_actions'), 10, 2 );
							}
		} // end add_CE_config_page()

		function filter_plugin_actions( $links, $file ){
			//Static so we don't call plugin_basename on every plugin row.
			static $this_plugin;
			if ( ! $this_plugin ) $this_plugin = plugin_basename(__FILE__);
			if ( $file == $this_plugin ){
				$settings_link = '<a href="options-general.php?page=community-events.php">' . __('Settings') . '</a>';
				
				array_unshift( $links, $settings_link ); // before other links
			}
			return $links;
		}

		function config_page() {
			global $dlextensions;
			global $wpdb;
			
			$adminpage == "";
			
			if ( !defined('WP_ADMIN_URL') )
				define( 'WP_ADMIN_URL', get_option('siteurl') . '/wp-admin');
			
			if ( isset($_GET['reset']) && $_GET['reset'] == "true") {
			
				update_option($schedulename, $options);
			}
			if ( isset($_GET['section']))
			{
				if ($_GET['section'] == 'eventtypes')
				{
					$adminpage = 'eventtypes';
				}
				elseif ($_GET['section'] == 'events')
				{
					$adminpage = 'events';
				}
				elseif ($_GET['section'] == 'general')
				{
					$adminpage = 'general';
				}
				elseif ($_GET['section'] == 'eventvenues')
				{
					$adminpage = 'eventvenues';
				}				
			}
			if ( isset($_POST['submit']) ) {
				if (!current_user_can('manage_options')) die(__('You cannot edit the Weekly Schedule for WordPress options.'));
				check_admin_referer('cepp-config');
								
				foreach (array('fullscheduleurl', 'addeventurl', 'columns', 'addneweventmsg', 'eventnamelabel', 'eventcatlabel', 'eventvenuelabel', 'eventdesclabel',
								'eventaddrlabel', 'eventticketaddrlabel', 'eventdatelabel', 'eventtimelabel', 'addeventbtnlabel') as $option_name) {
						if (isset($_POST[$option_name])) {
							$options[$option_name] = $_POST[$option_name];
						}
					}
					
				foreach (array('adjusttooltipposition', 'addeventreqlogin', 'outlook', 'emailnewevent') as $option_name) {
					if (isset($_POST[$option_name])) {
						$options[$option_name] = true;
					} else {
						$options[$option_name] = false;
					}
				}

				update_option('CE_PP', $options);
				
				echo '<div id="message" class="updated fade"><p><strong>Community Events Updated</strong></div>';
			}
			
			if ( isset($_GET['editcat']))
			{					
				$adminpage = 'eventtypes';
				
				$mode = "edit";
								
				$selectedcat = $wpdb->get_row("select * from " . $wpdb->prefix . "ce_category where event_cat_id = " . $_GET['editcat']);
			}			
			if ( isset($_POST['newcat']) || isset($_POST['updatecat'])) {
				if (!current_user_can('manage_options')) die(__('You cannot edit the Community Events for WordPress options.'));
				check_admin_referer('cepp-config');
				
				if (isset($_POST['name']))
					$newcat = array("event_cat_name" => $_POST['name']);
				else
					$newcat = "";
					
				if (isset($_POST['id']))
					$id = array("event_cat_id" => $_POST['id']);
					
					
				if (isset($_POST['newcat']))
				{
					$wpdb->insert( $wpdb->prefix.'ce_category', $newcat);
					echo '<div id="message" class="updated fade"><p><strong>Inserted New Category</strong></div>';
				}
				elseif (isset($_POST['updatecat']))
				{
					$wpdb->update( $wpdb->prefix.'ce_category', $newcat, $id);
					echo '<div id="message" class="updated fade"><p><strong>Category Updated</strong></div>';
				}
				
				$mode = "";
				
				$adminpage = 'eventtypes';	
			}
			if (isset($_GET['deletecat']))
			{
				$adminpage = 'eventtypes';
				
				$catexist = $wpdb->get_row("SELECT * from " . $wpdb->prefix . "ce_category WHERE event_cat_id = " . $_GET['deletecat']);
				
				if ($catexist)
				{
					$wpdb->query("DELETE from " . $wpdb->prefix . "ce_category WHERE id = " . $_GET['deletecat']);
					echo '<div id="message" class="updated fade"><p><strong>Category Deleted</strong></div>';
				}
			}
			
			if ( isset($_GET['editvenue']))
			{					
				$adminpage = 'eventvenues';
				
				$mode = "edit";
								
				$selectedvenue = $wpdb->get_row("select * from " . $wpdb->prefix . "ce_venues where ce_venue_id = " . $_GET['editvenue']);
			}			
			if ( isset($_POST['newvenue']) || isset($_POST['updatevenue'])) {
				if (!current_user_can('manage_options')) die(__('You cannot edit the Community Events for WordPress options.'));
				check_admin_referer('cepp-config');
				
				if (isset($_POST['ce_venue_name']))
					$newvenue = array("ce_venue_name" => $_POST['ce_venue_name'],
									"ce_venue_address" => $_POST['ce_venue_address'],
									"ce_venue_city" => $_POST['ce_venue_city'],
									"ce_venue_zipcode" => $_POST['ce_venue_zipcode'],
									"ce_venue_phone" => $_POST['ce_venue_phone'],
									"ce_venue_email" => $_POST['ce_venue_email'],
									"ce_venue_url" => $_POST['ce_venue_url']
									);
				else
					$newvenue = "";
					
				if (isset($_POST['ce_venue_id']))
					$id = array("ce_venue_id" => $_POST['ce_venue_id']);
					
				if (isset($_POST['newvenue']))
				{
					$wpdb->insert( $wpdb->prefix.'ce_venues', $newvenue);
					echo '<div id="message" class="updated fade"><p><strong>Inserted New Venue</strong></div>';
				}
				elseif (isset($_POST['updatevenue']))
				{
					$wpdb->update( $wpdb->prefix.'ce_venues', $newvenue, $id);
					echo '<div id="message" class="updated fade"><p><strong>Venue Updated</strong></div>';
				}
				
				$mode = "";
				
				$adminpage = 'eventvenues';	
			}
			if (isset($_GET['deletevenue']))
			{
				$adminpage = 'eventvenues';
				
				$venueexist = $wpdb->get_row("SELECT * from " . $wpdb->prefix . "ce_venues WHERE ce_venue_id = " . $_GET['deletevenue']);
				
				if ($venueexist)
				{
					$wpdb->query("DELETE from " . $wpdb->prefix . "ce_venues WHERE ce_venue_id = " . $_GET['deletevenue']);
					echo '<div id="message" class="updated fade"><p><strong>Venue Deleted</strong></div>';
				}
			}

			
			if ( isset($_GET['editevent']))
			{					
				$adminpage = 'events';
				
				$mode = "edit";
								
				$selectedevent = $wpdb->get_row("select * from " . $wpdb->prefix . "ce_events where event_id = " . $_GET['editevent']);
			}
			if (isset($_POST['newevent']) || isset($_POST['updateevent']))
			{
				if (!current_user_can('manage_options')) die(__('You cannot edit the Community Events for WordPress options.'));
				check_admin_referer('cepp-config');
				
				if (isset($_POST['event_name']) && isset($_POST['event_time']) && isset($_POST['event_date']) && $_POST['event_name'] != '')
				{
					$newevent = array("event_name" => $_POST['event_name'],
									 "event_description" => $_POST['event_description'],
									 "event_date" => $_POST['event_date'],
									 "event_time" => $_POST['event_time'],
									 "event_url" => $_POST['event_url'],
									 "event_ticket_url" => $_POST['event_ticket_url'],
									 "event_category" => $_POST['event_category'],
									 "event_venue" => $_POST['event_venue']);

					if (isset($_POST['event_id']))
						$id = array("event_id" => $_POST['event_id']);
						
					if (isset($_POST['newevent']))
					{
						$wpdb->insert( $wpdb->prefix.'ce_events', $newevent);
						echo '<div id="message" class="updated fade"><p><strong>Inserted New Event: ' . $_POST['event_name'] . '</strong></div>';
					}
					elseif (isset($_POST['updateevent']))
					{
						$wpdb->update( $wpdb->prefix.'ce_events', $newevent, $id);
						echo '<div id="message" class="updated fade"><p><strong>Event Updated: ' . $_POST['event_name'] . '</strong></div>';
					}									 
				}				
				
				$mode = "";
					
				$adminpage = 'events';
			}
			if (isset($_GET['deleteevent']))
			{
				$adminpage = 'events';
				
				$eventexist = $wpdb->get_row("SELECT * from " . $wpdb->prefix . "ce_events WHERE event_id = " . $_GET['deleteevent']);
				
				if ($eventexist)
				{
					$wpdb->query("DELETE from " . $wpdb->prefix . "ce_events WHERE id = " . $_GET['deleteitem']);
					
					echo '<div id="message" class="updated fade"><p><strong>Event Deleted</strong></div>';
				}				
			}
			
			$cepluginpath = WP_CONTENT_URL.'/plugins/'.plugin_basename(dirname(__FILE__)).'/';
	
			$options = get_option('CE_PP');
			
			?>
			<div class="wrap">
				<h2>Community Events Configuration</h2>
				<a href="http://yannickcorner.nayanna.biz/wordpress-plugins/community-events/" target="community-events"><img src="<?php echo $cepluginpath; ?>/icons/btn_donate_LG.gif" /></a> | <a target='ceinstructions' href='http://wordpress.org/extend/plugins/community-events/installation/'>Installation Instructions</a> | <a href='http://wordpress.org/extend/plugins/community-events/faq/' target='cefaq'>FAQ</a> | <a href='http://yannickcorner.nayanna.biz/contact-me'>Contact the Author</a><br /><br />
	
				<?php if (($adminpage == "") || ($adminpage == "general")): ?>
				<a href="?page=community-events.php&amp;section=general"><strong>General Settings</strong></a> | <a href="?page=community-events.php&amp;section=eventtypes">Manage Event Types</a> | <a href="?page=community-events.php&amp;section=eventvenues">Manage Venues</a> | <a href="?page=community-events.php&amp;section=events">Manage Events</a><br /><br />
				<form name="wsadminform" action="<?php echo WP_ADMIN_URL ?>/options-general.php?page=community-events.php" method="post" id="ce-config">
				<?php
					if ( function_exists('wp_nonce_field') )
						wp_nonce_field('cepp-config');
					?>
				<fieldset style='border:1px solid #CCC;padding:10px'>
				<legend class="tooltip" title='These apply to all Settings Sets' style='padding: 0 5px 0 5px;'><strong><?php _e('Usage Instructions','community-events'); ?></strong></legend>				
					<table class='widefat' style='clear:none;width:100%;background: #DFDFDF url(/wp-admin/images/gray-grad.png) repeat-x scroll left top;'>
						<thead>
						<tr>
							<td>Functionality</td>
							<td>Code to place in Wordpress page to activate</td>
						</tr>
						</thead>
						<tr>
							<td style='background-color: #fff'>7-day event view with optional outlook</td>
							<td style='background-color: #fff'>[community-events-7day]</td>
						</tr>
						<tr>
							<td style='background-color: #fff'>Full schedule table</td>
							<td style='background-color: #fff'>[community-events-full]</td>
						</tr>
						<tr>
							<td style='background-color: #fff'>Event submission form</td>
							<td style='background-color: #fff'>[community-events-addevent]</td>
						</tr>
					</table>
				</fieldset>
				<br />
				<fieldset style='border:1px solid #CCC;padding:10px'>
				<legend class="tooltip" title='These apply to all Settings Sets' style='padding: 0 5px 0 5px;'><strong><?php _e('General Configuration','community-events'); ?></strong></legend>								
					<table>
						<tr>
							<td style='width: 240px'>Full Schedule URL</td>
							<td><input style="width:660px" type="text" name="fullscheduleurl" <?php echo "value='" . $options['fullscheduleurl'] . "'";?>/></td>
						</tr>
						<tr>
							<td style='width: 240px'>Event submission form URL</td>
							<td><input style="width:660px" type="text" name="addeventurl" <?php echo "value='" . $options['addeventurl'] . "'";?>/></td>
						</tr>						
						<tr>
							<td>Show outlook view in 7-day view</td>
							<td><input type="checkbox" id="outlook" name="outlook" <?php if ($options['outlook']) echo ' checked="checked" '; ?>/></td>
						</tr>
					</table>
				</fieldset>

				<div>
					<fieldset style='border:1px solid #CCC;padding:10px;margin:15px 0 5px 0;'>
					<legend style='padding: 0 5px 0 5px;'><strong><?php _e('Event User Submission', 'community-events'); ?></strong></legend>
					<table>
						<tr>
							<td style='width:200px'><?php _e('Require login to display form', 'community-events'); ?></td>
							<td style='width:75px;padding-right:20px'><input type="checkbox" id="addeventreqlogin" name="addeventreqlogin" <?php if ($options['addeventreqlogin']) echo ' checked="checked" '; ?>/></td>
							<td style='width: 20px'></td>
							<td style='width:200px'><?php _e('Send e-mail when new event is submitted', 'community-events'); ?></td>
							<td style='width:75px;padding-right:20px'><input type="checkbox" id="emailnewevent" name="emailnewevent" <?php if ($options['emailnewevent']) echo ' checked="checked" '; ?>/></td>							
						</tr>
						<tr>
							<td style='width:200px'><?php _e('Add new event label', 'community-events'); ?></td>
							<?php if ($options['addneweventmsg'] == "") $options['addneweventmsg'] = __('Add New Event', 'community-events'); ?>
							<td><input type="text" id="addneweventmsg" name="addneweventmsg" size="30" value="<?php echo $options['addneweventmsg']; ?>"/></td>
							<td style='width: 20px'></td>
							<td style='width:200px'><?php _e('Event Name Label', 'community-events'); ?></td>
							<?php if ($options['eventnamelabel'] == "") $options['eventnamelabel'] = __('Event Name', 'community-events'); ?>
							<td><input type="text" id="eventnamelabel" name="eventnamelabel" size="30" value="<?php echo $options['eventnamelabel']; ?>"/></td>
						</tr>
						<tr>
							<td style='width:200px'><?php _e('Event Category Label', 'community-events'); ?></td>
							<?php if ($options['eventcatlabel'] == "") $options['eventcatlabel'] = __('Event Category', 'community-events'); ?>
							<td><input type="text" id="eventcatlabel" name="eventcatlabel" size="30" value="<?php echo $options['eventcatlabel']; ?>"/></td>
							<td style='width: 20px'></td>
							<td style='width:200px'><?php _e('Event Venue Label', 'community-events'); ?></td>
							<?php if ($options['eventvenuelabel'] == "") $options['eventvenuelabel'] = __('Event Venue Label', 'community-events'); ?>
							<td><input type="text" id="eventvenuelabel" name="eventvenuelabel" size="30" value="<?php echo $options['eventvenuelabel']; ?>"/></td>
						</tr>
						<tr>
							<td style='width:200px'><?php _e('Event Description Label', 'community-events'); ?></td>
							<?php if ($options['eventdesclabel'] == "") $options['eventdesclabel'] = __('Event Description', 'community-events'); ?>
							<td><input type="text" id="eventdesclabel" name="eventdesclabel" size="30" value="<?php echo $options['eventdesclabel']; ?>"/></td>
							<td style='width: 20px'></td>
							<td style='width:200px'><?php _e('Event Web Address Label', 'community-events'); ?></td>
							<?php if ($options['eventaddrlabel'] == "") $options['eventaddrlabel'] = __('Event Web Address', 'community-events'); ?>
							<td><input type="text" id="eventaddrlabel" name="eventaddrlabel" size="30" value="<?php echo $options['eventaddrlabel']; ?>"/></td>
						</tr>
						<tr>
							<td style='width:200px'><?php _e('Event Ticket Purchase Link Label', 'community-events'); ?></td>
							<?php if ($options['eventticketaddrlabel'] == "") $options['eventticketaddrlabel'] = __('Event Ticket Purchase Link Label', 'community-events'); ?>
							<td><input type="text" id="eventticketaddrlabel" name="eventticketaddrlabel" size="30" value="<?php echo $options['eventticketaddrlabel']; ?>"/></td>
							<td style='width:200px'></td>
							<td style='width:200px'><?php _e('Event Date Label', 'community-events'); ?></td>
							<?php if ($options['eventdatelabel'] == "") $options['eventdatelabel'] = __('Event Date', 'community-events'); ?>
							<td><input type="text" id="eventdatelabel" name="eventdatelabel" size="30" value="<?php echo $options['eventdatelabel']; ?>"/></td>
						</tr>
						<tr>
							<td style='width:200px'><?php _e('Event Time Label', 'community-events'); ?></td>
							<?php if ($options['eventtimelabel'] == "") $options['eventtimelabel'] = __('Event Time', 'community-events'); ?>
							<td><input type="text" id="eventtimelabel" name="eventtimelabel" size="30" value="<?php echo $options['eventtimelabel']; ?>"/></td>
							<td style='width: 20px'></td>
							<td style='width:200px'><?php _e('Add Event Button Label', 'community-events'); ?></td>
							<?php if ($options['addeventbtnlabel'] == "") $options['addeventbtnlabel'] = __('Add Event', 'community-events'); ?>
							<td><input type="text" id="addeventbtnlabel" name="addeventbtnlabel" size="30" value="<?php echo $options['addeventbtnlabel']; ?>"/></td>
						</tr>
					</table>
					</fieldset>
					</div>

					<p style="border:0;" class="submit"><input type="submit" name="submit" value="Update Settings &raquo;" /></p>
				</form>
				<?php elseif (($adminpage == "eventtypes")): ?>
				<a href="?page=community-events.php&amp;section=general">General Settings</a> | <a href="?page=community-events.php&amp;section=eventtypes"><strong>Manage Event Types</strong></a> | <a href="?page=community-events.php&amp;section=eventvenues">Manage Venues</a> | <a href="?page=community-events.php&amp;section=events">Manage Events</a><br /><br />
				<div style='float:left;margin-right: 15px'>
				<form name="cecatform" action="" method="post" id="ws-config">
					<?php
					if ( function_exists('wp_nonce_field') )
						wp_nonce_field('cepp-config');
					?>
					<?php if ($mode == "edit"): ?>
					<strong>Editing Category #<?php echo $selectedcat->event_cat_id; ?></strong><br />
					<?php endif; ?>
					Category Name: <input style="width:300px" type="text" name="name" <?php if ($mode == "edit") echo "value='" . $selectedcat->event_cat_name . "'";?>/>
					<input type="hidden" name="id" value="<?php if ($mode == "edit") echo $selectedcat->event_cat_id; ?>" />
					<?php if ($mode == "edit"): ?>
						<p style="border:0;" class="submit"><input type="submit" name="updatecat" value="Update &raquo;" /></p>
					<?php else: ?>
						<p style="border:0;" class="submit"><input type="submit" name="newcat" value="Insert New Category &raquo;" /></p>
					<?php endif; ?>
				</form>
				</div>
				<div>
					<?php $cats = $wpdb->get_results("SELECT count( e.event_id ) AS nbitems, c.event_cat_id, c.event_cat_name FROM " . $wpdb->prefix . "ce_category c LEFT JOIN " . $wpdb->prefix . "ce_events e ON e.event_category = c.event_cat_id GROUP BY c.event_cat_name");
					
							if ($cats): ?>
							  <table class='widefat' style='clear:none;width:400px;background: #DFDFDF url(/wp-admin/images/gray-grad.png) repeat-x scroll left top;'>
							  <thead>
							  <tr>
  							  <th scope='col' style='width: 50px' id='id' class='manage-column column-id' >ID</th>
							  <th scope='col' id='name' class='manage-column column-name' style=''>Name</th>
							  <th scope='col' style='width: 50px;text-align: right' id='items' class='manage-column column-items' style=''>Items</th>
							  <th style='width: 30px'></th>
							  </tr>
							  </thead>
							  
							  <tbody id='the-list' class='list:link-cat'>

							  <?php foreach($cats as $cat): ?>
								<tr>
								<td class='name column-name' style='background: #FFF'><?php echo $cat->event_cat_id; ?></td>
								<td style='background: #FFF'><a href='?page=community-events.php&amp;editcat=<?php echo $cat->event_cat_id; ?>'><strong><?php echo $cat->event_cat_name; ?></strong></a></td>
								<td style='background: #FFF;text-align:right'><?php echo $cat->nbitems; ?></td>
								<?php if ($cat->nbitems == 0): ?>
								<td style='background:#FFF'><a href='?page=community-events.php&amp;deletecat=<?php echo $cat->event_cat_id; ?>' 
								<?php echo "onclick=\"if ( confirm('" . esc_js(sprintf( __("You are about to delete this category '%s'\n  'Cancel' to stop, 'OK' to delete."), $cat->event_cat_name )) . "') ) { return true;}return false;\"" ?>><img src='<?php echo $cepluginpath; ?>/icons/delete.png' /></a></td>
								<?php else: ?>
								<td style='background: #FFF'></td>
								<?php endif; ?>
								</tr>
							  <?php endforeach; ?>				
							  
							  </tbody>
							  </table>
							 
							<?php endif; ?>
							
							<p>Categories can only be deleted when they do not have any associated events.</p>
				</div>
				
				<?php elseif (($adminpage == "eventvenues")): ?>
				<a href="?page=community-events.php&amp;section=general">General Settings</a> | <a href="?page=community-events.php&amp;section=eventtypes">Manage Event Types</a> | <a href="?page=community-events.php&amp;section=eventvenues"><strong>Manage Venues</strong></a> | <a href="?page=community-events.php&amp;section=events">Manage Events</a><br /><br />
				<div style='float:left;margin-right: 15px'>
				<form name="cevenueform" action="" method="post" id="ce-config">
					<?php
					if ( function_exists('wp_nonce_field') )
						wp_nonce_field('cepp-config');
					?>
					<?php if ($mode == "edit"): ?>
					<strong>Editing Venue #<?php echo $selectedvenue->ce_venue_id; ?></strong><br />
					<?php endif; ?>
					<table>
						<tr>
							<td>Venue Name</td>
							<td><input style="width:300px" type="text" name="ce_venue_name" <?php if ($mode == "edit") echo "value='" . $selectedvenue->ce_venue_name . "'";?>/></td>
						</tr>
						<tr>
							<td>Venue Address</td>
							<td><input style="width:300px" type="text" name="ce_venue_address" <?php if ($mode == "edit") echo "value='" . $selectedvenue->ce_venue_address . "'";?>/></td>
						</tr>	
						<tr>
							<td>Venue City</td>
							<td><input style="width:300px" type="text" name="ce_venue_city" <?php if ($mode == "edit") echo "value='" . $selectedvenue->ce_venue_city . "'";?>/></td>
						</tr>
						<tr>
							<td>Venue Zip Code</td>
							<td><input style="width:300px" type="text" name="ce_venue_zipcode" <?php if ($mode == "edit") echo "value='" . $selectedvenue->ce_venue_zipcode . "'";?>/></td>
						</tr>	
						<tr>
							<td>Venue Phone</td>
							<td><input style="width:300px" type="text" name="ce_venue_phone" <?php if ($mode == "edit") echo "value='" . $selectedvenue->ce_venue_phone . "'";?>/></td>
						</tr>				
						<tr>
							<td>Venue E-mail</td>
							<td><input style="width:300px" type="text" name="ce_venue_email" <?php if ($mode == "edit") echo "value='" . $selectedvenue->ce_venue_email . "'";?>/></td>
						</tr>	
						<tr>
							<td>Venue URL</td>
							<td><input style="width:300px" type="text" name="ce_venue_url" <?php if ($mode == "edit") echo "value='" . $selectedvenue->ce_venue_url . "'";?>/></td>
						</tr>	
					</table>
					<input type="hidden" name="ce_venue_id" value="<?php if ($mode == "edit") echo $selectedvenue->ce_venue_id; ?>" />
					<?php if ($mode == "edit"): ?>
						<p style="border:0;" class="submit"><input type="submit" name="updatevenue" value="Update &raquo;" /></p>
					<?php else: ?>
						<p style="border:0;" class="submit"><input type="submit" name="newvenue" value="Insert New Venue &raquo;" /></p>
					<?php endif; ?>
				</form>
				</div>
				<div>
					<?php $venues = $wpdb->get_results("SELECT count( e.event_id ) AS nbitems, v.ce_venue_id, v.ce_venue_name FROM " . $wpdb->prefix . "ce_venues v LEFT JOIN " . $wpdb->prefix . "ce_events e ON e.event_venue = v.ce_venue_id GROUP BY v.ce_venue_name");
					
							if ($venues): ?>
							  <table class='widefat' style='clear:none;width:400px;background: #DFDFDF url(/wp-admin/images/gray-grad.png) repeat-x scroll left top;'>
							  <thead>
							  <tr>
  							  <th scope='col' style='width: 50px' id='id' class='manage-column column-id' >ID</th>
							  <th scope='col' id='name' class='manage-column column-name' style=''>Name</th>
							  <th scope='col' style='width: 50px;text-align: right' id='items' class='manage-column column-items' style=''>Items</th>
							  <th style='width: 30px'></th>
							  </tr>
							  </thead>
							  
							  <tbody id='the-list' class='list:link-cat'>

							  <?php foreach($venues as $venue): ?>
								<tr>
								<td class='name column-name' style='background: #FFF'><?php echo $venue->ce_venue_id; ?></td>
								<td style='background: #FFF'><a href='?page=community-events.php&amp;editvenue=<?php echo $venue->ce_venue_id; ?>'><strong><?php echo $venue->ce_venue_name; ?></strong></a></td>
								<td style='background: #FFF;text-align:right'><?php echo $venue->nbitems; ?></td>
								<?php if ($venue->nbitems == 0): ?>
								<td style='background:#FFF'><a href='?page=community-events.php&amp;deletevenue=<?php echo $venue->ce_venue_id; ?>' 
								<?php echo "onclick=\"if ( confirm('" . esc_js(sprintf( __("You are about to delete this venue '%s'\n  'Cancel' to stop, 'OK' to delete."), $venue->ce_venue_name )) . "') ) { return true;}return false;\"" ?>><img src='<?php echo $cepluginpath; ?>/icons/delete.png' /></a></td>
								<?php else: ?>
								<td style='background: #FFF'></td>
								<?php endif; ?>
								</tr>
							  <?php endforeach; ?>				
							  
							  </tbody>
							  </table>
							 
							<?php endif; ?>
							
							<p>Venues can only be deleted when they do not have any associated events.</p>
				</div>
				
				<?php elseif (($adminpage == "events")): 
				
				$currentday = date("z", current_time('timestamp')) + 1;
					if (date("L") == 1 && $currentday > 60)
						$currentday++;		
	
				$currentyear = date("Y");
				?>
				<a href="?page=community-events.php&amp;section=general">General Settings</a> | <a href="?page=community-events.php&amp;section=eventtypes">Manage Event Types</a> | <a href="?page=community-events.php&amp;section=eventvenues">Manage Venues</a> | <a href="?page=community-events.php&amp;section=events"><strong>Manage Events</strong></a><br /><br />
				
				<script type="text/javascript">
				 jQuery(document).ready(function() {
						jQuery("#datepicker").datepick({dateFormat: 'yyyy-mm-dd', showTrigger: '<button type="button" class="trigger"><img src="<?php echo $cepluginpath; ?>/icons/calendar.png" /></button>', minDate: -0});
				 });
				</script>
				
				<div style='float:left;margin-right: 15px;width: 500px;'>
					<form name="ceeventsform" action="" method="post" id="ce-config">
					<?php
					if ( function_exists('wp_nonce_field') )
						wp_nonce_field('cepp-config');
					?>
					<input type="hidden" name="event_id" value="<?php if ($mode == "edit") echo $selectedevent->event_id; ?>" />
					<?php if ($mode == "edit"): ?>
					<strong>Editing Item #<?php echo $selectedevent->event_id; ?></strong>
					<?php endif; ?>

					<table>
					<?php
					if ( function_exists('wp_nonce_field') )
						wp_nonce_field('cepp-config');
					?>
					<tr>
					<td style='width: 180px'>Event Name</td>
					<td><input style="width:360px" type="text" name="event_name" <?php if ($mode == "edit") echo "value='" . $selectedevent->event_name . "'";?>/></td>
					</tr>
					<tr>
					<td>Category</td>
					<td><select style='width: 360px' name="event_category">
					<?php $cats = $wpdb->get_results("SELECT * from " . $wpdb->prefix. "ce_category ORDER by event_cat_name");
					
						foreach ($cats as $cat)
						{
							if ($cat->event_cat_id == $selectedevent->event_category)
									$selectedstring = "selected='selected'";
								else 
									$selectedstring = ""; 
									
							echo "<option value='" . $cat->event_cat_id . "' " . $selectedstring . ">" .  $cat->event_cat_name . "\n";
						}
					?></select></td>
					</tr>
					<tr>
					<td>Venue</td>
					<td><select style='width: 360px' name="event_venue">
					<?php $venues = $wpdb->get_results("SELECT * from " . $wpdb->prefix. "ce_venues ORDER by ce_venue_name");
					
						foreach ($venues as $venue)
						{
							if ($venue->ce_venue_id == $selectedevent->event_venue)
									$selectedstring = "selected='selected'";
								else 
									$selectedstring = ""; 
									
							echo "<option value='" . $venue->ce_venue_id . "' " . $selectedstring . ">" .  $venue->ce_venue_name . "\n";
						}
					?></select></td>
					</tr>					
					<tr>
						<td>Description</td>
						<td><input style="width:360px" type="text" name="event_description" <?php if ($mode == "edit") echo "value='" . stripslashes($selectedevent->event_description) . "'";?>/></td>
					</tr>
					<tr>
						<td>Event Web Address</td>
						<td><input style="width:360px" type="text" name="event_url" <?php if ($mode == "edit") echo "value='" . $selectedevent->event_url . "'";?>/></td>
					</tr>
					<tr>
						<td>Event Ticket Purchase Web Address</td>
						<td><input style="width:360px" type="text" name="event_ticket_url" <?php if ($mode == "edit") echo "value='" . $selectedevent->event_ticket_url . "'";?>/></td>
					</tr>
					<tr>
						<td>Date</td>
						<td><input type="text" id="datepicker" name="event_date" size="30" <?php if ($mode == "edit") echo "value='" . $selectedevent->event_date . "'"; else echo "value='" . date('Y-m-d', current_time('timestamp')) . "'"; ?>/></td>
					</tr>
					<tr>
					<td>Time</td>
					<td><input type="text" name="event_time" size="30" <?php if ($mode == "edit") echo "value='" . $selectedevent->event_time. "'";?>/></td>
					</tr>
					<tr>
					</table>
					<?php if ($mode == "edit"): ?>
						<p style="border:0;" class="submit"><input type="submit" name="updateevent" value="Update &raquo;" /></p>
					<?php else: ?>
						<p style="border:0;" class="submit"><input type="submit" name="newevent" value="Insert New Event &raquo;" /></p>
					<?php endif; ?>
				</form>
				</div>
				<div style='float: left'>
				<?php 
					$currentday = date("z", current_time('timestamp')) + 1;
					if (date("L") == 1 && $currentday > 60)
						$currentday++;		
	
					$currentyear = date("Y");
					
					$eventcountquery = "SELECT count(*) from " . $wpdb->prefix . "ce_events where YEAR(event_date) = " . $currentyear . " and DAYOFYEAR(DATE(event_date)) >= " . $currentday;
					$eventcounttotal = $wpdb->get_var($eventcountquery);	
					
					if ($_GET['pagecount'] != "")
						$pagecount = $_GET['pagecount']; 
					else
						$pagecount = 1;

					    echo print_event_table($currentyear, $currentday, $pagecount); ?>					
						
						<input type='hidden' name='eventpage' id='eventpage' value='<?php echo $pagecount; ?>' />
							  
						<div class='navleft' style='float: left; padding-top: 4px'><button id='previouspage'><img alt="Previous page of events" src='<?php echo $cepluginpath; ?>/icons/resultset_previous.png' /></button></div>
					  
						<div class='navright' style='float: right; padding-top: 4px'><button id='nextpage'><img alt="Next page of events" src='<?php echo $cepluginpath; ?>/icons/resultset_next.png' /></button></div>
					  
						<script type="text/javascript">
							jQuery(document).ready(function() {
								var currentpage = <?php echo $pagecount; ?>;
								if (currentpage < 2) jQuery(".navleft").hide();
								var eventcounttotal = <?php echo $eventcounttotal; ?>;								
								if (eventcounttotal < (currentpage * 10)) { jQuery('.navright').hide(); }
								jQuery(".navright").click(function() { var el = jQuery('#eventpage');
								var eventcount = <?php echo $eventcounttotal; ?>;
								el.val( parseInt( el.val(), 10 ) + 1 );
								if (el.val() * 10 > eventcount) { 
									jQuery('.navright').hide()
								};
								if (1 < el.val()) { 
									jQuery('.navleft').show();
								};
								var map = {currentyear : <?php echo $currentyear; ?>, currentday : <?php echo $currentday; ?>, page: el.val() };
								jQuery('#ce-event-list').replaceWith('<div id=\"ce-event-list\"><img src=\"<?php echo WP_PLUGIN_URL; ?>/community-events/icons/Ajax-loader.gif\" alt=\"Loading data, please wait...\"></div>');
								jQuery.get('<?php echo WP_PLUGIN_URL; ?>/community-events/get-events-admin.php', map, function(data){
									jQuery('#ce-event-list').replaceWith(data);
									});
								});
								jQuery(".navleft").click(function() { var el = jQuery('#eventpage');
								var eventcount = <?php echo $eventcounttotal; ?>;
								el.val( parseInt( el.val(), 10 ) - 1 );
								if (el.val() * 10 < eventcount) { 
									jQuery('.navright').show()
								};
								if (1 == el.val()) { 
									jQuery('.navleft').hide();
								};
								var map = {currentyear : <?php echo $currentyear; ?>, currentday : <?php echo $currentday; ?>, page: el.val() };
								jQuery('#ce-event-list').replaceWith('<div id=\"ce-event-list\"><img src=\"<?php echo WP_PLUGIN_URL; ?>/community-events/icons/Ajax-loader.gif\" alt=\"Loading data, please wait...\"></div>');jQuery.get('<?php echo WP_PLUGIN_URL; ?>/community-events/get-events-admin.php', map, function(data){jQuery('#ce-event-list').replaceWith(data);});
								});
							});
						</script>								
						
					<?php endif; ?>
				</div>
		</div>
			<?php
		} // end config_page()

	} // end class CE_Admin
} //endif

function ce_7day_func($atts) {
	extract(shortcode_atts(array(
	), $atts));
	
	$options = get_option('CE_PP');
	
	return ce_7day($options['fullscheduleurl'], $options['outlook'], $options['addeventurl']);
}

function venuelist ($year = 0, $dayofyear = 0, $outlook = 'true', $showdate = 'false', $maxevents = 5) {

	global $wpdb;
	global $cepluginpath;
	
	$output = "<table class='ce-7day-innertable' id='ce-7day-innertable'>\n";

	if ($outlook == 'false')
	{			
		$eventquery = "SELECT * from " . $wpdb->prefix . "ce_events e LEFT JOIN " . $wpdb->prefix . "ce_venues v ON e.event_venue = v.ce_venue_id ";
		$eventquery .= "where YEAR(event_date) = " . $year . " and DAYOFYEAR(DATE(event_date)) = " . $dayofyear;
		$eventquery .= " order by e.event_name";
		
		$events = $wpdb->get_results($eventquery, ARRAY_A);
		
		$output = "<table class='ce-7day-innertable' id='ce-7day-innertable'>\n";
		$dayofyearforcalc = $dayofyear - 1;
				
		if ($showdate == 'true')
		{	$dayofyearforcalc = $dayofyear - 1;
			$output .= "<tr><td><span class='ce-outlook-day'>" . date("l, M jS", strtotime('+ ' . $dayofyearforcalc . 'days', mktime(0,0,0,1,1,$year))) . "</span></td></tr>";
		}

		if ($events)
		{
			if (count($events) < $maxevents)
				$maxevents = count($events);
				
			$randomevents = array_rand($events, $maxevents);
			
			foreach($randomevents as $randomevent)
			{
				$output .= "<tr><td><span class='ce-event-name'>";
				if ($events[$randomevent]['event_url'] != '')
					$output .= "<a href='" . $events[$randomevent]['event_url'] . "'>";

				$output .= $events[$randomevent]['event_name'];

				if ($events[$randomevent]['event_url'] != '')
					$output .= "</a>";
				
				$output .= "</span> ";
				
				if ($events[$randomevent]['event_time'] != "")
					$output .= "<span class='ce-event-time'>" . $events[$randomevent]['event_time'] . "</span>. ";
					
				if ($events[$randomevent]['ce_venue_name'] != "")
					$output .= "<span>" . $events[$randomevent]['ce_venue_name'] . "</span></td></tr>\n";
			}
			
			if (count($events) > $maxevents)
				$output .= "<tr><td><a href='#' onClick=\"showDayEvents('" . $dayofyear . "', '" . $year . "', false, true);return false;\">See all events for " . date("l, M jS", strtotime('+ ' . $dayofyearforcalc . 'days', mktime(0,0,0,1,1,$year))) . "</a></td></tr>\n";
		}
		else
			$output .= "\n<tr><td>No events for this date.</td></tr>\n";
			
		if ($showdate == 'true')
		{
			$output .= "<tr><td>Select a date: <input type='text' id='datepicker' name='event_date' size='30' /><input type='hidden' id='dayofyear' name='dayofyear' size='30' /><input type='hidden' id='year' name='year' size='30' />\n";
			$output .= "<button id='displayDate'>Go!</button></td></tr>\n";
		}
	}
	elseif ($outlook == 'true')
	{	
		for ($i = 0; $i <= 6; $i++)
		{		
			$calculatedday = $dayofyear + $i;
		
			$eventquery = "SELECT * from " . $wpdb->prefix . "ce_events e LEFT JOIN " . $wpdb->prefix . "ce_venues v ON e.event_venue = v.ce_venue_id ";
			$eventquery .= "where YEAR(event_date) = " . $year . " and DAYOFYEAR(DATE(event_date)) = " . $calculatedday;
			$eventquery .= " order by e.event_name";
			$dayevents = $wpdb->get_results($eventquery, ARRAY_A);
			
			$output .= "\t\t<tr><td class='" . ($i % 2 == 0 ? "even" : "odd") . "'><span class='ce-outlook-day'>" . date("l, M jS", strtotime("+" . $i . " day", current_time('timestamp')));
			
			if (count($dayevents) > 1) $output .= "<span class='seemore'><a href='#' onClick=\"showDayEvents('" . $calculatedday . "', '" . $year . "', false, true);return false;\">See more</a></span>";
			
			$output .= "</span><br />\n";
			
			if ($dayevents)
			{
				$randomentry = array_rand($dayevents);
							
				$output .= "<span class='";
				
				if ($dayevents[$randomentry]['event_description'] != "")
					$output .= "tooltip ";
				
				$output .= "ce-outlook-event-name'";
				
				if ($dayevents[$randomentry]['event_description'] != "")
					$output .= " title='" . $dayevents[$randomentry]['event_description'] . "'";
				
				$output .= ">";
				
				if ($dayevents[$randomentry]['event_url'] != '')
					$output .= "<a id='Event Link' href='" . $dayevents[$randomentry]['event_url'] . "'>";

				$output .= $dayevents[$randomentry]['event_name'];

				if ($dayevents[$randomentry]['event_url'] != '')
					$output .= "</a>";
				
				$output .= "</span> ";
				
				if ($dayevents[$randomentry]['event_time'] != "")
					$output .= "<span class='ce-event-time'>" . $dayevents[$randomentry]['event_time'] . "</span>. ";
					
				if ($dayevents[$randomentry]['ce_venue_name'] != "")
					$output .= "<span class='tooltip ce-venue-name' title='<strong>" . $dayevents[$randomentry]['ce_venue_name'] . "</strong><br />" . $dayevents[$randomentry]['ce_venue_address']  . "<br />" . $dayevents[$randomentry]['ce_venue_city'] . "<br />" . $dayevents[$randomentry]['ce_venue_zipcode'] . "<br />" . $dayevents[$randomentry]['ce_venue_email'] . "<br />" . $dayevents[$randomentry]['ce_venue_phone'] . "<br />" .  $dayevents[$randomentry]['ce_venue_url'] . "'>" . $dayevents[$randomentry]['ce_venue_name'] . "</span>\n";
					
				if ($dayevents[$randomentry]['event_ticket_url'] != "")
					$output .= "<span class='ce-ticket-link'><a id='event url' href='" . $dayevents[$randomentry]['event_ticket_url'] . "'<img title='Ticket Link' src='" . $cepluginpath . "/icons/tickets.gif' /></a></span>\n";
					
				$output .= "</td></tr>";
			}
			else
				$output .= "<span class='ce-outlook-event-name'>No events.</span></td></tr>\n";		
		}
		
		$output .= "<tr><td>Select a date: <input type='text' id='datepicker' name='event_date' size='30' /><input type='hidden' id='dayofyear' name='dayofyear' size='30' /><input type='hidden' id='year' name='year' size='30' />\n";
		$output .= "<button id='displayDate'>Go!</button></td></tr>\n";
	}
	
	$output .= "<script type='text/javascript'>\n";
	$output .= "jQuery(document).ready(function() {\n";
	$output .= "\tjQuery('#datepicker').datepick({dateFormat: 'yyyy-mm-dd', showTrigger: '<button type=\"button\" class=\"trigger\"><img src=\"" . $cepluginpath . "/icons/calendar.png\" /></button>', minDate: -0, onSelect: function(dates) { jQuery('#dayofyear').val(truncate(jQuery.datepick.dayOfYear(dates[0]))+1); jQuery('#year').val(jQuery.datepick.formatDate('yyyy', dates[0]))}});";
	$output .= "\tjQuery('#displayDate').click(function() { if (jQuery('#dayofyear').val() != '') {showDayEvents(jQuery('#dayofyear').val(), jQuery('#year').val(), false, true)} else { alert('Select date first'); };});\n";
	$output .= "});\n";
	$output .= "</script>";	
	
	$output .= "\t</table>";
	
	return $output;
}

	
function ce_7day($fullscheduleurl = '', $outlook = true, $addeventurl = '') {
	global $wpdb;
	global $cepluginpath;
	
	$currentday = date("z", current_time('timestamp')) + 1;

	if (date("L") == 1 && $currentday > 60)
		$currentday++;		
	
	$currentyear = date("Y");	
		
	$output = "<SCRIPT LANGUAGE=\"JavaScript\">\n";

	$output .= "function showDayEvents ( _dayofyear, _year, _outlook, _showdate) {\n";
	$output .= "var map = {dayofyear : _dayofyear, year : _year, outlook: _outlook, showdate: _showdate }\n";
	$output .= "\tjQuery('.ce-7day-innertable').replaceWith('<div class=\"ce-7day-innertable\"><img src=\"" . WP_PLUGIN_URL . "/community-events/icons/Ajax-loader.gif\" alt=\"Loading data, please wait...\"></div>');jQuery.get('" . WP_PLUGIN_URL . "/community-events/get-events.php', map, function(data){jQuery('.ce-7day-innertable').replaceWith(data);});\n";
	$output .= "}\n\n";
	
	$output .= "function truncate(n) {\n";
	$output .= "return Math[n > 0 ? 'floor' : 'ceil'](n);\n";
	$output .= "}\n";
	
	$output .= "jQuery(document).ready(function() {\n";
    $output .= "\tjQuery('.ce-day-link').click(function() {\n";
	$output .= "\t\tvar elementid = jQuery(this).attr('id');\n";
	$output .= "\t\telementid = '#' + elementid + '_cell';\n";
	$output .= "\t\tjQuery('.ce-daybox').each(function()\n";
	$output .= "\t\t\t{ jQuery(this).removeClass('selected');	}\n";
	$output .= "\t\t);\n";
	$output .= "\t\tjQuery(elementid).addClass('selected');\n";
	$output .= "\t});\n\n";
	
	$output .= "\tjQuery('.tooltip').each(function()\n";
	$output .= "\t\t{ jQuery(this).tipTip(); }\n";
	$output .= "\t);\n";
	$output .= "});\n";

	$output .= "</SCRIPT>\n\n";
	
	$output .= "<div class='community-events-7day'><table class='ce-7day-toptable'><tr>\n";
	
	if ($outlook == true)
	{
		$output .= "\t<td class='ce-daybox selected' id='day_0_" . $currentyear . "_cell'><a href='#' class='ce-day-link' id='day_0_" . $currentyear . "' onClick=\"showDayEvents('" . $currentday . "', '" . $currentyear . "', true, false);return false;\"><strong>Upcoming Events</strong></a></td>\n";
	}
	
	for ($i = 0; $i <= 6; $i++) {
		$daynumber = $currentday + $i;
		$output .= "\t<td class='ce-daybox " . ($i % 2 == 0 ? "even" : "odd");
		$output .= "' id='day_" . $daynumber . "_" . $currentyear . "_cell'>\n";
		
		$output .= "\t\t<span class='ce-dayname'><a href='#' class='ce-day-link' id='day_" . $daynumber . "_" . $currentyear . "' onClick=\"showDayEvents('" . $daynumber. "', '" . $currentyear . "', false, false);return false;\">" . date("D", strtotime("+" . $i . " day", current_time('timestamp'))) . "<br /><span class='ce-date'>" . date("j", strtotime("+" . $i . " day", current_time('timestamp'))) . "</a></span>\n";
		
		$output .= "\t</td>\n";
	}

	$output .= "</tr>\n\t<tr><td class='ce-inner-table-row' colspan='" . (($outlook == true) ? 8 : 7) . "'>\n";
	
	if ($outlook == true)
		$output .= venuelist($currentyear, $currentday, 'true');
	else
		$output .= venuelist($currentyear, $currentday, 'false');
	$output .= "\t</td></tr>\n";
	
	if ($fullscheduleurl != '')
		$output .= "<tr class='ce-full-schedule-link'><td colspan='" . (($outlook == true) ? 8 : 7) . "'><a href='" . $fullscheduleurl . "'>Full Schedule</a></td></tr>";
		
	if ($addeventurl != '')
		$output .= "<tr class='ce-add-event-link'><td colspan='" . (($outlook == true) ? 8 : 7) . "'><a href='" . $addeventurl . "'>Submit your own event</a></td></tr>";
		
	
	$output .= "</table></div>\n";

 	return $output;
}

function ce_full_func($atts) {
	extract(shortcode_atts(array(
	), $atts));
	
	$options = get_option('CE_PP');
	
	return ce_full();
}

function ce_full() {
		global $wpdb;
	
	$currentday = date("z", current_time('timestamp')) + 1;
	if (date("L") == 1 && $currentday > 60)
		$currentday++;		
	
	$currentyear = date("Y");	
	
	$eventquery = "SELECT *, UNIX_TIMESTAMP(event_date) as datestamp, DAYOFYEAR(DATE(event_date)) as doy from " . $wpdb->prefix . "ce_events e LEFT JOIN ";
	$eventquery .= $wpdb->prefix . "ce_venues v ON e.event_venue = v.ce_venue_id where (YEAR(event_date) = " . $currentyear;
	$eventquery .= " and DAYOFYEAR(DATE(event_date)) >= " . $currentday . ") ";
	$eventquery .= " or YEAR(event_date) > " . $currentyear;
	$eventquery .= " order by event_date, event_name";
	$events = $wpdb->get_results($eventquery, ARRAY_A);
	
	$doy = 0;
	
	$output .= "<div class='community-events-full'>\n";
	
	$output .= "\n\t<div class='ce-full-events-table'><table>\n";

	if ($events)
	{	
		foreach($events as $event)
		{
		
			if ($doy != $event['doy'])
			{
				$output .= "<tr><td colspan='2' class='ce-full-dayrow'>" . date("l, F jS", $event['datestamp']) . "</td></tr>";
				$output .= "<tr><td class='ce-full-dayevent'>Event</td><td class='ce-full-dayvenue'>Venue</td></tr>";
				$doy = $event['doy'];
			}
				
			$output .= "\t\t<tr><td class='ce-full-event-name'>";

			if ($event['event_url'] != '')
				$output .= "<a href='" . $event['event_url'] . "'>";

			$output .= $event['event_name'];

			if ($event['event_url'] != '')
				$output .= "</a>";

			$output .= "</td><td class='ce-full-event-venue'>" . $event['ce_venue_name'] . "</td></tr>\n";
		}	
	}
	else
		$output .= "\n\n<tr><td>No events for this date.</td></tr>\n";
	
	$output .= "\t</table></div>\n";
	
	if ($fullscheduleurl != '')
		$output .= "<div class='ce-full-schedule'><a href='" . $fullscheduleurl . "'>Full Schedule</a></div>";
	
	$output .= "</div>\n";

	return $output;
}

function ce_addevent_func($atts) {
	extract(shortcode_atts(array(
	), $atts));
	
	global $wpdb;
	
	$options = get_option('CE_PP');
	
	if ($_POST['event_name'])
	{
		if ($_POST['event_name'] != '')
		{
			$newevent = array("event_name" => wp_specialchars(stripslashes($_POST['event_name'])), "event_date" => wp_specialchars(stripslashes($_POST['event_date'])), "event_time" => wp_specialchars(stripslashes($_POST['event_time'])),
				"event_description" => wp_specialchars(stripslashes($_POST['event_description'])), "event_url" => wp_specialchars(stripslashes($_POST['event_url'])), "event_ticket_url" => wp_specialchars(stripslashes($_POST['event_ticket_url'])), "event_venue" => $_POST['event_venue'], "event_category" => $_POST['event_category']);
			
			$wpdb->insert( $wpdb->prefix.'ce_events', $newevent);
						
			if ($options['emailnewevent'])
			{
				$adminmail = get_option('admin_email');
				$headers = "MIME-Version: 1.0\r\n";
				$headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
				
				$message = __('A user submitted a new event to your Wordpress Community Events database.', 'community-events') . "<br /><br />";
				$message .= __('Event Name', 'community-events') . ": " . $newevent['event_name'] . "<br />";
				$message .= __('Event Category', 'community-events') . ": " . $newevent['event_category'] . "<br />";
				$message .= __('Event Venue', 'community-events') . ": " . $newevent['event_venue'] . "<br />";
				$message .= __('Event Description', 'community-events') . ": " . $newevent['event_description'] . "<br />";
				$message .= __('Event Web Address', 'community-events') . ": " . $newevent['event_url'] . "<br />";
				$message .= __('Event Ticket Purchase Link', 'community-events') . ": " . $newevent['event_ticket_url'] . "<br /><br />";
				$message .= __('Event Date', 'community-events') . ": " . $newevent['event_date'] . "<br /><br />";
				$message .= __('Event Dime', 'community-events') . ": " . $newevent['event_time'] . "<br /><br />";
							
				if ( !defined('WP_ADMIN_URL') )
					define( 'WP_ADMIN_URL', get_option('siteurl') . '/wp-admin');
										
				$message .= "<br /><br />" . __('Message Generated by', 'community-events') . " <a href='http://yannickcorner.nayanna.biz/wordpress-plugins/community-events/'>Community Events</a> for Wordpress";
				
				wp_mail($adminmail, htmlspecialchars_decode(get_option('blogname'), ENT_QUOTES) . " - New event added: " . htmlspecialchars($_POST['event_name']), $message, $headers);
			}	

				$message = "<div class='eventconfirmsubmit'>Thank you for your submission.</div>\n";
		}
	}
	
	return $message . ce_addevent($options['columns'], $options['addeventreqlogin'], $options['addneweventmsg'], $options['eventnamelabel'], $options['eventcatlabel'], 
						$options['eventvenuelabel'], $options['eventdesclabel'], $options['eventaddrlabel'], $options['eventticketaddrlabel'], $options['eventdatelabel'],
						$options['eventtimelabel'], $options['addeventbtnlabel']);
}

function ce_addevent($columns = 2, $addeventreqlogin = false, $addneweventmsg = "", $eventnamelabel = "", $eventcatlabel = "", $eventvenuelabel = "", 
					$eventdesclabel = "", $eventaddrlabel = "", $eventticketaddrlabel = "", $eventdatelabel = "", $eventtimelabel = "", $addeventbtnlabel = "") {

	global $wpdb;
	global $cepluginpath;

	if (($addeventreqlogin && current_user_can("read")) || !$addeventreqlogin)
	{
		$output = "<form method='post' id='ceaddevent'>\n";
		$output .= "<div class='ce-addevent'>\n";
		
		if ($addneweventmsg == "") $addneweventmsg = __('Add New Event', 'community-events');
		$output .= "<div id='ce-addeventtitle'>" . $addneweventmsg . "</div>\n";
		
		$output .= "<table class='ce-addeventtable'><tr>\n";
		
		if ($eventnamelabel == "") $eventnamelabel = __('Event Name', 'community-events');
		$output .= "<th style='width: 100px'>" . $eventnamelabel . "</th><td colspan=" . ($columns == 1 ? 1 : 3) . "><input type='text' size='80' name='event_name' id='event_name' /></td></tr>\n";
		
		if ($eventcatlabel == "") $eventcatlabel = __('Event Category', 'community-events');		
		$output .= "<tr><th style='width: 100px'>" . $eventcatlabel . "</th><td><select style='width: 200px' name='event_category'>\n";
	$cats = $wpdb->get_results("SELECT * from " . $wpdb->prefix. "ce_category ORDER by event_cat_name");	
		foreach ($cats as $cat)
		{
			if ($cat->event_cat_id == $selectedevent->event_category)
					$selectedstring = "selected='selected'";
				else 
					$selectedstring = ""; 
					
			$output .= "<option value='" . $cat->event_cat_id . "' " . $selectedstring . ">" .  $cat->event_cat_name . "\n";
		}
		$output .= "</select></td>\n";
		
		if ($columns == 1)
			$output .= "</tr><tr>";
			
		if ($eventvenuelabel == "") $eventvenuelabel = __('Event Venue', 'community-events');		
		$output .= "<th style='width: 100px'>" . $eventvenuelabel . "</th><td><select style='width: 200px' name='event_venue'>\n";
		$venues = $wpdb->get_results("SELECT * from " . $wpdb->prefix. "ce_venues ORDER by ce_venue_name");
					
		foreach ($venues as $venue)
		{
			if ($venue->ce_venue_id == $selectedevent->event_venue)
					$selectedstring = "selected='selected'";
				else 
					$selectedstring = ""; 
					
			$output .= "<option value='" . $venue->ce_venue_id . "' " . $selectedstring . ">" .  $venue->ce_venue_name . "\n";
		}
		$output .= "</select></td></tr>\n";			
		
		if ($eventdesclabel == "") $eventdesclabel = __('Event Description', 'community-events');
		$output .= "<tr><th>" . $eventdesclabel . "</th><td colspan=" . ($columns == 1 ? 1 : 3) . "><input type='text' size='80' name='event_description' id='event_description' /></td></tr>\n";				
			
		if ($eventaddrlabel == "") $eventaddrlabel = __('Event Web Address', 'community-events');
		$output .= "<tr><th>" . $eventaddrlabel . "</th><td colspan=" . ($columns == 1 ? 1 : 3) . "><input type='text' size='80' name='event_url' id='event_url' /></td></tr>\n";
		
		if ($eventticketaddrlabel == "") $eventticketaddrlabel = __('Event Ticket Purchase Link', 'community-events');
		$output .= "<tr><th>" . $eventticketaddrlabel . "</th><td colspan=" . ($columns == 1 ? 1 : 3) . "><input type='text' size='80' name='event_ticket_url' id='event_ticket_url' /></td></tr>\n";
		
		if ($eventdatelabel == "") $eventdatelabel = __('Event Date', 'community-events');
		$output .= "<tr><th>" . $eventdatelabel . "</th><td><input type='text' name='event_date' id='datepickeraddform' value='" . date('Y-m-d', current_time('timestamp')) . "' /></td>\n";
		
		if ($columns == 1)
			$output .= "</tr><tr>";
		
		if ($eventtimelabel == "") $eventtimelabel = __('Event Time', 'community-events');
		$output .= "<th>" . $eventtimelabel . "</th><td><input type='text' name='event_time' id='event_time' /></td></tr>\n";
							
		$output .= "</table>\n";
		
		if ($addeventbtnlabel == "") $addeventbtnlabel = __('Add Event', 'community-events');
		$output .= '<span style="border:0;" class="submit"><input type="submit" name="submit" value="' . $addeventbtnlabel . '" /></span>';
		
		$output .= "</div>\n";
		$output .= "</form>\n\n";
		
		$output .= "<script type='text/javascript'>\n";
		$output .= "jQuery(document).ready(function() {\n";
		$output .= "jQuery('#datepickeraddform').datepick({dateFormat: 'yyyy-mm-dd', showTrigger: '<button type=\"button\" class=\"trigger\"><img src=\"" . $cepluginpath . "/icons/calendar.png\" /></button>', minDate: -0});\n";
		$output .= "});\n";
		$output .= "</script>\n";
	}

	return $output;
}

function ce_header() {
	echo '<link rel="stylesheet" type="text/css" media="screen" href="'. WP_PLUGIN_URL . '/community-events/stylesheet.css"/>';
}

function community_events_init() {
	wp_enqueue_script('datepicker', get_bloginfo('wpurl') . '/wp-content/plugins/community-events/js/jquery.datepick.min.js');
	wp_enqueue_style('datepickerstyle', get_bloginfo('wpurl') . '/wp-content/plugins/community-events/css/jquery.datepick.css');
	wp_enqueue_script('tiptip', get_bloginfo('wpurl') . '/wp-content/plugins/community-events/tiptip/jquery.tipTip.minified.js');
	wp_enqueue_style('tiptipstyle', get_bloginfo('wpurl') . '/wp-content/plugins/community-events/tiptip/tipTip.css');
	global $cepluginpath;
	load_plugin_textdomain( 'community-events', $llpluginpath . '/languages', 'community-events/languages');
}  

function ce_daily_cleanup() {
	global $wpdb;
	
	$currentday = date("z", current_time('timestamp')) + 1;
	if (date("L") == 1 && $currentday > 60)
		$currentday++;		
	
	$currentyear = date("Y");	
	
	$cleanupquery = "DELETE FROM " . $wpdb->prefix . "ce_events e where (YEAR(event_date) <= " . $currentyear;
	$cleanupquery .= " and DAYOFYEAR(DATE(event_date)) < " . $currentday . ") ";
	
	$wpdb->get_results($cleanupquery);
}


// adds the menu item to the admin interface
add_action('admin_menu', array('CE_Admin','add_config_page'));

add_shortcode('community-events-7day', 'ce_7day_func');

add_shortcode('community-events-full', 'ce_full_func');

add_shortcode('community-events-addevent', 'ce_addevent_func');

add_action('wp_head', 'ce_header');

wp_enqueue_script('jquery');

add_action('init', 'community_events_init');

add_action('ce_daily_event', 'ce_daily_cleanup');

?>