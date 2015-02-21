<?php
/**
 * CAS Controller abstract class.
 *
 * @package \WPCASServerPlugin\Server
 * @version 1.2.0
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * CAS controller abstract class definition.
 *
 * @since 1.2.0
 */
abstract class CASController {

	/**
	 * CAS server intance.
	 * @var WPCASServer
	 */
	protected $server;

	/**
	 * Constructor.
	 * @param WPCASServer $server CAS server instance.
	 */
	public function __construct( WPCASServer $server ) {
		$this->server = $server;
	}

	/**
	 * Handle a CAS request.
	 *
	 * @param  string $path    CAS request URI.
	 *
	 * @return string          Request response.
	 */
	abstract public function handleRequest( $args );
}
