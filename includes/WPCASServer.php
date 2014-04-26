<?php
/**
 * @package WPCASServerPlugin
 * @subpackage WPCASServer
 */

require_once( dirname( __FILE__ ) . '/ICASServer.php');

if (!class_exists( 'WPCASServer' )) :

class WPCASServer implements ICASServer {

    /**
     * XML response.
     * @var DOMDocument
     */
    protected $xmlResponse;

    /**
     * WordPress CAS Server constructor.
     * 
     * @uses get_option()
     */
    public function __construct () {
        $this->xmlResponse = new DOMDocument( '1.0', get_option( 'blog_charset' ) );
    }

    //
    // CAS Server Methods
    //

    /**
     * Get the list of routes supported by this CAS server and the callbacks each will invoke.
     * 
     * - `/login`
     * - `/logout`
     * - `/proxy`
     * - `/proxyValidate`
     * - `/serviceValidate`
     * - `/validate`
     * 
     * @return array Array containing supported routes as keys and their callbacks as values.
     * 
     * @uses apply_filters()
     */
    public function routes () {

        $cas_routes = array(
            'login'           => array( $this, 'login' ),
            'logout'          => array( $this, 'logout' ),
            'proxy'           => array( $this, 'proxy' ),
            'proxyValidate'   => array( $this, 'proxyValidate' ),
            'serviceValidate' => array( $this, 'serviceValidate' ),
            'validate'        => array( $this, 'validate' ),
            );

        return apply_filters( 'cas_server_routes', $cas_routes );
    }

    /**
     * Perform an HTTP redirect.
     * 
     * If the 'allowed_services' contains at least one host, it will always perform a safe
     * redirect.
     * 
     * Calling WPCASServer::_redirect() will _always_ terminate the request.
     * 
     * @param  string  $location [description]
     * @param  integer $status   [description]
     * 
     * @uses wp_redirect()
     * @uses wp_safe_redirect()
     */
    protected function _redirect ( $location, $status = 302 ) {

        if (!WPCASServerPlugin::get_option( 'allowed_services' )) {
            wp_redirect( $location, $status );
        }

        wp_safe_redirect( $location, $status );

        exit;
    }

    /**
     * Handle a CAS server request for a specific URI.
     * 
     * This method will attempt to set the following HTTP headers to prevent browser caching:
     * 
     * - `Pragma: no-cache`
     * - `Cache-Control: no-store`
     * - `Expires: <time of request>`
     * 
     * @param  string $path    CAS request URI.
     * 
     * @return string          Request response.
     * 
     * @uses apply_filters()
     * @uses do_action()
     * @uses is_wp_error()
     */
    public function handleRequest ( $path ) {

        if (!defined( 'CAS_REQUEST' )) define( 'CAS_REQUEST', true );

        $this->_setResponseHeader( 'Pragma'         , 'no-cache' );
        $this->_setResponseHeader( 'Cache-Control'  , 'no-store' );
        $this->_setResponseHeader( 'Expires'        , gmdate( ICASServer::RFC1123_DATE_FORMAT ) );

        /**
         * Fires before the CAS request is processed.
         * 
         * @param  string $path Requested URI path.
         * @return string       Filtered requested URI path.
         */
        do_action( 'cas_server_before_request', $path );

        if (empty( $path )) {
            $path = isset( $_SERVER['PATH_INFO'] ) ? $_SERVER['PATH_INFO'] : '/';
        }

        $result = $this->_dispatch( $path );

        if (is_wp_error( $result )) {
            $output = $this->_xmlError( $result );
        }
        else {
            $output = $result;
        }
        
        /**
         * Fires after the CAS request is processed.
         * 
         * @param string $path Requested URI path.
         */
        do_action( 'cas_server_after_request', $path );

        /**
         * Filters the CAS server response string.
         * 
         * @param string $output Response output string.
         * @param string $path   Requested URI path.
         */
        $output = apply_filters( 'cas_server_response', $output, $path );

        return $output;
    }

    /**
     * Dispatch the request for processing by the relevant callback as determined by the routes
     * list returned by `WPCASServer::routes()`.
     * 
     * @param  string            $path Requested URI path.
     * 
     * @return (string|WP_Error)       Service response string or WordPress error.
     * 
     * @uses apply_filters()
     * @uses is_wp_error()
     */
    protected function _dispatch ( $path ) {

        $enabled = apply_filters( 'cas_enabled', true );

        if (!$enabled) {
            return new WP_Error( 'authenticationFailure',
                __('The CAS server is disabled.', 'wordpress-cas-server'),
                array( 'code' => ICASServer::ERROR_INTERNAL_ERROR )
                );
        }

        foreach ($this->routes() as $route => $callback) {

            $match = preg_match( '@^' . $route . '/?$@', $path );

            if (!$match) {
                continue;
            }

            if (!is_callable( $callback )) {
                return new WP_Error( 'authenticationFailure',
                    __('The handler for the route is invalid.', 'wordpress-cas-server'),
                    array( 'code' => ICASServer::ERROR_INTERNAL_ERROR )
                    );
            }

            $args = $_GET;

            /**
             * Filters the callback arguments to be dispatched for the request.
             * 
             * Plugin developers may return a WP_Error object via the cas_server_dispatch_args
             * filter to abort the request.
             * 
             * @param array          $args     Arguments to pass the callback.
             * @param (string|array) $callback Callback function or method.
             * @param string         $path     Requested URI path.
             */
            $args = apply_filters( 'cas_server_dispatch_args', $args, $callback, $path );

            if (is_wp_error( $args )) {
                return $args;
            }

            return call_user_func( $callback, $args );
        }

        return new WP_Error( 'authenticationFailure',
            __( 'The server does not support the method requested.', 'wordpress-cas-server' ),
            array( 'code' => ICASServer::ERROR_INVALID_REQUEST )
            );
    }

    /**
     * Wraps calls to session_start() to prevent 'headers already sent' errors.
     */
    protected function _sessionStart () {
        if (headers_sent()) return;
        session_start();
    }

    /**
     * Sets an HTTP response header.
     * 
     * @param string $key   Header key.
     * @param string $value Header value.
     */
    protected function _setResponseHeader ( $key, $value ) {
        if (headers_sent()) return;
        header( sprintf( '%s: %s', $key, $value ) );
    }

    /**
     * Wrap a CAS 2.0 XML response and output it as a string.
     * 
     * @param  DOMNode $response XML response contents for a CAS 2.0 request.
     * 
     * @return string            CAS 2.0 server response as an XML string.
     */
    protected function _xmlResponse ( $response ) {
        $this->_setResponseHeader( 'Content-Type', 'text/xml; charset=' . get_option( 'blog_charset' ) );

        $root = $this->xmlResponse->createElementNS( ICASServer::CAS_NS, 'cas:serviceResponse' );
        $root->appendChild( $response );
        $this->xmlResponse->appendChild($root);

        return $this->xmlResponse->saveXML();
    }

    /**
     * XML success response to a CAS 2.0 validation request.
     * 
     * @param  WP_User    $user                 Authenticated WordPress user.
     * @param  string     $proxyGrantingTicket  Generated Proxy-Granting Ticket (PGT) to return.
     * @param  array      $proxies              List of proxy URIs.
     * 
     * @return DOMElement                       CAS success response XML fragment.
     * 
     * @uses apply_filters()
     * @uses get_userdata()
     */
    protected function _xmlValidateSuccess ( $user, $proxyGrantingTicket = '', $proxies = array() ) {

        $response = $this->xmlResponse->createElementNS( ICASServer::CAS_NS,
            "cas:authenticationSuccess" );

        // Include login name:
        
        $response->appendChild( $this->xmlResponse->createElementNS( ICASServer::CAS_NS,
            "cas:user", $user->get( 'user_login' ) ) );

        // CAS attributes:
        
        $attributes = WPCASServerPlugin::get_option( 'attributes' );
        
        if (is_array( $attributes ) && count( $attributes ) > 0) {

            $response_attributes = array();

            foreach ($attributes as $key) {
                $response_attributes[$key] = $user->get( $key );
            }

            /**
             * Allows developers to filter a list of (key, value) pairs before they're included
             * in a `/serviceValidate` response.
             * 
             * @param  array   $attributes List of attributes to filter.
             * @param  WP_User $user       Authenticated user.
             * 
             * @return array               Filtered list of attributes.
             */
            $response_attributes = apply_filters( 'cas_server_validation_extra_attributes', $response_attributes, $user );

            $xmlAttributes = $this->xmlResponse->createElementNS( ICASServer::CAS_NS, "cas:attributes" );

            foreach ($response_attributes as $key => $value) {
                $xmlAttribute = $this->xmlResponse->createElementNS( ICASServer::CAS_NS, "cas:$key", $value );
                $xmlAttributes->appendChild( $xmlAttribute );
            }

            $response->appendChild( $xmlAttributes );
        }

        // Include Proxy-Granting Ticket in successful `/proxyValidate` responses:
        
        if ($proxyGrantingTicket) {
            $response->appendChild( $this->xmlResponse->createElementNS( ICASServer::CAS_NS,
                "cas:proxyGrantingTicket", $proxyGrantingTicket ) );
        }

        // Include proxies in successful `/proxyValidate` responses:
        
        if (count( $proxies ) > 0) {
            $xmlProxies = $this->xmlResponse->createElementNS( ICASServer::CAS_NS, "cas:proxies" );

            foreach ($proxies as $proxy) {
                $xmlProxies->appendChild( $this->xmlResponse->createElementNS( ICASServer::CAS_NS,
                    "cas:proxy", $proxy ) );
            }

            $response->appendChild( $xmlProxies );
        }

        return $response;
    }

    /**
     * XML success response to a CAS 2.0 proxy request.
     * 
     * @param  WP_User    $user    Authenticated WordPress user.
     * @param  string     $service Service URI.
     * 
     * @return DOMElement          CAS success response XML fragment.
     */
    protected function _xmlProxySuccess ( $user, $service = '' ) {

        $expiration = WPCASServerPlugin::get_option( 'expiration', 30 );

        $proxy_ticket = $this->_createTicket( $user, $service, ICASServer::TYPE_PT, $expiration );

        $response = $this->xmlResponse->createElementNS( ICASServer::CAS_NS,
            "cas:proxySuccess" );

        $response->appendChild( $this->xmlResponse->createElementNS( ICASServer::CAS_NS,
            "cas:proxyTicket", $proxy_ticket ) );

        return $response;
    }

    /**
     * XML error response to a CAS 2.0 request.
     * 
     * @param  WP_Error   $error Error object.
     * 
     * @return DOMElement        CAS error response XML fragment.
     * 
     * @uses do_action()
     */
    protected function _xmlError ( $error ) {

        /**
         * Fires if the CAS server has to return an XML error.
         * 
         * @param WP_Error $error WordPress error to return as XML.
         */
        do_action( 'cas_server_error', $error );

        foreach (array( 'authenticationFailure', 'proxyFailure' ) as $slug) {
            if ($error->errors[$slug]) {
                $element = $this->xmlResponse->createElementNS( ICASServer::CAS_NS,
                    "cas:$slug", implode( "\n", $error->errors[$slug] ) );
                $element->setAttribute( "code", $error->error_data[$slug]['code'] );
                return $this->_xmlResponse( $element );
            }
        }

        $response = $this->xmlResponse->createElementNS( ICASServer::CAS_NS,
            "cas:authenticationFailure", __( 'Unknown error', 'wordpress-cas-server' ) );

        $response->setAttribute( "code", ICASServer::ERROR_INTERNAL_ERROR );

        return $this->_xmlResponse( $response );
    }

    /**
     * Generate a new security ticket for the CAS service.
     * 
     * @param  WP_USER $user        WordPress user to authenticate.
     * @param  string  $service     Service URI.
     * @param  string  $type        Ticket type (default TYPE_ST).
     * @param  int     $expiration  Ticket expiration time in seconds (default 30). The CAS
     *                              protocol specification recommends that a ticket should
     *                              not be valid for longer than 5 minutes.
     * 
     * @return string               Generated ticket.
     * 
     * @uses apply_filters()
     * @uses is_ssl()
     * @uses set_transient()
     * @uses wp_hash()
     */
    protected function _createTicket( $user, $service = '', $type = ICASServer::TYPE_ST, $expiration = 30 ) {
        /**
         * This filter allows developers to override the default ticket expiration period.
         * 
         * @param  int     $expiration Ticket expiration period (in seconds).
         * @param  string  $type       Type of ticket to set.
         * @param  WP_User $user       Authenticated user associated with the ticket.
         * 
         * @return int                 Filtered ticket expiration period (in seconds).
         */
        $expiration = apply_filters( 'cas_server_ticket_expiration', $expiration, $type, $user );
        $expires    = microtime( true ) + $expiration;

        $key        = wp_hash( $user->user_login . '|' . substr($user->user_pass, 8, 4) . '|' . $expires );
        $hash       = hash_hmac( 'sha1', $user->user_login . '|' . $service . '|' . $expires, $key );
        $ticket     = $user->user_login . '|' . $service . '|' . $expires . '|' . $hash;

        set_transient( WPCASServerPlugin::TRANSIENT_PREFIX . $key, $ticket, 5 * 60 );

        return $type . '-' . urlencode( base64_encode( $ticket ) );
    }

    /**
     * Generates an authentication error.
     * 
     * @param  string   $slug    Error slug.
     * @param  string   $message Error message to pass.
     * @param  string   $code    CAS error code.
     * 
     * @return WP_Error          WordPress error.
     */
    protected function _validateError( $slug, $message, $code = ICASServer::ERROR_INVALID_TICKET ) {

        $error = new WP_Error( $slug, $message, array( 'code' => $code ) );

        /**
         * Fires on an invalid ticket.
         * 
         * @param WP_Error $error   Validation error for the provided ticket.
         */
        do_action( 'cas_server_validation_error', $error );

        return $error;
    }

    /**
     * Validates a ticket and returns its associated user.
     * 
     * @param  string               $ticket             Service or proxy ticket.
     * @param  string               $service            Service URI.
     * @param  array                $valid_ticket_types Ticket must be of the specified types.
     * 
     * @return (WP_User|WP_Error)                       Authenticated WordPress user or error.
     * 
     * @uses delete_transient()
     * @uses do_action()
     * @uses get_transient()
     * @uses get_user_by()
     * @uses wp_hash()
     * @uses WP_Error
     */
    protected function _validateTicket ( $ticket, $service = '', $valid_ticket_types = array() ) {

        if (in_array( ICASServer::TYPE_PGT, $valid_ticket_types )) {
            $error_slug = 'proxyFailure';
            $error_code = ICASServer::ERROR_BAD_PGT;
        }
        else
        {
            $error_slug = 'authenticationFailure';
            $error_code = ICASServer::ERROR_INVALID_TICKET;
        }

        if (empty( $service )) {
            return $this->_validateError( $error_slug,
                __( 'Service is required.', 'wordpress-cas-server' ),
                ICASServer::ERROR_INVALID_REQUEST );
        }

        if (empty( $ticket )) {
            return $this->_validateError( $error_slug,
                __( 'Ticket is required.', 'wordpress-cas-server' ),
                ICASServer::ERROR_INVALID_REQUEST );
        }

        if (strpos( $ticket, '-' ) === false) {
            $ticket = '-' . $ticket;
        }

        list( $ticket_type, $ticket_content ) = explode( '-', $ticket, 2 );

        $ticket_elements = explode( '|', base64_decode( $ticket_content ) );

        if ($ticket_type && !in_array( $ticket_type, $valid_ticket_types )) {
            return $this->_validateError( $error_slug,
                __( 'Ticket type cannot be validated.', 'wordpress-cas-server' ),
                $error_code );
        }

        if (count( $ticket_elements ) < 4) {
            return $this->_validateError( $error_slug,
                __( 'Ticket is malformed.', 'wordpress-cas-server' ),
                $error_code );
        }

        list( $user_login, $ticket_service, $expires, $ticket_hash ) = $ticket_elements;

        if ( $ticket_service !== $service ) {
            return $this->_validateError( $error_slug,
                __( 'Ticket does not match service.', 'wordpress-cas-server' ),
                $error_code );
        }

        if ( $expires < time() ) {
            return $this->_validateError( $error_slug,
                __( 'Ticket has expired.', 'wordpress-cas-server' ),
                $error_code );
        }

        $user = get_user_by( 'login', $user_login );

        if ( !$user ) {
            return $this->_validateError( $error_slug,
                __( 'Ticket does not match user.', 'wordpress-cas-server' ),
                $error_code );
        }

        $key  = wp_hash( $user->user_login . '|' . substr( $user->user_pass, 8, 4 ) . '|' . $expires );
        $hash = hash_hmac( 'sha1', $user->user_login . '|' . $service . '|' . $expires, $key );

        if ($ticket_hash !== $hash) {
            return $this->_validateError( $error_slug,
                __( 'Ticket is corrupted.', 'wordpress-cas-server' ), $ticket, $service );
        }

        if (WPCASServerPlugin::get_option( 'allow_ticket_reuse' ) == false && !get_transient( WPCASServerPlugin::TRANSIENT_PREFIX . $key )) {
            return $this->_validateError( $error_slug,
                __( 'Ticket is not recognized.', 'wordpress-cas-server' ),
                $error_code );
        }

        delete_transient( WPCASServerPlugin::TRANSIENT_PREFIX . $key );

        /**
         * Fires on an valid ticket.
         * 
         * @param WP_User $user   WordPress user validated by ticket.
         * @param string  $ticket Valid ticket string.
         */
        do_action( 'cas_server_validation_success', $user, $ticket );

        return $user;
    }

    /**
     * Login User
     * @param  WP_User $user    WordPress user to authenticate.
     * @param  string  $service URI for the service requesting user authentication.
     * 
     * @uses add_query_arg()
     * @uses apply_filters()
     * @uses home_url()
     */
    protected function _loginUser ( $user, $service ) {

        $expiration = WPCASServerPlugin::get_option( 'expiration', 30 );

        $ticket = $this->_createTicket( $user, $service, ICASServer::TYPE_ST, $expiration );

        if ($service) {
            $service = add_query_arg( 'ticket', $ticket, $service );

            /**
             * Filters the redirect URI for the service requesting user authentication.
             * 
             * @param  string  $service Service URI requesting user authentication.
             * @param  WP_User $user    Logged in WordPress user.
             * 
             * @return string           Filtered service URI requesting user authentication.
             */
            $service = apply_filters( 'cas_server_redirect_service', $service, $user );

            $this->_redirect( $service );
        }

        $this->_redirect( home_url() );
    }

    /**
     * Redirects the user to either the standard WordPress authentication page or a custom one
     * at a URI returned by the `cas_server_custom_auth_uri` filter.
     * 
     * @param array $args HTTP request parameters received by `/login`.
     * 
     * @uses apply_filters()
     * @uses auth_redirect()
     */
    protected function _authRedirect ( $args = array() ) {
        /**
         * Allows developers to redirect the user to a custom login form.
         * 
         * @param string $custom_login_url URI for the custom login page.
         * @param array  $args             Login request parameters.
         */
        $custom_login_url = apply_filters( 'cas_server_custom_auth_uri', false, $args );

        if ($custom_login_url) {
            $this->_redirect( $custom_login_url );
        }

        auth_redirect();
        exit;
    }

    //
    // CAS Server Protocol Methods
    //

    /**
     * Implements the `/login` URI and determines whether to interpret the request as a credential
     * requestor or a credential acceptor.
     * 
     * @param  array $args Request arguments.
     */
    public function login ( $args ) {

        $args = array_merge( $_POST, $args );
        $args = apply_filters( 'cas_server_login_args', $args );

        if (isset( $args['username'] ) && isset( $args['password'] ) && isset( $args['lt'] )) {
            $this->_loginAcceptor( $args );
        }
        else
        {
            $this->_loginRequestor( $args );
        }
    }

    /**
     * Implements the `/login` URI behaviour as credential acceptor when a set of accepted
     * credentials are passed to `/login` via POST.
     * 
     * This plugin does not implement a form to take advantage of this request behaviour, and relies
     * on WordPress' own authentication interfaces. Developers may implement custom forms so long as
     * they send the request parameters described below.
     * 
     * The following HTTP request parameters MUST be passed to `/login` while it is acting as a
     * credential acceptor for username/password authentication. They are all case-sensitive.
     * 
     * - `username`: The username of the client that is trying to log in.
     * - `password`: The password of the client that is trying to log in.
     * - `lt`: A login ticket. It acts as a nonce to prevent replaying requests.
     * 
     * The following HTTP request parameters are optional:
     * 
     * - `service`: The URL of the application the client is trying to access. CAS will redirect the
     *   client to this URL upon successful authentication.
     * - `warn`: If this parameter is set, single sign-on will NOT be transparent. The client will
     *   be prompted before being authenticated to another service.
     * 
     * @param array $args Request arguments.
     * 
     * @uses sanitize_user()
     * @uses esc_url_raw()
     * @uses wp_signon()
     * @uses wp_verify_nonce()
     * 
     * @todo Support for the optional "warn" parameter.
     */
    protected function _loginAcceptor ( $args ) {

        $username   = sanitize_user( $args['username'] );
        $password   = $args['password'];
        $lt         = preg_replace( '@^' . ICASServer::TYPE_LT . '-@', '', $args['lt'] );
        $service    = isset( $args['service'] ) ? esc_url_raw( $args['service'] ) : null;
        $warn       = isset( $args['warn'] ) && 'true' === $args['warn'];

        // TODO: Support for the optional "warn" parameter.

        if (!wp_verify_nonce( $lt, 'lt' )) {
            $this->_authRedirect( $args );
        }

        $user = wp_signon( array(
            'user_login'    => $username,
            'user_password' => $password,
            ) );

        if (!$user || is_wp_error( $user )) {
            $this->_authRedirect( $args );
        }

        $this->_loginUser( $user, $service );
    }

    /**
     * Implements the `/login` URI as credential requestor.
     * 
     * If the client has already established a single sign-on session with CAS, the
     * client will have presented its HTTP session cookie to `/login` unless the "renew"
     * parameter is set to "true".
     * 
     * If there is no session or the "renew" parameter is set, CAS will respond by
     * displaying a login screen requesting (usually) a username and password.
     * 
     * @param array $args Request arguments.
     * 
     * @uses esc_url_raw()
     * @uses is_user_logged_in()
     * @uses remove_query_arg()
     * @uses wp_get_current_user()
     * @uses wp_logout()
     */
    protected function _loginRequestor ( $args ) {
        global $userdata, $user_ID;

        $this->_sessionStart();

        $renew   = isset( $args['renew'] )   && 'true' === $args['renew'];
        $gateway = isset( $args['gateway'] ) && 'true' === $args['gateway'];
        $service = isset( $args['service'] ) ? esc_url_raw( $args['service'] ) : '';

        if ($renew) {
            if (!headers_sent()) {
                wp_logout();
            }

            $url = (is_ssl() ? 'https://' : 'http://')
                 . $_SERVER['HTTP_HOST']
                 . $_SERVER['REQUEST_URI'];
            $url = remove_query_arg( 'renew', $url );

            $this->_redirect( $url );
        }

        if (!is_user_logged_in()) {
            if ($gateway && $service) {
                $this->_redirect( $service );
            }

            $this->_authRedirect( $args );
        }

        $this->_loginUser( wp_get_current_user(), $service );
    }

    /**
     * `/logout` destroys a client's single sign-on CAS session.
     * 
     * When called, this method will destroy the ticket-granting cookie and prevent subsequent
     * requests to `/login` from returning service tickets until the user re-authenticates using
     * their primary credentials.
     * 
     * The following HTTP request parameter may be specified:
     * 
     * - `url` (optional): If specified, the server will redirect the user to the page located at
     *   `url`, which should contain a description telling the user they've been logged out.
     * 
     * @param array $args Request arguments.
     * 
     * @uses esc_url_raw()
     * @uses home_url()
     * @uses wp_logout()
     */
    public function logout ( $args ) {
        $service = esc_url_raw( $args['service'] );
        $this->_sessionStart();
        session_unset();
        session_destroy();
        wp_logout();

        $this->_redirect( $service ? $service : home_url() );
    }

    /**
     * `/proxy` provides proxy tickets to services that have acquired proxy-granting tickets and
     * will be proxying authentication to back-end services.
     * 
     * The following HTTP request parameters must be provided:
     * 
     * - `pgt` (required): The proxy-granting ticket (PGT) acquired by the service during
     *   service ticket (ST) or proxy ticket (PT) validation.
     * - `targetService` (required): The service identifier of the back-end service. Note that not
     *   all back-end services are web services so this service identifier will not always be a URL.
     *   However, the service identifier specified here must match the "service" parameter specified
     *   to `/proxyValidate` upon validation of the proxy ticket.
     * 
     * @param  array $args Request arguments.
     * 
     * @return mixed       Successful response XML as string or WP_Error.
     */
    public function proxy ( $args ) {

        $pgt            = $args['pgt'];
        $targetService  = $args['targetService'];

        /**
         * `/proxy` checks the validity of the proxy-granting ticket passed.
         */
        $valid_ticket_types = array(
            ICASServer::TYPE_PGT,
        );

        $user = $this->_validateTicket( $pgt, $targetService, $valid_ticket_types );

        if (is_wp_error( $user )) {
            return $user;
        }

        return $this->_xmlResponse( $this->_xmlProxySuccess( $user, $targetService ) );
    }

    /**
     * `/proxyValidate` must perform the same validation tasks as `/serviceValidate` and
     * additionally validate proxy tickets. `/proxyValidate` must be capable of validating both
     * service tickets and proxy tickets.
     * 
     * @param  array $args Request arguments.
     * 
     * @return mixed       Successful response XML as string or WP_Error.
     * 
     * @uses is_wp_error()
     * @uses WP_Error
     * 
     * @todo Accept proxy callback URL (pgtUrl) parameter.
     * @todo Accept renew parameter.
     */
    public function proxyValidate ( $args ) {

        $service = $args['service'];
        $ticket  = $args['ticket'];
        $pgtUrl  = isset( $args['pgtUrl'] ) ? $args['pgtUrl'] : ''; // TODO
        $renew   = isset( $args['renew'] )  ? $args['renew']  : ''; // TODO

        /**
         * `/proxyValidate` checks the validity of both service and proxy tickets.
         */
        $valid_ticket_types = array(
            ICASServer::TYPE_ST,
            ICASServer::TYPE_PT,
        );

        $user = $this->_validateTicket( $ticket, $service, $valid_ticket_types );

        if (is_wp_error( $user )) {
            return $user;
        }

        return $this->_xmlResponse( $this->_xmlValidateSuccess( $user ) );
    }

    /**
     * `/serviceValidate` is a CAS 2.0 protocol method that checks the validity of a service ticket
     * (ST) and returns an XML response.
     * 
     * `/serviceValidate` will also generate and issue proxy-granting tickets (PGT) when requested.
     * The service will deny authenticating  user if it receives a proxy ticket.
     * 
     * @param  array $args Request arguments.
     * 
     * @return mixed       Successful response XML as string or WP_Error.
     * 
     * @uses is_wp_error()
     * @uses WP_Error
     * 
     * @todo Accept proxy callback URL (pgtUrl) parameter.
     * @todo Accept renew parameter.
     */
    public function serviceValidate ( $args ) {
        
        $service = $args['service'];
        $ticket  = $args['ticket'];
        $pgtUrl  = isset( $args['pgtUrl'] ) ? $args['pgtUrl']  : ''; // TODO
        $renew   = isset( $args['renew'] )  ? $args['renew']   : ''; // TODO

        /**
         * `/serviceValidate` checks the validity of a service ticket and does not handle proxy
         * authentication. CAS MUST respond with a ticket validation failure response when a proxy
         * ticket is passed to `/serviceValidate`.
         */
        $valid_ticket_types = array(
            ICASServer::TYPE_ST,
        );

        $user = $this->_validateTicket( $ticket, $service, $valid_ticket_types );

        if (is_wp_error( $user )) {
            return $user;
        }

        return $this->_xmlResponse( $this->_xmlValidateSuccess( $user ) );
    }

    /**
     * `/validate` is a CAS 1.0 protocol method that checks the validity of a service ticket.
     * 
     * Being part of the CAS 1.0 protocol, `/validate` does not handle proxy authentication.
     * 
     * The following HTTP request parameters may be specified:
     * 
     * - `service` (required): The URL of the service for which the ticket was issued.
     * - `ticket` (required): The service ticket issued by `/login`.
     * - `renew` (optional): If this parameter is set, the server will force the user to reenter
     *   their primary credentials.
     * 
     * `/validate` will return one of the following two responses:
     *
     * On successful validation:
     *  
     * ```
     * yes\n
     * username\n
     * ```
     * 
     * On validation failure:
     * 
     * ```
     * no\n
     * \n
     * ```
     * 
     * This method will attempt to set a `Content-Type: text/plain` HTTP header when called.
     * 
     * @param  array  $args Request arguments.
     * 
     * @return string       Validation response.
     * 
     * @uses get_userdata()
     * @uses is_wp_error()
     */
    public function validate ( $args ) {

        $this->_setResponseHeader( 'Content-Type', 'text/plain; charset=' . get_option( 'blog_charset' ) );

        $service = isset( $args['service'] ) ? $args['service'] : '';
        $ticket  = isset( $args['ticket'] )  ? $args['ticket']  : '';

        /**
         * `/validate` checks the validity of a service ticket. `/validate` is part of the CAS 1.0
         * protocol and thus does not handle proxy authentication. CAS MUST respond with a ticket
         * validation failure response when a proxy ticket is passed to `/validate`.
         */
        $valid_ticket_types = array(
            ICASServer::TYPE_ST,
        );

        $user = $this->_validateTicket( $ticket, $service, $valid_ticket_types );

        if ($user && !is_wp_error( $user )) {
            return "yes\n" . $user->get( 'user_login' ) . "\n";
        }

        return "no\n\n";
    }

}

endif;
