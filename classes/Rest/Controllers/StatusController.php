<?php
/**
 * Status REST controller.
 *
 * @package Jetpack_CRM_REST_API_Improved
 */

namespace Jetpack_CRM_REST_API_Improved\Rest\Controllers;

use Jetpack_CRM_REST_API_Improved\Rest\BaseController;
use Jetpack_CRM_REST_API_Improved\Rest\Permissions;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides /status and /me endpoints.
 */
class StatusController extends BaseController {

	/**
	 * Plugin instance.
	 *
	 * @var \Jetpack_CRM_REST_API_Improved\Plugin|null
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param \Jetpack_CRM_REST_API_Improved\Plugin|null $plugin Plugin instance.
	 */
	public function __construct( $plugin = null ) {
		$this->plugin = $plugin;
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_status' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/me',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_me' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);
	}

	/**
	 * Permission check for the status endpoints.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function permissions_check( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		if ( ! is_user_logged_in() ) {
			return $this->error_unauthorized();
		}

		if ( ! Permissions::can( 'status', 'read' ) ) {
			return $this->error_forbidden();
		}

		return true;
	}

	/**
	 * API status.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_status( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$crm_version = defined( 'ZEROBSCRM_PATH' ) && class_exists( '\ZeroBSCRM' ) ? \ZeroBSCRM::VERSION : null;

		return rest_ensure_response(
			array(
				'status'      => 'ok',
				'message'     => __( 'Jetpack CRM REST API Improved is available.', 'jetpack-crm-rest-api-improved' ),
				'api_version' => 'v1',
				'crm_version' => $crm_version,
				'plugin'      => defined( 'JPCRM_IMPROVED_VERSION' ) ? JPCRM_IMPROVED_VERSION : null,
			)
		);
	}

	/**
	 * Current user and granted CRM permissions.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_me( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$user = wp_get_current_user();

		$resources = array();

		foreach ( array_keys( Permissions::MAP ) as $resource ) {
			$resources[ $resource ] = array(
				'read'   => Permissions::can( $resource, 'read' ),
				'create' => Permissions::can( $resource, 'write' ),
				'update' => Permissions::can( $resource, 'write' ),
				'delete' => Permissions::can( $resource, 'delete' ),
			);
		}

		return rest_ensure_response(
			array(
				'id'           => (int) $user->ID,
				'login'        => $user->user_login,
				'display_name' => $user->display_name,
				'capabilities' => $resources,
			)
		);
	}
}
