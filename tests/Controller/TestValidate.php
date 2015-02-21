<?php
/**
 * @package \WPCASServerPlugin\Tests
 */

/**
 * @coversDefaultClass WPCASControllerValidate
 */
class TestWPCASControllerValidate extends WPCAS_UnitTestCase {

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
	 * @covers ::validate
	 */
	function test_validate () {

		$this->assertTrue( is_callable( array( $this->server, 'validate' ) ),
			"'validate' method is callable." );

		/**
		 * No service.
		 */
		$args = array(
			'service' => '',
			'ticket'  => 'ticket',
			);

		$this->assertEquals( $this->server->validate( $args ), "no\n\n",
			"Error on empty service." );

		/**
		 * No ticket.
		 */
		$args = array(
			'service' => 'http://test.local/',
			'ticket'  => '',
			);

		$this->assertEquals( $this->server->validate( $args ), "no\n\n",
			"Error on empty ticket." );

		/**
		 * Invalid ticket.
		 */
		$args = array(
			'service' => 'http://test.local/',
			'ticket'  => 'bad-ticket',
			);

		$this->assertEquals( $this->server->validate( $args ), "no\n\n",
			"Error on invalid ticket." );

		/**
		 * Valid ticket.
		 */
		$service = 'http://test/';
		$user_id = $this->factory->user->create();

		wp_set_current_user( $user_id );

		try {
			$this->server->login( array( 'service' => $service ) );
		}
		catch (WPDieException $message) {
			parse_str( parse_url( $this->redirect_location, PHP_URL_QUERY ), $query );
		}

		$args = array(
			'service' => $service,
			'ticket'  => $query['ticket'],
			);

		$user = get_user_by( 'id', $user_id );

		$this->assertEquals( "yes\n" . $user->user_login . "\n", $this->server->validate( $args ),
			"Valid ticket." );

		WPCASServerPlugin::setOption( 'allow_ticket_reuse', 1 );

		$this->assertEquals( "yes\n" . $user->user_login . "\n", $this->server->validate( $args ),
			"Tickets may reused." );

		WPCASServerPlugin::setOption( 'allow_ticket_reuse', 0 );

		$this->assertEquals( "no\n\n", $this->server->validate( $args ),
			"Tickets may not be reused." );

		$this->markTestIncomplete( "Test support for the optional 'pgtUrl' and 'renew' parameters." );
	}

}

