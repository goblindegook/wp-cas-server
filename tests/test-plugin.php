<?php
/**
 * @package WPCASServerPlugin
 * @subpackage WPCASServerPlugin_Tests
 */

class WP_TestWPCASServerPlugin extends WP_UnitTestCase {

    const TEST_ACTION = 0;
    const TEST_FILTER = 1;

    private $plugin;

    /**
     * Setup test suite for the CASServerPlugin class.
     */
    function setUp () {
        parent::setUp();
        $this->plugin = $GLOBALS[WPCASServerPlugin::SLUG];
    }

    /**
     * Finish the test suite for the CASServerPlugin class.
     */
    function tearDown () {
        parent::tearDown();
        unset( $this->plugin );
    }

    /**
     * Test plugin constant and static attributes.
     */
    function test_plugin_constants () {
        $slug = 'wordpress-cas-server';
        $this->assertEquals( $slug, WPCASServerPlugin::SLUG, "Plugin slug is $slug." );

        $file = 'wordpress-cas-server/wordpress-cas-server.php';
        $this->assertEquals( $file, WPCASServerPlugin::FILE, "Plugin file is $file." );
    }

    /**
     * The plugin should be installed and activated.
     */
    function test_plugin_activated () {
        $this->assertNotNull( $GLOBALS[WPCASServerPlugin::SLUG],
            'Plugin is instantiated.' );

        $this->assertTrue( is_plugin_active( WPCASServerPlugin::FILE ),
            'Plugin is activated.' );
    }

    /**
     * Test plugin action callbacks.
     */
    function test_actions () {
        $actions = array(
            'plugins_loaded' => array(
                'priority'  => 10,
                'callback'  => array( $this->plugin, 'plugins_loaded' ),
                ),
            'init' => array(
                'priority'  => 10,
                'callback'  => array( $this->plugin, 'init' ),
                ),
            'template_redirect' => array(
                'priority'  => -100,
                'callback'  => array( $this->plugin, 'template_redirect' ),
                ),
        );

        foreach ($actions as $tag => $action) {
            $this->assertEquals( $action['priority'], has_action( $tag, $action['callback'] ),
                "Plugin has a '$tag' action callback." );
            $this->assertTrue( is_callable( $action['callback'] ),
                "'$tag' action callback is callable." );
        }
    }

    /**
     * Test plugin filter callbacks.
     */
    function test_filters () {
        $filters = array(
            'allowed_redirect_hosts' => array(
                'priority'  => 10,
                'callback'  => array( $this->plugin, 'allowed_redirect_hosts' ),
                ),
        );

        foreach ($filters as $tag => $filter) {
            $this->assertEquals( $filter['priority'], has_filter( $tag, $filter['callback'] ),
                "Plugin has a '$tag' filter callback." );
            $this->assertTrue( is_callable( $filter['callback'] ),
                "'$tag' filter callback is callable." );
        }
    }

    /**
     * Test plugin options.
     */
    function test_plugin_options () {
        delete_option( WPCASServerPlugin::OPTIONS_KEY );
        $this->plugin->init();
        $this->assertNotEmpty( get_option( WPCASServerPlugin::OPTIONS_KEY ), 'Plugin sets default options on init.' );
    }

    function test_plugin_rewrite_rules () {
        $options = get_option( WPCASServerPlugin::OPTIONS_KEY );
        $this->assertNotEmpty( $options['path'], 'Plugin sets default URI path root.');
    }

    /**
     * Test whether a WordPress hook is called for a given function and set of arguments.
     * @param  string  $label    Hook label to test.
     * @param  mixed   $function String or array with the function to execute.
     * @param  array   $args     Ordered list of arguments to pass the function.
     * @param  int     $test     What to test: TEST_ACTION (default) or TEST_FILTER.
     * @return boolean           [description]
     */
    private function _is_hook_called ( $label, $function, $args, $test = self::TEST_ACTION ) {
        $called = false;
        $handler = function ( $in ) use (&$called) {
            $called = true;
            return $in;
        };

        switch ($test) {
            case self::TEST_ACTION:
                add_action( $label, $handler );
                break;

            case self::TEST_FILTER:
                add_filter( $label, $handler );
                break;
            
            default:
                break;
        }

        ob_start();
        call_user_func_array( $function, $args );
        ob_end_clean();

        switch ($test) {
            case self::TEST_ACTION:
                remove_action( $label, $handler );
                break;

            case self::TEST_FILTER:
                remove_filter( $label, $handler );
                break;
            
            default:
                break;
        }

        return $called;
    }

    /**
     * Test actions introduced by the plugin.
     */
    function test_plugin_actions () {
        $server  = new WPCASServer;

        $triggers = array(
            'cas_server_before_request' => array( 'function' => array( $server, 'handleRequest' ),
                                                  'args'     => array( '/invalid-uri' ),
                                                  ),
            'cas_server_after_request'  => array( 'function' => array( $server, 'handleRequest' ),
                                                  'args'     => array( '/invalid-uri' ),
                                                  ),
            'cas_server_error'          => array( 'function' => array( $server, 'handleRequest' ),
                                                  'args'     => array( '/invalid-uri' ),
                                                  ),
            );

        foreach ($triggers as $action => $trigger) {
            $called = $this->_is_hook_called( $action, $trigger['function'], $trigger['args'], self::TEST_ACTION );
            $this->assertTrue( $called, "Action callback for '$action' is called." );
        }
    }

    /**
     * Test filters introduced by the plugin.
     * 
     * @TODO: Some filters not tested until I find a way to get around exit and die().
     */
    function test_plugin_filters () {
        $server  = new WPCASServer;

        $triggers = array(
            'cas_enabled'                  => array( 'function' => array( $server, 'handleRequest' ),
                                                     'args'     => array( '/invalid-uri' ),
                                                     ),
            'cas_server_routes'            => array( 'function' => array( $server, 'handleRequest' ),
                                                     'args'     => array( '/invalid-uri' ),
                                                     ),
            /*
            'cas_server_dispatch_callback' => array( 'function' => array( $server, 'handleRequest' ),
                                                     'args'     => array( '/login' ),
                                                     ),
            'cas_server_dispatch_args'     => array( 'function' => array( $server, 'handleRequest' ),
                                                     'args'     => array( '/login' ),
                                                     ),
            'cas_server_login_args'        => array( 'function' => array( $server, 'handleRequest' ),
                                                     'args'     => array( '/login' ),
                                                     ),
            'cas_server_ticket'            => array( 'function' => array( $server, 'handleRequest' ),
                                                     'args'     => array( '/login' ),
                                                     ),
            'cas_server_service'           => array( 'function' => array( $server, 'handleRequest' ),
                                                     'args'     => array( '/login' ),
                                                     ), */
            );

        foreach ($triggers as $filter => $trigger) {

            $_GET['service'] = 'https://test.local/';

            $user_id = $this->factory->user->create();
            wp_set_current_user( $user_id );

            $called = $this->_is_hook_called( $filter, $trigger['function'], $trigger['args'], self::TEST_FILTER );
            $this->assertTrue( $called, "Filter callback for '$filter' is called." );
        }

        $this->markTestIncomplete( 'Some filters not tested until I find a way to get around exit and die().' );
    }

    /**
     * Test rewrite rules set by the plugin.
     */
    function test_rewrite_rules () {
        $this->markTestIncomplete();
    }

    /**
     * The cas_route query variable should be registered.
     */
    function test_cas_route_query_var () {
        global $wp;
        $this->assertTrue( in_array( 'cas_route', $wp->public_query_vars ) );
    }

}

