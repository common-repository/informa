=== informa ===
Contributors: alchemetrics
Donate link: 
Tags: 
Requires at least: 3.3
Tested up to: 3.9.1
Stable tag: 0.6.5
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Using shortcodes informa DQS embeds dynamically created forms so you only ask people what you don't yet know. 

== Description ==

Wordpress integration to the <strong>informa<sup>&reg;</sup> DQS</strong> marketing database service.

The informa<sup>&reg;</sup> wordpress plugin allows anyone to use the full power of informa from <a href="http://www.alchemetrics.co.uk">Alchemetrics</a> to feed your marketing database. You can build up information over time about your visitors, even asking them one question at a time, and use informs&reg;'s powerful tools to segment and communicate to them.

You will need an account and database to use this plugin, which is designed for websites with at least 100,000 users. Contact <a href="http://www.alchemetrics.co.uk">Alchemetrics</a> for more information.

== Using the informa DQS shortcode ==

Once you have set-up the settings, you need to add a shortcode wherever you want a DQS Question set to appear.

For example, inserting <strong>&lt;dqs qs="demo"&gt;</strong> will add a Question Set named demo to your article, comment or page.

You can also include the following extra information in the shortcode to change the beaviour of DQS. 

The following options are available:

**key="session"** 
Tells informa to group answers together for the session, until the browser's window is closed normally. This takes precedence over the default key in settings.

**key="cookie"** 
Tells informa to remember the user's answers after the browser is closed (the expiry time is in settings).&nbsp;This takes precedence over the default key in settings.<br>

**defaults="question1=value1&amp;question2=val2"**
Pre-answers the specified questions if they have not been answered before. Useful if you want to pre-answer a hidden question to indicate what page they have answered.

**overrides="question1=value1&amp;question2=val2"**
Pre-answers the specified questions, overwriting any previously given answer. Also can be used if you want to pre-answer a hidden question to indicate what page they have answered.

**botlock="anyinvalidvalue"**
Simple bot blocker to stop bots from submitting forms. When the form is loaded, javascript attempts to find a hidden input field with a value "anyinvalidvalue", and changes it to "OK". 
By creating an informa DQS hidden Question with a validation forcing it to be 2 chars long, and a default value of "anyinvalidvalue" 
the form will only validate on the server when the value has been changed by the javascript (which catches most bots out).

**formAction="wordpress-url"**
If you want a form submission to go to a particular wordpress page, you can specify that as the parameter here<br>

== Using the widget ==
The informa widget should show up in the widget manager, and can be added to the sidebar or footer as desired. The only parameter to set-up is the Question Set name (which must be live). 
Some styling will be needed until some pre-packaged sub-themes have been developed. The following custom CSS was suitable to get a form displayed on the right of the footer:
<pre>DIV#informawidget-2.cell.widget.widget_informawidget {
  width: 40%;
  float: right;
  margin-right: 3%;
}</pre>


== Installation ==

1. Upload the `informa` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Enter the Informa Settings on the Settings page, with the parameters given to you by your account manager at Alchemetrics
1. See "Other Notes" for instructions on how to use DQS shortcodes and widgets.


== Frequently asked questions ==

= Can anyone use informa? =

Informa is designed for companies with over 100,000 users to enable dynamic and targeted marketing. License fees for the use of informa are required, however this plugin is free to use

== Screenshots ==

1. 
2. 

== Changelog ==

= 0.1 =
Initial Trial version. For development use only

= 0.2 =
First stable version. Removed debug output, contains main body styling only.

= 0.3 =
Included minor bug fixes, full return cycle tested.

= 0.4 =
Bug Fixes

= 0.5 =
CSS Improvements

= 0.6 =
Added Clickstream logging with configurable parameter in settings
Added Error messages in admin bar
Added page redirection based on DQS data
Added GET/POST API Puts
Fixed minor bugs
	Stopped autocompleting API identity fields

= 0.6.1 =
Fixed inevitable minor faults

= 0.6.2 =
Added first release of Widget functionality

= 0.6.3 =
Added getQSAnswer snippet functionality

= 0.6.4 =
Added botlock attribute to give a mechanism for stopping bit traffic

= 0.6.5 =
Added HTTP_USER_AGENT to click stream


== Upgrade notice ==

All users should upgrade to version 0.5 as a minimum.
