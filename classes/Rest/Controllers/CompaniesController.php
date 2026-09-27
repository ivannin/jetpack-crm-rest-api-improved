<?php
/**
 * Companies REST controller.
 *
 * @package Jetpack_CRM_REST_API_Improved
 */

namespace Jetpack_CRM_REST_API_Improved\Rest\Controllers;

use Jetpack_CRM_REST_API_Improved\Rest\ObjectController;
use Jetpack_CRM_REST_API_Improved\Rest\Fields;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CompaniesController extends ObjectController {

	protected $rest_base     = 'companies';
	protected $dal_layer    = 'companies';
	protected $obj_type     = 2; // ZBS_TYPE_COMPANY
	protected $perm_resource = 'companies';

	protected $method_get    = 'getCompany';
	protected $method_list   = 'getCompanies';
	protected $method_save   = 'addUpdateCompany';
	protected $method_delete = 'deleteCompany';
	protected $method_count  = 'getCompanyCount';
	protected $method_list_counts = true;

	protected function format_item( array $item ) {
		return Fields::company_from_dal( $item );
	}

	protected function to_dal( array $payload ) {
		return Fields::company_to_dal( $payload );
	}

	protected function sort_field_map() {
		return array(
			'id'               => 'ID',
			'name'             => 'zbsc_name',
			'status'           => 'zbsc_status',
			'email'            => 'zbsc_email',
			'date_created_gmt' => 'zbsc_created',
		);
	}

	protected function single_args() {
		return array(
			'withCustomFields' => true,
			'withTags'         => true,
			'withOwner'        => true,
			'withContacts'     => true,
			'ignoreowner'      => zeroBSCRM_DAL2_ignoreOwnership( $this->obj_type ),
		);
	}

	protected function apply_extra_filters( &$args, $request ) {
		$company = $request->get_param( 'company' );
		if ( ! empty( $company ) ) {
			$args['hasContact'] = (int) $company;
		}

		$tags = $request->get_param( 'tags' );
		if ( ! empty( $tags ) ) {
			$args['isTagged'] = array_map( 'intval', (array) $tags );
		}
	}

	public function get_collection_params() {
		$params = parent::get_collection_params();
		$params['contact'] = array(
			'description' => __( 'Filter by linked contact ID.', 'jetpack-crm-rest-api-improved' ),
			'type'        => 'integer',
		);
		$params['tags'] = array(
			'description' => __( 'Filter by tags.', 'jetpack-crm-rest-api-improved' ),
			'type'        => 'array',
			'items'       => array( 'type' => 'integer' ),
		);
		return $params;
	}
}
