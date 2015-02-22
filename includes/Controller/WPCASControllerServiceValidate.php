<?php
/**
 * serviceValidate controller class.
 *
 * @package \WPCASServerPlugin\Server
 * @version 1.2.0
 * @since 1.2.0
 */

/**
 * Implements CAS service validation.
 *
 * `/serviceValidate` checks the validity of a service ticket and does not handle proxy
 * authentication. CAS MUST respond with a ticket validation failure response when a proxy
 * ticket is passed to `/serviceValidate`.
 *
 * @since 1.2.0
 */
class WPCASControllerServiceValidate extends WPCASControllerValidate {

	/**
	 * Valid ticket types.
	 *
	 * @var array
	 */
	protected $validTicketTypes = array(
		WPCASTicket::TYPE_ST,
	);

	/**
	 * Handles ticket validation requests.
	 *
	 * This method attempts to set a `Content-Type: text/xml` HTTP response header.
	 *
	 * @param  array  $request Request arguments.
	 * @return string          Response XML string.
	 *
	 * @todo Accept proxy callback URL (pgtUrl) parameter.
	 * @todo Accept renew parameter.
	 */
	public function handleRequest( $request ) {
		$service = isset( $request['service'] ) ? $request['service'] : '';
		$ticket  = isset( $request['ticket'] )  ? $request['ticket']  : '';
		// $pgtUrl = isset( $request['pgtUrl'] ) ? $request['pgtUrl'] : '';
		// $renew  = isset( $request['renew'] ) && 'true' === $request['renew'];

		$response = new WPCASResponseValidate();

		try {
			$ticket = $this->validateRequest( $ticket, $service );
			$response->setTicket( $ticket );
		}
		catch (WPCASException $exception) {
			$response->setError( $exception->getErrorInstance() );
		}

		$this->server->setResponseContentType( 'text/xml' );

		return $response->prepare();
	}
}
