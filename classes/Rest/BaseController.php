<?php
/**
 * Base REST controller.
 *
 * @package Jetpack_CRM_REST_API_Improved
 */

namespace Jetpack_CRM_REST_API_Improved\Rest;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared behaviour for all controllers: namespace, pagination, responses, errors.
 */
abstract class BaseController extends WP_REST_Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'jpcrm-improved/v1';

	/**
	 * Default number of items per page.
	 *
	 * @var int
	 */
	protected $default_per_page = 20;

	/**
	 * Get the Jetpack CRM data layer.
	 *
	 * @return object|null
	 */
	protected function get_dal() {
		return isset( $GLOBALS['zbs'] ) ? $GLOBALS['zbs']->DAL : null;
	}

	/**
	 * Resolve pagination parameters from the request.
	 *
	 * DAL uses a 1-based page number plus perPage, so `page` maps directly.
	 * `offset` is emulated by fetching offset + per_page and slicing the result.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return array{page:int,per_page:int,offset:int,slice:int,all:bool}
	 */
	protected function parse_pagination( WP_REST_Request $request ) {
		$raw_per_page = $request->get_param( 'per_page' );

		if ( null === $raw_per_page || '' === $raw_per_page ) {
			$per_page = $this->default_per_page;
		} else {
			$per_page = (int) $raw_per_page;
		}

		$raw_offset = $request->get_param( 'offset' );
		$offset     = ( null === $raw_offset || '' === $raw_offset ) ? 0 : max( 0, (int) $raw_offset );

		$raw_page = $request->get_param( 'page' );
		$page     = ( $raw_page && (int) $raw_page > 0 ) ? (int) $raw_page : 1;

		if ( -1 === $per_page ) {
			return array(
				'page'     => -1,
				'per_page' => -1,
				'offset'   => 0,
				'slice'    => 0,
				'all'      => true,
			);
		}

		$per_page = max( 1, $per_page );

		/**
		 * Filters the maximum allowed `per_page` value.
		 *
		 * @param int $max Maximum items per page. 0 or less disables the cap.
		 */
		$max = (int) apply_filters( 'jpcrm_improved_rest_max_per_page', 1000 );

		if ( $max > 0 && $per_page > $max ) {
			$per_page = $max;
		}

		if ( $offset > 0 && ! $raw_page ) {
			return array(
				'page'     => 1,
				'per_page' => $offset + $per_page,
				'offset'   => $offset,
				'slice'    => $offset,
				'all'      => false,
			);
		}

		return array(
			'page'     => $page,
			'per_page' => $per_page,
			'offset'   => 0,
			'slice'    => 0,
			'all'      => false,
		);
	}

	/**
	 * Build a collection response with pagination headers.
	 *
	 * @param array $items    Collection items.
	 * @param int   $total    Total number of items.
	 * @param int   $per_page Items per page.
	 * @return WP_REST_Response
	 */
	protected function collection_response( $items, $total, $per_page ) {
		$response = rest_ensure_response( $items );

		$response->header( 'X-WP-Total', (string) (int) $total );

		if ( $per_page > 0 ) {
			$pages = (int) ceil( $total / $per_page );
		} else {
			$pages = $total > 0 ? 1 : 0;
		}

		$response->header( 'X-WP-TotalPages', (string) $pages );

		return $response;
	}

	/**
	 * Create a WP_Error.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @param int    $status  HTTP status.
	 * @return WP_Error
	 */
	protected function error( $code, $message, $status ) {
		return new WP_Error( $code, $message, array( 'status' => (int) $status ) );
	}

	/**
	 * Not found error.
	 *
	 * @return WP_Error
	 */
	protected function error_not_found() {
		return $this->error(
			'jpcrm_rest_not_found',
			__( 'The requested resource was not found.', 'jetpack-crm-rest-api-improved' ),
			404
		);
	}

	/**
	 * Forbidden error.
	 *
	 * @return WP_Error
	 */
	protected function error_forbidden() {
		return $this->error(
			'jpcrm_rest_forbidden',
			__( 'Sorry, you are not allowed to access this resource.', 'jetpack-crm-rest-api-improved' ),
			403
		);
	}

	/**
	 * Unauthorized error.
	 *
	 * @return WP_Error
	 */
	protected function error_unauthorized() {
		return $this->error(
			'jpcrm_rest_unauthorized',
			__( 'Authentication is required to access this resource.', 'jetpack-crm-rest-api-improved' ),
			401
		);
	}
}
