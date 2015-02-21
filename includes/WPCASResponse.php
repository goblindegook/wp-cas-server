<?php

if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'WPCASResponse' ) ) {

	class WPCASResponse {

		/**
		 * CAS XML Namespace URI
		 */
		const CAS_NS = 'http://www.yale.edu/tp/cas';

		/**
		 * XML response.
		 * @var DOMDocument
		 */
		protected $xmlResponse;

		/**
		 * Response constructor.
		 */
		public function __construct() {
			$this->xmlResponse = new DOMDocument( '1.0', get_bloginfo( 'charset' ) );
		}

		/**
		 * Sets an HTTP response header.
		 *
		 * @param string $key   Header key.
		 * @param string $value Header value.
		 */
		protected function setResponseHeader( $key, $value ) {
			if (headers_sent()) return;
			header( sprintf( '%s: %s', $key, $value ) );
		}

		/**
		 * Create response element.
		 * @param  [type] $element [description]
		 * @param  [type] $inner   [description]
		 * @return [type]          [description]
		 */
		public function createElement( $element, $inner = null ) {
			if ( $inner === null ) {
				return $this->xmlResponse->createElementNS( static::CAS_NS, $element );
			}

			return $this->xmlResponse->createElementNS( static::CAS_NS, $element, $inner );
		}

		/**
		 * Wrap a CAS 2.0 XML response and output it as a string.
		 *
		 * This method attempts to set a `Content-Type: text/xml` HTTP response header.
		 *
		 * @param  DOMNode $response XML response contents for a CAS 2.0 request.
		 *
		 * @return string            CAS 2.0 server response as an XML string.
		 *
		 * @uses get_bloginfo()
		 */
		public function prepareXml( DOMNode $response ) {
			$this->setResponseHeader( 'Content-Type', 'text/xml; charset=' . get_bloginfo( 'charset' ) );

			$root = $this->createElement( 'cas:serviceResponse' );
			$root->appendChild( $response );

			// Removing all child nodes from response document:

			while ($this->xmlResponse->firstChild) {
				$this->xmlResponse->removeChild( $this->xmlResponse->firstChild );
			}

			$this->xmlResponse->appendChild( $root );

			return $this->xmlResponse->saveXML();
		}

		/**
		 * XML error response to a CAS 2.0 request.
		 *
		 * @param  WP_Error   $error Error object.
		 * @param  string     $tag   XML tag for the error (default: "authenticationFailure").
		 *
		 * @return DOMElement        CAS error response XML fragment.
		 *
		 * @uses do_action()
		 * @uses is_wp_error()
		 */
		public function xmlError( WP_Error $error = null, $tag = 'authenticationFailure' ) {

			/**
			 * Fires if the CAS server has to return an XML error.
			 *
			 * @param WP_Error $error WordPress error to return as XML.
			 */
			do_action( 'cas_server_error', $error );

			$message = __( 'Unknown error', 'wp-cas-server' );
			$code    = WPCASException::ERROR_INTERNAL_ERROR;

			if ($error) {
				$code    = $error->get_error_code();
				$message = $error->get_error_message( $code );
			}

			$response = $this->createElement( "cas:$tag", $message );

			$response->setAttribute( 'code', $code );

			return $this->prepareXml( $response );
		}

	}

}
