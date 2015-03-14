<?php
/**
 * @coversDefaultClass \Cassava\Options
 */
class WP_TestWPCASServerOptions extends WP_UnitTestCase {

	private $plugin;

	/**
	 * Setup a test method for the CASServerPlugin class.
	 */
	function setUp () {
		parent::setUp();
		$this->plugin = $GLOBALS[ \Cassava\Plugin::SLUG ];
	}

	/**
	 * Finish a test method for the CASServerPlugin class.
	 */
	function tearDown () {
		parent::tearDown();
		unset( $this->plugin );
	}

	/**
	 * @covers \Cassava\Options::setDefaults
	 * @covers \Cassava\Options::all
	 */
	function test_setDefaults () {
		\delete_option( \Cassava\Options::KEY );
		$this->assertEmpty( \Cassava\Options::all(),
			'Plugin has no options set.' );

		\Cassava\Options::setDefaults();
		$this->assertNotEmpty( \Cassava\Options::all(),
			'Plugin sets default options on init.' );
	}

	/**
	 * Test plugin settings getter.
	 * @covers \Cassava\Options::get
	 */
	function test_get () {
		\Cassava\Options::setDefaults();

		$path = \Cassava\Options::get( 'endpoint_slug' );
		$this->assertEquals( \Cassava\Plugin::ENDPOINT_SLUG, $path,
			'Obtain the path setting.' );

		$path = \Cassava\Options::get( 'endpoint_slug', 'default' );
		$this->assertEquals( \Cassava\Plugin::ENDPOINT_SLUG, $path,
			'Ignores default when obtaining an existing setting.' );

		$unset = \Cassava\Options::get( 'unset', 'nothing' );
		$this->assertEquals( 'nothing', $unset,
			'Obtain the default for a non-existing setting.' );
	}

	/**
	 * Test plugin settings setter.
	 *
	 * @covers \Cassava\Options::get
	 * @covers \Cassava\Options::set
	 */
	function test_set () {
		\Cassava\Options::set( 'zero', 0 );
		$this->assertSame( 0, \Cassava\Options::get( 'zero' ),
			'Set 0 integer.' );

		\Cassava\Options::set( 'integer', 99 );
		$this->assertSame( 99, \Cassava\Options::get( 'integer' ),
			'Set non-zero integer.' );

		\Cassava\Options::set( 'float', 99.99 );
		$this->assertSame( 99.99, \Cassava\Options::get( 'float' ),
			'Set float.' );

		\Cassava\Options::set( 'string', 'test' );
		$this->assertSame( 'test', \Cassava\Options::get( 'string' ),
			'Set string.' );

		\Cassava\Options::set( 'array', array( 1, 2, 3 ) );
		$this->assertSame( array( 1, 2, 3 ), \Cassava\Options::get( 'array' ),
			'Set array.' );

		\Cassava\Options::set( 'object', (object) array( 1, 2, 3 ) );
		$this->assertEquals( (object) array( 1, 2, 3 ), \Cassava\Options::get( 'object' ),
			'Set object.' );
	}

}

