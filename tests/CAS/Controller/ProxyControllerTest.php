<?php

use Cassava\CAS;
use Cassava\Exception\RequestException;
use Cassava\Exception\TicketException;

/**
 * @coversDefaultClass \Cassava\CAS\Controller\ProxyController
 */
class TestWPCASControllerProxy extends WPCAS_UnitTestCase {

	private $server;
	private $controller;

	function setUp() {
		parent::setUp();
		$this->server     = new CAS\Server();
		$this->controller = new CAS\Controller\ProxyController( $this->server );
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
			'ProxyController extends BaseController.' );
	}

	/**
	 * @runInSeparateProcess
	 * @covers ::proxy
	 */
	function test_proxy () {

		$targetService = 'http://test/';

		/**
		 * No proxy-granting ticket.
		 */
		$args = array(
			'targetService' => $targetService,
			'pgt'           => '',
			);

		$error = $this->controller->handleRequest( $args );

		$this->assertXPathMatch( 1, 'count(//cas:proxyFailure)', $error,
			'Error if proxy-granting ticket not provided.' );

		$this->assertXPathMatch( RequestException::ERROR_INVALID_REQUEST, 'string(//cas:proxyFailure[1]/@code)', $error,
			'INVALID_REQUEST error code if proxy-granting ticket not provided.' );

		/**
		 * No target service.
		 */
		$args = array(
			'targetService' => '',
			'pgt'           => 'pgt',
			);

		$error = $this->controller->handleRequest( $args );

		$this->assertXPathMatch( 1, 'count(//cas:proxyFailure)', $error,
			'Error if target service not provided.' );

		$this->assertXPathMatch( RequestException::ERROR_INVALID_REQUEST, 'string(//cas:proxyFailure[1]/@code)', $error,
			'INVALID_REQUEST error code if target service not provided.' );

		/**
		 * Invalid proxy-granting ticket.
		 */
		$args = array(
			'targetService' => $targetService,
			'pgt'           => 'bad-ticket',
			);

		$error = $this->controller->handleRequest( $args );

		$this->assertXPathMatch( 1, 'count(//cas:proxyFailure)', $error,
			'Error on bad proxy-granting ticket.' );

		$this->assertXPathMatch( TicketException::ERROR_BAD_PGT, 'string(//cas:proxyFailure[1]/@code)', $error,
			'BAD_PGT error code on bad proxy-granting ticket.' );

		/**
		 * /proxy should not validate service tickets.
		 */
		$user_id = $this->factory->user->create();

		wp_set_current_user( $user_id );

		try {
			$login = new CAS\Controller\LoginController( $this->server );
			$login->handleRequest( array( 'service' => $targetService ) );
		}
		catch (WPDieException $message) {
			parse_str( parse_url( $this->redirect_location, PHP_URL_QUERY ), $query );
		}

		$args = array(
			'targetService' => $targetService,
			'pgt'           => $query['ticket'],
			);

		$xml = $this->controller->handleRequest( $args );

		$this->assertXPathMatch( 1, 'count(//cas:proxyFailure)', $xml,
			"'proxy' should not validate service tickets." );

		$this->assertXPathMatch( TicketException::ERROR_BAD_PGT, 'string(//cas:proxyFailure[1]/@code)', $xml,
			'BAD_PGT error code on proxy ticket.' );

		/**
		 * /proxy should not validate proxy tickets.
		 */

		$args = array(
			'targetService' => $targetService,
			'pgt'           => preg_replace( '@^' . CAS\Ticket::TYPE_ST . '@', CAS\Ticket::TYPE_PT, $query['ticket'] ),
			);

		$xml = $this->controller->handleRequest( $args );

		$this->assertXPathMatch( 1, 'count(//cas:proxyFailure)', $xml,
			"'proxy' should not validate proxy tickets." );

		$this->assertXPathMatch( TicketException::ERROR_BAD_PGT, 'string(//cas:proxyFailure[1]/@code)', $xml,
			'BAD_PGT error code on service ticket.' );

		/**
		 * /proxy validates a Proxy-Granting Ticket successfully.
		 */

		Cassava\Options::set( 'allow_ticket_reuse', 1 );

		$args = array(
			'targetService' => $targetService,
			'pgt'           => preg_replace( '@^' . CAS\Ticket::TYPE_ST . '@', CAS\Ticket::TYPE_PGT, $query['ticket'] ),
			);

		$xml = $this->controller->handleRequest( $args );

		$this->assertXPathMatch( 1, 'count(//cas:proxySuccess)', $xml,
			'Successful validation on proxy-granting ticket.' );

		$this->assertXPathMatch( 1, 'count(//cas:proxySuccess/cas:proxyTicket)', $xml,
			"'/proxy' response returns a proxy ticket." );

		$proxyTicket = $this->xpathEvaluate( 'string(//cas:proxySuccess[1]/cas:proxyTicket[1])', $xml );

		$args = array(
			'service' => $targetService,
			'ticket'  => $proxyTicket,
			);

		$proxyValidate = new CAS\Controller\ProxyValidateController( $this->server );
		$xml = $proxyValidate->handleRequest( $args );

		$this->assertXPathMatch( 1, 'count(//cas:authenticationSuccess)', $xml,
			"'/proxy' response returns a valid proxy ticket." );

		/**
		 * Do not enforce single-use tickets.
		 */

		$args = array(
			'targetService' => $targetService,
			'pgt'           => preg_replace( '@^' . CAS\Ticket::TYPE_ST . '@', CAS\Ticket::TYPE_PGT, $query['ticket'] ),
			);

		$xml = $this->controller->handleRequest( $args );

		$this->assertXPathMatch( 1, 'count(//cas:proxySuccess)', $xml,
			'Settings allow ticket reuse.' );

		/**
		 * Enforce single-use tickets.
		 */

		Cassava\Options::set( 'allow_ticket_reuse', 0 );

		$error = $this->controller->handleRequest( $args );

		$this->assertXPathMatch( 1, 'count(//cas:proxyFailure)', $error,
			"Settings do not allow ticket reuse." );

		$this->assertXPathMatch( TicketException::ERROR_BAD_PGT, 'string(//cas:proxyFailure[1]/@code)', $error,
			'BAD_PGT error code on ticket reuse.' );
	}

}

