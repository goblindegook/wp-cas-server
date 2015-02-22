<?php

use Cassava\CAS;

/**
 * @coversDefaultClass \Cassava\CAS\Controller\LogoutController
 */
class TestWPCASControllerLogout extends WPCAS_UnitTestCase {

	private $controller;

	function setUp() {
		parent::setUp();
		$this->controller = new CAS\Controller\LogoutController( new CAS\Server );
	}

	function tearDown() {
		parent::tearDown();
		unset( $this->controller );
	}

	/**
	 * @covers ::__construct
	 */
	function test_construct () {
		$this->assertTrue( is_a( $this->controller, '\Cassava\CAS\Controller\BaseController' ),
			'LogoutController extends BaseController.' );
	}

	/**
	 * @runInSeparateProcess
	 * @covers ::logout
	 */
	function test_logout () {

		/**
		 * /logout?service=http://test/
		 */

		$service = 'http://test/';

		wp_set_current_user( $this->factory->user->create() );

		$this->assertTrue( is_user_logged_in(),
			'User is logged in.' );

		try {
			$this->controller->handleRequest( array( 'service' => $service ) );
		}
		catch (WPDieException $message) {
			parse_str( parse_url( $this->redirect_location, PHP_URL_QUERY ), $query );
		}

		$this->assertFalse( is_user_logged_in(),
			'User is logged out.' );

		$this->assertStringStartsWith( $service, $this->redirect_location,
			"'logout' redirects to service." );

		/**
		 * /logout
		 */

		wp_set_current_user( $this->factory->user->create() );

		try {
			$this->controller->handleRequest( array( 'service' => $service ) );
		}
		catch (WPDieException $message) {
			parse_str( parse_url( $this->redirect_location, PHP_URL_QUERY ), $query );
		}

		$this->assertStringStartsWith( $service, $this->redirect_location,
			"'logout' redirects to home if no service is provided." );
	}

}

