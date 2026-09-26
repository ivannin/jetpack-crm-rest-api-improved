<?php
/**
 * Subresource REST controller (tags, meta, custom fields).
 *
 * @package Jetpack_CRM_REST_API_Improved
 */

namespace Jetpack_CRM_REST_API_Improved\Rest\Controllers;

use Jetpack_CRM_REST_API_Improved\Rest\BaseController;
use Jetpack_CRM_REST_API_Improved\Rest\Permissions;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers `/…/{id}/tags`, `/…/{id}/meta/{key}` and
 * `/…/{id}/custom-fields/{key}` for every DAL object type.
 */
class SubresourcesController extends BaseController {

	/**
	 * Register routes for all supported entities.
	 *
	 * @return void
	 */
	public function register_routes() {
		foreach ( $this->entities() as $resource => $type ) {
			$this->register_entity_routes( $resource, $type );
		}
	}

	/**
	 * Map of REST resources to DAL object type ids.
	 *
	 * @return array<string,int>
	 */
	private function entities() {
		if ( ! defined( 'ZBS_TYPE_CONTACT' ) ) {
			return array();
		}

		return array(
			'contacts'        => ZBS_TYPE_CONTACT,
			'companies'       => ZBS_TYPE_COMPANY,
			'invoices'        => ZBS_TYPE_INVOICE,
			'quotes'          => ZBS_TYPE_QUOTE,
			'transactions'    => ZBS_TYPE_TRANSACTION,
			'tasks'           => ZBS_TYPE_TASK,
			'forms'           => ZBS_TYPE_FORM,
			'segments'        => ZBS_TYPE_SEGMENT,
			'quote-templates' => ZBS_TYPE_QUOTETEMPLATE,
			'logs'            => ZBS_TYPE_LOG,
		);
	}

	/**
	 * Register routes for a single entity.
	 *
	 * @param string $resource Resource name.
	 * @param int    $type     DAL object type id.
	 * @return void
	 */
	private function register_entity_routes( $resource, $type ) {
		$base = '/' . $resource . '/(?P<id>[\d]+)';

		// Tags.
		register_rest_route(
			$this->namespace,
			$base . '/tags',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => function ( $request ) use ( $type ) {
						return $this->get_tags( $request, $type );
					},
					'permission_callback' => $this->permission( $resource, 'read' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => function ( $request ) use ( $type ) {
						return $this->set_tags( $request, $type );
					},
					'permission_callback' => $this->permission( $resource, 'write' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/tags/(?P<tag>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => function ( $request ) use ( $type ) {
						return $this->delete_tag( $request, $type );
					},
					'permission_callback' => $this->permission( $resource, 'write' ),
				),
			)
		);

		// Meta.
		register_rest_route(
			$this->namespace,
			$base . '/meta',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => function ( $request ) use ( $type ) {
						return $this->get_meta( $request, $type );
					},
					'permission_callback' => $this->permission( $resource, 'read' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/meta/(?P<key>[A-Za-z0-9_\-\.]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => function ( $request ) use ( $type ) {
						return $this->get_meta( $request, $type );
					},
					'permission_callback' => $this->permission( $resource, 'read' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => function ( $request ) use ( $type ) {
						return $this->set_meta( $request, $type );
					},
					'permission_callback' => $this->permission( $resource, 'write' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => function ( $request ) use ( $type ) {
						return $this->delete_meta( $request, $type );
					},
					'permission_callback' => $this->permission( $resource, 'write' ),
				),
			)
		);

		// Custom fields.
		register_rest_route(
			$this->namespace,
			$base . '/custom-fields',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => function ( $request ) use ( $type ) {
						return $this->get_custom_fields( $request, $type );
					},
					'permission_callback' => $this->permission( $resource, 'read' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => function ( $request ) use ( $type ) {
						return $this->set_custom_fields( $request, $type );
					},
					'permission_callback' => $this->permission( $resource, 'write' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/custom-fields/(?P<key>[A-Za-z0-9_\-\.]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => function ( $request ) use ( $type ) {
						return $this->get_custom_field( $request, $type );
					},
					'permission_callback' => $this->permission( $resource, 'read' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => function ( $request ) use ( $type ) {
						return $this->set_custom_field( $request, $type );
					},
					'permission_callback' => $this->permission( $resource, 'write' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => function ( $request ) use ( $type ) {
						return $this->delete_custom_field( $request, $type );
					},
					'permission_callback' => $this->permission( $resource, 'write' ),
				),
			)
		);

		// External sources.
		register_rest_route(
			$this->namespace,
			$base . '/external-sources',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => function ( $request ) use ( $type ) {
						return $this->get_external_sources( $request, $type );
					},
					'permission_callback' => $this->permission( $resource, 'read' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => function ( $request ) use ( $type ) {
						return $this->add_external_source( $request, $type );
					},
					'permission_callback' => $this->permission( $resource, 'write' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/external-sources/(?P<ext_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => function ( $request ) use ( $type ) {
						return $this->delete_external_source( $request, $type );
					},
					'permission_callback' => $this->permission( $resource, 'write' ),
				),
			)
		);

		// Object links.
		register_rest_route(
			$this->namespace,
			$base . '/links',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => function ( $request ) use ( $type ) {
						return $this->get_links( $request, $type );
					},
					'permission_callback' => $this->permission( $resource, 'read' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => function ( $request ) use ( $type ) {
						return $this->add_link( $request, $type );
					},
					'permission_callback' => $this->permission( $resource, 'write' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/links/(?P<object_type>[\d]+)/(?P<object_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => function ( $request ) use ( $type ) {
						return $this->delete_link( $request, $type );
					},
					'permission_callback' => $this->permission( $resource, 'write' ),
				),
			)
		);
	}

	/**
	 * List external sources of an object.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param int             $type    Object type.
	 * @return \WP_REST_Response
	 */
	public function get_external_sources( $request, $type ) {
		global $wpdb, $ZBSCRM_t;

		if ( ! isset( $ZBSCRM_t['externalsources'] ) ) {
			return rest_ensure_response( array() );
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$ZBSCRM_t['externalsources']}` WHERE zbss_objtype = %d AND zbss_objid = %d ORDER BY ID ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $type,
				(int) $request->get_param( 'id' )
			),
			ARRAY_A
		);

		return rest_ensure_response( array_map( array( $this, 'format_external_source' ), (array) $rows ) );
	}

	/**
	 * Add an external source to an object.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param int             $type    Object type.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function add_external_source( $request, $type ) {
		$dal = $this->get_dal();

		if ( ! $dal ) {
			return $this->error( 'jpcrm_rest_unknown_error', __( 'CRM data layer is unavailable.', 'jetpack-crm-rest-api-improved' ), 500 );
		}

		$params = $this->payload( $request );
		$source = isset( $params['source'] ) ? sanitize_text_field( (string) $params['source'] ) : '';
		$uid    = isset( $params['uid'] ) ? sanitize_text_field( (string) $params['uid'] ) : '';

		if ( '' === $source || '' === $uid ) {
			return $this->error( 'jpcrm_rest_validation_error', __( 'Нужны source и uid.', 'jetpack-crm-rest-api-improved' ), 422 );
		}

		$result = $dal->addUpdateExternalSource(
			array(
				'id'   => -1,
				'data' => array(
					'objectType' => (int) $type,
					'objectID'   => (int) $request->get_param( 'id' ),
					'source'     => $source,
					'uid'        => $uid,
					'origin'     => isset( $params['origin'] ) ? sanitize_text_field( (string) $params['origin'] ) : '',
				),
			)
		);

		if ( ! $result ) {
			return $this->error( 'jpcrm_rest_validation_error', __( 'Не удалось добавить внешний источник.', 'jetpack-crm-rest-api-improved' ), 422 );
		}

		return $this->get_external_sources( $request, $type );
	}

	/**
	 * Delete an external source by its line id.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param int             $type    Object type.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_external_source( $request, $type ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$dal = $this->get_dal();

		if ( ! $dal ) {
			return $this->error( 'jpcrm_rest_unknown_error', __( 'CRM data layer is unavailable.', 'jetpack-crm-rest-api-improved' ), 500 );
		}

		$ext_id = (int) $request->get_param( 'ext_id' );

		$result = $dal->deleteExternalSource( array( 'id' => $ext_id ) );

		if ( ! $result ) {
			return $this->error( 'jpcrm_rest_unknown_error', __( 'Не удалось удалить внешний источник.', 'jetpack-crm-rest-api-improved' ), 500 );
		}

		return rest_ensure_response( array( 'deleted' => true, 'ext_id' => $ext_id ) );
	}

	/**
	 * List object links (both directions).
	 *
	 * @param WP_REST_Request $request Request.
	 * @param int             $type    Object type.
	 * @return \WP_REST_Response
	 */
	public function get_links( $request, $type ) {
		global $wpdb, $ZBSCRM_t;

		if ( ! isset( $ZBSCRM_t['objlinks'] ) ) {
			return rest_ensure_response( array() );
		}

		$id      = (int) $request->get_param( 'id' );
		$object_type = $request->get_param( 'object_type' );
		$links   = array();

		$from_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$ZBSCRM_t['objlinks']}` WHERE zbsol_objtype_from = %d AND zbsol_objid_from = %d ORDER BY ID ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $type,
				$id
			),
			ARRAY_A
		);

		foreach ( (array) $from_rows as $row ) {
			$links[] = array(
				'link_id'     => (int) $row['ID'],
				'direction'   => 'from',
				'object_type' => (int) $row['zbsol_objtype_to'],
				'object_id'   => (int) $row['zbsol_objid_to'],
			);
		}

		$to_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$ZBSCRM_t['objlinks']}` WHERE zbsol_objtype_to = %d AND zbsol_objid_to = %d ORDER BY ID ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $type,
				$id
			),
			ARRAY_A
		);

		foreach ( (array) $to_rows as $row ) {
			$links[] = array(
				'link_id'     => (int) $row['ID'],
				'direction'   => 'to',
				'object_type' => (int) $row['zbsol_objtype_from'],
				'object_id'   => (int) $row['zbsol_objid_from'],
			);
		}

		if ( null !== $object_type && '' !== $object_type ) {
			$object_type = (int) $object_type;
			$links       = array_values(
				array_filter(
					$links,
					function ( $link ) use ( $object_type ) {
						return $link['object_type'] === $object_type;
					}
				)
			);
		}

		return rest_ensure_response( $links );
	}

	/**
	 * Create an object link.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param int             $type    Object type.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function add_link( $request, $type ) {
		$dal = $this->get_dal();

		if ( ! $dal ) {
			return $this->error( 'jpcrm_rest_unknown_error', __( 'CRM data layer is unavailable.', 'jetpack-crm-rest-api-improved' ), 500 );
		}

		$params      = $this->payload( $request );
		$object_type = isset( $params['object_type'] ) ? (int) $params['object_type'] : 0;
		$object_id   = isset( $params['object_id'] ) ? (int) $params['object_id'] : 0;

		if ( $object_type <= 0 || $object_id <= 0 ) {
			return $this->error( 'jpcrm_rest_validation_error', __( 'Нужны object_type и object_id.', 'jetpack-crm-rest-api-improved' ), 422 );
		}

		$result = $dal->addUpdateObjLink(
			array(
				'id'   => -1,
				'data' => array(
					'objtypefrom' => (int) $type,
					'objtypeto'   => $object_type,
					'objfromid'   => (int) $request->get_param( 'id' ),
					'objtoid'     => $object_id,
				),
			)
		);

		if ( ! $result ) {
			return $this->error( 'jpcrm_rest_validation_error', __( 'Не удалось создать связь.', 'jetpack-crm-rest-api-improved' ), 422 );
		}

		return rest_ensure_response(
			array(
				'created'     => true,
				'link_id'     => (int) $result,
				'object_type' => $object_type,
				'object_id'   => $object_id,
			)
		);
	}

	/**
	 * Delete an object link.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param int             $type    Object type.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_link( $request, $type ) {
		$dal = $this->get_dal();

		if ( ! $dal ) {
			return $this->error( 'jpcrm_rest_unknown_error', __( 'CRM data layer is unavailable.', 'jetpack-crm-rest-api-improved' ), 500 );
		}

		$object_type = (int) $request->get_param( 'object_type' );
		$object_id   = (int) $request->get_param( 'object_id' );

		$dal->deleteObjLinks(
			array(
				'objtypefrom' => (int) $type,
				'objtypeto'   => $object_type,
				'objfromid'   => (int) $request->get_param( 'id' ),
				'objtoid'     => $object_id,
			)
		);

		return rest_ensure_response( array( 'deleted' => true ) );
	}

	/**
	 * Format an external source row.
	 *
	 * @param mixed $row Row.
	 * @return array
	 */
	public function format_external_source( $row ) {
		$row = (array) $row;

		return array(
			'id'               => isset( $row['ID'] ) ? (int) $row['ID'] : 0,
			'source'           => isset( $row['zbss_source'] ) ? $row['zbss_source'] : '',
			'uid'              => isset( $row['zbss_uid'] ) ? $row['zbss_uid'] : '',
			'origin'           => isset( $row['zbss_origin'] ) ? $row['zbss_origin'] : '',
			'date_created_gmt' => \Jetpack_CRM_REST_API_Improved\Rest\Fields::uts_to_iso8601( isset( $row['zbss_created'] ) ? $row['zbss_created'] : 0 ),
		);
	}

	/**
	 * Build a permission callback.
	 *
	 * @param string $resource Resource name.
	 * @param string $action   read|write.
	 * @return callable
	 */
	private function permission( $resource, $action ) {
		return function ( $request ) use ( $resource, $action ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
			if ( ! is_user_logged_in() ) {
				return $this->error_unauthorized();
			}

			if ( ! Permissions::can( $resource, $action ) ) {
				return $this->error_forbidden();
			}

			return true;
		};
	}

	/**
	 * List tags of an object.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param int             $type    Object type.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_tags( $request, $type ) {
		$dal = $this->get_dal();

		if ( ! $dal ) {
			return $this->error( 'jpcrm_rest_unknown_error', __( 'CRM data layer is unavailable.', 'jetpack-crm-rest-api-improved' ), 500 );
		}

		$tags = $dal->getTagsForObjID(
			array(
				'objtypeid' => (int) $type,
				'objid'     => (int) $request->get_param( 'id' ),
			)
		);

		$tags = is_array( $tags ) ? $tags : array();

		return rest_ensure_response( array_map( array( $this, 'format_tag' ), $tags ) );
	}

	/**
	 * Set tags of an object.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param int             $type    Object type.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function set_tags( $request, $type ) {
		$dal = $this->get_dal();

		if ( ! $dal ) {
			return $this->error( 'jpcrm_rest_unknown_error', __( 'CRM data layer is unavailable.', 'jetpack-crm-rest-api-improved' ), 500 );
		}

		$params = $this->payload( $request );
		$tags   = isset( $params['tags'] ) && is_array( $params['tags'] ) ? $params['tags'] : array();
		$mode   = isset( $params['mode'] ) ? sanitize_text_field( (string) $params['mode'] ) : 'replace';

		if ( ! in_array( $mode, array( 'replace', 'append', 'remove' ), true ) ) {
			$mode = 'replace';
		}

		$result = $dal->addUpdateObjectTags(
			array(
				'objid'     => (int) $request->get_param( 'id' ),
				'objtype'   => (int) $type,
				'tag_input' => $tags,
				'mode'      => $mode,
			)
		);

		if ( false === $result ) {
			return $this->error( 'jpcrm_rest_validation_error', __( 'Не удалось обновить теги.', 'jetpack-crm-rest-api-improved' ), 422 );
		}

		return $this->get_tags( $request, $type );
	}

	/**
	 * Remove one tag from an object.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param int             $type    Object type.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_tag( $request, $type ) {
		$dal = $this->get_dal();

		if ( ! $dal ) {
			return $this->error( 'jpcrm_rest_unknown_error', __( 'CRM data layer is unavailable.', 'jetpack-crm-rest-api-improved' ), 500 );
		}

		$dal->deleteTagObjLink(
			array(
				'objtype' => (int) $type,
				'objid'   => (int) $request->get_param( 'id' ),
				'tagid'   => (int) $request->get_param( 'tag' ),
			)
		);

		return rest_ensure_response(
			array(
				'deleted'  => true,
				'tag_id'   => (int) $request->get_param( 'tag' ),
			)
		);
	}

	/**
	 * Get all meta (or one meta value) of an object.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param int             $type    Object type.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_meta( $request, $type ) {
		$id  = (int) $request->get_param( 'id' );
		$key = $request->get_param( 'key' );

		if ( $key ) {
			$value = $this->read_meta( $type, $id, $key );

			if ( null === $value ) {
				return $this->error_not_found();
			}

			return rest_ensure_response( array( 'key' => $key, 'value' => $value ) );
		}

		return rest_ensure_response( array( 'meta' => $this->read_meta( $type, $id ) ) );
	}

	/**
	 * Set a meta value.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param int             $type    Object type.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function set_meta( $request, $type ) {
		$dal = $this->get_dal();

		if ( ! $dal ) {
			return $this->error( 'jpcrm_rest_unknown_error', __( 'CRM data layer is unavailable.', 'jetpack-crm-rest-api-improved' ), 500 );
		}

		$key    = (string) $request->get_param( 'key' );
		$params = $this->payload( $request );

		if ( '' === $key ) {
			return $this->error( 'jpcrm_rest_validation_error', __( 'Аргумент key обязателен.', 'jetpack-crm-rest-api-improved' ), 400 );
		}

		if ( ! array_key_exists( 'value', $params ) ) {
			return $this->error( 'jpcrm_rest_validation_error', __( 'Аргумент value обязателен.', 'jetpack-crm-rest-api-improved' ), 422 );
		}

		$result = $dal->addUpdateMeta(
			array(
				'id'   => -1,
				'data' => array(
					'objtype' => (int) $type,
					'objid'   => (int) $request->get_param( 'id' ),
					'key'     => $key,
					'val'     => maybe_serialize( $params['value'] ),
				),
			)
		);

		if ( ! $result ) {
			return $this->error( 'jpcrm_rest_validation_error', __( 'Не удалось сохранить meta.', 'jetpack-crm-rest-api-improved' ), 422 );
		}

		return rest_ensure_response( array( 'key' => $key, 'value' => $params['value'] ) );
	}

	/**
	 * Delete a meta value.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param int             $type    Object type.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_meta( $request, $type ) {
		$dal = $this->get_dal();

		if ( ! $dal ) {
			return $this->error( 'jpcrm_rest_unknown_error', __( 'CRM data layer is unavailable.', 'jetpack-crm-rest-api-improved' ), 500 );
		}

		$key    = (string) $request->get_param( 'key' );
		$result = $dal->deleteMeta(
			array(
				'objtype' => (int) $type,
				'objid'   => (int) $request->get_param( 'id' ),
				'key'     => $key,
			)
		);

		if ( 0 === (int) $result ) {
			return $this->error_not_found();
		}

		return rest_ensure_response( array( 'deleted' => true, 'key' => $key ) );
	}

	/**
	 * Get all custom field values of an object.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param int             $type    Object type.
	 * @return \WP_REST_Response
	 */
	public function get_custom_fields( $request, $type ) {
		return rest_ensure_response( array( 'custom_fields' => $this->read_custom_fields( $type, (int) $request->get_param( 'id' ) ) ) );
	}

	/**
	 * Get one custom field value.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param int             $type    Object type.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_custom_field( $request, $type ) {
		$key   = (string) $request->get_param( 'key' );
		$value = $this->read_custom_fields( $type, (int) $request->get_param( 'id' ), $key );

		if ( null === $value ) {
			return $this->error_not_found();
		}

		return rest_ensure_response( array( 'key' => $key, 'value' => $value ) );
	}

	/**
	 * Set a single custom field value.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param int             $type    Object type.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function set_custom_field( $request, $type ) {
		$key    = (string) $request->get_param( 'key' );
		$params = $this->payload( $request );

		if ( '' === $key || ! array_key_exists( 'value', $params ) ) {
			return $this->error( 'jpcrm_rest_validation_error', __( 'Нужны key и value.', 'jetpack-crm-rest-api-improved' ), 422 );
		}

		$result = $this->write_custom_field( $type, (int) $request->get_param( 'id' ), $key, $params['value'] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'key' => $key, 'value' => $params['value'] ) );
	}

	/**
	 * Set multiple custom field values.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param int             $type    Object type.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function set_custom_fields( $request, $type ) {
		$params = $this->payload( $request );
		$values = isset( $params['values'] ) && is_array( $params['values'] ) ? $params['values'] : null;

		if ( ! is_array( $values ) ) {
			return $this->error( 'jpcrm_rest_validation_error', __( 'Аргумент values обязателен и должен быть объектом.', 'jetpack-crm-rest-api-improved' ), 422 );
		}

		$id = (int) $request->get_param( 'id' );

		foreach ( $values as $key => $value ) {
			$result = $this->write_custom_field( $type, $id, (string) $key, $value );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return rest_ensure_response( array( 'custom_fields' => $this->read_custom_fields( $type, $id ) ) );
	}

	/**
	 * Delete one custom field value.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param int             $type    Object type.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_custom_field( $request, $type ) {
		global $wpdb, $ZBSCRM_t;

		$key = (string) $request->get_param( 'key' );

		if ( ! isset( $ZBSCRM_t['customfields'] ) ) {
			return $this->error( 'jpcrm_rest_unknown_error', __( 'Хранилище кастомных полей недоступно.', 'jetpack-crm-rest-api-improved' ), 500 );
		}

		$deleted = $wpdb->delete(
			$ZBSCRM_t['customfields'],
			array(
				'zbscf_objtype' => (int) $type,
				'zbscf_objid'   => (int) $request->get_param( 'id' ),
				'zbscf_objkey'  => $key,
			),
			array( '%d', '%d', '%s' )
		);

		if ( ! $deleted ) {
			return $this->error_not_found();
		}

		return rest_ensure_response( array( 'deleted' => true, 'key' => $key ) );
	}

	/**
	 * Write a custom field value through the DAL.
	 *
	 * @param int    $type  Object type.
	 * @param int    $id    Object id.
	 * @param string $key   Field key.
	 * @param mixed  $value Value.
	 * @return true|WP_Error
	 */
	private function write_custom_field( $type, $id, $key, $value ) {
		$dal = $this->get_dal();

		if ( ! $dal ) {
			return $this->error( 'jpcrm_rest_unknown_error', __( 'CRM data layer is unavailable.', 'jetpack-crm-rest-api-improved' ), 500 );
		}

		$result = $dal->addUpdateCustomField(
			array(
				'id'   => -1,
				'data' => array(
					'objtype' => (int) $type,
					'objid'   => (int) $id,
					'objkey'  => (string) $key,
					'objval'  => is_array( $value ) ? maybe_serialize( $value ) : (string) $value,
				),
			)
		);

		if ( ! $result ) {
			return $this->error( 'jpcrm_rest_validation_error', __( 'Не удалось сохранить кастомное поле.', 'jetpack-crm-rest-api-improved' ), 422 );
		}

		return true;
	}

	/**
	 * Read meta values via wpdb.
	 *
	 * @param int         $type Object type.
	 * @param int         $id   Object id.
	 * @param string|null $key  Optional key.
	 * @return mixed
	 */
	private function read_meta( $type, $id, $key = null ) {
		global $wpdb, $ZBSCRM_t;

		if ( ! isset( $ZBSCRM_t['meta'] ) ) {
			return null;
		}

		$table = $ZBSCRM_t['meta'];

		if ( $key ) {
			$value = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT zbsm_val FROM `{$table}` WHERE zbsm_objtype = %d AND zbsm_objid = %d AND zbsm_key = %s ORDER BY ID DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					(int) $type,
					(int) $id,
					$key
				)
			);

			return null === $value ? null : maybe_unserialize( $value );
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT zbsm_key, zbsm_val FROM `{$table}` WHERE zbsm_objtype = %d AND zbsm_objid = %d ORDER BY ID ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $type,
				(int) $id
			),
			ARRAY_A
		);

		$out = array();

		foreach ( (array) $rows as $row ) {
			$out[ $row['zbsm_key'] ] = maybe_unserialize( $row['zbsm_val'] );
		}

		return $out;
	}

	/**
	 * Read custom field values via wpdb.
	 *
	 * @param int         $type Object type.
	 * @param int         $id   Object id.
	 * @param string|null $key  Optional key.
	 * @return mixed
	 */
	private function read_custom_fields( $type, $id, $key = null ) {
		global $wpdb, $ZBSCRM_t;

		if ( ! isset( $ZBSCRM_t['customfields'] ) ) {
			return $key ? null : array();
		}

		$table = $ZBSCRM_t['customfields'];

		if ( $key ) {
			$value = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT zbscf_objval FROM `{$table}` WHERE zbscf_objtype = %d AND zbscf_objid = %d AND zbscf_objkey = %s ORDER BY ID DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					(int) $type,
					(int) $id,
					$key
				)
			);

			return null === $value ? null : $value;
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT zbscf_objkey, zbscf_objval FROM `{$table}` WHERE zbscf_objtype = %d AND zbscf_objid = %d ORDER BY ID ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $type,
				(int) $id
			),
			ARRAY_A
		);

		$out = array();

		foreach ( (array) $rows as $row ) {
			$out[ $row['zbscf_objkey'] ] = $row['zbscf_objval'];
		}

		return $out;
	}

	/**
	 * Format a tag row.
	 *
	 * @param mixed $tag Tag row.
	 * @return array
	 */
	public function format_tag( $tag ) {
		$tag = (array) $tag;

		$id = isset( $tag['id'] ) ? $tag['id'] : ( isset( $tag['ID'] ) ? $tag['ID'] : 0 );

		return array(
			'id'          => (int) $id,
			'name'        => isset( $tag['name'] ) ? $tag['name'] : ( isset( $tag['zbstag_name'] ) ? $tag['zbstag_name'] : '' ),
			'slug'        => isset( $tag['slug'] ) ? $tag['slug'] : ( isset( $tag['zbstag_slug'] ) ? $tag['zbstag_slug'] : '' ),
			'object_type' => isset( $tag['objtype'] ) ? (int) $tag['objtype'] : ( isset( $tag['zbstag_objtype'] ) ? (int) $tag['zbstag_objtype'] : 0 ),
		);
	}

	/**
	 * Extract the JSON/body payload.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	private function payload( $request ) {
		$params = $request->get_json_params();

		if ( ! is_array( $params ) ) {
			$params = $request->get_body_params();
		}

		return is_array( $params ) ? $params : array();
	}
}
