<?php
/**
 * Login controller class.
 *
 * @version 1.2.0
 * @since 1.2.0
 */

namespace Cassava\CAS\Controller;

use Cassava\CAS;
use Cassava\Plugin;

/**
 * Implements CAS login.
 *
 * Implements the `/login` URI and determines whether to interpret the request as a credential
 * requestor or a credential acceptor.
 *
 * @since 1.2.0
 */
class LoginController extends BaseController {

	/**
	 * Handles login requests.
	 *
	 * @param array $request Request arguments.
	 *
	 * @global $_POST
	 *
	 * @uses \apply_filters()
	 */
	public function handleRequest( $request ) {
		$request = array_merge( $_POST, (array) $request );

		/**
		 * Allows developers to change the request parameters passed to a `/login` request.
		 *
		 * @param array $request HTTP request (GET, POST) parameters.
		 */
		$request = \apply_filters( 'cas_server_login_args', $request );

		if ( isset( $request['username'] ) && isset( $request['password'] ) && isset( $request['lt'] ) ) {
			$this->loginAcceptor( $request );
			return;
		}

		$this->loginRequestor( $request );
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
	 * @param array $request Request arguments.
	 *
	 * @uses \is_wp_error()
	 * @uses \sanitize_user()
	 * @uses \wp_signon()
	 * @uses \wp_verify_nonce()
	 *
	 * @todo Support for the optional warn parameter.
	 */
	private function loginAcceptor( $request = array() ) {

		$username   = \sanitize_user( $request['username'] );
		$password   = $request['password'];
		$lt         = preg_replace( '@^' . CAS\Ticket::TYPE_LT . '-@', '', $request['lt'] );
		$service    = isset( $request['service'] ) ? $request['service'] : null;

		if ( ! \wp_verify_nonce( $lt, 'lt' ) ) {
			$this->server->authRedirect( $request );
		}

		$user = \wp_signon( array(
			'user_login'    => $username,
			'user_password' => $password,
		) );

		if ( ! $user || \is_wp_error( $user ) ) {
			$this->server->authRedirect( $request );
		}

		$this->login( $user, $service );
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
	 * @param array $request Request arguments.
	 *
	 * @uses \is_user_logged_in()
	 * @uses \wp_get_current_user()
	 */
	private function loginRequestor( $request = array() ) {

		$this->server->sessionStart();

		$renew   = isset( $request['renew'] )   && 'true' === $request['renew'];
		$gateway = isset( $request['gateway'] ) && 'true' === $request['gateway'];
		$service = isset( $request['service'] ) ? $request['service'] : '';

		if ( $renew ) {
			$this->renew();
		}

		if ( ! \is_user_logged_in() ) {
			if ( $gateway && $service ) {
				$this->server->redirect( $service );
			}

			$this->server->authRedirect( $request );
		}

		$this->login( \wp_get_current_user(), $service );
	}

	/**
	 * Renews the user session.
	 *
	 * Invalidates the user session and repeats the login request without the
	 * `renew` parameter.
	 *
	 * @uses \is_ssl()
	 * @uses \remove_query_arg()
	 * @uses \wp_logout()
	 */
	private function renew() {
		\wp_logout();

		$schema = \is_ssl() ? 'https://' : 'http://';
		$url    = $schema . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		$url    = \remove_query_arg( 'renew', $url );

		$this->server->redirect( $url );
	}

	/**
	 * Logs the user in.
	 *
	 * @param \WP_User $user    WordPress user to authenticate.
	 * @param string   $service URI for the service requesting user authentication.
	 *
	 * @uses \add_query_arg()
	 * @uses \apply_filters()
	 * @uses \esc_url_raw()
	 * @uses \home_url()
	 */
	private function login( $user, $service = '' ) {
		$ticket = new CAS\Ticket( CAS\Ticket::TYPE_ST, $user, $service );

		$service = empty( $service ) ? \home_url() : \esc_url_raw( $service );
		$service = \add_query_arg( 'ticket', (string) $ticket, $service );

		/**
		 * Filters the redirect URI for the service requesting user authentication.
		 *
		 * @param  string  $service Service URI requesting user authentication.
		 * @param  WP_User $user    Logged in WordPress user.
		 */
		$service = \apply_filters( 'cas_server_redirect_service', $service, $user );

		$this->server->redirect( $service );
	}
}
