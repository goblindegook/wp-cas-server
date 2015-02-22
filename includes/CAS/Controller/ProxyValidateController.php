<?php
/**
 * proxyValidate controller class.
 *
 * @version 1.2.0
 * @since 1.2.0
 */

namespace Cassava\CAS\Controller;

use Cassava\CAS;

/**
 * Implements CAS proxy validation.
 *
 * `/proxyValidate` must perform the same validation tasks as `/serviceValidate` and
 * additionally validate proxy tickets. `/proxyValidate` must be capable of validating both
 * service tickets and proxy tickets.
 *
 * @since 1.2.0
 */
class ProxyValidateController extends ServiceValidateController {

	/**
	 * Valid ticket types.
	 *
	 * `/proxyValidate` checks the validity of both service and proxy tickets.
	 *
	 * @var array
	 */
	protected $validTicketTypes = array(
		CAS\Ticket::TYPE_ST,
		CAS\Ticket::TYPE_PT,
	);
}
