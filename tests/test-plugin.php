<?php
/**
 * @package WPCASServerPlugin
 * @subpackage Tests
 */

class WP_TestWPCASServerPlugin extends WP_UnitTestCase {

    private $plugin;

    /**
     * Setup a test method for the CASServerPlugin class.
     */
    function setUp () {
        parent::setUp();
        $this->plugin = $GLOBALS[WPCASServerPlugin::SLUG];
    }

    /**
     * Finish a test method for the CASServerPlugin class.
     */
    function tearDown () {
        parent::tearDown();
        unset( $this->plugin );
    }

    /**
     * Test plugin constant and static attributes.
     */
    function test_plugin_constants () {
        $slug = 'wp-cas-server';
        $this->assertEquals( $slug, WPCASServerPlugin::SLUG, "Plugin slug is $slug." );

        $file = 'wp-cas-server/wp-cas-server.php';
        $this->assertEquals( $file, WPCASServerPlugin::FILE, "Plugin file is $file." );
    }

    /**
     * The plugin should be installed and activated.
     * @covers WPCASServerPlugin::plugin_activated
     */
    function test_plugin_activated () {
        $this->assertNotNull( $GLOBALS[WPCASServerPlugin::SLUG],
            'Plugin is instantiated.' );

        $this->assertTrue( is_plugin_active( WPCASServerPlugin::FILE ),
            'Plugin is activated.' );
    }

    /**
     * Test plugin options.
     * @covers WPCASServerPlugin::init
     */
    function test_init () {
        global $wp;

        delete_option( WPCASServerPlugin::OPTIONS_KEY );

        $this->plugin->init();

        $this->assertNotEmpty( get_option( WPCASServerPlugin::OPTIONS_KEY ), 'Plugin sets default options on init.' );

        $this->assertTrue( in_array( 'cas_route', $wp->public_query_vars ), 'Plugin sets the cas_route endpoint.' );
    }

    /**
     * Test plugin action callbacks.
     * @group action
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
     * @group filter
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
     * Test plugin settings getter.
     * @covers WPCASServerPlugin::getOption
     */
    function testGetOption () {
        $path = WPCASServerPlugin::getOption( 'endpoint_slug' );
        $this->assertEquals( WPCASServerPlugin::ENDPOINT_SLUG, $path, 'Obtain the path setting.' );

        $path = WPCASServerPlugin::getOption( 'endpoint_slug', 'default' );
        $this->assertEquals( WPCASServerPlugin::ENDPOINT_SLUG, $path, 'Ignores default when obtaining an existing setting.' );

        $unset = WPCASServerPlugin::getOption( 'unset', 'nothing' );
        $this->assertEquals( 'nothing', $unset, 'Obtain the default for a non-existing setting.' );
    }

    /**
     * Test plugin settings setter.
     * 
     * @covers WPCASServerPlugin::getOption
     * @covers WPCASServerPlugin::setOption
     */
    function testSetOption () {
        WPCASServerPlugin::setOption( 'zero', 0 );
        $this->assertSame( 0, WPCASServerPlugin::getOption( 'zero' ), 'Set 0 integer.' );

        WPCASServerPlugin::setOption( 'integer', 99 );
        $this->assertSame( 99, WPCASServerPlugin::getOption( 'integer' ), 'Set non-zero integer.' );

        WPCASServerPlugin::setOption( 'float', 99.99 );
        $this->assertSame( 99.99, WPCASServerPlugin::getOption( 'float' ), 'Set float.' ); 

        WPCASServerPlugin::setOption( 'string', 'test' );
        $this->assertSame( 'test', WPCASServerPlugin::getOption( 'string' ), 'Set string.' ); 

        WPCASServerPlugin::setOption( 'array', array( 1, 2, 3 ) );
        $this->assertSame( array( 1, 2, 3 ), WPCASServerPlugin::getOption( 'array' ), 'Set array.' );

        WPCASServerPlugin::setOption( 'object', (object) array( 1, 2, 3 ) );
        $this->assertEquals( (object) array( 1, 2, 3 ), WPCASServerPlugin::getOption( 'object' ), 'Set object.' );        
    }

    /**
     * Test allowed_redirect_hosts filter callback.
     * @covers WPCASServerPlugin::allowed_redirect_hosts
     */
    function testAllowedRedirectHosts () {

        $noSchemaAllowed = version_compare( phpversion(), '5.4.7', '>=' );        

        WPCASServerPlugin::setOption( 'allowed_services', array(
            'http://test1/',
            'http://test2:8080/',
            'https://test3/',
            'http://test4/path/',
            'http://user@test5/',
            '//test6',
            ) );

        $hosts = $this->plugin->allowed_redirect_hosts( array( 'test.local' ));

        $this->assertContains( 'test.local', $hosts,
            'test.local is retained in the allowed redirect hosts list.' );

        $this->assertContains( 'test1', $hosts,
            'test1 is added to the allowed redirect hosts list.' );

        $this->assertContains( 'test2', $hosts,
            'test2 is added to the allowed redirect hosts list.' );

        $this->assertContains( 'test3', $hosts,
            'test3 is added to the allowed redirect hosts list.' );

        $this->assertContains( 'test4', $hosts,
            'test4 is added to the allowed redirect hosts list.' );

        $this->assertContains( 'test5', $hosts,
            'test5 is added to the allowed redirect hosts list.' );

        if ($noSchemaAllowed) {
            $this->assertContains( 'test6', $hosts,
                'test6 is added to the allowed redirect hosts list.' );
        }

        $expected_count = $noSchemaAllowed ? 7 : 6;

        $this->assertCount( $expected_count, $hosts,
            'Allowed hosts are added to an existing list.');

        WPCASServerPlugin::setOption( 'allowed_services', false );

        $hosts = $this->plugin->allowed_redirect_hosts( array( 'test.local' ));

        $this->assertContains( 'test.local', $hosts,
            'test.local is retained in the allowed redirect hosts list.' );

        $this->assertNotContains( '', $hosts,
            'Empty setting does not add invalid hosts to the list.' );

        $this->assertCount( 1, $hosts,
            'Invalid hosts are not added to an existing list.');

        WPCASServerPlugin::setOption( 'allowed_services', 'http://test-string/' );

        $hosts = $this->plugin->allowed_redirect_hosts();

        $this->assertContains( 'test-string', $hosts,
            'String setting adds a single host to the list.' );

        $this->assertCount( 1, $hosts,
            'Single host is added.');
    }

    /**
     * Test the rewrite rules set by the plugin.
     * 
     * @todo Test rewrite rules.
     * @todo Test that the endpoint_slug reverts to the default when empty.
     */
    function test_rewrite_rules () {
        
        $path = WPCASServerPlugin::getOption( 'endpoint_slug' );

        $this->assertNotEmpty( $path, 'Plugin sets default URI path root.');

        $rule = '^' . $path . '/(.*)?';

        // TODO: Look for endpoints
        // - Force SSL option OFF --> OK
        // - Force SSL option ON and...
        //     - SSL ON           --> OK
        //     - SSL OFF          --> Error

        // Plugin forces default endpoint slug
        
        WPCASServerPlugin::setOption( 'endpoint_slug', '' );

        $this->markTestIncomplete();
    }

}

