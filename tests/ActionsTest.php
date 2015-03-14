<?php
/**
 * @package WPCASServerPlugin
 * @subpackage Tests
 */

class WP_TestWPCASServerPluginActions extends WP_UnitTestCase {

	private $plugin;
	private $server;
	private $redirect_location;

	/**
	 * Setup a test method for the WP_TestWPCASServerPluginActions class.
	 */
	function setUp () {
		parent::setUp();
		$this->plugin = $GLOBALS[ Cassava\Plugin::SLUG ];
		$this->server = new Cassava\CAS\Server;
		add_filter( 'wp_redirect', array( $this, 'wp_redirect_handler' ) );
	}

	/**
	 * Finish a test method for the WP_TestWPCASServerPluginActions class.
	 */
	function tearDown () {
		parent::tearDown();
		unset( $this->plugin );
		unset( $this->redirect_location );
		remove_filter( 'wp_redirect', array( $this, 'wp_redirect_handler' ) );
	}

	/**
	 * Callback triggered on WordPress redirects.
	 *
	 * It saves the redirect location to a test case private attribute and throws a
	 * `WPDieException` to prevent PHP from terminating immediately after the redirect.
	 *
	 * @param  string $location URI for WordPress to redirect to.
	 *
	 * @throws WPDieException Thrown to signal redirects and prevent tests from terminating.
	 */
	function wp_redirect_handler ( $location ) {
		$this->redirect_location = $location;
		throw new WPDieException( "Redirecting to $location" );
	}

	/**
	 * Test whether a WordPress action is called for a given function and set of arguments.
	 * @param  string  $label    Action label to test.
	 * @param  mixed   $function String or array with the function to execute.
	 * @param  array   $args     Ordered list of arguments to pass the function.
	 * @return boolean           Whether the given action was called.
	 */
	private function _assertActionIsCalled ( $label, $function, $args ) {
		$mock = $this->getMock( 'stdClass', array( 'action' ) );
		$mock->expects( $this->once() )->method( 'action' );

		add_filter( $label, array( $mock, 'action' ) );
		ob_start();

		try {
			call_user_func_array( $function, $args );
		}
		catch ( WPDieException $message ) {
			ob_end_clean();
			remove_filter( $label, array( $mock, 'action' ) );
			return;
		}

		// finally not supported in PHP 5.3 and 5.4
		ob_end_clean();
		remove_filter( $label, array( $mock, 'action' ) );
	}

	/**
	 * @group action
	 */
	function test_cas_server_before_request () {
		$action   = 'cas_server_before_request';
		$function = array( $this->server, 'handleRequest' );
		$args     = array( 'invalid-uri' );

		$this->_assertActionIsCalled( $action, $function, $args );
	}

	/**
	 * @group action
	 */
	function test_cas_server_after_request () {
		$action   = 'cas_server_after_request';
		$function = array( $this->server, 'handleRequest' );
		$args     = array( 'invalid-uri' );

		$this->_assertActionIsCalled( $action, $function, $args );
	}

	/**
	 * @group action
	 */
	function test_cas_server_error () {
		$action   = 'cas_server_error';
		$function = array( $this->server, 'handleRequest' );
		$args     = array( 'invalid-uri' );

		$this->_assertActionIsCalled( $action, $function, $args );
	}

	/**
	 * @group action
	 */
	function test_cas_server_validation_success () {
		$action     = 'cas_server_validation_success';
		$controller = new Cassava\CAS\Controller\ServiceValidateController( $this->server );
		$function   = array( $controller, 'handleRequest' );

		$service = 'http://test/';

		wp_set_current_user( $this->factory->user->create() );

		try {
			$loginController = new Cassava\CAS\Controller\LoginController( $this->server );
			$loginController->handleRequest( array( 'service' => $service ) );
		}
		catch (WPDieException $message) {
			parse_str( parse_url( $this->redirect_location, PHP_URL_QUERY ), $query );
		}

		Cassava\Options::set( 'attributes', array( 'user_email' ) );

		$args = array(
			'service' => $service,
			'ticket'  => $query['ticket'],
			);

		$this->_assertActionIsCalled( $action, $function, array( $args ) );
	}

}
