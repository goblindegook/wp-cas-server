<?php
/**
 * proxyValidate controller class.
 *
 * @package \WPCASServerPlugin\Server
 * @version 1.2.0
 * @since 1.2.0
 */

/**
 * Implements CAS proxy validation.
 *
 * `/proxyValidate` must perform the same validation tasks as `/serviceValidate` and
 * additionally validate proxy tickets. `/proxyValidate` must be capable of validating both
 * service tickets and proxy tickets.
 *
 * @since 1.2.0
 */
class WPCASControllerProxyValidate extends WPCASControllerServiceValidate {

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
}
