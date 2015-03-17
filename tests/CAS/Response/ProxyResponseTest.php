<?php

use Cassava\CAS;

/**
 * @coversDefaultClass \Cassava\CAS\Response\ProxyResponse
 */
class TestWPCASResponseProxyResponse extends WPCAS_UnitTestCase {

	private $response;

	function setUp() {
		parent::setUp();
		$this->response = new CAS\Response\ProxyResponse();
		wp_set_current_user( false );
	}

	function tearDown() {
		parent::tearDown();
		unset( $this->response );
	}

	/**
	 * @covers ::__construct
	 */
	function test_construct () {
		$this->assertTrue( is_a( $this->response, '\Cassava\CAS\Response\BaseResponse' ),
			'ProxyResponse extends BaseResponse.' );
	}

	/**
	 * @covers ::prepare
	 * @covers ::setTicket
	 */
	function test_setTicket() {
		$type    = CAS\Ticket::TYPE_PT;
		$user    = get_user_by( 'id', $this->factory->user->create() );
		$service = 'https://test/';

		$ticket = new CAS\Ticket( $type, $user, $service );

		$this->response->setTicket( $ticket );

		$xml = $this->response->prepare();

		$this->assertXPathMatch( 1, 'count(//cas:proxySuccess)', $xml,
			'Successful response has a proxySuccess tag.' );

		$this->assertXPathMatch( (string) $ticket, 'string(//cas:proxySuccess/cas:proxyTicket/text())', $xml,
			'Response contains the set ticket.' );

		$this->response->setTicket( $ticket );

		$xml = $this->response->prepare();

		$this->assertXPathMatch( 1, 'count(//cas:proxySuccess)', $xml,
			'setTicket is an idempotent operation.' );
	}

}
