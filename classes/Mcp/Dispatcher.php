<?php
/**
 * Dispatches MCP tool calls to the plugin's own REST controllers.
 *
 * @package Jetpack_CRM_REST_API_Improved
 */

namespace Jetpack_CRM_REST_API_Improved\Mcp;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Executes internal REST requests on behalf of the current user.
 *
 * Reuses the registered controllers through `rest_do_request()` so that
 * validation, permissions and response formatting stay identical to the REST API.
 */
class Dispatcher {

	const REST_NAMESPACE = 'jpcrm-improved/v1';

	/**
	 * Perform an internal REST request and return the raw response.
	 *
	 * @param string $method HTTP method.
	 * @param string $path   Path relative to the REST namespace, e.g. `/contacts`.
	 * @param array  $query  Query parameters.
	 * @param array  $body   JSON body.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function dispatch( $method, $path, array $query = array(), array $body = array() ) {
		$route = '/' . self::REST_NAMESPACE . '/' . ltrim( (string) $path, '/' );

		$request = new WP_REST_Request( strtoupper( (string) $method ), $route );

		if ( ! empty( $query ) ) {
			$request->set_query_params( $query );
		}

		if ( ! empty( $body ) ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( wp_json_encode( $body ) );
			$request->set_body_params( $body );
		}

		return rest_do_request( $request );
	}

	/**
	 * Perform an internal REST request and return the decoded data.
	 *
	 * @param string $method HTTP method.
	 * @param string $path   Path relative to the REST namespace, e.g. `/contacts`.
	 * @param array  $query  Query parameters.
	 * @param array  $body   JSON body.
	 * @return array|WP_Error Decoded response data or error.
	 */
	public static function request( $method, $path, array $query = array(), array $body = array() ) {
		$response = self::dispatch( $method, $path, $query, $body );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( ! $response instanceof WP_REST_Response ) {
			return new WP_Error(
				'jpcrm_mcp_internal_error',
				__( 'Unexpected response from the CRM REST API.', 'jetpack-crm-rest-api-improved' ),
				array( 'status' => 500 )
			);
		}

		$status = (int) $response->get_status();
		$data   = $response->get_data();

		if ( $status >= 400 ) {
			$code    = is_array( $data ) && isset( $data['code'] ) ? $data['code'] : 'jpcrm_mcp_request_failed';
			$message = is_array( $data ) && isset( $data['message'] ) ? $data['message'] : __( 'The CRM request failed.', 'jetpack-crm-rest-api-improved' );

			return new WP_Error( $code, $message, array( 'status' => $status ) );
		}

		return $data;
	}

	/**
	 * Read a header from a REST response (case-insensitive).
	 *
	 * @param WP_REST_Response $response Response.
	 * @param string           $name     Header name.
	 * @return string|null
	 */
	public static function header( WP_REST_Response $response, $name ) {
		$headers = $response->get_headers();

		foreach ( $headers as $key => $value ) {
			if ( strtolower( $key ) === strtolower( $name ) ) {
				return (string) $value;
			}
		}

		return null;
	}
}
