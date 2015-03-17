<?php

use Cassava\CAS;
use Cassava\Options;

/**
 * @coversDefaultClass \Cassava\CAS\Response\ValidateResponse
 */
class TestWPCASResponseValidateResponse extends WPCAS_UnitTestCase {

	private $user;

	private $ticket;

	private $response;

	function setUp() {
		parent::setUp();

		$type           = CAS\Ticket::TYPE_ST;
		$service        = 'https://test/';
		$this->user     = get_user_by( 'id', $this->factory->user->create() );
		$this->ticket   = new CAS\Ticket( $type, $this->user, $service );
		$this->response = new CAS\Response\ValidateResponse();
	}

	function tearDown() {
		parent::tearDown();
		unset( $this->user );
		unset( $this->ticket );
		unset( $this->response );
	}

	/**
	 * @covers ::__construct
	 */
	function test_construct () {
		$this->assertTrue( is_a( $this->response, '\Cassava\CAS\Response\BaseResponse' ),
			'ValidateResponse extends BaseResponse.' );
	}

	/**
	 * @covers ::prepare
	 * @covers ::setTicket
	 */
	function test_setTicket() {

		$this->response->setTicket( $this->ticket );

		$xml = $this->response->prepare();

		$this->assertXPathMatch( 1, 'count(//cas:authenticationSuccess)', $xml,
			'Successful response has a authenticationSuccess tag.' );

		$this->assertXPathMatch( $this->user->user_login,
			'string(//cas:authenticationSuccess/cas:user/text())', $xml,
			'Successful response contains the logged in username.' );

		$this->assertXPathMatch( 0, 'count(//cas:proxyGrantingTicket)', $xml,
			'Response has no proxyGrantingTicket tag if no PGT provided.' );

		$this->assertXPathMatch( 0, 'count(//cas:proxies)', $xml,
			'Response has no proxies tag if no proxies provided.' );

		$this->response->setTicket( $this->ticket );

		$xml = $this->response->prepare();

		$this->assertXPathMatch( 1, 'count(//cas:authenticationSuccess)', $xml,
			'setTicket is an idempotent operation.' );
	}

	/**
	 * @covers ::prepare
	 * @covers ::setTicket
	 */
	function test_setTicket_proxyGrantingTicket() {
		$pgt = 'test';

		$this->response->setTicket( $this->ticket, $pgt );

		$xml = $this->response->prepare();

		$this->assertXPathMatch( 1, 'count(//cas:proxyGrantingTicket)', $xml,
			'Response contains a proxyGrantingTicket tag.' );

		$this->assertXPathMatch( $pgt, 'string(//cas:proxyGrantingTicket/text())', $xml,
			'Response contains the provided proxy-granting ticket.' );
	}

	/**
	 * @covers ::prepare
	 * @covers ::setTicket
	 */
	function test_setTicket_proxies() {
		$proxies = array( 'test1', 'test2', 'test3' );

		$this->response->setTicket( $this->ticket, null, $proxies );

		$xml = $this->response->prepare();

		$this->assertXPathMatch( 3, 'count(//cas:proxies/cas:proxy)', $xml,
			'Response contains proxy information.' );

		foreach ( $proxies as $idx => $proxy ) {
			$x_idx = $idx + 1;
			$this->assertXPathMatch( $proxy, "string(//cas:proxy[$x_idx]/text())", $xml,
				'Response contains each of the provided proxies.' );
		}
	}

	/**
	 * @covers ::prepare
	 * @covers ::setTicket
	 * @covers ::setUserAttributes
	 *
	 * @dataProvider data_setUserAttributes
	 */
	function test_setUserAttributes( $attributes ) {
		Options::set( 'attributes', $attributes );

		$this->response->setTicket( $this->ticket );

		$xml = $this->response->prepare();

		$this->assertXPathMatch( count( $attributes ), 'count(//cas:attributes/*)', $xml,
			'Response contains the expected number of attributes.' );

		foreach ( $attributes as $attribute ) {
			$expected = $this->user->get( $attribute );

			$this->assertXPathMatch( $expected, "string(//cas:attributes/cas:$attribute/text())", $xml,
				'Response contains the expected attribute value.' );
		}
	}

	/**
	 * @return array Test data for ::setUserAttributes().
	 */
	function data_setUserAttributes() {
		return array(
			array( array() ),
			array( array( 'ID' ) ),
			array( array( 'user_nicename', 'user_email' ) ),
		);
	}

	/**
	 * @covers ::prepare
	 * @covers ::setTicket
	 * @covers ::setUserAttributes
	 *
	 * @dataProvider data_setUserAttributes_filter
	 */
	function test_setUserAttributes_filter( $attributes ) {

		$callback = function () use ( $attributes ) {
			return $attributes;
		};

		add_filter( 'cas_server_validation_user_attributes', $callback, 10, 2 );

		$this->response->setTicket( $this->ticket );

		$xml = $this->response->prepare();

		$this->assertXPathMatch( count( $attributes ), 'count(//cas:attributes/*)', $xml,
			'Response contains the expected number of filtered attributes.' );

		foreach ( $attributes as $attribute => $expected ) {
			$this->assertXPathMatch( $expected, "string(//cas:attributes/cas:$attribute/text())", $xml,
				'Response contains the expected filtered attribute value.' );
		}

		remove_filter( 'cas_server_validation_user_attributes', $callback );
	}

	/**
	 * @return array Test data for ::test_setUserAttributes_filter().
	 */
	function data_setUserAttributes_filter() {
		return array(
			array( array() ),
			array( array( 'int' => 2 ) ),
			array( array( 'float' => 2.5 ), array( 'string' => 'test' ) ),
		);
	}

}
