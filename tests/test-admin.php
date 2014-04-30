<?php
/**
 * @package WPCASServerPlugin
 * @subpackage Tests
 */

class WP_TestWPCASServerPluginAdmin extends WP_UnitTestCase {
    
    private $plugin;
    private $admin;

    /**
     * Setup a test method for the WP_TestWPCASServerPluginActions class.
     */
    function setUp () {
        parent::setUp();
        $this->plugin = $GLOBALS[WPCASServerPlugin::SLUG];
        $this->admin = new WPCASServerPluginAdmin;
    }

    /**
     * Finish a test method for the WP_TestWPCASServerPluginActions class.
     */
    function tearDown () {
        parent::tearDown();
        unset( $this->admin );
    }

    function test_admin_init () {
        $this->markTestIncomplete();
    }

}