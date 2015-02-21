<?php
/**
 * @package \WPCASServerPlugin\Tests
 */

/**
 * @coversDefaultClass WPCASServer
 */
class WP_TestWPCASServer extends WPCAS_UnitTestCase {

	private $server;
	private $routes;

	/**
	 * Setup a test method for the WPCASServer class.
	 */
	function setUp () {
		parent::setUp();
		$this->server = new WPCASServer;
		$this->routes = $this->server->routes();
	}

	/**
	 * Finish a test method for the CASServer class.
	 */
	function tearDown () {
		parent::tearDown();
		unset( $this->server );
	}

	/**
	 * @runInSeparateProcess
	 * @covers ::redirect
	 */
	function test_redirect() {
		$service = 'http://test/';

		try {
			$this->server->redirect( $service );
		}
		catch (WPDieException $message) {
			parse_str( parse_url( $this->redirect_location, PHP_URL_QUERY ), $query );
		}

		$this->assertStringStartsWith( $service, $this->redirect_location,
			'Server redirects to a URL.' );
	}

	/**
	 * @runInSeparateProcess
	 * @covers ::authRedirect
	 */
	function test_authRedirect() {
		$service  = 'http://test/';
		$loginUrl = function () { return 'http://custom-login/'; };

		try {
			$this->server->authRedirect( array( 'service' => $service ) );
		}
		catch (WPDieException $message) {
			parse_str( parse_url( $this->redirect_location, PHP_URL_QUERY ), $query );
		}

		$this->assertStringStartsWith( home_url(), $this->redirect_location,
			'Server redirects to the authentication screen.' );

		add_filter( 'cas_server_custom_auth_uri', $loginUrl );

		try {
			$this->server->authRedirect( array( 'service' => $service ) );
		}
		catch (WPDieException $message) {
			parse_str( parse_url( $this->redirect_location, PHP_URL_QUERY ), $query );
		}

		$this->assertStringStartsWith( $loginUrl(), $this->redirect_location,
			'Server redirects to a custom login URL.' );
	}

	/**
	 * @covers ::routes
	 */
	function test_routes () {
		$routes = array(
			'login',
			'logout',
			'validate',
			'proxy',
			'proxyValidate',
			'serviceValidate',
			'p3/proxyValidate',
			'p3/serviceValidate',
			);

		$server_routes = $this->server->routes();

		foreach ($routes as $route) {
			$this->assertArrayHasKey( $route, $server_routes,
				"Route '$route' has a callback." );
			$this->assertTrue( is_callable( $server_routes[$route] ),
				"Method for route '$route' is callable." );
		}
	}

	/**
	 * @covers ::handleRequest
	 * @todo
	 */
	function test_handleRequest () {

		$this->assertTrue( is_callable( array( $this->server, 'handleRequest' ) ),
			"'handleRequest' method is callable." );

		$error = $this->server->handleRequest( 'invalid-endpoint' );

		$this->assertTrue( defined( 'CAS_REQUEST' ), 'handleRequest defines CAS_REQUEST constant.');

		$this->assertTrue( CAS_REQUEST, 'handleRequest sets CAS_REQUEST constant to true.');

		$this->assertXPathMatch( 1, 'count(//cas:authenticationFailure)', $error,
			"Handling invalid endpoint returns an error." );

		$this->assertXPathMatch( WPCASRequestException::ERROR_INVALID_REQUEST, 'string(//cas:authenticationFailure[1]/@code)', $error,
			'Handling invalid endpoint returns an invalid request error.' );

		$this->markTestIncomplete();
	}

	/**
	 * Tests /login requestor behaviour.
	 *
	 * @runInSeparateProcess
	 * @covers ::login
	 * @covers ::_loginRequestor
	 */
	function test_login_requestor () {

		$this->assertTrue( is_callable( array( $this->server, 'login' ) ),
			"'login' method is callable." );

		$service = 'http://test/';

		/**
		 * /login?gateway=true&service=http://test/ (user logged out)
		 */

		wp_set_current_user( false );

		try {
			$this->server->login( array( 'service' => $service, 'gateway' => 'true' ) );
		}
		catch (WPDieException $message) {
			parse_str( parse_url( $this->redirect_location, PHP_URL_QUERY ), $query );
		}

		$this->assertStringStartsWith( $service, $this->redirect_location,
			"'login' redirects to service when acting as a gateway and no user is authenticated." );

		/**
		 * /login?service=http://test/ (user logged out)
		 */

		try {
			$this->server->login( array( 'service' => $service ) );
		}
		catch (WPDieException $message) {
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
			$this->server->login( array() );
		}
		catch (WPDieException $message) {
			parse_str( parse_url( $this->redirect_location, PHP_URL_QUERY ), $query );
		}

		$this->assertStringStartsWith( home_url(), $this->redirect_location,
			"'login' redirects to home when no service is provided." );

		/**
		 * /login?service=http://test/ (user logged in)
		 */

		try {
			$this->server->login( array( 'service' => $service ) );
		}
		catch (WPDieException $message) {
			parse_str( parse_url( $this->redirect_location, PHP_URL_QUERY ), $query );
		}

		$this->assertNotEmpty( $query['ticket'],
			"'login' generates ticket." );

		$this->assertStringStartsWith( WPCASTicket::TYPE_ST, $query['ticket'],
			"'login' generates a service ticket." );

		$this->assertStringStartsWith( $service, $this->redirect_location,
			"'login' redirects to provided service." );

		/**
		 * /login?service=http://test/ (repeat request, user logged in)
		 */

		try {
			$this->server->login( array( 'service' => $service ) );
		}
		catch (WPDieException $message) {
			parse_str( parse_url( $this->redirect_location, PHP_URL_QUERY ), $another_query );
		}

		$this->assertNotEquals( $query['ticket'], $another_query['ticket'],
			"'login' generates different tickets." );

		/**
		 * /login?renew=true&service=http://test/ (user logged in)
		 */

		try {
			$this->server->login( array( 'service' => $service, 'renew' => 'true' ) );
		}
		catch (WPDieException $message) {
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
	 * @covers ::_loginAcceptor
	 *
	 * @todo Resolve errors with Travis's PHPUnit 4.0.14 around attempts to set headers
	 *       in spite of process isolation.
	 * @todo Test support for the optional 'warn' parameter.
	 */
	function test_login_acceptor () {

		$this->markTestIncomplete();

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
			$this->server->login( array( 'service' => $service ) );
		}
		catch (WPDieException $message) {
			parse_str( parse_url( $this->redirect_location, PHP_URL_QUERY ), $query );
		}

		$this->assertNotEmpty( $query['ticket'],
			"'login' generates ticket." );

		$this->assertStringStartsWith( WPCASTicket::TYPE_ST, $query['ticket'],
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
			$this->server->login( array( 'service' => $service ) );
		}
		catch (WPDieException $message) {
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
			$this->server->login( array( 'service' => $service ) );
		}
		catch (WPDieException $message) {
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
			$this->server->login( array( 'service' => $service ) );
		}
		catch (WPDieException $message) {
			parse_str( parse_url( $this->redirect_location, PHP_URL_QUERY ), $query );
		}

		$this->assertStringStartsWith( home_url(), $this->redirect_location,
			"'login' redirects to login screen when both credentials and login ticket are invalid" );

		$this->assertFalse( isset( $query['ticket'] ),
			"'login' does not generate a ticket before validating credentials and the login ticket." );

		$this->markTestIncomplete( 'Test support for the optional "warn" parameter.' );
	}

}

