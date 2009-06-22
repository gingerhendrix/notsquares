=== Last.FM Events ===
Contributors: simonwheatley
Donate link: http://www.simonwheatley.co.uk/wordpress-plugins/
Tags: lastfm, events, gigs, concerts, music, widget, hcal, ical, microformat
Requires at least: 2.3
Tested up to: 2.3
Stable tag: 1.1b

This plugin adds a widget to display your upcoming events (gigs) from Last.FM on your Wordpress blog (using the hCal Microformat).

== Description ==

This plugin adds a widget to display your upcoming events (gigs) from [Last.FM](http://last.fm/), with 
options for displaying full venue details, linking, etc. Events are displayed using valid 
[hCal](http://microformats.org/wiki/hcalendar) [Microformat](http://microformats.org/) code (makes no 
difference to the display, but plays nicer with the web in general).

This plugin is still in development, but is stable enough that I use it on my own blog. Once other
people have used it successfully for a while I'll feel happier labelling it stable.

Please feel free to try the plugin on previous versions of Wordpress, I think it may work on 2.2, maybe lower.

Please let me know how you get on.

== Change Log ==

1.1b
* BUG FIX: Corrected the time descriptions (previously it would state "in 3 months, 5 months", obviously wrong)

== Requests ==

I'm simply noting requests here, I've not necessarily looked into how possible any of these are or how much effort they might require.

* BUG REPORT: A lot of the timestamps for events aren't correct therefore, you see "495 months ago." because it shows a timestamp of 1969 for some reason even though the dates are correct on last.fm. Is there a way you could have it to just display the date instead of trying to calcuate the time until? (Reported by [Mike](http://www.deadlydesigns.com/))
* REQUEST: Would it be possible to have the link to last.fm events open in a new window so it will not load over top of the blog? (Requested by [Mike](http://www.deadlydesigns.com/))

== Installation ==

1. Upload `lfm_events.php` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Add the widget to a sidebar, and configure the options (at the least, you need to tell it your LastFM username)
