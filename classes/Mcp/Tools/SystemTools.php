<?php
/**
 * System MCP tools: status, me, entities.
 *
 * @package Jetpack_CRM_REST_API_Improved
 */

namespace Jetpack_CRM_REST_API_Improved\Mcp\Tools;

use Jetpack_CRM_REST_API_Improved\Mcp\Dispatcher;
use Jetpack_CRM_REST_API_Improved\Mcp\EntityRegistry;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handlers for read-only system tools.
 */
class SystemTools {

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
	 * Return API status.
	 *
	 * @param array $args Tool arguments.
	 * @return array|WP_Error
	 */
	public function status( $args = array() ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		return Dispatcher::request( 'GET', '/status' );
	}

	/**
	 * Return the current user and capabilities.
	 *
	 * @param array $args Tool arguments.
	 * @return array|WP_Error
	 */
	public function me( $args = array() ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		return Dispatcher::request( 'GET', '/me' );
	}

	/**
	 * Return the entity catalog (optionally a single entity).
	 *
	 * @param array $args Tool arguments.
	 * @return array|WP_Error
	 */
	public function entities( $args = array() ) {
		$catalog = EntityRegistry::for_user();
		$name    = isset( $args['entity'] ) ? sanitize_text_field( $args['entity'] ) : '';

		if ( '' !== $name ) {
			if ( ! isset( $catalog[ $name ] ) ) {
				return new WP_Error(
					'jpcrm_mcp_unknown_entity',
					sprintf(
						/* translators: %s: entity name */
						__( 'Неизвестная или недоступная сущность: %s', 'jetpack-crm-rest-api-improved' ),
						$name
					),
					array( 'status' => 404 )
				);
			}

			return $catalog[ $name ];
		}

		return array( 'entities' => array_values( $catalog ) );
	}
}
