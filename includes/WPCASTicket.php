<?php
/**
 * Implements CAS tickets for the plugin's CAS server.
 * 
 * @package \WPCASServerPlugin\Ticket
 * @version 1.1.0
 * @since   1.1.0
 */


if (!class_exists( 'WPCASTicket' )) {

    /**
     * Class that implements CAS tickets.
     * 
     * @version 1.1.0
     * @since   1.1.0
     */
    class WPCASTicket {

        /**
         * Service Ticket
         */
        const TYPE_ST               = 'ST';

        /**
         * Proxy Ticket
         */
        const TYPE_PT               = 'PT';

        /**
         * Proxy-Granting Ticket
         */
        const TYPE_PGT              = 'PGT';

        /**
         * Proxy-Granting Ticket IOU
         */
        const TYPE_PGTIOU           = 'PGTIOU';

        /**
         * Ticket-Granting Cookie
         */
        const TYPE_TGC              = 'TGC';

        /**
         * Login Ticket
         */
        const TYPE_LT               = 'LT';

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
         * @param integer $expiration Time until ticket expires, in seconds.
         * @param float   $expires    Expiration timestamp, in seconds.
         *                            Freshly generated tickets should not provide this value.
         */
        public function __construct ( $type, $user, $service, $expiration = 0, $expires = 0 ) {

            $this->type       = $type;
            $this->user       = $user;
            $this->service    = $service;
            $this->expires    = $expires;

            /**
             * Freshly generated tickets have no expiration timestamp:
             */
            if (!$expires) {
                /**
                 * This filter allows developers to override the default ticket expiration period.
                 * 
                 * @param  int     $expiration Ticket expiration period (in seconds).
                 * @param  string  $type       Type of ticket to set.
                 * @param  WP_User $user       Authenticated user associated with the ticket.
                 */
                $expiration = apply_filters( 'cas_server_ticket_expiration', $expiration, $type, $user );

                $this->expires = microtime( true ) + $expiration;

                $this->markUnused();
            }
        }

        /**
         * Magic method that returns the ticket as a string.
         * 
         * @return string Ticket as string.
         */
        public function __toString () {
            return $this->type . '-' . base64_encode(
                implode( '|', array( $this->user->user_login, $this->service, $this->expires, $this->generateSignature() ) ) );
        }

        /**
         * Create a new ticket instance from a ticket string.
         * 
         * @param  string      $ticket Ticket string.
         * 
         * @return WPCASTicket         Ticket object.
         * 
         * @throws WPCASTicketException Invalid ticket exception.
         * 
         * @uses get_user_by()
         * @uses is_wp_error()
         */
        public static function fromString ( $ticket ) {

            if (strpos( $ticket, '-' ) === false) {
                $ticket = static::TYPE_ST . '-' . $ticket;
            }

            list( $type, $content ) = explode( '-', $ticket, 2 );

            $elements = explode( '|', base64_decode( $content ) );

            if (count( $elements ) < 4) {
                throw new WPCASTicketException( __( 'Ticket is malformed.', 'wp-cas-server' ) );
            }

            list( $login, $service, $expires, $signature ) = $elements;

            if ($expires < time()) {
                throw new WPCASTicketException( __( 'Ticket has expired.', 'wp-cas-server' ) );
            }

            $user = get_user_by( 'login', $login );

            if (!$user || is_wp_error( $user )) {
                throw new WPCASTicketException( __( 'Ticket does not match a valid user.', 'wp-cas-server' ) );
            }

            $ticket = new WPCASTicket( $type, $user, $service, null, $expires );
            
            if ($ticket->generateSignature() !== $signature) {
                throw new WPCASTicketException( __( 'Ticket is corrupted.', 'wp-cas-server' ) );
            }

            if ($ticket->isUsed()) {
                throw new WPCASTicketException( __( 'Ticket is unknown or has already been used.', 'wp-cas-server' ) );
            }

            return $ticket;
        }

        /**
         * Generate security key for a ticket.
         * 
         * @return string Generated security key.
         * 
         * @uses wp_hash()
         */
        private function generateKey () {
            return wp_hash( $this->user->user_login . '|' . substr($this->user->user_pass, 8, 4) . '|' . $this->expires );
        }

        /**
         * Create a ticket signature by concatenating components and signing them with a key.
         * 
         * @return string      Generated signature hash.
         */
        public function generateSignature () {
            return hash_hmac( 'sha1', implode( '|', array( $this->user->login, $this->service, $this->expires ) ), $this->generateKey() );
        }

        /**
         * Validates a ticket string against a list of expected types.
         * 
         * @param  string $ticket Ticket string to validate.
         * @param  array  $types  List of allowed type prefixes.
         * 
         * @throws WPCASTicketException
         */
        public static function validateAllowedTypes ( $ticket, $types = array() ) {
            list( $type ) = explode( '-', $ticket, 2 );

            if (!in_array( $type, $types )) {
                throw new WPCASTicketException( __( 'Ticket type cannot be validated.', 'wp-cas-server' ) );
            }
        }

        /**
         * Remember a fresh ticket using WordPress's Transients API.
         * 
         * @uses set_transient()
         */
        private function markUnused () {
            $key = $this->generateKey();
            set_transient( WPCASServerPlugin::TRANSIENT_PREFIX . $key, (string) $this, $this->expires );
        }

        /**
         * Remember a ticket as having been used using WordPress's Transients API.
         * 
         * @uses delete_transient()
         */
        public function markUsed () {
            $key = $this->generateKey();
            delete_transient( WPCASServerPlugin::TRANSIENT_PREFIX . $key );
        }

        /**
         * Checks whether a ticket has been used using WordPress's Transients API.
         * 
         * @return boolean      Whether the ticket has been used.
         * 
         * @uses get_transient()
         */
        public function isUsed () {

            if (WPCASServerPlugin::getOption( 'allow_ticket_reuse' )) {
                return false;
            }

            $key = $this->generateKey();

            return !get_transient( WPCASServerPlugin::TRANSIENT_PREFIX . $key );
        }

    }

}