<?php
/**
 * Implements the ICASServer interface as required by the WP CAS Server plugin.
 * 
 * @package \WPCASServerPlugin\Server
 * @version 1.1.0
 */

if (!defined( 'ABSPATH' )) exit; // No monkey business.

require_once( dirname( __FILE__ ) . '/WPCASException.php' );
require_once( dirname( __FILE__ ) . '/ICASServer.php');
require_once( dirname( __FILE__ ) . '/WPCASTicket.php');


if (!class_exists( 'WPCASServer' )) {

    /**
     * Class providing all public CAS methods.
     * 
     * @since 1.0.0
     */
    class WPCASServer implements ICASServer {

        /**
         * XML response.
         * @var DOMDocument
         */
        protected $xmlResponse;

        /**
         * WP CAS Server constructor.
         * 
         * @uses get_bloginfo()
         */
        public function __construct () {
            $this->xmlResponse = new DOMDocument( '1.0', get_bloginfo( 'charset' ) );
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

            $casRoutes = array(
                'login'           => array( $this, 'login' ),
                'logout'          => array( $this, 'logout' ),
                'proxy'           => array( $this, 'proxy' ),
                'proxyValidate'   => array( $this, 'proxyValidate' ),
                'serviceValidate' => array( $this, 'serviceValidate' ),
                'validate'        => array( $this, 'validate' ),
                );

            /**
             * Allows developers to override the default callback
             * mapping, define additional endpoints and provide
             * alternative implementations to the provided methods.
             * 
             * @param array $cas_routes CAS endpoint to callback mapping.
             */
            return apply_filters( 'cas_server_routes', $casRoutes );
        }

        /**
         * Perform an HTTP redirect.
         * 
         * If the 'allowed_services' contains at least one host, it will always perform a safe
         * redirect.
         * 
         * Calling WPCASServer::_redirect() will _always_ terminate the request.
         * 
         * @param  string  $location URI to redirect to.
         * @param  integer $status   HTTP status code (default 302).
         * 
         * @uses wp_redirect()
         * @uses wp_safe_redirect()
         */
        protected function redirect ( $location, $status = 302 ) {

            $allowedServices = WPCASServerPlugin::getOption( 'allowed_services' );

            if (is_array( $allowedServices ) && count( $allowedServices ) > 0) {
                wp_safe_redirect( $location, $status );
            }

            wp_redirect( $location, $status );
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
         * @throws WPCASException
         * 
         * @global $_SERVER
         * 
         * @uses apply_filters()
         * @uses do_action()
         * @uses is_wp_error()
         */
        public function handleRequest ( $path ) {

            if (!defined( 'CAS_REQUEST' )) define( 'CAS_REQUEST', true );

            $this->setResponseHeader( 'Pragma'         , 'no-cache' );
            $this->setResponseHeader( 'Cache-Control'  , 'no-store' );
            $this->setResponseHeader( 'Expires'        , gmdate( ICASServer::RFC1123_DATE_FORMAT ) );

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

            try {
                $output = $this->dispatch( $path );                
            }
            catch (WPCASException $exception) {
                $output = $this->xmlError( $exception->getErrorInstance() );
            }
            
            /**
             * Fires after the CAS request is processed.
             * 
             * @param string $path Requested URI path.
             */
            do_action( 'cas_server_after_request', $path );

            /**
             * Lets developers change the CAS server response string.
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
         * @param  string $path Requested URI path.
         * 
         * @return mixed        Service response string or WordPress error.
         * 
         * @throws WPCASException
         * 
         * @global $_GET
         * 
         * @uses apply_filters()
         * @uses is_wp_error()
         */
        protected function dispatch ( $path ) {

            /**
             * Allows developers to disable CAS.
             * 
             * @param boolean $cas_enabled Whether the server should respond to single sign-on requests.
             */
            $enabled = apply_filters( 'cas_enabled', true );

            if (!$enabled) {
                throw new WPCASException( __('The CAS server is disabled.', 'wp-cas-server') );
            }

            foreach ($this->routes() as $route => $callback) {

                $match = preg_match( '@^' . $route . '/?$@', $path );

                if (!$match) {
                    continue;
                }

                if (!is_callable( $callback )) {
                    throw new WPCASException( __('The handler for the route is invalid.', 'wp-cas-server') );
                }

                $args = $_GET;

                /**
                 * Filters the callback arguments to be dispatched for the request.
                 * 
                 * Plugin developers may return a WP_Error object via the cas_server_dispatch_args
                 * filter to abort the request.
                 * 
                 * @param  array  $args     Arguments to pass the callback.
                 * @param  mixed  $callback Callback function or method.
                 * @param  string $path     Requested URI path.
                 * 
                 * @return mixed            Arguments to pass the callback, or `WP_Error`.
                 */
                $args = apply_filters( 'cas_server_dispatch_args', $args, $callback, $path );

                if (is_wp_error( $args )) {
                    throw WPCASException::fromError( $args );
                }

                return call_user_func( $callback, $args );
            }

            throw new WPCASRequestException( __( 'The server does not support the method requested.', 'wp-cas-server' ) );
        }

        /**
         * Wraps calls to session_start() to prevent 'headers already sent' errors.
         */
        protected function sessionStart () {
            $sessionExists = function_exists( 'session_status' ) && session_status() == PHP_SESSION_NONE;
            if (headers_sent() || $sessionExists || strlen( session_id() )) return;
            session_start();
        }

        /**
         * Wraps calls to session destruction functions.
         */
        protected function sessionDestroy () {
            wp_logout();
            wp_set_current_user( false );

            $sessionExists = function_exists( 'session_status' ) && session_status() == PHP_SESSION_NONE;
            if (headers_sent() || !$sessionExists || !strlen( session_id() )) return;

            session_unset();
            session_destroy();
        }

        /**
         * Sets an HTTP response header.
         * 
         * @param string $key   Header key.
         * @param string $value Header value.
         */
        protected function setResponseHeader ( $key, $value ) {
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
        protected function xmlResponse ( DOMNode $response ) {
            $this->setResponseHeader( 'Content-Type', 'text/xml; charset=' . get_bloginfo( 'charset' ) );

            $root = $this->xmlResponse->createElementNS( ICASServer::CAS_NS, 'cas:serviceResponse' );
            $root->appendChild( $response );

            // Removing all child nodes from response document:
            
            while ($this->xmlResponse->firstChild) {
                $this->xmlResponse->removeChild( $this->xmlResponse->firstChild );
            }

            $this->xmlResponse->appendChild( $root );

            return $this->xmlResponse->saveXML();
        }

        /**
         * XML success response to a CAS 2.0 validation request.
         * 
         * @param  WPCASTicket $ticket               Validated ticket.
         * @param  string      $proxyGrantingTicket  Generated Proxy-Granting Ticket (PGT) to return.
         * @param  array       $proxies              List of proxy URIs.
         * 
         * @return DOMElement                        CAS success response XML fragment.
         * 
         * @uses apply_filters()
         * @uses get_userdata()
         */
        protected function xmlValidateSuccess ( WPCASTicket $ticket, $proxyGrantingTicket = '', $proxies = array() ) {

            $response = $this->xmlResponse->createElementNS( ICASServer::CAS_NS,
                "cas:authenticationSuccess" );

            // Include login name:
            
            $response->appendChild( $this->xmlResponse->createElementNS( ICASServer::CAS_NS,
                "cas:user", $ticket->user->user_login ) );

            // CAS attributes:
            
            $attributes = WPCASServerPlugin::getOption( 'attributes' );
            
            if (is_array( $attributes ) && count( $attributes ) > 0) {

                $responseAttributes = array();

                foreach ($attributes as $key) {
                    $responseAttributes[$key] = $ticket->user->get( $key );
                }

                /**
                 * Allows developers to change the list of (key, value) pairs before they're included
                 * in a `/serviceValidate` response.
                 * 
                 * @param  array   $attributes List of attributes to output.
                 * @param  WP_User $user       Authenticated user.
                 */
                $responseAttributes = apply_filters( 'cas_server_validation_extra_attributes', $responseAttributes, $ticket->user );

                $xmlAttributes = $this->xmlResponse->createElementNS( ICASServer::CAS_NS, "cas:attributes" );

                foreach ($responseAttributes as $key => $value) {
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
        protected function xmlProxySuccess ( WP_User $user, $service = '' ) {

            $expiration = WPCASServerPlugin::getOption( 'expiration', 30 );

            $proxyTicket = new WPCASTicket( WPCASTicket::TYPE_PT, $user, $service, $expiration );

            $response = $this->xmlResponse->createElementNS( ICASServer::CAS_NS,
                "cas:proxySuccess" );

            $response->appendChild( $this->xmlResponse->createElementNS( ICASServer::CAS_NS,
                "cas:proxyTicket", $proxyTicket ) );

            return $response;
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
        protected function xmlError ( WP_Error $error = NULL, $tag = 'authenticationFailure' ) {

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

            $response = $this->xmlResponse->createElementNS( ICASServer::CAS_NS, "cas:$tag", $message );

            $response->setAttribute( "code", $code );

            return $this->xmlResponse( $response );
        }

        /**
         * Validates a ticket, returning a ticket object, or throws an exception.
         * 
         * Triggers the `cas_server_validation_success` action on ticket validation.
         * 
         * @param  string      $ticket             Service or proxy ticket.
         * @param  string      $service            Service URI.
         * @param  array       $validTicketTypes   Ticket must be of the specified types.
         * 
         * @return WPCASTicket                     Valid ticket object associated with request.
         *                                               
         * @uses do_action()
         * 
         * @throws WPCASRequestException
         * @throws WPCASTicketException
         */
        protected function validateRequest ( $ticket = '', $service = '', $validTicketTypes = array() ) {

            if (empty( $ticket )) {
                throw new WPCASRequestException( __( 'Ticket is required.', 'wp-cas-server' ) );
            }

            if (empty( $service )) {
                throw new WPCASRequestException( __( 'Service is required.', 'wp-cas-server' ) );
            }

            try {
                WPCASTicket::validateAllowedTypes( $ticket, $validTicketTypes );

                $ticket = WPCASTicket::fromString( $ticket );

                $ticket->markUsed();

            } catch (WPCASTicketException $exception) {
                $isProxyRequest = in_array( WPCASTicket::TYPE_PGT, $validTicketTypes );

                // FIXME: I don't like this.
                
                if ($isProxyRequest) {
                    throw new WPCASTicketException( $exception->getMessage(),
                        WPCASTicketException::ERROR_BAD_PGT );
                }

                throw $exception;
            }

            if ($ticket->service !== $service ) {
                throw new WPCASRequestException( __( 'Ticket does not match the service provided.', 'wp-cas-server' ),
                    WPCASRequestException::ERROR_INVALID_SERVICE );
            }

            /**
             * Fires on successful ticket validation.
             * 
             * @param WPCASTicket $ticket Valid ticket object.
             */
            do_action( 'cas_server_validation_success', $ticket );

            return $ticket;
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
        protected function loginUser ( $user, $service = "" ) {

            $expiration = WPCASServerPlugin::getOption( 'expiration', 30 );

            $ticket = new WPCASTicket( WPCASTicket::TYPE_ST, $user, $service, $expiration );

            if (empty( $service )) {
                $service = home_url();
            }

            $service = add_query_arg( 'ticket', (string) $ticket, $service );

            /**
             * Filters the redirect URI for the service requesting user authentication.
             * 
             * @param  string  $service Service URI requesting user authentication.
             * @param  WP_User $user    Logged in WordPress user.
             */
            $service = apply_filters( 'cas_server_redirect_service', $service, $user );

            $this->redirect( $service );
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
        protected function authRedirect ( $args = array() ) {
            /**
             * Allows developers to redirect the user to a custom login form.
             * 
             * @param string $custom_login_url URI for the custom login page.
             * @param array  $args             Login request parameters.
             */
            $custom_login_url = apply_filters( 'cas_server_custom_auth_uri', false, $args );

            if ($custom_login_url) {
                $this->redirect( $custom_login_url );
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
         * 
         * @global $_POST
         */
        public function login ( $args = array() ) {

            $args = array_merge( $_POST, (array) $args );

            /**
             * Allows developers to change the request parameters passed to a `/login` request.
             * 
             * @param array $args HTTP request (GET, POST) parameters.
             */
            $args = apply_filters( 'cas_server_login_args', $args );

            if (isset( $args['username'] ) && isset( $args['password'] ) && isset( $args['lt'] )) {
                $this->loginAcceptor( $args );
                return;
            }

            $this->loginRequestor( $args );

            return;
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
         * @todo Support for the optional warn parameter.
         */
        protected function loginAcceptor ( $args = array() ) {

            $username   = sanitize_user( $args['username'] );
            $password   = $args['password'];
            $lt         = preg_replace( '@^' . WPCASTicket::TYPE_LT . '-@', '', $args['lt'] );
            $service    = isset( $args['service'] ) ? esc_url_raw( $args['service'] ) : null;
            // $warn       = isset( $args['warn'] ) && 'true' === $args['warn'];

            if (!wp_verify_nonce( $lt, 'lt' )) {
                $this->authRedirect( $args );
            }

            $user = wp_signon( array(
                'user_login'    => $username,
                'user_password' => $password,
                ) );

            if (!$user || is_wp_error( $user )) {
                $this->authRedirect( $args );
            }

            $this->loginUser( $user, $service );
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
         * @uses is_ssl()
         * @uses is_user_logged_in()
         * @uses remove_query_arg()
         * @uses wp_get_current_user()
         * @uses wp_logout()
         */
        protected function loginRequestor ( $args = array() ) {

            $this->sessionStart();

            $renew   = isset( $args['renew'] )   && 'true' === $args['renew'];
            $gateway = isset( $args['gateway'] ) && 'true' === $args['gateway'];
            $service = isset( $args['service'] ) ? esc_url_raw( $args['service'] ) : '';

            if ($renew) {
                wp_logout();

                $schema = is_ssl() ? 'https://' : 'http://';
                $url    = $schema . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                $url    = remove_query_arg( 'renew', $url );

                $this->redirect( $url );
            }

            if (!is_user_logged_in()) {
                if ($gateway && $service) {
                    $this->redirect( $service );
                }

                $this->authRedirect( $args );
            }

            $this->loginUser( wp_get_current_user(), $service );
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
        public function logout ( $args = array() ) {
            $service = !empty( $args['service'] ) ? esc_url_raw( $args['service'] ) : home_url();
            $this->sessionStart();
            $this->sessionDestroy();
            $this->redirect( $service );
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
         * @param  array  $args Request arguments.
         * 
         * @return string       Response XML string.
         * 
         * @uses esc_url_raw()
         */
        public function proxy ( $args = array() ) {

            $pgt            = !empty( $args['pgt'] )           ? $args['pgt']                          : '';
            $targetService  = !empty( $args['targetService'] ) ? esc_url_raw( $args['targetService'] ) : '';

            /**
             * `/proxy` checks the validity of the proxy-granting ticket passed.
             */
            $validTicketTypes = array(
                WPCASTicket::TYPE_PGT,
            );

            try {
                $ticket = $this->validateRequest( $pgt, $targetService, $validTicketTypes );
            }
            catch (WPCASException $exception) {
                return $this->xmlError( $exception->getErrorInstance(), 'proxyFailure' );
            }

            return $this->xmlResponse( $this->xmlProxySuccess( $ticket->user, $targetService ) );
        }

        /**
         * `/proxyValidate` must perform the same validation tasks as `/serviceValidate` and
         * additionally validate proxy tickets. `/proxyValidate` must be capable of validating both
         * service tickets and proxy tickets.
         * 
         * @param  array  $args Request arguments.
         * 
         * @return string       Response XML string.
         * 
         * @uses esc_url_raw()
         * 
         * @todo Accept proxy callback URL (pgtUrl) parameter.
         * @todo Accept renew parameter.
         */
        public function proxyValidate ( $args = array() ) {

            $service = !empty( $args['service'] ) ? esc_url_raw( $args['service'] ) : '';
            $ticket  = !empty( $args['ticket'] )  ? $args['ticket']                 : '';
            // $pgtUrl  = !empty( $args['pgtUrl'] )  ? esc_url_raw( $args['pgtUrl'] )  : '';
            // $renew   = isset( $args['renew'] ) && 'true' === $args['renew'];

            /**
             * `/proxyValidate` checks the validity of both service and proxy tickets.
             */
            $validTicketTypes = array(
                WPCASTicket::TYPE_ST,
                WPCASTicket::TYPE_PT,
            );

            try {
                $ticket = $this->validateRequest( $ticket, $service, $validTicketTypes );
            }
            catch (WPCASException $exception) {
                return $this->xmlError( $exception->getErrorInstance() );
            }

            return $this->xmlResponse( $this->xmlValidateSuccess( $ticket ) );
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
         * @return string       Response XML string.
         * 
         * @uses esc_url_raw()
         * 
         * @todo Accept proxy callback URL (pgtUrl) parameter.
         * @todo Accept renew parameter.
         */
        public function serviceValidate ( $args = array() ) {
            
            $service = !empty( $args['service'] ) ? esc_url_raw( $args['service'] ) : '';
            $ticket  = !empty( $args['ticket'] )  ? $args['ticket']                 : '';
            // $pgtUrl  = !empty( $args['pgtUrl'] )  ? esc_url_raw( $args['pgtUrl'] )  : '';
            // $renew   = isset( $args['renew'] ) && 'true' === $args['renew'];

            /**
             * `/serviceValidate` checks the validity of a service ticket and does not handle proxy
             * authentication. CAS MUST respond with a ticket validation failure response when a proxy
             * ticket is passed to `/serviceValidate`.
             */
            $validTicketTypes = array(
                WPCASTicket::TYPE_ST,
            );

            try {
                $ticket = $this->validateRequest( $ticket, $service, $validTicketTypes );
            }
            catch (WPCASException $exception) {
                return $this->xmlError( $exception->getErrorInstance() );
            }

            return $this->xmlResponse( $this->xmlValidateSuccess( $ticket ) );
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
         * @uses esc_url_raw()
         * @uses get_bloginfo()
         */
        public function validate ( $args = array() ) {

            $this->setResponseHeader( 'Content-Type', 'text/plain; charset=' . get_bloginfo( 'charset' ) );

            $service = !empty( $args['service'] ) ? esc_url_raw( $args['service'] ) : '';
            $ticket  = !empty( $args['ticket'] )  ? $args['ticket']                 : '';

            /**
             * `/validate` checks the validity of a service ticket. `/validate` is part of the CAS 1.0
             * protocol and thus does not handle proxy authentication. CAS MUST respond with a ticket
             * validation failure response when a proxy ticket is passed to `/validate`.
             */
            $validTicketTypes = array(
                WPCASTicket::TYPE_ST,
            );

            try {
                $ticket = $this->validateRequest( $ticket, $service, $validTicketTypes );
            }
            catch (WPCASException $exception) {
                return "no\n\n";
            }

            return "yes\n" . $ticket->user->user_login . "\n";
        }

    }

} // !class_exists( 'WPCASServer' )
