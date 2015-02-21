<?php
/**
 * Implements the ICASServer interface as required by the WP CAS Server plugin.
 *
 * @package \WPCASServerPlugin\Server
 * @version 1.1.0
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once dirname( __FILE__ ) . '/ICASServer.php';
require_once dirname( __FILE__ ) . '/WPCASException.php';
require_once dirname( __FILE__ ) . '/WPCASRequestException.php';
require_once dirname( __FILE__ ) . '/WPCASTicket.php';

require_once dirname( __FILE__ ) . '/Controller/WPCASController.php';
require_once dirname( __FILE__ ) . '/Controller/WPCASControllerLogout.php';
require_once dirname( __FILE__ ) . '/Controller/WPCASControllerValidate.php';
require_once dirname( __FILE__ ) . '/Controller/WPCASControllerProxy.php';
require_once dirname( __FILE__ ) . '/Controller/WPCASControllerProxyValidate.php';
require_once dirname( __FILE__ ) . '/Controller/WPCASControllerServiceValidate.php';

require_once dirname( __FILE__ ) . '/Response/WPCASResponse.php';
require_once dirname( __FILE__ ) . '/Response/WPCASResponseProxy.php';
require_once dirname( __FILE__ ) . '/Response/WPCASResponseValidate.php';


if ( ! class_exists( 'WPCASServer' ) ) {

	/**
	 * Class providing all public CAS methods.
	 *
	 * @since 1.0.0
	 */
	class WPCASServer implements ICASServer {

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
		public function routes() {
			$routes = array(
				'login'              => array( $this, 'login' ),
				'logout'             => array( $this, 'logout' ),
				'validate'           => array( $this, 'validate' ),
				'proxy'              => array( $this, 'proxy' ),
				'proxyValidate'      => array( $this, 'proxyValidate' ),
				'serviceValidate'    => array( $this, 'serviceValidate' ),
				'p3/proxyValidate'   => array( $this, 'proxyValidate' ),
				'p3/serviceValidate' => array( $this, 'serviceValidate' ),
				);

			/**
			 * Allows developers to override the default callback
			 * mapping, define additional endpoints and provide
			 * alternative implementations to the provided methods.
			 *
			 * @param array $cas_routes CAS endpoint to callback mapping.
			 */
			return apply_filters( 'cas_server_routes', $routes );
		}

		/**
		 * Perform an HTTP redirect.
		 *
		 * If the 'allowed_services' contains at least one host, it will always perform a safe
		 * redirect.
		 *
		 * Calling WPCASServer::redirect() will _always_ terminate the request.
		 *
		 * @param  string  $location URI to redirect to.
		 * @param  integer $status   HTTP status code (default 302).
		 *
		 * @uses wp_redirect()
		 * @uses wp_safe_redirect()
		 */
		public function redirect( $location, $status = 302 ) {
			$allowedServices = WPCASServerPlugin::getOption( 'allowed_services' );

			if ( is_array( $allowedServices ) && count( $allowedServices ) > 0 ) {
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
		public function handleRequest( $path ) {

			if (!defined( 'CAS_REQUEST' )) define( 'CAS_REQUEST', true );

			$this->setResponseHeader( 'Pragma'       , 'no-cache' );
			$this->setResponseHeader( 'Cache-Control', 'no-store' );
			$this->setResponseHeader( 'Expires'      , gmdate( ICASServer::RFC1123_DATE_FORMAT ) );

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
				$this->setResponseContentType( 'text/xml' );
				$response = new WPCASResponse();
				$response->setError( $exception->getErrorInstance() );
				$output = $response->prepare();
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
		protected function dispatch( $path ) {

			/**
			 * Allows developers to disable CAS.
			 *
			 * @param boolean $cas_enabled Whether the server should respond to single sign-on requests.
			 */
			$enabled = apply_filters( 'cas_enabled', true );

			if (!$enabled) {
				throw new WPCASException( __('The CAS server is disabled.', 'wp-cas-server') );
			}

			$routes = $this->routes();

			foreach ( $routes as $route => $callback ) {

				$match = preg_match( '@^' . preg_quote( $route ) . '/?$@', $path );

				if ( ! $match ) {
					continue;
				}

				if ( ! is_callable( $callback ) ) {
					throw new WPCASException( __('The handler for the route is invalid.', 'wp-cas-server') );
				}

				$args = $_GET;

				/**
				 * Filters the callback arguments to be dispatched for the request.
				 *
				 * Plugin developers may return a WP_Error object via the `cas_server_dispatch_args`
				 * filter to abort the request. Avoid throwing a `WPCASException` exception here
				 * because that would interrupt the filter callback chain.
				 *
				 * @param  array  $args     Arguments to pass the callback.
				 * @param  mixed  $callback Callback function or method.
				 * @param  string $path     Requested URI path.
				 *
				 * @return mixed            Arguments to pass the callback, or `WP_Error`.
				 */
				$args = apply_filters( 'cas_server_dispatch_args', $args, $callback, $path );

				if ( is_wp_error( $args ) ) {
					throw WPCASException::fromError( $args );
				}

				return call_user_func( $callback, $args );
			}

			throw new WPCASRequestException(
				__( 'The server does not support the method requested.', 'wp-cas-server' ) );
		}

		/**
		 * Wraps calls to session_start() to prevent 'headers already sent' errors.
		 */
		public function sessionStart() {
			$sessionExists = function_exists( 'session_status' ) && session_status() === PHP_SESSION_NONE;
			if ( headers_sent() || $sessionExists || strlen( session_id() ) ) {
				return;
			}
			session_start();
		}

		/**
		 * Wraps calls to session destruction functions.
		 */
		public function sessionDestroy() {
			wp_logout();
			wp_set_current_user( false );

			$sessionExists = function_exists( 'session_status' ) && session_status() === PHP_SESSION_NONE;

			if ( headers_sent() || ! $sessionExists || ! strlen( session_id() ) ) {
				return;
			}

			session_unset();
			session_destroy();
		}

		/**
		 * Sets an HTTP response header.
		 *
		 * @param string $key   Header key.
		 * @param string $value Header value.
		 */
		protected function setResponseHeader( $key, $value ) {
			if (headers_sent()) return;
			header( sprintf( '%s: %s', $key, $value ) );
		}

		/**
		 * Set response headers for a CAS version response.
		 */
		public function setResponseContentType( $type ) {
			$this->setResponseHeader( 'Content-Type', $type . '; charset=' . get_bloginfo( 'charset' ) );
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
		protected function loginUser( $user, $service = '' ) {
			$expiration = WPCASServerPlugin::getOption( 'expiration', 30 );

			$ticket     = new WPCASTicket( WPCASTicket::TYPE_ST, $user, $service, $expiration );

			$service    = empty( $service ) ? home_url() : esc_url_raw( $service );
			$service    = add_query_arg( 'ticket', (string) $ticket, $service );

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
		public function authRedirect ( $args = array() ) {
			/**
			 * Allows developers to redirect the user to a custom login form.
			 *
			 * @param string $custom_login_url URI for the custom login page.
			 * @param array  $args             Login request parameters.
			 */
			$custom_login_url = apply_filters( 'cas_server_custom_auth_uri', false, $args );

			if ( $custom_login_url ) {
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
		 * @uses wp_signon()
		 * @uses wp_verify_nonce()
		 *
		 * @todo Support for the optional warn parameter.
		 */
		protected function loginAcceptor( $args = array() ) {

			$username   = sanitize_user( $args['username'] );
			$password   = $args['password'];
			$lt         = preg_replace( '@^' . WPCASTicket::TYPE_LT . '-@', '', $args['lt'] );
			$service    = isset( $args['service'] ) ? $args['service'] : null;
			// $warn       = isset( $args['warn'] ) && 'true' === $args['warn'];

			if ( ! wp_verify_nonce( $lt, 'lt' ) ) {
				$this->authRedirect( $args );
			}

			$user = wp_signon( array(
				'user_login'    => $username,
				'user_password' => $password,
			) );

			if ( ! $user || is_wp_error( $user ) ) {
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
		 * @uses is_ssl()
		 * @uses is_user_logged_in()
		 * @uses remove_query_arg()
		 * @uses wp_get_current_user()
		 * @uses wp_logout()
		 */
		protected function loginRequestor( $args = array() ) {

			$this->sessionStart();

			$renew   = isset( $args['renew'] )   && 'true' === $args['renew'];
			$gateway = isset( $args['gateway'] ) && 'true' === $args['gateway'];
			$service = isset( $args['service'] ) ? $args['service'] : '';

			if ( $renew ) {
				wp_logout();

				$schema = is_ssl() ? 'https://' : 'http://';
				$url    = $schema . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
				$url    = remove_query_arg( 'renew', $url );

				$this->redirect( $url );
			}

			if ( ! is_user_logged_in() ) {
				if ( $gateway && $service ) {
					$this->redirect( $service );
				}

				$this->authRedirect( $args );
			}

			$this->loginUser( wp_get_current_user(), $service );
		}

		/**
		 * @todo Remove.
		 */
		public function logout( $args = array() ) {
			$controller = new WPCASControllerLogout( $this );
			return $controller->handleRequest( $args );
		}

		/**
		 * @todo Remove.
		 */
		public function proxy( $args = array() ) {
			$controller = new WPCASControllerProxy( $this );
			return $controller->handleRequest( $args );
		}

		/**
		 * @todo Remove.
		 */
		public function proxyValidate( $args = array() ) {
			$controller = new WPCASControllerProxyValidate( $this );
			return $controller->handleRequest( $args );
		}

		/**
		 * @todo Remove.
		 */
		public function serviceValidate( $args = array() ) {
			$controller = new WPCASControllerServiceValidate( $this );
			return $controller->handleRequest( $args );
		}

		/**
		 * @todo Remove.
		 */
		public function validate( $args = array() ) {
			$controller = new WPCASControllerValidate( $this );
			return $controller->handleRequest( $args );
		}

	}

} // !class_exists( 'WPCASServer' )
