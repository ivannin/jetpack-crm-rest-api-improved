<?php
/**
 * Task reminder REST controller.
 *
 * @package Jetpack_CRM_REST_API_Improved
 */

namespace Jetpack_CRM_REST_API_Improved\Rest\Controllers;

use Jetpack_CRM_REST_API_Improved\Rest\ObjectController;
use Jetpack_CRM_REST_API_Improved\Rest\Fields;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * CRUD for task reminders, plus nested `/tasks/{id}/reminders`.
 */
class TaskRemindersController extends ObjectController {
	protected $rest_base      = 'task-reminders';
	protected $dal_layer     = 'eventreminders';
	protected $obj_type      = 11;
	protected $perm_resource = 'task-reminders';
	protected $method_get    = 'getEventreminder';
	protected $method_list   = 'getEventreminders';
	protected $method_save   = 'addUpdateEventreminder';
	protected $method_delete = 'deleteEventreminder';
	protected $method_count  = 'getEventReminderCount';
	protected $method_list_counts = true;

	/**
	 * Register flat routes and the nested task routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		parent::register_routes();

		register_rest_route(
			$this->namespace,
			'/tasks/(?P<id>[\d]+)/reminders',
			array(
				'args'   => array(
					'id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_for_task' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_for_task' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
				),
			)
		);
	}

	/**
	 * List reminders of a task.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_for_task( $request ) {
		$layer = $this->get_layer();
		if ( ! $layer ) { return $this->dal_error(); }

		$reminders = $layer->getEventreminders(
			array(
				'event'       => (int) $request->get_param( 'id' ),
				'page'        => -1,
				'perPage'     => -1,
				'ignoreowner' => true,
			)
		);

		if ( ! is_array( $reminders ) ) { $reminders = array(); }

		return rest_ensure_response( array_map( array( $this, 'format_item' ), $reminders ) );
	}

	/**
	 * Create a reminder for a task.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_for_task( $request ) {
		$layer = $this->get_layer();
		if ( ! $layer ) { return $this->dal_error(); }

		$payload          = $this->get_payload( $request );
		$payload['event'] = (int) $request->get_param( 'id' );

		$result = $layer->{$this->method_save}(
			array(
				'id'    => -1,
				'owner' => isset( $payload['owner'] ) ? (int) $payload['owner'] : -1,
				'data'  => $this->to_dal( $payload ),
			)
		);

		if ( ! $result ) {
			return $this->error( 'jpcrm_rest_validation_error', __( 'Не удалось создать напоминание.', 'jetpack-crm-rest-api-improved' ), 422 );
		}

		$item     = $layer->getEventreminder( (int) $result );
		$response = rest_ensure_response( $this->format_item( $item ) );
		$response->set_status( 201 );

		return $response;
	}

	protected function format_item( array $item ) {
		return array(
			'id'               => isset( $item['id'] ) ? (int) $item['id'] : 0,
			'event'            => isset( $item['event'] ) ? (int) $item['event'] : 0,
			'remind_at'        => $this->to_iso( isset( $item['remind_at'] ) ? $item['remind_at'] : 0 ),
			'sent'             => isset( $item['sent'] ) ? (bool) $item['sent'] : false,
			'date_created_gmt' => $this->to_iso( isset( $item['created'] ) ? $item['created'] : 0 ),
		);
	}

	protected function to_dal( array $payload ) {
		$data = array();

		if ( isset( $payload['event'] ) ) {
			$data['event'] = (int) $payload['event'];
		}

		if ( isset( $payload['remind_at'] ) ) {
			$data['remind_at'] = $this->to_uts( $payload['remind_at'] );
		}

		if ( isset( $payload['sent'] ) ) {
			$data['sent'] = (int) (bool) $payload['sent'];
		}

		return $data;
	}

	/**
	 * Convert a value to ISO-8601 when it looks like a UTS.
	 *
	 * @param mixed $value Value.
	 * @return string|null
	 */
	private function to_iso( $value ) {
		if ( is_numeric( $value ) ) {
			return Fields::uts_to_iso8601( (int) $value );
		}

		return ( '' === $value || null === $value ) ? null : (string) $value;
	}

	/**
	 * Convert an ISO date or UTS into a Unix timestamp.
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	private function to_uts( $value ) {
		if ( is_numeric( $value ) ) {
			return (int) $value;
		}

		$ts = strtotime( (string) $value );

		return $ts ? $ts : 0;
	}
}
