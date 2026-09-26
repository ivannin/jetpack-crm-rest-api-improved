<?php
/**
 * Main plugin class.
 *
 * @package Jetpack_CRM_REST_API_Improved
 */

namespace Jetpack_CRM_REST_API_Improved;

use Jetpack_CRM_REST_API_Improved\Admin\SettingsPage;
use Jetpack_CRM_REST_API_Improved\Mcp\HttpEndpoint;
use Jetpack_CRM_REST_API_Improved\Rest\Loader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bootstraps the extension and wires it into Jetpack CRM.
 */
class Plugin {

	/**
	 * Extension slug used by Jetpack CRM.
	 *
	 * @var string
	 */
	const EXTENSION_SLUG = 'rest_api_improved';

	/**
	 * Whether the extension has already been registered with Jetpack CRM.
	 *
	 * @var bool
	 */
	private $registration_attempted = false;

	/**
	 * REST loader instance.
	 *
	 * @var Loader|null
	 */
	private $loader;

	/**
	 * Settings page instance.
	 *
	 * @var SettingsPage|null
	 */
	private $settings_page;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'jpcrm_register_free_extensions', array( $this, 'register_extension' ) );
		add_action( 'plugins_loaded', array( $this, 'register_with_crm' ), 5 );
		add_action( 'init', array( $this, 'register_with_crm' ), 25 );
		add_action( 'init', array( $this, 'init_admin' ), 20 );
		add_filter( 'zbs_extensions_array', array( $this, 'add_to_extensions_list' ) );
		add_filter( 'wp_is_application_passwords_available', array( $this, 'enable_app_passwords_for_local' ) );
		add_filter( 'zbs-tools-menu', array( $this, 'add_tools_menu_item' ) );
		add_filter( 'zbs_menu_wpmenu', array( $this, 'add_wp_pages' ) );
	}

	/**
	 * Register the REST routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		if ( ! $this->is_crm_available() ) {
			return;
		}

		$this->loader = new Loader( $this );
		$this->loader->register_routes();

		if ( SettingsPage::is_enabled() ) {
			$endpoint = new HttpEndpoint( $this );
			$endpoint->register_routes();
		}
	}

	/**
	 * Initialise the admin settings page (admin only).
	 *
	 * @return void
	 */
	public function init_admin() {
		if ( ! is_admin() ) {
			return;
		}

		$this->settings_page = new SettingsPage( $this );
	}

	/**
	 * Render the settings page, instantiating it on demand.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( null === $this->settings_page ) {
			$this->settings_page = new SettingsPage( $this );
		}

		$this->settings_page->render_page();
	}

	/**
	 * Add a link to the Jetpack CRM Tools menu.
	 *
	 * @param array $menu_items Menu items.
	 * @return array
	 */
	public function add_tools_menu_item( $menu_items ) {
		if ( ! is_array( $menu_items ) ) {
			$menu_items = array();
		}

		if ( ! function_exists( 'zeroBSCRM_getAdminURL' ) ) {
			return $menu_items;
		}

		$menu_items[] = '<a href="' . esc_url( zeroBSCRM_getAdminURL( 'jpcrm-rest-api' ) ) . '" class="item"><i class="plug icon"></i> ' . esc_html__( 'REST API / MCP', 'jetpack-crm-rest-api-improved' ) . '</a>';

		return $menu_items;
	}

	/**
	 * Register the settings page in the Jetpack CRM admin menu.
	 *
	 * @param array $menu_array Menu array.
	 * @return array
	 */
	public function add_wp_pages( $menu_array = array() ) {
		if ( ! is_array( $menu_array ) ) {
			$menu_array = array();
		}

		$menu_array['jpcrm']['subitems']['jpcrm-rest-api'] = array(
			'title'      => __( 'REST API / MCP', 'jetpack-crm-rest-api-improved' ),
			'url'        => 'jpcrm-rest-api',
			'perms'      => SettingsPage::CAPABILITY,
			'order'      => 30,
			'wpposition' => 30,
			'callback'   => array( $this, 'render_settings_page' ),
			'stylefuncs' => array( 'zeroBSCRM_global_admin_styles' ),
		);

		return $menu_array;
	}

	/**
	 * Add the extension card to the Jetpack CRM extensions screen.
	 *
	 * @param array $extensions Registered extensions.
	 * @return array
	 */
	public function register_extension( $extensions ) {
		if ( ! is_array( $extensions ) ) {
			$extensions = array();
		}

		$extensions[ self::EXTENSION_SLUG ] = array(
			'name'       => __( 'REST API Improved', 'jetpack-crm-rest-api-improved' ),
			'i'          => 'api.png',
			'short_desc' => __( 'Полнофункциональный стандартный REST API для Jetpack CRM.', 'jetpack-crm-rest-api-improved' ),
		);

		return $extensions;
	}

	/**
	 * Mark the extension as installed within Jetpack CRM.
	 *
	 * @return void
	 */
	public function register_with_crm() {
		if ( ! $this->is_crm_available() ) {
			return;
		}

		if ( ! function_exists( 'jpcrm_register_external_extension' ) ) {
			return;
		}

		if ( ! $this->registration_attempted ) {
			$this->registration_attempted = true;
			jpcrm_register_external_extension( self::EXTENSION_SLUG );
		}
	}

	/**
	 * Ensure the extension is present in the installed extensions list.
	 *
	 * @param array $extensions_array Installed extensions.
	 * @return array
	 */
	public function add_to_extensions_list( $extensions_array ) {
		if ( ! is_array( $extensions_array ) ) {
			$extensions_array = array();
		}

		if ( ! in_array( self::EXTENSION_SLUG, $extensions_array, true ) ) {
			$extensions_array[] = self::EXTENSION_SLUG;
		}

		return $extensions_array;
	}

	/**
	 * Log a message when debugging is enabled.
	 *
	 * @param mixed  $message Message to log.
	 * @param string $level   Log level.
	 * @return void
	 */
	public function log( $message, $level = 'info' ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$prefix = '[' . gmdate( 'Y-m-d H:i:s' ) . '] Jetpack CRM REST API Improved - ' . $level . ' - ';

		if ( is_array( $message ) || is_object( $message ) ) {
			error_log( $prefix . print_r( $message, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		} else {
			error_log( $prefix . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Whether Jetpack CRM is loaded.
	 *
	 * @return bool
	 */
	private function is_crm_available() {
		return defined( 'ZEROBSCRM_PATH' ) && isset( $GLOBALS['zbs'] );
	}

	/**
	 * Enable Application Passwords when running in a local environment.
	 *
	 * @param bool $available Whether application passwords are available.
	 * @return bool
	 */
	public function enable_app_passwords_for_local( $available ) {
		if ( function_exists( 'wp_get_environment_type' ) && 'local' === wp_get_environment_type() ) {
			return true;
		}

		return $available;
	}
}
