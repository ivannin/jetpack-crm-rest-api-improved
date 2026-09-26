<?php
/**
 * Contacts REST controller.
 *
 * @package Jetpack_CRM_REST_API_Improved
 */

namespace Jetpack_CRM_REST_API_Improved\Rest\Controllers;

use Jetpack_CRM_REST_API_Improved\Plugin;
use Jetpack_CRM_REST_API_Improved\Rest\BaseController;
use Jetpack_CRM_REST_API_Improved\Rest\Fields;
use Jetpack_CRM_REST_API_Improved\Rest\Permissions;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles CRUD for CRM contacts.
 */
class ContactsController extends BaseController {

	/**
	 * REST base.
	 *
	 * @var string
	 */
	protected $rest_base = 'contacts';

	/**
	 * Plugin instance.
	 *
	 * @var Plugin|null
	 */
	private $plugin;

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
						'description' => __( 'Уникальный идентификатор контакта.', 'jetpack-crm-rest-api-improved' ),
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
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Collection query parameters.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		return array(
			'page'      => array(
				'description' => __( 'Номер страницы (с 1).', 'jetpack-crm-rest-api-improved' ),
				'type'        => 'integer',
				'minimum'     => 1,
			),
			'per_page'  => array(
				'description' => __( 'Количество записей на странице. -1 — вернуть все.', 'jetpack-crm-rest-api-improved' ),
				'type'        => 'integer',
			),
			'offset'    => array(
				'description' => __( 'Смещение выборки.', 'jetpack-crm-rest-api-improved' ),
				'type'        => 'integer',
				'minimum'     => 0,
			),
			'search'    => array(
				'description' => __( 'Поисковая фраза.', 'jetpack-crm-rest-api-improved' ),
				'type'        => 'string',
			),
			's'         => array(
				'description' => __( 'Псевдоним search.', 'jetpack-crm-rest-api-improved' ),
				'type'        => 'string',
			),
			'orderby'   => array(
				'description' => __( 'Поле сортировки.', 'jetpack-crm-rest-api-improved' ),
				'type'        => 'string',
				'enum'        => array( 'id', 'email', 'first_name', 'last_name', 'status', 'date_created_gmt', 'date_modified_gmt' ),
				'default'     => 'id',
			),
			'order'     => array(
				'description' => __( 'Направление сортировки.', 'jetpack-crm-rest-api-improved' ),
				'type'        => 'string',
				'enum'        => array( 'asc', 'desc' ),
				'default'     => 'desc',
			),
			'status'    => array(
				'description' => __( 'Фильтр по статусу контакта.', 'jetpack-crm-rest-api-improved' ),
				'type'        => 'string',
			),
			'owner'     => array(
				'description' => __( 'Фильтр по владельцу (WP user ID).', 'jetpack-crm-rest-api-improved' ),
				'type'        => 'integer',
			),
			'owned_by'  => array(
				'description' => __( 'Псевдоним owner.', 'jetpack-crm-rest-api-improved' ),
				'type'        => 'integer',
			),
			'company'   => array(
				'description' => __( 'Фильтр по связанной компании.', 'jetpack-crm-rest-api-improved' ),
				'type'        => 'integer',
			),
			'tags'      => array(
				'description' => __( 'Фильтр по тегам.', 'jetpack-crm-rest-api-improved' ),
				'type'        => 'array',
				'items'       => array( 'type' => 'integer' ),
			),
			'has_email' => array(
				'description' => __( 'Только контакты с email.', 'jetpack-crm-rest-api-improved' ),
				'type'        => 'boolean',
			),
		);
	}

	/**
	 * Permissions for listing contacts.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function get_items_permissions_check( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		return $this->check( 'read' );
	}

	/**
	 * Permissions for reading a contact.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function get_item_permissions_check( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		return $this->check( 'read' );
	}

	/**
	 * Permissions for creating a contact.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function create_item_permissions_check( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		return $this->check( 'write' );
	}

	/**
	 * Permissions for updating a contact.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function update_item_permissions_check( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		return $this->check( 'write' );
	}

	/**
	 * Permissions for deleting a contact.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function delete_item_permissions_check( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		return $this->check( 'delete' );
	}

	/**
	 * List contacts.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_items( $request ) {
		$dal = $this->get_dal();

		if ( ! $dal ) {
			return $this->error( 'jpcrm_rest_unknown_error', __( 'CRM data layer is unavailable.', 'jetpack-crm-rest-api-improved' ), 500 );
		}

		$pagination = $this->parse_pagination( $request );

		$contacts = $dal->contacts->getContacts( $this->build_query_args( $request, $pagination ) );

		if ( ! is_array( $contacts ) ) {
			$contacts = array();
		}

		$items = array_map( array( Fields::class, 'contact_from_dal' ), $contacts );

		if ( $pagination['slice'] > 0 ) {
			$items = array_slice( $items, $pagination['slice'] );
		}

		$count_args = $this->build_query_args( $request, array( 'page' => -1, 'per_page' => -1 ) );
		$total      = (int) $dal->contacts->getContactCount( $count_args );

		return $this->collection_response( $items, $total, $pagination['per_page'] );
	}

	/**
	 * Read a single contact.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( $request ) {
		$dal = $this->get_dal();

		if ( ! $dal ) {
			return $this->error( 'jpcrm_rest_unknown_error', __( 'CRM data layer is unavailable.', 'jetpack-crm-rest-api-improved' ), 500 );
		}

		$contact = $dal->contacts->getContact( (int) $request->get_param( 'id' ), $this->get_single_args() );

		if ( ! $contact ) {
			return $this->error_not_found();
		}

		return rest_ensure_response( Fields::contact_from_dal( $contact ) );
	}

	/**
	 * Create a contact.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_item( $request ) {
		$dal = $this->get_dal();

		if ( ! $dal ) {
			return $this->error( 'jpcrm_rest_unknown_error', __( 'CRM data layer is unavailable.', 'jetpack-crm-rest-api-improved' ), 500 );
		}

		$payload = $this->get_payload( $request );
		$error   = $this->validate_payload( $payload );

		if ( is_wp_error( $error ) ) {
			return $error;
		}

		$owner  = isset( $payload['owner'] ) ? (int) $payload['owner'] : -1;
		$result = $dal->contacts->addUpdateContact(
			array(
				'id'    => -1,
				'owner' => $owner,
				'data'  => Fields::contact_to_dal( $payload ),
			)
		);

		if ( ! $result ) {
			return $this->error( 'jpcrm_rest_validation_error', __( 'Не удалось создать контакт.', 'jetpack-crm-rest-api-improved' ), 422 );
		}

		$contact = $dal->contacts->getContact( (int) $result, $this->get_single_args() );

		$response = rest_ensure_response( Fields::contact_from_dal( $contact ) );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( $this->namespace . '/' . $this->rest_base . '/' . (int) $result ) );

		return $response;
	}

	/**
	 * Update a contact.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( $request ) {
		$dal = $this->get_dal();

		if ( ! $dal ) {
			return $this->error( 'jpcrm_rest_unknown_error', __( 'CRM data layer is unavailable.', 'jetpack-crm-rest-api-improved' ), 500 );
		}

		$id = (int) $request->get_param( 'id' );

		if ( ! $dal->contacts->getContact( $id, array( 'onlyID' => true ) ) ) {
			return $this->error_not_found();
		}

		$payload = $this->get_payload( $request );
		$error   = $this->validate_payload( $payload, false );

		if ( is_wp_error( $error ) ) {
			return $error;
		}

		$args = array(
			'id'   => $id,
			'data' => Fields::contact_to_dal( $payload ),
		);

		if ( isset( $payload['owner'] ) ) {
			$args['owner'] = (int) $payload['owner'];
		}

		if ( 'PATCH' === strtoupper( (string) $request->get_method() ) ) {
			$args['do_not_update_blanks'] = true;
		}

		$result = $dal->contacts->addUpdateContact( $args );

		if ( ! $result ) {
			return $this->error( 'jpcrm_rest_validation_error', __( 'Не удалось обновить контакт.', 'jetpack-crm-rest-api-improved' ), 422 );
		}

		$contact = $dal->contacts->getContact( $id, $this->get_single_args() );

		return rest_ensure_response( Fields::contact_from_dal( $contact ) );
	}

	/**
	 * Delete a contact.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_item( $request ) {
		$dal = $this->get_dal();

		if ( ! $dal ) {
			return $this->error( 'jpcrm_rest_unknown_error', __( 'CRM data layer is unavailable.', 'jetpack-crm-rest-api-improved' ), 500 );
		}

		$id      = (int) $request->get_param( 'id' );
		$contact = $dal->contacts->getContact( $id, $this->get_single_args() );

		if ( ! $contact ) {
			return $this->error_not_found();
		}

		$result = $dal->contacts->deleteContact( array( 'id' => $id ) );

		if ( false === $result ) {
			return $this->error( 'jpcrm_rest_unknown_error', __( 'Не удалось удалить контакт.', 'jetpack-crm-rest-api-improved' ), 500 );
		}

		return rest_ensure_response(
			array(
				'deleted'  => true,
				'previous' => Fields::contact_from_dal( $contact ),
			)
		);
	}

	/**
	 * Arguments used when fetching a single contact.
	 *
	 * @return array
	 */
	private function get_single_args() {
		return array(
			'withCustomFields' => true,
			'withTags'         => true,
			'withOwner'        => true,
			'ignoreowner'      => zeroBSCRM_DAL2_ignoreOwnership( ZBS_TYPE_CONTACT ),
		);
	}

	/**
	 * Build DAL query arguments from the request.
	 *
	 * @param WP_REST_Request $request    Request.
	 * @param array           $pagination Pagination descriptor.
	 * @return array
	 */
	private function build_query_args( $request, array $pagination ) {
		$args = array(
			'withCustomFields' => true,
			'withTags'         => true,
			'withOwner'        => true,
			'ignoreowner'      => zeroBSCRM_DAL2_ignoreOwnership( ZBS_TYPE_CONTACT ),
		);

		if ( isset( $pagination['page'] ) && -1 === (int) $pagination['page'] ) {
			$args['page']    = -1;
			$args['perPage'] = -1;
		} else {
			$args['page']    = (int) $pagination['page'];
			$args['perPage'] = (int) $pagination['per_page'];
		}

		$sort_map = array(
			'id'                => 'ID',
			'email'             => 'zbsc_email',
			'first_name'        => 'zbsc_fname',
			'last_name'         => 'zbsc_lname',
			'status'            => 'zbsc_status',
			'date_created_gmt'  => 'zbsc_created',
			'date_modified_gmt' => 'zbsc_lastupdated',
		);

		$orderby = $request->get_param( 'orderby' );
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

		$company = $request->get_param( 'company' );
		if ( ! empty( $company ) ) {
			$args['inCompany'] = (int) $company;
		}

		$tags = $request->get_param( 'tags' );
		if ( ! empty( $tags ) ) {
			$args['isTagged'] = array_map( 'intval', (array) $tags );
		}

		$has_email = $request->get_param( 'has_email' );
		if ( null !== $has_email && '' !== $has_email ) {
			$args['hasEmail'] = (bool) $has_email;
		}

		return $args;
	}

	/**
	 * Extract the JSON payload from the request.
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

	/**
	 * Validate a contact payload.
	 *
	 * @param array $payload      Payload.
	 * @param bool  $require_data Whether at least one field is required.
	 * @return true|\WP_Error
	 */
	private function validate_payload( array $payload, $require_data = true ) {
		if ( $require_data && empty( $payload ) ) {
			return $this->error( 'jpcrm_rest_validation_error', __( 'Пустой запрос.', 'jetpack-crm-rest-api-improved' ), 422 );
		}

		if ( isset( $payload['email'] ) && '' !== $payload['email'] && ! is_email( $payload['email'] ) ) {
			return $this->error( 'jpcrm_rest_validation_error', __( 'Некорректный email.', 'jetpack-crm-rest-api-improved' ), 422 );
		}

		return true;
	}

	/**
	 * Shared permission check.
	 *
	 * @param string $action One of read, write, delete.
	 * @return bool|\WP_Error
	 */
	private function check( $action ) {
		if ( ! is_user_logged_in() ) {
			return $this->error_unauthorized();
		}

		if ( ! Permissions::can( 'contacts', $action ) ) {
			return $this->error_forbidden();
		}

		return true;
	}
}
