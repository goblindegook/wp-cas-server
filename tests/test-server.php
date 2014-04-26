<?php
/**
 * @package WPCASServerPlugin
 * @subpackage WPCASServerPlugin_Tests
 */

/**
 * @coversDefaultClass WPCASServer
 */
class WP_TestWPCASServer extends WP_UnitTestCase {

    private $server;
    private $routes;
    private $redirect_location;

    /**
     * Setup a test method for the WPCASServer class.
     */
    function setUp () {
        parent::setUp();
        $this->server = new WPCASServer;
        $this->routes = $this->server->routes();

        add_filter( 'wp_redirect', array( $this, 'wp_redirect_handler' ) );
    }

    /**
     * Finish a test method for the CASServer class.
     */
    function tearDown () {
        parent::tearDown();
        unset( $this->server );
        unset( $this->redirect_location );
        unset( $this->redirect_status );

        remove_filter( 'wp_redirect', array( $this, 'wp_redirect_handler' ) );
    }

    function wp_redirect_handler ( $location ) {
        $this->redirect_location = $location;
        throw new WPDieException( "Redirecting to $location" );
    }

    function test_interface () {
        $this->assertArrayHasKey( 'ICASServer', class_implements( $this->server ),
            'WPCASServer implements the ICASServer interface.' );
    }

    /**
     * @covers ::routes
     */
    function test_routes () {
        $routes = array(
            'login',
            'logout',
            'proxy',
            'proxyValidate',
            'serviceValidate',
            'validate',
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

        $this->markTestIncomplete();
    }

    /**
     * Tests /login requestor behaviour.
     * 
     * @covers ::login
     */
    function test_login_requestor () {

        $this->assertTrue( is_callable( array( $this->server, 'login' ) ),
            "'login' method is callable." );

        $service = 'http://test/';

        wp_set_current_user( false );

        /**
         * /login?gateway=true&service=http://test/ (user logged out)
         */

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
            parse_str( parse_url( $this->redirect_location, PHP_URL_QUERY ), $query_repeated );
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

        $this->assertStringStartsWith( ICASServer::TYPE_ST, $query['ticket'],
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
     * @covers ::login
     * @runInSeparateProcess
     * 
     * @todo Test support for the optional "warn" parameter.
     */
    function test_login_acceptor () {

        $user = get_user_by( 'id', $this->factory->user->create() );

        $service  = 'http://test/';
        $username = $user->user_login;
        $password = wp_generate_password();

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

        $this->assertStringStartsWith( ICASServer::TYPE_ST, $query['ticket'],
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
            "'login' redirects to login screen when user is forced to renew credentials." );

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
            "'login' redirects to login screen when user is forced to renew credentials." );

        $this->assertFalse( isset( $query['ticket'] ),
            "'login' generates no ticket before validating credentials." );

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
            "'login' redirects to login screen when user is forced to renew credentials." );

        $this->assertFalse( isset( $query['ticket'] ),
            "'login' generates no ticket before validating credentials." );
    }

    /**
     * @covers ::logout
     * @todo
     */
    function test_logout () {

        $this->assertTrue( is_callable( array( $this->server, 'logout' ) ), "'logout' method is callable." );

        $this->markTestIncomplete();
    }

    /**
     * @covers ::proxy
     * @todo
     */
    function test_proxy () {

        $this->assertTrue( is_callable( array( $this->server, 'proxy' ) ), "'proxy' method is callable." );

        $this->markTestIncomplete();
    }

    /**
     * @covers ::proxyValidate
     * @todo
     */
    function test_proxyValidate () {

        $this->assertTrue( is_callable( array( $this->server, 'proxyValidate' ) ), "'proxyValidate' method is callable." );

        $this->markTestIncomplete();
    }

    /**
     * @covers ::serviceValidate
     * @todo
     */
    function test_serviceValidate () {

        $this->assertTrue( is_callable( array( $this->server, 'serviceValidate' ) ),
            "'serviceValidate' method is callable." );

        $this->markTestIncomplete();
    }

    /**
     * @covers ::validate
     */
    function test_validate () {

        $this->assertTrue( is_callable( array( $this->server, 'validate' ) ),
            "'validate' method is callable." );

        /**
         * No service.
         */
        $args = array(
            'service' => '',
            'ticket'  => 'ticket',
            );

        $this->assertEquals( $this->server->validate( $args ), "no\n\n",
            "Error on empty service." );

        /**
         * No ticket.
         */
        $args = array(
            'service' => 'http://test.local/',
            'ticket'  => '',
            );

        $this->assertEquals( $this->server->validate( $args ), "no\n\n",
            "Error on empty ticket." );

        /**
         * Invalid ticket.
         */
        $args = array(
            'service' => 'http://test.local/',
            'ticket'  => 'bad-ticket',
            );

        $this->assertEquals( $this->server->validate( $args ), "no\n\n",
            "Error on invalid ticket." );

        /**
         * Valid ticket.
         */
        $service = 'http://test/';
        $user_id = $this->factory->user->create();

        wp_set_current_user( $user_id );

        try {
            $this->server->login( array( 'service' => $service ) );
        }
        catch (WPDieException $message) {
            parse_str( parse_url( $this->redirect_location, PHP_URL_QUERY ), $query );
        }

        $args = array(
            'service' => $service,
            'ticket'  => $query['ticket'],
            );

        $user = get_user_by( 'id', $user_id );

        $this->assertEquals( $this->server->validate( $args ), "yes\n" . $user->user_login . "\n",
            "Valid ticket." );

        update_option( WPCASServerPlugin::OPTIONS_KEY, array(
            'expiration'         => 60,
            'allow_ticket_reuse' => true,
            ) );

        $this->assertEquals( $this->server->validate( $args ), "yes\n" . $user->user_login . "\n",
            "Tickets may reused." );

        update_option( WPCASServerPlugin::OPTIONS_KEY, array(
            'expiration'         => 60,
            'allow_ticket_reuse' => false,
            ) );

        $this->assertEquals( $this->server->validate( $args ), "no\n\n",
            "Tickets may not be reused." );
    }

}

