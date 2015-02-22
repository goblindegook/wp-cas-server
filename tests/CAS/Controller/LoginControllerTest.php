<?php

use Cassava\CAS;

/**
 * @coversDefaultClass \Cassava\CAS\Controller\LoginController
 */
class TestWPCASControllerLogin extends WPCAS_UnitTestCase {

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
	 * Tests /login requestor behaviour.
	 *
	 * @runInSeparateProcess
	 * @covers ::login
	 */
	function test_login_requestor () {

		$service = 'http://test/';

		/**
		 * /login?gateway=true&service=http://test/ (user logged out)
		 */

		wp_set_current_user( false );

		try {
			$this->controller->handleRequest( array( 'service' => $service, 'gateway' => 'true' ) );
		}
		catch ( WPDieException $message ) {
			parse_str( parse_url( $this->redirect_location, PHP_URL_QUERY ), $query );
		}

		$this->assertStringStartsWith( $service, $this->redirect_location,
			"'login' redirects to service when acting as a gateway and no user is authenticated." );

		/**
		 * /login?service=http://test/ (user logged out)
		 */

		try {
			$this->controller->handleRequest( array( 'service' => $service ) );
		}
		catch ( WPDieException $message ) {
			parse_str( parse_url( $this->redirect_location, PHP_URL_QUERY ), $query );
		}

		$this->assertStringStartsWith( home_url(), $this->redirect_location,
			"'login' redirects to authentication screen when no user is authenticated." );

		/**
		 * /login (user logged in)
		 */

		$user_id = $this->factory->user->create();

		wp_set_current_user( $user_id );

		try {
			$this->controller->handleRequest( array() );
		}
		catch ( WPDieException $message ) {
			parse_str( parse_url( $this->redirect_location, PHP_URL_QUERY ), $query );
		}

		$this->assertStringStartsWith( home_url(), $this->redirect_location,
			"'login' redirects to home when no service is provided." );

		/**
		 * /login?service=http://test/ (user logged in)
		 */

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

		/**
		 * /login?service=http://test/ (repeat request, user logged in)
		 */

		try {
			$this->controller->handleRequest( array( 'service' => $service ) );
		}
		catch ( WPDieException $message ) {
			parse_str( parse_url( $this->redirect_location, PHP_URL_QUERY ), $another_query );
		}

		$this->assertNotEquals( $query['ticket'], $another_query['ticket'],
			"'login' generates different tickets." );

		/**
		 * /login?renew=true&service=http://test/ (user logged in)
		 */

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

	/**
	 * Tests /login acceptor behaviour.
	 *
	 * @runInSeparateProcess
	 * @covers ::login
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

		wp_set_current_user( false );

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

		/**
		 * /login?service=http://test/ (invalid credentials, valid login ticket)
		 */

		wp_set_current_user( false );

		$_POST = array(
			'username' => $username,
			'password' => wp_generate_password( 6 ),
			'lt'       => wp_create_nonce( 'lt' ),
			);

		try {
			$this->controller->handleRequest( array( 'service' => $service ) );
		}
		catch ( WPDieException $message ) {
			parse_str( parse_url( $this->redirect_location, PHP_URL_QUERY ), $query );
		}

		$this->assertStringStartsWith( home_url(), $this->redirect_location,
			"'login' redirects to login screen when credentials are invalid" );

		$this->assertFalse( isset( $query['ticket'] ),
			"'login' generates no ticket before validating credentials." );

		/**
		 * /login?service=http://test/ (valid credentials, invalid login ticket)
		 */

		wp_set_current_user( false );

		$_POST = array(
			'username' => $username,
			'password' => $password,
			'lt'       => wp_create_nonce( 'bad-lt' ),
			);

		try {
			$this->controller->handleRequest( array( 'service' => $service ) );
		}
		catch ( WPDieException $message ) {
			parse_str( parse_url( $this->redirect_location, PHP_URL_QUERY ), $query );
		}

		$this->assertStringStartsWith( home_url(), $this->redirect_location,
			"'login' redirects to login screen when the login ticket is invalid" );

		$this->assertFalse( isset( $query['ticket'] ),
			"'login' generates no ticket before validating the login ticket." );

		/**
		 * /login?service=http://test/ (invalid credentials, invalid login ticket)
		 */

		wp_set_current_user( false );

		$_POST = array(
			'username' => $username,
			'password' => wp_generate_password( 6 ),
			'lt'       => wp_create_nonce( 'bad-lt' ),
		);

		try {
			$this->controller->handleRequest( array( 'service' => $service ) );
		}
		catch ( WPDieException $message ) {
			parse_str( parse_url( $this->redirect_location, PHP_URL_QUERY ), $query );
		}

		$this->assertStringStartsWith( home_url(), $this->redirect_location,
			"'login' redirects to login screen when both credentials and login ticket are invalid" );

		$this->assertFalse( isset( $query['ticket'] ),
			"'login' does not generate a ticket before validating credentials and the login ticket." );

		$this->markTestIncomplete( 'Test support for the optional "warn" parameter.' );
	}

}

