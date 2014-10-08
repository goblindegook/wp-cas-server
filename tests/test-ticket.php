<?php
/**
 * @package \WPCASServerPlugin\Tests
 */

/**
 * @coversDefaultClass WPCASTicket
 */
class WP_TestWPCASTicket extends WP_UnitTestCase {

	/**
	 * Setup a test method for the WPCASTicket class.
	 */
	function setUp () {
		parent::setUp();
	}

	/**
	 * Finish a test method for the WPCASTicket class.
	 */
	function tearDown () {
		parent::tearDown();
	}


	/**
	 * @covers ::__constructor
	 */
	function test_constructor () {

		$type       = WPCASTicket::TYPE_ST;
		$user       = get_user_by( 'id', $this->factory->user->create() );
		$service    = 'https://test/';
		$expiration = 30;

		$ticket = new WPCASTicket( $type, $user, $service, $expiration );

		$this->assertEquals( $type, $ticket->type,
			'User correctly set.' );

		$this->assertEquals( $user, $ticket->user,
			'User correctly set.' );

		$this->assertEquals( $service, $ticket->service,
			'Service correctly set.' );

		$this->assertGreaterThanOrEqual( time() + $expiration, $ticket->expires,
			'Expiration timestamp correctly set.' );

		$this->assertFalse( $ticket->isUsed(),
			'Newly generated ticket is fresh.' );

	}

	/**
	 * @covers ::__toString
	 * @covers ::fromString
	 * @covers ::generateSignature()
	 */
	function test_toString () {
		$type       = WPCASTicket::TYPE_ST;
		$user       = get_user_by( 'id', $this->factory->user->create() );
		$service    = 'https://test/';
		$expiration = 30;

		$ticket     = new WPCASTicket( $type, $user, $service, $expiration );

		$duplicateTicket = WPCASTicket::fromString( (string) $ticket );

		$this->assertEquals( $ticket->generateSignature(), $duplicateTicket->generateSignature(),
			"Ticket generated from string has the same signature as original." );
	}


	/**
	 * @covers ::fromString
	 * @todo Test exceptions.
	 */
	function test_fromString () {
		$this->markTestIncomplete();
	}

	/**
	 * @covers ::isUsed
	 * @covers ::markUsed
	 */
	function test_reuse () {

		$type       = WPCASTicket::TYPE_ST;
		$user       = get_user_by( 'id', $this->factory->user->create() );
		$service    = 'https://test/';
		$expiration = 30;

		$ticket     = new WPCASTicket( $type, $user, $service, $expiration );

		$this->assertFalse( $ticket->isUsed(),
			'Newly generated ticket is fresh.' );

		$ticket->markUsed();

		$this->assertTrue( $ticket->isUsed(),
			'Ticket correctly marked as used.' );

		WPCASServerPlugin::setOption( 'allow_ticket_reuse', 1 );

		$this->assertFalse( $ticket->isUsed(),
			'Settings allow ticket reuse.' );

	}

}
