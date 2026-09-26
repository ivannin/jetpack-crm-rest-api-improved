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
		return array( 'id' => 'ID', 'date_created_gmt' => 'zbsc_created' );
	}

	protected function apply_extra_filters( &$args, $request ) {
		$objtype = $request->get_param( 'object_type' );
		if ( ! empty( $objtype ) ) { $args['objtype'] = (int) $objtype; }
		$objid = $request->get_param( 'object_id' );
		if ( ! empty( $objid ) ) { $args['objid'] = (int) $objid; }
		$type = $request->get_param( 'type' );
		if ( ! empty( $type ) ) { $args['type'] = sanitize_text_field( $type ); }
	}

	public function get_collection_params() {
		$params = parent::get_collection_params();
		$params['object_type'] = array( 'description' => __( 'Filter by object type.', 'jetpack-crm-rest-api-improved' ), 'type' => 'integer' );
		$params['object_id']   = array( 'description' => __( 'Filter by object ID.', 'jetpack-crm-rest-api-improved' ), 'type' => 'integer' );
		$params['type']        = array( 'description' => __( 'Filter by log type.', 'jetpack-crm-rest-api-improved' ), 'type' => 'string' );
		return $params;
	}
}
