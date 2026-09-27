<?php
namespace Jetpack_CRM_REST_API_Improved\Rest\Controllers;

use Jetpack_CRM_REST_API_Improved\Rest\ObjectController;
use Jetpack_CRM_REST_API_Improved\Rest\Fields;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class InvoicesController extends ObjectController {
	protected $rest_base      = 'invoices';
	protected $dal_layer     = 'invoices';
	protected $obj_type      = 4;
	protected $perm_resource = 'invoices';
	protected $method_get    = 'getInvoice';
	protected $method_list   = 'getInvoices';
	protected $method_save   = 'addUpdateInvoice';
	protected $method_delete = 'deleteInvoice';
	protected $method_count  = 'getInvoiceCount';
	protected $method_list_counts = true;

	protected function format_item( array $item ) { return Fields::invoice_from_dal( $item ); }
	protected function to_dal( array $payload ) { return Fields::invoice_to_dal( $payload ); }

	protected function single_args() {
		return array(
			'withLineItems'    => true,
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
		$tags = $request->get_param( 'tags' );
		if ( ! empty( $tags ) ) { $args['isTagged'] = array_map( 'intval', (array) $tags ); }
	}

	public function get_collection_params() {
		$params = parent::get_collection_params();
		$params['contact'] = array( 'description' => __( 'Filter by contact ID.', 'jetpack-crm-rest-api-improved' ), 'type' => 'integer' );
		$params['company'] = array( 'description' => __( 'Filter by company ID.', 'jetpack-crm-rest-api-improved' ), 'type' => 'integer' );
		return $params;
	}
}
