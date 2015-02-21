<?php
/**
 * Validate controller class.
 *
 * @package \WPCASServerPlugin\Server
 * @version 1.2.0
 */

/**
 * Implements CAS logout.
 *
 * @since 1.2.0
 */
class WPCASControllerValidate extends WPCASController {

	/**
	 * Valid ticket types.
	 *
	 * `/validate` checks the validity of a service ticket. `/validate` is part of the CAS 1.0
	 * protocol and thus does not handle proxy authentication. CAS MUST respond with a ticket
	 * validation failure response when a proxy ticket is passed to `/validate`.
	 *
	 * @var array
	 */
	protected $validTicketTypes = array(
		WPCASTicket::TYPE_ST,
	);

	/**
	 * `/validate` is a CAS 1.0 protocol method that checks the validity of a service ticket.
	 *
	 * Being part of the CAS 1.0 protocol, `/validate` does not handle proxy authentication.
	 *
	 * The following HTTP request parameters may be specified:
	 *
	 * - `service` (required): The URL of the service for which the ticket was issued.
	 * - `ticket` (required): The service ticket issued by `/login`.
	 * - `renew` (optional): If this parameter is set, the server will force the user to reenter
	 *   their primary credentials.
	 *
	 * `/validate` will return one of the following two responses:
	 *
	 * On successful validation:
	 *
	 * ```
	 * yes\n
	 * username\n
	 * ```
	 *
	 * On validation failure:
	 *
	 * ```
	 * no\n
	 * \n
	 * ```
	 *
	 * This method will attempt to set a `Content-Type: text/plain` HTTP header when called.
	 *
	 * @param  array  $request Request arguments.
	 * @return string          Validation response.
	 */
	public function handleRequest( $request ) {
		$this->server->setResponseContentType( 'text/plain' );

		$service = isset( $request['service'] ) ? $request['service'] : '';
		$ticket  = isset( $request['ticket'] )  ? $request['ticket']  : '';

		try {
			$ticket = $this->validateRequest( $ticket, $service );
		}
		catch ( WPCASException $exception ) {
			return "no\n\n";
		}

		return "yes\n" . $ticket->user->user_login . "\n";
	}

	/**
	 * Validates a ticket, returning a ticket object, or throws an exception.
	 *
	 * Triggers the `cas_server_validation_success` action on ticket validation.
	 *
	 * @param  string      $ticket  Service or proxy ticket.
	 * @param  string      $service Service URI.
	 * @return WPCASTicket          Valid ticket object associated with request.
	 *
	 * @uses do_action()
	 *
	 * @throws WPCASRequestException
	 * @throws WPCASTicketException
	 */
	protected function validateRequest( $ticket = '', $service = '' ) {

		if ( empty( $ticket ) ) {
			throw new WPCASRequestException( __( 'Ticket is required.', 'wp-cas-server' ) );
		}

		if ( empty( $service ) ) {
			throw new WPCASRequestException( __( 'Service is required.', 'wp-cas-server' ) );
		}

		$service = esc_url_raw( $service );

		WPCASTicket::validateAllowedTypes( $ticket, $this->validTicketTypes );
		$ticket = WPCASTicket::fromString( $ticket );
		$ticket->markUsed();

		if ( $ticket->service !== $service ) {
			throw new WPCASRequestException(
				__( 'Ticket does not match the service provided.', 'wp-cas-server' ),
				WPCASRequestException::ERROR_INVALID_SERVICE );
		}

		/**
		 * Fires on successful ticket validation.
		 *
		 * @param WPCASTicket $ticket Valid ticket object.
		 */
		do_action( 'cas_server_validation_success', $ticket );

		return $ticket;
	}

}
