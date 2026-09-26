<?php
namespace Jetpack_CRM_REST_API_Improved\Rest\Controllers;
use Jetpack_CRM_REST_API_Improved\Rest\BaseController;
use Jetpack_CRM_REST_API_Improved\Rest\MailAdapter;
use Jetpack_CRM_REST_API_Improved\Rest\Permissions;
use WP_REST_Server;
if ( ! defined( 'ABSPATH' ) ) { exit; }

class EmailsController extends BaseController {
	protected $rest_base = 'emails';

	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'perms_check' ),
				'args'                => $this->get_collection_params(),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'perms_write_check' ),
			),
		) );
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'perms_check' ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'perms_check' ),
			),
		) );
	}

	public function perms_check( $request ) {
		if ( ! is_user_logged_in() ) { return $this->error_unauthorized(); }
		if ( ! Permissions::can( 'emails', 'read' ) ) { return $this->error_forbidden(); }
		return true;
	}

	public function perms_write_check( $request ) {
		if ( ! is_user_logged_in() ) { return $this->error_unauthorized(); }
		if ( ! Permissions::can( 'emails', 'write' ) ) { return $this->error_forbidden(); }
		return true;
	}

	/**
	 * Send a new email to a contact.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_item( $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_body_params();
		}
		if ( ! is_array( $params ) ) {
			$params = array();
		}

		$contact_id      = isset( $params['contact_id'] ) ? (int) $params['contact_id'] : 0;
		$email           = isset( $params['email'] ) ? sanitize_email( (string) $params['email'] ) : '';
		$subject         = isset( $params['subject'] ) ? wp_strip_all_tags( (string) $params['subject'] ) : '';
		$content         = isset( $params['content'] ) ? (string) $params['content'] : '';
		$delivery_method = isset( $params['delivery_method'] ) && '' !== $params['delivery_method'] ? sanitize_text_field( (string) $params['delivery_method'] ) : -1;

		if ( '' === trim( $content ) ) {
			return $this->error( 'jpcrm_rest_validation_error', __( 'Аргумент content обязателен.', 'jetpack-crm-rest-api-improved' ), 422 );
		}

		if ( $contact_id <= 0 && '' === $email ) {
			return $this->error( 'jpcrm_rest_validation_error', __( 'Укажите contact_id или email.', 'jetpack-crm-rest-api-improved' ), 422 );
		}

		$result = MailAdapter::send_email( $contact_id, $email, $subject, $content, $delivery_method );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$response = rest_ensure_response(
			array(
				'sent'  => true,
				'email' => $result,
			)
		);
		$response->set_status( 201 );

		return $response;
	}

	public function get_items( $request ) {
		$args = array(
			'page'     => (int) ( $request->get_param( 'page' ) ?: 0 ),
			'per_page' => (int) ( $request->get_param( 'per_page' ) ?: 20 ),
		);
		$contact = $request->get_param( 'contact' );
		if ( ! empty( $contact ) ) { $args['contact_id'] = (int) $contact; }
		$status = $request->get_param( 'status' );
		if ( ! empty( $status ) ) { $args['status'] = sanitize_text_field( $status ); }
		$starred = $request->get_param( 'starred' );
		if ( null !== $starred && '' !== $starred ) { $args['starred'] = (bool) $starred; }
		$emails   = MailAdapter::get_emails( $args );
		$items    = array_map( array( MailAdapter::class, 'format_email' ), $emails );
		$total    = MailAdapter::count_emails( $args );
		$per_page = $args['per_page'] > 0 ? $args['per_page'] : $total;
		return $this->collection_response( $items, $total, $per_page );
	}

	public function get_item( $request ) {
		$email = MailAdapter::get_email( (int) $request->get_param( 'id' ) );
		if ( ! $email ) { return $this->error_not_found(); }
		return rest_ensure_response( MailAdapter::format_email( $email ) );
	}

	public function delete_item( $request ) {
		$id    = (int) $request->get_param( 'id' );
		$email = MailAdapter::get_email( $id );
		if ( ! $email ) { return $this->error_not_found(); }
		$result = MailAdapter::delete_email( $id );
		if ( ! $result ) {
			return $this->error( 'jpcrm_rest_unknown_error', __( 'Failed to delete email.', 'jetpack-crm-rest-api-improved' ), 500 );
		}
		return rest_ensure_response( array( 'deleted' => true, 'previous' => MailAdapter::format_email( $email ) ) );
	}

	public function get_collection_params() {
		return array(
			'page'     => array( 'description' => __( 'Current page.', 'jetpack-crm-rest-api-improved' ), 'type' => 'integer', 'minimum' => 0 ),
			'per_page' => array( 'description' => __( 'Items per page.', 'jetpack-crm-rest-api-improved' ), 'type' => 'integer' ),
			'contact'  => array( 'description' => __( 'Filter by contact ID.', 'jetpack-crm-rest-api-improved' ), 'type' => 'integer' ),
			'status'   => array( 'description' => __( 'Filter by status.', 'jetpack-crm-rest-api-improved' ), 'type' => 'string', 'enum' => array( 'inbox', 'sent' ) ),
			'starred'  => array( 'description' => __( 'Filter by starred.', 'jetpack-crm-rest-api-improved' ), 'type' => 'boolean' ),
		);
	}
}
