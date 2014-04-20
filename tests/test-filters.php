<?php
/**
 * @package WPCASServerPlugin
 * @subpackage WPCASServerPlugin_Tests
 */

class WP_TestWPCASServerPluginFilters extends WP_UnitTestCase {
    
    private $plugin;
    private $server;

    /**
     * Setup test suite for the CASServerPlugin class.
     */
    function setUp () {
        parent::setUp();
        $this->plugin = $GLOBALS[WPCASServerPlugin::SLUG];
        $this->server = new WPCASServer;
    }

    /**
     * Finish the test suite for the CASServerPlugin class.
     */
    function tearDown () {
        parent::tearDown();
        unset( $this->plugin );
    }

    /**
     * Test whether a WordPress filter is called for a given function and set of arguments.
     * @param  string  $label    Action label to test.
     * @param  mixed   $function String or array with the function to execute.
     * @param  array   $args     Ordered list of arguments to pass the function.
     * @return boolean           Whether the given filter was called.
     */
    private function _is_filter_called ( $label, $function, $args ) {
        $called = false;
        $handler = function ( $in ) use (&$called) {
            $called = true;
            return $in;
        };

        add_filter( $label, $handler );
        ob_start();
        call_user_func_array( $function, $args );
        ob_end_clean();
        remove_filter( $label, $handler );

        return $called;
    }

    function test_cas_enabled () {
        $filter   = 'cas_enabled';
        $function = array( $this->server, 'handleRequest' );
        $args     = array( '/invalid-uri' );
        
        $this->assertTrue( $this->_is_filter_called( $filter, $function, $args ),
            "Filter callback for '$filter' is called." );
    }

    function test_cas_server_routes () {
        $filter   = 'cas_server_routes';
        $function = array( $this->server, 'routes' );
        $args     = array();
        
        $this->assertTrue( $this->_is_filter_called( $filter, $function, $args ),
            "Filter callback for '$filter' is called." );
    }

    function test_cas_server_response () {
        $filter   = 'cas_server_response';
        $function = array( $this->server, 'handleRequest' );
        $args     = array( '/invalid-uri' );
        
        $this->assertTrue( $this->_is_filter_called( $filter, $function, $args ),
            "Filter callback for '$filter' is called." );
    }

    function test_cas_server_dispatch_callback () {
        $filter   = 'cas_server_dispatch_callback';
        $function = array( $this->server, 'handleRequest' );
        $args     = array( '/login' );
        
        /*
        $this->assertTrue( $this->_is_filter_called( $filter, $function, $args ),
            "Filter callback for '$filter' is called." );
        */
        $this->markTestIncomplete( "Filter callback for '$filter' not tested until I find a way to get around exit and die()." );
    }

    function test_cas_server_dispatch_args () {
        $filter   = 'cas_server_dispatch_args';
        $function = array( $this->server, 'handleRequest' );
        $args     = array( '/login' );
        
        /*
        $this->assertTrue( $this->_is_filter_called( $filter, $function, $args ),
            "Filter callback for '$filter' is called." );
        */
        $this->markTestIncomplete( "Filter callback for '$filter' not tested until I find a way to get around exit and die()." );
    }

    function test_cas_server_login_args () {
        $filter   = 'cas_server_login_args';
        $function = array( $this->server, 'handleRequest' );
        $args     = array( '/login' );
        
        /*
        $this->assertTrue( $this->_is_filter_called( $filter, $function, $args ),
            "Filter callback for '$filter' is called." );
        */
        $this->markTestIncomplete( "Filter callback for '$filter' not tested until I find a way to get around exit and die()." );
    }

    function test_cas_server_redirect_service () {
        $filter   = 'cas_server_redirect_service';
        $function = array( $this->server, 'handleRequest' );
        $args     = array( '/login' );
        
        /*
        $this->assertTrue( $this->_is_filter_called( $filter, $function, $args ),
            "Filter callback for '$filter' is called." );
        */
        $this->markTestIncomplete( "Filter callback for '$filter' not tested until I find a way to get around exit and die()." );
    }

    function test_cas_server_custom_auth_uri () {
        $filter   = 'cas_server_custom_auth_uri';
        $function = array( $this->server, 'handleRequest' );
        $args     = array( '/login' );
        
        /*
        $this->assertTrue( $this->_is_filter_called( $filter, $function, $args ),
            "Filter callback for '$filter' is called." );
        */
        $this->markTestIncomplete( "Filter callback for '$filter' not tested until I find a way to get around exit and die()." );
    }

    function test_cas_server_ticket_expiration () {
        $filter   = 'cas_server_ticket_expiration';
        $function = array( $this->server, 'handleRequest' );
        $args     = array( '/login' );
        
        /*
        $this->assertTrue( $this->_is_filter_called( $filter, $function, $args ),
            "Filter callback for '$filter' is called." );
        */
        $this->markTestIncomplete( "Filter callback for '$filter' not tested until I find a way to get around exit and die()." );
    }

}
