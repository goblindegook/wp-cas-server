<?php
/**
 * WP CAS Server plugin loader.
 *
 * @version 1.2.3
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

$GLOBALS[ Cassava\Plugin::SLUG ] = new \Cassava\Plugin( new \Cassava\CAS\Server );
$GLOBALS[ Cassava\Plugin::SLUG ]->ready();
