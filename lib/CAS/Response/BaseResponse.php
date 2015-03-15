<?php
/**
 * CAS response class.
 *
 * @package \WPCASServerPlugin\Server
 * @version 1.2.0
 * @since 1.2.0
 */

namespace Cassava\CAS\Response;

use Cassava\CAS;
use Cassava\Exception\GeneralException;

/**
 * Implements the base CAS response.
 *
 * @version 1.2.0
 */
class BaseResponse {

	/**
	 * CAS XML Namespace URI
	 */
	const CAS_NS = 'http://www.yale.edu/tp/cas';

	/**
	 * XML response document.
	 * @var \DOMDocument
	 */
	protected $document;

	/**
	 * XML response node.
	 * @var \DOMNode
	 */
	protected $response;

	/**
	 * Response constructor.
	 *
	 * @uses \get_bloginfo()
	 */
	public function __construct() {
		$this->document = new \DOMDocument( '1.0', \get_bloginfo( 'charset' ) );
	}

	/**
	 * Wrap a CAS 2.0 XML response and output it as a string.
	 *
	 * @return string CAS 2.0+ server response as an XML string.
	 */
	public function prepare() {
		$root = $this->createElement( 'serviceResponse' );

		if ( ! empty( $this->response ) ) {
			$root->appendChild( $this->response );
		}

		// Removing all child nodes from response document:
		while ( $this->document->firstChild ) {
			$this->document->removeChild( $this->document->firstChild );
		}

		$this->document->appendChild( $root );

		return $this->document->saveXML();
	}

	/**
	 * Set error response.
	 *
	 * @param \WP_Error|null $error Response error.
	 * @param string         $tag   Response XML tag (defaults to `authenticationFailure`).
	 *
	 * @uses \WP_Error
	 * @uses \do_action()
	 */
	public function setError( \WP_Error $error = null, $tag = 'authenticationFailure' ) {
		/**
		 * Fires if the CAS server has to return an XML error.
		 *
		 * @param WP_Error $error WordPress error to return as XML.
		 */
		\do_action( 'cas_server_error', $error );

		$message = __( 'Unknown error', 'wp-cas-server' );
		$code    = GeneralException::ERROR_INTERNAL_ERROR;

		if ( ! empty( $error ) ) {
			$code    = $error->get_error_code();
			$message = $error->get_error_message( $code );
		}

		$this->response = $this->createElement( $tag, $message );
		$this->response->setAttribute( 'code', $code );
	}

	/**
	 * Create response element.
	 *
	 * @param  string      $element Unqualified element tag name.
	 * @param  string|null $value   Optional element value.
	 * @return \DOMNode             XML element.
	 */
	protected function createElement( $element, $value = null ) {
		return $this->document->createElementNS( static::CAS_NS, "cas:$element", $value );
	}

}
