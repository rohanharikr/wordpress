<?php
/**
 * Plugin Admin settings page class.
 *
 * @package   OpenID_Connect_Generic
 * @category  Settings
 * @author    Jonathan Daggerhart <jonathan@daggerhart.com>
 * @copyright 2015-2020 daggerhart
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 */

/**
 * OpenID_Connect_Generic_Settings_Page class.
 *
 * Admin settings page.
 *
 * @package OpenID_Connect_Generic
 * @category  Settings
 */
class OpenID_Connect_Generic_Settings_Page {

	/**
	 * Local copy of the settings provided by the base plugin.
	 *
	 * @var OpenID_Connect_Generic_Option_Settings
	 */
	private $settings;

	/**
	 * Instance of the plugin logger.
	 *
	 * @var OpenID_Connect_Generic_Option_Logger
	 */
	private $logger;

	/**
	 * The controlled list of settings & associated defined during
	 * construction for i18n reasons.
	 *
	 * @var array
	 */
	private $settings_fields = array();

	/**
	 * Options page slug.
	 *
	 * @var string
	 */
	private $options_page_name = 'openid-connect-generic-settings';

	/**
	 * Options page settings group name.
	 *
	 * @var string
	 */
	private $settings_field_group;

	/**
	 * Settings page class constructor.
	 *
	 * @param OpenID_Connect_Generic_Option_Settings $settings The plugin settings object.
	 * @param OpenID_Connect_Generic_Option_Logger   $logger   The plugin logging class object.
	 */
	function __construct( OpenID_Connect_Generic_Option_Settings $settings, OpenID_Connect_Generic_Option_Logger $logger ) {

		$this->settings             = $settings;
		$this->logger               = $logger;
		$this->settings_field_group = $this->settings->get_option_name() . '-group';

		$fields = $this->get_settings_fields();

		// Some simple pre-processing.
		foreach ( $fields as $key => &$field ) {
			$field['key']  = $key;
			$field['name'] = $this->settings->get_option_name() . '[' . $key . ']';
		}

		// Allow alterations of the fields.
		$this->settings_fields = $fields;
	}

	/**
	 * Hook the settings page into WordPress.
	 *
	 * @param OpenID_Connect_Generic_Option_Settings $settings A plugin settings object instance.
	 * @param OpenID_Connect_Generic_Option_Logger   $logger   A plugin logger object instance.
	 *
	 * @return \OpenID_Connect_Generic_Settings_Page
	 */
	static public function register( OpenID_Connect_Generic_Option_Settings $settings, OpenID_Connect_Generic_Option_Logger $logger ) {
		$settings_page = new self( $settings, $logger );

		// Add our options page the the admin menu.
		add_action( 'admin_menu', array( $settings_page, 'admin_menu' ) );

		// Register our settings.
		add_action( 'admin_init', array( $settings_page, 'admin_init' ) );

		return $settings_page;
	}

	/**
	 * Implements hook admin_menu to add our options/settings page to the
	 *  dashboard menu.
	 *
	 * @return void
	 */
	public function admin_menu() {
		add_options_page(
			__( 'OpenID Connect - Generic Client' ),
			__( 'OpenID Connect Client' ),
			'manage_options',
			$this->options_page_name,
			array( $this, 'settings_page' )
		);
	}

	/**
	 * Implements hook admin_init to register our settings.
	 *
	 * @return void
	 */
	public function admin_init() {
		register_setting(
			$this->settings_field_group,
			$this->settings->get_option_name(),
			array(
				$this,
				'sanitize_settings',
			)
		);

		add_settings_section(
			'client_settings',
			__( 'Client Settings' ),
			array( $this, 'client_settings_description' ),
			$this->options_page_name
		);

		add_settings_section(
			'user_settings',
			__( 'WordPress User Settings' ),
			array( $this, 'user_settings_description' ),
			$this->options_page_name
		);

		add_settings_section(
			'authorization_settings',
			__( 'Authorization Settings' ),
			array( $this, 'authorization_settings_description' ),
			$this->options_page_name
		);

		add_settings_section(
			'log_settings',
			__( 'Log Settings' ),
			array( $this, 'log_settings_description' ),
			$this->options_page_name
		);

		// Preprocess fields and add them to the page.
		foreach ( $this->settings_fields as $key => $field ) {
			// Make sure each key exists in the settings array.
			if ( ! isset( $this->settings->{ $key } ) ) {
				$this->settings->{ $key } = null;
			}

			// Determine appropriate output callback.
			switch ( $field['type'] ) {
				case 'checkbox':
					$callback = 'do_checkbox';
					break;

				case 'select':
					$callback = 'do_select';
					break;

				case 'text':
				default:
					$callback = 'do_text_field';
					break;
			}

			// Add the field.
			add_settings_field(
				$key,
				$field['title'],
				array( $this, $callback ),
				$this->options_page_name,
				$field['section'],
				$field
			);
		}
	}

	/**
	 * Get the plugin settings fields definition.
	 *
	 * @return array
	 */
	private function get_settings_fields() {

		/**
		 * Simple settings fields have:
		 *
		 * - title
		 * - description
		 * - type ( checkbox | text | select )
		 * - section - settings/option page section ( client_settings | authorization_settings )
		 * - example (optional example will appear beneath description and be wrapped in <code>)
		 */
		$fields = array(
			'login_type'        => array(
				'title'       => __( 'Login Type' ),
				'description' => __( 'Select how the client (login form) should provide login options.' ),
				'type'        => 'select',
				'options'     => array(
					'button' => __( 'OpenID Connect button on login form' ),
					'auto'   => __( 'Auto Login - SSO' ),
				),
				'section'     => 'client_settings',
			),
			'client_id'         => array(
				'title'       => __( 'Client ID' ),
				'description' => __( 'The ID this client will be recognized as when connecting the to Identity provider server.' ),
				'example'     => 'my-wordpress-client-id',
				'type'        => 'text',
				'section'     => 'client_settings',
			),
			'client_secret'     => array(
				'title'       => __( 'Client Secret Key' ),
				'description' => __( 'Arbitrary secret key the server expects from this client. Can be anything, but should be very unique.' ),
				'type'        => 'text',
				'section'     => 'client_settings',
			),
			'scope'             => array(
				'title'       => __( 'OpenID Scope' ),
				'description' => __( 'Space separated list of scopes this client should access.' ),
				'example'     => 'email profile openid offline_access',
				'type'        => 'text',
				'section'     => 'client_settings',
			),
			'endpoint_login'    => array(
				'title'       => __( 'Login Endpoint URL' ),
				'description' => __( 'Identify provider authorization endpoint.' ),
				'example'     => 'https://example.com/oauth2/authorize',
				'type'        => 'text',
				'section'     => 'client_settings',
			),
			'endpoint_userinfo' => array(
				'title'       => __( 'Userinfo Endpoint URL' ),
				'description' => __( 'Identify provider User information endpoint.' ),
				'example'     => 'https://example.com/oauth2/UserInfo',
				'type'        => 'text',
				'section'     => 'client_settings',
			),
			'endpoint_token'    => array(
				'title'       => __( 'Token Validation Endpoint URL' ),
				'description' => __( 'Identify provider token endpoint.' ),
				'example'     => 'https://example.com/oauth2/token',
				'type'        => 'text',
				'section'     => 'client_settings',
			),
			'endpoint_end_session'    => array(
				'title'       => __( 'End Session Endpoint URL' ),
				'description' => __( 'Identify provider logout endpoint.' ),
				'example'     => 'https://example.com/oauth2/logout',
				'type'        => 'text',
				'section'     => 'client_settings',
			),
			'identity_key'     => array(
				'title'       => __( 'Identity Key' ),
				'description' => __( 'Where in the user claim array to find the user\'s identification data. Possible standard values: preferred_username, name, or sub. If you\'re having trouble, use "sub".' ),
				'example'     => 'preferred_username',
				'type'        => 'text',
				'section'     => 'client_settings',
			),
			'no_sslverify'      => array(
				'title'       => __( 'Disable SSL Verify' ),
				'description' => __( 'Do not require SSL verification during authorization. The OAuth extension uses curl to make the request. By default CURL will generally verify the SSL certificate to see if its valid an issued by an accepted CA. This setting disabled that verification.<br><strong>Not recommended for production sites.</strong>' ),
				'type'        => 'checkbox',
				'section'     => 'client_settings',
			),
			'http_request_timeout'      => array(
				'title'       => __( 'HTTP Request Timeout' ),
				'description' => __( 'Set the timeout for requests made to the IDP. Default value is 5.' ),
				'example'     => 30,
				'type'        => 'text',
				'section'     => 'client_settings',
			),
			'enforce_privacy'   => array(
				'title'       => __( 'Enforce Privacy' ),
				'description' => __( 'Require users be logged in to see the site.' ),
				'type'        => 'checkbox',
				'section'     => 'authorization_settings',
			),
			'alternate_redirect_uri'   => array(
				'title'       => __( 'Alternate Redirect URI' ),
				'description' => __( 'Provide an alternative redirect route. Useful if your server is causing issues with the default admin-ajax method. You must flush rewrite rules after changing this setting. This can be done by saving the Permalinks settings page.' ),
				'type'        => 'checkbox',
				'section'     => 'authorization_settings',
			),
			'nickname_key'     => array(
				'title'       => __( 'Nickname Key' ),
				'description' => __( 'Where in the user claim array to find the user\'s nickname. Possible standard values: preferred_username, name, or sub.' ),
				'example'     => 'preferred_username',
				'type'        => 'text',
				'section'     => 'client_settings',
			),
			'email_format'     => array(
				'title'       => __( 'Email Formatting' ),
				'description' => __( 'String from which the user\'s email address is built. Specify "{email}" as long as the user claim contains an email claim.' ),
				'example'     => '{email}',
				'type'        => 'text',
				'section'     => 'client_settings',
			),
			'displayname_format'     => array(
				'title'       => __( 'Display Name Formatting' ),
				'description' => __( 'String from which the user\'s display name is built.' ),
				'example'     => '{given_name} {family_name}',
				'type'        => 'text',
				'section'     => 'client_settings',
			),
			'identify_with_username'     => array(
				'title'       => __( 'Identify with User Name' ),
				'description' => __( 'If checked, the user\'s identity will be determined by the user name instead of the email address.' ),
				'type'        => 'checkbox',
				'section'     => 'client_settings',
			),
			'state_time_limit'     => array(
				'title'       => __( 'State time limit' ),
				'description' => __( 'State valid time in seconds. Defaults to 180' ),
				'type'        => 'number',
				'section'     => 'client_settings',
			),
			'token_refresh_enable'   => array(
				'title'       => __( 'Enable Refresh Token' ),
				'description' => __( 'If checked, support refresh tokens used to obtain access tokens from supported IDPs.' ),
				'type'        => 'checkbox',
				'section'     => 'client_settings',
			),
			'link_existing_users'   => array(
				'title'       => __( 'Link Existing Users' ),
				'description' => __( 'If a WordPress account already exists with the same identity as a newly-authenticated user over OpenID Connect, login as that user instead of generating an error.' ),
				'type'        => 'checkbox',
				'section'     => 'user_settings',
			),
			'create_if_does_not_exist'   => array(
				'title'       => __( 'Create user if does not exist' ),
				'description' => __( 'If the user identity is not link to an existing Wordpress user, it is created. If this setting is not enabled and if the user authenticates with an account which is not link to an existing Wordpress user then the authentication failed' ),
				'type'        => 'checkbox',
				'section'     => 'user_settings',
			),
			'redirect_user_back'   => array(
				'title'       => __( 'Redirect Back to Origin Page' ),
				'description' => __( 'After a successful OpenID Connect authentication, this will redirect the user back to the page on which they clicked the OpenID Connect login button. This will cause the login process to proceed in a traditional WordPress fashion. For example, users logging in through the default wp-login.php page would end up on the WordPress Dashboard and users logging in through the WooCommerce "My Account" page would end up on their account page.' ),
				'type'        => 'checkbox',
				'section'     => 'user_settings',
			),
			'redirect_on_logout'   => array(
				'title'       => __( 'Redirect to the login screen session is expired' ),
				'description' => __( 'When enabled, this will automatically redirect the user back to the WordPress login page if their access token has expired.' ),
				'type'        => 'checkbox',
				'section'     => 'user_settings',
			),
			'enable_logging'    => array(
				'title'       => __( 'Enable Logging' ),
				'description' => __( 'Very simple log messages for debugging purposes.' ),
				'type'        => 'checkbox',
				'section'     => 'log_settings',
			),
			'log_limit'         => array(
				'title'       => __( 'Log Limit' ),
				'description' => __( 'Number of items to keep in the log. These logs are stored as an option in the database, so space is limited.' ),
				'type'        => 'number',
				'section'     => 'log_settings',
			),
		);

		return apply_filters( 'openid-connect-generic-settings-fields', $fields );

	}

	/**
	 * Sanitization callback for settings/option page.
	 *
	 * @param array $input The submitted settings values.
	 *
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$options = array();

		// Loop through settings fields to control what we're saving.
		foreach ( $this->settings_fields as $key => $field ) {
			if ( isset( $input[ $key ] ) ) {
				$options[ $key ] = sanitize_text_field( trim( $input[ $key ] ) );
			} else {
				$options[ $key ] = '';
			}
		}

		return $options;
	}

	/**
	 * Output the options/settings page.
	 *
	 * @return void
	 */
	public function settings_page() {
		$redirect_uri = admin_url( 'admin-ajax.php?action=openid-connect-authorize' );

		if ( $this->settings->alternate_redirect_uri ) {
			$redirect_uri = site_url( '/openid-connect-authorize' );
		}
		?>
		<div class="wrap">
			<h2><?php print esc_html( get_admin_page_title() ); ?></h2>

			<form method="post" action="options.php">
				<?php
				settings_fields( $this->settings_field_group );
				do_settings_sections( $this->options_page_name );
				submit_button();

				// Simple debug to view settings array.
				if ( isset( $_GET['debug'] ) ) {
					var_dump( $this->settings->get_values() );
				}
				?>
			</form>

			<h4><?php _e( 'Notes' ); ?></h4>

			<p class="description">
				<strong><?php _e( 'Redirect URI' ); ?></strong>
				<code><?php print $redirect_uri; ?></code>
			</p>
			<p class="description">
				<strong><?php _e( 'Login Button Shortcode' ); ?></strong>
				<code>[openid_connect_generic_login_button]</code>
			</p>
			<p class="description">
				<strong><?php _e( 'Authentication URL Shortcode' ); ?></strong>
				<code>[openid_connect_generic_auth_url]</code>
			</p>

			<?php if ( $this->settings->enable_logging ) { ?>
				<h2><?php _e( 'Logs' ); ?></h2>
				<div id="logger-table-wrapper">
					<?php print $this->logger->get_logs_table(); ?>
				</div>

			<?php } ?>
		</div>
		<?php
	}

	/**
	 * Output a standard text field.
	 *
	 * @param array $field The settings field definition array.
	 *
	 * @return void
	 */
	public function do_text_field( $field ) {
		?>
		<input type="<?php print esc_attr( $field['type'] ); ?>"
			   id="<?php print esc_attr( $field['key'] ); ?>"
			   class="large-text"
			   name="<?php print esc_attr( $field['name'] ); ?>"
			   value="<?php print esc_attr( $this->settings->{ $field['key'] } ); ?>">
		<?php
		$this->do_field_description( $field );
	}

	/**
	 * Output a checkbox for a boolean setting.
	 *  - hidden field is default value so we don't have to check isset() on save.
	 *
	 * @param array $field The settings field definition array.
	 *
	 * @return void
	 */
	public function do_checkbox( $field ) {
		?>
		<input type="hidden" name="<?php print esc_attr( $field['name'] ); ?>" value="0">
		<input type="checkbox"
			   id="<?php print esc_attr( $field['key'] ); ?>"
			   name="<?php print esc_attr( $field['name'] ); ?>"
			   value="1"
			<?php checked( $this->settings->{ $field['key'] }, 1 ); ?>>
		<?php
		$this->do_field_description( $field );
	}

	/**
	 * Output a select control.
	 *
	 * @param array $field The settings field definition array.
	 *
	 * @return void
	 */
	function do_select( $field ) {
		$current_value = isset( $this->settings->{ $field['key'] } ) ? $this->settings->{ $field['key'] } : '';
		?>
		<select name="<?php print esc_attr( $field['name'] ); ?>">
			<?php foreach ( $field['options'] as $value => $text ) : ?>
				<option value="<?php print esc_attr( $value ); ?>" <?php selected( $value, $current_value ); ?>><?php print esc_html( $text ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
		$this->do_field_description( $field );
	}

	/**
	 * Output the field description, and example if present.
	 *
	 * @param array $field The settings field definition array.
	 *
	 * @return void
	 */
	public function do_field_description( $field ) {
		?>
		<p class="description">
			<?php print $field['description']; ?>
			<?php if ( isset( $field['example'] ) ) : ?>
				<br/><strong><?php _e( 'Example' ); ?>: </strong>
				<code><?php print $field['example']; ?></code>
			<?php endif; ?>
		</p>
		<?php
	}

	/**
	 * Output the 'Client Settings' plugin setting section description.
	 *
	 * @return void
	 */
	public function client_settings_description() {
		_e( 'Enter your OpenID Connect identity provider settings' );
	}

	/**
	 * Output the 'WordPress User Settings' plugin setting section description.
	 *
	 * @return void
	 */
	public function user_settings_description() {
		_e( 'Modify the interaction between OpenID Connect and WordPress users' );
	}

	/**
	 * Output the 'Authorization Settings' plugin setting section description.
	 *
	 * @return void
	 */
	public function authorization_settings_description() {
		_e( 'Control the authorization mechanics of the site' );
	}

	/**
	 * Output the 'Log Settings' plugin setting section description.
	 *
	 * @return void
	 */
	public function log_settings_description() {
		_e( 'Log information about login attempts through OpenID Connect Generic' );
	}
}
