<?php
/**
 * Plugin Name: Jetpack CRM REST API Improved
 * Plugin URI: https://github.com/ivannin/jetpack-crm-rest-api-improved
 * Description: Полнофункциональный стандартный WordPress REST API для всех сущностей Jetpack CRM.
 * Version: 0.8.0
 * Author: Иван Никитин
 * Author URI: https://ivannikitin.com
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Requires Plugins: zero-bs-crm
 * Text Domain: jetpack-crm-rest-api-improved
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * Network: false
 * Update URI: false
 *
 * @package Jetpack_CRM_REST_API_Improved
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'JPCRM_IMPROVED_VERSION', '0.8.0' );
define( 'JPCRM_IMPROVED_FILE', __FILE__ );
define( 'JPCRM_IMPROVED_PATH', plugin_dir_path( __FILE__ ) );

$jpcrm_improved_autoload = __DIR__ . '/vendor/autoload.php';

if ( file_exists( $jpcrm_improved_autoload ) ) {
	require_once $jpcrm_improved_autoload;
} else {
	require_once __DIR__ . '/classes/Autoloader.php';
	\Jetpack_CRM_REST_API_Improved\Autoloader::register();
}

new \Jetpack_CRM_REST_API_Improved\Plugin();
