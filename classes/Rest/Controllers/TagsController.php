<?php
namespace Jetpack_CRM_REST_API_Improved\Rest\Controllers;

use Jetpack_CRM_REST_API_Improved\Rest\BaseController;
use Jetpack_CRM_REST_API_Improved\Rest\Permissions;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class TagsController extends BaseController {
	protected $namespace = 'jpcrm-improved/v1';
	protected $rest_base = 'tags';

	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permission_check' ),
				'args'                => $this->get_collection_params(),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'create_item_permission_check' ),
			),
		) );
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_items_permission_check' ),
			),
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_item' ),
				'permission_callback' => array( $this, 'create_item_permission_check' ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'delete_item_permission_check' ),
			),
		) );
	}

	public function get_items_permission_check( $request ) {
		if ( ! is_user_logged_in() ) { return $this->error_unauthorized(); }
		if ( ! Permissions::can( 'tags', 'read' ) ) { return $this->error_forbidden(); }
		return true;
	}
	public function create_item_permission_check( $request ) {
		if ( ! is_user_logged_in() ) { return $this->error_unauthorized(); }
		if ( ! Permissions::can( 'tags', 'write' ) ) { return $this->error_forbidden(); }
		return true;
	}
	public function delete_item_permission_check( $request ) {
		if ( ! is_user_logged_in() ) { return $this->error_unauthorized(); }
		if ( ! Permissions::can( 'tags', 'delete' ) ) { return $this->error_forbidden(); }
		return true;
	}

	public function get_items( $request ) {
		$dal = $this->get_dal();
		if ( ! $dal ) { return $this->error( 'jpcrm_rest_unknown_error', __( 'CRM data layer is unavailable.', 'jetpack-crm-rest-api-improved' ), 500 ); }

		$args = array(
			'searchPhrase' => $request->get_param( 'search' ) ?: '',
			'withStats'    => false,
			'sortByField'  => 'ID',
			'sortOrder'    => 'ASC',
			'page'         => -1,
			'perPage'      => -1,
		);

		$objtype = $request->get_param( 'object_type' );
		if ( ! empty( $objtype ) ) {
			$args['objtype'] = (int) $objtype;
		}

		$tags = $dal->getAllTags( $args );
		if ( ! is_array( $tags ) ) { $tags = array(); }

		$items = array_map( array( $this, 'format_tag' ), $tags );
		return $this->collection_response( $items, count( $items ), -1 );
	}

	public function get_item( $request ) {
		$dal = $this->get_dal();
		if ( ! $dal ) { return $this->error( 'jpcrm_rest_unknown_error', __( 'CRM data layer is unavailable.', 'jetpack-crm-rest-api-improved' ), 500 ); }

		$tag = $dal->getTag( (int) $request->get_param( 'id' ), array( 'withStats' => false ) );
		if ( ! $tag ) { return $this->error_not_found(); }
		return rest_ensure_response( $this->format_tag( $tag ) );
	}

	public function create_item( $request ) {
		$dal = $this->get_dal();
		if ( ! $dal ) { return $this->error( 'jpcrm_rest_unknown_error', __( 'CRM data layer is unavailable.', 'jetpack-crm-rest-api-improved' ), 500 ); }

		$payload = $request->get_json_params();
		if ( empty( $payload['name'] ) ) {
			return $this->error( 'jpcrm_rest_validation_error', __( 'Tag name is required.', 'jetpack-crm-rest-api-improved' ), 422 );
		}

		$data = array(
			'name' => sanitize_text_field( $payload['name'] ),
		);
		if ( ! empty( $payload['object_type'] ) ) { $data['objtype'] = (int) $payload['object_type']; }
		if ( ! empty( $payload['slug'] ) ) { $data['slug'] = sanitize_text_field( $payload['slug'] ); }

		$result = $dal->addUpdateTag( array( 'id' => -1, 'data' => $data ) );
		if ( ! $result ) {
			return $this->error( 'jpcrm_rest_validation_error', __( 'Failed to create tag.', 'jetpack-crm-rest-api-improved' ), 422 );
		}

		$tag = $dal->getTag( (int) $result, array( 'withStats' => false ) );
		$response = rest_ensure_response( $this->format_tag( $tag ) );
		$response->set_status( 201 );
		return $response;
	}

	public function update_item( $request ) {
		$dal = $this->get_dal();
		if ( ! $dal ) { return $this->error( 'jpcrm_rest_unknown_error', __( 'CRM data layer is unavailable.', 'jetpack-crm-rest-api-improved' ), 500 ); }

		$id = (int) $request->get_param( 'id' );
		$existing = $dal->getTag( $id );
		if ( ! $existing ) { return $this->error_not_found(); }

		$payload = $request->get_json_params();
		$data = array();
		if ( isset( $payload['name'] ) ) { $data['name'] = sanitize_text_field( $payload['name'] ); }
		if ( isset( $payload['slug'] ) ) { $data['slug'] = sanitize_text_field( $payload['slug'] ); }
		if ( isset( $payload['object_type'] ) ) { $data['objtype'] = (int) $payload['object_type']; }

		$result = $dal->addUpdateTag( array( 'id' => $id, 'data' => $data ) );
		if ( ! $result ) {
			return $this->error( 'jpcrm_rest_validation_error', __( 'Failed to update tag.', 'jetpack-crm-rest-api-improved' ), 422 );
		}

		$tag = $dal->getTag( $id, array( 'withStats' => false ) );
		return rest_ensure_response( $this->format_tag( $tag ) );
	}

	public function delete_item( $request ) {
		$dal = $this->get_dal();
		if ( ! $dal ) { return $this->error( 'jpcrm_rest_unknown_error', __( 'CRM data layer is unavailable.', 'jetpack-crm-rest-api-improved' ), 500 ); }

		$id = (int) $request->get_param( 'id' );
		$existing = $dal->getTag( $id, array( 'withStats' => false ) );
		if ( ! $existing ) { return $this->error_not_found(); }

		$result = $dal->deleteTag( array( 'id' => $id ) );
		if ( ! $result ) {
			return $this->error( 'jpcrm_rest_unknown_error', __( 'Failed to delete tag.', 'jetpack-crm-rest-api-improved' ), 500 );
		}

		return rest_ensure_response( array( 'deleted' => true, 'previous' => $this->format_tag( $existing ) ) );
	}

	protected function format_tag( $tag ) {
		return array(
			'id'          => isset( $tag['id'] ) ? (int) $tag['id'] : 0,
			'name'        => isset( $tag['name'] ) ? $tag['name'] : '',
			'slug'        => isset( $tag['slug'] ) ? $tag['slug'] : '',
			'object_type' => isset( $tag['objtype'] ) ? (int) $tag['objtype'] : 0,
			'count'       => isset( $tag['count'] ) ? (int) $tag['count'] : 0,
		);
	}

	public function get_collection_params() {
		return array(
			'search'       => array( 'description' => __( 'Search tags.', 'jetpack-crm-rest-api-improved' ), 'type' => 'string' ),
			'object_type'  => array( 'description' => __( 'Filter by object type.', 'jetpack-crm-rest-api-improved' ), 'type' => 'integer' ),
		);
	}
}
