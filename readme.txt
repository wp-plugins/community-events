=== Community Events ===
Contributors: jackdewey
Donate link: http://yannickcorner.nayanna.biz/wordpress-plugins/community-events/
Tags: events, list, AJAX
Requires at least: 3.0
Tested up to: 3.0
Stable tag: trunk

The purpose of this plugin is to allow users to create a schedule of upcoming events and display events for the next 7 days in an AJAX-driven box or displaying a full list of upcoming events. *WORK-IN-PROGRESS*

== Description ==

The purpose of this plugin is to allow users to create a schedule of upcoming events and display events for the next 7 days in an AJAX-driven box or displaying a full list of upcoming events. *WORK-IN-PROGRESS*

== Installation ==

1. Download the plugin and unzip it.
1. Upload the community-events folder to the /wp-content/plugins/ directory of your web site.
1. Activate the plugin in the Wordpress Admin.
1. Using the Configuration Panel for the plugin, create events, venues and categories.
1. To see the 7-day schedule box, in the Wordpress Admin, create a new page containing the following code: [community-events-7day]
1. To see the full schedule, in the Wordpress Admin area, create a new page containing the following code: [community-events-full]
1. To see a link for the full schedule in the 7-day box, set the address of the full schedule page in the Community Events settings.

== Changelog ==

= 0.4 =
* Fixed errors when clicking on dates in schedule
* Changed calendar plugin to use jQuery date picker
* Re-arranged back-end code to provide more structure and make admin sections hideable
* Added search capability
* Adding paging mechanism when viewing events in admin

= 0.3 =
* Removed duration field on events
* Change data entry for event time into hours and minutes
* Added end date field (but not currently using it to display events)
* Added option to put new events in moderation queue upon user submission
* Added moderation mechanism on admin page to view only events awaiting moderation and approve them
* Changed layout of full schedule and add event links

= 0.2.2 =
* Fixed some image styling to avoid problems with plugins assigning border to images.
* Fixed: unable to add events in version 0.2 through user form or back-end admin

= 0.2.1 =
* Added missing icons and javascript plugin (TipTip)

= 0.2 =
* Added calendar button next to date field in back-end to bring up calendar
* Added paging buttons in event section of admin to navigate events
* Limited calendar only to allow selections past current day
* Added tooltips in calendar view when mouse hovers over events to display more venue information and event information
* Made Day Links in 7-day view one link as opposed to two
* Added new outlook section to 7-day view to show one item per day
* Added calendar to upcoming events section to be able to choose other dates
* Added button to buy tickets when link is available
* Added shortcode to display form for visitors to submit events
* Added options in admin page to control display of new event form
* Plugin sends e-mail when new events are submitted
* Added scheduled task to perform daily cleanup of expired events in the database

= 0.1 =
* First checkin: Still a work-in-progress

== Frequently Asked Questions ==

There are no FAQs at this time.

== Screenshots ==

There are no screenshots at this time.