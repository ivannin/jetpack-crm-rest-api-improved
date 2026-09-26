<?php
/**
 * JSON-RPC 2.0 helpers for the MCP endpoint.
 *
 * @package Jetpack_CRM_REST_API_Improved
 */

namespace Jetpack_CRM_REST_API_Improved\Mcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds JSON-RPC response envelopes.
 */
class JsonRpc {

	const PARSE_ERROR        = -32700;
	const INVALID_REQUEST    = -32600;
	const METHOD_NOT_FOUND   = -32601;
	const INVALID_PARAMS     = -32602;
	const INTERNAL_ERROR     = -32603;
	const RESOURCE_NOT_FOUND = -32002;

	/**
	 * Build a successful JSON-RPC response.
	 *
	 * @param mixed $id     Request id.
	 * @param mixed $result Result payload.
	 * @return array
	 */
	public static function result( $id, $result ) {
		return array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'result'  => $result,
		);
	}

	/**
	 * Build a JSON-RPC error response.
	 *
	 * @param mixed  $id      Request id.
	 * @param int    $code    Error code.
	 * @param string $message Error message.
	 * @param mixed  $data    Optional error data.
	 * @return array
	 */
	public static function error( $id, $code, $message, $data = null ) {
		$error = array(
			'code'    => (int) $code,
			'message' => (string) $message,
		);

		if ( null !== $data ) {
			$error['data'] = $data;
		}

		return array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'error'   => $error,
		);
	}
}
