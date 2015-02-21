<?php
/**
 * CAS proxy response class.
 *
 * @package \WPCASServerPlugin\Server
 * @version 1.2.0
 */

if ( ! class_exists( 'WPCASResponseProxy' ) ) {

	/**
	 * Implements the CAS response for proxy requests.
	 *
	 * @version 1.2.0
	 */
	class WPCASResponseProxy extends WPCASResponse {

		/**
		 * Set XML success response to a CAS 2.0 proxy request.
		 *
		 * @param  WPCASTicket $user    Validated proxy ticket.
		 */
		public function setTicket( WPCASTicket $proxyTicket ) {
			$this->response = $this->createElement( 'proxySuccess' );
			$this->response->appendChild( $this->createElement( 'proxyTicket', $proxyTicket ) );
		}

	}

}
