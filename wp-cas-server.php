<?php
/*
Plugin Name: Cassava: A WordPress CAS Server
Version: 1.0.1
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
 * @package \WPCASServerPlugin
 * @version 1.0.1
 */

require_once( dirname( __FILE__ ) . '/includes/WPCASServer.php' );
require_once( dirname( __FILE__ ) . '/includes/WPCASServerPluginAdmin.php' );

if (!class_exists( 'WPCASServerPlugin' )) {

    /**
     * Main plugin class.
     * 
     * @since 1.0.0
     */
    class WPCASServerPlugin {

        /**
         * Plugin version.
         */
        const VERSION = '1.0.1';

        /**
         * Plugin slug.
         */
        const SLUG = 'wp-cas-server';

        /**
         * Default endpoint slug.
         */
        const ENDPOINT_SLUG = 'wp-cas';

        /**
         * Plugin options key.
         */
        const OPTIONS_KEY = 'wp_cas_server';

        /**
         * Plugin file.
         */
        const FILE = 'wp-cas-server/wp-cas-server.php';

        /**
         * Query variable used to pass the requested CAS route.
         */
        const QUERY_VAR_ROUTE = 'cas_route';

        /**
         * Transient prefix for ticket reuse validation.
         */
        const TRANSIENT_PREFIX = 'cas_';

        /**
         * CAS server instance.
         * @var WPCASServer
         */
        protected $server;

        /**
         * CAS server plugin admin instance.
         * @var WPCASServerAdmin
         */
        protected $admin;

        /**
         * Default plugin options.
         * @var array
         */
        private $default_options = array(
            /**
             * CAS server endpoint path fragment (e.g. `<scheme>://<host>/wp-cas/login`).
             */
            'endpoint_slug'      => self::ENDPOINT_SLUG,

            /**
             * Service ticket expiration, in seconds [0..300].
             */
            'expiration'         => 30,

            /**
             * `allow_ticket_reuse` exists as a workaround for potential issues with
             * WordPress's Transients API.
             */
            'allow_ticket_reuse' => false,

            /**
             * @todo Allow requests from these service URIs only.
             */
            'allowed_services'   => array(),

            /**
             * User attributes to return on a successful `/serviceValidate` response.
             */
            'attributes'         => array(),
            );

        /**
         * WP CAS Server plugin constructor.
         * 
         * @param ICASServer $server CAS server instance.
         * 
         * @uses is_admin()
         * @uses register_activation_hook()
         * @uses register_deactivation_hook()
         * @uses add_action()
         */
        public function __construct ( ICASServer $server ) {
            $this->server = $server;

            if (is_admin()) {
                $this->admin  = new WPCASServerPluginAdmin();
            }

            register_activation_hook( __FILE__, array( $this, 'activation' ) );
            register_deactivation_hook( __FILE__, array( $this, 'deactivation' ) );

            add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
        }

        /**
         * Plugin activation callback.
         * 
         * @param bool $network_wide Plugin is activated for the entire network.
         * 
         * @uses flush_rewrite_rules()
         * @uses is_multisite()
         * @uses restore_current_blog()
         * @uses switch_to_blog()
         * @uses wp_get_sites()
         */
        public function activation ( $network_wide ) {
            if (function_exists( 'is_multisite' ) && is_multisite() && $network_wide) {
                $sites = wp_get_sites();
                foreach ($sites as $site) {
                    switch_to_blog( $site['blog_id'] );
                    $this->_add_rewrite_rules();
                    flush_rewrite_rules();
                }
                restore_current_blog();
            }
            else
            {
                $this->_add_rewrite_rules();
                flush_rewrite_rules();
            }
        }

        /**
         * Plugin deactivation callback to flush rewrite rules.
         * 
         * @param bool $network_wide Plugin is activated for the entire network.
         * 
         * @uses flush_rewrite_rules()
         * @uses is_multisite()
         * @uses restore_current_blog()
         * @uses switch_to_blog()
         * @uses wp_get_sites()
         */
        public function deactivation ( $network_wide ) {
            if (function_exists( 'is_multisite' ) && is_multisite() && $network_wide) {
                $sites = wp_get_sites();
                foreach ($sites as $site) {
                    switch_to_blog( $site['blog_id'] );
                    flush_rewrite_rules();
                }
                restore_current_blog();
            }
            else
            {
                flush_rewrite_rules();
            }
        }

        /**
         * Plugin loading callback.
         * 
         * @uses add_action()
         * @uses add_filter()
         */
        public function plugins_loaded () {
            add_action( 'init'                  , array( $this, 'init' ) );
            add_action( 'template_redirect'     , array( $this, 'template_redirect' ), -100 );
            add_filter( 'allowed_redirect_hosts', array( $this, 'allowed_redirect_hosts' ) );
        }

        /**
         * Plugin initialization callback.
         * 
         * @global WP $wp
         * 
         * @uses apply_filters()
         * @uses load_plugin_textdomain()
         * @uses load_textdomain()
         * @uses trailingslashit()
         */
        public function init () {
            global $wp;

            $domain = self::SLUG;
            $locale = apply_filters( 'plugin_locale', get_locale(), 'wp-cas-server' );

            load_textdomain( $domain, trailingslashit( WP_LANG_DIR ) . $domain . '/' . $domain . '-' . $locale . '.mo' );
            load_plugin_textdomain( $domain, FALSE, basename( plugin_dir_path( dirname( __FILE__ ) ) ) . '/languages/' );

            if (!get_option( self::OPTIONS_KEY )) {
                $this->_set_default_options();
            }

            $wp->add_query_var( self::QUERY_VAR_ROUTE );
            $this->_add_rewrite_rules();
        }

        /**
         * Serve the CAS request and stop.
         * 
         * @global WP $wp
         */
        public function template_redirect () {
            global $wp;

            // Abort unless processing a CAS request:
            if (empty( $wp->query_vars[self::QUERY_VAR_ROUTE] )) {
                return;
            }

            echo $this->server->handleRequest( $wp->query_vars[self::QUERY_VAR_ROUTE] );

            exit;
        }

        /**
         * Callback to filter the hosts WordPress allows redirecting to.
         * 
         * @param  array $allowed List of valid redirection target hosts.
         * 
         * @return array          Filtered list of valid redirection target hosts.
         */
        public function allowed_redirect_hosts ( $allowed = array() ) {

            foreach ((array) self::get_option( 'allowed_services' ) as $uri) {
                // `allowed_redirect_hosts` returns a list of **hosts**, not URIs:
                $host = parse_url( $uri, PHP_URL_HOST );

                if (!empty( $host )) {
                    $allowed[] = $host;
                }
            }

            return $allowed;
        }

        /**
         * Get plugin option by key.
         * 
         * @param  string $key     Plugin option key to return.
         * @param  mixed  $default Option value to return if `$key` is not found.
         * 
         * @return mixed           Plugin option value.
         * 
         * @uses get_option()
         */
        public static function get_option ( $key = null, $default = false ) {
            $options = get_option( self::OPTIONS_KEY );
            return isset( $options[$key] ) ? $options[$key] : $default;
        }

        /**
         * Set plugin option by key.
         * 
         * @param string $key   Plugin option key to set.
         * @param mixed  $value Plugin option value to set.
         */
        public static function set_option ( $key, $value ) {
            if (!isset( $key )) return;
            $options = get_option( self::OPTIONS_KEY );

            if (!isset( $value )) {
                unset( $options[$key] );
            }
            else
            {
                $options[$key] = $value;
            }

            update_option( self::OPTIONS_KEY, $options );
        }

        /**
         * Update the plugin options in the database.
         * 
         * @param  array  $updated_options  Updated options to set (will be merged with existing options).
         * 
         * @uses get_option()
         * @uses update_option()
         */
        private function _set_default_options ( $updated_options = array() ) {
            $options = get_option( self::OPTIONS_KEY, $this->default_options );
            $options = array_merge( (array) $options, (array) $updated_options );
            update_option( self::OPTIONS_KEY, $options );
        }

        /**
         * Register new rewrite rules for the CAS server URIs.
         * 
         * @uses add_rewrite_endpoint()
         */
        private function _add_rewrite_rules () {

            /**
             * Enforce SSL
             */
            if (!is_ssl()) {
                return;
            }

            $path = self::get_option( 'endpoint_slug' );

            if (empty( $path )) {
                $path = self::ENDPOINT_SLUG;
            }

            add_rewrite_endpoint( $path, EP_ALL, self::QUERY_VAR_ROUTE );
        }

    }

    $GLOBALS[WPCASServerPlugin::SLUG] = new WPCASServerPlugin( new WPCASServer );

} // !class_exists( 'WPCASServerPlugin' )
