=== Nomads Connected  ===
Contributors: Frank Hoefer
Tags: maps, map, geo, geocoding, google, googlemaps, location, position, travel, route
Requires at least: 3.5
Tested up to: 3.5.1
Stable tag: 2.0.1

Interface to the Nomads Connected social travel network. Add live maps of your route (or parts of it) to your posts and pages.

== Description ==
Nomads Connected is a free service for travelers where you can comfortably enter your planned route and keep it up to date when on the road.
(And it does a few other cool things like free GEO classifieds.) 

Further information at http://www.nomadsconnected.com/.

The WordPress plugin lets you add Google maps of your route (or parts of it) to your posts and pages. 


= Key Features =

* Display your route just by entering start and end date.
* Real-time update of your Google map when you edit your route in Nomads Connected.
* Use different colors for past and future routes.
* Auto Connect points that are not more than X days apart.
* Set default height for maps.
* Select your preferred map type.
* Select wether to show your map at the top or bottom of posts (or not at all).
* Automatic map scaling depending on current content.

== Installation ==

= Requirements =
* WordPress 3.5 or higher.
* PHP 5.2 or higher.
* cURL support activated in your PHP installation (standard on most web servers).
* A free Nomads Connected account (http://www.nomadsconnected.com/acces/register.html) with activated API key (http://www.nomadsconnected.com/settings/systemSettings.html).

= Steps =
1. Download the archive file and uncompress it.
2. Put the "nomads-connected" folder in "wp-content/plugins"
3. Enable in WordPress by visiting the "Plugins" menu and activating it.
4. Go to the Settings page in the admin and enter your Nomads Connected API Key.

(You need a free Nomads Connected account to use this plugin. 

= Upgrade Notice =

If upgrading from a previous version of the plugin:

1. If you are not performing an automatic upgrade, deactivate and reactivate the plugin to ensure any new features are correctly installed.
2. Visit the settings page after installing the plugin to customise any new options.

== Changelog ==

Note: Changes in the third digit are bugfix releases without new functionality.

= Nomads Connected 2.0.1 =

Bugfix: Plugin didn't work for new installs.

= Nomads Connected 2.0 =

The plugin now uses the Google API v3.

= Nomads Connected 1.2.9 =

Bugfix: Media Upload in the post editor didn't work

= Nomads Connected 1.2.8 =

Now works with WordPress 3.5+ (datepicker problem fixed)

= Nomads Connected 1.2.7 =

Bugfix: Markers were not properly connected in some cases.

= Nomads Connected 1.2.6 =

Just a clarification in the README.txt. If you are using version 1.2.5 you don't need to upgrade.

= Nomads Connected 1.2.5 =

Bugfix: Set timeout to 5 seconds, in case the Nomads Connected server is unreachable.

= Nomads Connected 1.2.4 =

Bugfix: The route could not be displayed if any one waypoint name contained one or more single quotes.

= Nomads Connected 1.2.3 =

Fixed a bug in the auto-connect feature. Sometimes lines were drawn although interval between two points was too big.

= Nomads Connected 1.2 =

New Settings:

* Marker color.
* Connect points that are not more than X days apart.
* Line color and thickness.

= Nomads Connected 1.1 =

* New settings for map type and height.
* Different line color for past and future routes.

= Nomads Connected 1.0 =

First version.

== Languages ==

Nomads Connected is currently available in the following languages:

* English (default)
* German
