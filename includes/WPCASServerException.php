<?php
/**
 * Implements CAS server exception classes for the WP CAS Server plugin.
 * 
 * @package \WPCASServerPlugin\Server
 * @version 1.1.0
 */

if (!defined( 'ABSPATH' )) exit; // No monkey business.


if (!class_exists( 'WPCASServerException' )) {
    /**
     * Request exception.
     * 
     * @since 1.1.0
     */
    class WPCASServerException extends Exception {

	    /**
	     * Internal Error
	     */
	    const ERROR_INTERNAL_ERROR  = 'INTERNAL_ERROR';

	    /**
	     * Invalid Service Error
	     */
	    const ERROR_INVALID_SERVICE = 'INVALID_SERVICE';

	    /**
	     * Authentication error slug.
	     */
	    const SLUG_AUTHENTICATION = 'authenticationFailure';

	    /**
	     * Proxy validation error slug.
	     */
	    const SLUG_PROXY = 'proxyFailure';

	    /**
	     * Error code.
	     * @var string
	     */
	    protected $casCode;

	    /**
	     * Exception constructor.
	     * 
	     * @param string $message Exception message.
	     * @param string $casCode CAS error code (default: "INTERNAL_ERROR").
	     */
    	public function __construct ( $message, $casCode = self::ERROR_INTERNAL_ERROR ) {
    		parent::__construct( $message );

    		$this->casCode = $casCode;
    	}

    	/**
    	 * Error code getter.
    	 * 
    	 * @return string Associated error code.
    	 */
    	final public function getCASCode() {
    		return $this->casCode;
    	}

    	/**
    	 * Returns a WordPress error object based on the exception.
         * 
         * @param  string   $slug Error slug, either `authenticationError` or `proxyError`.
         * 
    	 * @return WP_Error       WordPress error.
    	 */
    	public function getErrorInstance ( $slug = self::SLUG_AUTHENTICATION ) {
    		return new WP_Error( $slug, $this->message, array( 'code' => $this->casCode ) );
    	}

    }
}


if (!class_exists( 'WPCASRequestException' )) {
    /**
     * Request exception.
     * 
     * @since 1.1.0
     */
    class WPCASRequestException extends WPCASServerException {

	    /**
	     * Invalid Request Error
	     */
	    const ERROR_INVALID_REQUEST = 'INVALID_REQUEST';

        /**
         * Generates a new ticket exception.
         * 
         * @param string $message Exception description.
         * @param string $casCode CAS error code (default: "INVALID_REQUEST").
         */
        public function __construct ( $message, $casCode = self::ERROR_INVALID_REQUEST ) {
            parent::__construct( $message, $casCode );
        }

    }
}


if (!class_exists( 'WPCASTicketException' )) {
    /**
     * Ticket exception.
     * 
     * @version 1.1.0
     * @since   1.1.0
     */
    class WPCASTicketException extends WPCASServerException {

	    /**
	     * Invalid Ticket Error
	     */
	    const ERROR_INVALID_TICKET  = 'INVALID_TICKET';

	    /**
	     * Bad Proxy-Granting Ticket Error
	     */
	    const ERROR_BAD_PGT         = 'BAD_PGT';

        /**
         * Generates a new ticket exception.
         * 
         * @param string $message Exception description.
         * @param string $casCode CAS error code (default: "INVALID_TICKET").
         */
        public function __construct ( $message, $casCode = self::ERROR_INVALID_TICKET ) {
            parent::__construct( $message, $casCode );
        }

    }
}
