<?php
/**
 * Implements the Cassava plugin's administration interface components.
 *
 * @version 1.0.1
 * @since   1.0.0
 */

namespace Cassava;

/**
 * Plugin administration class.
 *
 * @since 1.0.0
 */
class Admin {

	/**
	 * Instantiates the admin panel object.
	 *
	 * @uses add_action()
	 */
	public function __construct () {
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
	}

	/**
	 * Initializes the admin panel and registers settings fields.
	 *
	 * Triggered by the `admin_init` action.
	 *
	 * @uses add_action()
	 */
	public function admin_init() {
		$this->savePermalinks();
		$this->addSettings();

		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	/**
	 * Register the menu entry for the plugin's settings page.
	 *
	 * @since 1.1.0
	 */
	public function admin_menu() {

		add_options_page(
			__( 'Cassava CAS Server', 'wp-cas-server' ),
			__( 'Cassava CAS Server', 'wp-cas-server' ),
			'manage_options',
			Plugin::SLUG,
			array( $this, 'pageSettings' )
		);

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
	public function admin_notices() {
		if ( ! is_ssl() && current_user_can( 'install_plugins' ) ) {
			$this->adminNoticeNoSSL();
		}
	}

	/**
	 * Nags the user with an administration notice explaining that the plugin will only
	 * work if HTTP
	 */
	protected function adminNoticeNoSSL() {
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
	protected function savePermalinks() {
		if ( ! is_admin() ) {
			return;
		}

		$option = Options::KEY . '_endpoint_slug';

		if ( false
			|| isset( $_POST['permalink_structure'] )
			|| isset( $_POST['category_base'] )
			|| isset( $_POST[ $option ] )
		) {
			Options::set( 'endpoint_slug', trim( sanitize_text_field( $_POST[ $option ] ) ) );
		}
	}

	/**
	 * Register plugin settings.
	 *
	 * @uses add_settings_field()
	 * @uses add_settings_section()
	 * @uses register_setting()
	 *
	 * @since   1.0.0
	 */
	protected function addSettings() {

		register_setting(
			Plugin::SLUG,
			Options::KEY,
			array( $this, 'validateSettings' )
		);

		// Default plugin settings:

		add_settings_section( 'default', '', false, Plugin::SLUG );

		add_settings_field(
			'attributes',
			__( 'User Attributes To Return', 'wp-cas-server' ),
			array( $this, 'fieldUserAttributes' ),
			Plugin::SLUG
		);

		// Permalink settings:

		add_settings_field(
			Options::KEY . '_endpoint_slug',
			__( 'CAS server base', 'wp-cas-server' ),
			array( $this, 'fieldPermalinksEndpointSlug' ),
			'permalink',
			'optional'
		);

	}

	/**
	 * Validates and updates CAS server plugin settings.
	 *
	 * @param  array $input Unvalidated input arguments when settings are updated.
	 *
	 * @return array        Validated plugin settings to be saved in the database.
	 *
	 * @since 1.1.0
	 */
	public function validateSettings( $input ) {
		$options = Options::getAll();

		$options['attributes'] = (array) $input['attributes'];

		return $options;
	}

	/**
	 * Display the configuration field for the CAS endpoint.
	 *
	 * @uses esc_attr()
	 *
	 * @since 1.0.0
	 */
	public function fieldPermalinksEndpointSlug() {
		$option   = Options::KEY . '_endpoint_slug';
		$endpoint = Options::get( 'endpoint_slug' );
		?>
		<input id="<?php echo $option; ?>" name="<?php echo $option; ?>"
			type="text" class="regular-text code"
			value="<?php if ( isset( $endpoint ) ) echo esc_attr( $endpoint ); ?>"
			placeholder="<?php echo Plugin::ENDPOINT_SLUG; ?>" />
		<?php
	}

	/**
	 * Displays the CAS server settings page in the dashboard.
	 *
	 * @uses _e()
	 * @uses do_settings_sections()
	 * @uses settings_fields()
	 * @uses submit_button()
	 *
	 * @since 1.1.0
	 */
	public function pageSettings() {
		?>
		<div class="wrap">
			<h2><?php _e( 'Cassava CAS Server Settings', 'wp-cas-server' ); ?></h2>

			<p><?php _e( 'Configuration panel for the Central Authentication Service provided by this site.', 'wp-cas-server' ); ?></p>

			<form action="options.php" method="POST">
				<?php do_settings_sections( Plugin::SLUG ); ?>
				<?php settings_fields( Plugin::SLUG ); ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Display the configuration fieldset for the user attributs to return on successful
	 * requests.
	 *
	 * Checked attributes for the authenticated user will be returned on successful
	 * `/validateService` request responses inside an optional `<cas:attributes></cas:attributes>`
	 * tag.
	 *
	 * @uses _e()
	 * @uses apply_filters()
	 *
	 * @since 1.1.0
	 */
	public function fieldUserAttributes() {

		$user       = wp_get_current_user();
		$attributes = Options::get( 'attributes' );

		$attributeOptions = array(
			'first_name'   => __( 'First Name', 'wp-cas-server' ),
			'last_name'    => __( 'Last Name', 'wp-cas-server' ),
			'display_name' => __( 'Public Name', 'wp-cas-server' ),
			'user_email'   => __( 'Email', 'wp-cas-server' ),
			'user_url'     => __( 'Website', 'wp-cas-server' ),
		);

		/**
		 * Allows developers to change the list of user attributes that appear in the dashboard for
		 * an administrator to set to return on successful validation requests.
		 *
		 * Options are stored in an associative array, with user attribute slugs as array keys and
		 * option labels as array values.
		 *
		 * These settings are valid only for CAS 2.0 validation requests.
		 *
		 * @param  array $attributeOptions Attribute options an administrator can set on the dashboard.
		 *
		 * @return array                   Attribute options to display.
		 *
		 * @since 1.1.0
		 */
		$attributeOptions = apply_filters( 'cas_server_settings_user_attribute_options', $attributeOptions );
		?>

		<fieldset>
		<legend class="screen-reader-text"><?php _e( 'User Attributes', 'wp-cas-server' ) ?></legend>
			<?php foreach ($attributeOptions as $value => $label) : ?>
			<label>
				<input id="<?php echo Options::KEY . '-attribute-' . $value ?>"
				name="<?php echo Options::KEY ?>[attributes][]"
				type="checkbox" <?php if (in_array( $value, $attributes )) echo "checked" ?>
				value="<?php echo $value ?>">
				<span><?php echo $label ?></span>
				<?php if ($user->get( $value )) : ?>
				<span class="description"><?php
					printf( __( '(e.g. %s)', 'wp-cas-server' ), implode( ',', (array) $user->get( $value ) )  );
				?></span>
				<?php endif; ?>
			</label><br>
			<?php endforeach; ?>
			<p class="description"><?php _e( 'Checked attributes are disclosed on successful validation requests (CAS 2.0 only).', 'wp-cas-server' ) ?></p>
		</fieldset>
		<?php
	}
}
