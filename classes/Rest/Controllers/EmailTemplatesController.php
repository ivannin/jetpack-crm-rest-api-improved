<?php
namespace Jetpack_CRM_REST_API_Improved\Rest\Controllers;
use Jetpack_CRM_REST_API_Improved\Rest\BaseController;
use Jetpack_CRM_REST_API_Improved\Rest\Fields;
use Jetpack_CRM_REST_API_Improved\Rest\MailAdapter;
use Jetpack_CRM_REST_API_Improved\Rest\Permissions;
use WP_REST_Server;
if ( ! defined( 'ABSPATH' ) ) { exit; }

class EmailTemplatesController extends BaseController {
	protected $rest_base = 'email-templates';

	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
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
		) );
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
			'args' => array(
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
		) );
	}

	public function perms_check( $request ) {
		if ( ! is_user_logged_in() ) { return $this->error_unauthorized(); }
		if ( ! Permissions::can( 'email-templates', 'read' ) ) { return $this->error_forbidden(); }
		return true;
	}

	public function perms_write_check( $request ) {
		if ( ! is_user_logged_in() ) { return $this->error_unauthorized(); }
		if ( ! Permissions::can( 'email-templates', 'write' ) ) { return $this->error_forbidden(); }
		return true;
	}

	public function get_items( $request ) {
		$templates = MailAdapter::get_email_templates();
		$items     = array_map( array( $this, 'format_template' ), $templates );
		return $this->collection_response( $items, count( $items ), -1 );
	}

	public function get_item( $request ) {
		$tpl = MailAdapter::get_email_template( (int) $request->get_param( 'id' ) );
		if ( ! $tpl ) { return $this->error_not_found(); }
		return rest_ensure_response( $this->format_template( $tpl ) );
	}

	/**
	 * Create a template.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_item( $request ) {
		$payload = $this->payload( $request );
		$result  = MailAdapter::save_email_template( $payload );

		if ( is_wp_error( $result ) ) { return $result; }

		$response = rest_ensure_response( $this->format_template( $result ) );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * Update a template.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( $request ) {
		$id = (int) $request->get_param( 'id' );

		if ( ! MailAdapter::get_email_template( $id ) ) {
			return $this->error_not_found();
		}

		$payload         = $this->payload( $request );
		$payload['id']   = $id;
		$result          = MailAdapter::save_email_template( $payload );

		if ( is_wp_error( $result ) ) { return $result; }

		return rest_ensure_response( $this->format_template( $result ) );
	}

	/**
	 * Delete a template.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_item( $request ) {
		$id = (int) $request->get_param( 'id' );

		if ( ! MailAdapter::get_email_template( $id ) ) {
			return $this->error_not_found();
		}

		$result = MailAdapter::delete_email_template( $id );

		if ( is_wp_error( $result ) ) { return $result; }

		return rest_ensure_response( array( 'deleted' => true, 'id' => $id ) );
	}

	protected function format_template( $tpl ) {
		$tpl = (array) $tpl;

		if ( empty( $tpl ) ) { return array(); }

		return array(
			'id'                => isset( $tpl['zbsmail_id'] ) ? (int) $tpl['zbsmail_id'] : 0,
			'active'            => isset( $tpl['zbsmail_active'] ) ? (bool) $tpl['zbsmail_active'] : false,
			'delivery_method'   => isset( $tpl['zbsmail_deliverymethod'] ) ? $tpl['zbsmail_deliverymethod'] : '',
			'from_name'         => isset( $tpl['zbsmail_fromname'] ) ? $tpl['zbsmail_fromname'] : '',
			'from_address'      => isset( $tpl['zbsmail_fromaddress'] ) ? $tpl['zbsmail_fromaddress'] : '',
			'reply_to'          => isset( $tpl['zbsmail_replyto'] ) ? $tpl['zbsmail_replyto'] : '',
			'cc_to'             => isset( $tpl['zbsmail_ccto'] ) ? $tpl['zbsmail_ccto'] : '',
			'bcc_to'            => isset( $tpl['zbsmail_bccto'] ) ? $tpl['zbsmail_bccto'] : '',
			'subject'           => isset( $tpl['zbsmail_subject'] ) ? $tpl['zbsmail_subject'] : '',
			'body'              => isset( $tpl['zbsmail_body'] ) ? $tpl['zbsmail_body'] : '',
			'date_created_gmt'  => Fields::uts_to_iso8601( isset( $tpl['zbsmail_created'] ) ? $tpl['zbsmail_created'] : 0 ),
			'date_modified_gmt' => Fields::uts_to_iso8601( isset( $tpl['zbsmail_lastupdated'] ) ? $tpl['zbsmail_lastupdated'] : 0 ),
		);
	}

	/**
	 * Extract the JSON/body payload.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array
	 */
	private function payload( $request ) {
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = $request->get_body_params();
		}
		return is_array( $payload ) ? $payload : array();
	}
}
