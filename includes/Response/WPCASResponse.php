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
		 * XML response document.
		 * @var DOMDocument
		 */
		protected $document;

		/**
		 * XML response node.
		 * @var DOMNode
		 */
		protected $response;

		/**
		 * Response constructor.
		 */
		public function __construct() {
			$this->document = new DOMDocument( '1.0', get_bloginfo( 'charset' ) );
		}

		/**
		 * Response mutator.
		 * @param DOMNode $response Response DOM node.
		 */
		public function setResponse( DOMNode $response ) {
			$this->response = $response;
		}

		/**
		 * Create response element.
		 * @param  [type] $element [description]
		 * @param  [type] $inner   [description]
		 * @return [type]          [description]
		 */
		public function createElement( $element, $inner = null ) {
			if ( $inner === null ) {
				return $this->document->createElementNS( static::CAS_NS, $element );
			}

			return $this->document->createElementNS( static::CAS_NS, $element, $inner );
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
		public function prepare() {
			$root = $this->createElement( 'cas:serviceResponse' );
			$root->appendChild( $this->response );

			// Removing all child nodes from response document:

			while ($this->document->firstChild) {
				$this->document->removeChild( $this->document->firstChild );
			}

			$this->document->appendChild( $root );

			return $this->document->saveXML();
		}

	}

}
