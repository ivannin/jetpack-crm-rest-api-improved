<?php
namespace Jetpack_CRM_REST_API_Improved\Rest\Controllers;

use Jetpack_CRM_REST_API_Improved\Rest\ObjectController;
use Jetpack_CRM_REST_API_Improved\Rest\Fields;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class TasksController extends ObjectController {
	protected $rest_base      = 'tasks';
	protected $dal_layer     = 'events';
	protected $obj_type      = 6;
	protected $perm_resource = 'tasks';
	protected $method_get    = 'getEvent';
	protected $method_list   = 'getEvents';
	protected $method_save   = 'addUpdateEvent';
	protected $method_delete = 'deleteEvent';
	protected $method_count  = 'getEventCount';
	protected $method_list_counts = true;

	protected function format_item( array $item ) { return Fields::task_from_dal( $item ); }
	protected function to_dal( array $payload ) { return Fields::task_to_dal( $payload ); }

	protected function single_args() {
		return array(
			'withReminders'    => true,
			'withCustomFields' => true,
			'withTags'         => true,
			'withAssigned'     => true,
			'ignoreowner'      => zeroBSCRM_DAL2_ignoreOwnership( $this->obj_type ),
		);
	}

	protected function sort_field_map() {
		return array( 'id' => 'ID', 'start' => 'zbsc_start', 'date_created_gmt' => 'zbsc_created' );
	}

	protected function apply_extra_filters( &$args, $request ) {
		$contact = $request->get_param( 'contact' );
		if ( ! empty( $contact ) ) { $args['assignedContact'] = (int) $contact; }
		$company = $request->get_param( 'company' );
		if ( ! empty( $company ) ) { $args['assignedCompany'] = (int) $company; }
		$complete = $request->get_param( 'complete' );
		if ( null !== $complete && '' !== $complete ) {
			if ( (bool) $complete ) {
				$args['isComplete'] = true;
			} else {
				$args['isIncomplete'] = true;
			}
		}
	}

	public function get_collection_params() {
		$params = parent::get_collection_params();
		$params['contact']  = array( 'description' => __( 'Filter by contact ID.', 'jetpack-crm-rest-api-improved' ), 'type' => 'integer' );
		$params['company']  = array( 'description' => __( 'Filter by company ID.', 'jetpack-crm-rest-api-improved' ), 'type' => 'integer' );
		$params['complete'] = array( 'description' => __( 'Filter by completion status.', 'jetpack-crm-rest-api-improved' ), 'type' => 'boolean' );
		return $params;
	}
}
