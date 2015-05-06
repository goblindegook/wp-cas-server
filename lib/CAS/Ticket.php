<?php
/**
 * Implements CAS tickets for the plugin's CAS server.
 *
 * @version 1.1.0
 * @since   1.1.0
 */

namespace Cassava\CAS;

use Cassava\Exception\TicketException;
use Cassava\Options;
use Cassava\Plugin;

/**
 * Class that implements CAS tickets.
 *
 * @version 1.1.0
 * @since   1.1.0
 */
class Ticket {

	/**
	 * Service Ticket
	 */
	const TYPE_ST = 'ST';

	/**
	 * Proxy Ticket
	 */
	const TYPE_PT = 'PT';

	/**
	 * Proxy-Granting Ticket
	 */
	const TYPE_PGT = 'PGT';

	/**
	 * Proxy-Granting Ticket IOU
	 */
	const TYPE_PGTIOU = 'PGTIOU';

	/**
	 * Ticket-Granting Cookie
	 */
	const TYPE_TGC = 'TGC';

	/**
	 * Login Ticket
	 */
	const TYPE_LT = 'LT';

	/**
	 * Ticket type.
	 * @var string
	 */
	public $type;

	/**
	 * Authenticated WordPress user who owns the ticket.
	 * @var WP_User
	 */
	public $user;

	/**
	 * URL for the service that requested authentication.
	 * @var string
	 */
	public $service;

	/**
	 * Expiration timestamp, in seconds.
	 * @var float
	 */
	public $expires;

	/**
	 * CAS ticket constructor.
	 *
	 * @param string  $type       Ticket type.
	 * @param WP_User $user       Authenticated WordPress user who owns the ticket.
	 * @param string  $service    URL for the service that requested authentication.
	 * @param double  $expires    Expiration timestamp, in seconds.
	 *                            Freshly generated tickets should not provide this value.
	 *
	 * @todo "Remember-Me" tickets should have an expiration date of up to 3 months.
	 */
	public function __construct( $type, $user, $service, $expires = 0.0 ) {
		$this->type    = $type;
		$this->user    = $user;
		$this->service = esc_url_raw( $service );
		$this->expires = $expires;

		/**
		 * Freshly generated tickets have no expiration timestamp:
		 */
		if ( ! $expires ) {
			$expiration  = Options::get( 'expiration', 30 );

			/**
			 * This filter allows developers to override the default ticket expiration period.
			 *
			 * @param  int     $expiration Ticket expiration period (in seconds).
			 * @param  string  $type       Type of ticket to set.
			 * @param  WP_User $user       Authenticated user associated with the ticket.
			 */
			$expiration = \apply_filters( 'cas_server_ticket_expiration', $expiration, $type, $user );

			$this->expires = microtime( true ) + $expiration;

			$this->markUnused();
		}
	}

	/**
	 * Magic method that returns the ticket as a string.
	 *
	 * @return string Ticket as string.
	 */
	public function __toString() {
		return $this->encodeTicket();
	}

	/**
	 * Create a new ticket instance from a ticket string.
	 *
	 * @param  string $string Ticket string.
	 * @return Ticket         Ticket object.
	 *
	 * @throws \Cassava\Exception\TicketException
	 *
	 * @uses \__()
	 * @uses \get_user_by()
	 * @uses \is_wp_error()
	 */
	public static function fromString( $string ) {

		$components = static::decodeTicket( $string );

		if ( $components['expires'] < time() ) {
			throw new TicketException( __( 'Expired ticket.', 'wp-cas-server' ) );
		}

		$user = \get_user_by( 'login', $components['login'] );

		if ( ! $user || \is_wp_error( $user ) ) {
			throw new TicketException( __( 'No user matches ticket.', 'wp-cas-server' ) );
		}

		$ticket = new static( $components['type'], $user, $components['service'], $components['expires'] );

		if ( $ticket->generateSignature() !== $components['signature'] ) {
			throw new TicketException( __( 'Corrupted ticket.', 'wp-cas-server' ) );
		}

		if ( $ticket->isUsed() ) {
			throw new TicketException( __( 'Unknown or used ticket.', 'wp-cas-server' ) );
		}

		return $ticket;
	}

	/**
	 * Serialize ticket components into a string.
	 *
	 * @return string Ticket string.
	 */
	protected function encodeTicket() {
		$components = array(
			$this->user->user_login,     // 1. User login
			urlencode( $this->service ), // 2. Service URL
			$this->expires,              // 3. Expiration timestamp
			$this->generateSignature(),  // 4. Cryptographic signature
		);

		return $this->type . '-' . base64_encode( implode( '|', $components ) );
	}

	/**
	 * Extracts components from a base64 encoded ticket string.
	 *
	 * @param  string $ticket Ticket string (minus the prefix).
	 * @return array          Ticket components.
	 *
	 * @throws \Cassava\Exception\TicketException
	 *
	 * @uses \__()
	 */
	protected static function decodeTicket( $ticket ) {

		if ( strpos( $ticket, '-' ) === false ) {
			$ticket = static::TYPE_ST . '-' . $ticket;
		}

		list( $type, $content ) = explode( '-', $ticket, 2 );

		$values = explode( '|', base64_decode( $content ) );

		if (count( $values ) < 4) {
			throw new TicketException( __( 'Malformed ticket.', 'wp-cas-server' ) );
		}

		$keys       = array( 'login', 'service', 'expires', 'signature' );
		$components = array_combine( $keys, $values );

		$components['type']    = $type;
		$components['service'] = urldecode( $components['service'] );

		return $components;
	}

	/**
	 * Generate security key for a ticket.
	 *
	 * @return string Generated security key.
	 *
	 * @uses \wp_hash()
	 */
	protected function generateKey() {
		$keyComponents = array(
			$this->user->user_login,
			substr( $this->user->user_pass, 8, 4 ),
			$this->expires,
		);
		return \wp_hash( implode( '|', $keyComponents ) );
	}

	/**
	 * Create a ticket signature by concatenating components and signing them with a key.
	 *
	 * @return string      Generated signature hash.
	 */
	public function generateSignature() {
		$signatureComponents = array(
			$this->user->login,
			$this->service,
			$this->expires,
		);
		return hash_hmac( 'sha1', implode( '|', $signatureComponents ), $this->generateKey() );
	}

	/**
	 * Validates a ticket string against a list of expected types.
	 *
	 * @param  string $ticket Ticket string to validate.
	 * @param  array  $types  List of allowed type prefixes.
	 *
	 * @throws \Cassava\Exception\TicketException
	 */
	public static function validateAllowedTypes( $ticket, $types = array() ) {
		list( $type ) = explode( '-', $ticket, 2 );

		if ( ! in_array( $type, $types ) ) {
			throw new TicketException( __( 'Ticket type cannot be validated.', 'wp-cas-server' ) );
		}
	}

	/**
	 * Remember a fresh ticket using WordPress's Transients API.
	 *
	 * @uses \set_transient()
	 */
	protected function markUnused() {
		$key = $this->generateKey();
		\set_transient( Plugin::TRANSIENT_PREFIX . $key, (string) $this, $this->expires );
	}

	/**
	 * Remember a ticket as having been used using WordPress's Transients API.
	 *
	 * @uses \delete_transient()
	 *
	 * @todo "Remember-Me" tickets should not be invalidated.
	 */
	public function markUsed() {
		$key = $this->generateKey();
		\delete_transient( Plugin::TRANSIENT_PREFIX . $key );
	}

	/**
	 * Checks whether a ticket has been used using WordPress's Transients API.
	 *
	 * @return boolean Whether the ticket has been used.
	 *
	 * @uses \get_transient()
	 */
	public function isUsed() {

		if ( Options::get( 'allow_ticket_reuse' ) ) {
			return false;
		}

		$key = $this->generateKey();

		return ! \get_transient( Plugin::TRANSIENT_PREFIX . $key );
	}

}
