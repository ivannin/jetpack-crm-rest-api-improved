<?php
namespace Jetpack_CRM_REST_API_Improved\Rest\Controllers;

use Jetpack_CRM_REST_API_Improved\Rest\ObjectController;
use Jetpack_CRM_REST_API_Improved\Rest\Fields;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class TransactionsController extends ObjectController {
	protected $rest_base      = 'transactions';
	protected $dal_layer     = 'transactions';
	protected $obj_type      = 5;
	protected $perm_resource = 'transactions';
	protected $method_get    = 'getTransaction';
	protected $method_list   = 'getTransactions';
	protected $method_save   = 'addUpdateTransaction';
	protected $method_delete = 'deleteTransaction';
	protected $method_count  = 'getTransactionCount';
	protected $method_list_counts = true;

	protected function format_item( array $item ) { return Fields::transaction_from_dal( $item ); }
	protected function to_dal( array $payload ) { return Fields::transaction_to_dal( $payload ); }

	protected function single_args() {
		return array(
			'withCustomFields' => true,
			'withTags'         => true,
			'withOwner'        => true,
			'withAssigned'     => true,
			'ignoreowner'      => zeroBSCRM_DAL2_ignoreOwnership( $this->obj_type ),
		);
	}

	protected function sort_field_map() {
		return array( 'id' => 'ID', 'status' => 'zbsc_status', 'date' => 'zbsc_date', 'date_created_gmt' => 'zbsc_created' );
	}

	protected function apply_extra_filters( &$args, $request ) {
		$contact = $request->get_param( 'contact' );
		if ( ! empty( $contact ) ) { $args['assignedContact'] = (int) $contact; }
		$company = $request->get_param( 'company' );
		if ( ! empty( $company ) ) { $args['assignedCompany'] = (int) $company; }
		$invoice = $request->get_param( 'invoice' );
		if ( ! empty( $invoice ) ) { $args['assignedInvoice'] = (int) $invoice; }
		$type = $request->get_param( 'type' );
		if ( ! empty( $type ) ) { $args['hasStatus'] = sanitize_text_field( $type ); }
	}

	public function get_collection_params() {
		$params = parent::get_collection_params();
		$params['contact'] = array( 'description' => __( 'Filter by contact ID.', 'jetpack-crm-rest-api-improved' ), 'type' => 'integer' );
		$params['company'] = array( 'description' => __( 'Filter by company ID.', 'jetpack-crm-rest-api-improved' ), 'type' => 'integer' );
		$params['invoice'] = array( 'description' => __( 'Filter by invoice ID.', 'jetpack-crm-rest-api-improved' ), 'type' => 'integer' );
		$params['type']    = array( 'description' => __( 'Filter by transaction type.', 'jetpack-crm-rest-api-improved' ), 'type' => 'string' );
		return $params;
	}
}
