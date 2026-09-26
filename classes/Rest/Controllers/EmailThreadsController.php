<?php
/**
 * Email threads REST controller.
 *
 * @package Jetpack_CRM_REST_API_Improved
 */

namespace Jetpack_CRM_REST_API_Improved\Rest\Controllers;

use Jetpack_CRM_REST_API_Improved\Rest\BaseController;
use Jetpack_CRM_REST_API_Improved\Rest\MailAdapter;
use Jetpack_CRM_REST_API_Improved\Rest\Permissions;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles email threads (grouping of messages by `zbsmail_sender_thread`).
 */
class EmailThreadsController extends BaseController {

	/**
	 * REST base.
	 *
	 * @var string
	 */
	protected $rest_base = 'email-threads';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$id_arg = array(
			'id' => array(
				'description' => __( 'Идентификатор треда.', 'jetpack-crm-rest-api-improved' ),
				'type'        => 'integer',
				'required'    => true,
			),
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'perms_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				'args'   => $id_arg,
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'perms_check' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'perms_write_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/star',
			array(
				'args'   => $id_arg,
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'star_item' ),
					'permission_callback' => array( $this, 'perms_write_check' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'unstar_item' ),
					'permission_callback' => array( $this, 'perms_write_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/read',
			array(
				'args' => $id_arg,
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'read_item' ),
					'permission_callback' => array( $this, 'perms_write_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/reply',
			array(
				'args' => $id_arg,
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'reply_item' ),
					'permission_callback' => array( $this, 'perms_write_check' ),
				),
			)
		);
	}

	/**
	 * Read permission check.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function perms_check( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		if ( ! is_user_logged_in() ) {
			return $this->error_unauthorized();
		}

		if ( ! Permissions::can( 'email-threads', 'read' ) ) {
			return $this->error_forbidden();
		}

		return true;
	}

	/**
	 * Write permission check.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function perms_write_check( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		if ( ! is_user_logged_in() ) {
			return $this->error_unauthorized();
		}

		if ( ! Permissions::can( 'email-threads', 'write' ) ) {
			return $this->error_forbidden();
		}

		return true;
	}

	/**
	 * List threads.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_items( $request ) {
		$per_page = $request->get_param( 'per_page' );
		$per_page = ( null === $per_page || '' === $per_page ) ? 20 : (int) $per_page;
		$page     = (int) ( $request->get_param( 'page' ) ?: 0 );

		$threads = MailAdapter::get_threads(
			array(
				'page'     => $page,
				'per_page' => $per_page,
			)
		);

		$items = array_map( array( MailAdapter::class, 'format_email' ), $threads );

		return $this->collection_response( $items, MailAdapter::count_threads(), $per_page > 0 ? $per_page : 0 );
	}

	/**
	 * Read all messages in a thread.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( $request ) {
		$id       = (int) $request->get_param( 'id' );
		$messages = MailAdapter::get_thread_emails( $id );

		if ( empty( $messages ) ) {
			return $this->error_not_found();
		}

		return rest_ensure_response(
			array(
				'thread_id' => $id,
				'messages'  => array_map( array( MailAdapter::class, 'format_email' ), $messages ),
			)
		);
	}

	/**
	 * Delete a thread.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_item( $request ) {
		$id = (int) $request->get_param( 'id' );

		if ( empty( MailAdapter::get_thread_emails( $id ) ) ) {
			return $this->error_not_found();
		}

		if ( ! MailAdapter::delete_thread( $id ) ) {
			return $this->error( 'jpcrm_rest_unknown_error', __( 'Не удалось удалить тред.', 'jetpack-crm-rest-api-improved' ), 500 );
		}

		return rest_ensure_response(
			array(
				'deleted'   => true,
				'thread_id' => $id,
			)
		);
	}

	/**
	 * Star a thread.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function star_item( $request ) {
		$id = (int) $request->get_param( 'id' );

		if ( empty( MailAdapter::get_thread_emails( $id ) ) ) {
			return $this->error_not_found();
		}

		MailAdapter::star_thread( $id );

		return rest_ensure_response( array( 'thread_id' => $id, 'starred' => true ) );
	}

	/**
	 * Unstar a thread.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function unstar_item( $request ) {
		$id = (int) $request->get_param( 'id' );

		if ( empty( MailAdapter::get_thread_emails( $id ) ) ) {
			return $this->error_not_found();
		}

		MailAdapter::unstar_thread( $id );

		return rest_ensure_response( array( 'thread_id' => $id, 'starred' => false ) );
	}

	/**
	 * Mark a thread as read.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function read_item( $request ) {
		$id = (int) $request->get_param( 'id' );

		if ( empty( MailAdapter::get_thread_emails( $id ) ) ) {
			return $this->error_not_found();
		}

		MailAdapter::mark_thread_read( $id );

		return rest_ensure_response( array( 'thread_id' => $id, 'read' => true ) );
	}

	/**
	 * Reply within a thread.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function reply_item( $request ) {
		$id = (int) $request->get_param( 'id' );

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_body_params();
		}
		if ( ! is_array( $params ) ) {
			$params = array();
		}

		$content         = isset( $params['content'] ) ? (string) $params['content'] : '';
		$subject         = isset( $params['subject'] ) ? wp_strip_all_tags( (string) $params['subject'] ) : '';
		$delivery_method = isset( $params['delivery_method'] ) && '' !== $params['delivery_method'] ? sanitize_text_field( (string) $params['delivery_method'] ) : -1;

		if ( '' === trim( $content ) ) {
			return $this->error( 'jpcrm_rest_validation_error', __( 'Аргумент content обязателен.', 'jetpack-crm-rest-api-improved' ), 422 );
		}

		$result = MailAdapter::reply_thread( $id, $subject, $content, $delivery_method );

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
}
