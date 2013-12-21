=== Events Manager and WPML Compatibility ===
Contributors: netweblogic
Donate link: http://wp-events-plugin.com
Tags: events, multilingual, wpml, event, event registration, event calendar, events calendar, event management
Requires at least: 3.3
Tested up to: 3.8
Stable tag: 0.3
License: GPLv2

Integrates the Events Manager and WPML plugins together to provide a smoother multilingual experience (Requires Events Manager and WPML)

== Description ==

This plugin helps make [Events Manager](http://wordpress.org/extend/plugins/events-manager/) and [WPML](http://wpml.org) work better together by improving various issues:

* Detects translated pages of specific EM pages (assigned in Events > Settings > Pages) and displays relevant language content
* Event translations now share relevant information across all translations, including 
 * Event Times
 * Location Information
  * If translations for the location exist, translated events will show/link to location of the same language, if not the original location translation.
 * Bookings and Booking Forms
 * If you delete an event that is the originally translated event, booking and other meta info is transferred to default language or next available language translation.
* Custom texts, emails and formats can now be customized for each language.
 
Requires Events Manager 5.3.3 or higher

= Special Installation Steps =
Please ensure that WPML and EM 5.3.3 or higher are installed BEFORE activating this plugin.

When setting up EM and WPML, you should create translated versions of the event, location, category, tag, etc. pages assigned in Events > Settings > Pages of your admin area. Duplicating them using WPML is enough.
 
= Nuances = 
WPML and Events Manager are both complex plugins and there are some inevitable nuances and features that currently won't work and more time is needed to find appropriate solutions:

* Event/Location Attributes (solution on the way)
 * Currently these aren't copied over to translations, so you need to recreate custom attributes for each translations.
 * Attributes aren't translatable
* Recurring events (to be fixed after event/location attributes, no ETA yet)
 * Recurring Events can't be translated when editing the recurrence template, they must be done one by one i.e. at single event level
 * Recurring events are disabled by default due to the above
* Location Searching
 * currently autocompleter forces searches for current languages, we may want to change this in the future to search all languages but give precedence to showing the translated version if available
* Taxonomies
 * Taxonomy translation links aren't going to show in the language switcher unless WPML decides to make SitePress::$wp_query a public property
 * Translation switcher options aren't all going to be reflected in taxonomies as $teplate_args isn't passed to filter icl_ls_languages
* Ticket names aren't translatable, they remain the same across all translations
* MultiSite
 * Event Manager's MultiSite Global Tables Mode will not work as expected, listing events and locations from other sites will not return the correct items (if at all). This is due to the architecture of WPML vs. EM when in Global Tables Mode.
* Custom Booking Forms
 * Pro Booking forms currently aren't translatable, the original ticket names and booking form fields are used.
* RSS Feeds not translated
* iCal feeds not translated
 
There is a big combination of things to test, therefore many combinations may have been missed which result in unexpected behaviour. Please let us know of any other nuances you come across and we'll do our best to fix them as time permits.
 
== Installation ==

This plugin requires WPML and Events Manager to be installed BEFORE installing this plugin.

Events Manager WPML works like any standard Wordpress plugin. [See the Codex for installation instructions](http://codex.wordpress.org/Managing_Plugins#Installing_Plugins).

== Changelog ==
= 0.3 =
* fixed version update checks and table installations on MultiSite causing event submission issues
* fixed attribute translations not being editable

= 0.2 =
* fixed PHP warnings due to non-static function declarations
* fixed unexpected behaviour when checking translated EM assigned pages

= 0.1 =
* first release