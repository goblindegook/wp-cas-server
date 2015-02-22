<?php

namespace Cassava\Exception;

/**
 * CAS request exception.
 *
 * @since 1.1.0
 */
class RequestException extends GeneralException {

	/**
	 * Invalid Request Error
	 */
	const ERROR_INVALID_REQUEST = 'INVALID_REQUEST';

	/**
	 * Invalid Service Error
	 */
	const ERROR_INVALID_SERVICE = 'INVALID_SERVICE';

	/**
	 * Generates a new ticket exception.
	 *
	 * @param string $message Exception description.
	 * @param string $casCode CAS error code (default: "INVALID_REQUEST").
	 */
	public function __construct( $message = '', $casCode = self::ERROR_INVALID_REQUEST ) {
		parent::__construct( $message, $casCode );
	}

}
