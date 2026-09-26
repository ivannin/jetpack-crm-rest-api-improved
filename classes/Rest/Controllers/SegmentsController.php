<?php
namespace Jetpack_CRM_REST_API_Improved\Rest\Controllers;

use Jetpack_CRM_REST_API_Improved\Rest\ObjectController;
use Jetpack_CRM_REST_API_Improved\Rest\Fields;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class SegmentsController extends ObjectController {
	protected $rest_base      = 'segments';
	protected $dal_layer     = 'segments';
	protected $obj_type      = 9;
	protected $perm_resource = 'segments';
	protected $method_get    = 'getSegment';
	protected $method_list   = 'getSegments';
	protected $method_save   = 'addUpdateSegment';
	protected $method_delete = 'deleteSegment';
	protected $method_count  = 'getSegmentCount';

	/**
	 * Register standard routes plus the compile action.
	 *
	 * @return void
	 */
	public function register_routes() {
		parent::register_routes();

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/compile',
			array(
				'args'   => array(
					'id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'compile_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Recompile a segment.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function compile_item( $request ) {
		$layer = $this->get_layer();
		if ( ! $layer ) { return $this->dal_error(); }

		$id = (int) $request->get_param( 'id' );

		if ( ! $layer->getSegment( $id, true ) ) {
			return $this->error_not_found();
		}

		$count = $layer->compileSegment( $id );
		$item  = $layer->getSegment( $id, true );
		$data  = $this->format_item( $item );

		$data['compiled']      = true;
		$data['compile_count'] = (int) $count;

		return rest_ensure_response( $data );
	}

	public function get_items( $request ) {
		$layer = $this->get_layer();
		if ( ! $layer ) { return $this->dal_error(); }

		$pagination = $this->parse_pagination( $request );
		$search = $request->get_param( 'search' ) ?: '';
		$sort   = ( 'asc' === strtolower( (string) $request->get_param( 'order' ) ) ) ? 'ASC' : 'DESC';

		$owner = $request->get_param( 'owner' );
		$owner_id = -1;
		if ( ! empty( $owner ) ) { $owner_id = (int) $owner; }

		$segments = $layer->getSegments(
			$owner_id,
			$pagination['all'] ? -1 : (int) $pagination['per_page'],
			$pagination['all'] ? -1 : (int) $pagination['page'],
			true,
			$search,
			'',
			'',
			$sort
		);

		if ( ! is_array( $segments ) ) { $segments = array(); }

		$items = array_map( array( $this, 'format_item' ), $segments );
		$total = (int) $layer->getSegmentCount();
		return $this->collection_response( $items, $total, $pagination['per_page'] );
	}

	public function get_item( $request ) {
		$layer = $this->get_layer();
		if ( ! $layer ) { return $this->dal_error(); }

		$item = $layer->getSegment( (int) $request->get_param( 'id' ), true );
		if ( ! $item ) { return $this->error_not_found(); }
		return rest_ensure_response( $this->format_item( $item ) );
	}

	public function create_item( $request ) {
		$layer = $this->get_layer();
		if ( ! $layer ) { return $this->dal_error(); }

		$payload = $this->get_payload( $request );
		if ( empty( $payload['name'] ) ) {
			return $this->error( 'jpcrm_rest_validation_error', __( 'Segment name is required.', 'jetpack-crm-rest-api-improved' ), 422 );
		}

		$conditions = isset( $payload['conditions'] ) && is_array( $payload['conditions'] ) ? $payload['conditions'] : array();
		$match_type = isset( $payload['match_type'] ) ? $payload['match_type'] : 'all';

		$result = $layer->addUpdateSegment(
			-1,
			isset( $payload['owner'] ) ? (int) $payload['owner'] : -1,
			sanitize_text_field( $payload['name'] ),
			$conditions,
			$match_type
		);

		if ( ! $result ) {
			return $this->error( 'jpcrm_rest_validation_error', __( 'Failed to create segment.', 'jetpack-crm-rest-api-improved' ), 422 );
		}

		$item = $layer->getSegment( (int) $result, true );
		$response = rest_ensure_response( $this->format_item( $item ) );
		$response->set_status( 201 );
		return $response;
	}

	public function update_item( $request ) {
		$layer = $this->get_layer();
		if ( ! $layer ) { return $this->dal_error(); }

		$id = (int) $request->get_param( 'id' );
		$existing = $layer->getSegment( $id, true );
		if ( ! $existing ) { return $this->error_not_found(); }

		$payload = $this->get_payload( $request );
		$name = isset( $payload['name'] ) ? sanitize_text_field( $payload['name'] ) : ( isset( $existing['name'] ) ? $existing['name'] : '' );
		$conditions = isset( $payload['conditions'] ) ? $payload['conditions'] : ( isset( $existing['conditions'] ) ? $existing['conditions'] : array() );
		$match_type = isset( $payload['match_type'] ) ? $payload['match_type'] : ( isset( $existing['match_type'] ) ? $existing['match_type'] : 'all' );

		$result = $layer->addUpdateSegment( $id, -1, $name, $conditions, $match_type );
		if ( ! $result ) {
			return $this->error( 'jpcrm_rest_validation_error', __( 'Failed to update segment.', 'jetpack-crm-rest-api-improved' ), 422 );
		}

		$item = $layer->getSegment( $id, true );
		return rest_ensure_response( $this->format_item( $item ) );
	}

	protected function format_item( array $item ) {
		return array(
			'id'                     => isset( $item['id'] ) ? (int) $item['id'] : 0,
			'name'                   => isset( $item['name'] ) ? $item['name'] : '',
			'slug'                   => isset( $item['slug'] ) ? $item['slug'] : '',
			'match_type'             => isset( $item['match_type'] ) ? $item['match_type'] : 'all',
			'conditions'             => ( isset( $item['conditions'] ) && is_array( $item['conditions'] ) ) ? $item['conditions'] : array(),
			'compile_count'          => isset( $item['count'] ) ? (int) $item['count'] : 0,
			'date_last_compiled_gmt' => Fields::uts_to_iso8601( isset( $item['compiled'] ) ? $item['compiled'] : 0 ),
		);
	}

	protected function to_dal( array $payload ) { return $payload; }
}
