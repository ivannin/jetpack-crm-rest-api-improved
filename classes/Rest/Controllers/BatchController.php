<?php
namespace Jetpack_CRM_REST_API_Improved\Rest\Controllers;
use Jetpack_CRM_REST_API_Improved\Rest\BaseController;
use WP_REST_Request;
use WP_REST_Server;
if ( ! defined( 'ABSPATH' ) ) { exit; }

class BatchController extends BaseController {
	protected $rest_base = 'batch';

	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'run_batch' ),
				'permission_callback' => array( $this, 'perms_check' ),
			),
		) );
	}

	public function perms_check( $request ) {
		if ( ! is_user_logged_in() ) { return $this->error_unauthorized(); }
		return true;
	}

	public function run_batch( $request ) {
		$params  = $request->get_json_params();
		$requests = isset( $params['requests'] ) && is_array( $params['requests'] ) ? $params['requests'] : array();

		$max = (int) apply_filters( 'jpcrm_improved_rest_batch_max_requests', 25 );
		if ( count( $requests ) > $max ) {
			return $this->error( 'jpcrm_rest_invalid_param', __( 'Too many batch requests.', 'jetpack-crm-rest-api-improved' ), 400 );
		}

		$results = array();
		foreach ( $requests as $sub ) {
			$method = isset( $sub['method'] ) ? strtoupper( sanitize_text_field( $sub['method'] ) ) : 'GET';
			$path   = isset( $sub['path'] ) ? sanitize_text_field( $sub['path'] ) : '';
			$body   = isset( $sub['body'] ) ? $sub['body'] : array();

			if ( empty( $path ) ) {
				$results[] = array( 'status' => 400, 'body' => array( 'error' => 'Missing path' ) );
				continue;
			}

			$req = new WP_REST_Request( $method, $path );
			if ( ! empty( $body ) && is_array( $body ) ) {
				$req->set_body_params( $body );
			}
			$req->set_header( 'Content-Type', 'application/json' );

			$response = rest_do_request( $req );
			$results[] = array(
				'status' => $response->get_status(),
				'body'   => $response->get_data(),
			);
		}

		return rest_ensure_response( $results );
	}
}
