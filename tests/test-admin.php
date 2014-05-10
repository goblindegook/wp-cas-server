<?php
/**
 * @package WPCASServerPlugin
 * @subpackage Tests
 */

/**
 * @coversDefaultClass WPCASServerPluginAdmin
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

    /**
     * @covers ::__construct
     */
    function test_construct () {
        $actions = array(
            'admin_init' => array(
                'priority'  => 10,
                'callback'  => array( $this->admin, 'admin_init' ),
                ),
            'admin_menu' => array(
                'priority'  => 10,
                'callback'  => array( $this->admin, 'admin_menu' ),
                ),
        );

        foreach ($actions as $tag => $action) {
            $this->assertEquals( $action['priority'], has_action( $tag, $action['callback'] ),
                "Admin has a '$tag' action callback." );
            $this->assertTrue( is_callable( $action['callback'] ),
                "'$tag' action callback is callable." );
        }
    }

    /**
     * @covers ::admin_init
     * @runInSeparateProcess
     */
    function test_admin_init () {
        $actions = array(
            'admin_notices' => array(
                'priority'  => 10,
                'callback'  => array( $this->admin, 'admin_notices' ),
                ),
        );

        $this->admin->admin_init();

        foreach ($actions as $tag => $action) {
            $this->assertEquals( $action['priority'], has_action( $tag, $action['callback'] ),
                "Admin has a '$tag' action callback." );
            $this->assertTrue( is_callable( $action['callback'] ),
                "'$tag' action callback is callable." );
        }

        $this->markTestIncomplete();

        // TODO: Assert that the endpoint slug is saved when permalink options are saved.
        // TODO: Assert that settings are registered.
    }

    /**
     * @covers ::admin_menu
     */
    function test_admin_menu () {
        $this->markTestIncomplete();

        // TODO: Assert that menu entry exists.
    }

    /**
     * @covers ::test_admin_notices
     */
    function test_admin_notices () {
        $this->markTestIncomplete();

        // TODO: Assert that administrators see a "No HTTPS" warning when SSL is off.
    }

    /**
     * @covers ::pageSettings
     */
    function test_pageSettings () {
        $this->markTestIncomplete();

        // TODO: Assert that settings page is output.
        // TODO: Assert that settings page contains a form.
        // TODO: Assert that settings page contains a submit button.
        // TODO: Assert that settings page contains a hidden field for option_page (value must be the plugin slug).
        // TODO: Assert that settings page contains a hidden field for action (value must be 'update').
        // TODO: Assert that settings page contains a hidden field for _wpnonce (value must be valid)
    }

    /**
     * @covers ::fieldPermalinksEndpointSlug
     */
    function test_fieldPermalinksEndpointSlug () {
        $this->markTestIncomplete();

        // TODO: Assert that 
    }

    /**
     * @covers ::fieldUserAttributes
     */
    function test_fieldUserAttributes () {
        $this->markTestIncomplete();

        // TODO: Assert that the user attributes field set is output.
        // TODO: Assert that the user attributes field set contains one checkbox per allowed attribute.
        // TODO: Assert that the user attributes' checkboxes are ticked for enabled attributes.
        // TODO: Assert that the user attributes to output can be filtered.
    }

    /**
     * @covers ::validateSettings
     */
    function test_validateSettings () {
        $this->markTestIncomplete();

        // TODO: Assert that the options array can be returned unaltered.
        // TODO: Assert that an empty options array can be returned with new settings.
        // TODO: Assert that an existing options array can be returned with updated settings.
    }

}