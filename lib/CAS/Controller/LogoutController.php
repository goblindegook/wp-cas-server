<?php
/**
 * Logout controller class.
 *
 * @version 1.2.0
 * @since 1.2.0
 */

namespace Cassava\CAS\Controller;

use Cassava\CAS;

/**
 * Implements CAS logout.
 *
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
 * @since 1.2.0
 */
class LogoutController extends BaseController {

	/**
	 * Handles logout requests.
	 *
	 * @param array $request CAS request arguments.
	 *
	 * @uses \home_url()
	 */
	public function handleRequest( $request ) {
		$service = ! empty( $request['service'] ) ? $request['service'] : \home_url();
		$this->server->sessionStart();
		$this->server->sessionDestroy();
		$this->server->redirect( $service );
	}
}
