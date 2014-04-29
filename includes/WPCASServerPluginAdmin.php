<?php
/**
 * Implements the WordPress CAS Server plugin's administration interface components.
 * 
 * @package     WPCASServerPlugin
 * @subpackage  WPCASServerPluginAdmin
 */

if (!defined( 'ABSPATH' )) exit; // No monkey business.

if (!class_exists( 'WPCASServerPluginAdmin' )) :

/**
 * WC_Admin_Permalink_Settings Class
 */
class WPCASServerPluginAdmin {

    /**
     * Hooks callbacks to admin actions and filters.
     * 
     * @uses add_action()
     */
    public function __construct() {
        add_action( 'admin_init', array( $this, 'add_settings_fields' ) );
    }

    /**
     * Register plugin settings fields.
     * 
     * @uses add_settings_field()
     */
    public function add_settings_fields () {

        add_settings_field(
            'cas_server_endpoint_slug',
            __( 'CAS server base', 'wp-cas-server' ),
            array( $this, 'cas_server_endpoint_slug_field' ),
            'permalink',
            'optional'
        );

    }

    /**
     * Show the configuration field for the CAS endpoint.
     */
    public function cas_server_endpoint_slug_field() {
        $endpoint = WPCASServerPlugin::get_option( 'path' );
        ?>
        <input name="cas_server_endpoint_slug" type="text" class="regular-text code" value="<?php if ( isset( $endpoint ) ) echo esc_attr( $endpoint ); ?>" placeholder="<?php _ex( 'wp-cas', 'slug', 'wp-cas-server' ) ?>" />
        <?php
    }

}

endif; // !class_exists( 'WPCASServerPluginAdmin' )