<?php
/**
 * OpenAPI document endpoint.
 *
 * @package Jetpack_CRM_REST_API_Improved
 */

namespace Jetpack_CRM_REST_API_Improved\Rest\Controllers;

use Jetpack_CRM_REST_API_Improved\Rest\BaseController;
use Jetpack_CRM_REST_API_Improved\Rest\OpenApiGenerator;
use Jetpack_CRM_REST_API_Improved\Rest\Permissions;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serves the generated OpenAPI 3 document.
 */
class OpenApiController extends BaseController {

	/**
	 * REST base.
	 *
	 * @var string
	 */
	protected $rest_base = 'openapi.json';

	/**
	 * Register the route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_spec' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);
	}

	/**
	 * Permission check (private by default, filterable).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function permissions_check( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		if ( apply_filters( 'jpcrm_improved_openapi_public', false ) ) {
			return true;
		}

		if ( ! is_user_logged_in() ) {
			return $this->error_unauthorized();
		}

		if ( ! Permissions::can( 'status', 'read' ) ) {
			return $this->error_forbidden();
		}

		return true;
	}

	/**
	 * Return the OpenAPI document.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_spec( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		return rest_ensure_response( OpenApiGenerator::generate() );
	}
}
