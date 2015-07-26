<?php
/*
Plugin Name: Cassava: A WordPress CAS Server
Version: 1.2.2
Description: Provides authentication services based on the Jasig CAS protocol.
Author: Luís Rodrigues
Author URI: http://goblindegook.net/
Plugin URI: https://goblindegook.github.io/wp-cas-server
Github URI: https://github.com/goblindegook/wp-cas-server
Text Domain: wp-cas-server
Domain Path: /languages
*/

/*  Copyright 2014  Luís Rodrigues  <hello@goblindegook.net>

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/**
 * WP CAS Server main plugin file.
 *
 * @version 1.2.0
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

require_once dirname( __FILE__ ) . '/vendor/autoload.php';

$GLOBALS[ Cassava\Plugin::SLUG ] = new \Cassava\Plugin( new \Cassava\CAS\Server );
$GLOBALS[ Cassava\Plugin::SLUG ]->ready();
