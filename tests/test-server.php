<?php
/**
 * @package WPCASServerPlugin
 * @subpackage WPCASServerPlugin_Tests
 */

class WP_TestWPCASServer extends WP_UnitTestCase {

	/**
	 * Setup test suite for the WPCASServer class.
	 */
	function setUp () {
		parent::setUp();
		$this->server = new WPCASServer;
	}

	/**
	 * Finish the test suite for the CASServer class.
	 */
	function tearDown () {
		parent::tearDown();
		unset( $this->server );
	}

	function test_login_route () {
		$this->markTestIncomplete();
	}

}

