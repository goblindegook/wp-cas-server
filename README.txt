=== Plugin Name ===
Contributors: goblindegook
Tags: authentication, CAS, Central Authentication Service, single sign-on
Requires at least: 3.9
Tested up to: 3.9
Stable tag: trunk

Provides authentication services based on Jasig CAS protocols.

== Description ==

[![Build Status](https://travis-ci.org/goblindegook/wordpress-cas-server.svg?branch=master)](https://travis-ci.org/goblindegook/wordpress-cas-server)

This plugin allows WordPress to act as a single sign-on authenticator using versions 1.0 and 2.0 of the Central Authentication Service protocol.

That way, users on your WordPress install may be able to access different applications that support the CAS protocol while providing a single set of credentials, once only and without exposing the user's password.

The following URIs are provided:

* `/wp-cas/login`: TODO
* `/wp-cas/logout`: TODO
* `/wp-cas/proxy` [CAS 2.0]: TODO
* `/wp-cas/proxyValidate` [CAS 2.0]: TODO
* `/wp-cas/serviceValidate` [CAS 2.0]: TODO
* `/wp-cas/validate` [CAS 1.0]: TODO

== Installation ==

1. Upload `wordpress-cas-server` directory to the `/wp-content/plugins/` directory.
1. Activate the plugin under 'Plugins' in the WordPress administration panel.

== Frequently Asked Questions ==

= How does CAS work? =

The CAS protocol requires three entities to function: the user's web _browser_, a web _application_ requesting authentication, and a _CAS server_ as implemented by this plugin.

When a user accesses an application and attempts to authenticate to it, the application sends the user to the CAS server for validation. The CAS server will look for an active session or else explicitly request the user to insert their credentials.

Upon authenticating the user, the CAS server returns the user to the application they came from along with a security ticket.

Behind the scenes, the application then contacts the CAS server over a secure connection to independently verify that the security ticket is valid.  The CAS server responds with information about the user's status, confirming they are who they claim to be.

= Where can I read about the CAS protocol specification? =

You may peruse the CAS 1.0 and 2.0 protocol specifications in complete detail at the [official project site](http://www.jasig.org/cas/protocol).

= What types of tickets does this plugin support? =

WordPress CAS Server sets and receives Ticket-Granting Cookies (TGC), Service Tickets (ST), Proxy-Granting Tickets (PGT), Proxy-Granting Ticket IOUs (PGTIOU) and Proxy Tickets (PT).

== Hooks ==

= Action: `cas_*` =

TODO

= Filter: `cas_*` =

TODO

== Changelog ==

= 1.0 =

* Initial release.