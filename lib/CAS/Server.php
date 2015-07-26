<?php
/**
 * Implements the CAS server class.
 *
 * @version 1.2.0
 * @since   1.0.0
 */

namespace Cassava\CAS;

use Cassava\Exception\GeneralException;
use Cassava\Exception\RequestException;
use Cassava\Options;
use Cassava\Plugin;

/**
 * Class providing all public CAS methods.
 *
 * @since 1.0.0
 */
class Server {

	/**
	 * RFC 1123 Date-Time Format
	 */
	const RFC1123_DATE_FORMAT = 'D, d M Y H:i:s T';

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
	 * @uses \apply_filters()
	 */
	public function routes() {
		$namespace = '\\Cassava\\CAS\\Controller\\';

		$routes = array(
			'login'              => $namespace . 'LoginController',
			'logout'             => $namespace . 'LogoutController',
			'validate'           => $namespace . 'ValidateController',
			'proxy'              => $namespace . 'ProxyController',
			'proxyValidate'      => $namespace . 'ProxyValidateController',
			'serviceValidate'    => $namespace . 'ServiceValidateController',
			'p3/proxyValidate'   => $namespace . 'ProxyValidateController',
			'p3/serviceValidate' => $namespace . 'ServiceValidateController',
			);

		/**
		 * Allows developers to override the default controller
		 * mapping, define additional endpoints and provide
		 * alternative implementations to the provided methods.
		 *
		 * @param array $cas_routes CAS endpoint to controller mapping.
		 */
		return \apply_filters( 'cas_server_routes', $routes );
	}

	/**
	 * Perform an HTTP redirect.
	 *
	 * If the 'allowed_services' contains at least one host, it will always perform a safe
	 * redirect.
	 *
	 * Calling Server::redirect() will _always_ end the request.
	 *
	 * @param  string  $location URI to redirect to.
	 * @param  integer $status   HTTP status code (default 302).
	 *
	 * @uses \wp_redirect()
	 * @uses \wp_safe_redirect()
	 */
	public function redirect( $location, $status = 302 ) {
		$allowedServices = Options::get( 'allowed_services' );

		if ( is_array( $allowedServices ) && count( $allowedServices ) > 0 ) {
			\wp_safe_redirect( $location, $status );
		}

		\wp_redirect( $location, $status );
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
	 * @throws \Cassava\Exception\GeneralException
	 *
	 * @global $_SERVER
	 *
	 * @uses \apply_filters()
	 * @uses \do_action()
	 */
	public function handleRequest( $path ) {

		if ( ! defined( 'CAS_REQUEST' ) ) {
			define( 'CAS_REQUEST', true );
		}

		$this->setResponseHeader( 'Pragma'       , 'no-cache' );
		$this->setResponseHeader( 'Cache-Control', 'no-store' );
		$this->setResponseHeader( 'Expires'      , gmdate( static::RFC1123_DATE_FORMAT ) );

		/**
		 * Fires before processing the CAS request.
		 *
		 * @param  string $path Requested URI path.
		 * @return string       Filtered requested URI path.
		 */
		\do_action( 'cas_server_before_request', $path );

		if (empty( $path )) {
			$path = isset( $_SERVER['PATH_INFO'] ) ? $_SERVER['PATH_INFO'] : '/';
		}

		try {
			$output = $this->dispatch( $path );
		}
		catch ( GeneralException $exception ) {
			$this->setResponseContentType( 'text/xml' );
			$response = new Response\BaseResponse();
			$response->setError( $exception->getErrorInstance() );
			$output = $response->prepare();
		}

		/**
		 * Fires after the CAS request is processed.
		 *
		 * @param string $path Requested URI path.
		 */
		\do_action( 'cas_server_after_request', $path );

		/**
		 * Lets developers change the CAS server response string.
		 *
		 * @param string $output Response output string.
		 * @param string $path   Requested URI path.
		 */
		$output = \apply_filters( 'cas_server_response', $output, $path );

		return $output;
	}

	/**
	 * Dispatch the request for processing by the relevant callback as determined by the routes
	 * list returned by `Server::routes()`.
	 *
	 * @param  string $path Requested URI path.
	 * @return mixed        Service response string or WordPress error.
	 *
	 * @throws \Cassava\Exception\GeneralException
	 * @throws \Cassava\Exception\RequestException
	 *
	 * @global $_GET
	 *
	 * @uses \apply_filters()
	 * @uses \is_ssl()
	 * @uses \is_wp_error()
	 */
	protected function dispatch( $path ) {

		if ( ! \is_ssl() ) {
			throw new GeneralException(
				__( 'The CAS server requires SSL.', 'wp-cas-server' ) );
		}

		/**
		 * Allows developers to disable CAS.
		 *
		 * @param boolean $cas_enabled Whether the server should respond to single sign-on requests.
		 */
		$enabled = apply_filters( 'cas_enabled', true );

		if ( ! $enabled ) {
			throw new GeneralException(
				__( 'The CAS server is disabled.', 'wp-cas-server' ) );
		}

		$routes = $this->routes();

		foreach ( $routes as $route => $controller ) {

			$match = preg_match( '@^' . preg_quote( $route ) . '/?$@', $path );

			if ( ! $match ) {
				continue;
			}

			if ( ! class_exists( $controller ) ) {
				throw new GeneralException(
					__( 'The controller for the route is invalid.', 'wp-cas-server' ) );
			}

			$args = $_GET;

			/**
			 * Filters the controller arguments to be dispatched for the request.
			 *
			 * Plugin developers may return a WP_Error object via the
			 * `cas_server_dispatch_args` filter to abort the request. Avoid throwing
			 * a `\Cassava\Exception\GeneralException` exception here because that
			 * would interrupt the filter controller chain.
			 *
			 * @param  array  $args       Arguments to pass the controller.
			 * @param  mixed  $controller Controller class.
			 * @param  string $path       Requested URI path.
			 *
			 * @return mixed              Arguments to pass the controller, or `WP_Error`.
			 */
			$args = \apply_filters( 'cas_server_dispatch_args', $args, $controller, $path );

			if ( \is_wp_error( $args ) ) {
				throw GeneralException::fromError( $args );
			}

			$controllerInstance = new $controller( $this );

			return $controllerInstance->handleRequest( $args );
		}

		throw new RequestException(
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

}
