<?php
/**
 * Base controller for DAL-based entity CRUD.
 *
 * @package Jetpack_CRM_REST_API_Improved
 */

namespace Jetpack_CRM_REST_API_Improved\Rest;

use Jetpack_CRM_REST_API_Improved\Plugin;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generic CRUD controller that adapts DAL3 object layers to REST.
 */
abstract class ObjectController extends BaseController {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin|null
	 */
	protected $plugin;

	/**
	 * DAL layer name (e.g. 'contacts', 'companies').
	 *
	 * @var string
	 */
	protected $dal_layer;

	/**
	 * ZBS_TYPE_* constant value.
	 *
	 * @var int
	 */
	protected $obj_type;

	/**
	 * REST resource name for permissions.
	 *
	 * @var string
	 */
	protected $perm_resource;

	/**
	 * Method name for getSingle: getContact, getCompany, etc.
	 *
	 * @var string
	 */
	protected $method_get = '';

	/**
	 * Method name for getList: getContacts, getCompanies, etc.
	 *
	 * @var string
	 */
	protected $method_list = '';

	/**
	 * Method name for addUpdate: addUpdateContact, etc.
	 *
	 * @var string
	 */
	protected $method_save = '';

	/**
	 * Method name for delete: deleteContact, etc.
	 *
	 * @var string
	 */
	protected $method_delete = '';

	/**
	 * Method name for count: getContactCount, etc.
	 *
	 * @var string
	 */
	protected $method_count = '';

	/**
	 * Constructor.
	 *
	 * @param Plugin|null $plugin Plugin instance.
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
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				'args' => array(
					'id' => array(
						'description' => __( 'Unique identifier.', 'jetpack-crm-rest-api-improved' ),
						'type'        => 'integer',
						'required'    => true,
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permission_check' ),
				),
			)
		);
	}

	/**
	 * Permission checks.
	 */
	public function get_items_permissions_check( $request ) {
		return $this->check( 'read' );
	}

	public function get_item_permissions_check( $request ) {
		return $this->check( 'read' );
	}

	public function create_item_permissions_check( $request ) {
		return $this->check( 'write' );
	}

	public function update_item_permissions_check( $request ) {
		return $this->check( 'write' );
	}

	public function delete_item_permissions_check( $request ) {
		return $this->check( 'delete' );
	}

	public function get_items_permission_check( $request ) {
		return $this->get_items_permissions_check( $request );
	}

	public function get_item_permission_check( $request ) {
		return $this->get_item_permissions_check( $request );
	}

	public function create_item_permission_check( $request ) {
		return $this->create_item_permissions_check( $request );
	}

	public function update_item_permission_check( $request ) {
		return $this->update_item_permissions_check( $request );
	}

	public function delete_item_permission_check( $request ) {
		return $this->delete_item_permissions_check( $request );
	}

	/**
	 * List items.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$layer = $this->get_layer();
		if ( ! $layer ) {
			return $this->dal_error();
		}

		$pagination = $this->parse_pagination( $request );
		$args       = $this->build_list_args( $request, $pagination );

		$items = $layer->{$this->method_list}( $args );
		if ( ! is_array( $items ) ) {
			$items = array();
		}

		$formatted = array_map( array( $this, 'format_item' ), $items );

		if ( $pagination['slice'] > 0 ) {
			$formatted = array_slice( $formatted, $pagination['slice'] );
		}

		$count_args = $this->build_list_args( $request, array( 'page' => -1, 'per_page' => -1 ) );
		$total      = (int) $layer->{$this->method_count}( $count_args );

		return $this->collection_response( $formatted, $total, $pagination['per_page'] );
	}

	/**
	 * Get a single item.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$layer = $this->get_layer();
		if ( ! $layer ) {
			return $this->dal_error();
		}

		$item = $layer->{$this->method_get}( (int) $request->get_param( 'id' ), $this->single_args() );
		if ( ! $item ) {
			return $this->error_not_found();
		}

		return rest_ensure_response( $this->format_item( $item ) );
	}

	/**
	 * Create an item.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$layer = $this->get_layer();
		if ( ! $layer ) {
			return $this->dal_error();
		}

		$payload = $this->get_payload( $request );
		$error   = $this->validate_payload( $payload );
		if ( is_wp_error( $error ) ) {
			return $error;
		}

		$dal_data = $this->to_dal( $payload );
		$owner    = isset( $payload['owner'] ) ? (int) $payload['owner'] : -1;

		$result = $layer->{$this->method_save}(
			array(
				'id'    => -1,
				'owner' => $owner,
				'data'  => $dal_data,
			)
		);

		if ( ! $result ) {
			return $this->error( 'jpcrm_rest_validation_error', __( 'Failed to create resource.', 'jetpack-crm-rest-api-improved' ), 422 );
		}

		$item = $layer->{$this->method_get}( (int) $result, $this->single_args() );
		$response = rest_ensure_response( $this->format_item( $item ) );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( $this->namespace . '/' . $this->rest_base . '/' . (int) $result ) );

		return $response;
	}

	/**
	 * Update an item.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$layer = $this->get_layer();
		if ( ! $layer ) {
			return $this->dal_error();
		}

		$id = (int) $request->get_param( 'id' );

		$existing = $layer->{$this->method_get}( $id, $this->single_args() );
		if ( ! $existing ) {
			return $this->error_not_found();
		}

		$payload = $this->get_payload( $request );
		$error   = $this->validate_payload( $payload, false );
		if ( is_wp_error( $error ) ) {
			return $error;
		}

		$args = array(
			'id'   => $id,
			'data' => $this->to_dal( $payload ),
		);

		if ( isset( $payload['owner'] ) ) {
			$args['owner'] = (int) $payload['owner'];
		}

		if ( 'PATCH' === strtoupper( (string) $request->get_method() ) ) {
			$args['do_not_update_blanks'] = true;
		}

		$result = $layer->{$this->method_save}( $args );
		if ( ! $result ) {
			return $this->error( 'jpcrm_rest_validation_error', __( 'Failed to update resource.', 'jetpack-crm-rest-api-improved' ), 422 );
		}

		$item = $layer->{$this->method_get}( $id, $this->single_args() );
		return rest_ensure_response( $this->format_item( $item ) );
	}

	/**
	 * Delete an item.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$layer = $this->get_layer();
		if ( ! $layer ) {
			return $this->dal_error();
		}

		$id  = (int) $request->get_param( 'id' );
		$item = $layer->{$this->method_get}( $id, $this->single_args() );
		if ( ! $item ) {
			return $this->error_not_found();
		}

		$result = $layer->{$this->method_delete}( array( 'id' => $id ) );
		if ( false === $result ) {
			return $this->error( 'jpcrm_rest_unknown_error', __( 'Failed to delete resource.', 'jetpack-crm-rest-api-improved' ), 500 );
		}

		return rest_ensure_response(
			array(
				'deleted'  => true,
				'previous' => $this->format_item( $item ),
			)
		);
	}

	/**
	 * Get the DAL object layer.
	 *
	 * @return object|null
	 */
	protected function get_layer() {
		$dal = $this->get_dal();
		if ( ! $dal || empty( $this->dal_layer ) ) {
			return null;
		}
		return isset( $dal->{$this->dal_layer} ) ? $dal->{$this->dal_layer} : null;
	}

	/**
	 * DAL unavailable error.
	 *
	 * @return WP_Error
	 */
	protected function dal_error() {
		return $this->error( 'jpcrm_rest_unknown_error', __( 'CRM data layer is unavailable.', 'jetpack-crm-rest-api-improved' ), 500 );
	}

	/**
	 * Shared permission check.
	 *
	 * @param string $action read/write/delete.
	 * @return bool|WP_Error
	 */
	protected function check( $action ) {
		if ( ! is_user_logged_in() ) {
			return $this->error_unauthorized();
		}
		if ( ! Permissions::can( $this->perm_resource, $action ) ) {
			return $this->error_forbidden();
		}
		return true;
	}

	/**
	 * Extract JSON payload.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	protected function get_payload( $request ) {
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = $request->get_body_params();
		}
		return is_array( $payload ) ? $payload : array();
	}

	/**
	 * Default single-item DAL args (with relations).
	 *
	 * @return array
	 */
	protected function single_args() {
		return array(
			'withCustomFields' => true,
			'withTags'         => true,
			'withOwner'        => true,
			'ignoreowner'      => zeroBSCRM_DAL2_ignoreOwnership( $this->obj_type ),
		);
	}

	/**
	 * Build DAL list query args from request.
	 *
	 * @param WP_REST_Request $request    Request.
	 * @param array           $pagination Pagination.
	 * @return array
	 */
	protected function build_list_args( $request, array $pagination ) {
		$args = array(
			'withCustomFields' => true,
			'withTags'         => true,
			'withOwner'        => true,
			'ignoreowner'      => zeroBSCRM_DAL2_ignoreOwnership( $this->obj_type ),
		);

		if ( isset( $pagination['page'] ) && -1 === (int) $pagination['page'] ) {
			$args['page']    = -1;
			$args['perPage'] = -1;
		} else {
			$args['page']    = (int) $pagination['page'];
			$args['perPage'] = (int) $pagination['per_page'];
		}

		$sort_map = $this->sort_field_map();
		$orderby  = $request->get_param( 'orderby' );
		$args['sortByField'] = isset( $sort_map[ $orderby ] ) ? $sort_map[ $orderby ] : 'ID';
		$args['sortOrder']   = ( 'asc' === strtolower( (string) $request->get_param( 'order' ) ) ) ? 'ASC' : 'DESC';

		$search = $request->get_param( 'search' );
		if ( null === $search || '' === $search ) {
			$search = $request->get_param( 's' );
		}
		if ( ! empty( $search ) ) {
			$args['searchPhrase'] = sanitize_text_field( $search );
		}

		$status = $request->get_param( 'status' );
		if ( ! empty( $status ) ) {
			$args['hasStatus'] = sanitize_text_field( $status );
		}

		$owner = $request->get_param( 'owner' );
		if ( null === $owner || '' === $owner ) {
			$owner = $request->get_param( 'owned_by' );
		}
		if ( ! empty( $owner ) ) {
			$args['ownedBy'] = (int) $owner;
		}

		$this->apply_extra_filters( $args, $request );

		return $args;
	}

	/**
	 * Sort field mapping (REST name → DAL column).
	 *
	 * @return array
	 */
	protected function sort_field_map() {
		return array(
			'id'               => 'ID',
			'status'           => 'zbsc_status',
			'date_created_gmt' => 'zbsc_created',
		);
	}

	/**
	 * Apply entity-specific filters.
	 *
	 * @param array           &$args    DAL args.
	 * @param WP_REST_Request $request  Request.
	 */
	protected function apply_extra_filters( &$args, $request ) {}

	/**
	 * Default collection params.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		return array(
			'page'     => array(
				'description' => __( 'Current page of the collection.', 'jetpack-crm-rest-api-improved' ),
				'type'        => 'integer',
				'minimum'     => 1,
			),
			'per_page' => array(
				'description' => __( 'Maximum number of items per page. -1 for all.', 'jetpack-crm-rest-api-improved' ),
				'type'        => 'integer',
			),
			'offset'   => array(
				'description' => __( 'Offset the result set.', 'jetpack-crm-rest-api-improved' ),
				'type'        => 'integer',
				'minimum'     => 0,
			),
			'search'   => array(
				'description' => __( 'Search phrase.', 'jetpack-crm-rest-api-improved' ),
				'type'        => 'string',
			),
			's'        => array(
				'description' => __( 'Alias for search.', 'jetpack-crm-rest-api-improved' ),
				'type'        => 'string',
			),
			'orderby'  => array(
				'description' => __( 'Sort field.', 'jetpack-crm-rest-api-improved' ),
				'type'        => 'string',
				'default'     => 'id',
			),
			'order'    => array(
				'description' => __( 'Sort direction.', 'jetpack-crm-rest-api-improved' ),
				'type'        => 'string',
				'enum'        => array( 'asc', 'desc' ),
				'default'     => 'desc',
			),
			'status'   => array(
				'description' => __( 'Filter by status.', 'jetpack-crm-rest-api-improved' ),
				'type'        => 'string',
			),
			'owner'    => array(
				'description' => __( 'Filter by owner (WP user ID).', 'jetpack-crm-rest-api-improved' ),
				'type'        => 'integer',
			),
			'owned_by' => array(
				'description' => __( 'Alias for owner.', 'jetpack-crm-rest-api-improved' ),
				'type'        => 'integer',
			),
		);
	}

	/**
	 * Validate payload.
	 *
	 * @param array $payload      Payload.
	 * @param bool  $require_data Whether data is required.
	 * @return true|WP_Error
	 */
	protected function validate_payload( array $payload, $require_data = true ) {
		if ( $require_data && empty( $payload ) ) {
			return $this->error( 'jpcrm_rest_validation_error', __( 'Empty request.', 'jetpack-crm-rest-api-improved' ), 422 );
		}
		return true;
	}

	/**
	 * Format a DAL object for REST output.
	 *
	 * @param array $item DAL object.
	 * @return array
	 */
	abstract protected function format_item( array $item );

	/**
	 * Convert REST payload to DAL data array.
	 *
	 * @param array $payload REST payload.
	 * @return array
	 */
	abstract protected function to_dal( array $payload );
}
