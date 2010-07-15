<?php
/*Plugin Name: Community Events
Plugin URI: http://yannickcorner.nayanna.biz/wordpress-plugins/community-events
Description: A plugin used to create a page with a list of TV shows
Version: 1.0
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
		
		update_option('CE_PP',$options);
	}
}
register_activation_hook(CE_FILE, 'ce_install');

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
				check_admin_referer('wspp-config');
				
				foreach (array('fullscheduleurl') as $option_name) {
						if (isset($_POST[$option_name])) {
							$options[$option_name] = $_POST[$option_name];
						}
					}
					
				foreach (array('adjusttooltipposition') as $option_name) {
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
						wp_nonce_field('wspp-config');
					?>
					<table>
						<tr>
							<td style='width: 140px'>Full Schedule URL</td>
							<td><input style="width:660px" type="text" name="fullscheduleurl" <?php echo "value='" . $options['fullscheduleurl'] . "'";?>/></td>
						</tr>
					</table>
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
							
							<p>Categories can only be deleted when they don't have any associated events.</p>
				</div>
				
				<?php elseif (($adminpage == "eventvenues")): ?>
				<a href="?page=community-events.php&amp;section=general">General Settings</a> | <a href="?page=community-events.php&amp;section=eventtypes">Manage Event Types</a> | <a href="?page=community-events.php&amp;section=eventvenues"><strong>Manage Venues</strong></a> | <a href="?page=community-events.php&amp;section=events">Manage Events</a><br /><br />
				<div style='float:left;margin-right: 15px'>
				<form name="cevenueform" action="" method="post" id="ws-config">
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
					<input type="hidden" name="id" value="<?php if ($mode == "edit") echo $selectedvenue->ce_venue_id; ?>" />
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
							
							<p>Venues can only be deleted when they don't have any associated events.</p>
				</div>
				
				<?php elseif (($adminpage == "events")): ?>
				<a href="?page=community-events.php&amp;section=general">General Settings</a> | <a href="?page=community-events.php&amp;section=eventtypes">Manage Event Types</a> | <a href="?page=community-events.php&amp;section=eventvenues">Manage Venues</a> | <a href="?page=community-events.php&amp;section=events"><strong>Manage Events</strong></a><br /><br />
				
				<script type="text/javascript">
				 jQuery(document).ready(function() {
						jQuery("#datepicker").datepick({dateFormat: 'yyyy-mm-dd'});
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
						<td><input style="width:360px" type="text" name="event_description" <?php if ($mode == "edit") echo  stripslashes($selectedevent->event_description);?> /></td>
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
						<td><input type="text" id="datepicker" name="event_date" size="30" <?php if ($mode == "edit") echo "value='" . $selectedevent->event_date . "'";?>/></td>
					</tr>
					<tr>
					<td>Time</td>
					<td><input type="text" name="event_time" size="30" <?php if ($mode == "edit") echo "value='" . $selectedevent->event_time. "'";?>/></td>
					</tr>
					<tr>
					<td>Duration</td>
					<td><input type="text" name="event_duration" size="30" <?php if ($mode == "edit") echo "value='" . $selectedevent->event_duration. "'";?>/></td>
					</tr>
					</table>
					<?php if ($mode == "edit"): ?>
						<p style="border:0;" class="submit"><input type="submit" name="updateevent" value="Update &raquo;" /></p>
					<?php else: ?>
						<p style="border:0;" class="submit"><input type="submit" name="newevent" value="Insert New Event &raquo;" /></p>
					<?php endif; ?>
				</form>
				</div>
				<div>
				<?php 
					$currentday = date("z", current_time('timestamp')) + 1;
					if (date("L") == 1 && $currentday > 60)
						$currentday++;		
	
					$currentyear = date("Y");	

					$eventquery = "SELECT * from " . $wpdb->prefix . "ce_events where YEAR(event_date) = " . $currentyear . " and DAYOFYEAR(DATE(event_date)) >= " . $currentday . " ORDER by event_date LIMIT 0, 10";
					$events = $wpdb->get_results($eventquery, ARRAY_A);
					
							if ($events): ?>
							  <table class='widefat' style='clear:none;width:500px;background: #DFDFDF url(/wp-admin/images/gray-grad.png) repeat-x scroll left top;'>
							  <thead>
							  <tr>
  							  <th scope='col' style='width: 50px' id='id' class='manage-column column-id' >ID</th>
							  <th scope='col' id='name' class='manage-column column-name' style=''>Name</th>
							  <th scope='col' id='day' class='manage-column column-day' style='text-align: right'>Date</th>
							  <th scope='col' style='width: 50px;text-align: right' id='starttime' class='manage-column column-items' style=''>Time</th>
							  <th style='width: 30px'></th>
							  </tr>
							  </thead>
							  
							  <tbody id='the-list' class='list:link-cat'>

							  <?php foreach($events as $event): ?>
								<tr>
								<td class='name column-name' style='background: #FFF'><?php echo $event['event_id']; ?></td>
								<td style='background: #FFF'><a href='?page=community-events.php&amp;editevent=<?php echo $event['event_id']; ?>'><strong><?php echo $event['event_name']; ?></strong></a></td>
								<td style='background: #FFF;text-align:right'><?php echo $event['event_date']; ?></td>
								<td style='background: #FFF;text-align:right'></td>

								<td style='background:#FFF'><a href='?page=community-events.php&amp;deleteevent=<?php echo $event['event_id']; ?>' 
								<?php echo "onclick=\"if ( confirm('" . esc_js(sprintf( __("You are about to delete the event '%s'\n  'Cancel' to stop, 'OK' to delete."), $event['event_name'] )) . "') ) { return true;}return false;\""; ?>><img src='<?php echo $cepluginpath; ?>/icons/delete.png' /></a></td>
								</tr>
							  <?php endforeach; ?>				
							  
							  </tbody>
							  </table>
							<?php endif; ?>
				</div>
				<?php endif; ?>
			</div>
			<?php
		} // end config_page()

	} // end class CE_Admin
} //endif

function ce_7day_func($atts) {
	extract(shortcode_atts(array(
	), $atts));
	
	$options = get_option('CE_PP');
	
	return ce_7day($options['fullscheduleurl']);
}

	
function ce_7day($fullscheduleurl = '') {
	global $wpdb;
	
	$currentday = date("z", current_time('timestamp')) + 1;

	if (date("L") == 1 && $currentday > 60)
		$currentday++;		
	
	$currentyear = date("Y");	
	
	$eventquery = "SELECT * from " . $wpdb->prefix . "ce_events e LEFT JOIN " . $wpdb->prefix . "ce_venues v ON e.event_venue = v.ce_venue_id ";
	$eventquery .= "where YEAR(event_date) = " . $currentyear . " and DAYOFYEAR(DATE(event_date)) = " . $currentday;
	$eventquery .= " order by e.event_name";
	$events = $wpdb->get_results($eventquery, ARRAY_A);
	
	$output = "<SCRIPT LANGUAGE=\"JavaScript\">\n";

	$output .= "function showDayEvents ( _dayofyear, _year) {\n";
	$output .= "var map = {dayofyear : _dayofyear, year : _year}\n";
	$output .= "\tjQuery('.ce-7day-innertable').replaceWith('<div class=\"ce-7day-innertable\"><img src=\"" . WP_PLUGIN_URL . "/community-events/icons/Ajax-loader.gif\" alt=\"Loading data, please wait...\"></div>');jQuery.get('" . WP_PLUGIN_URL . "/community-events/get-events.php', map, function(data){jQuery('.ce-7day-innertable" . $settings. "').replaceWith(data);});\n";
	$output .= "}\n\n";
	
	$output .= "jQuery(document).ready(function() {\n";
    $output .= "\tjQuery('.ce-day-link').click(function() {\n";
	$output .= "\t\tvar elementid = jQuery(this).attr('id');\n";
	$output .= "\t\telementid = '#' + elementid + '_cell';\n";
	$output .= "\t\tjQuery('.ce-daybox').each(function()\n";
	$output .= "\t\t\t{ jQuery(this).removeClass('selected');	}\n";
	$output .= "\t\t);\n";
	$output .= "\t\tjQuery(elementid).addClass('selected');\n";
	$output .= "\t});\n";
	$output .= "});\n";

	$output .= "</SCRIPT>\n\n";
	
	$output .= "<div class='community-events-7day'><table class='ce-7day-toptable'><tr>\n";
	
	for ($i = 0; $i <= 6; $i++) {
		$daynumber = $currentday + $i;
		$output .= "\t<td class='ce-daybox " . ($i % 2 == 0 ? "even" : "odd");
		if ($i == 0) 
			$output .= " selected";
			
		$output .= "' id='day_" . $daynumber . "_" . $currentyear . "_cell'>\n";
		
		$output .= "\t\t<span class='ce-dayname'><a href='#' class='ce-day-link' id='day_" . $daynumber . "_" . $currentyear . "' onClick=\"showDayEvents('" . $daynumber. "', '" . $currentyear . "');return false;\">" . date("D", strtotime("+" . $i . " day", current_time('timestamp'))) . "</a></span><br />\n";
		
		$output .= "\t\t<span class='ce-date'><a href='#' class='ce-day-link' id='day_" . $daynumber . "_" . $currentyear . "' onClick=\"showDayEvents('" . $daynumber. "', '" . $currentyear . "');return false;\">" . date("j", strtotime("+" . $i . " day", current_time('timestamp'))) . "</a></span>\n";
		
		$output .= "\t</td>\n";
	}

	$output .= "</tr>\n\t<tr><td class='ce-inner-table-row' colspan='7'><table class='ce-7day-innertable' id='ce-7day-innertable'>\n";

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
	
	$output .= "\t</table></td></tr>\n";
	
	if ($fullscheduleurl != '')
		$output .= "<tr class='ce-full-schedule-link'><td colspan='7'><a href='" . $fullscheduleurl . "'>Full Schedule</a></td></tr>";
	
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


function ce_header() {
	echo '<link rel="stylesheet" type="text/css" media="screen" href="'. WP_PLUGIN_URL . '/community-events/stylesheet.css"/>';
}

function ce_admin_scripts() {
	echo '<link rel="stylesheet" type="text/css" media="screen" href="'. WP_PLUGIN_URL . '/community-events/css/jquery.datepick.css"/>';
}

function community_events_init() {
	wp_enqueue_script('datepicker', get_bloginfo('wpurl') . '/wp-content/plugins/community-events/js/jquery.datepick.min.js');
}  

// adds the menu item to the admin interface
add_action('admin_menu', array('CE_Admin','add_config_page'));

add_shortcode('community-events-7day', 'ce_7day_func');

add_shortcode('community-events-full', 'ce_full_func');

add_action('wp_head', 'ce_header');

wp_enqueue_script('jquery');

add_action('init', 'community_events_init');

add_filter('admin_head', 'ce_admin_scripts'); // the_posts gets triggered before wp_head

?>