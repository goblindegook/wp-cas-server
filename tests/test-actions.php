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
    private function _assertActionIsCalled ( $label, $function, $args ) {
        $mock = $this->getMock( 'stdClass', array( 'action' ) );
        $mock->expects( $this->once() )->method( 'action' );

        add_filter( $label, array( $mock, 'action' ) );
        ob_start();
        call_user_func_array( $function, $args );
        ob_end_clean();
        remove_filter( $label, array( $mock, 'action' ) );

        unset( $mock );
    }

    /**
     * @group action
     */
    function test_cas_server_before_request () {
        $action   = 'cas_server_before_request';
        $function = array( $this->server, 'handleRequest' );
        $args     = array( 'invalid-uri' );
        
        $this->_assertActionIsCalled( $action, $function, $args );
    }

    /**
     * @group action
     */
    function test_cas_server_after_request () {
        $action   = 'cas_server_after_request';
        $function = array( $this->server, 'handleRequest' );
        $args     = array( 'invalid-uri' );
        
        $this->_assertActionIsCalled( $action, $function, $args );
    }

    /**
     * @group action
     */
    function test_cas_server_error () {
        $action   = 'cas_server_error';
        $function = array( $this->server, 'handleRequest' );
        $args     = array( 'invalid-uri' );
        
        $this->_assertActionIsCalled( $action, $function, $args );
    }

    /**
     * @group action
     * @todo
     */
    function test_cas_server_validation_success () {
        $action   = 'cas_server_validation_success';
        $function = array( $this->server, 'handleRequest' );
        $args     = array( 'serviceValidate' );

        $user_id  = $this->factory->user->create();

        wp_set_current_user( $user_id );

        // TODO: Generate a service ticket to validate.
        
        $this->markTestIncomplete();
        
        $this->_assertActionIsCalled( $action, $function, $args );
    }

    /**
     * @group action
     * @todo
     */
    function test_cas_server_validation_error () {
        $action   = 'cas_server_validation_error';
        $function = array( $this->server, 'handleRequest' );

        foreach (array( 'proxy', 'proxyValidate', 'serviceValidate' ) as $endpoint) {
            $this->_assertActionIsCalled( $action, $function, array( $endpoint ) );
        }

    }

}