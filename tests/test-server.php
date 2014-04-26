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

        $this->assertTrue( is_callable( array( $this->server, 'handleRequest' ) ), "'handleRequest' method is callable." );

        $this->markTestIncomplete();
    }

    /**
     * @covers ::login
     * @todo
     */
    function test_login () {

        $this->assertTrue( is_callable( array( $this->server, 'login' ) ), "'login' method is callable." );

        // $this->go_to();

        $this->markTestIncomplete();
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

        $this->assertTrue( is_callable( array( $this->server, 'serviceValidate' ) ), "'serviceValidate' method is callable." );

        $this->markTestIncomplete();
    }

    /**
     * @covers ::validate
     * @todo
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
            parse_str( parse_url( $this->redirect_location, PHP_URL_QUERY ), $request );
            $ticket = $request['ticket'];
        }

        $args = array(
            'service' => $service,
            'ticket'  => $ticket,
            );

        $user = get_user_by( 'id', $user_id );

        $this->assertEquals( $this->server->validate( $args ), "yes\n" . $user->user_login . "\n",
            "Valid ticket." );
    }

}

