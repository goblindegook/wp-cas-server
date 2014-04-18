<?php
/**
 * @package WPCASServerPlugin
 * @subpackage WPCASServerPlugin_Tests
 */

class WP_TestWPCASServerPlugin extends WP_UnitTestCase {

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
				'priority'	=> 10,
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
				'priority'	=> 10,
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
	 * [test_plugin_actions description]
	 */
	function test_plugin_actions () {
		// TODO:
		// cas_server_before_request
		// cas_server_after_request
		// cas_server_error
		
		$this->markTestIncomplete();
	}

	/**
	 * [test_plugin_filters description]
	 */
	function test_plugin_filters () {
		// TODO:
		// cas_enabled
		// cas_server_routes
		// cas_server_dispatch_callback
		// cas_server_dispatch_args
		// cas_server_login_args
		// cas_server_ticket
		// cas_server_service
		
		$this->markTestIncomplete();
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

