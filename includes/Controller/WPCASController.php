<?php
/**
 * CAS Controller abstract class.
 *
 * @package \WPCASServerPlugin\Server
 * @version 1.2.0
 */

/**
 * CAS controller abstract class definition.
 *
 * @since 1.2.0
 */
abstract class WPCASController {

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
	 * @param array $request CAS request.
	 */
	abstract public function handleRequest( $request );
}
