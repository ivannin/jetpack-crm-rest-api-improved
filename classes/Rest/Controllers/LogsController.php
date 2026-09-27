<?php
namespace Jetpack_CRM_REST_API_Improved\Rest\Controllers;

use Jetpack_CRM_REST_API_Improved\Rest\ObjectController;
use Jetpack_CRM_REST_API_Improved\Rest\Fields;
use WP_REST_Request;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class LogsController extends ObjectController {
	protected $rest_base      = 'logs';
	protected $dal_layer     = 'logs';
	protected $obj_type      = 8;
	protected $perm_resource = 'logs';
	protected $method_get    = 'getLog';
	protected $method_list   = 'getLogsForANYObj';
	protected $method_save   = 'addUpdateLog';
	protected $method_delete = 'deleteLog';
	protected $method_count  = 'getLogCount';

	protected function format_item( array $item ) { return Fields::log_from_dal( $item ); }
	protected function to_dal( array $payload ) { return Fields::log_to_dal( $payload ); }

	/**
	 * List logs.
	 *
	 * The core DAL method `getLogsForANYObj()` treats the default `objtype = -1`
	 * as a real filter (`! empty( -1 )` is true), which makes the collection look
	 * empty. We therefore always pass an explicit objtype (0 means "all"), and
	 * switch to `getLogsForObj()` when a specific object is requested because the
	 * ANYObj method has no objid filter.
	 *
	 * The core `getLogCount()` cannot see any of these filters, so the total is
	 * calculated with a dedicated COUNT query that mirrors the list filters.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_items( $request ) {
		$layer = $this->get_layer();
		if ( ! $layer ) { return $this->dal_error(); }

		$pagination = $this->parse_pagination( $request );
		$args       = $this->build_list_args( $request, $pagination );

		if ( ! empty( $args['objid'] ) && ! empty( $args['objtype'] ) ) {
			$items = $layer->getLogsForObj( $args );
		} else {
			// 0 disables the objtype filter in the core DAL (unlike -1).
			$args['objtype'] = ! empty( $args['objtype'] ) ? (int) $args['objtype'] : 0;
			unset( $args['objid'] );
			// The ANYObj method has no pinned filter; keep the count consistent.
			unset( $args['only_pinned'] );
			// The core default is -1 which is truthy and adds `LIKE '%-1%'`.
			if ( ! isset( $args['searchPhrase'] ) ) { $args['searchPhrase'] = ''; }
			$items = $layer->getLogsForANYObj( $args );
		}

		if ( ! is_array( $items ) ) { $items = array(); }

		$formatted = array_map( array( $this, 'format_item' ), $items );

		if ( $pagination['slice'] > 0 ) {
			$formatted = array_slice( $formatted, $pagination['slice'] );
		}

		return $this->collection_response( $formatted, $this->count_logs( $args ), $pagination['per_page'] );
	}

	public function get_item( $request ) {
		$layer = $this->get_layer();
		if ( ! $layer ) { return $this->dal_error(); }

		$item = $layer->{$this->method_get}( array( 'id' => (int) $request->get_param( 'id' ), 'incMeta' => true ) );
		if ( ! $item ) { return $this->error_not_found(); }

		return rest_ensure_response( $this->format_item( $item ) );
	}

	public function create_item( $request ) {
		$layer = $this->get_layer();
		if ( ! $layer ) { return $this->dal_error(); }

		$payload = $this->get_payload( $request );
		$error   = $this->validate_payload( $payload );
		if ( is_wp_error( $error ) ) { return $error; }

		$result = $layer->{$this->method_save}( array(
			'id'   => -1,
			'owner' => isset( $payload['owner'] ) ? (int) $payload['owner'] : -1,
			'data' => $this->to_dal( $payload ),
		) );

		if ( ! $result ) {
			return $this->error( 'jpcrm_rest_validation_error', __( 'Failed to create log.', 'jetpack-crm-rest-api-improved' ), 422 );
		}

		$item = $layer->{$this->method_get}( array( 'id' => (int) $result, 'incMeta' => true ) );
		$response = rest_ensure_response( $this->format_item( $item ) );
		$response->set_status( 201 );
		return $response;
	}

	public function update_item( $request ) {
		$layer = $this->get_layer();
		if ( ! $layer ) { return $this->dal_error(); }

		$id = (int) $request->get_param( 'id' );
		$existing = $layer->{$this->method_get}( array( 'id' => $id, 'incMeta' => true ) );
		if ( ! $existing ) { return $this->error_not_found(); }

		$payload = $this->get_payload( $request );
		$error   = $this->validate_payload( $payload, false );
		if ( is_wp_error( $error ) ) { return $error; }

		$args = array( 'id' => $id, 'data' => $this->to_dal( $payload ) );
		if ( 'PATCH' === strtoupper( (string) $request->get_method() ) ) {
			$args['do_not_update_blanks'] = true;
		}

		$result = $layer->{$this->method_save}( $args );
		if ( ! $result ) {
			return $this->error( 'jpcrm_rest_validation_error', __( 'Failed to update log.', 'jetpack-crm-rest-api-improved' ), 422 );
		}

		$item = $layer->{$this->method_get}( array( 'id' => $id, 'incMeta' => true ) );
		return rest_ensure_response( $this->format_item( $item ) );
	}

	protected function single_args() {
		return array( 'incMeta' => true, 'ignoreowner' => zeroBSCRM_DAL2_ignoreOwnership( $this->obj_type ) );
	}

	protected function sort_field_map() {
		return array( 'id' => 'ID', 'date_created_gmt' => 'zbsl_created' );
	}

	/**
	 * Map log filters to the keys expected by the core DAL.
	 *
	 * @param array           &$args   DAL args.
	 * @param WP_REST_Request $request Request.
	 * @return void
	 */
	protected function apply_extra_filters( &$args, $request ) {
		$objtype = $request->get_param( 'object_type' );
		if ( null === $objtype || '' === $objtype ) { $objtype = $request->get_param( 'objtype' ); }
		if ( null !== $objtype && '' !== $objtype && (int) $objtype > 0 ) { $args['objtype'] = (int) $objtype; }

		$objid = $request->get_param( 'object_id' );
		if ( null === $objid || '' === $objid ) { $objid = $request->get_param( 'objid' ); }
		if ( null !== $objid && '' !== $objid && (int) $objid > 0 ) { $args['objid'] = (int) $objid; }

		$type = $request->get_param( 'type' );
		if ( null === $type || '' === $type ) { $type = $request->get_param( 'notetype' ); }
		if ( null !== $type && '' !== $type ) { $args['notetype'] = sanitize_text_field( $type ); }

		$pinned = $request->get_param( 'pinned' );
		if ( null !== $pinned && '' !== $pinned ) { $args['only_pinned'] = (bool) $pinned; }
	}

	/**
	 * Count logs matching the list filters.
	 *
	 * @param array $args DAL list args.
	 * @return int
	 */
	protected function count_logs( array $args ) {
		$dal = $this->get_dal();
		if ( ! $dal ) { return 0; }

		$where = array( 'direct' => array() );

		if ( ! empty( $args['objtype'] ) ) {
			$where['objtype'] = array( 'zbsl_objtype', '=', '%d', (int) $args['objtype'] );
		}

		if ( ! empty( $args['objid'] ) ) {
			$where['objid'] = array( 'zbsl_objid', '=', '%d', (int) $args['objid'] );
		}

		if ( ! empty( $args['notetype'] ) ) {
			$where['notetype'] = array( 'zbsl_type', '=', '%s', (string) $args['notetype'] );
		}

		if ( ! empty( $args['only_pinned'] ) ) {
			$where['direct'][] = array( 'zbsl_pinned = 1', array() );
		}

		if ( ! empty( $args['searchPhrase'] ) ) {
			$phrase            = (string) $args['searchPhrase'];
			$where['direct'][] = array(
				'(zbsl_shortdesc LIKE %s OR zbsl_longdesc LIKE %s)',
				array( '%' . $phrase . '%', '%' . $phrase . '%' ),
			);
		}

		$count = $dal->getFieldByWHERE(
			array(
				'objtype'     => defined( 'ZBS_TYPE_LOG' ) ? ZBS_TYPE_LOG : $this->obj_type,
				'colname'     => 'COUNT(ID)',
				'where'       => $where,
				'ignoreowner' => ! empty( $args['ignoreowner'] ),
			)
		);

		return is_numeric( $count ) ? (int) $count : 0;
	}

	public function get_collection_params() {
		$params = parent::get_collection_params();
		$params['object_type'] = array( 'description' => __( 'Filter by object type.', 'jetpack-crm-rest-api-improved' ), 'type' => 'integer' );
		$params['object_id']   = array( 'description' => __( 'Filter by object ID.', 'jetpack-crm-rest-api-improved' ), 'type' => 'integer' );
		$params['objtype']     = array( 'description' => __( 'Alias for object_type.', 'jetpack-crm-rest-api-improved' ), 'type' => 'integer' );
		$params['objid']       = array( 'description' => __( 'Alias for object_id.', 'jetpack-crm-rest-api-improved' ), 'type' => 'integer' );
		$params['type']        = array( 'description' => __( 'Filter by log type.', 'jetpack-crm-rest-api-improved' ), 'type' => 'string' );
		$params['notetype']    = array( 'description' => __( 'Alias for type.', 'jetpack-crm-rest-api-improved' ), 'type' => 'string' );
		$params['pinned']      = array( 'description' => __( 'Only pinned logs.', 'jetpack-crm-rest-api-improved' ), 'type' => 'boolean' );
		return $params;
	}
}
