<?php
/*
Plugin Name: Cassava: A WordPress CAS Server
Version: 1.2.3
Description: Provides authentication services based on the Jasig CAS protocol.
Author: Luís Rodrigues
Author URI: http://goblindegook.com/
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

require_once dirname( __FILE__ ) . '/wp-requirements.php';

$wp_cas_server_requirements = new WP_Requirements(
	__( 'Cassava CAS Server', 'wp-cas-server' ),
    plugin_basename( __FILE__ ),
    array(
        'PHP'        => '5.3.2',
        'WordPress'  => '3.9.0',
        'Extensions' => array(
        	'libxml',
        ),
    )
);

if ( $wp_cas_server_requirements->pass() === false ) {
    $wp_cas_server_requirements->halt();
    return;
}

if ( file_exists( dirname( __FILE__ ) . '/vendor/autoload.php' ) ) {
	require_once dirname( __FILE__ ) . '/vendor/autoload.php';
}

require_once dirname( __FILE__ ) . '/plugin.php';
