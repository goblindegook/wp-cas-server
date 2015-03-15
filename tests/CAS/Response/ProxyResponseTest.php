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

}
