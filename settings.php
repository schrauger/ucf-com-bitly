<?php
/**
 * User: stephen
 * Date: 2019-07-19
 */

class ucf_com_bitly_settings {


	const shortcode                 = 'ucf_com_bitly'; // what people type into their page
	const options_menu_slug         = 'ucf-com-bitly'; // slug in admin options menu
	const settings_section          = 'ucf_com_bitly_section'; // section within the settings page

	private $options;

	function __construct() {
		// Add the javascript to the locations page
		//add_action( 'init', array( $this, 'register_location_js_css' ) );

		add_shortcode( ucf_com_bitly::shortcode, array( $this, 'handle_shortcode'));

		if ( is_admin() ) {
			add_action('admin_menu', array( $this, 'add_plugin_page'));
			add_action('admin_init', array( $this, 'register_settings'));
		}

	}

	function add_plugin_page(){
		add_options_page(
			"UCF COM Bit.ly Settings",
			"Bit.ly Settings",
			"manage_options",
			self::shortcode,
			array($this, 'settings_page'));
	}

	function settings_page(){
		$this->options = get_option('ucf_com_bitly_options');
		?>
		<div class="wrap">
			<h1>UCF COM Bit.ly Settings</h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( "ucf-com-bitly");
				do_settings_sections( self::options_menu_slug);
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	function register_settings(){
		register_setting(
			'ucf-com-bitly',
			'ucf_com_bitly_options'
		);

		add_settings_section(
			self::settings_section,
			"Bit.ly Config",
			array( $this, 'options_header'),
			self::options_menu_slug
		);
		add_settings_field(
			'auth_token',
			'Bit.ly Auth Token (acquired from https://bitly.com/a/oauth_apps and generating a Generic Access Token)',
			array($this, 'option_auth_token'),
			self::options_menu_slug,
			self::settings_section
		);
		add_settings_field(
			'group_name',
			'Bit.ly Group Name (not required)',
			array($this, 'option_group_name'),
			self::options_menu_slug,
			self::settings_section
		);
	}

	function options_header() {
		print 'Enter your settings below:';
	}
	function option_auth_token(){
		printf(
			'<input type="text" id="auth_token" name="ucf_com_bitly_options[auth_token]" value="%s" />',
			isset( $this->options['auth_token'] ) ? esc_attr( $this->options['auth_token']) : ''
		);
	}

	function option_group_name(){
		printf(
			'<input type="text" id="group_name" name="ucf_com_bitly_options[group_name]" value="%s" />',
			isset( $this->options['group_name'] ) ? esc_attr( $this->options['group_name']) : ''
		);
	}

}

new ucf_com_bitly_settings();

?>
