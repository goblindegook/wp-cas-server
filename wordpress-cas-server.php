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

require_once( dirname( __FILE__ ) . '/inc/WPCASServer.php' );

if (!class_exists( 'WPCASServerPlugin' )):

class WPCASServerPlugin {

    const CAS_NS             = 'http://www.yale.edu/tp/cas';

    const ERROR_INTERNAL_ERROR  = 'INTERNAL_ERROR';
    const ERROR_INVALID_REQUEST = 'INVALID_REQUEST';
    const ERROR_INVALID_SERVICE = 'INVALID_SERVICE';
    const ERROR_INVALID_TICKET  = 'INVALID_TICKET';
    const ERROR_BAD_PGT         = 'BAD_PGT';

    const TYPE_PGT              = 'PGT';
    const TYPE_PGTIOU           = 'PGTIOU';
    const TYPE_PT               = 'PT';
    const TYPE_ST               = 'ST';
    const TYPE_TGC              = 'TGC';

    /**
     * CAS service URI prefix.
     * @var string
     */
    public $cas_path = 'wp-cas';

    /**
     * XML response.
     * @var DOMDocument
     */
    protected $response;

    /**
     * WordPress CAS Server plugin constructor.
     */
    public function __construct () {
        register_activation_hook( __FILE__, array( $this, 'activation' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivation' ) );

        add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );

        $this->response = new DOMDocument( '1.0', get_option( 'blog_charset' ) );
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
        $wp->add_query_var( 'cas_route' );
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
        if (empty( $wp->query_vars['cas_route'] )) {
            return;
        }

        define( 'CAS_REQUEST', true );

        do_action( 'cas_server_before_request' );

        $this->serve_request( $wp->query_vars['cas_route'] );

        do_action( 'cas_server_after_request' );

        die();
    }

    public function allowed_redirect_hosts ( $allowed ) {
        // TODO: Allow redirecting to the following hosts on logout
        return $allowed;
    }

    protected function register_rewrites () {
        add_rewrite_rule( '^' . $this->cas_path . '/?$'  , 'index.php?cas_route=/'          , 'top' );
        add_rewrite_rule( '^' . $this->cas_path . '(.*)?', 'index.php?cas_route=$matches[1]', 'top' );
    }

    //
    // CAS Server Methods
    //

    /**
     * Get routes supported by the CAS server and the callbacks to be invoked.
     * 
     * - /login
     * - /logout
     * - /proxy
     * - /proxyValidate
     * - /serviceValidate
     * - /validate
     * 
     * @return array Array containing supported routes as keys and their callbacks as values.
     */
    protected function get_routes () {

        $cas_routes = array(
            '/login/?'            => array( $this, 'cas_login' ),
            '/logout/?'           => array( $this, 'cas_logout' ),
            '/proxy/?'            => array( $this, 'cas_proxy' ),
            '/proxyValidate/?'    => array( $this, 'cas_proxy_validate' ),
            '/serviceValidate/?'  => array( $this, 'cas_service_validate' ),
            '/validate/?'         => array( $this, 'cas_validate' ),
            );

        return apply_filters( 'cas_server_get_routes', $cas_routes );
    }

    /**
     * [serve_request description]
     * 
     * @param  string $path CAS request URI.
     * 
     * @return void
     */
    public function serve_request ( $path ) {
        $this->set_response_header( 'Pragma'         , 'no-cache' );
        $this->set_response_header( 'Cache-Control'  , 'no-store' );
        $this->set_response_header( 'Expires'        , gmdate( 'D, d M Y H:i:s T' ) );

        if (empty( $path )) {
            $path = isset( $_SERVER['PATH_INFO'] ) ? $_SERVER['PATH_INFO'] : '/';
        }

        $result = $this->dispatch( $path );

        if (is_wp_error( $result )) {
            echo $this->cas_xml_response( $this->cas_xml_error( $result ) );
        }
        else {
            echo $result;
        }

    }

    /**
     * TODO
     * 
     * @return (string|WP_Error) Service response string or WordPress error.
     */
    public function dispatch ( $path ) {

        $enabled = apply_filters( 'cas_enabled', true );

        if (!$enabled) {
            return new WP_Error( 'authenticationFailure',
                __('The CAS server is disabled.', 'wordpress-cas-server'),
                array( 'code' => self::ERROR_INTERNAL_ERROR )
                );
        }

        foreach ($this->get_routes() as $route => $callback) {

            $match = preg_match( '@^' . $route . '$@', $path );

            if (!$match) {
                continue;
            }

            $callback = apply_filters( 'cas_server_dispatch_callback', $callback );

            if (!is_callable( $callback )) {
                return new WP_Error( 'authenticationFailure',
                    __('The handler for the route is invalid.', 'wordpress-cas-server'),
                    array( 'code' => self::ERROR_INTERNAL_ERROR )
                    );
            }

            $args = $_GET;

            $args = apply_filters( 'cas_server_dispatch_args', $args, $callback );

            // Allow plugins to halt the request via this filter:
            if (is_wp_error( $args )) {
                return $args;
            }

            return call_user_func( $callback, $args );
        }

        return new WP_Error( 'authenticationFailure',
            __( 'The server does not support the method requested.', 'wordpress-cas-server' ),
            array( 'code' => self::ERROR_INVALID_REQUEST )
            );
    }

    /**
     * Sets an HTTP response header.
     * 
     * @param string $key   Header key.
     * @param string $value Header value.
     */
    protected function set_response_header ( $key, $value ) {
        header( sprintf( '%s: %s', $key, $value ) );
    }

    /**
     * Wrap an XML CAS response and output it as a string.
     * 
     * @param DOMNode $response 
     * @return string
     */
    protected function cas_xml_response ( $response ) {
        $this->set_response_header( 'Content-Type'   , 'text/xml; charset=' . get_option( 'blog_charset' ) );

        $root = $this->response->createElementNS( self::CAS_NS, 'cas:serviceResponse' );
        $root->appendChild( $response );
        $this->response->appendChild($root);

        return $this->response->saveXML();
    }

    /**
     * Error response.
     * 
     * @param WP_Error $wp_error Error object.
     * @return SimpleXMLElement CAS error.
     */
    protected function cas_xml_error ( $error ) {
        do_action( 'cas_server_error', $error );

        foreach (array( 'authenticationFailure', 'proxyFailure' ) as $type) {
            if (!empty( $error->errors[$type] )) {
                $element = $this->response->createElementNS( self::CAS_NS, "cas:$type", implode( "\n", $error->errors[$type] ) );
                $element->setAttribute( "code", $error->error_data[$type]['code'] );
                return $element;
            }
        }

        $element = $this->response->createElementNS( self::CAS_NS, "cas:authenticationFailure", __( 'Unknown error', 'wordpress-cas-server' ) );
        $element->setAttribute( "code", self::ERROR_INTERNAL_ERROR );
        return $element;
    }

    //
    // CAS Server Protocol Methods
    //

    /**
     * [cas_login description]
     * 
     * @param array $args Request arguments.
     * 
     * @return string [description]
     */
    protected function cas_login ( $args ) {

        $renew   = isset( $args['renew'] )   && 'true' === $args['renew'];
        $gateway = isset( $args['gateway'] ) && 'true' === $args['gateway'];
        $service = sanitize_url( $args['service'] );

        if ($renew) {
            wp_logout();

            $url = '//' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            $url = remove_query_arg( 'renew', $url );

            wp_redirect( $url );
            exit;
        }

        if (false && !is_user_logged_in()) {
            if ($gateway && !empty( $service )) {
                wp_redirect( $service );
                exit;
            }
            else
            {
                auth_redirect();
                exit;
            }
        }

        $user = get_current_user();
        $user = get_user_by( 'id', 1 );

        $ticket = $this->create_ticket( $user, self::TYPE_ST );
        $ticket = apply_filters( 'cas_server_ticket', $ticket, self::TYPE_ST, $user );

        if (!empty( $service )) {
            $service = add_query_arg( 'ticket', $ticket, $service );
            $service = apply_filters( 'cas_server_service', $service, $user );
            wp_redirect( $service );
            exit;
        }

        wp_redirect( get_option( 'home' ) );
        exit;
    }

    private function create_ticket( $user, $type = self::TYPE_ST ) {
        return $type . '-' . urlencode( str_rot13( wp_generate_auth_cookie( $user->ID, time() + 15, 'auth' ) ) );
    }

    /**
     * [cas_logout description]
     * 
     * @return [type] [description]
     */
    protected function cas_logout ( $args ) {
        $service = sanitize_url( $args['service'] );
        session_start();
        session_unset();
        session_destroy();
        wp_logout();
        wp_redirect( !empty( $service ) ? $service : get_option( 'home' ) );
        exit;
    }

    /**
     * [cas_proxy description]
     * 
     * @return [type] [description]
     */
    protected function cas_proxy ( $args ) {
    }

    /**
     * [cas_proxy_validate description]
     * 
     * @return [type] [description]
     */
    protected function cas_proxy_validate ( $args ) {
    }

    /**
     * [cas_service_validate description]
     * 
     * @return [type] [description]
     */
    protected function cas_service_validate ( $args ) {
    }

    /**
     * [cas_validate description]
     * 
     * @return [type] [description]
     */
    protected function cas_validate ( $args ) {
    }

}

new WPCASServerPlugin;

endif;