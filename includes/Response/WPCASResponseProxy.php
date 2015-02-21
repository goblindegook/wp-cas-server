<?php

if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'WPCASResponseProxy' ) ) {

	class WPCASResponseProxy extends WPCASResponse {

		/**
		 * Set XML success response to a CAS 2.0 proxy request.
		 *
		 * @param  WPCASTicket $user    Validated proxy ticket.
		 */
		public function setTicket( WPCASTicket $proxyTicket ) {
			$node = $this->createElement( 'proxySuccess' );
			$node->appendChild( $this->createElement( 'proxyTicket', $proxyTicket ) );
			$this->setResponse( $node );
		}

	}

}
