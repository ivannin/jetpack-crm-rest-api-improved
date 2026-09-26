<?php
/**
 * Line items REST controller.
 *
 * @package Jetpack_CRM_REST_API_Improved
 */

namespace Jetpack_CRM_REST_API_Improved\Rest\Controllers;

use Jetpack_CRM_REST_API_Improved\Rest\BaseController;
use Jetpack_CRM_REST_API_Improved\Rest\Fields;
use Jetpack_CRM_REST_API_Improved\Rest\Permissions;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * CRUD for invoice/quote line items.
 */
class LineItemsController extends BaseController {
	protected $rest_base = 'line-items';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'perms_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'perms_write_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				'args'   => array(
					'id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'perms_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'perms_write_check' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'perms_write_check' ),
				),
			)
		);
	}

	public function perms_check( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		if ( ! is_user_logged_in() ) { return $this->error_unauthorized(); }
		if ( ! Permissions::can( 'line-items', 'read' ) ) { return $this->error_forbidden(); }
		return true;
	}

	public function perms_write_check( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		if ( ! is_user_logged_in() ) { return $this->error_unauthorized(); }
		if ( ! Permissions::can( 'line-items', 'write' ) ) { return $this->error_forbidden(); }
		return true;
	}

	/**
	 * List line items.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_items( $request ) {
		$layer = $this->get_layer();
		if ( ! $layer ) { return $this->dal_error(); }

		$pagination = $this->parse_pagination( $request );
		$args       = $this->build_query_args( $request, $pagination );

		$items = $layer->getLineitems( $args );
		if ( ! is_array( $items ) ) { $items = array(); }

		$parents = $this->parents_for( $items );

		$formatted = array();
		foreach ( $items as $item ) {
			$formatted[] = $this->format_item( $item, isset( $parents[ (int) $item['id'] ] ) ? $parents[ (int) $item['id'] ] : null );
		}

		$count = (int) $layer->getLineItemCount( $this->build_query_args( $request, array( 'page' => -1, 'per_page' => -1 ) ) );

		return $this->collection_response( $formatted, $count, $pagination['per_page'] );
	}

	/**
	 * Read a line item.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( $request ) {
		$layer = $this->get_layer();
		if ( ! $layer ) { return $this->dal_error(); }

		$item = $layer->getLineitem( (int) $request->get_param( 'id' ) );
		if ( ! $item ) { return $this->error_not_found(); }

		return rest_ensure_response( $this->format_item( $item ) );
	}

	/**
	 * Create a line item.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_item( $request ) {
		$layer = $this->get_layer();
		if ( ! $layer ) { return $this->dal_error(); }

		$payload = $this->get_payload( $request );

		$parent_type = isset( $payload['parent_object_type'] ) ? (int) $payload['parent_object_type'] : 0;
		$parent_id   = isset( $payload['parent_object_id'] ) ? (int) $payload['parent_object_id'] : 0;

		if ( $parent_type <= 0 || $parent_id <= 0 ) {
			return $this->error( 'jpcrm_rest_validation_error', __( 'Нужны parent_object_type и parent_object_id.', 'jetpack-crm-rest-api-improved' ), 422 );
		}

		$args = array(
			'id'            => -1,
			'owner'         => isset( $payload['owner'] ) ? (int) $payload['owner'] : -1,
			'linkedObjType' => $parent_type,
			'linkedObjID'   => $parent_id,
			'data'          => $this->to_dal( $payload ),
		);

		$result = $layer->addUpdateLineitem( $args );
		if ( ! $result ) {
			return $this->error( 'jpcrm_rest_validation_error', __( 'Не удалось создать позицию.', 'jetpack-crm-rest-api-improved' ), 422 );
		}

		$item     = $layer->getLineitem( (int) $result );
		$response = rest_ensure_response( $this->format_item( $item ) );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * Update a line item.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( $request ) {
		$layer = $this->get_layer();
		if ( ! $layer ) { return $this->dal_error(); }

		$id       = (int) $request->get_param( 'id' );
		$existing = $layer->getLineitem( $id );
		if ( ! $existing ) { return $this->error_not_found(); }

		$payload = $this->get_payload( $request );
		$data    = $this->to_dal( $payload );

		foreach ( array( 'order', 'title', 'desc', 'quantity', 'price', 'currency', 'net', 'discount', 'fee', 'shipping', 'shipping_taxes', 'shipping_tax', 'taxes', 'tax', 'total' ) as $key ) {
			if ( ! array_key_exists( $key, $data ) && isset( $existing[ $key ] ) ) {
				$data[ $key ] = $existing[ $key ];
			}
		}

		$result = $layer->addUpdateLineitem(
			array(
				'id'   => $id,
				'data' => $data,
			)
		);

		if ( ! $result ) {
			return $this->error( 'jpcrm_rest_validation_error', __( 'Не удалось обновить позицию.', 'jetpack-crm-rest-api-improved' ), 422 );
		}

		$item = $layer->getLineitem( $id );

		return rest_ensure_response( $this->format_item( $item ) );
	}

	/**
	 * Delete a line item.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_item( $request ) {
		$layer = $this->get_layer();
		if ( ! $layer ) { return $this->dal_error(); }

		$id   = (int) $request->get_param( 'id' );
		$item = $layer->getLineitem( $id );
		if ( ! $item ) { return $this->error_not_found(); }

		$result = $layer->deleteLineitem( array( 'id' => $id ) );
		if ( false === $result ) {
			return $this->error( 'jpcrm_rest_unknown_error', __( 'Не удалось удалить позицию.', 'jetpack-crm-rest-api-improved' ), 500 );
		}

		return rest_ensure_response( array( 'deleted' => true, 'previous' => $this->format_item( $item ) ) );
	}

	/**
	 * Build DAL query args.
	 *
	 * @param WP_REST_Request $request    Request.
	 * @param array           $pagination Pagination descriptor.
	 * @return array
	 */
	private function build_query_args( $request, array $pagination ) {
		$args = array(
			'sortByField' => 'ID',
			'sortOrder'   => 'ASC',
		);

		if ( isset( $pagination['page'] ) && -1 === (int) $pagination['page'] ) {
			$args['page']    = -1;
			$args['perPage'] = -1;
		} else {
			$args['page']    = max( 0, (int) $pagination['page'] - 1 );
			$args['perPage'] = (int) $pagination['per_page'];
		}

		$search = $request->get_param( 'search' );
		if ( ! empty( $search ) ) {
			$args['searchPhrase'] = sanitize_text_field( $search );
		}

		$parent_type = $request->get_param( 'parent_object_type' );
		if ( ! empty( $parent_type ) ) {
			$args['associatedObjType'] = (int) $parent_type;
		}

		$parent_id = $request->get_param( 'parent_object_id' );
		if ( ! empty( $parent_id ) ) {
			$args['associatedObjID'] = (int) $parent_id;
		}

		return $args;
	}

	/**
	 * Format a line item for output.
	 *
	 * @param mixed $item DAL item.
	 * @return array
	 */
	private function format_item( $item, $parent = null ) {
		$item = (array) $item;

		if ( null === $parent ) {
			$parent = $this->parent_of( isset( $item['id'] ) ? $item['id'] : 0 );
		}

		return array(
			'id'                 => isset( $item['id'] ) ? (int) $item['id'] : 0,
			'order'              => isset( $item['order'] ) ? $item['order'] : '',
			'title'              => isset( $item['title'] ) ? $item['title'] : '',
			'description'        => isset( $item['desc'] ) ? $item['desc'] : '',
			'quantity'           => isset( $item['quantity'] ) ? (float) $item['quantity'] : 0,
			'price'              => isset( $item['price'] ) ? (float) $item['price'] : 0,
			'currency'           => isset( $item['currency'] ) ? $item['currency'] : '',
			'net'                => isset( $item['net'] ) ? (float) $item['net'] : 0,
			'discount'           => isset( $item['discount'] ) ? (float) $item['discount'] : 0,
			'fee'                => isset( $item['fee'] ) ? (float) $item['fee'] : 0,
			'shipping'           => isset( $item['shipping'] ) ? (float) $item['shipping'] : 0,
			'shipping_taxes'     => isset( $item['shipping_taxes'] ) ? $item['shipping_taxes'] : '',
			'shipping_tax'       => isset( $item['shipping_tax'] ) ? (float) $item['shipping_tax'] : 0,
			'taxes'              => isset( $item['taxes'] ) ? $item['taxes'] : '',
			'tax'                => isset( $item['tax'] ) ? (float) $item['tax'] : 0,
			'total'              => isset( $item['total'] ) ? (float) $item['total'] : 0,
			'parent_object_type' => isset( $item['linkedObjType'] ) ? (int) $item['linkedObjType'] : (int) $parent[0],
			'parent_object_id'   => isset( $item['linkedObjID'] ) ? (int) $item['linkedObjID'] : (int) $parent[1],
			'date_created_gmt'   => Fields::uts_to_iso8601( isset( $item['createduts'] ) ? $item['createduts'] : ( isset( $item['created'] ) ? $item['created'] : 0 ) ),
		);
	}

	/**
	 * Convert a REST payload into DAL data.
	 *
	 * @param array $payload Payload.
	 * @return array
	 */
	private function to_dal( array $payload ) {
		$data = array();

		$map = array( 'order', 'title', 'quantity', 'price', 'currency', 'net', 'discount', 'fee', 'shipping', 'shipping_taxes', 'shipping_tax', 'taxes', 'tax', 'total' );

		foreach ( $map as $key ) {
			if ( array_key_exists( $key, $payload ) ) {
				$data[ $key ] = $payload[ $key ];
			}
		}

		if ( array_key_exists( 'description', $payload ) ) {
			$data['desc'] = $payload['description'];
		}

		if ( array_key_exists( 'created', $payload ) ) {
			$data['created'] = $payload['created'];
		}

		return $data;
	}

	/**
	 * Get the DAL line items layer.
	 *
	 * @return object|null
	 */
	protected function get_layer() {
		$dal = $this->get_dal();
		if ( ! $dal || ! isset( $dal->lineitems ) ) {
			return null;
		}
		return $dal->lineitems;
	}

	/**
	 * DAL unavailable error.
	 *
	 * @return \WP_Error
	 */
	protected function dal_error() {
		return $this->error( 'jpcrm_rest_unknown_error', __( 'CRM data layer is unavailable.', 'jetpack-crm-rest-api-improved' ), 500 );
	}

	/**
	 * Resolve the parent object of a line item.
	 *
	 * @param int $id Line item ID.
	 * @return array{0:int,1:int}
	 */
	private function parent_of( $id ) {
		$parents = $this->parents_for( array( array( 'id' => (int) $id ) ) );

		return isset( $parents[ (int) $id ] ) ? $parents[ (int) $id ] : array( 0, 0 );
	}

	/**
	 * Bulk-resolve parents for a list of line items.
	 *
	 * @param array $items Line items.
	 * @return array<int,array{0:int,1:int}>
	 */
	private function parents_for( array $items ) {
		global $wpdb, $ZBSCRM_t;

		$ids = array();

		foreach ( $items as $item ) {
			$item = (array) $item;
			if ( ! empty( $item['id'] ) ) {
				$ids[] = (int) $item['id'];
			}
		}

		if ( empty( $ids ) || ! isset( $ZBSCRM_t['objlinks'] ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$params       = array_merge( array( ZBS_TYPE_LINEITEM ), $ids );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT zbsol_objid_from, zbsol_objtype_to, zbsol_objid_to FROM `{$ZBSCRM_t['objlinks']}` WHERE zbsol_objtype_from = %d AND zbsol_objid_from IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				$params
			),
			ARRAY_A
		);

		$out = array();

		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['zbsol_objid_from'] ] = array( (int) $row['zbsol_objtype_to'], (int) $row['zbsol_objid_to'] );
		}

		return $out;
	}

	/**
	 * Extract the JSON payload.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	private function get_payload( $request ) {
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = $request->get_body_params();
		}
		return is_array( $payload ) ? $payload : array();
	}
}
