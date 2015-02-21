<?php
/**
 * @package \WPCASServerPlugin\Tests
 */

/**
 * @coversDefaultClass WPCASServer
 */
class WPCAS_UnitTestCase extends WP_UnitTestCase {

	protected $redirect_location;

	/**
	 * Setup a test method for the WPCASServer class.
	 */
	function setUp () {
		parent::$ignore_files = true;
		parent::setUp();
		add_filter( 'wp_redirect', array( $this, 'wp_redirect_handler' ) );
	}

	/**
	 * Finish a test method for the CASServer class.
	 */
	function tearDown () {
		parent::tearDown();
		$this->redirect_location = null;
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

}
