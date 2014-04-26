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

        // No service
        
        $args = array(
            'service' => '',
            'ticket'  => 'ticket',
            );

        $this->assertEquals( $this->server->validate( $args ), "no\n\n",
            "error on empty service" );

        // No ticket

        $args = array(
            'service' => 'http://test.local/',
            'ticket'  => '',
            );

        $this->assertEquals( $this->server->validate( $args ), "no\n\n",
            "error on empty ticket" );

        // Invalid ticket

        $args = array(
            'service' => 'http://test.local/',
            'ticket'  => 'bad-ticket',
            );

        $this->assertEquals( $this->server->validate( $args ), "no\n\n",
            "error on invalid ticket" );

        $this->markTestIncomplete();

        // Valid ticket

        $user_id = $this->factory->user->create();

        wp_set_current_user( $user_id );

        $user = get_user_by( 'id', $user_id );

        // TODO: Generate ticket with login

        $args = array(
            'service' => 'http://test/',
            'ticket'  => '',
            );

        $this->assertEquals( $this->server->validate( $args ), "yes\n" . $user->user_login . "\n",
            "valid ticket" );
    }

}

