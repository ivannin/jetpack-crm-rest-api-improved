<?php
/**
 * Quote and segment action MCP tools.
 *
 * @package Jetpack_CRM_REST_API_Improved
 */

namespace Jetpack_CRM_REST_API_Improved\Mcp\Tools;

use Jetpack_CRM_REST_API_Improved\Mcp\Dispatcher;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handlers for non-CRUD actions.
 */
class ActionTools {

	/**
	 * Plugin instance.
	 *
	 * @var object|null
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param object|null $plugin Plugin instance.
	 */
	public function __construct( $plugin = null ) {
		$this->plugin = $plugin;
	}

	/**
	 * Accept or unaccept a quote.
	 *
	 * @param array $args Tool arguments.
	 * @return array|WP_Error
	 */
	public function quote_accept( $args ) {
		$id = $this->id( $args );

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$accept = ! isset( $args['accept'] ) || (bool) $args['accept'];

		if ( ! $accept ) {
			return Dispatcher::request( 'DELETE', '/quotes/' . $id . '/accept' );
		}

		$body = array();

		if ( isset( $args['signed_by'] ) && '' !== $args['signed_by'] ) {
			$body['signed_by'] = (string) $args['signed_by'];
		}

		return Dispatcher::request( 'POST', '/quotes/' . $id . '/accept', array(), $body );
	}

	/**
	 * Recompile a segment.
	 *
	 * @param array $args Tool arguments.
	 * @return array|WP_Error
	 */
	public function segment_compile( $args ) {
		$id = $this->id( $args );

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		return Dispatcher::request( 'POST', '/segments/' . $id . '/compile' );
	}

	/**
	 * Validate and return an object id.
	 *
	 * @param array $args Tool arguments.
	 * @return int|WP_Error
	 */
	private function id( $args ) {
		if ( ! isset( $args['id'] ) || ! is_numeric( $args['id'] ) || (int) $args['id'] <= 0 ) {
			return new WP_Error(
				'jpcrm_mcp_invalid_param',
				__( 'Аргумент id обязателен и должен быть положительным числом.', 'jetpack-crm-rest-api-improved' ),
				array( 'status' => 400 )
			);
		}

		return (int) $args['id'];
	}
}
