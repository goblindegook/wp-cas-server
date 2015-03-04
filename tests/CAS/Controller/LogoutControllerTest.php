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
	 * Tests /logout
	 * @runInSeparateProcess
	 * @dataProvider data_logout
	 * @covers ::logout
	 */
	function test_logout( $service, $expected ) {
		wp_set_current_user( $this->factory->user->create() );

		try {
			$this->controller->handleRequest( array( 'service' => $service ) );
		}
		catch ( WPDieException $message ) {
			parse_str( parse_url( $this->redirect_location, PHP_URL_QUERY ) );
		}

		$this->assertFalse( is_user_logged_in(),
			'User is logged out.' );

		$this->assertStringStartsWith( $expected, $this->redirect_location,
			"'logout' performs a redirect." );
	}

	/**
	 * @return array Test data for logout tests.
	 */
	function data_logout() {
		return array(
			array( 'http://test/', 'http://test/' ),
			array( null, home_url() ),
		);
	}

}

