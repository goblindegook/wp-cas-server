<?php

use Cassava\CAS;

/**
 * @coversDefaultClass \Cassava\CAS\Controller\LoginController
 */
class TestWPCASControllerLoginRequestor extends WPCAS_UnitTestCase {

	private $controller;

	function setUp() {
		parent::setUp();
		$this->controller = new CAS\Controller\LoginController( new CAS\Server );
	}

	function tearDown() {
		parent::tearDown();
		unset( $this->controller );
	}

	/**
	 * @covers ::__construct
	 */
	function test_construct () {
		$this->assertTrue( is_a( $this->controller, '\Cassava\CAS\Controller\BaseController' ),
			'LoginController extends BaseController.' );
	}

	/**
	 * Tests /login requestor behaviour when the user is logged out.
	 *
	 * @runInSeparateProcess
	 * @dataProvider data_login
	 * @covers ::login
	 */
	function test_login ( $service, $gateway, $expected ) {

		wp_set_current_user( false );

		try {
			$this->controller->handleRequest( array(
				'service' => $service,
				'gateway' => $gateway,
			) );
		}
		catch ( WPDieException $message ) {
			parse_str( parse_url( $this->redirect_location, PHP_URL_QUERY ) );
		}

		$this->assertStringStartsWith( $expected, $this->redirect_location,
			"'login' performs a redirect when no user is authenticated." );
	}

	/**
	 * @return array Test data for login tests.
	 */
	function data_login() {
		return array(
			array( 'http://test/', 'true', 'http://test/' ),
			array( 'http://test/', null, home_url() ),
			array( null, 'true', home_url() ),
			array( null, null, home_url() ),
		);
	}

	/**
	 * Tests /login requestor behaviour when the user is already logged in.
	 *
	 * - No service provided.
	 * - No forced renewal.
	 *
	 * @runInSeparateProcess
	 * @covers ::login
	 */
	function test_login_user () {

		wp_set_current_user( $this->factory->user->create() );

		try {
			$this->controller->handleRequest( array() );
		}
		catch ( WPDieException $message ) {
			parse_str( parse_url( $this->redirect_location, PHP_URL_QUERY ) );
		}

		$this->assertStringStartsWith( home_url(), $this->redirect_location,
			"'login' redirects to home when no service is provided." );
	}

	/**
	 * Tests /login requestor behaviour when the user is already logged in.
	 *
	 * - Using service.
	 * - No forced renewal.
	 *
	 * @runInSeparateProcess
	 * @covers ::login
	 */
	function test_login_user_service () {

		$service = 'http://test/';

		wp_set_current_user( $this->factory->user->create() );

		try {
			$this->controller->handleRequest( array( 'service' => $service ) );
		}
		catch ( WPDieException $message ) {
			parse_str( parse_url( $this->redirect_location, PHP_URL_QUERY ), $query );
		}

		$this->assertNotEmpty( $query['ticket'],
			"'login' generates ticket." );

		$this->assertStringStartsWith( CAS\Ticket::TYPE_ST, $query['ticket'],
			"'login' generates a service ticket." );

		$this->assertStringStartsWith( $service, $this->redirect_location,
			"'login' redirects to provided service." );

		// Repeat request, user logged in.

		try {
			$this->controller->handleRequest( array( 'service' => $service ) );
		}
		catch ( WPDieException $message ) {
			parse_str( parse_url( $this->redirect_location, PHP_URL_QUERY ), $another_query );
		}

		$this->assertNotEquals( $query['ticket'], $another_query['ticket'],
			"'login' generates different tickets." );
	}

	/**
	 * Tests /login requestor behaviour when the user is already logged in.
	 *
	 * - Using service.
	 * - Forced renewal.
	 *
	 * @runInSeparateProcess
	 * @covers ::login
	 */
	function test_login_user_renew () {

		$service = 'http://test/';

		wp_set_current_user( $this->factory->user->create() );

		try {
			$this->controller->handleRequest( array( 'service' => $service, 'renew' => 'true' ) );
		}
		catch ( WPDieException $message ) {
			parse_str( parse_url( $this->redirect_location, PHP_URL_QUERY ), $query );
		}

		$this->assertStringStartsWith( home_url(), $this->redirect_location,
			"'login' redirects to login screen when user is forced to renew credentials." );

		$this->assertFalse( isset( $query['ticket'] ),
			"'login' generates no ticket before renewing credentials." );
	}

}
