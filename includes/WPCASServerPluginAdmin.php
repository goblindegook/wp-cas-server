<?php
/**
 * Implements the Cassava plugin's administration interface components.
 * 
 * @package \WPCASServerPlugin\Admin
 * @version 1.0.1
 */

if (!defined( 'ABSPATH' )) exit; // No monkey business.

if (!class_exists( 'WPCASServerPluginAdmin' )) {

    /**
     * Plugin administration class.
     * 
     * @since 1.0.0
     */
    class WPCASServerPluginAdmin {

        /**
         * Instantiates the admin panel object.
         * 
         * @uses add_action()
         */
        public function __construct () {
            add_action( 'admin_init', array( $this, 'admin_init' ) );
        }

        /**
         * Initializes the admin panel and registers settings fields.
         */
        public function admin_init () {
            $this->add_settings_fields();
            $this->save_permalinks();
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
         * 
         * @uses esc_attr()
         */
        public function cas_server_endpoint_slug_field () {
            $endpoint = WPCASServerPlugin::get_option( 'endpoint_slug' );
            ?>
            <input name="cas_server_endpoint_slug" type="text" class="regular-text code" value="<?php if ( isset( $endpoint ) ) echo esc_attr( $endpoint ); ?>" placeholder="<?php echo WPCASServerPlugin::ENDPOINT_SLUG ?>" />
            <?php
        }

        /**
         * Updates the CAS server endpoint when saving permalinks.
         * 
         * @uses is_admin()
         * @uses sanitize_text_field()
         */
        public function save_permalinks () {
            if (!is_admin()) return;

            if (false
                || isset( $_POST['permalink_structure'] )
                || isset( $_POST['category_base'] )
                || isset( $_POST['cas_server_endpoint_slug'] )
            ) {
                WPCASServerPlugin::set_option( 'endpoint_slug', trim( sanitize_text_field( $_POST['cas_server_endpoint_slug'] ) ) );
            }
        }

    }

} // !class_exists( 'WPCASServerPluginAdmin' )
