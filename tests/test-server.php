<?php
/**
 * @package WPCASServerPlugin
 * @subpackage WPCASServerPlugin_Tests
 */

class WP_TestWPCASServer extends WP_UnitTestCase {

	private $server;
	private $routes;

	/**
	 * Setup a test method for the WPCASServer class.
	 */
	function setUp () {
		parent::setUp();
		$this->server = new WPCASServer;
		$this->routes = $this->server->routes();
	}

	/**
	 * Finish a test method for the CASServer class.
	 */
	function tearDown () {
		parent::tearDown();
		unset( $this->server );
	}

	function test_routes () {
		$routes = array(
			'login',
			'logout',
			'proxy',
			'proxyValidate',
			'serviceValidate',
			'validate',
			);

		foreach ($routes as $route) {
			$this->assertArrayHasKey( $route, $this->server->routes(),
				"Route '$route' has a callback." );
			$this->assertTrue( is_callable( $this->server->routes()[$route] ),
				"Method for route '$route' is callable." );
		}
	}

	function test_login () {
		// $this->go_to();

		$this->markTestIncomplete();
	}

	function test_logout () {
		$this->markTestIncomplete();
	}

	function test_validate () {
		$this->markTestIncomplete();
	}

	function test_serviceValidate () {
		$this->markTestIncomplete();
	}

	function test_proxy () {
		$this->markTestIncomplete();
	}

	function test_proxyValidate () {
		$this->markTestIncomplete();
	}

}

