<?php
/**
 * Proxy controller class.
 *
 * @version 1.2.0
 * @since 1.2.0
 */

namespace Cassava\CAS\Controller;

use Cassava\CAS;
use Cassava\Exception\GeneralException;
use Cassava\Exception\RequestException;
use Cassava\Exception\TicketException;
use Cassava\Plugin;

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
class ProxyController extends ValidateController {

	/**
	 * Valid ticket types.
	 *
	 * `/proxy` checks the validity of the proxy-granting ticket passed.
	 *
	 * @var array
	 */
	protected $validTicketTypes = array(
		CAS\Ticket::TYPE_PGT,
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
		$pgt           = isset( $request['pgt'] )           ? $request['pgt']           : '';
		$targetService = isset( $request['targetService'] ) ? $request['targetService'] : '';

		$response = new CAS\Response\ProxyResponse;

		try {
			$ticket      = $this->validateRequest( $pgt, $targetService );
			$proxyTicket = new CAS\Ticket( CAS\Ticket::TYPE_PT, $ticket->user, $targetService );
			$response->setTicket( $proxyTicket );
		}
		catch ( GeneralException $exception ) {
			$response->setError( $exception->getErrorInstance(), 'proxyFailure' );
		}

		$this->server->setResponseContentType( 'text/xml' );

		return $response->prepare();
	}

	/**
	 * Validates a proxy ticket, returning a ticket object, or throws an exception.
	 *
	 * @param  string              $ticket  Service or proxy ticket.
	 * @param  string              $service Service URI.
	 * @return \Cassava\CAS\Ticket          Valid ticket object associated with request.
	 *
	 * @throws \Cassava\Exception\RequestException
	 * @throws \Cassava\Exception\TicketException
	 */
	public function validateRequest( $ticket = '', $service = '' ) {
		try {
			return parent::validateRequest( $ticket, $service );

		} catch ( TicketException $exception ) {
			throw new TicketException( $exception->getMessage(),
				TicketException::ERROR_BAD_PGT );
		}
	}

}
