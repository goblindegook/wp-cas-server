<?php
/*
Plugin Name: WordPress CAS Server
Version: 0.1-alpha
Description: Provides authentication services based on Jasig CAS protocols.
Author: Luís Rodrigues
Author URI: http://goblindegook.net/
Plugin URI: https://github.com/goblindegook/wordpress-cas-server
Text Domain: wordpress-cas-server
Domain Path: /languages
*/
/**
 * WordPress CAS Server main plugin file.
 * @package WPCASServerPlugin
 */

require_once( dirname( __FILE__ ) . '/includes/WPCASServer.php' );

if (!class_exists( 'WPCASServerPlugin' )):

/**
 * Main plugin class.
 */
class WPCASServerPlugin {

    /**
     * Plugin version.
     */
    const VERSION = '0.1-alpha';

    /**
     * Plugin slug.
     */
    const SLUG = 'wordpress-cas-server';

    /**
     * Plugin options key.
     */
    const OPTIONS_KEY = 'wordpress_cas_server';

    /**
     * Plugin file.
     */
    const FILE = 'wordpress-cas-server/wordpress-cas-server.php';

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
     * Default plugin options.
     * @var array
     */
    private $default_options = array(
        /**
         * CAS server endpoint path fragment (e.g. `<scheme>://<host>/wp-cas/login`).
         */
        'path'               => 'wp-cas',

        /**
         * Service ticket expiration, in seconds [0..300].
         */
        'expiration'         => 30,

        /**
         * @todo Disable the server if SSL is not enabled on WordPress and reject
         * requests from services not operating over SSL.
         */
        'force_ssl'          => false,

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
     * WordPress CAS Server plugin constructor.
     */
    public function __construct ( ICASServer $server ) {
        register_activation_hook( __FILE__, array( $this, 'activation' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivation' ) );

        add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );

        $this->server = $server;
    }

    /**
     * Plugin activation callback.
     * 
     * @param bool $network_wide Plugin is activated for the entire network.
     */
    public function activation ( $network_wide ) {
        if (function_exists( 'is_multisite' ) && is_multisite() && $network_wide) {
            $sites = wp_get_sites();
            foreach ( $sites as $site ) {
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
     */
    public function deactivation ( $network_wide ) {
        if (function_exists( 'is_multisite' ) && is_multisite() && $network_wide) {
            $sites = wp_get_sites();
            foreach ( $sites as $site ) {
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
     */
    public function plugins_loaded () {
        add_action( 'init'                  , array( $this, 'init' ) );
        add_action( 'template_redirect'     , array( $this, 'template_redirect' ), -100 );
        add_filter( 'allowed_redirect_hosts', array( $this, 'allowed_redirect_hosts' ) );
    }

    /**
     * Plugin initialization callback.
     * 
     * @uses $wp
     */
    public function init () {
        global $wp;
        $wp->add_query_var( self::QUERY_VAR_ROUTE );
        $this->_update_options();
        $this->_add_rewrite_rules();
    }

    /**
     * Serve the CAS request and stop.
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
    static public function get_option ( $key = null, $default = false ) {
        $options = get_option( self::OPTIONS_KEY );
        return isset( $options[$key] ) ? $options[$key] : $default;
    }

    /**
     * Update the plugin options in the database.
     * 
     * @param  array  $updated_options  Updated options to set (will be merged with existing options).
     * 
     * @uses get_option()
     * @uses update_option()
     */
    private function _update_options ( $updated_options = array() ) {
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
        if (self::get_option( 'force_ssl' ) && !is_ssl()) {
            return;
        }

        $path = self::get_option( 'path' );
        add_rewrite_endpoint( $path, EP_ALL, self::QUERY_VAR_ROUTE );
    }

}

$GLOBALS[WPCASServerPlugin::SLUG] = new WPCASServerPlugin( new WPCASServer );

endif;