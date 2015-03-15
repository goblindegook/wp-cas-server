<?php

use Cassava\CAS;

/**
 * @coversDefaultClass \Cassava\CAS\Response\BaseResponse
 */
class TestWPCASResponseBaseResponse extends WPCAS_UnitTestCase {

	private $response;

	function setUp() {
		parent::setUp();
		$this->response = new CAS\Response\BaseResponse();
		wp_set_current_user( false );
	}

	function tearDown() {
		parent::tearDown();
		unset( $this->response );
	}

	/**
	 * @covers ::__construct
	 * @covers ::prepare
	 */
	function test_prepare() {
		$xml = $this->response->prepare();
		$this->assertXPathMatch( 1, 'count(//cas:serviceResponse)', $xml,
			'Default response is wrapped in a serviceResponse tag.' );
	}

	/**
	 * @covers ::prepare
	 * @covers ::setError
	 * @dataProvider data_setError
	 */
	function test_setError( $error, $expectedCode, $tag, $expectedTag ) {

		if ( ! empty( $tag ) ) {
			$this->response->setError( $error, $tag );
		} else {
			$this->response->setError( $error );
		}

		$xml           = $this->response->prepare();
		$xpathTagCount = "count(//cas:$expectedTag)";
		$xpathCode     = "string(//cas:${expectedTag}[1]/@code)";

		$this->assertXPathMatch( 1, $xpathTagCount, $xml );

		$this->assertXPathMatch( $expectedCode, $xpathCode, $xml );

		if ( \is_wp_error( $error ) ) {
			$code         = $error->get_error_code();
			$message      = $error->get_error_message( $code );
			$xpathMessage = "string(//cas:${expectedTag}[1]/text())";

			$this->assertXPathMatch( $message, $xpathMessage, $xml );
		}
	}

	/**
	 * @return array Test data for ::test_setError().
	 */
	function data_setError() {
		$defaultCode = \Cassava\Exception\GeneralException::ERROR_INTERNAL_ERROR;
		$defaultTag  = 'authenticationFailure';

		return array(
			array( null, $defaultCode, null, $defaultTag ),
			array( null, $defaultCode, 'testTag', 'testTag' ),
			array( new \WP_Error( 'test', 'message' ), 'test', null, $defaultTag ),
			array( new \WP_Error( 'test', 'message' ), 'test', 'testTag', 'testTag' ),
			array( new \WP_Error( 'test', '' ), 'test', 'testTag', 'testTag' ),
			array( new \WP_Error( '', 'message' ), '', 'testTag', 'testTag' ),
			array( new \WP_Error( 'test', null ), 'test', 'testTag', 'testTag' ),
			array( new \WP_Error( null, 'message' ), null, 'testTag', 'testTag' ),
		);
	}

}
