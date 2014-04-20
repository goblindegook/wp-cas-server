<?php
/**
 * @package WPCASServerPlugin
 * @subpackage WPCASServer
 */

/**
 * CAS server class interface definition.
 */
interface ICASServer {
	
    const CAS_NS                = 'http://www.yale.edu/tp/cas';

    const ERROR_INTERNAL_ERROR  = 'INTERNAL_ERROR';
    const ERROR_INVALID_REQUEST = 'INVALID_REQUEST';
    const ERROR_INVALID_SERVICE = 'INVALID_SERVICE';
    const ERROR_INVALID_TICKET  = 'INVALID_TICKET';
    const ERROR_BAD_PGT         = 'BAD_PGT';

    const TYPE_ST               = 'ST';
    const TYPE_PT               = 'PT';
    const TYPE_PGT              = 'PGT';
    const TYPE_PGTIOU           = 'PGTIOU';
    const TYPE_TGC              = 'TGC';
    const TYPE_LT               = 'LT';

    const RFC1123_DATE_FORMAT   = 'D, d M Y H:i:s T';

	public function handleRequest ( $path );

	public function login ( $args );
	public function logout ( $args );
	public function proxy ( $args );
	public function proxyValidate ( $args );
	public function serviceValidate ( $args );
	public function validate ( $args );

}