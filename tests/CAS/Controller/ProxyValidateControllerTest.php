<?php

use Cassava\CAS;
use Cassava\Exception\RequestException;
use Cassava\Exception\TicketException;

/**
 * @coversDefaultClass \Cassava\CAS\Controller\ProxyValidateController
 */
class TestWPCASControllerProxyValidate extends WPCAS_UnitTestCase {

	private $server;
	private $controller;

	function setUp() {
		parent::setUp();
		$this->server     = new CAS\Server();
		$this->controller = new CAS\Controller\ProxyValidateController( $this->server );
	}

	function tearDown() {
		parent::tearDown();
		unset( $this->server );
		unset( $this->controller );
	}

	/**
	 * @covers ::__construct
	 */
	function test_construct () {
		$this->assertTrue( is_a( $this->controller, '\Cassava\CAS\Controller\BaseController' ),
			'ProxyValidateController extends BaseController.' );
	}

	/**
	 * @runInSeparateProcess
	 * @covers ::proxyValidate
	 *
	 * @todo Test support for the optional 'pgtUrl' parameter.
	 * @todo Test support for the optional 'renew' parameter.
	 */
	function test_proxyValidate () {

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

		$this->assertXPathMatch( RequestException::ERROR_INVALID_REQUEST, 'string(//cas:authenticationFailure[1]/@code)', $error,
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

		$this->assertXPathMatch( RequestException::ERROR_INVALID_REQUEST, 'string(//cas:authenticationFailure[1]/@code)', $error,
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

		$this->assertXPathMatch( TicketException::ERROR_INVALID_TICKET, 'string(//cas:authenticationFailure[1]/@code)', $error,
			'INVALID_TICKET error code on bad ticket.' );

		/**
		 * Valid ticket.
		 */
		$user_id = $this->factory->user->create();

		wp_set_current_user( $user_id );

		try {
			$login = new CAS\Controller\LoginController( $this->server );
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

		$this->assertXPathMatch( $user->user_login, 'string(//cas:authenticationSuccess[1]/cas:user[1])', $xml,
			"Ticket validation returns user login." );

		/**
		 * Do not enforce single-use tickets.
		 */

		Cassava\Options::set( 'allow_ticket_reuse', 1 );

		$xml = $this->controller->handleRequest( $args );

		$this->assertXPathMatch( 1, 'count(//cas:authenticationSuccess)', $xml,
			'Settings allow ticket reuse.' );

		/**
		 * /proxyValidate may validate Proxy Tickets.
		 */
		$args = array(
			'service' => $service,
			'ticket'  => preg_replace( '@^' . CAS\Ticket::TYPE_ST . '@', CAS\Ticket::TYPE_PT, $query['ticket'] ),
			);

		$xml = $this->controller->handleRequest( $args );

		$this->assertXPathMatch( 1, 'count(//cas:authenticationSuccess)', $xml,
			"'proxyValidate' may validate proxy tickets." );

		/**
		 * Enforce single-use tickets.
		 */
		Cassava\Options::set( 'allow_ticket_reuse', 0 );

		$args = array(
			'service' => $service,
			'ticket'  => $query['ticket'],
			);

		$error = $this->controller->handleRequest( $args );

		$this->assertXPathMatch( 1, 'count(//cas:authenticationFailure)', $error,
			"Settings do not allow ticket reuse." );

		$this->assertXPathMatch( TicketException::ERROR_INVALID_TICKET, 'string(//cas:authenticationFailure[1]/@code)', $error,
			'INVALID_TICKET error code on ticket reuse.' );
	}

}

