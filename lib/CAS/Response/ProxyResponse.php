<?php
/**
 * CAS proxy response class.
 *
 * @package \WPCASServerPlugin\Server
 * @version 1.2.0
 * @since 1.2.0
 */

namespace Cassava\CAS\Response;

use Cassava\CAS;

/**
 * Implements the CAS response for proxy requests.
 *
 * @version 1.2.0
 *
 * @todo Throw exception on bad or no ticket.
 */
class ProxyResponse extends BaseResponse {

	/**
	 * Set XML success response to a CAS 2.0 proxy request.
	 *
	 * @param \Cassava\CAS\Ticket $proxyTicket Validated proxy ticket.
	 */
	public function setTicket( CAS\Ticket $proxyTicket ) {
		$this->response = $this->createElement( 'proxySuccess' );
		$this->response->appendChild( $this->createElement( 'proxyTicket', $proxyTicket ) );
	}

}
