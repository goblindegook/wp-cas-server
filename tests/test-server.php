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
     * Run an XPath query on an XML string.
     * 
     * @param  string $query XPath query to run.
     * @param  string $xml   Stringified XML.
     * 
     * @return array         Query results in array form.
     */
    private function _xpathQueryXML( $query, $xml ) {
        $doc = new DOMDocument;
        $doc->loadXML( $xml );

        $xpath = new DOMXPath( $doc );
        $results = $xpath->query( $query );

        $output = array();

        foreach ($results as $element) {
            $output[] = $element;
        }

        return $output;
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
     * @covers ::_loginRequestor
     * @runInSeparateProcess
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
     * @covers ::_loginAcceptor
     * @runInSeparateProcess
     * 
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

    /**
     * @covers ::logout
     * @runInSeparateProcess
     */
    function test_logout () {

        $this->assertTrue( is_callable( array( $this->server, 'logout' ) ),
            "'logout' method is callable." );

        /**
         * /logout?service=http://test/
         */

        $service = 'http://test/';

        wp_set_current_user( $this->factory->user->create() );

        $this->assertTrue( is_user_logged_in(),
            'User is logged in.' );

        try {
            $this->server->logout( array( 'service' => $service ) );
        }
        catch (WPDieException $message) {
            parse_str( parse_url( $this->redirect_location, PHP_URL_QUERY ), $query );
        }

        $this->assertFalse( is_user_logged_in(),
            'User is logged out.' );

        $this->assertStringStartsWith( $service, $this->redirect_location,
            "'logout' redirects to service." );

        /**
         * /logout
         */

        wp_set_current_user( $this->factory->user->create() );

        try {
            $this->server->logout( array( 'service' => $service ) );
        }
        catch (WPDieException $message) {
            parse_str( parse_url( $this->redirect_location, PHP_URL_QUERY ), $query );
        }

        $this->assertStringStartsWith( $service, $this->redirect_location,
            "'logout' redirects to home if no service is provided." );
    }

    /**
     * @covers ::proxy
     */
    function test_proxy () {

        $this->assertTrue( is_callable( array( $this->server, 'proxy' ) ),
            "'proxy' method is callable." );

        $targetService = 'http://test/';

        /**
         * No target service.
         */
        $args = array(
            'targetService' => '',
            'pgt'           => 'pgt',
            );

        $error = $this->server->proxy( $args );

        $this->assertInstanceOf( 'WP_Error', $error,
            'Error if target service not provided.' );

        $this->assertEquals( ICASServer::ERROR_INVALID_REQUEST, $error->error_data['proxyFailure']['code'],
            'INVALID_REQUEST error code if target service not provided.' );

        /**
         * No proxy-granting ticket.
         */
        $args = array(
            'targetService' => $targetService,
            'pgt'           => '',
            );

        $error = $this->server->proxy( $args );

        $this->assertInstanceOf( 'WP_Error', $error,
            'Error if proxy-granting ticket not provided.' );

        $this->assertEquals( ICASServer::ERROR_INVALID_REQUEST, $error->error_data['proxyFailure']['code'],
            'INVALID_REQUEST error code if proxy-granting ticket not provided.' );

        /**
         * Invalid proxy-granting ticket.
         */
        $args = array(
            'targetService' => $targetService,
            'pgt'           => 'bad-ticket',
            );

        $error = $this->server->proxy( $args );

        $this->assertInstanceOf( 'WP_Error', $error,
            'Error on bad proxy-granting ticket.' );

        $this->assertEquals( ICASServer::ERROR_BAD_PGT, $error->error_data['proxyFailure']['code'],
            'BAD_PGT error code on bad proxy-granting ticket.' );

        /**
         * /proxy should not validate service tickets.
         */
        $user_id = $this->factory->user->create();

        wp_set_current_user( $user_id );

        try {
            $this->server->login( array( 'service' => $targetService ) );
        }
        catch (WPDieException $message) {
            parse_str( parse_url( $this->redirect_location, PHP_URL_QUERY ), $query );
        }

        $args = array(
            'targetService' => $targetService,
            'pgt'           => $query['ticket'],
            );

        $xml = $this->server->proxy( $args );

        $this->assertInstanceOf( 'WP_Error', $xml,
            "'proxy' should not validate service tickets." );

        $this->assertEquals( ICASServer::ERROR_BAD_PGT, $error->error_data['proxyFailure']['code'],
            'BAD_PGT error code on proxy ticket.' );

        /**
         * /proxy should not validate proxy tickets.
         */

        $args = array(
            'targetService' => $targetService,
            'pgt'           => preg_replace( '@^' . ICASServer::TYPE_ST . '@', ICASServer::TYPE_PT, $query['ticket'] ),
            );

        $xml = $this->server->proxy( $args );

        $this->assertInstanceOf( 'WP_Error', $xml,
            "'proxy' should not validate proxy tickets." );

        $this->assertEquals( ICASServer::ERROR_BAD_PGT, $error->error_data['proxyFailure']['code'],
            'BAD_PGT error code on service ticket.' );

        /**
         * /proxy validates a Proxy-Granting Ticket successfully.
         */
        
        update_option( WPCASServerPlugin::OPTIONS_KEY, array(
            'expiration'         => 60,
            'allow_ticket_reuse' => true,
            ) );

        $args = array(
            'targetService' => $targetService,
            'pgt'           => preg_replace( '@^' . ICASServer::TYPE_ST . '@', ICASServer::TYPE_PGT, $query['ticket'] ),
            );

        $xml = $this->server->proxy( $args );

        $this->assertNotInstanceOf( 'WP_Error', $xml,
            'Successful validation on proxy-granting ticket.' );

        $xpath_query_results = $this->_xpathQueryXML( '//cas:serviceResponse/cas:proxySuccess/cas:proxyTicket', $xml );

        $this->assertCount( 1, $xpath_query_results,
            "'/proxy' response returns a proxy ticket.");

        $proxyTicket = $xpath_query_results[0]->nodeValue;

        $args = array(
            'service' => $targetService,
            'ticket'  => $proxyTicket,
            );

        $xml = $this->server->proxyValidate( $args );

        $this->assertNotInstanceOf( 'WP_Error', $xml,
            "'/proxy' response returns a valid proxy ticket." );

        /**
         * Do not enforce single-use tickets.
         */

        $args = array(
            'targetService' => $targetService,
            'pgt'           => preg_replace( '@^' . ICASServer::TYPE_ST . '@', ICASServer::TYPE_PGT, $query['ticket'] ),
            );

        $xml = $this->server->proxy( $args );

        $this->assertNotInstanceOf( 'WP_Error', $xml,
            'Settings allow ticket reuse.' );

        /**
         * Enforce single-use tickets.
         */
        update_option( WPCASServerPlugin::OPTIONS_KEY, array(
            'expiration'         => 60,
            'allow_ticket_reuse' => false,
            ) );

        $error = $this->server->proxy( $args );

        $this->assertInstanceOf( 'WP_Error', $error,
            "Settings do not allow ticket reuse." );

        $this->assertEquals( ICASServer::ERROR_BAD_PGT, $error->error_data['proxyFailure']['code'],
            'BAD_PGT error code on ticket reuse.' );
    }

    /**
     * @covers ::proxyValidate
     * 
     * @todo Test support for the optional 'pgtUrl' parameter.
     * @todo Test support for the optional 'renew' parameter.
     */
    function test_proxyValidate () {

        $this->assertTrue( is_callable( array( $this->server, 'proxyValidate' ) ),
            "'proxyValidate' method is callable." );

        $service = 'http://test/';

        /**
         * No service.
         */
        $args = array(
            'service' => '',
            'ticket'  => 'ticket',
            );

        $error = $this->server->proxyValidate( $args );

        $this->assertInstanceOf( 'WP_Error', $error,
            'Error if service not provided.' );

        $this->assertEquals( ICASServer::ERROR_INVALID_REQUEST, $error->error_data['authenticationFailure']['code'],
            'INVALID_REQUEST error code if service not provided.' );

        /**
         * No ticket.
         */
        $args = array(
            'service' => $service,
            'ticket'  => '',
            );

        $error = $this->server->proxyValidate( $args );

        $this->assertInstanceOf( 'WP_Error', $error,
            'Error if ticket not provided.' );

        $this->assertEquals( ICASServer::ERROR_INVALID_REQUEST, $error->error_data['authenticationFailure']['code'],
            'INVALID_REQUEST error code if ticket not provided.' );

        /**
         * Invalid ticket.
         */
        $args = array(
            'service' => $service,
            'ticket'  => 'bad-ticket',
            );

        $error = $this->server->proxyValidate( $args );

        $this->assertInstanceOf( 'WP_Error', $error,
            'Error on bad ticket.' );

        $this->assertEquals( ICASServer::ERROR_INVALID_TICKET, $error->error_data['authenticationFailure']['code'],
            'INVALID_TICKET error code on bad ticket.' );

        /**
         * Valid ticket.
         */
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

        $xml = $this->server->proxyValidate( $args );

        $this->assertNotInstanceOf( 'WP_Error', $xml,
            'Successful validation.' );

        $xpath_query_results = $this->_xpathQueryXML( '//cas:serviceResponse/cas:authenticationSuccess/cas:user', $xml );

        $this->assertCount( 1, $xpath_query_results,
            "Ticket validation response returns a user.");

        $this->assertEquals( $xpath_query_results[0]->nodeValue, $user->user_login,
            "Ticket validation returns user login." );

        /**
         * Do not enforce single-use tickets.
         */
        update_option( WPCASServerPlugin::OPTIONS_KEY, array(
            'expiration'         => 60,
            'allow_ticket_reuse' => true,
            ) );

        $xml = $this->server->proxyValidate( $args );

        $this->assertNotInstanceOf( 'WP_Error', $xml,
            'Settings allow ticket reuse.' );

        /**
         * /proxyValidate may validate Proxy Tickets.
         */
        $args = array(
            'service' => $service,
            'ticket'  => preg_replace( '@^' . ICASServer::TYPE_ST . '@', ICASServer::TYPE_PT, $query['ticket'] ),
            );

        $xml = $this->server->proxyValidate( $args );

        $this->assertNotInstanceOf( 'WP_Error', $xml,
            "'proxyValidate' may validate proxy tickets." );

        /**
         * Enforce single-use tickets.
         */
        update_option( WPCASServerPlugin::OPTIONS_KEY, array(
            'expiration'         => 60,
            'allow_ticket_reuse' => false,
            ) );

        $args = array(
            'service' => $service,
            'ticket'  => $query['ticket'],
            );

        $error = $this->server->proxyValidate( $args );

        $this->assertInstanceOf( 'WP_Error', $error,
            "Settings do not allow ticket reuse." );

        $this->assertEquals( ICASServer::ERROR_INVALID_TICKET, $error->error_data['authenticationFailure']['code'],
            'INVALID_TICKET error code on ticket reuse.' );
    }

    /**
     * @covers ::serviceValidate
     * 
     * @todo Test support for the optional 'pgtUrl' parameter.
     * @todo Test support for the optional 'renew' parameter.
     */
    function test_serviceValidate () {

        $this->assertTrue( is_callable( array( $this->server, 'serviceValidate' ) ),
            "'serviceValidate' method is callable." );

        $service = 'http://test/';

        /**
         * No service.
         */
        $args = array(
            'service' => '',
            'ticket'  => 'ticket',
            );

        $error = $this->server->serviceValidate( $args );

        $this->assertInstanceOf( 'WP_Error', $error,
            'Error if service not provided.' );

        $this->assertEquals( ICASServer::ERROR_INVALID_REQUEST, $error->error_data['authenticationFailure']['code'],
            'INVALID_REQUEST error code if service not provided.' );

        /**
         * No ticket.
         */
        $args = array(
            'service' => $service,
            'ticket'  => '',
            );

        $error = $this->server->serviceValidate( $args );

        $this->assertInstanceOf( 'WP_Error', $error,
            'Error if ticket not provided.' );

        $this->assertEquals( ICASServer::ERROR_INVALID_REQUEST, $error->error_data['authenticationFailure']['code'],
            'INVALID_REQUEST error code if ticket not provided.' );

        /**
         * Invalid ticket.
         */
        $args = array(
            'service' => $service,
            'ticket'  => 'bad-ticket',
            );

        $error = $this->server->serviceValidate( $args );

        $this->assertInstanceOf( 'WP_Error', $error,
            'Error on bad ticket.' );

        $this->assertEquals( ICASServer::ERROR_INVALID_TICKET, $error->error_data['authenticationFailure']['code'],
            'INVALID_TICKET error code on bad ticket.' );

        /**
         * Valid ticket.
         */
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

        $xml = $this->server->serviceValidate( $args );

        $this->assertNotInstanceOf( 'WP_Error', $xml,
            'Successful validation.' );

        $xpath_query_results = $this->_xpathQueryXML( '//cas:serviceResponse/cas:authenticationSuccess/cas:user', $xml );

        $this->assertCount( 1, $xpath_query_results,
            "Ticket validation response returns a user.");

        $this->assertEquals( $xpath_query_results[0]->nodeValue, $user->user_login,
            "Ticket validation returns user login." );

        /**
         * Do not enforce single-use tickets.
         */
        update_option( WPCASServerPlugin::OPTIONS_KEY, array(
            'expiration'         => 60,
            'allow_ticket_reuse' => true,
            ) );

        $xml = $this->server->serviceValidate( $args );

        $this->assertNotInstanceOf( 'WP_Error', $xml,
            'Settings allow ticket reuse.' );

        /**
         * /serviceValidate should not validate Proxy Tickets.
         */
        $args = array(
            'service' => $service,
            'ticket'  => preg_replace( '@^' . ICASServer::TYPE_ST . '@', ICASServer::TYPE_PT, $query['ticket'] ),
            );

        $error = $this->server->serviceValidate( $args );

        $this->assertInstanceOf( 'WP_Error', $error,
            "'serviceValidate' may not validate proxy tickets." );

        $this->assertEquals( ICASServer::ERROR_INVALID_TICKET, $error->error_data['authenticationFailure']['code'],
            'INVALID_TICKET error code on proxy ticket.' );

        /**
         * Enforce single-use tickets.
         */
        update_option( WPCASServerPlugin::OPTIONS_KEY, array(
            'expiration'         => 60,
            'allow_ticket_reuse' => false,
            ) );

        $args = array(
            'service' => $service,
            'ticket'  => $query['ticket'],
            );

        $error = $this->server->serviceValidate( $args );

        $this->assertInstanceOf( 'WP_Error', $error,
            "Settings do not allow ticket reuse." );

        $this->assertEquals( ICASServer::ERROR_INVALID_TICKET, $error->error_data['authenticationFailure']['code'],
            'INVALID_TICKET error code on ticket reuse.' );

        $this->markTestIncomplete( "Test support for the optional 'pgtUrl' and 'renew' parameters." );
    }

    /**
     * @covers ::validate
     * @runInSeparateProcess
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

        $this->markTestIncomplete( "Test support for the optional 'pgtUrl' and 'renew' parameters." );
    }

}

