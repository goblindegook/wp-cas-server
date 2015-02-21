<?php
/**
 * proxyValidate controller class.
 *
 * @package \WPCASServerPlugin\Server
 * @version 1.2.0
 */

/**
 * Implements CAS proxy validation.
 *
 * @since 1.2.0
 */
class WPCASControllerProxyValidate extends WPCASControllerValidate {

	/**
	 * Valid ticket types.
	 *
	 * `/proxyValidate` checks the validity of both service and proxy tickets.
	 *
	 * @var array
	 */
	protected $validTicketTypes = array(
		WPCASTicket::TYPE_ST,
		WPCASTicket::TYPE_PT,
	);

	/**
	 * `/proxyValidate` must perform the same validation tasks as `/serviceValidate` and
	 * additionally validate proxy tickets. `/proxyValidate` must be capable of validating both
	 * service tickets and proxy tickets.
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
		// $pgtUrl  = !empty( $request['pgtUrl'] ) ? $request['pgtUrl'] : '';
		// $renew   = isset( $request['renew'] ) && 'true' === $request['renew'];

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
