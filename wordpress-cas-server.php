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
 * @package WPCASServerPlugin
 * @subpackage WPCASServerPlugin
 */

require_once( dirname( __FILE__ ) . '/inc/WPCASServer.php' );

if (!class_exists( 'WPCASServerPlugin' )):

class WPCASServerPlugin {

    const SLUG = 'wordpress-cas-server';

    const FILE = 'wordpress-cas-server/wordpress-cas-server.php';

    const QUERY_VAR_ROUTE = 'cas_route';

    /**
     * CAS service URI prefix.
     * @var string
     */
    public $cas_path = 'wp-cas';

    /**
     * CAS server instance.
     * @var WPCASServer
     */
    protected $server;

    /**
     * WordPress CAS Server plugin constructor.
     */
    public function __construct ( $server ) {
        register_activation_hook( __FILE__, array( $this, 'activation' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivation' ) );

        add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );

        $this->server = $server;
    }

    /**
     * Plugin activation callback.
     * 
     * @return void
     */
    public function activation ( $network_wide ) {
        if (function_exists( 'is_multisite' ) && is_multisite() && $network_wide) {
            $sites = wp_get_sites();
            foreach ( $sites as $site ) {
                switch_to_blog( $site['blog_id'] );
                $this->register_rewrites();
                flush_rewrite_rules();
            }
            restore_current_blog();
        }
        else
        {
            $this->register_rewrites();
            flush_rewrite_rules();
        }
    }

    /**
     * Plugin deactivation callback to flush rewrite rules.
     * 
     * @return void
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
     * 
     * @return void
     */
    public function plugins_loaded () {
        add_action( 'init'                  , array( $this, 'init' ) );
        add_action( 'template_redirect'     , array( $this, 'template_redirect' ), -100 );
        add_filter( 'allowed_redirect_hosts', array( $this, 'allowed_redirect_hosts' ) );
    }

    /**
     * Plugin initialization callback.
     * @return void
     * 
     * @uses $wp
     */
    public function init () {
        global $wp;
        $wp->add_query_var( self::QUERY_VAR_ROUTE );
        $this->register_rewrites();
    }

    /**
     * Serve the CAS request and stop.
     * 
     * @return void
     */
    public function template_redirect () {
        global $wp;

        // Abort unless processing a CAS request:
        if (empty( $wp->query_vars[self::QUERY_VAR_ROUTE] )) {
            return;
        }

        $this->server->handleRequest( $wp->query_vars[self::QUERY_VAR_ROUTE] );

        die();
    }

    public function allowed_redirect_hosts ( $allowed ) {
        // TODO: Allow redirecting to a list of hosts on logout.
        return $allowed;
    }

    protected function register_rewrites () {
        add_rewrite_rule( '^' . $this->cas_path . '/?$'  , 'index.php?' . self::QUERY_VAR_ROUTE . '=/'          , 'top' );
        add_rewrite_rule( '^' . $this->cas_path . '(.*)?', 'index.php?' . self::QUERY_VAR_ROUTE . '=$matches[1]', 'top' );
    }

}

$GLOBALS[WPCASServerPlugin::SLUG] = new WPCASServerPlugin( new WPCASServer );

endif;