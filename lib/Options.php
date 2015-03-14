<?php
/**
 * Main Cassava plugin file.
 *
 * @version 1.2.0
 * @since   1.2.0
 */

namespace Cassava;

/**
 * Plugin options class.
 *
 * @since 1.0.0
 */
class Options {

	/**
	 * Plugin options key.
	 */
	const KEY = 'wp_cas_server';

	/**
	 * Default plugin options.
	 * @var array
	 */
	private static $defaults = array(
		/**
		 * CAS server endpoint path fragment (e.g. `<scheme>://<host>/wp-cas/login`).
		 */
		'endpoint_slug'      => Plugin::ENDPOINT_SLUG,

		/**
		 * Service ticket expiration, in seconds [0..300].
		 */
		'expiration'         => 30,

		/**
		 * `allow_ticket_reuse` exists as a workaround for potential issues with
		 * WordPress's Transients API.
		 */
		'allow_ticket_reuse' => false,

		/**
		 * @todo Allow requests from these service URIs only.
		 */
		'allowed_services'   => array(),

		/**
		 * User attributes to return on a successful `/serviceValidate` response.
		 */
		'attributes'         => array(),
	);

	/**
	 * Gets all options.
	 *
	 * @return array All plugin options.
	 */
	public static function getAll() {
		return \get_option( static::KEY );
	}

	/**
	 * Get plugin option by key.
	 *
	 * @param  string $key     Plugin option key to return.
	 * @param  mixed  $default Option value to return if `$key` is not found.
	 *
	 * @return mixed           Plugin option value.
	 *
	 * @uses \get_option()
	 */
	public static function get( $key = '', $default = null ) {
		$options = \get_option( static::KEY );
		return isset( $options[ $key ] ) ? $options[ $key ] : $default;
	}

	/**
	 * Set plugin option by key.
	 *
	 * @param string $key   Plugin option key to set.
	 * @param mixed  $value Plugin option value to set.
	 *
	 * @uses \get_option()
	 * @uses \update_option()
	 */
	public static function set( $key, $value ) {
		if ( ! isset( $key ) ) {
			return;
		}

		$options = \get_option( static::KEY );

		if ( ! isset( $value ) ) {
			unset( $options[ $key ] );
		} else {
			$options[ $key ] = $value;
		}

		\update_option( static::KEY, $options );
	}

	/**
	 * Set the default plugin options in the database.
	 *
	 * @uses \get_option()
	 * @uses \update_option()
	 */
	public static function setDefaults() {
		$options = \get_option( static::KEY, self::$defaults );
		\update_option( static::KEY, $options );
	}

}
