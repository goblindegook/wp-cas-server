<?php
/**
 * Proxy controller class.
 *
 * @package \WPCASServerPlugin\Server
 * @version 1.2.0
 * @since 1.2.0
 */

/**
 * Implements CAS proxy ticket generation.
 *
 * `/proxy` provides proxy tickets to services that have acquired proxy-granting tickets and
 * will be proxying authentication to back-end services.
 *
 * The following HTTP request parameters must be provided:
 *
 * - `pgt` (required): The proxy-granting ticket (PGT) acquired by the service during
 *   service ticket (ST) or proxy ticket (PT) validation.
 * - `targetService` (required): The service identifier of the back-end service. Note that not
 *   all back-end services are web services so this service identifier will not always be a URL.
 *   However, the service identifier specified here must match the "service" parameter specified
 *   to `/proxyValidate` upon validation of the proxy ticket.
 *
 * @since 1.2.0
 */
class WPCASControllerProxy extends WPCASControllerValidate {

	/**
	 * Valid ticket types.
	 *
	 * `/proxy` checks the validity of the proxy-granting ticket passed.
	 *
	 * @var array
	 */
	protected $validTicketTypes = array(
		WPCASTicket::TYPE_PGT,
	);

	/**
	 * Handles proxy ticket generation requests.
	 *
	 * This method attempts to set a `Content-Type: text/xml` HTTP response header.
	 *
	 * @param  array  $request Request arguments.
	 * @return string          Response XML string.
	 */
	public function handleRequest( $request ) {
		$pgt            = isset( $request['pgt'] )           ? $request['pgt']           : '';
		$targetService  = isset( $request['targetService'] ) ? $request['targetService'] : '';

		$response = new WPCASResponseProxy();

		try {
			$expiration  = WPCASServerPlugin::getOption( 'expiration', 30 );
			$ticket      = $this->validateRequest( $pgt, $targetService );
			$proxyTicket = new WPCASTicket( WPCASTicket::TYPE_PT, $ticket->user, $targetService, $expiration );
			$response->setTicket( $proxyTicket );
		}
		catch (WPCASException $exception) {
			$response->setError( $exception->getErrorInstance(), 'proxyFailure' );
		}

		$this->server->setResponseContentType( 'text/xml' );

		return $response->prepare();
	}

	/**
	 * Validates a proxy ticket, returning a ticket object, or throws an exception.
	 *
	 * @param  string      $ticket  Service or proxy ticket.
	 * @param  string      $service Service URI.
	 * @return WPCASTicket          Valid ticket object associated with request.
	 *
	 * @throws WPCASRequestException
	 * @throws WPCASTicketException
	 */
	public function validateRequest( $ticket = '', $service = '' ) {
		try {
			return parent::validateRequest( $ticket, $service );

		} catch (WPCASTicketException $exception) {
			throw new WPCASTicketException( $exception->getMessage(),
				WPCASTicketException::ERROR_BAD_PGT );
		}
	}

}
