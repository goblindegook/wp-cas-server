<?php
/**
 * @package \WPCASServerPlugin\Tests
 */

/**
 * @coversDefaultClass WPCASControllerLogout
 */
class TestWPCASControllerLogout extends WPCAS_UnitTestCase {

	private $server;

	/**
	 * Setup a test method for the WPCASServer class.
	 */
	function setUp() {
		parent::setUp();
		$this->server = new WPCASServer;
	}

	/**
	 * Finish a test method for the CASServer class.
	 */
	function tearDown() {
		parent::tearDown();
		unset( $this->server );
	}

	function test_interface () {
		$this->assertArrayHasKey( 'ICASServer', class_implements( $this->server ),
			'WPCASServer implements the ICASServer interface.' );
	}

	/**
	 * @runInSeparateProcess
	 * @covers ::logout
	 */
	function test_logout () {

		$this->assertTrue( is_callable( array( $this->server, 'logout' ) ),
			"'logout' method is callable." );

		/**
		 * /logout?service=http://test/
		 */

		$service = 'http://test/';

		wp_set_current_user( $this->factory->user->create() );

		$this->assertTrue( is_user_logged_in(),
			'User is logged in.' );

		try {
			$this->server->logout( array( 'service' => $service ) );
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
			$this->server->logout( array( 'service' => $service ) );
		}
		catch (WPDieException $message) {
			parse_str( parse_url( $this->redirect_location, PHP_URL_QUERY ), $query );
		}

		$this->assertStringStartsWith( $service, $this->redirect_location,
			"'logout' redirects to home if no service is provided." );
	}

}

