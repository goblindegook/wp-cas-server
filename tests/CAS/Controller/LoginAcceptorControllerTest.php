<?php

use Cassava\CAS;

/**
 * @coversDefaultClass \Cassava\CAS\Controller\LoginController
 */
class TestWPCASControllerLoginAcceptor extends WPCAS_UnitTestCase {

	private $controller;

	function setUp() {
		parent::setUp();
		$this->controller = new CAS\Controller\LoginController( new CAS\Server );
		wp_set_current_user( false );
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
	 * Tests /login acceptor behaviour.
	 *
	 * @covers ::login
	 * @runInSeparateProcess
	 *
	 * @todo Resolve errors with Travis's PHPUnit 4.0.14 around attempts to set headers
	 *       in spite of process isolation.
	 * @todo Test support for the optional 'warn' parameter.
	 */
	function test_login_acceptor () {

		$user = get_user_by( 'id', $this->factory->user->create() );

		$service  = 'http://test/';
		$username = $user->user_login;
		$password = wp_generate_password( 12 );

		wp_set_password( $password, $user->ID );

		/**
		 * /login?service=http://test/ (valid credentials, valid login ticket)
		 */

		$_POST = array(
			'username' => $username,
			'password' => $password,
			'lt'       => wp_create_nonce( 'lt' ),
		);

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
	}

	/**
	 * Tests /login acceptor behaviour.
	 *
	 * @covers ::login
	 * @dataProvider data_login_errors
	 * @runInSeparateProcess
	 *
	 * @todo Test support for the optional 'warn' parameter.
	 */
	function test_login_acceptor_errors( $password, $request, $messages ) {
		$service  = 'http://test/';
		$user     = get_user_by( 'id', $this->factory->user->create() );
		$username = $user->user_login;

		wp_set_password( $password, $user->ID );

		$_POST             = $request;
		$_POST['username'] = $username;

		try {
			$this->controller->handleRequest( array( 'service' => $service ) );
		}
		catch ( WPDieException $message ) {
			parse_str( parse_url( $this->redirect_location, PHP_URL_QUERY ), $query );
		}

		$this->assertStringStartsWith( home_url(), $this->redirect_location, $messages['redirect'] );

		$this->assertFalse( isset( $query['ticket'] ), $messages['ticket'] );

		// $this->markTestIncomplete( 'Test support for the optional "warn" parameter.' );
	}

	/**
	 * @return array Test data for login tests.
	 */
	function data_login_errors() {
		$password = wp_generate_password( 12 );

		return array(
			array(
				$password,
				array(
					'password' => wp_generate_password( 6 ),
					'lt'       => wp_create_nonce( 'lt' ),
				),
				array(
					'redirect' => "'login' redirects to login screen when credentials are invalid",
					'ticket'   => "'login' generates no ticket before validating credentials.",
				),
			),
			array(
				$password,
				array(
					'password' => $password,
					'lt'       => wp_create_nonce( 'bad-lt' ),
				),
				array(
					'redirect' => "'login' redirects to login screen when the login ticket is invalid",
					'ticket'   => "'login' generates no ticket before validating the login ticket.",
				),
			),
			array(
				$password,
				array(
					'password' => wp_generate_password( 6 ),
					'lt'       => wp_create_nonce( 'bad-lt' ),
				),
				array(
					'redirect' => "'login' redirects to login screen when both credentials and login ticket are invalid",
					'ticket'   => "'login' does not generate a ticket before validating credentials and the login ticket.",
				),
			),
		);
	}

}

