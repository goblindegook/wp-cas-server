<?php
/**
 * CAS ticket validation response class.
 *
 * @package \WPCASServerPlugin\Server
 * @version 1.2.0
 */

if ( ! class_exists( 'WPCASResponseValidate' ) ) {

	/**
	 * Implements the CAS response for validation requests.
	 *
	 * @version 1.2.0
	 */
	class WPCASResponseValidate extends WPCASResponse {

		/**
		 * XML success response to a CAS 2.0 validation request.
		 *
		 * @param  WPCASTicket $ticket              Validated ticket.
		 * @param  string      $proxyGrantingTicket Generated Proxy-Granting Ticket (PGT) to return.
		 * @param  array       $proxies             List of proxy URIs.
		 *
		 * @uses apply_filters()
		 * @uses get_userdata()
		 */
		public function setTicket( WPCASTicket $ticket, $proxyGrantingTicket = '', $proxies = array() ) {

			$this->response = $this->createElement( 'authenticationSuccess' );

			// Include login name:

			$this->response->appendChild( $this->createElement( 'user', $ticket->user->user_login ) );

			// CAS attributes:

			$attributeKeys = WPCASServerPlugin::getOption( 'attributes' );

			if (is_array( $attributeKeys ) && count( $attributeKeys ) > 0) {

				$attributes = array();

				foreach ($attributeKeys as $key) {
					$attributes[$key] = implode( ',', (array) $ticket->user->get( $key ) );
				}

				/**
				 * Allows developers to change the list of (key, value) pairs before they're included
				 * in a `/serviceValidate` response.
				 *
				 * @param  array   $attributes List of attributes to output.
				 * @param  WP_User $user       Authenticated user.
				 */
				$attributes = apply_filters( 'cas_server_validation_user_attributes', $attributes, $ticket->user );

				$xmlAttributes = $this->createElement( 'attributes' );

				foreach ($attributes as $key => $value) {
					$xmlAttribute = $this->createElement( $key, $value );
					$xmlAttributes->appendChild( $xmlAttribute );
				}

				$this->response->appendChild( $xmlAttributes );
			}

			// Include Proxy-Granting Ticket in successful `/proxyValidate` responses:

			if ( $proxyGrantingTicket ) {
				$this->response->appendChild( $this->createElement(
					'proxyGrantingTicket', $proxyGrantingTicket ) );
			}

			// Include proxies in successful `/proxyValidate` responses:

			if (count( $proxies ) > 0) {
				$xmlProxies = $this->createElement( 'proxies' );

				foreach ($proxies as $proxy) {
					$xmlProxies->appendChild( $this->createElement(
						'proxy', $proxy ) );
				}

				$this->response->appendChild( $xmlProxies );
			}
		}

	}

}
