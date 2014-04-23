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
     * Ticket validation error.
     * @var string
     */
    protected $ticketValidationError;

    /**
     * WordPress CAS Server plugin options.
     * @var array
     */
    protected $options;

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
     * Handle a CAS server request for a specific URI.
     * 
     * This method will attempt to set the following HTTP headers to prevent browser caching:
     * 
     * - `Pragma: no-cache`
     * - `Cache-Control: no-store`
     * - `Expires: <time of request>`
     * 
     * @param  string $path CAS request URI.
     * @return string       Request response.
     * 
     * @uses apply_filters()
     * @uses do_action()
     * @uses is_wp_error()
     */
    public function handleRequest ( $path, $options = array() ) {

        $this->options = $options;

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

            /**
             * Filters the callback to be dispatched for the request.
             * 
             * @param (string|array) $callback Callback function or method.
             * @param string         $path     Requested URI path.
             */
            $callback = apply_filters( 'cas_server_dispatch_callback', $callback, $path );

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
     * XML success response to a CAS 2.0 authentication request.
     * 
     * @param  WP_User    $user                 Authenticated WordPress user.
     * @param  string     $proxyGratingTicket   Generated Proxy-Granting Ticket (PGT) to return.
     * @param  array      $proxies              List of proxy URIs.
     * 
     * @return DOMElement       CAS success response XML fragment.
     * 
     * @uses apply_filters()
     * @uses get_userdata()
     */
    protected function _xmlSuccess ( $user, $proxyGrantingTicket = '', $proxies = array() ) {

        $response = $this->xmlResponse->createElementNS( ICASServer::CAS_NS,
            "cas:authenticationSuccess" );

        // Include login name:
        
        $response->appendChild( $this->xmlResponse->createElementNS( ICASServer::CAS_NS,
            "cas:user", $user->get( 'user_login' ) ) );

        // CAS attributes:
        
        if (is_array( $this->options['attributes'] ) && !empty( $this->options['attributes'] )) {

            $attributes = array();

            foreach ($this->options['attributes'] as $key) {
                $attributes[$key] = $user->get( $key );
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
            $attributes = apply_filters( 'cas_server_validation_extra_attributes', $attributes, $user );

            $xmlAttributes = $this->xmlResponse->createElementNS( ICASServer::CAS_NS, "cas:attributes" );

            foreach ($attributes as $key => $value) {
                $xmlAttribute = $this->xmlResponse->createElementNS( ICASServer::CAS_NS, "cas:$key", $value );
                $xmlAttributes->appendChild( $xmlAttribute );
            }

            $response->appendChild( $xmlAttributes );
        }

        // Include Proxy-Granting Ticket in successful `/proxyValidate` responses:
        
        if (!empty( $proxyGrantingTicket )) {
            $response->appendChild( $this->xmlResponse->createElementNS( ICASServer::CAS_NS,
                "cas:proxyGrantingTicket", $proxyGrantingTicket ) );
        }

        // Include proxies in successful `/proxyValidate` responses:
        
        if (!empty( $proxies )) {
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
     * XML error response to a CAS 2.0 request.
     * 
     * @param  WP_Error   $wp_error Error object.
     * @return DOMElement           CAS error response XML fragment.
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

        foreach (array( 'authenticationFailure', 'proxyFailure' ) as $type) {
            if (!empty( $error->errors[$type] )) {
                $element = $this->xmlResponse->createElementNS( ICASServer::CAS_NS,
                    "cas:$type", implode( "\n", $error->errors[$type] ) );
                $element->setAttribute( "code", $error->error_data[$type]['code'] );
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
     * @param  string  $type        Ticket type (default TYPE_ST).
     * @param  int     $expiration  Ticket expiration time in seconds (default 15). The CAS
     *                              specification recommends that the duration a ticket is valid be
     *                              no longer than 5 minutes.
     * @return string               Generated ticket.
     * 
     * @uses apply_filters()
     * @uses wp_generate_auth_cookie()
     */
    protected function _createTicket( $user, $type = ICASServer::TYPE_ST, $expiration = 15 ) {
        /**
         * This filters allows developers to override the default ticket expiration period.
         * 
         * @param  int     $expiration Ticket expiration period (in seconds).
         * @param  string  $type       Type of ticket to set.
         * @param  WP_User $user       Authenticated user associated with the ticket.
         * 
         * @return int                 Filtered ticket expiration period (in seconds).
         */
        $expiration = apply_filters( 'cas_server_ticket_expiration', $expiration, $type, $user );

        return $type . '-' . urlencode( base64_encode( wp_generate_auth_cookie( $user->ID, time() + $expiration, 'auth' ) ) );
    }

    /**
     * Login User
     * @param  WP_User $user    WordPress user to authenticate.
     * @param  string  $service URI for the service requesting user authentication.
     * 
     * @uses add_query_arg()
     * @uses apply_filters()
     * @uses get_option()
     * @uses wp_redirect()
     */
    protected function _loginUser ( $user, $service ) {
        $ticket = $this->_createTicket( $user, ICASServer::TYPE_ST, 60 );

        if (!empty( $service )) {
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

            wp_redirect( $service );
            exit;
        }

        wp_redirect( get_option( 'home' ) );
        exit;
    }

    /**
     * Redirects the user to either the standard WordPress authentication page or a custom one
     * at a URI returned by the `cas_server_custom_auth_uri` filter.
     * 
     * @uses apply_filters()
     * @uses auth_redirect()
     * @uses wp_redirect()
     */
    protected function _loginAuthRedirect () {
        /**
         * Allows developers to redirect the user to a custom login form.
         * 
         * @param string $custom_login_url URI for the custom login page.
         * @param array  $args             Login request parameters.
         */
        $custom_login_url = apply_filters( 'cas_server_custom_auth_uri', false, $args );

        if ($custom_login_url) {
            wp_redirect( $custom_login_url );
        } else {
            auth_redirect();                    
        }
        exit;
    }

    /**
     * Sets a ticket validation error message when an authentication cookie is malformed.
     * 
     * @param  string $cookie Malformed auth cookie.
     * @param  string $scheme Authentication scheme. Values include 'auth', 'secure_auth',
     *                        or 'logged_in'.
     */
    public function auth_cookie_malformed ( $cookie, $scheme ) {
        $this->ticketValidationError = __( 'Ticket is malformed.', 'wordpress-cas-server' );
    }

    /**
     * Sets a ticket validation error message once an authentication cookie has expired.
     * 
     * @param array $cookie_elements An array of data for the authentication cookie.
     */
    public function auth_cookie_expired ( $cookie_elements ) {
        $this->ticketValidationError = __( 'Ticket has expired.', 'wordpress-cas-server' );
    }

    /**
     * Sets a ticket validation error message if a bad username is entered in the user authentication process.
     * 
     * @param array $cookie_elements An array of data for the authentication cookie.
     */
    public function auth_cookie_bad_username ( $cookie_elements ) {
        $this->ticketValidationError = __( 'Invalid user for ticket.', 'wordpress-cas-server' );
    }

    /**
     * Sets a ticket validation error message if a bad authentication cookie hash is encountered.
     * 
     * @param array $cookie_elements An array of data for the authentication cookie.
     */
    public function auth_cookie_bad_hash ( $cookie_elements ) {
        $this->ticketValidationError = __( 'Ticket hash is invalid.', 'wordpress-cas-server' );
    }

    /**
     * Validates a ticket and returns its associated user.
     * 
     * @param  string               $ticket             Service or proxy ticket.
     * @param  array                $valid_ticket_types Ticket must be of the specified types.
     * @return (WP_User|WP_Error)                       Authenticated WordPress user or error.
     * 
     * @uses add_action()
     * @uses do_action()
     * @uses get_user_by()
     * @uses remove_action()
     * @uses wp_validate_auth_cookie()
     * @uses WP_Error
     */
    protected function _validateTicket ( $ticket, $valid_ticket_types = array() ) {

        $this->ticketValidationError = '';

        list( $ticket_type, $ticket_content ) = explode( '-', $ticket, 2 );

        if (!in_array( $ticket_type, $valid_ticket_types )) {
            $this->ticketValidationError = __( 'Ticket type is incorrect.', 'wordpress-cas-server' );
        }

        if (empty( $ticket )) {
            $this->ticketValidationError = __( 'Invalid ticket.', 'wordpress-cas-server' );
        }

        $user = false;

        add_action( 'auth_cookie_malformed'     , array( $this, 'auth_cookie_malformed' )    , 10, 2 );
        add_action( 'auth_cookie_expired'       , array( $this, 'auth_cookie_expired' )      , 10, 1 );
        add_action( 'auth_cookie_bad_username'  , array( $this, 'auth_cookie_bad_username' ) , 10, 1 );
        add_action( 'auth_cookie_bad_hash'      , array( $this, 'auth_cookie_bad_hash')      , 10, 1 );

        if ($user_id = wp_validate_auth_cookie( base64_decode( $ticket_content ), 'auth' )) {
            $user = get_user_by( 'id', $user_id );
        }

        remove_action( 'auth_cookie_malformed'     , array( $this, 'auth_cookie_malformed') );
        remove_action( 'auth_cookie_expired'       , array( $this, 'auth_cookie_expired') );
        remove_action( 'auth_cookie_bad_username'  , array( $this, 'auth_cookie_bad_username') );
        remove_action( 'auth_cookie_bad_hash'      , array( $this, 'auth_cookie_bad_hash') );

        if ($user && empty( $this->ticketValidationError )) {
            /**
             * Fires on an valid ticket.
             * 
             * @param WP_User $user   WordPress user validated by ticket.
             * @param string  $ticket Valid ticket string.
             */
            do_action( 'cas_server_valid_ticket', $user, $ticket );

            return $user;
        }

        $error = new WP_Error( 'authenticationFailure',
            $this->ticketValidationError,
            array( 'code' => ICASServer::ERROR_INVALID_TICKET )
            );

        /**
         * Fires on an invalid ticket.
         * 
         * @param WP_Error $error  Validation error for the provided ticket.
         * @param string   $ticket Invalid ticket string.
         */
        do_action( 'cas_server_invalid_ticket', $error, $ticket );

        return $error;
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
     * - `lt`: A login ticket. It acts as a nonce to prevent replaying requests and must be
     *   generated using `wp_create_nonce( 'lt' )`.
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
     * @todo What happens if the nonce check fails?
     * @todo What happens if the user login fails?
     */
    protected function _loginAcceptor ( $args ) {

        $username   = sanitize_user( $args['username'] );
        $password   = $args['password'];
        $lt         = preg_replace( '@^' . ICASServer::TYPE_LT . '-@', '', $args['lt'] );
        $service    = isset( $args['service'] ) ? esc_url_raw( $args['service'] ) : null;
        $warn       = isset( $args['warn'] ) && 'true' === $args['warn'];

        // TODO: Support for the optional "warn" parameter.

        if (!wp_verify_nonce( $lt, 'lt' )) {

            // TODO: What do I do if the nonce verification fails?
            
            $this->_loginAuthRedirect();
        }

        $user = wp_signon( array(
            'user_login'    => $username,
            'user_password' => $password,
            ) );

        if (!$user) {

            // TODO: What do I do if signon fails?
            
            $this->_loginAuthRedirect();
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
     * @uses wp_redirect()
     */
    protected function _loginRequestor ( $args ) {

        $renew   = isset( $args['renew'] )   && 'true' === $args['renew'];
        $gateway = isset( $args['gateway'] ) && 'true' === $args['gateway'];
        $service = isset( $args['service'] ) ? esc_url_raw( $args['service'] ) : null;

        if ($renew) {
            wp_logout();

            $url = '//' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            $url = remove_query_arg( 'renew', $url );

            wp_redirect( $url );
            exit;
        }

        if (!is_user_logged_in()) {
            if ($gateway && !empty( $service )) {
                wp_redirect( $service );
                exit;
            }
            else
            {
                $this->_loginAuthRedirect();
            }
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
     * @uses get_option()
     * @uses wp_logout()
     * @uses wp_redirect()
     */
    public function logout ( $args ) {
        $service = esc_url_raw( $args['service'] );
        session_start();
        session_unset();
        session_destroy();
        wp_logout();
        wp_redirect( !empty( $service ) ? $service : get_option( 'home' ) );
        exit;
    }

    /**
     * [proxy description]
     * 
     * @param  array $args Request arguments.
     * @return [type]      [description]
     */
    public function proxy ( $args ) {
        // TODO
    }

    /**
     * [proxyValidate description]
     * 
     * @param  array $args Request arguments.
     * @return [type]      [description]
     */
    public function proxyValidate ( $args ) {
        // TODO
    }

    /**
     * `/serviceValidate` is a CAS 2.0 protocol method that checks the validity of a service ticket
     * (ST) and returns an XML response.
     * 
     * `/serviceValidate` will also generate and issue proxy-granting tickets (PGT) when requested.
     * The service will deny authenticating  user if it receives a proxy ticket.
     * 
     * @param  array  $args Request arguments.
     * 
     * @return mixed        Successful response XML as string or WP_Error.
     * 
     * @uses is_wp_error()
     * @uses WP_Error
     * 
     * @todo Accept proxy callback URL (pgtUrl) parameter.
     * @todo Accept renew parameter.
     */
    public function serviceValidate ( $args ) {
        
        if (empty( $args['service'] )) {
            return new WP_Error( 'authenticationFailure',
                __( 'Request must include a service ticket.', 'wordpress-cas-server' ),
                array( 'code' => ICASServer::ERROR_INVALID_REQUEST )
                );
        }

        if (empty( $args['ticket'] )) {
            return new WP_Error( 'authenticationFailure',
                __( 'Request must include a service ticket.', 'wordpress-cas-server' ),
                array( 'code' => ICASServer::ERROR_INVALID_REQUEST )
                );
        }

        $service = $args['service'];
        $ticket  = $args['ticket'];
        $pgtUrl  = (!empty( $args['pgtUrl'] ))  ? $args['pgtUrl']  : ''; // TODO
        $renew   = (!empty( $args['renew'] ))   ? $args['renew']   : ''; // TODO

        /**
         * `/serviceValidate` checks the validity of a service ticket and does not handle proxy
         * authentication. CAS MUST respond with a ticket validation failure response when a proxy
         * ticket is passed to `/serviceValidate`.
         */
        $valid_ticket_types = array(
            ICASServer::TYPE_ST,
        );

        $user = $this->_validateTicket( $ticket, $valid_ticket_types );

        if (is_wp_error( $user )) {
            return $user;
        }

        return $this->_xmlResponse( $this->_xmlSuccess( $user ) );
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

        /**
         * `service` is required.
         */
        if (empty( $args['service'] )) {
            return "no\n\n";
        }

        $ticket = isset( $args['ticket'] ) ? $args['ticket'] : '';

        /**
         * `/validate` checks the validity of a service ticket. `/validate` is part of the CAS 1.0
         * protocol and thus does not handle proxy authentication. CAS MUST respond with a ticket
         * validation failure response when a proxy ticket is passed to `/validate`.
         */
        $valid_ticket_types = array(
            ICASServer::TYPE_ST,
        );

        $user = $this->_validateTicket( $ticket, $valid_ticket_types );

        if ($user && !is_wp_error( $user )) {
            return "yes\n" . $user->get( 'user_login' ) . "\n";
        }

        return "no\n\n";
    }

}

endif;
