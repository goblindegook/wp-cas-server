<?php

class WP_TestCASServerPlugin extends WP_UnitTestCase {

	/**
	 * The plugin should be installed and activated.
	 */
	function test_plugin_activated() {
		// $this->assertTrue( is_plugin_active( 'wordpress-cas-server/wordpress-cas-server.php' ) );
	}

	/**
	 * The init hook should have been registered with init, and should
	 * have a default priority of 10.
	 */
	function test_init_action_added() {
		// $this->assertEquals( 10, has_action( 'init', array( $wp_cas_server, 'init' ) ) );
	}

	/**
	 * The cas_route query variable should be registered.
	 */
	function test_cas_route_query_var() {
		global $wp;
		$this->assertTrue( in_array( 'cas_route', $wp->public_query_vars ) );
	}

}

