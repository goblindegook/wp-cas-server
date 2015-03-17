<?php
/**
 * CAS ticket validation response class.
 *
 * @version 1.2.0
 * @since 1.2.0
 */

namespace Cassava\CAS\Response;

use Cassava\CAS;
use Cassava\Options;
use Cassava\Plugin;

/**
 * Implements the CAS response for validation requests.
 *
 * @version 1.2.0
 */
class ValidateResponse extends BaseResponse {

	/**
	 * XML success response to a CAS 2.0 validation request.
	 *
	 * @param  \Cassava\CAS\Ticket $ticket              Validated ticket.
	 * @param  string              $proxyGrantingTicket Generated Proxy-Granting Ticket (PGT) to return.
	 * @param  array               $proxies             List of proxy URIs.
	 *
	 * @todo Throw exception on bad or no ticket.
	 */
	public function setTicket( CAS\Ticket $ticket, $proxyGrantingTicket = '', $proxies = array() ) {

		$this->response = $this->createElement( 'authenticationSuccess' );

		// Include login name:

		$this->response->appendChild( $this->createElement( 'user', $ticket->user->user_login ) );

		// Include user attributes:

		$this->setUserAttributes( $ticket );

		// Include Proxy-Granting Ticket in successful `/proxyValidate` responses:

		if ( $proxyGrantingTicket ) {
			$this->response->appendChild( $this->createElement(
				'proxyGrantingTicket', $proxyGrantingTicket ) );
		}

		// Include proxies in successful `/proxyValidate` responses:

		if ( ! empty( $proxies ) ) {
			$xmlProxies = $this->createElement( 'proxies' );

			foreach ($proxies as $proxy) {
				$xmlProxies->appendChild( $this->createElement(
					'proxy', $proxy ) );
			}

			$this->response->appendChild( $xmlProxies );
		}
	}

	/**
	 * Add user attributes to the response.
	 *
	 * @param CAS\Ticket $ticket Validated ticket.
	 *
	 * @uses \apply_filters()
	 */
	protected function setUserAttributes( CAS\Ticket $ticket ) {
		$attributeKeys = Options::get( 'attributes' );

		$attributes = array();

		foreach ( $attributeKeys as $key ) {
			$attributes[ $key ] = implode( ',', (array) $ticket->user->get( $key ) );
		}

		/**
		 * Allows developers to change the list of (key, value) pairs before they're included
		 * in a `/serviceValidate` response.
		 *
		 * @param  array   $attributes List of attributes to output.
		 * @param  WP_User $user       Authenticated user.
		 */
		$attributes = \apply_filters( 'cas_server_validation_user_attributes', $attributes, $ticket->user );

		if ( ! is_array( $attributes ) || empty( $attributes ) ) {
			return;
		}

		$xmlAttributes = $this->createElement( 'attributes' );

		foreach ($attributes as $key => $value) {
			$xmlAttribute = $this->createElement( $key, $value );
			$xmlAttributes->appendChild( $xmlAttribute );
		}

		$this->response->appendChild( $xmlAttributes );
	}
}
