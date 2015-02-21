<?php
/**
 * Contains a CAS Server interface.
 *
 * @package \WPCASServerPlugin\Server
 * @version 1.0.1
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * CAS server class interface definition.
 *
 * @since 1.0.0
 */
interface ICASServer {

	/**
	 * RFC 1123 Date-Time Format
	 */
	const RFC1123_DATE_FORMAT = 'D, d M Y H:i:s T';

	/**
	 * Handle a CAS server request for a specific URI.
	 *
	 * @param  string $path    CAS request URI.
	 *
	 * @return string          Request response.
	 */
	public function handleRequest( $path );

	/**
	 * Handles `/login` method requests [CAS 1.0 and 2.0].
	 *
	 * @param  array $args Request arguments.
	 */
	public function login( $args );

	/**
	 * Handles `/logout` method requests [CAS 1.0 and 2.0].
	 *
	 * @param  array $args Request arguments.
	 */
	public function logout( $args );

	/**
	 * Handles `/proxy` method requests [CAS 2.0].
	 *
	 * @param  array $args Request arguments.
	 */
	public function proxy( $args );

	/**
	 * Handles `/proxyValidate` method requests [CAS 2.0].
	 *
	 * @param  array $args Request arguments.
	 */
	public function proxyValidate( $args );

	/**
	 * Handles `/serviceValidate` method requests [CAS 2.0].
	 *
	 * @param  array $args Request arguments.
	 *
	 * @return string      Validation response.
	 */
	public function serviceValidate( $args );

	/**
	 * Handles `/validate` method requests [CAS 1.0].
	 *
	 * @param  array $args Request arguments.
	 *
	 * @return string      Validation response.
	 */
	public function validate( $args );
}
