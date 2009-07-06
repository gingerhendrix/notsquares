<?php

/*
Plugin Name: Last.FM Events
Version: 1.1b
Plugin URI: http://simonwheatley.co.uk/wordpress-plugins/
Description: Looks at the events you've said you're attending in Last.FM, and displays them on your blog. Uses hCal microformat. Loosely based on Ricardo Gonz&aacute;lez's Last.fm for Wordpress plugin. 
Author: Simon Wheatley
Author URI: http://www.simonwheatley.co.uk/

Copyright 2007 Simon Wheatley

This script is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This script is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.

*/

// You can edit this, but I don't suggest making it too often
define('LFE_CACHE_AGE', 60*60); // In seconds, so this is 1 hour (60 minutes). No need to check more frequently than this.
define('LFE_CACHE', 'lfe_cached_events');
define('LFE_OPTIONS', 'widget_lfe');

define('LFE_DEFAULT_PROFILE_LINK_TEXT', __('My Last.FM events'));
define('LFE_DEFAULT_TITLE', __('Upcoming Last.FM events'));
define('LFE_DATE_ISO8601', '%Y-%m-%dT%H:%M:%S'); // PHP5 has this set automatically, but we can't rely on PHP5. Sigh.

// HTTP client stuff
define('LFE_USER_AGENT', 'WordPress/' . $GLOBALS['wp_version']);
define('LFE_FETCH_TIME_OUT', 5); // 5 second timeout, Last.FM can be slow
// Use gzip encoding to fetch remote files if supported?
define('LFE_USE_GZIP', true);

require_once(ABSPATH . WPINC . '/rss.php');
include "Snoopy.class.php";

// Modelled on the Magpie function of a similar name
function lfe_fetch_remote_file ( $url ) {
	// Snoopy is an HTTP client in PHP
	$client = new Snoopy();
	$client->agent = LFE_USER_AGENT;
	$client->read_timeout = LFE_FETCH_TIME_OUT;
	$client->use_gzip = LFE_USE_GZIP;
	// SWTODO: Would be good to utilise last-modified when fetching the initial file, allowing them to return 304 Not Modified, to help reduce Last.FM's load.
	if (is_array($headers) ) {
		$client->rawheaders = $headers;
	}

	@$client->fetch($url);
	return $client;
}

function lfe_fetch_remote_file_contents( $url )
{
	$response = lfe_fetch_remote_file( $url );
  
	// Check we had a sensible response
	// If we timed out, return false and we can fall back (hopefully) on a cached version
	if ( $response->timed_out ) return false;
	// If its anything other than 200 OK,
	// e.g. 404 Not Found, 500 Internal Server Error, 304 Not Modified, etc, 
	// Request for event info failed, we'll fall back on a (hopefully) cached copy
	if ( $response->status != 200 ) return false;	
	// It all looks OK
	$contents = $response->results;
	
	return $contents;
}

// Provide an associative array of event info, using the cache if in date or getting 
// new data if the cache is outdated
function lfe_maybe_get_new_events()
{
	// Check if we've got a cached copy
	$cached_events = get_option(LFE_CACHE);
	// No cache? Get new events...
	if ( empty($cached_events) ) return lfe_get_new_events();

	// Check if it's out of date
	$now = time();
	$out_of_date = (bool) (( $now - $cached_events['modified_timestamp'] ) > LFE_CACHE_AGE);
	// Out of date? Get new events...
	if ( $out_of_date ) return lfe_get_new_events();

	return $cached_events['events'];
}

// Get new events data from Last.FM
function lfe_get_new_events()
{
	$options = get_option(LFE_OPTIONS);
	$username = urlencode($options['username']);
	
	// Get the file contents in a way which will work, hopefully even for systems with url_file_open off.
  // 	$url = "http://ws.audioscrobbler.com/2.0/artist/not%20squares/events.ical";
  //HACK to retrieve artist events instead
	$url = "http://ws.audioscrobbler.com/2.0/artist/$username/events.ical";

	$vcal = lfe_fetch_remote_file_contents ($url);
	
	// Something went wrong with the request
	if ( $vcal === false ) {
		// Check if we've got a cached copy
		$cached_events = get_option(LFE_CACHE);
		// Got a cache? Use it...
		if ( ! empty($cached_events) ) {
			return $cached_events['events'];
		}
		return false;
	}

	// Split into it's parts
	$vcal_events = explode('BEGIN:VEVENT', $vcal);

	// First array index will be the vCal general info, which we don't care about
	// (Currently the timezones appear to all be UTC, but if this changes we may need to
	// take note before chucking the general info.)
	array_shift($vcal_events);

	// Create an object we can cache, including a modified time so we know when it goes out of date
	$to_cache = array('events'=>array(), 'modified_timestamp'=>time());

	// Create a reference for ease
	$events = & $to_cache['events'];

	// SWFIXME: Maybe optimise performance by passing by reference?
	foreach ( $vcal_events AS $vcal_event ) {
		$events[] = lfe_parse_event( $vcal_event );
	}
	
	update_option(LFE_CACHE, $to_cache);

	return $to_cache['events'];
}

function lfe_parse_event($vcal_event)
{		
	// Get rid of '\r' entities (characters? things? annoyances? line returns?)
	$vcal_event = str_replace("\r", '', $vcal_event);

	// Split the vCal event by line
	$vcal_event_lines = explode("\n", $vcal_event);

	// A nice clean array of information
	$event = array();

	// Go through the lines, and extract the information
	foreach ( $vcal_event_lines AS $line ) {
		// The URL
		if ( stripos($line, 'URL;VALUE=URI:') !== false ) {
			// e.g. URL;VALUE=URI:http://www.last.fm/event/306012
			$event['url'] = str_replace('URL;VALUE=URI:', '', $line);
		}
		// The start datetime in ISO 8601, which we'll convert to UNIX time for convenience
		// SWFIXME: We'll ignore timezones, because it's just too hard in PHP without screeds of adding data on all the timezones *in the world* to this script
		if ( stripos($line, 'DTSTART') !== false ) {
			// e.g. DTSTART;TZID=UTC;VALUE=DATE:20080131
			// e.g. DTSTART;TZID=Europe/London:20070930T190000
			$bits = explode(':', $line);
			$event['start_iso_date'] = $bits[1];
			// Assume we're dealing with a compact ISO datetime (i.e. without dash separators)
			$event['start_timestamp'] = lfe_iso_date_to_time( $event['iso_date'] );
		}
		// The start datetime in ISO 8601, which we'll convert to UNIX time for convenience
		// SWFIXME: We'll ignore timezones initially, because it's just too hard in PHP without adding screeds of data defining all the timezones *in the world* to this script
		if ( stripos($line, 'DTSTART') !== false ) {
			// e.g. DTSTART;TZID=UTC;VALUE=DATE:20080131
			// e.g. DTSTART;TZID=Europe/London:20070930T190000
			$bits = explode(':', $line);
			$event['start_iso_date'] = $bits[1];
			// Assume we're dealing with a compact ISO datetime (i.e. without dash separators)
			$event['start_timestamp'] = lfe_iso_date_to_time( $event['start_iso_date'] );
		}
		// The end datetime in ISO 8601, which we'll convert to UNIX time for convenience
		// SWFIXME: We'll ignore timezones initially, because it's just too hard in PHP without adding screeds of data defining all the timezones *in the world* to this script
		if ( stripos($line, 'DTEND;') !== false ) {
			// e.g. DTEND;TZID=UTC;VALUE=DATE:20080131
			// e.g. DTEND;TZID=Europe/London:20070930T190000
			$bits = explode(':', $line);
			$event['end_iso_date'] = $bits[1];
			// Assume we're dealing with a compact ISO datetime (i.e. without dash separators)
			$event['end_timestamp'] = lfe_iso_date_to_time( $event['end_iso_date'] );
		}
		// Summary
		if ( stripos($line, 'SUMMARY:') !== false ) {
			// e.g. SUMMARY:The Puppini Sisters at The Ritz
			$event['summary'] = str_replace('SUMMARY:', '', $line);
		}
		// Description, slightly different to summary, but not much
		if ( stripos($line, 'DESCRIPTION:') !== false ) {
			// e.g. DESCRIPTION:The Puppini Sisters - The Ritz
			$event['description'] = str_replace('DESCRIPTION:', '', $line);
		}
		// The location, which includes a URL for the Last.FM page for the venue
		if ( stripos($line, 'LOCATION;') !== false ) {
			// e.g. LOCATION;VENUE-UID="http://www.last.fm/venue/8837067":The Ritz\, Manchester\, United Kingdom
			$bits = explode(':', $line);
			// SWFIXME: The address is escaped, wonder what else is escaped that I'm not noticing?
			$event['venue_address'] = stripslashes($bits[2]);
			// SWFIXME: We assume here that the venue URL contains no quote marks... safe assumption?
			$other_bits = explode('"', $line);
			$event['venue_url'] = $other_bits[1];
		}
	}

	return $event;
}

// Display Last.fm events.
function lfe_write_events( & $events, $username = '', $num = 5, $list = true, $list = true, $link_event  = true, 
	$show_venue = true, $link_venue = true ) {

	// Initiate a counter
	$i = 0;
	// Always hoping for at least one event
	if ($num <=0) $num = 1;
	// Max ten events
	if ($num >10) $num = 10;

	if ($list) echo '<ul class="lfe">';

	if ($username == '') {
		if ($list) echo '<li>';
		echo '<p style="color: #c00; background-color: #ff9; padding: 5px;"><strong>'.__('You need to set your Last.FM username!').'</strong>'.__('Edit the widget settings to add it.').'</p>';
		if ($list) echo '</li>';
	} else {
		if ( empty($events) ) {
			if ($list) {
				echo '<li class="lfe_no_events">';
			} else {
				echo '<p class="lfe_no_events">';
			}
			echo '<em>'.__('No events coming up.').'</em>';
			if ($list) {
				echo '</li>';
			} else {
				echo '</p>';
			}
		} else {
			// Init counter
			$counter = 0;
			foreach ( $events as $event ) {			

				// SWTODO: Maybe allow user to choose to display the date or time remaining until
				$time_as = 'date';//'difference';

				$zero_length = ( $event['start_timestamp'] == $event['end_timestamp'] );

				if ( $time_as == 'date' ) {	
					// strftime should be locale sensitive.
					$nice_start_date = strftime('%d %b', $event['start_timestamp']);
					$nice_end_date = strftime('%d %b', $event['end_timestamp']);
				} else {
					$nice_start_date = lfe_time_difference($event['start_timestamp']);
					$nice_end_date = lfe_time_difference($event['end_timestamp']);
				}

				$iso_start_date = strftime(LFE_DATE_ISO8601, $event['start_timestamp']);
				$iso_end_date = strftime(LFE_DATE_ISO8601, $event['end_timestamp']);

				// Start writing the event
				if ($list) {
					echo "<li class='vevent vevent_$counter'>";
				} elseif ($num != 1) {
					echo "<p class='vevent vevent_$counter'>";
				}
				echo "<strong>";
				if ( $link_event ) {
					echo "<a href='{$event['url']}' class='url summary'>";
				} else {
					echo "<span class='summary'>";
				}
				echo $event['summary'];
				if ( $link_event ) {
					echo "</a>";
				} else {
					echo "</span>";
				}
				echo "</strong>";
				if ( $show_venue ) {
					// SWTODO: Find a way of splitting the location information to make this an hCard
					echo "<br /><span class='location'>";
					if ( $link_venue ) echo "<a href='{$event['venue_url']}'>";
					echo "{$event['venue_address']}";
					if ( $link_venue ) echo "</a>";
					echo "</span> ";
				}
				echo "<br /><abbr class='dtstart' title='$iso_start_date'>";
				// Don't show the optional (in hcal terms) end date if it's the same as the start
				echo "$nice_start_date</abbr>";
			/*	if ( ! $zero_length && $time_as != 'difference' ) {
					echo " - <abbr class='dtend' title='$iso_end_date'>$nice_end_date</abbr>";
				}
				elseif ( ! $zero_length && $time_as == 'difference' ) {
					echo "<abbr class='dtend lfe_hide' title='$iso_end_date'>.</abbr>";
				}
				else {
					echo "<span class='lfe_hide'>.</span>";
				}*/
				if ($list) {
					echo "</li>";
				} elseif ($num != 1) {
					echo "</p>";
				}

				$counter++;
				if ( $counter >= $num ) break;
			} // foreach
		}

		if ($list) echo '</ul>';
	}
}

function lfe_iso_date_to_time( $iso_date )
{
	// We're going to use MySQL for this, because we can't rely on PHP having a decent
	// function for the job. (Because we can't rely on PHP5.)
	global $wpdb;
	$sql = "SELECT UNIX_TIMESTAMP('$iso_date')";
	return $wpdb->get_var($sql);
}

// Return the time either until or ago in months, weeks and days
// Very rough, assumes a day is 24 hours (i.e. ignores daylight saving)
// and assumes a month is 30 days
function lfe_time_difference($timestamp) {
	$diff = $timestamp - time();
	$prefix = 'In ';
	$suffix = '';
	if ( $diff < 0 ) {
		$prefix = '';
		$suffix = ' ago';
		// Got to deal with positive numbers
		$diff = $diff * -1;
	}

	// Define our time periods in seconds
	$minute = 60; // Start off with a minute being 60 seconds
	$hour = 60 * $minute; // Fairly safe
	$day = 24 * $hour; // Inaccurate, as it ignores daylight saving
	$week = 7 * $day; // Fairly safe
	$month = 4 * $week; // Obviously inaccurate
	
    $months = floor($diff / $month);
    $diff -= $months * $month;
    $weeks = floor($diff / $week);
    $diff -= $weeks * $week;
    $days = floor($diff / $day);
    $diff -= $days * $day;
    $hours = floor($diff / $hour);
    $diff -= $hours * $hour;
    $minutes = floor($diff / $minute);
    $diff -= $minutes * $minute;
    $seconds = $diff;

	$time_difference = '';
	if ($months>0) {
		// months
		$time_difference .= ($time_difference?', ':'').$months.' ';
		// Potentially the il8n for month/week/day is not just suffixing an 's'
		// So we'll treat them as separate strings for internationalisation
		if ( $months == 1 ) {
			$time_difference .= __('month');
		} else {
			$time_difference .= __('months');
		}
	} elseif ($weeks>0) {
		// weeks and days
		$time_difference .= ($time_difference?', ':'').$weeks.' ';
		if ( $weeks == 1 ) {
			$time_difference .= __('week');
		} else {
			$time_difference .= __('weeks');
		}
		$time_difference .= $days>0?($time_difference?', ':'').$days.' ':'';
		if ( $days == 1 ) {
			$time_difference .= __('day');
		} elseif ( $days > 0 ) {
			$time_difference .= __('days');
		}
	} elseif ($days>0) {
		// days and hours
		$time_difference .= ($time_difference?', ':'').$days.' ';
		if ( $days == 1 ) {
			$time_difference .= __('day');
		} else {
			$time_difference .= __('days');
		}
		$time_difference .= $hours>0?($time_difference?', ':'').$hours.' ':'';
		if ( $hours == 1 ) {
			$time_difference .= __('hour');
		} elseif ( $hours > 0 ) {
			$time_difference .= __('hours');
		}
	} elseif ($hours>0) {
		// hours and minutes
		$time_difference .= ($time_difference?', ':'').$hours.' ';
		if ( $hours == 1 ) {
			$time_difference .= __('hour');
		} else {
			$time_difference .= __('hours');
		}
		$time_difference .= $minutes>0?($time_difference?', ':'').$minutes.' ':'';
		if ( $minutes == 1 ) {
			$time_difference .= __('minute');
		} elseif ( $minutes > 0 ) {
			$time_difference .= __('minutes');
		}
	} elseif ($minutes>0) {
		// minutes only
		$time_difference .= ($time_difference?', ':'').$minutes.' ';
		if ( $minutes == 1 ) {
			$time_difference .= __('minute');
		} else {
			$time_difference .= __('minutes');
		}
	} else {
		// seconds only
		$time_difference .= ($time_difference?', ':'').$seconds.' ';
		if ( $seconds == 1 ) {
			$time_difference .= __('second');
		} else {
			$time_difference .= __('seconds');
		}
	}

    // Add proper verbiage
	$time_difference = $prefix . $time_difference . $suffix;
    return $time_difference;
}

// lastfm widget stuff
function lfe_widget_init() {

	if ( !function_exists('register_sidebar_widget') )
		return;

	function lfe_widget($args) {
		
		// $args is an array of strings that help widgets to conform to
		// the active theme: before_widget, before_title, after_widget,
		// and after_title are the array keys. Default tags: li and h2.
		extract($args);

		// Each widget can store its own options. We keep strings here.
		$options = get_option(LFE_OPTIONS);
		$username = $options['username'];
		// Set some default text
		$title = ($options['title']) ? $options['title'] : LFE_DEFAULT_TITLE;
		$profile_link_text = ($options['profile_link_text']) ? $options['profile_link_text'] : LFE_DEFAULT_PROFILE_LINK_TEXT;
		$num = $options['num'];
		$list = ($options['list']) ? true : false;
		$link_event = ($options['link_event']) ? true : false;
		$show_venue = ($options['show_venue']) ? true : false;
		$link_venue = ($options['link_venue']) ? true : false;
		$hide_on_empty = ($options['hide_on_empty']) ? true : false;

		// These lines generate our output.

		// This will use a cached copy of the events data if it exists and is in date
		$events = & lfe_maybe_get_new_events();

		// If we're not supposed to have anything when we're empty, we abandon ship at this point.
		// SWTODO: Maybe how a 'blank slate' event if the user is a logged on admin? So it doesn't appear as though nothing is happening.
		if ( empty($events) && $hide_on_empty ) return;

		echo $before_widget . $before_title . $title . $after_title;
		lfe_write_events($events, $username, $num, true, $list, $link_event, $show_venue, $link_venue);
		echo "<p class='lastfm-profile'><a href='http://www.last.fm/music/".urlencode($username)."/+events'>";
		echo "$profile_link_text</a></p>";
		echo $after_widget;
	}

	// This is the function that outputs the form to let the users edit
	// the widget's the options for the widget.
	function lfe_widget_ctrl() {

		// Get our options and see if we're handling a form submission.
		$options = get_option(LFE_OPTIONS);
		if ( ! is_array($options) ) {
			// Populate defaults
			$options = array(
					'title'=>LFE_DEFAULT_TITLE, 
					'username'=>'', 
					'profile_link_text'=>LFE_DEFAULT_PROFILE_LINK_TEXT, 
					'num'=>'5', 
					'list'=>true, 
					'link_event'=>true,
					'show_venue'=>true,
					'link_venue'=>true,
					'hide_on_empty'=>true,
				);
		}
		if ( $_POST['lfe_submit'] ) {
			// Remember to sanitize and format input appropriately.
			$options['title'] = strip_tags(stripslashes($_POST['lfe_title']));
			$options['username'] = strip_tags(stripslashes($_POST['lfe_username']));
			$options['profile_link_text'] = strip_tags(stripslashes($_POST['lfe_profile_link_text']));
			$options['num'] = strip_tags(stripslashes($_POST['lfe_num']));
			$options['list'] = (bool) @ $_POST['lfe_list'];
			$options['link_event'] = (bool) @ $_POST['lfe_link_event'];
			$options['show_venue'] = (bool) @ $_POST['lfe_show_venue'];
			$options['link_venue'] = (bool) @ $_POST['lfe_link_venue'];
			$options['hide_on_empty'] = (bool) @ $_POST['lfe_hide_on_empty'];
			update_option(LFE_OPTIONS, $options);
		}

		// Be sure you format your options to be valid HTML attributes.
		$title = htmlspecialchars($options['title'], ENT_QUOTES);
		$username = htmlspecialchars($options['username'], ENT_QUOTES);
		$profile_link_text = htmlspecialchars($options['profile_link_text'], ENT_QUOTES);
		$num = htmlspecialchars($options['num'], ENT_QUOTES);
		$list_checked = ($options['list']) ? 'checked="checked"' : '';
		$link_event_checked = ($options['link_event']) ? 'checked="checked"' : '';
		$show_venue_checked = ($options['show_venue']) ? 'checked="checked"' : '';
		$link_venue_checked = ($options['link_venue']) ? 'checked="checked"' : '';
		$hide_on_empty_checked = ($options['hide_on_empty']) ? 'checked="checked"' : '';
		
		// Here is our little form segment. Notice that we don't need a
		// complete form. This will be embedded into the existing form.
		echo '<p style="text-align:right;"><label for="lfe_title">' . __('Title:') . ' <input style="width: 200px;" id="lfe_title" name="lfe_title" type="text" value="'.$title.'" /></label></p>';
		echo '<p style="text-align:right;"><label for="lfe_username">' . __('Username:') . ' <input style="width: 200px;" id="lfe_username" name="lfe_username" type="text" value="'.$username.'" /></label></p>';
		echo '<p style="text-align:right;"><label for="lfe_hide_on_empty">' . __('Hide this widget if there\'s no events:') . ' <input id="lfe_hide_on_empty" name="lfe_hide_on_empty" type="checkbox"'.$hide_on_empty_checked.' /></label></p>';
		echo '<p style="text-align:right;"><label for="lfe_profile_link_text">' . __('Profile link text:') . ' <input style="width: 200px;" id="lfe_profile_link_text" name="lfe_profile_link_text" type="text" value="'.$profile_link_text.'" /></label></p>';
		echo '<p style="text-align:right;"><label for="lfe_num">' . __('Maximum number of events:') . ' <input style="width: 25px;" id="lfe_num" name="lfe_num" type="text" value="'.$num.'" /></label></p>';
		echo '<p style="text-align:right;"><label for="lfe_list">' . __('Display events in a bulleted list:') . ' <input id="lfe_list" name="lfe_list" type="checkbox"'.$list_checked.' /></label></p>';
		echo '<p style="text-align:right;"><label for="lfe_link_event">' . __('Link to events on Last.FM:') . ' <input id="lfe_link_event" name="lfe_link_event" type="checkbox"'.$link_event_checked.' /></label></p>';
		echo '<p style="text-align:right;"><label for="lfe_show_venue">' . __('Show venue information:') . ' <input id="lfe_show_venue" name="lfe_show_venue" type="checkbox"'.$show_venue_checked.' /></label></p>';
		echo '<p style="text-align:right;"><label for="lfe_link_venue">' . __('Link to venues on Last.FM:') . ' <input id="lfe_link_venue" name="lfe_link_venue" type="checkbox"'.$link_venue_checked.' /></label></p>';
		echo '<input type="hidden" id="lfe_submit" name="lfe_submit" value="1" />';
	}

	// This registers our widget so it appears with the other available
	// widgets and can be dragged and dropped into any active sidebars.
	register_sidebar_widget(array('Last.FM Events', 'widgets'), 'lfe_widget');

	// This registers our optional widget control form. Because of this
	// our widget will have a button that reveals a 300x100 pixel form.
	register_widget_control(array('Last.FM Events', 'widgets'), 'lfe_widget_ctrl', 300, 180);
}

// Run our code later in case this loads prior to any required plugins.
add_action('widgets_init', 'lfe_widget_init');

?>
