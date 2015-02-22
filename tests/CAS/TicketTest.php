<?php
/**
 * @package \WPCASServerPlugin\Tests
 */

use Cassava\CAS;

/**
 * @coversDefaultClass \Cassava\CAS\Ticket
 */
class WP_TestWPCASTicket extends WP_UnitTestCase {

	/**
	 * @covers ::__constructor
	 */
	function test_constructor () {
		$type       = CAS\Ticket::TYPE_ST;
		$user       = get_user_by( 'id', $this->factory->user->create() );
		$service    = 'https://test/ÚÑ|Çº∂€/';
		$expiration = Cassava\Plugin::getOption( 'expiration', 30 );

		$ticket = new CAS\Ticket( $type, $user, $service );

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
		$type    = CAS\Ticket::TYPE_ST;
		$user    = get_user_by( 'id', $this->factory->user->create() );
		$service = 'https://test/ÚÑ|Çº∂€/';

		$ticket = new CAS\Ticket( $type, $user, $service );

		$duplicateTicket = CAS\Ticket::fromString( (string) $ticket );

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
		$type    = CAS\Ticket::TYPE_ST;
		$user    = get_user_by( 'id', $this->factory->user->create() );
		$service = 'https://test/ÚÑ|Çº∂€/';

		$ticket = new CAS\Ticket( $type, $user, $service );

		$this->assertFalse( $ticket->isUsed(),
			'Newly generated ticket is fresh.' );

		$ticket->markUsed();

		$this->assertTrue( $ticket->isUsed(),
			'Ticket correctly marked as used.' );

		Cassava\Plugin::setOption( 'allow_ticket_reuse', 1 );

		$this->assertFalse( $ticket->isUsed(),
			'Settings allow ticket reuse.' );

	}

}
