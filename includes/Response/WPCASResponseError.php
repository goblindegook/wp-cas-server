<?php

if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'WPCASResponseError' ) ) {

	/**
	 * XML error response to a CAS 2.0 request.
	 */
	class WPCASResponseError extends WPCASResponse {

		/**
		 * Thrown error.
		 * @var WP_Error
		 */
		private $error;

		/**
		 * XML error tag.
		 * @var string
		 */
		private $tag;

		/**
		 * Set error response.
		 *
		 * @param WP_Error $error Response error.
		 * @param string   $tag   Response XML tag (defaults to `authenticationFailure`).
		 */
		public function setError( WP_Error $error, $tag = 'authenticationFailure' ) {
			/**
			 * Fires if the CAS server has to return an XML error.
			 *
			 * @param WP_Error $error WordPress error to return as XML.
			 */
			do_action( 'cas_server_error', $error );

			$this->tag = $tag;
			$message   = __( 'Unknown error', 'wp-cas-server' );
			$code      = WPCASException::ERROR_INTERNAL_ERROR;

			if ( isset( $error ) ) {
				$this->error = $error;
				$code        = $this->error->get_error_code();
				$message     = $this->error->get_error_message( $code );
			}

			$response = $this->createElement( 'cas:' . $this->tag, $message );

			$response->setAttribute( 'code', $code );

			$this->setResponse( $response );
		}

	}

}
