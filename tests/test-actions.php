<?php
/**
 * @package WPCASServerPlugin
 * @subpackage WPCASServerPlugin_Tests
 */

class WP_TestWPCASServerPluginActions extends WP_UnitTestCase {
    
    private $plugin;
    private $server;

    /**
     * Setup a test method for the WP_TestWPCASServerPluginActions class.
     */
    function setUp () {
        parent::setUp();
        $this->plugin = $GLOBALS[WPCASServerPlugin::SLUG];
        $this->server = new WPCASServer;
    }

    /**
     * Finish a test method for the WP_TestWPCASServerPluginActions class.
     */
    function tearDown () {
        parent::tearDown();
        unset( $this->plugin );
    }

    /**
     * Test whether a WordPress action is called for a given function and set of arguments.
     * @param  string  $label    Action label to test.
     * @param  mixed   $function String or array with the function to execute.
     * @param  array   $args     Ordered list of arguments to pass the function.
     * @return boolean           Whether the given action was called.
     */
    private function _is_action_called ( $label, $function, $args ) {
        $called = false;
        $handler = function () use (&$called) {
            $called = true;
        };

        add_action( $label, $handler );
        ob_start();
        call_user_func_array( $function, $args );
        ob_end_clean();
        remove_action( $label, $handler );

        return $called;
    }

    /**
     * @group action
     */
    function test_cas_server_before_request () {
        $action   = 'cas_server_before_request';
        $function = array( $this->server, 'handleRequest' );
        $args     = array( '/invalid-uri' );
        
        $this->assertTrue( $this->_is_action_called( $action, $function, $args ),
            "Action callback for '$action' is called." );

        // TODO: Test did_action()
    }

    /**
     * @group action
     */
    function test_cas_server_after_request () {
        $action   = 'cas_server_after_request';
        $function = array( $this->server, 'handleRequest' );
        $args     = array( '/invalid-uri' );
        
        $this->assertTrue( $this->_is_action_called( $action, $function, $args ),
            "Action callback for '$action' is called." );
    }

    /**
     * @group action
     */
    function test_cas_server_error () {
        $action   = 'cas_server_error';
        $function = array( $this->server, 'handleRequest' );
        $args     = array( '/invalid-uri' );
        
        $this->assertTrue( $this->_is_action_called( $action, $function, $args ),
            "Action callback for '$action' is called." );
    }

    /**
     * @group action
     * @todo
     */
    function test_cas_server_valid_ticket () {
        $this->markTestIncomplete( 'TODO' );
    }

    /**
     * @group action
     * @todo
     */
    function test_cas_server_invalid_ticket () {
        $this->markTestIncomplete( 'TODO' );
    }

}