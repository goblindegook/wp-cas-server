<?php

use Cassava\Exception\RequestException;

/**
 * @coversDefaultClass \Cassava\CAS\Server
 */
class WP_TestWPCASServer extends WPCAS_UnitTestCase {

	private $server;
	private $routes;

	function setUp () {
		parent::setUp();
		$this->server = new \Cassava\CAS\Server;
		$this->routes = $this->server->routes();
	}

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

		$this->assertXPathMatch( RequestException::ERROR_INVALID_REQUEST, 'string(//cas:authenticationFailure[1]/@code)', $error,
			'Handling invalid endpoint returns an invalid request error.' );

		$this->markTestIncomplete();
	}

}

