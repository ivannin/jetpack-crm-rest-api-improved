<?php
/**
 * MCP HTTP endpoint.
 *
 * @package Jetpack_CRM_REST_API_Improved
 */

namespace Jetpack_CRM_REST_API_Improved\Mcp;

use Jetpack_CRM_REST_API_Improved\Rest\Permissions;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and serves the MCP endpoint as a WordPress REST route.
 */
class HttpEndpoint {

	const REST_NAMESPACE = 'jpcrm-improved/v1';

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
	 * Register the MCP route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/mcp',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'method_not_allowed' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);
	}

	/**
	 * Require an authenticated CRM backend user.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function check_permission( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'jpcrm_rest_unauthorized',
				__( 'Authentication is required to access this resource.', 'jetpack-crm-rest-api-improved' ),
				array( 'status' => 401 )
			);
		}

		if ( ! Permissions::can( 'status', 'read' ) ) {
			return new WP_Error(
				'jpcrm_rest_forbidden',
				__( 'Sorry, you are not allowed to access this resource.', 'jetpack-crm-rest-api-improved' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Handle a POST MCP request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function handle( $request ) {
		$server = new Server( $this->plugin, new ToolRegistry( $this->plugin ) );

		return $server->handle( $request );
	}

	/**
	 * Reject GET requests (no server-initiated stream).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_Error
	 */
	public function method_not_allowed( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		return new WP_Error(
			'jpcrm_rest_method_not_allowed',
			__( 'The MCP endpoint accepts POST requests only.', 'jetpack-crm-rest-api-improved' ),
			array( 'status' => 405 )
		);
	}
}
