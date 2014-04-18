<?php
/**
 * @package WPCASServerPlugin
 * @subpackage WPCASServerPlugin
 */

if (!class_exists( 'WPCASServer' )) :

class WPCASServer {

    const CAS_NS                = 'http://www.yale.edu/tp/cas';

    const ERROR_INTERNAL_ERROR  = 'INTERNAL_ERROR';
    const ERROR_INVALID_REQUEST = 'INVALID_REQUEST';
    const ERROR_INVALID_SERVICE = 'INVALID_SERVICE';
    const ERROR_INVALID_TICKET  = 'INVALID_TICKET';
    const ERROR_BAD_PGT         = 'BAD_PGT';

    const TYPE_ST               = 'ST-';
    const TYPE_PT               = 'PT-';
    const TYPE_PGT              = 'PGT-';
    const TYPE_PGTIOU           = 'PGTIOU-';
    const TYPE_TGC              = 'TGC-';
    const TYPE_LT               = 'LT-';

    const RFC1123_DATE_FORMAT   = 'D, d M Y H:i:s T';

    /**
     * XML response.
     * @var DOMDocument
     */
    protected $response;

    /**
     * WordPress CAS Server constructor.
     * 
     * @uses get_option()
     */
    public function __construct () {
        $this->response = new DOMDocument( '1.0', get_option( 'blog_charset' ) );
    }

    //
    // CAS Server Methods
    //

    /**
     * Get the list of routes supported by this CAS server and the callbacks each will invoke.
     * 
     * - /login
     * - /logout
     * - /proxy
     * - /proxyValidate
     * - /serviceValidate
     * - /validate
     * 
     * @return array Array containing supported routes as keys and their callbacks as values.
     * 
     * @uses apply_filters()
     */
    protected function getRoutes () {

        $cas_routes = array(
            '/login/?'            => array( $this, 'login' ),
            '/logout/?'           => array( $this, 'logout' ),
            '/proxy/?'            => array( $this, 'proxy' ),
            '/proxyValidate/?'    => array( $this, 'proxyValidate' ),
            '/serviceValidate/?'  => array( $this, 'serviceValidate' ),
            '/validate/?'         => array( $this, 'validate' ),
            );

        return apply_filters( 'cas_server_routes', $cas_routes );
    }

    /**
     * [serveRequest description]
     * 
     * @param  string $path CAS request URI.
     * 
     * @return void
     */
    public function handleRequest ( $path ) {
        define( 'CAS_REQUEST', true );

        $this->_setResponseHeader( 'Pragma'         , 'no-cache' );
        $this->_setResponseHeader( 'Cache-Control'  , 'no-store' );
        $this->_setResponseHeader( 'Expires'        , gmdate( self::RFC1123_DATE_FORMAT ) );

        do_action( 'cas_server_before_request' );

        if (empty( $path )) {
            $path = isset( $_SERVER['PATH_INFO'] ) ? $_SERVER['PATH_INFO'] : '/';
        }

        $result = $this->_dispatch( $path );

        if (is_wp_error( $result )) {
            echo $this->_xmlResponse( $this->_xmlError( $result ) );
        }
        else {
            echo $result;
        }

        do_action( 'cas_server_after_request' );
    }

    /**
     * Dispatch the request for processing by the relevant callback as determined by the routes
     * list returned by WPCASServer::getRoutes().
     * 
     * @return (string|WP_Error) Service response string or WordPress error.
     * 
     * @uses apply_filters()
     * @uses is_wp_error()
     */
    protected function _dispatch ( $path ) {

        $enabled = apply_filters( 'cas_enabled', true );

        if (!$enabled) {
            return new WP_Error( 'authenticationFailure',
                __('The CAS server is disabled.', 'wordpress-cas-server'),
                array( 'code' => self::ERROR_INTERNAL_ERROR )
                );
        }

        foreach ($this->getRoutes() as $route => $callback) {

            $match = preg_match( '@^' . $route . '$@', $path );

            if (!$match) {
                continue;
            }

            $callback = apply_filters( 'cas_server_dispatch_callback', $callback );

            if (!is_callable( $callback )) {
                return new WP_Error( 'authenticationFailure',
                    __('The handler for the route is invalid.', 'wordpress-cas-server'),
                    array( 'code' => self::ERROR_INTERNAL_ERROR )
                    );
            }

            $args = $_GET;

            $args = apply_filters( 'cas_server_dispatch_args', $args, $callback );

            // Allow plugins to halt the request via this filter:
            if (is_wp_error( $args )) {
                return $args;
            }

            return call_user_func( $callback, $args );
        }

        return new WP_Error( 'authenticationFailure',
            __( 'The server does not support the method requested.', 'wordpress-cas-server' ),
            array( 'code' => self::ERROR_INVALID_REQUEST )
            );
    }

    /**
     * Sets an HTTP response header.
     * 
     * @param string $key   Header key.
     * @param string $value Header value.
     */
    protected function _setResponseHeader ( $key, $value ) {
        header( sprintf( '%s: %s', $key, $value ) );
    }

    /**
     * Wrap an XML CAS response and output it as a string.
     * 
     * @param DOMNode $response 
     * @return string
     */
    protected function _xmlResponse ( $response ) {
        $this->_setResponseHeader( 'Content-Type', 'text/xml; charset=' . get_option( 'blog_charset' ) );

        $root = $this->response->createElementNS( self::CAS_NS, 'cas:serviceResponse' );
        $root->appendChild( $response );
        $this->response->appendChild($root);

        return $this->response->saveXML();
    }

    /**
     * Error response.
     * 
     * @param  WP_Error   $wp_error Error object.
     * @return DOMElement CAS error.
     * 
     * @uses do_action()
     */
    protected function _xmlError ( $error ) {
        do_action( 'cas_server_error', $error );

        foreach (array( 'authenticationFailure', 'proxyFailure' ) as $type) {
            if (!empty( $error->errors[$type] )) {
                $element = $this->response->createElementNS( self::CAS_NS,
                    "cas:$type", implode( "\n", $error->errors[$type] ) );
                $element->setAttribute( "code", $error->error_data[$type]['code'] );
                return $element;
            }
        }

        $element = $this->response->createElementNS( self::CAS_NS,
            "cas:authenticationFailure", __( 'Unknown error', 'wordpress-cas-server' ) );
        $element->setAttribute( "code", self::ERROR_INTERNAL_ERROR );
        return $element;
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
     * @uses wp_generate_auth_cookie()
     */
    protected function _createTicket( $user, $type = self::TYPE_ST, $expiration = 15 ) {
        return $type . urlencode( str_rot13( wp_generate_auth_cookie( $user->ID, time() + $expiration, 'auth' ) ) );
    }

    //
    // CAS Server Protocol Methods
    //

    /**
     * Implements the /login URI and determines whether to interpret the request as a credential
     * requestor or a credential acceptor.
     * 
     * @param  array $args Request arguments.
     * @return void
     */
    protected function login ( $args ) {

        $args = array_merge( $_POST, $args );
        $args = apply_filters( 'cas_server_login_args', $args );

        if (isset( $args['username'] ) && isset( $args['password'] ) && isset( $args['lt'] )) {
            return $this->_loginAcceptor( $args );
        }

        return $this->_loginRequestor( $args );
    }

    /**
     * Implements the /login URI behaviour as credential acceptor when a set of accepted credentials
     * are passed to /login via POST.
     * 
     * The following HTTP request parameters MUST be passed to /login while it is acting as a
     * credential acceptor for username/password authentication. They are all case-sensitive.
     * 
     * - username: The username of the client that is trying to log in.
     * - password: The password of the client that is trying to log in.
     * - lt: A login ticket. It acts as a nonce to prevent replaying requests and must be generated
     *   using wp_create_nonce( 'lt' ).
     * 
     * The following HTTP request parameters are optional:
     * 
     * - service: The URL of the application the client is trying to access. CAS will redirect the
     *   client to this URL upon successful authentication.
     * - warn: If this parameter is set, single sign-on will NOT be transparent. The client will be
     *   prompted before being authenticated to another service.
     * 
     * @param  array $args Request arguments.
     * @return void
     * 
     * @uses sanitize_user()
     * @uses sanitize_url()
     * @uses wp_signon()
     * @uses wp_verify_nonce()
     * 
     * @todo Support for the optional "warn" parameter.
     */
    protected function _loginAcceptor ( $args ) {

        $username   = sanitize_user( $args['username'] );
        $password   = $args['password'];
        $lt         = preg_replace( '@^' . self::TYPE_LT . '@', '', $args['lt'] );

        $service    = sanitize_url( $args['service'] );
        $warn       = isset( $args['warn'] ) && 'true' === $args['warn'];

        // TODO: Support for the optional "warn" parameter.

        if (!wp_verify_nonce( $lt, 'lt' )) {

        }

        $user = wp_signon( array(
            'user_login'    => $username,
            'user_password' => $password,
            ) );
        
        $this->_loginUser( $user, $service );
    }

    /**
     * Implements the /login URI as credential requestor.
     * 
     * If the client has already established a single sign-on session with CAS, the
     * client will have presented its HTTP session cookie to /login unless the "renew"
     * parameter is set to "true".
     * 
     * If there is no session or the "renew" parameter is set, CAS will respond by
     * displaying a login screen requesting (usually) a username and password.
     * 
     * @param  array  $args Request arguments.
     * @return void
     * 
     * @uses add_query_arg()
     * @uses apply_filters()
     * @uses auth_redirect()
     * @uses get_option()
     * @uses get_user_by()
     * @uses is_user_logged_in()
     * @uses remove_query_arg()
     * @uses sanitize_url()
     * @uses wp_logout()
     * @uses wp_redirect()
     */
    protected function _loginRequestor ( $args ) {

        $renew   = isset( $args['renew'] )   && 'true' === $args['renew'];
        $gateway = isset( $args['gateway'] ) && 'true' === $args['gateway'];
        $service = sanitize_url( $args['service'] );

        if ($renew) {
            wp_logout();

            $url = '//' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            $url = remove_query_arg( 'renew', $url );

            wp_redirect( $url );
            exit;
        }

        if (false && !is_user_logged_in()) {
            if ($gateway && !empty( $service )) {
                wp_redirect( $service );
                exit;
            }
            else
            {
                auth_redirect();
                exit;
            }
        }

        $user = get_current_user();

        $this->_loginUser( $user, $service );
    }

    /**
     * Login 
     * @param  WP_User $user    WordPress user to authenticate.
     * @param  string  $service URI for the service requesting user authentication.
     * @return void
     */
    protected function _loginUser ( $user, $service ) {
        $ticket = $this->_createTicket( $user, self::TYPE_ST );
        $ticket = apply_filters( 'cas_server_ticket', $ticket, self::TYPE_ST, $user );

        if (!empty( $service )) {
            $service = add_query_arg( 'ticket', $ticket, $service );
            $service = apply_filters( 'cas_server_service', $service, $user );
            wp_redirect( $service );
            exit;
        }

        wp_redirect( get_option( 'home' ) );
        exit;
    }

    /**
     * [logout description]
     * 
     * @return [type] [description]
     * 
     * @uses get_option()
     * @uses wp_logout()
     * @uses wp_redirect()
     */
    protected function logout ( $args ) {
        $service = sanitize_url( $args['service'] );
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
     * @return [type] [description]
     */
    protected function proxy ( $args ) {
    }

    /**
     * [proxyValidate description]
     * 
     * @return [type] [description]
     */
    protected function proxyValidate ( $args ) {
    }

    /**
     * [serviceValidate description]
     * 
     * @return [type] [description]
     */
    protected function serviceValidate ( $args ) {
    }

    /**
     * [validate description]
     * 
     * @return [type] [description]
     */
    protected function validate ( $args ) {
    }

}

endif;
