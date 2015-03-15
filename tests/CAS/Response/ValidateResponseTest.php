<?php

use Cassava\CAS;

/**
 * @coversDefaultClass \Cassava\CAS\Response\ValidateResponse
 */
class TestWPCASResponseValidateResponse extends WPCAS_UnitTestCase {

	private $response;

	function setUp() {
		parent::setUp();
		$this->response = new CAS\Response\ValidateResponse();
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
			'ValidateResponse extends BaseResponse.' );
	}

}
