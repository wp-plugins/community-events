<?php
/*Plugin Name: Community Events
Plugin URI: http://yannickcorner.nayanna.biz/wordpress-plugins/community-events
Description: A plugin used to create a page with a list of TV shows
Version: 0.4
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

define('COMMUNITY_EVENTS_ADMIN_PAGE_NAME', 'community-events');

if (is_file(trailingslashit(ABSPATH.PLUGINDIR).'community-events.php')) {
	define('CE_FILE', trailingslashit(ABSPATH.PLUGINDIR).'community-events.php');
}
else if (is_file(trailingslashit(ABSPATH.PLUGINDIR).'community-events/community-events.php')) {
	define('CE_FILE', trailingslashit(ABSPATH.PLUGINDIR).'community-events/community-events.php');
}

//class that reperesent the complete plugin
class community_events_plugin {

	//constructor of class, PHP4 compatible construction for backward compatibility
	function community_events_plugin() {

		$this->cepluginpath = WP_CONTENT_URL.'/plugins/'.plugin_basename(dirname(__FILE__)).'/';
		
		load_plugin_textdomain( 'community-events', $this->cepluginpath . '/languages', 'community-events/languages');

		$options = get_option('CE_PP');

		if ($options['schemaversion'] < 0.3)
			$this->ce_install();

		//add filter for WordPress 2.8 changed backend box system !
		add_filter('screen_layout_columns', array($this, 'on_screen_layout_columns'), 10, 2);
		//register callback for admin menu  setup
		add_action('admin_menu', array($this, 'on_admin_menu')); 
		//register the callback been used if options of page been submitted and needs to be processed
		add_action('admin_post_save_community_events_general', array($this, 'on_save_changes_general'));
		add_action('admin_post_save_community_events_event_types', array($this, 'on_save_changes_eventtypes'));
		add_action('admin_post_save_community_events_venues', array($this, 'on_save_changes_venues'));
		add_action('admin_post_save_community_events_events', array($this, 'on_save_changes_events'));
		
		wp_enqueue_script('jquery');
		wp_enqueue_script('tiptip', get_bloginfo('wpurl').'/wp-content/plugins/community-events/tiptip/jquery.tipTip.minified.js', "jQuery", "1.0rc3");
		wp_enqueue_style('tiptipstyle', get_bloginfo('wpurl').'/wp-content/plugins/community-events/tiptip/tipTip.css');
		wp_enqueue_script('ui.datepicker', get_bloginfo('wpurl') . '/wp-content/plugins/community-events/js/ui.datepicker.js');
		wp_enqueue_style('datePickerstyle', get_bloginfo('wpurl') . '/wp-content/plugins/community-events/css/ui-lightness/jquery-ui-1.8.4.custom.css');
		
		add_shortcode('community-events-7day', array($this, 'ce_7day_func'));
		add_shortcode('community-events-full', array($this, 'ce_full_func'));
		add_shortcode('community-events-addevent', array($this, 'ce_addevent_func'));
		
		add_action('wp_head', array($this, 'ce_header'));
		
		add_action('ce_daily_event', array($this, 'ce_daily_cleanup'));
		
		register_activation_hook(CE_FILE, array($this, 'ce_install'));
		register_deactivation_hook(CE_FILE, array($this, 'ce_uninstall'));
	}
	
	//for WordPress 2.8 we have to tell, that we support 2 columns !
	function on_screen_layout_columns($columns, $screen) {
		if ($screen == $this->pagehooktop) {
			$columns[$this->pagehooktop] = 2;
		}
		elseif ($screen == $this->pagehookeventtypes) {
			$columns[$this->pagehookeventtypes] = 2;
		}
		elseif ($screen == $this->pagehookvenues) {
			$columns[$this->pagehookvenues] = 2;
		}
		elseif ($screen == $this->pagehookevents) {
			$columns[$this->pagehookevents] = 2;
		}

		return $columns;
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
			$options['eventdatelabel'] = __('Event Start Date', 'community-events');
			$options['eventtimelabel'] = __('Event Time', 'community-events');
			$options['addeventbtnlabel'] = __('Add Event', 'community-events');
			$options['eventenddatelabel'] = __('Event End Date', 'community-events');
			$options['outlook'] = true;
			$options['emailnewevent'] = true;
			$options['moderateevents'] = true;
			$options['maxevents7dayview'] = 5;
			
			update_option('CE_PP',$options);
			
			$wpdb->ceevents = $wpdb->prefix.'ce_events';
			
			$result = $wpdb->query("
					CREATE TABLE IF NOT EXISTS `$wpdb->ceevents` (
					  `event_id` bigint(20) NOT NULL AUTO_INCREMENT,
					  `event_name` varchar(255) DEFAULT NULL,
					  `event_start_date` date DEFAULT NULL,
					  `event_start_hour` int(11) DEFAULT NULL,
					  `event_start_minute` int(2) unsigned zerofill DEFAULT NULL,
					  `event_start_ampm` varchar(2) DEFAULT NULL,
					  `event_end_date` date DEFAULT NULL,
					  `event_description` varchar(140) DEFAULT NULL,
					  `event_url` varchar(255) DEFAULT NULL,
					  `event_ticket_url` varchar(256) DEFAULT NULL,
					  `event_venue` int(11) DEFAULT NULL,
					  `event_category` int(11) DEFAULT NULL,
					  `event_published` varchar(1) DEFAULT NULL,
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
	
		}
		else
		{
			if ($options['schemaversion'] < 0.3)
			{
				$options['schemaversion'] = 0.3;
				update_option('CE_PP',$options);
				
				$wpdb->get_results("ALTER TABLE `" . $wpdb->prefix . "ce_events` ADD `event_start_hour` INT NULL AFTER `event_date`,  ADD `event_start_minute` INT( 2 ) UNSIGNED ZEROFILL NULL AFTER `event_start_hour`,  ADD `event_start_ampm` VARCHAR( 2 ) NULL AFTER `event_start_minute`,  ADD `event_end_date` DATE NULL AFTER `event_start_ampm`;");
				$wpdb->get_results("ALTER TABLE `" . $wpdb->prefix . "ce_events` DROP `event_duration`;");
				$wpdb->get_results("ALTER TABLE `" . $wpdb->prefix . "ce_events` CHANGE `event_date` `event_start_date` DATE NULL DEFAULT NULL");
				$wpdb->get_results("ALTER TABLE `" . $wpdb->prefix . "ce_events` ADD `event_published` VARCHAR( 1 ) NULL;");
			}
		}
		
		wp_clear_scheduled_hook('ce_daily_event');
		
		wp_schedule_event(current_time('timestamp'), 'daily', 'ce_daily_event');	
	}
	
	function ce_uninstall() {
		wp_clear_scheduled_hook('ce_daily_event');
	}
	
	function ce_daily_cleanup() {
		global $wpdb;
		
		$currentday = date("z", current_time('timestamp')) + 1;
		if (date("L") == 1 && $currentday > 60)
			$currentday++;		
		
		$currentyear = date("Y");	
		
		$cleanupquery = "DELETE FROM " . $wpdb->prefix . "ce_events e where (YEAR(event_start_date) <= " . $currentyear;
		$cleanupquery .= " and DAYOFYEAR(DATE(event_start_date)) < " . $currentday . ") ";
		
		$wpdb->get_results($cleanupquery);
	}
	
	function remove_querystring_var($url, $key) { 
		$url = preg_replace('/(.*)(?|&)' . $key . '=[^&]+?(&)(.*)/i', '$1$2$4', $url . '&'); 
		$url = substr($url, 0, -1); 
		return $url; 
	}

	//extend the admin menu
	function on_admin_menu() {
		//add our own option page, you can also add it to different sections or use your own one
		$this->pagehooktop = add_menu_page(__('Community Events General Options', 'community-events'), "Community Events", 'manage_options', COMMUNITY_EVENTS_ADMIN_PAGE_NAME, array($this, 'on_show_page'));
		$this->pagehookeventtypes = add_submenu_page( COMMUNITY_EVENTS_ADMIN_PAGE_NAME, __('Community Events - Event Types', 'community-events') , __('Event Types', 'community-events'), 'manage_options', 'community-events-event-types', array($this,'on_show_page'));
		$this->pagehookvenues = add_submenu_page( COMMUNITY_EVENTS_ADMIN_PAGE_NAME, __('Community Events - Venues', 'community-events') , __('Venues', 'community-events'), 'manage_options', 'community-events-venues', array($this,'on_show_page'));
		$this->pagehookevents = add_submenu_page( COMMUNITY_EVENTS_ADMIN_PAGE_NAME, __('Community Events - Events', 'community-events') , __('Events', 'community-events'), 'manage_options', 'community-events-events', array($this,'on_show_page')); 
		
		//register  callback gets call prior your own page gets rendered
		add_action('load-'.$this->pagehooktop, array(&$this, 'on_load_page'));
		add_action('load-'.$this->pagehookeventtypes, array(&$this, 'on_load_page'));
		add_action('load-'.$this->pagehookvenues, array(&$this, 'on_load_page'));
		add_action('load-'.$this->pagehookevents, array(&$this, 'on_load_page'));		
	}
	
	//will be executed if wordpress core detects this page has to be rendered
	function on_load_page() {
		//ensure, that the needed javascripts been loaded to allow drag/drop, expand/collapse and hide/show of boxes
		wp_enqueue_script('common');
		wp_enqueue_script('wp-lists');
		wp_enqueue_script('postbox');		

		//add several metaboxes now, all metaboxes registered during load page can be switched off/on at "Screen Options" automatically, nothing special to do therefore
		add_meta_box('communityevents_general_usage_meta_box', __('Usage Instructions', 'community-events'), array($this, 'general_usage_meta_box'), $this->pagehooktop, 'normal', 'high');
		add_meta_box('communityevents_general_config_meta_box', __('General Configuration', 'community-events'), array($this, 'general_config_meta_box'), $this->pagehooktop, 'normal', 'high');		
		add_meta_box('communityevents_general_user_sub_meta_box', __('Event User Submission', 'community-events'), array($this, 'general_user_sub_meta_box'), $this->pagehooktop, 'normal', 'high');		
		add_meta_box('communityevents_general_save_meta_box', __('Save', 'community-events'), array($this, 'general_save_meta_box'), $this->pagehooktop, 'side', 'high');
		add_meta_box('communityevents_event_types_meta_box', __('Event Types Editor', 'community-events'), array($this, 'event_types_meta_box'), $this->pagehookeventtypes, 'normal', 'high');
		add_meta_box('communityevents_event_types_save_meta_box', __('Save', 'community-events'), array($this, 'event_types_save_meta_box'), $this->pagehookeventtypes, 'side', 'high');
		add_meta_box('communityevents_venues_meta_box', __('Venues Editor', 'community-events'), array($this, 'event_venues_meta_box'), $this->pagehookvenues, 'normal', 'high');
		add_meta_box('communityevents_venues_save_meta_box', __('Save', 'community-events'), array($this, 'event_venues_save_meta_box'), $this->pagehookvenues, 'side', 'high');				
		add_meta_box('communityevents_events_meta_box', __('Events Editor', 'community-events'), array($this, 'events_meta_box'), $this->pagehookevents, 'normal', 'high');
 		add_meta_box('communityevents_events_save_meta_box', __('Save', 'community-events'), array($this, 'events_save_meta_box'), $this->pagehookevents, 'side', 'high');				
	}

	//executed to show the plugins complete admin page
	function on_show_page() {
		//we need the global screen column value to beable to have a sidebar in WordPress 2.8
		global $screen_layout_columns;
		global $wpdb;

		if ($_GET['page'] == 'community-events')
		{
			$pagetitle = __('Community Events General Settings', 'community-events');
			$formvalue = 'save_community_events_general';

			if ($_GET['message'] == '1')
				echo '<div id="message" class="updated fade"><p><strong>' . __('Community Events Updated', 'community-events') . '</strong></div>';
		}
		elseif ($_GET['page'] == 'community-events-event-types')
		{
			$pagetitle = __('Community Events - Event Types', 'community-events');
			$formvalue = 'save_community_events_event_types';
			
			if ( isset($_GET['editcat']))
			{					
				$mode = "edit";
				$selectedcat = $wpdb->get_row("select * from " . $wpdb->prefix . "ce_category where event_cat_id = " . $_GET['editcat']);
			}
			elseif (isset($_GET['deletecat']))
			{
				$catexist = $wpdb->get_row("SELECT * from " . $wpdb->prefix . "ce_category WHERE event_cat_id = " . $_GET['deletecat']);
				
				if ($catexist)
				{
					$wpdb->query("DELETE from " . $wpdb->prefix . "ce_category WHERE id = " . $_GET['deletecat']);
					echo '<div id="message" class="updated fade"><p><strong>' . __('Category Deleted', 'community-events') . '</strong></div>';					
				}
			}
			
			if ($_GET['message'] == '1')
				echo '<div id="message" class="updated fade"><p><strong>' . __('Inserted New Category', 'community-events') . '</strong></div>';
			elseif ($_GET['message'] == '2')
				echo '<div id="message" class="updated fade"><p><strong>' . __('Category Updated', 'community-events') . '</strong></div>';
		}
		elseif ($_GET['page'] == 'community-events-venues')
		{
			$pagetitle = __('Community Events - Venues', 'community-events');
			$formvalue = 'save_community_events_venues';
			
			if ( isset($_GET['editvenue']))
			{					
				$mode = "edit";								
				$selectedvenue = $wpdb->get_row("select * from " . $wpdb->prefix . "ce_venues where ce_venue_id = " . $_GET['editvenue']);
			}
			elseif (isset($_GET['deletevenue']))
			{
				$venueexist = $wpdb->get_row("SELECT * from " . $wpdb->prefix . "ce_venues WHERE ce_venue_id = " . $_GET['deletevenue']);
				
				if ($venueexist)
				{
					$wpdb->query("DELETE from " . $wpdb->prefix . "ce_venues WHERE ce_venue_id = " . $_GET['deletevenue']);
					echo '<div id="message" class="updated fade"><p><strong>' . __('Venue Deleted', 'community-events') . '</strong></div>';
				}
			}
			
			if ($_GET['message'] == '1')
				echo '<div id="message" class="updated fade"><p><strong>' . __('Inserted New Venue', 'community-events') . '</strong></div>';
			elseif ($_GET['message'] == '2')
				echo '<div id="message" class="updated fade"><p><strong>' . __('Venue Updated', 'community-events') . '</strong></div>';
		}
		elseif ($_GET['page'] == 'community-events-events')
		{
			$pagetitle = __('Community Events - Events', 'community-events');
			$formvalue = 'save_community_events_events';

			if ( isset($_GET['editevent']))
			{					
				$mode = "edit";								
				$selectedevent = $wpdb->get_row("select * from " . $wpdb->prefix . "ce_events where event_id = " . $_GET['editevent']);
			}
			elseif (isset($_GET['deleteevent']))
			{
				$eventexist = $wpdb->get_row("SELECT * from " . $wpdb->prefix . "ce_events WHERE event_id = " . $_GET['deleteevent']);
				
				if ($eventexist)
				{
					$wpdb->query("DELETE from " . $wpdb->prefix . "ce_events WHERE event_id = " . $_GET['deleteevent']);
					echo '<div id="message" class="updated fade"><p><strong>' . __('Event Deleted', 'community-events') . '</strong></div>';
				}				
			}
			
			if ($_GET['message'] == '1')
				echo '<div id="message" class="updated fade"><p><strong>' . __('Inserted New Event', 'community-events') . '</strong></div>';
			elseif ($_GET['message'] == '2')
				echo '<div id="message" class="updated fade"><p><strong>' . __('Event Updated', 'community-events') . '</strong></div>';
		}
		
		$options = get_option('CE_PP');

		//define some data can be given to each metabox during rendering
		$data['options'] = $options;
		$data['mode'] = $mode;
		$data['selectedcat'] = $selectedcat;
		$data['selectedvenue'] = $selectedvenue;
		$data['selectedevent'] = $selectedevent;
		?>
		<div id="community-events-general" class="wrap">
		<?php screen_icon('options-general'); ?>
		<h2><?php echo $pagetitle; ?><span style='padding-left: 50px'><a href="http://yannickcorner.nayanna.biz/wordpress-plugins/community-events/" target="linklibrary"><img src="<?php echo $this->cepluginpath; ?>/icons/btn_donate_LG.gif" /></a></span></h2>
		<form action="admin-post.php" method="post">
			<?php wp_nonce_field('community-events-general'); ?>
			<?php wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false ); ?>
			<?php wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false ); ?>
			<input type="hidden" name="action" value="<?php echo $formvalue; ?>" />
		
			<div id="poststuff" class="metabox-holder<?php echo 2 == $screen_layout_columns ? ' has-right-sidebar' : ''; ?>">
				<div id="side-info-column" class="inner-sidebar">
					<?php 
						if ($_GET['page'] == 'community-events')
							do_meta_boxes($this->pagehooktop, 'side', $data); 
						elseif ($_GET['page'] == 'community-events-event-types')
							do_meta_boxes($this->pagehookeventtypes, 'side', $data); 
						elseif ($_GET['page'] == 'community-events-venues')
							do_meta_boxes($this->pagehookvenues, 'side', $data); 
						elseif ($_GET['page'] == 'community-events-events')
							do_meta_boxes($this->pagehookevents, 'side', $data); 
					?>
				</div>
				<div id="post-body" class="has-sidebar">
					<div id="post-body-content" class="has-sidebar-content">
						<?php 
							if ($_GET['page'] == 'community-events')
								do_meta_boxes($this->pagehooktop, 'normal', $data); 
							elseif ($_GET['page'] == 'community-events-event-types')
								do_meta_boxes($this->pagehookeventtypes, 'normal', $data); 
							elseif ($_GET['page'] == 'community-events-venues')
								do_meta_boxes($this->pagehookvenues, 'normal', $data); 
							elseif ($_GET['page'] == 'community-events-events')
								do_meta_boxes($this->pagehookevents, 'normal', $data); 
						?>
					</div>
				</div>
				<br class="clear"/>
								
			</div>	
		</form>
		</div>
	<script type="text/javascript">
		//<![CDATA[
		jQuery(document).ready( function($) {
			// close postboxes that should be closed
			$('.if-js-closed').removeClass('if-js-closed').addClass('closed');
			// postboxes setup
			postboxes.add_postbox_toggles('<?php echo $this->pagehook; ?>');
		});
		//]]>
	</script>
		
		<?php
	}

	//executed if the post arrives initiated by pressing the submit button of form
	function on_save_changes_general() {
		//user permission check
		if ( !current_user_can('manage_options') )
			wp_die( __('Cheatin&#8217; uh?') );			
		//cross check the given referer
		check_admin_referer('community-events-general');
		
		foreach (array('fullscheduleurl', 'addeventurl', 'columns', 'addneweventmsg', 'eventnamelabel', 'eventcatlabel', 'eventvenuelabel', 'eventdesclabel',
						'eventaddrlabel', 'eventticketaddrlabel', 'eventdatelabel', 'eventtimelabel', 'addeventbtnlabel', 'eventenddatelabel',
						'maxevents7dayview') as $option_name) {
				if (isset($_POST[$option_name])) {
					$options[$option_name] = $_POST[$option_name];
				}
			}
			
		foreach (array('adjusttooltipposition', 'addeventreqlogin', 'outlook', 'emailnewevent', 'moderateevents') as $option_name) {
			if (isset($_POST[$option_name])) {
				$options[$option_name] = true;
			} else {
				$options[$option_name] = false;
			}
		}

		update_option('CE_PP', $options);
		
		//lets redirect the post request into get request (you may add additional params at the url, if you need to show save results
		wp_redirect($this->remove_querystring_var($_POST['_wp_http_referer'], 'message') . "&message=1");
	}
	
		//executed if the post arrives initiated by pressing the submit button of form
	function on_save_changes_eventtypes() {
		//user permission check
		if ( !current_user_can('manage_options') )
			wp_die( __('Cheatin&#8217; uh?') );			
		//cross check the given referer
		check_admin_referer('community-events-general');
		
		global $wpdb;
		$message = '';
		
		if ( isset($_POST['newcat']) || isset($_POST['updatecat'])) {
			if (isset($_POST['name']))
				$newcat = array("event_cat_name" => $_POST['name']);
			else
				$newcat = "";
				
			if (isset($_POST['id']))
				$id = array("event_cat_id" => $_POST['id']);
				
			if (isset($_POST['newcat']))
			{
				$wpdb->insert( $wpdb->prefix.'ce_category', $newcat);
				$message = '1';
			}
			elseif (isset($_POST['updatecat']))
			{
				$wpdb->update( $wpdb->prefix.'ce_category', $newcat, $id);
				$message = '2';
			}
		}
		
		//lets redirect the post request into get request (you may add additional params at the url, if you need to show save results
		$cleanredirecturl = $this->remove_querystring_var($_POST['_wp_http_referer'], 'message');
		$cleanredirecturl = $this->remove_querystring_var($cleanredirecturl, 'editcat');
		
		if ($message != '')
			$cleanredirecturl .= "&message=" . $message;

		wp_redirect($cleanredirecturl);		
	}
	
		//executed if the post arrives initiated by pressing the submit button of form
	function on_save_changes_venues() {
		//user permission check
		if ( !current_user_can('manage_options') )
			wp_die( __('Cheatin&#8217; uh?') );			
		//cross check the given referer
		check_admin_referer('community-events-general');
		
		global $wpdb;
		$message = '';
		
		if ( isset($_POST['newvenue']) || isset($_POST['updatevenue'])) {
			
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
				$message = '1';
			}
			elseif (isset($_POST['updatevenue']))
			{
				$wpdb->update( $wpdb->prefix.'ce_venues', $newvenue, $id);
				$message = '2';
			}
		}

		//lets redirect the post request into get request (you may add additional params at the url, if you need to show save results
		$cleanredirecturl = $this->remove_querystring_var($_POST['_wp_http_referer'], 'message');
		$cleanredirecturl = $this->remove_querystring_var($cleanredirecturl, 'editvenue');
		
		if ($message != '')
			$cleanredirecturl .= "&message=" . $message;

		wp_redirect($cleanredirecturl);			
	}
	
		//executed if the post arrives initiated by pressing the submit button of form
	function on_save_changes_events() {
		//user permission check
		if ( !current_user_can('manage_options') )
			wp_die( __('Cheatin&#8217; uh?') );			
		//cross check the given referer
		check_admin_referer('community-events-general');
		
		$message = '';
		global $wpdb;
		
		if (isset($_POST['newevent']) || isset($_POST['updateevent']))
		{
			if (isset($_POST['event_name']) && isset($_POST['event_start_hour']) && isset($_POST['event_start_minute']) && isset($_POST['event_start_ampm']) && isset($_POST['event_start_date']) && $_POST['event_name'] != '')
			{
				$newevent = array("event_name" => $_POST['event_name'],
								 "event_description" => $_POST['event_description'],
								 "event_start_date" => $_POST['event_start_date'],
								 "event_start_hour" => $_POST['event_start_hour'],
								 "event_start_minute" => $_POST['event_start_minute'],
								 "event_start_ampm" => $_POST['event_start_ampm'],
								 "event_url" => $_POST['event_url'],
								 "event_ticket_url" => $_POST['event_ticket_url'],
								 "event_category" => $_POST['event_category'],
								 "event_venue" => $_POST['event_venue'],
								 "event_published" => 'Y');
								 
				if ($_POST['event_end_date'] != '')
					$newevent['event_end_date'] = $_POST['event_end_date'];

				if (isset($_POST['event_id']))
					$id = array("event_id" => $_POST['event_id']);
					
				if (isset($_POST['newevent']))
				{
					$wpdb->insert( $wpdb->prefix.'ce_events', $newevent);
					$message = '1';
				}
				elseif (isset($_POST['updateevent']))
				{
					$wpdb->update( $wpdb->prefix.'ce_events', $newevent, $id);
					$message = '2';
				}									 
			}				
		}
		
		//lets redirect the post request into get request (you may add additional params at the url, if you need to show save results
		$cleanredirecturl = $this->remove_querystring_var($_POST['_wp_http_referer'], 'message');
		$cleanredirecturl = $this->remove_querystring_var($cleanredirecturl, 'editevent');
		
		if ($message != '')
			$cleanredirecturl .= "&message=" . $message;

		wp_redirect($cleanredirecturl);			
	}
	
	function ce_highlight_phrase($str, $phrase, $tag_open = '<strong>', $tag_close = '</strong>')
	{
		if ($str == '')
		{
			return '';
		}
	
		if ($phrase != '')
		{
			return preg_replace('/('.preg_quote($phrase, '/').'(?![^<]*>))/i', $tag_open."\\1".$tag_close, $str);
		}
	
		return $str;
	}
	
	function print_event_table($currentyear, $currentday, $page, $moderate = false) {
		global $wpdb;
	
		$countquery = "SELECT COUNT(*) from " . $wpdb->prefix . "ce_events where YEAR(event_start_date) = " . $currentyear . " and ( DAYOFYEAR(DATE(event_start_date)) >= " . $currentday . " OR DAYOFYEAR(DATE(event_end_date)) >= " . $currentday . ") ";
		
		if ($moderate == true)
			$countquery .= " AND event_published = 'N' ";
			
		$count = $wpdb->get_var($countquery);	
		
		$start = ($page - 1) * 10;
		$eventquery = "SELECT * from " . $wpdb->prefix . "ce_events where YEAR(event_start_date) = " . $currentyear . " and ( DAYOFYEAR(DATE(event_start_date)) >= " . $currentday . " OR DAYOFYEAR(DATE(event_end_date)) >= " . $currentday . ") ";
		
		if ($moderate == true)
			$eventquery .= " AND event_published = 'N' ";
		
		$eventquery .= " ORDER by event_start_date, event_name LIMIT " . $start . ", 10";
		
		$events = $wpdb->get_results($eventquery, ARRAY_A);
		
		$output .= "<div id='ce-event-list'>\n";
				
		$output .= "<table class='widefat' style='clear:none;width:100%;background: #DFDFDF url(/wp-admin/images/gray-grad.png) repeat-x scroll left top;'>\n";
		$output .= "\t<thead>\n";
		$output .= "\t\t<tr>\n";
		$output .= "\t\t\t<th scope='col' id='id' class='manage-column column-id' >ID</th>\n";
		
		if ($moderate == true)
			$output .= "\t\t\t<th scope='col' id='approveevents' class='manage-column column-id' ></th>\n";
			
		$output .= "\t\t\t<th scope='col' id='name' class='manage-column column-name' style=''>Name</th>\n";
		$output .= "\t\t\t<th scope='col' id='day' class='manage-column column-day' style='text-align: right'>Date(s)</th>\n";
		$output .= "\t\t\t<th scope='col' style='text-align: right' id='starttime' class='manage-column column-items' style=''>Time</th>\n";
		$output .= "\t\t\t<th scope='col' style='text-align: right' id='published' class='manage-column column-items' style=''>Published</th>\n";
		$output .= "\t\t\t<th></th>\n";
		$output .= "\t\t</tr>\n";
		$output .= "\t</thead>\n";
		$output .= "\t<tbody class='list:link-cat'>\n";
					
		if ($events)
		{
			foreach($events as $event) {
				$output .= "\t\t<tr>\n";
				$output .= "\t\t\t<td class='name column-name' style='background: #FFF'>" . $event['event_id'] . "</td>\n";
				
				if ($moderate == true)
					$output .= "\t\t\t<td style='background: #FFF'><input type='checkbox' name='events[]' value='" . $event['event_id'] . "' /></td>\n";
					
				$output .= "\t\t\t<td style='background: #FFF'><a href='admin.php?page=community-events-events&amp;editevent=" . $event['event_id'] . "&pagecount=" . $page . "'><strong>" . $event['event_name'] . "</strong></a></td>\n";
				$output .= "\t\t\t<td style='background: #FFF;text-align:right'>" . $event['event_start_date'] . ($event['event_end_date'] == NULL ? '' : ' - ' . $event['event_end_date']) . "</td>\n";
				$output .= "\t\t\t<td style='background: #FFF;text-align:right'>" . $event['event_start_hour'] . ":" . $event['event_start_minute'] . " " . $event['event_start_ampm'] . "</td>\n";
				$output .= "\t\t\t<td style='background: #FFF;text-align:right'>" . $event['event_published'] . "</td>\n";			
				$output .= "\t\t\t<td style='background:#FFF'><a href='admin.php?page=community-events-events&amp;deleteevent=" . $event['event_id'] . "&pagecount=" . $page . "'\n";
				$output .= "\t\t\tonclick=\"if ( confirm('" . esc_js(sprintf( __("You are about to delete the event '%s'\n  'Cancel' to stop, 'OK' to delete."), $event['event_name'] )) . "') ) { return true;}return false;\"><img src='" . $this->cepluginpath . "/icons/delete.png' /></a></td>\n";
				$output .= "\t\t\t</tr>\n";
			}
				}
		else
		{
			$output .= "<tr><td  style='background: #FFF' colspan='" . ($moderate == true ? '7' : '6') . "'>No events found" . ($moderate == true ? " to moderate" : "") . ".</td></tr>\n";
		}
		
		$output .= "\t</tbody>\n";
		$output .= "</table>\n";
	
		if ($page > 1)
			$output .= "<div class='navleft' style='float: left; padding-top: 4px'><button id='previouspage' onClick='navigateLeft()'><img alt='Previous page of events' src='" . $this->cepluginpath . "/icons/resultset_previous.png' /></button></div>\n";
			
		if ((($page) * 10) < $count)
			$output .= "<div class='navright' style='float: right; padding-top: 4px'><button id='nextpage' onClick='navigateRight()'><img alt='Next page of events' src='" . $this->cepluginpath . "/icons/resultset_next.png' /></button></div>\n";
		
		$output .= "</div>\n";
			
		return $output;
	}
	
	function general_usage_meta_box($data) { ?>
	
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

    <?php }
	
	function general_config_meta_box($data) { 
		$options = $data['options'];
	?>

		<table>
			<tr>
				<td><?php _e('Full Schedule URL', 'community-events'); ?></td>
				<td colspan='4'><input style="width:100%" type="text" name="fullscheduleurl" <?php echo "value='" . $options['fullscheduleurl'] . "'";?>/></td>
			</tr>
			<tr>
				<td><?php _e('Event submission form URL', 'community-events'); ?></td>
				<td colspan='4'><input style="width:100%" type="text" name="addeventurl" <?php echo "value='" . $options['addeventurl'] . "'";?>/></td>
			</tr>
			<tr>
				<td><?php _e('Show outlook view in 7-day view', 'community-events'); ?></td>
				<td><input type="checkbox" id="outlook" name="outlook" <?php if ($options['outlook']) echo ' checked="checked" '; ?>/></td>
				<td style='width: 100px'></td>
				<td><?php _e('Max number of events per day in 7-day view', 'community-events'); ?></td>
				<td><input style="width:50px" type="text" name="maxevents7dayview" <?php if ($options['maxevents7dayview'] != '') echo "value='" . $options['maxevents7dayview'] . "'"; else echo "value='5'"; ?>/></td>
			</tr>
		</table>

	<?php }
	
	function general_user_sub_meta_box($data) {
		$options = $data['options'];
		?>

						<table>
						<tr>
							<td style='width:200px'><?php _e('Require login to display form', 'community-events'); ?></td>
							<td style='width:75px;padding-right:20px'><input type="checkbox" id="addeventreqlogin" name="addeventreqlogin" <?php if ($options['addeventreqlogin']) echo ' checked="checked" '; ?>/></td>
							<td style='width: 20px'></td>
							<td style='width:200px'><?php _e('Send e-mail when new event is submitted', 'community-events'); ?></td>
							<td style='width:75px;padding-right:20px'><input type="checkbox" id="emailnewevent" name="emailnewevent" <?php if ($options['emailnewevent']) echo ' checked="checked" '; ?>/></td>							
						</tr>
						<tr>
							<td style='width:200px'><?php _e('Wait for validation before displaying user events', 'community-events'); ?></td>
							<td style='width:75px;padding-right:20px'><input type="checkbox" id="moderateevents" name="moderateevents" <?php if ($options['moderateevents'] == true || $options['moderateevents'] == '') echo ' checked="checked" '; ?>/></td>							
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
							<td style='width:200px'><?php _e('Event Start Date Label', 'community-events'); ?></td>
							<?php if ($options['eventdatelabel'] == "") $options['eventdatelabel'] = __('Event Start Date', 'community-events'); ?>
							<td><input type="text" id="eventdatelabel" name="eventdatelabel" size="30" value="<?php echo $options['eventdatelabel']; ?>"/></td>
						</tr>
						<tr>
							<td style='width:200px'><?php _e('Event End Date Label', 'community-events'); ?></td>
							<?php if ($options['eventenddatelabel'] == "") $options['eventenddatelabel'] = __('Event End Date', 'community-events'); ?>
							<td><input type="text" id="eventenddatelabel" name="eventenddatelabel" size="30" value="<?php echo $options['eventenddatelabel']; ?>"/></td>
							<td style='width:200px'></td>
							<td style='width:200px'><?php _e('Event Time Label', 'community-events'); ?></td>
							<?php if ($options['eventtimelabel'] == "") $options['eventtimelabel'] = __('Event Time', 'community-events'); ?>
							<td><input type="text" id="eventtimelabel" name="eventtimelabel" size="30" value="<?php echo $options['eventtimelabel']; ?>"/></td>
						</tr>
						<tr>
							<td style='width:200px'><?php _e('Add Event Button Label', 'community-events'); ?></td>
							<?php if ($options['addeventbtnlabel'] == "") $options['addeventbtnlabel'] = __('Add Event', 'community-events'); ?>
							<td><input type="text" id="addeventbtnlabel" name="addeventbtnlabel" size="30" value="<?php echo $options['addeventbtnlabel']; ?>"/></td>
						</tr>
					</table>

	<?php }
	
	function general_save_meta_box($data) {
		$options = $data['options'];
		?>
		<div class="submitbox">
			<input type="submit" name="submit" class="button-primary" value="<?php _e('Save Settings','community-events'); ?>" />
		</div>
	
	<?php }
	
	function event_types_meta_box($data) {
		$options = $data['options'];
		$mode = $data['mode'];
		$selectedcat = $data['selectedcat'];
		global $wpdb;
		?>	
			<table style='width: 100%'>
				<tr>
					<td style='vertical-align: top; width: 45%'>
						<?php if ($mode == "edit"): ?>
						<strong><?php _e('Editing Category #', 'community-events'); ?><?php echo $selectedcat->event_cat_id; ?></strong><br /><br />
						<?php endif; ?>
						Category Name<br /><br /><input style='width: 95%' type="text" name="name" <?php if ($mode == "edit") echo "value='" . $selectedcat->event_cat_name . "'";?>/>
						<input type="hidden" name="id" value="<?php if ($mode == "edit") echo $selectedcat->event_cat_id; ?>" />
					</td>
					<td style='width: 55%; vertical-align: top'>
						<?php $cats = $wpdb->get_results("SELECT count( e.event_id ) AS nbitems, c.event_cat_id, c.event_cat_name FROM " . $wpdb->prefix . "ce_category c LEFT JOIN " . $wpdb->prefix . "ce_events e ON e.event_category = c.event_cat_id GROUP BY c.event_cat_name");

						if ($cats): ?>
							<table class='widefat' style='clear:none;background: #DFDFDF url(/wp-admin/images/gray-grad.png) repeat-x scroll left top;'>
							<thead>
							<tr>
							<th scope='col' id='id' class='manage-column column-id' >ID</th>
							<th scope='col' id='name' class='manage-column column-name' style=''>Name</th>
							<th scope='col' style='text-align: right' id='items' class='manage-column column-items' style=''>Items</th>
							<th ></th>
							</tr>
							</thead>

							<tbody id='the-list' class='list:link-cat'>

							<?php foreach($cats as $cat): ?>
							<tr>
							<td class='name column-name' style='background: #FFF'><?php echo $cat->event_cat_id; ?></td>
							<td style='background: #FFF'><a href='admin.php?page=community-events-event-types&amp;editcat=<?php echo $cat->event_cat_id; ?>'><strong><?php echo $cat->event_cat_name; ?></strong></a></td>
							<td style='background: #FFF;text-align:right'><?php echo $cat->nbitems; ?></td>
							<?php if ($cat->nbitems == 0): ?>
							<td style='background:#FFF'><a href='admin.php?page=community-events-event-types&amp;deletecat=<?php echo $cat->event_cat_id; ?>' 
							<?php echo "onclick=\"if ( confirm('" . esc_js(sprintf( __("You are about to delete this category '%s'\n  'Cancel' to stop, 'OK' to delete."), $cat->event_cat_name )) . "') ) { return true;}return false;\"" ?>><img src='<?php echo $this->cepluginpath; ?>/icons/delete.png' /></a></td>
							<?php else: ?>
							<td style='background: #FFF'></td>
							<?php endif; ?>
							</tr>
							<?php endforeach; ?>				

							</tbody>
							</table>
						<?php endif; ?>

						<p>Categories can only be deleted when they do not have any associated events.</p>
					</td>
				</tr>
			</table>

		<?php }
		
	function event_types_save_meta_box($data) {
		$mode = $data['mode'];
		?>
			<div class="submitbox">

			<?php if ($mode == "edit"): ?>
				<input type="submit" name="updatecat" class="button-primary" value="<?php _e('Update &raquo;','community-events'); ?>" />
			<?php else: ?>
				<input type="submit" name="newcat" class="button-primary" value="<?php _e('Insert New Category &raquo;','community-events'); ?>" />
			<?php endif; ?>

			</div>
	<?php }	
			
	function event_venues_meta_box($data) {
		$mode = $data['mode'];
		$selectedvenue = $data['selectedvenue'];
		global $wpdb;			
		?>
			<table style='width: 100%'>
			<tr>
				<td style='width: 45%; vertical-align: top;'>
					<?php if ($mode == "edit"): ?>
					<strong>Editing Venue #<?php echo $selectedvenue->ce_venue_id; ?></strong><br />
					<?php endif; ?>
					<table style='width: 100%'>
						<tr>
							<td>Venue Name</td>
							<td><input style="width:95%" type="text" name="ce_venue_name" <?php if ($mode == "edit") echo "value='" . $selectedvenue->ce_venue_name . "'";?>/></td>
						</tr>
						<tr>
							<td>Venue Address</td>
							<td><input style="width:95%" type="text" name="ce_venue_address" <?php if ($mode == "edit") echo "value='" . $selectedvenue->ce_venue_address . "'";?>/></td>
						</tr>	
						<tr>
							<td>Venue City</td>
							<td><input style="width:95%" type="text" name="ce_venue_city" <?php if ($mode == "edit") echo "value='" . $selectedvenue->ce_venue_city . "'";?>/></td>
						</tr>
						<tr>
							<td>Venue Zip Code</td>
							<td><input style="width:95%" type="text" name="ce_venue_zipcode" <?php if ($mode == "edit") echo "value='" . $selectedvenue->ce_venue_zipcode . "'";?>/></td>
						</tr>
						<tr>
							<td>Venue Phone</td>
							<td><input style="width:95%" type="text" name="ce_venue_phone" <?php if ($mode == "edit") echo "value='" . $selectedvenue->ce_venue_phone . "'";?>/></td>
						</tr>				
						<tr>
							<td>Venue E-mail</td>
							<td><input style="width:95%" type="text" name="ce_venue_email" <?php if ($mode == "edit") echo "value='" . $selectedvenue->ce_venue_email . "'";?>/></td>
						</tr>	
						<tr>
							<td>Venue URL</td>
							<td><input style="width:95%" type="text" name="ce_venue_url" <?php if ($mode == "edit") echo "value='" . $selectedvenue->ce_venue_url . "'";?>/></td>
						</tr>	
					</table>
					<input type="hidden" name="ce_venue_id" value="<?php if ($mode == "edit") echo $selectedvenue->ce_venue_id; ?>" />
				</td>
				<td style='width=55%; vertical-align: top;'>
					<?php $venues = $wpdb->get_results("SELECT count( e.event_id ) AS nbitems, v.ce_venue_id, v.ce_venue_name FROM " . $wpdb->prefix . "ce_venues v LEFT JOIN " . $wpdb->prefix . "ce_events e ON e.event_venue = v.ce_venue_id GROUP BY v.ce_venue_name");

					if ($venues): ?>
						<table class='widefat' style='clear:none;width:100%;background: #DFDFDF url(/wp-admin/images/gray-grad.png) repeat-x scroll left top;'>
						<thead>
						<tr>
						<th scope='col' style='width: 50px' id='id' class='manage-column column-id' >ID</th>
						<th scope='col' id='name' class='manage-column column-name' style=''>Name</th>
						<th scope='col' style='width: 40px;text-align: right' id='items' class='manage-column column-items' style=''>Items</th>
						<th style='width: 30px'></th>
						</tr>
						</thead>

						<tbody id='the-list' class='list:link-cat'>

					<?php foreach($venues as $venue): ?>
						<tr>
						<td class='name column-name' style='background: #FFF'><?php echo $venue->ce_venue_id; ?></td>
						<td style='background: #FFF'><a href='admin.php?page=community-events-venues&amp;editvenue=<?php echo $venue->ce_venue_id; ?>'><strong><?php echo $venue->ce_venue_name; ?></strong></a></td>
						<td style='background: #FFF;text-align:right'><?php echo $venue->nbitems; ?></td>
						<?php if ($venue->nbitems == 0): ?>
						<td style='background:#FFF'><a href='admin.php?page=community-events-venues&amp;deletevenue=<?php echo $venue->ce_venue_id; ?>' 
						<?php echo "onclick=\"if ( confirm('" . esc_js(sprintf( __("You are about to delete this venue '%s'\n  'Cancel' to stop, 'OK' to delete."), $venue->ce_venue_name )) . "') ) { return true;}return false;\"" ?>><img src='<?php echo $this->cepluginpath; ?>/icons/delete.png' /></a></td>
						<?php else: ?>
						<td style='background: #FFF'></td>
						<?php endif; ?>
						</tr>
					<?php endforeach; ?>

					</tbody>
					</table>

					<?php endif; ?>

					<p>Venues can only be deleted when they do not have any associated events.</p>

				</td>
			</tr>
			</table>

	<?php }
			
	function event_venues_save_meta_box($data) {
		$mode = $data['mode'];
		?>

		<div class="submitbox">

		<?php if ($mode == "edit"): ?>
			<input type="submit" name="updatevenue" class="button-primary" value="<?php _e('Update &raquo;','community-events'); ?>" />
		<?php else: ?>
			<input type="submit" name="newvenue" class="button-primary" value="<?php _e('Insert New Venue &raquo;','community-events'); ?>" />
		<?php endif; ?>

		</div>

	<?php }
			
	function events_meta_box($data) {
		$mode = $data['mode'];
		$selectedevent = $data['selectedevent'];

		$currentday = date("z", current_time('timestamp')) + 1;
		if (date("L") == 1 && $currentday > 60)
			$currentday++;		

		$currentyear = date("Y");
		global $wpdb;
		?>
			<script type="text/javascript">
			 jQuery(document).ready(function() {
					jQuery("#datepickerstart").datepicker({minDate: '+0', dateFormat: 'yy-mm-dd', showOn: 'both', buttonImage: '<?php echo $this->cepluginpath; ?>/icons/calendar.png' });
					jQuery("#datepickerend").datepicker({minDate: '+0', dateFormat: 'yy-mm-dd', showOn: 'both', buttonImage: '<?php echo $this->cepluginpath; ?>/icons/calendar.png'});

			 });
			</script>
				
			<table style='width: 100%;'>
				<tr>
					<td style='width: 45%; vertical-align: top'>
						<input type="hidden" name="event_id" value="<?php if ($mode == "edit") echo $selectedevent->event_id; ?>" />
						<?php if ($mode == "edit"): ?>
						<strong>Editing Item #<?php echo $selectedevent->event_id; ?></strong>
						<?php endif; ?>

						<table>
						<tr>
						<td style='width: 30%'>Event Name</td>
						<td><input style="width:100%" type="text" name="event_name" <?php if ($mode == "edit") echo "value='" . $selectedevent->event_name . "'";?>/></td>
						</tr>
						<tr>
						<td>Category</td>
						<td><select style='width:100%' name="event_category">
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
						<td><select style='width:100%' name="event_venue">
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
							<td><input style="width:100%" type="text" name="event_description" <?php if ($mode == "edit") echo "value='" . stripslashes($selectedevent->event_description) . "'";?>/></td>
						</tr>
						<tr>
							<td>Event Web Address</td>
							<td><input style="width:100%" type="text" name="event_url" <?php if ($mode == "edit") echo "value='" . $selectedevent->event_url . "'";?>/></td>
						</tr>
						<tr>
							<td>Event Ticket Purchase Web Address</td>
							<td><input style="width:100%" type="text" name="event_ticket_url" <?php if ($mode == "edit") echo "value='" . $selectedevent->event_ticket_url . "'";?>/></td>
						</tr>
						<tr>
							<td>Start Date</td>
							<td><input type="text" id="datepickerstart" name="event_start_date" size="26" <?php if ($mode == "edit") echo "value='" . $selectedevent->event_start_date . "'"; else echo "value='" . date('Y-m-d', current_time('timestamp')) . "'"; ?>/></td>
						</tr>
						<tr>
						<td>Time</td>
						<td>
							<select name="event_start_hour" style="width: 50px">
								<?php for ($i = 1; $i <= 12; $i++)
									  {
											echo "<option value=" . $i . ">" . $i . "</option>\n";
									  }
								?>
							</select>
							:
							<select name="event_start_minute" style="width: 50px">
								<?php $minutes = array('00', '15', '30', '45');
									  foreach ($minutes as $minute)
									  {
											echo "<option value=" . $minute . ">" . $minute . "</option>\n";
									  }
								?>
							</select>
							<select name="event_start_ampm" style="width: 50px">
								<option value="AM">AM</option>
								<option value="PM">PM</option>
							</select>						
						</td>
						</tr>
						<tr>
						<td>End Date</td>
						<td><input type="text" id="datepickerend" name="event_end_date" size="26" <?php if ($mode == "edit") echo "value='" . $selectedevent->event_end_date . "'"; ?>/></td>
						</tr>					
						<tr>
						</table>
					</td>
					<td style='width=55%; vertical-align: top'>
						<button id='normalmode'><?php _e('Event Management', 'community-events'); ?></button> <button id='moderate'><?php _e('Moderation', 'community-events'); ?></button> <button id='approveselected'><?php _e('Approve Selected', 'community-events'); ?></button><br /><br />
						<?php 
						$currentday = date("z", current_time('timestamp')) + 1;
						if (date("L") == 1 && $currentday > 60)
							$currentday++;		

						$currentyear = date("Y");
						
						$eventcountquery = "SELECT count(*) from " . $wpdb->prefix . "ce_events where YEAR(event_start_date) = " . $currentyear . " and DAYOFYEAR(DATE(event_start_date)) >= " . $currentday;
						$eventcounttotal = $wpdb->get_var($eventcountquery);	
						
						if ($_GET['pagecount'] != "")
							$pagecount = $_GET['pagecount']; 
						else
							$pagecount = 1;
							
						echo $this->print_event_table($currentyear, $currentday, $pagecount, false); ?>					
						
						<input type='hidden' name='eventpage' id='eventpage' value='<?php echo $pagecount; ?>' />
						<input type='hidden' name='moderatestatus' id='moderatestatus' value='false' />
												  
						<script type="text/javascript">
							
						function navigateRight()
						{
								var el = jQuery('#eventpage');
								el.val( parseInt( el.val(), 10 ) + 1 );
								var map = {currentyear : <?php echo $currentyear; ?>, currentday : <?php echo $currentday; ?>, page: el.val(), moderate: jQuery('#moderatestatus').val() };
								jQuery('#ce-event-list').replaceWith('<div id=\"ce-event-list\"><img src=\"<?php echo WP_PLUGIN_URL; ?>/community-events/icons/Ajax-loader.gif\" alt=\"Loading data, please wait...\"></div>');
								jQuery.get('<?php echo WP_PLUGIN_URL; ?>/community-events/get-events-admin.php', map, function(data){
									jQuery('#ce-event-list').replaceWith(data);});
						}
						
						function navigateLeft()
						{
								var el = jQuery('#eventpage'); 
								el.val( parseInt( el.val(), 10 ) - 1 );
								var map = {currentyear : <?php echo $currentyear; ?>, currentday : <?php echo $currentday; ?>, page: el.val(), moderate: jQuery('#moderatestatus').val() };
								jQuery('#ce-event-list').replaceWith('<div id=\"ce-event-list\"><img src=\"<?php echo WP_PLUGIN_URL; ?>/community-events/icons/Ajax-loader.gif\" alt=\"Loading data, please wait...\"></div>');jQuery.get('<?php echo WP_PLUGIN_URL; ?>/community-events/get-events-admin.php', map, function(data){jQuery('#ce-event-list').replaceWith(data);});
						}
						
						jQuery(document).ready(function() {								
							jQuery('#moderate').click(function() { 
								jQuery('#moderatestatus').val('true');
								var map = {currentyear : <?php echo $currentyear; ?>, currentday : <?php echo $currentday; ?>, page: 1, moderate: 'true' };
								jQuery('#ce-event-list').replaceWith('<div id=\"ce-event-list\"><img src=\"<?php echo WP_PLUGIN_URL; ?>/community-events/icons/Ajax-loader.gif\" alt=\"Loading data, please wait...\"></div>');jQuery.get('<?php echo WP_PLUGIN_URL; ?>/community-events/get-events-admin.php', map, function(data){jQuery('#ce-event-list').replaceWith(data);});
								jQuery('#eventpage').val(1);
							} );
							
							jQuery('#normalmode').click(function() {
								jQuery('#moderatestatus').val('false');
								var map = {currentyear : <?php echo $currentyear; ?>, currentday : <?php echo $currentday; ?>, page: 1, moderate: 'false' };
								jQuery('#ce-event-list').replaceWith('<div id=\"ce-event-list\"><img src=\"<?php echo WP_PLUGIN_URL; ?>/community-events/icons/Ajax-loader.gif\" alt=\"Loading data, please wait...\"></div>');jQuery.get('<?php echo WP_PLUGIN_URL; ?>/community-events/get-events-admin.php', map, function(data){jQuery('#ce-event-list').replaceWith(data);});
								jQuery('#eventpage').val(1);
							} );
							
							jQuery('#approveselected').click(function() {
								var values = new Array();
								jQuery.each(jQuery("input[name='events[]']:checked"), function() {
								  values.push(jQuery(this).val());
								  var map = { eventlist: values };
								  jQuery.get('<?php echo WP_PLUGIN_URL; ?>/community-events/approveevents.php', map, function(data) {});
								  var map = {currentyear : <?php echo $currentyear; ?>, currentday : <?php echo $currentday; ?>, page: 1, moderate: 'true' };									  jQuery('#ce-event-list').replaceWith('<div id=\"ce-event-list\"><img src=\"<?php echo WP_PLUGIN_URL; ?>/community-events/icons/Ajax-loader.gif\" alt=\"Loading data, please wait...\"></div>');jQuery.get('<?php echo WP_PLUGIN_URL; ?>/community-events/get-events-admin.php', map, function(data){jQuery('#ce-event-list').replaceWith(data);});
								});

							} );
						});
						</script>	
					</td>
				</tr>
			</table>
		<?php }	

	function events_save_meta_box($data) {
		$mode = $data['mode'];
		?>

		<div class="submitbox">

		<?php if ($mode == "edit"): ?>
			<input type="submit" name="updateevent" class="button-primary" value="<?php _e('Update &raquo;','community-events'); ?>" />
		<?php else: ?>
			<input type="submit" name="newevent" class="button-primary" value="<?php _e('Insert New Event &raquo;','community-events'); ?>" />
		<?php endif; ?>

		</div>

	<?php }

	/********************************************* Shortcode processing functions *****************************************/
	
	function ce_header() {
		echo '<link rel="stylesheet" type="text/css" media="screen" href="'. WP_PLUGIN_URL . '/community-events/stylesheet.css"/>';
	}

	function ce_7day_func($atts) {
		extract(shortcode_atts(array(
		), $atts));
		
		$options = get_option('CE_PP');
		
		return $this->ce_7day($options['fullscheduleurl'], $options['outlook'], $options['addeventurl'], $options['maxevents7dayview'], $options['moderateevents']);
	}

	function venuelist ($year, $dayofyear, $outlook = 'true', $showdate = 'false', $maxevents = 5, $moderateevents = 'false', $searchstring = '') {

		global $wpdb;
		
		$output = "<table class='ce-7day-innertable' id='ce-7day-innertable'>\n";
		
		if ($searchstring != '')
		{
			$eventquery = "SELECT * from " . $wpdb->prefix . "ce_events e LEFT JOIN " . $wpdb->prefix . "ce_venues v ON e.event_venue = v.ce_venue_id ";
			$eventquery .= "where ((event_name like '%" . $searchstring . "%')";
			$eventquery .= "    or (ce_venue_name like '%" . $searchstring . "%')";
			$eventquery .= "    or (event_description like '%" . $searchstring . "%')";
			$eventquery .= ")";
			
			if ($moderateevents == 'true' || $moderateevents == "")
				$eventquery .= " and event_published = 'Y' ";
				
			$eventquery .= " order by event_start_date";
			
			$events = $wpdb->get_results($eventquery, ARRAY_A);   
			
			if ($events)
			{
				foreach($events as $event)
				{
					$event['event_name'] = $this->ce_highlight_phrase($event['event_name'], $searchstring, '<span class="highlight_word">', '</span>'); 
					$event['ce_venue_name'] = $this->ce_highlight_phrase($event['ce_venue_name'], $searchstring, '<span class="highlight_word">', '</span>'); 
					$event['event_description'] = $this->ce_highlight_phrase($event['event_description'], $searchstring, '<span class="highlight_word">', '</span>'); 
					
					$output .= "<tr><td><span class='ce-event-name'>";
					$output .= $event['event_start_date'] . " ";
					
					if ($event['event_url'] != '')
						$output .= "<a href='" . $event['event_url'] . "'>";

					$output .= $event['event_name'];

					if ($event['event_url'] != '')
						$output .= "</a>";
					
					$output .= "</span> ";
					
					if ($event['event_time'] != "")
						$output .= "<span class='ce-event-time'>" . $event['event_time'] . "</span>. ";
						
					if ($event['ce_venue_name'] != "")
						$output .= "<span class='tooltip ce-venue-name' title='<strong>" . $event['ce_venue_name'] . "</strong><br />" . $event['ce_venue_address']  . "<br />" . $event['ce_venue_city'] . "<br />" . $event['ce_venue_zipcode'] . "<br />" . $event['ce_venue_email'] . "<br />" . $event['ce_venue_phone'] . "<br />" .  $event['ce_venue_url'] . "'>" . $event['ce_venue_name'] . "</span></td></tr>\n";                    
				}
				
				if (count($events) > $maxevents)
					$output .= "<tr><td><a href='#' onClick=\"showEvents('" . $dayofyear . "', '" . $year . "', false, true, '');return false;\">See all events for " . date("l, M jS", strtotime('+ ' . $dayofyearforcalc . 'days', mktime(0,0,0,1,1,$year))) . "</a></td></tr>\n";        
			}
		}
		elseif ($outlook == 'false')
		{			
			$eventquery = "SELECT * from " . $wpdb->prefix . "ce_events e LEFT JOIN " . $wpdb->prefix . "ce_venues v ON e.event_venue = v.ce_venue_id ";
			$eventquery .= "where YEAR(event_start_date) = " . $year . " and DAYOFYEAR(DATE(event_start_date)) = " . $dayofyear;
			
			if ($moderateevents == 'true' || $moderateevents == "")
				$eventquery .= " and event_published = 'Y' ";
			
			$eventquery .= " order by e.event_name";
			
			$events = $wpdb->get_results($eventquery, ARRAY_A);
			
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
				
				if (!is_array($randomevents))
					$randomevents = array($randomevents);
							
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
					$output .= "<tr><td><a href='#' onClick=\"showEvents('" . $dayofyear . "', '" . $year . "', false, true, '');return false;\">See all events for " . date("l, M jS", strtotime('+ ' . $dayofyearforcalc . 'days', mktime(0,0,0,1,1,$year))) . "</a></td></tr>\n";
			}
			else
				$output .= "\n<tr><td>No events for this date.</td></tr>\n";
				
			if ($showdate == 'true')
			{
				$output .= "<tr><td>Select a date: <input type='text' id='datepicker' name='event_start_date' size='30' /><input type='hidden' id='dayofyear' name='dayofyear' size='30' /><input type='hidden' id='year' name='year' size='30' />\n";
				$output .= "<button id='displayDate'>Go!</button></td></tr>\n";
			}
		}
		elseif ($outlook == 'true')
		{	
			for ($i = 0; $i <= 6; $i++)
			{		
				$calculatedday = $dayofyear + $i;
			
				$eventquery = "SELECT * from " . $wpdb->prefix . "ce_events e LEFT JOIN " . $wpdb->prefix . "ce_venues v ON e.event_venue = v.ce_venue_id ";
				$eventquery .= "where YEAR(event_start_date) = " . $year . " and DAYOFYEAR(DATE(event_start_date)) = " . $calculatedday;
				
				if ($moderateevents == 'true' || $moderateevents == "")
					$eventquery .= " and event_published = 'Y' ";
				
				$eventquery .= " order by e.event_name";
				$dayevents = $wpdb->get_results($eventquery, ARRAY_A);
				
				$output .= "\t\t<tr><td class='" . ($i % 2 == 0 ? "even" : "odd") . "'>";
				$output .= "<!-- Year " . $year . " Dayofyear " . $calculatedday . " -->\n";
				$output .= "<span class='ce-outlook-day'>" . date("l, M jS", strtotime("+" . $i . " day", current_time('timestamp')));
				
				if (count($dayevents) > 1) $output .= "<span class='seemore'><a href='#' onClick=\"showEvents('" . $calculatedday . "', '" . $year . "', false, true, '');return false;\">See more</a></span>";
				
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
						$output .= "<span class='ce-ticket-link'><a id='event url' href='" . $dayevents[$randomentry]['event_ticket_url'] . "'<img title='Ticket Link' src='" . $this->cepluginpath . "/icons/tickets.gif' /></a></span>\n";
						
					$output .= "</td></tr>";
				}
				else
					$output .= "<span class='ce-outlook-event-name'>No events.</span></td></tr>\n";		
			}
			
			$output .= "<tr><td>Select a date: <input type='text' id='datepicker' name='event_start_date' size='30' /><input type='hidden' id='dayofyear' name='dayofyear' size='30' /><input type='hidden' id='year' name='year' size='30' />\n";
			$output .= "<button id='displayDate'>Go!</button></td></tr>\n";
		}
		
		$output .= "<script type='text/javascript'>\n";    
		$output .= "jQuery(document).ready(function() {\n";
		$output .= "\tjQuery('#datepicker').datepicker({minDate: '+0', dateFormat: 'yy-mm-dd', showOn: 'both', buttonImage: '" . $this->cepluginpath . "/icons/calendar.png', onSelect: function(dateText, inst) {\n";
		$output .= "var datestring = dateText.replace(/-/gi, '/');\n";
		$output .= "var incomingdate = new Date(datestring);\n";
		$output .= "var onejan = new Date(incomingdate.getFullYear(),0,1);\n";
		$output .= "jQuery('#dayofyear').val(Math.ceil((incomingdate - onejan) / 86400000) + 1);\n";
		$output .= "jQuery('#year').val(incomingdate.getFullYear());\n";
		$output .= "}   });\n";
		$output .= "\tjQuery('#displayDate').click(function() { if (jQuery('#dayofyear').val() != '') {showEvents(jQuery('#dayofyear').val(), jQuery('#year').val(), false, true, '')} else { alert('Select date first'); };});\n";
		$output .= "});\n";
		$output .= "</script>";	
		
		$output .= "\t</table>";
		
		return $output;
	}

		
	function ce_7day($fullscheduleurl = '', $outlook = true, $addeventurl = '', $maxevents = 5, $moderateevents = true, $displaysearch = true) {
		global $wpdb;
		
		$currentday = date("z", current_time('timestamp')) + 1;

		if (date("L") == 1 && $currentday > 60)
			$currentday++;		
		
		$currentyear = date("Y");	

		$output = "<!-- Current day is " . $currentday . " -->\n";

		$output .= "<SCRIPT LANGUAGE=\"JavaScript\">\n";
		
		if ($maxevents == '') $maxevents = 5;

		$output .= "function showEvents ( _dayofyear, _year, _outlook, _showdate, _searchstring) {\n";
		$output .= "var map = {dayofyear : _dayofyear, year : _year, outlook: _outlook, showdate: _showdate, maxevents: " . $maxevents . ", moderateevents: '" . ($moderateevents == true ? 'true' : 'false') . "', searchstring: _searchstring};\n";
		$output .= "\tjQuery('.ce-7day-innertable').replaceWith('<div class=\"ce-7day-innertable\"><img src=\"" . WP_PLUGIN_URL . "/community-events/icons/Ajax-loader.gif\" alt=\"Loading data, please wait...\"></div>');jQuery.get('" . WP_PLUGIN_URL . "/community-events/get-events.php', map, function(data){jQuery('.ce-7day-innertable').replaceWith(data);";
		$output .= "\tjQuery('.tooltip').each(function()\n";
		$output .= "\t\t{ jQuery(this).tipTip(); }\n";
		$output .= "\t);});\n\n";
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
			$output .= "\t<td class='ce-daybox selected' id='day_0_" . $currentyear . "_cell'><a href='#' class='ce-day-link' id='day_0_" . $currentyear . "' onClick=\"showEvents('" . $currentday . "', '" . $currentyear . "', true, false, '');return false;\"><strong>Upcoming Events</strong></a></td>\n";
		}
		
		for ($i = 0; $i <= 6; $i++) {
			$daynumber = $currentday + $i;
			$output .= "\t<td class='ce-daybox " . ($i % 2 == 0 ? "even" : "odd");
			$output .= "' id='day_" . $daynumber . "_" . $currentyear . "_cell'>\n";
			
			$output .= "\t\t<span class='ce-dayname'><a href='#' class='ce-day-link' id='day_" . $daynumber . "_" . $currentyear . "' onClick=\"showEvents('" . $daynumber. "', '" . $currentyear . "', false, false, '');return false;\">" . date("D", strtotime("+" . $i . " day", current_time('timestamp'))) . "<br /><span class='ce-date'>" . date("j", strtotime("+" . $i . " day", current_time('timestamp'))) . "</a></span>\n";
			
			$output .= "\t</td>\n";
		}

		$output .= "</tr>\n\t<tr><td class='ce-inner-table-row' colspan='" . (($outlook == true) ? 8 : 7) . "'>\n";
		
		$output .= $this->venuelist($currentyear, $currentday, ($outlook == true ? 'true' : 'false'), 'false', $maxevents, ($moderateevents == true ? "true" : "false"));

		$output .= "\t</td></tr>\n";
		
		if ($displaysearch == true)
		{
			if ($outlook == true)
				$colspan = 8;
			else
				$colspan = 7;
				
			$output .= "<tr class='ce-search'><td colspan=" . $colspan . ">\n";
			$output .= "Search Events: <input type='text' id='ce_event_search' name='ce_event_search' size='50' />\n";
			$output .= "<button onClick=\"showEvents('" . $daynumber. "', '" . $currentyear . "', false, false, jQuery('#ce_event_search').val());return false;\" id='ce-search-button'><img src='" . $this->cepluginpath . "/icons/magnifier.png' /></button>\n";
			$output .= "</td></tr>\n";
		}
		
		if ($fullscheduleurl != '' || $addeventurl != '')
		{
			$output .= "<tr class='ce-full-schedule-link'>\n";
			
			if ($fullscheduleurl != '')
			{
				if ($outlook == true && $addeventurl != '')
					$colspan = 3;
				elseif ($outlook == true && $addeventurl == '')
					$colspan = 8;
				elseif ($outlook == false && $addeventurl != '')
					$colspan = 3;
				elseif ($outlook == false && $addeventurl == '')
					$colspan = 7;			
					
				$output .= "<td colspan='" . $colspan . "'><a href='" . $fullscheduleurl . "'>Full Schedule</a></td>";
			}
				
			if ($addeventurl != '')
			{
				if ($outlook == true && $fullscheduleurl != '')
					$colspan = 5;
				elseif ($outlook == true && $fullscheduleurl == '')
					$colspan = 8;
				elseif ($outlook == false && $fullscheduleurl != '')
					$colspan = 4;
				elseif ($outlook == false && $fullscheduleurl == '')
					$colspan = 7;			

				$output .= "<td colspan='" . $colspan . "' class='ce-add-event-link'><a href='" . $addeventurl . "'>Submit your own event</a></td>";
			}
			
			$output .= "</tr>\n";
		
		}		
		
		$output .= "</table></div>\n";	

		return $output;
	}

	function ce_full_func($atts) {
		extract(shortcode_atts(array(
		), $atts));
		
		$options = get_option('CE_PP');
		
		if ($options['schemaversion'] < 0.3)
			$this->ce_install();
		
		return $this->ce_full($options['moderateevents']);
	}

	function ce_full($moderateevents = true) {
			global $wpdb;
		
		$currentday = date("z", current_time('timestamp')) + 1;
		if (date("L") == 1 && $currentday > 60)
			$currentday++;		
		
		$currentyear = date("Y");	
		
		$eventquery = "SELECT *, UNIX_TIMESTAMP(event_start_date) as datestamp, DAYOFYEAR(DATE(event_start_date)) as doy from " . $wpdb->prefix . "ce_events e LEFT JOIN ";
		$eventquery .= $wpdb->prefix . "ce_venues v ON e.event_venue = v.ce_venue_id where ((YEAR(event_start_date) = " . $currentyear;
		$eventquery .= " and DAYOFYEAR(DATE(event_start_date)) >= " . $currentday . ") ";
		$eventquery .= " or YEAR(event_start_date) > " . $currentyear . ") ";
		
		if ($moderateevents == true || $moderateevents == "")
			$eventquery .= " and event_published = 'Y' ";
		
		$eventquery .= " order by event_start_date, event_name";
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
		
		if ($options['schemaversion'] < 0.3)
			ce_install();	
		
		if ($_POST['event_name'])
		{
			if ($_POST['event_name'] != '')
			{
				$newevent = array("event_name" => wp_specialchars(stripslashes($_POST['event_name'])), "event_start_date" => wp_specialchars(stripslashes($_POST['event_start_date'])), "event_start_hour" => wp_specialchars(stripslashes($_POST['event_start_hour'])), "event_start_minute" => wp_specialchars(stripslashes($_POST['event_start_minute'])), "event_start_ampm" => wp_specialchars(stripslashes($_POST['event_start_ampm'])),
					"event_description" => wp_specialchars(stripslashes($_POST['event_description'])), "event_url" => wp_specialchars(stripslashes($_POST['event_url'])), "event_ticket_url" => wp_specialchars(stripslashes($_POST['event_ticket_url'])), "event_venue" => $_POST['event_venue'], "event_category" => $_POST['event_category']);
					
				if ($_POST['event_end_date'] != '')
					$newevent['event_end_date'] = $_POST['event_end_date'];
					
				if ($options['moderateevents'] == true || $options['moderateevents'] == '')
					$newevent['event_published'] = 'N';
				else
					$newevent['event_published'] = 'Y';
				
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
					$message .= __('Event Start Date', 'community-events') . ": " . $newevent['event_start_date'] . "<br /><br />";
					$message .= __('Event Time', 'community-events') . ": " . $newevent['event_time'] . "<br /><br />";
								
					if ( !defined('WP_ADMIN_URL') )
						define( 'WP_ADMIN_URL', get_option('siteurl') . '/wp-admin');
											
					$message .= "<br /><br />" . __('Message Generated by', 'community-events') . " <a href='http://yannickcorner.nayanna.biz/wordpress-plugins/community-events/'>Community Events</a> for Wordpress";
					
					wp_mail($adminmail, htmlspecialchars_decode(get_option('blogname'), ENT_QUOTES) . " - New event added: " . htmlspecialchars($_POST['event_name']), $message, $headers);
				}	

					$message = "<div class='eventconfirmsubmit'>Thank you for your submission.</div>\n";
			}
		}
		
		return $message . $this->ce_addevent($options['columns'], $options['addeventreqlogin'], $options['addneweventmsg'], $options['eventnamelabel'], $options['eventcatlabel'], 
							$options['eventvenuelabel'], $options['eventdesclabel'], $options['eventaddrlabel'], $options['eventticketaddrlabel'], $options['eventdatelabel'],
							$options['eventtimelabel'], $options['addeventbtnlabel'], $options['eventenddatelabel']);
	}

	function ce_addevent($columns = 2, $addeventreqlogin = false, $addneweventmsg = "", $eventnamelabel = "", $eventcatlabel = "", $eventvenuelabel = "", 
						$eventdesclabel = "", $eventaddrlabel = "", $eventticketaddrlabel = "", $eventdatelabel = "", $eventtimelabel = "", $addeventbtnlabel = "",
						$eventenddatelabel = "") {

		global $wpdb;

		if (($addeventreqlogin && current_user_can("read")) || !$addeventreqlogin)
		{
			$output = "<form method='post' id='ceaddevent'>\n";
			$output .= "<div class='ce-addevent'>\n";
			
			if ($addneweventmsg == "") $addneweventmsg = __('Add New Event', 'community-events');
			$output .= "<div id='ce-addeventtitle'>" . $addneweventmsg . "</div>\n";
			
			$output .= "<table class='ce-addeventtable'><tr>\n";
			
			if ($eventnamelabel == "") $eventnamelabel = __('Event Name', 'community-events');
			$output .= "<th style='width: 100px'>" . $eventnamelabel . "</th><td colspan=" . ($columns == 1 ? 1 : 3) . "><input style='width: 100%' type='text' name='event_name' id='event_name' /></td></tr>\n";
			
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
			$output .= "<tr><th>" . $eventdesclabel . "</th><td colspan=" . ($columns == 1 ? 1 : 3) . "><input type='text' style='width: 100%' name='event_description' id='event_description' /></td></tr>\n";				
				
			if ($eventaddrlabel == "") $eventaddrlabel = __('Event Web Address', 'community-events');
			$output .= "<tr><th>" . $eventaddrlabel . "</th><td colspan=" . ($columns == 1 ? 1 : 3) . "><input type='text' style='width: 100%' name='event_url' id='event_url' /></td></tr>\n";
			
			if ($eventticketaddrlabel == "") $eventticketaddrlabel = __('Event Ticket Purchase Link', 'community-events');
			$output .= "<tr><th>" . $eventticketaddrlabel . "</th><td colspan=" . ($columns == 1 ? 1 : 3) . "><input type='text' style='width: 100%' name='event_ticket_url' id='event_ticket_url' /></td></tr>\n";
			
			if ($eventdatelabel == "") $eventdatelabel = __('Event Start Date', 'community-events');
			$output .= "<tr><th>" . $eventdatelabel . "</th><td><input type='text' name='event_start_date' id='datepickeraddform' value='" . date('Y-m-d', current_time('timestamp')) . "' /></td>\n";
			
			if ($columns == 1)
				$output .= "</tr><tr>";
						
			if ($eventtimelabel == "") $eventtimelabel = __('Event Time', 'community-events');
			$output .= "<th>" . $eventtimelabel . "</th><td>";
			
			$output .= "<select name='event_start_hour' style='width: 50px'>\n";
			for ($i = 1; $i <= 12; $i++)
			{
				$output .= "<option value=" . $i . ">" . $i . "</option>\n";
			}
			$output .= "</select>:\n";

			$output .= "<select name='event_start_minute' style='width: 50px'>\n";
			
			$minutes = array('00', '15', '30', '45');
			foreach ($minutes as $minute)
			{
				$output .= "<option value=" . $minute . ">" . $minute . "</option>\n";
			}
			$output .= "</select>\n";
			
			$output .= "<select name='event_start_ampm' style='width: 50px'>\n";
			$output .= "<option value='AM'>AM</option>\n";
			$output .= "<option value='PM'>PM</option>\n";
			$output .= "</select>\n";
			
			$output .= "</td></tr>\n";
			
			if ($eventenddatelabel == "") $eventenddatelabel = __('Event End Date', 'community-events');
			$output .= "<tr><th>" . $eventenddatelabel . "</th><td colspan='3'><input type='text' name='event_end_date' id='datepickeraddformend' /></td>\n";

								
			$output .= "</table>\n";
			
			if ($addeventbtnlabel == "") $addeventbtnlabel = __('Add Event', 'community-events');
			$output .= '<span style="border:0;" class="submit"><input type="submit" name="submit" value="' . $addeventbtnlabel . '" /></span>';
			
			$output .= "</div>\n";
			$output .= "</form>\n\n";
			
			$output .= "<script type='text/javascript'>\n";
			$output .= "jQuery(document).ready(function() {\n";
			$output .= "jQuery('#datepickeraddform').datepicker({minDate: '+0', dateFormat: 'yy-mm-dd', showOn: 'both', buttonImage: '" . $this->cepluginpath . "/icons/calendar.png'});\n";
			$output .= "jQuery('#datepickeraddformend').datepicker({minDate: '+0', dateFormat: 'yy-mm-dd'});\n";
			$output .= "});\n";
			$output .= "</script>\n";
		}

		return $output;
	}


}

$my_community_events_plugin = new community_events_plugin();

?>