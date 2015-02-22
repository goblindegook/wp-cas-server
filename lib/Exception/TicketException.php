<?php

namespace Cassava\Exception;

/**
 * CAS ticket exception.
 *
 * @version 1.1.0
 * @since   1.1.0
 */
class TicketException extends GeneralException {

	/**
	 * Invalid Ticket Error
	 */
	const ERROR_INVALID_TICKET = 'INVALID_TICKET';

	/**
	 * Bad Proxy-Granting Ticket Error
	 */
	const ERROR_BAD_PGT        = 'BAD_PGT';

	/**
	 * Generates a new ticket exception.
	 *
	 * @param string $message Exception description.
	 * @param string $casCode CAS error code (default: "INVALID_TICKET").
	 */
	public function __construct( $message = '', $casCode = self::ERROR_INVALID_TICKET ) {
		parent::__construct( $message, $casCode );
	}

}
