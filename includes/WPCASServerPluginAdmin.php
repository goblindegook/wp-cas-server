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
         * 
         * Triggered by the `admin_init` action.
         * 
         * @uses add_action()
         * 
         * @SuppressWarnings(CamelCaseMethodName)
         */
        public function admin_init () {
            $this->savePermalinks();
            $this->addSettingsFields();

            add_action( 'admin_notices', array( $this, 'admin_notices' ) );
        }

        /**
         * Presents admin notices.
         * 
         * Triggered by the `admin_notices` action.
         * 
         * @uses current_user_can()
         * @uses is_ssl()
         * 
         * @uses ::adminNoticeNoSSL()
         * 
         * @SuppressWarnings(CamelCaseMethodName)
         */
        public function admin_notices () {
            if (!is_ssl() && current_user_can( 'install_plugins' )) {
                $this->adminNoticeNoSSL();
            }
        }

        /**
         * Nags the user with an administration notice explaining that the plugin will only
         * work if HTTP
         */
        protected function adminNoticeNoSSL () {
            ?>
            <div class="update-nag">
                <?php _e( 'Cassava CAS Server requires that this site be configured for HTTPS. For more information, contact your system administrator or hosting provider.', 'wp-cas-server' ); ?>
            </div>
            <?php
        }

        /**
         * Updates the CAS server endpoint when saving permalinks.
         * 
         * @uses is_admin()
         * @uses sanitize_text_field()
         */
        protected function savePermalinks () {
            if (!is_admin()) return;

            if (false
                || isset( $_POST['permalink_structure'] )
                || isset( $_POST['category_base'] )
                || isset( $_POST['cas_server_endpoint_slug'] )
            ) {
                WPCASServerPlugin::setOption( 'endpoint_slug', trim( sanitize_text_field( $_POST['cas_server_endpoint_slug'] ) ) );
            }
        }

        /**
         * Register plugin settings fields.
         * 
         * @uses add_settings_field()
         */
        protected function addSettingsFields () {

            add_settings_field(
                'cas_server_endpoint_slug',
                __( 'CAS server base', 'wp-cas-server' ),
                array( $this, 'permalinksEndpointSlugField' ),
                'permalink',
                'optional'
            );

        }

        /**
         * Show the configuration field for the CAS endpoint.
         * 
         * @uses esc_attr()
         */
        public function permalinksEndpointSlugField () {
            $endpoint = WPCASServerPlugin::getOption( 'endpoint_slug' );
            ?>
            <input name="cas_server_endpoint_slug" type="text" class="regular-text code" value="<?php if ( isset( $endpoint ) ) echo esc_attr( $endpoint ); ?>" placeholder="<?php echo WPCASServerPlugin::ENDPOINT_SLUG ?>" />
            <?php
        }

    }

} // !class_exists( 'WPCASServerPluginAdmin' )
