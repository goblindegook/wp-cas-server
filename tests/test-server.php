<?php
/**
 * @package \WPCASServerPlugin\Tests
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
     * Evaluate XPath expression.
     * 
     * @param  string $xpath XPath query to evaluate.
     * @param  string $xml   XML content.
     * 
     * @return mixed         XPath query result.
     */
    protected function xpathEvaluate ( $xpath, $xml ) {
        $dom = new DOMDocument();
        $dom->loadXML( trim( $xml ) );
        $xpathObj = new DOMXPath( $dom );
        return $xpathObj->evaluate( $xpath );
    }

    /**
     * Run an XPath query on an XML string.
     * 
     * @param  mixed  $expected Expected XPath query output.
     * @param  string $xpath    XPath query.
     * @param  string $xml      XML content.
     * @param  string $message  Assert message to print.
     */
    protected function assertXPathMatch ( $expected, $xpath, $xml, $message = null ) {
        $this->assertEquals(
            $expected,
            $this->xpathEvaluate( $xpath, $xml ),
            $message
        );
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

    /**
     * @runInSeparateProcess
     * @covers ::logout
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
     * @runInSeparateProcess
     * @covers ::proxy
     */
    function test_proxy () {

        $this->assertTrue( is_callable( array( $this->server, 'proxy' ) ),
            "'proxy' method is callable." );

        $targetService = 'http://test/';

        /**
         * No proxy-granting ticket.
         */
        $args = array(
            'targetService' => $targetService,
            'pgt'           => '',
            );

        $error = $this->server->proxy( $args );

        $this->assertXPathMatch( 1, 'count(//cas:proxyFailure)', $error,
            'Error if proxy-granting ticket not provided.' );

        $this->assertXPathMatch( WPCASRequestException::ERROR_INVALID_REQUEST, 'string(//cas:proxyFailure[1]/@code)', $error,
            'INVALID_REQUEST error code if proxy-granting ticket not provided.' );

        /**
         * No target service.
         */
        $args = array(
            'targetService' => '',
            'pgt'           => 'pgt',
            );

        $error = $this->server->proxy( $args );

        $this->assertXPathMatch( 1, 'count(//cas:proxyFailure)', $error,
            'Error if target service not provided.' );

        $this->assertXPathMatch( WPCASRequestException::ERROR_INVALID_REQUEST, 'string(//cas:proxyFailure[1]/@code)', $error,
            'INVALID_REQUEST error code if target service not provided.' );

        /**
         * Invalid proxy-granting ticket.
         */
        $args = array(
            'targetService' => $targetService,
            'pgt'           => 'bad-ticket',
            );

        $error = $this->server->proxy( $args );

        $this->assertXPathMatch( 1, 'count(//cas:proxyFailure)', $error,
            'Error on bad proxy-granting ticket.' );

        $this->assertXPathMatch( WPCASTicketException::ERROR_BAD_PGT, 'string(//cas:proxyFailure[1]/@code)', $error,
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

        $this->assertXPathMatch( 1, 'count(//cas:proxyFailure)', $xml,
            "'proxy' should not validate service tickets." );

        $this->assertXPathMatch( WPCASTicketException::ERROR_BAD_PGT, 'string(//cas:proxyFailure[1]/@code)', $xml,
            'BAD_PGT error code on proxy ticket.' );

        /**
         * /proxy should not validate proxy tickets.
         */

        $args = array(
            'targetService' => $targetService,
            'pgt'           => preg_replace( '@^' . WPCASTicket::TYPE_ST . '@', WPCASTicket::TYPE_PT, $query['ticket'] ),
            );

        $xml = $this->server->proxy( $args );

        $this->assertXPathMatch( 1, 'count(//cas:proxyFailure)', $xml,
            "'proxy' should not validate proxy tickets." );

        $this->assertXPathMatch( WPCASTicketException::ERROR_BAD_PGT, 'string(//cas:proxyFailure[1]/@code)', $xml,
            'BAD_PGT error code on service ticket.' );

        /**
         * /proxy validates a Proxy-Granting Ticket successfully.
         */

        WPCASServerPlugin::setOption( 'allow_ticket_reuse', 1 );

        $args = array(
            'targetService' => $targetService,
            'pgt'           => preg_replace( '@^' . WPCASTicket::TYPE_ST . '@', WPCASTicket::TYPE_PGT, $query['ticket'] ),
            );

        $xml = $this->server->proxy( $args );

        $this->assertXPathMatch( 1, 'count(//cas:proxySuccess)', $xml,
            'Successful validation on proxy-granting ticket.' );

        $this->assertXPathMatch( 1, 'count(//cas:proxySuccess/cas:proxyTicket)', $xml,
            "'/proxy' response returns a proxy ticket." );

        $proxyTicket = $this->xpathEvaluate( 'string(//cas:proxySuccess[1]/cas:proxyTicket[1])', $xml );

        $args = array(
            'service' => $targetService,
            'ticket'  => $proxyTicket,
            );

        $xml = $this->server->proxyValidate( $args );

        $this->assertXPathMatch( 1, 'count(//cas:authenticationSuccess)', $xml,
            "'/proxy' response returns a valid proxy ticket." );

        /**
         * Do not enforce single-use tickets.
         */

        $args = array(
            'targetService' => $targetService,
            'pgt'           => preg_replace( '@^' . WPCASTicket::TYPE_ST . '@', WPCASTicket::TYPE_PGT, $query['ticket'] ),
            );

        $xml = $this->server->proxy( $args );

        $this->assertXPathMatch( 1, 'count(//cas:proxySuccess)', $xml,
            'Settings allow ticket reuse.' );

        /**
         * Enforce single-use tickets.
         */
        
        WPCASServerPlugin::setOption( 'allow_ticket_reuse', 0 );

        $error = $this->server->proxy( $args );

        $this->assertXPathMatch( 1, 'count(//cas:proxyFailure)', $error,
            "Settings do not allow ticket reuse." );

        $this->assertXPathMatch( WPCASTicketException::ERROR_BAD_PGT, 'string(//cas:proxyFailure[1]/@code)', $error,
            'BAD_PGT error code on ticket reuse.' );
    }

    /**
     * @runInSeparateProcess
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

        $this->assertXPathMatch( 1, 'count(//cas:authenticationFailure)', $error,
            'Error if service not provided.' );

        $this->assertXPathMatch( WPCASRequestException::ERROR_INVALID_REQUEST, 'string(//cas:authenticationFailure[1]/@code)', $error,
            'INVALID_REQUEST error code if service not provided.' );

        /**
         * No ticket.
         */
        $args = array(
            'service' => $service,
            'ticket'  => '',
            );

        $error = $this->server->proxyValidate( $args );

        $this->assertXPathMatch( 1, 'count(//cas:authenticationFailure)', $error,
            'Error if ticket not provided.' );

        $this->assertXPathMatch( WPCASRequestException::ERROR_INVALID_REQUEST, 'string(//cas:authenticationFailure[1]/@code)', $error,
            'INVALID_REQUEST error code if ticket not provided.' );

        /**
         * Invalid ticket.
         */
        $args = array(
            'service' => $service,
            'ticket'  => 'bad-ticket',
            );

        $error = $this->server->proxyValidate( $args );

        $this->assertXPathMatch( 1, 'count(//cas:authenticationFailure)', $error,
            'Error on bad ticket.' );

        $this->assertXPathMatch( WPCASTicketException::ERROR_INVALID_TICKET, 'string(//cas:authenticationFailure[1]/@code)', $error,
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

        $this->assertXPathMatch( 1, 'count(//cas:authenticationSuccess)', $xml,
            'Successful validation.' );

        $this->assertXPathMatch( 1, 'count(//cas:authenticationSuccess/cas:user)', $xml,
            "Ticket validation response returns a user.");

        $this->assertXPathMatch( $user->user_login, 'string(//cas:authenticationSuccess[1]/cas:user[1])', $xml,
            "Ticket validation returns user login." );

        /**
         * Do not enforce single-use tickets.
         */
        
        WPCASServerPlugin::setOption( 'allow_ticket_reuse', 1 );

        $xml = $this->server->proxyValidate( $args );

        $this->assertXPathMatch( 1, 'count(//cas:authenticationSuccess)', $xml,
            'Settings allow ticket reuse.' );

        /**
         * /proxyValidate may validate Proxy Tickets.
         */
        $args = array(
            'service' => $service,
            'ticket'  => preg_replace( '@^' . WPCASTicket::TYPE_ST . '@', WPCASTicket::TYPE_PT, $query['ticket'] ),
            );

        $xml = $this->server->proxyValidate( $args );

        $this->assertXPathMatch( 1, 'count(//cas:authenticationSuccess)', $xml,
            "'proxyValidate' may validate proxy tickets." );

        /**
         * Enforce single-use tickets.
         */
        WPCASServerPlugin::setOption( 'allow_ticket_reuse', 0 );

        $args = array(
            'service' => $service,
            'ticket'  => $query['ticket'],
            );

        $error = $this->server->proxyValidate( $args );

        $this->assertXPathMatch( 1, 'count(//cas:authenticationFailure)', $error,
            "Settings do not allow ticket reuse." );

        $this->assertXPathMatch( WPCASTicketException::ERROR_INVALID_TICKET, 'string(//cas:authenticationFailure[1]/@code)', $error,
            'INVALID_TICKET error code on ticket reuse.' );
    }

    /**
     * @runInSeparateProcess
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

        $this->assertXPathMatch( 1, 'count(//cas:authenticationFailure)', $error,
            'Error if service not provided.' );

        $this->assertXPathMatch( WPCASRequestException::ERROR_INVALID_REQUEST, 'string(//cas:authenticationFailure[1]/@code)', $error,
            'INVALID_REQUEST error code if service not provided.' );

        /**
         * No ticket.
         */
        $args = array(
            'service' => $service,
            'ticket'  => '',
            );

        $error = $this->server->serviceValidate( $args );

        $this->assertXPathMatch( 1, 'count(//cas:authenticationFailure)', $error,
            'Error if ticket not provided.' );

        $this->assertXPathMatch( WPCASRequestException::ERROR_INVALID_REQUEST, 'string(//cas:authenticationFailure[1]/@code)', $error,
            'INVALID_REQUEST error code if ticket not provided.' );

        /**
         * Invalid ticket.
         */
        $args = array(
            'service' => $service,
            'ticket'  => 'bad-ticket',
            );

        $error = $this->server->serviceValidate( $args );

        $this->assertXPathMatch( 1, 'count(//cas:authenticationFailure)', $error,
            'Error on bad ticket.' );

        $this->assertXPathMatch( WPCASTicketException::ERROR_INVALID_TICKET, 'string(//cas:authenticationFailure[1]/@code)', $error,
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

        $this->assertXPathMatch( 1, 'count(//cas:authenticationSuccess)', $xml,
            'Successful validation.' );

        $this->assertXPathMatch( 1, 'count(//cas:authenticationSuccess/cas:user)', $xml,
            "Ticket validation response returns a user.");

        $this->assertXPathMatch( $user->user_login, 'string(//cas:authenticationSuccess/cas:user)', $xml,
            "Ticket validation returns user login." );

        /**
         * Do not enforce single-use tickets.
         */
        WPCASServerPlugin::setOption( 'allow_ticket_reuse', 1 );

        $xml = $this->server->serviceValidate( $args );

        $this->assertXPathMatch( 1, 'count(//cas:authenticationSuccess)', $xml,
            'Settings allow ticket reuse.' );

        /**
         * Validate does not return any user attributes.
         */

        $this->assertXPathMatch( 0, 'count(//cas:authenticationSuccess/cas:attributes)', $xml,
            "Ticket validation returns no user attributes.");

        /**
         * Validate returns selected user attributes.
         */

        WPCASServerPlugin::setOption( 'attributes', array( 'display_name', 'user_email' ) );

        $xml = $this->server->serviceValidate( $args );

        $this->assertXPathMatch( $user->get( 'display_name' ),
            'string(//cas:authenticationSuccess/cas:attributes/cas:display_name)', $xml,
            'Ticket validation returns the user display name.' );

        $this->assertXPathMatch( $user->get( 'user_email' ),
            'string(//cas:authenticationSuccess/cas:attributes/cas:user_email)', $xml,
            'Ticket validation returns the user email.' );

        /**
         * /serviceValidate should not validate Proxy Tickets.
         */
        $args = array(
            'service' => $service,
            'ticket'  => preg_replace( '@^' . WPCASTicket::TYPE_ST . '@', WPCASTicket::TYPE_PT, $query['ticket'] ),
            );

        $error = $this->server->serviceValidate( $args );

        $this->assertXPathMatch( 1, 'count(//cas:authenticationFailure)', $error,
            "'serviceValidate' may not validate proxy tickets." );

        $this->assertXPathMatch( WPCASTicketException::ERROR_INVALID_TICKET, 'string(//cas:authenticationFailure[1]/@code)', $error,
            'INVALID_TICKET error code on proxy ticket.' );

        /**
         * Enforce single-use tickets.
         */
        WPCASServerPlugin::setOption( 'allow_ticket_reuse', 0 );

        $args = array(
            'service' => $service,
            'ticket'  => $query['ticket'],
            );

        $error = $this->server->serviceValidate( $args );

        $this->assertXPathMatch( 1, 'count(//cas:authenticationFailure)', $error,
            "Settings do not allow ticket reuse." );

        $this->assertXPathMatch( WPCASTicketException::ERROR_INVALID_TICKET, 'string(//cas:authenticationFailure[1]/@code)', $error,
            'INVALID_TICKET error code on ticket reuse.' );

        $this->markTestIncomplete( "Test support for the optional 'pgtUrl' and 'renew' parameters." );
    }

    /**
     * @runInSeparateProcess
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

        WPCASServerPlugin::setOption( 'allow_ticket_reuse', 1 );

        $this->assertEquals( $this->server->validate( $args ), "yes\n" . $user->user_login . "\n",
            "Tickets may reused." );

        WPCASServerPlugin::setOption( 'allow_ticket_reuse', 0 );

        $this->assertEquals( $this->server->validate( $args ), "no\n\n",
            "Tickets may not be reused." );

        $this->markTestIncomplete( "Test support for the optional 'pgtUrl' and 'renew' parameters." );
    }

}

