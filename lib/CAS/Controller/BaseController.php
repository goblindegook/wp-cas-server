<?php
/**
 * CAS Controller abstract class.
 *
 * @version 1.2.0
 * @since 1.2.0
 */

namespace Cassava\CAS\Controller;

use Cassava\CAS;

/**
 * Base CAS controller class definition.
 *
 * @since 1.2.0
 */
abstract class BaseController {

	/**
	 * CAS server intance.
	 * @var \Cassava\CAS\Server
	 */
	protected $server;

	/**
	 * Constructor.
	 *
	 * @param \Cassava\CAS\Server $server CAS server instance.
	 */
	public function __construct( CAS\Server $server ) {
		$this->server = $server;
	}

	/**
	 * Handle a CAS request.
	 *
	 * @param array $request CAS request.
	 *
	 * @return null
	 */
	abstract public function handleRequest( $request );
}
