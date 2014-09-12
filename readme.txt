=== Plugin Name ===
Contributors: ryac
Author: Invoke Media
Donate link: http://www.redcross.ca/donate
Tags: social media, social feed aggregation
Requires at least: 3.0.1
Tested up to: 4.0
Stable tag: 1.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Aggregate social media content from Facebook, Twitter, YouTube, Vimeo, Instagram, and RSS Feeds into WordPress and use PHP or Ajax to retrieve.

== Description ==

The Social Media Aggregator will pull content from various social media channels and aggregate them into WordPress, which you can then add into your templates. The various channels include:

* Facebook
* Twitter
* YouTube
* Vimeo
* Instagram
* RSS Feeds

Content is pulled into a custom post type, tagged with the source type (Facebook, Twitter, etc.), and updated on a daily WP-cron schedule. You don't have to use all the channels, the settings page will allow you to choose which channel(s) you would like to use. The settings page also allows you to provide any access tokens and screen names that are required.

An options page allows you to manually fetch the content without having to wait for the daily cron to run. You can also reset the feeds and this will add all available content the next time the feeds are fetched, and not check for duplicate entries.

Displaying the content using a shortcode:

Most basic:
`[imsa]`

You can adjust the number of columns (1 - 9) and pass the source types as follows:
`[imsa cols=4 source_types='facebook,instagram,vimeo,youtube']`

If you need more control, you can grab the raw data in either PHP or making an Ajax call. Here are the examples:

*In PHP*

`$imsa->get_feeds();								// this will return the complete list, organized by source type`
`$imsa->get_feeds(array('facebook', 'youtube'));	// this will return only the channels you provide in an array, organized by source type. all available source types can be found below.`

*In Javascript*

Grabbing the data in the front-end closely follows the [WP Ajax](http://codex.wordpress.org/Ajax_in_Plugins) way.

There will be a global variable called IMSA that contains the URL to call when making Ajax calls.

Example:
`
var feeds = ['facebook', 'instagram']; // all available source types can be found below.

$.ajax({
	url: IMSA.ajaxurl,
	data: {
		type: 'GET', // must be the default type of GET
		action: 'get_feeds', // the method to call
		feeds: feeds // pass an array if you want to be more selective of which channel you want, remove property completely if you want all
	}
}).done (function (result) {
	console.log (result); // the result will contain an object called feeds, with data organized by their social channel
});
`

If you're not using Ajax to fetch the data, you can remove the global Javascript var to keep your HTML clean. Do this by adding `define('IMSA_LOAD_SCRIPTS', false);` into your wp-config.php file.

If you still want to use Ajax but only load the global Javascript var on specific pages, you can still add `define('IMSA_LOAD_SCRIPTS', false);` into your wp-config.php and then load the script by calling `$imsa->load_scripts();` on the specific page(s).

Available source types:

* `facebook`
* `twitter`
* `youtube`
* `vimeo`
* `instagram`
* `rss`

== Installation ==

1. Upload the `im-social-aggregator` folder to your `/wp-content/plugins/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Look for the "Social Content" menu on the sidebar, click on "Settings", and enable and add your access tokens to the social channel you want to aggregate.

== Frequently Asked Questions ==

= Once the social feeds are in WordPress, how do I use them? =

Use the shortcode [imsa] on your page or post.

== Screenshots ==

1. This is the settings page.

== Changelog ==

= 0.2 =
* First version of plugin submitted to the Plugins directory.

= 1.1 =
* Tested plugin with WordPress 4.0, updated version numbers to match stable tag.

= 1.2 =
* Only matching versions across SVN tags and plugin version.

== Upgrade Notice ==

= 0.2 =
First version of plugin submitted to the Plugins directory.

= 1.1 =
Tested plugin with WordPress 4.0, updated version numbers to match stable tag.

= 1.2 =
Only matching versions across SVN tags and plugin version.
