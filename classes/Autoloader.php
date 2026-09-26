<?php
/**
 * PSR-4 autoloader for the plugin.
 *
 * @package Jetpack_CRM_REST_API_Improved
 */

namespace Jetpack_CRM_REST_API_Improved;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Minimal PSR-4 autoloader used when Composer is not available.
 */
class Autoloader {

	const PREFIX = 'Jetpack_CRM_REST_API_Improved\\';

	/**
	 * Register the autoloader.
	 *
	 * @return void
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Load a class file by its fully qualified name.
	 *
	 * @param string $class_name Fully qualified class name.
	 * @return void
	 */
	public static function autoload( $class_name ) {
		if ( 0 !== strpos( $class_name, self::PREFIX ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( self::PREFIX ) );
		$file     = __DIR__ . '/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
