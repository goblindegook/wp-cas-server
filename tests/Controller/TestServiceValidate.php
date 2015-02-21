<?php
/**
 * @package \WPCASServerPlugin\Tests
 */

/**
 * @coversDefaultClass WPCASControllerServiceValidate
 */
class TestWPCASControllerServiceValidate extends WPCAS_UnitTestCase {

	private $server;
	private $controller;

	/**
	 * Setup a test method for the WPCASControllerServiceValidate class.
	 */
	function setUp() {
		parent::setUp();
		$this->server     = new WPCASServer();
		$this->controller = new WPCASControllerServiceValidate( $this->server );
	}

	/**
	 * Finish a test method for the WPCASControllerServiceValidate class.
	 */
	function tearDown() {
		parent::tearDown();
		unset( $this->server );
		unset( $this->controller );
	}

	/**
	 * @covers ::__construct
	 */
	function test_construct () {
		$this->assertTrue( is_a( $this->controller, 'WPCASController' ),
			'WPCASControllerServiceValidate implements the WPCASController interface.' );
	}

	/**
	 * @runInSeparateProcess
	 * @covers ::serviceValidate
	 *
	 * @todo Test support for the optional 'pgtUrl' parameter.
	 * @todo Test support for the optional 'renew' parameter.
	 */
	function test_serviceValidate () {

		$service = 'http://test/';

		/**
		 * No service.
		 */
		$args = array(
			'service' => '',
			'ticket'  => 'ticket',
			);

		$error = $this->controller->handleRequest( $args );

		$this->assertXPathMatch( 1, 'count(//cas:authenticationFailure)', $error,
			'Error if service not provided.' );

		$this->assertXPathMatch( WPCASRequestException::ERROR_INVALID_REQUEST, 'string(//cas:authenticationFailure[1]/@code)', $error,
			'INVALID_REQUEST error code if service not provided.' );

		/**
		 * No ticket.
		 */
		$args = array(
			'service' => $service,
			'ticket'  => '',
			);

		$error = $this->controller->handleRequest( $args );

		$this->assertXPathMatch( 1, 'count(//cas:authenticationFailure)', $error,
			'Error if ticket not provided.' );

		$this->assertXPathMatch( WPCASRequestException::ERROR_INVALID_REQUEST, 'string(//cas:authenticationFailure[1]/@code)', $error,
			'INVALID_REQUEST error code if ticket not provided.' );

		/**
		 * Invalid ticket.
		 */
		$args = array(
			'service' => $service,
			'ticket'  => 'bad-ticket',
			);

		$error = $this->controller->handleRequest( $args );

		$this->assertXPathMatch( 1, 'count(//cas:authenticationFailure)', $error,
			'Error on bad ticket.' );

		$this->assertXPathMatch( WPCASTicketException::ERROR_INVALID_TICKET, 'string(//cas:authenticationFailure[1]/@code)', $error,
			'INVALID_TICKET error code on bad ticket.' );

		/**
		 * Valid ticket.
		 */
		$user_id = $this->factory->user->create();

		wp_set_current_user( $user_id );

		try {
			$login = new WPCASControllerLogin( $this->server );
			$login->handleRequest( array( 'service' => $service ) );
		}
		catch (WPDieException $message) {
			parse_str( parse_url( $this->redirect_location, PHP_URL_QUERY ), $query );
		}

		$args = array(
			'service' => $service,
			'ticket'  => $query['ticket'],
			);

		$user = get_user_by( 'id', $user_id );

		$xml = $this->controller->handleRequest( $args );

		$this->assertXPathMatch( 1, 'count(//cas:authenticationSuccess)', $xml,
			'Successful validation.' );

		$this->assertXPathMatch( 1, 'count(//cas:authenticationSuccess/cas:user)', $xml,
			"Ticket validation response returns a user.");

		$this->assertXPathMatch( $user->user_login, 'string(//cas:authenticationSuccess/cas:user)', $xml,
			"Ticket validation returns user login." );

		/**
		 * Do not enforce single-use tickets.
		 */
		WPCASServerPlugin::setOption( 'allow_ticket_reuse', 1 );

		$xml = $this->controller->handleRequest( $args );

		$this->assertXPathMatch( 1, 'count(//cas:authenticationSuccess)', $xml,
			'Settings allow ticket reuse.' );

		/**
		 * Validate does not return any user attributes.
		 */

		$this->assertXPathMatch( 0, 'count(//cas:authenticationSuccess/cas:attributes)', $xml,
			"Ticket validation returns no user attributes.");

		/**
		 * Validate returns selected user attributes.
		 */

		WPCASServerPlugin::setOption( 'attributes', array( 'display_name', 'user_email' ) );

		$xml = $this->controller->handleRequest( $args );

		$this->assertXPathMatch( $user->get( 'display_name' ),
			'string(//cas:authenticationSuccess/cas:attributes/cas:display_name)', $xml,
			'Ticket validation returns the user display name.' );

		$this->assertXPathMatch( $user->get( 'user_email' ),
			'string(//cas:authenticationSuccess/cas:attributes/cas:user_email)', $xml,
			'Ticket validation returns the user email.' );

		/**
		 * /serviceValidate should not validate Proxy Tickets.
		 */
		$args = array(
			'service' => $service,
			'ticket'  => preg_replace( '@^' . WPCASTicket::TYPE_ST . '@', WPCASTicket::TYPE_PT, $query['ticket'] ),
			);

		$error = $this->controller->handleRequest( $args );

		$this->assertXPathMatch( 1, 'count(//cas:authenticationFailure)', $error,
			"'serviceValidate' may not validate proxy tickets." );

		$this->assertXPathMatch( WPCASTicketException::ERROR_INVALID_TICKET, 'string(//cas:authenticationFailure[1]/@code)', $error,
			'INVALID_TICKET error code on proxy ticket.' );

		/**
		 * Enforce single-use tickets.
		 */
		WPCASServerPlugin::setOption( 'allow_ticket_reuse', 0 );

		$args = array(
			'service' => $service,
			'ticket'  => $query['ticket'],
			);

		$error = $this->controller->handleRequest( $args );

		$this->assertXPathMatch( 1, 'count(//cas:authenticationFailure)', $error,
			"Settings do not allow ticket reuse." );

		$this->assertXPathMatch( WPCASTicketException::ERROR_INVALID_TICKET, 'string(//cas:authenticationFailure[1]/@code)', $error,
			'INVALID_TICKET error code on ticket reuse.' );

		$this->markTestIncomplete( "Test support for the optional 'pgtUrl' and 'renew' parameters." );
	}

}

