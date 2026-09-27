<?php
namespace Jetpack_CRM_REST_API_Improved\Rest\Controllers;

use Jetpack_CRM_REST_API_Improved\Rest\ObjectController;
use Jetpack_CRM_REST_API_Improved\Rest\Fields;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class QuoteTemplatesController extends ObjectController {
	protected $rest_base      = 'quote-templates';
	protected $dal_layer     = 'quotetemplates';
	protected $obj_type      = 12;
	protected $perm_resource = 'quote-templates';
	protected $method_get    = 'getQuotetemplate';
	protected $method_list   = 'getQuotetemplates';
	protected $method_save   = 'addUpdateQuotetemplate';
	protected $method_delete = 'deleteQuotetemplate';
	protected $method_count  = 'getQuotetemplateCount';
	protected $method_list_counts = true;

	protected function format_item( array $item ) {
		return array(
			'id'                => isset( $item['id'] ) ? (int) $item['id'] : 0,
			'owner'             => isset( $item['owner'] ) ? (int) $item['owner'] : 0,
			'title'             => isset( $item['title'] ) ? $item['title'] : '',
			'value'             => isset( $item['value'] ) ? (float) $item['value'] : 0,
			'date'              => isset( $item['date_str'] ) ? $item['date_str'] : '',
			'content'           => isset( $item['content'] ) ? $item['content'] : '',
			'notes'             => isset( $item['notes'] ) ? $item['notes'] : '',
			'currency'          => isset( $item['currency'] ) ? $item['currency'] : '',
			'date_created_gmt'  => Fields::uts_to_iso8601( isset( $item['createduts'] ) ? $item['createduts'] : 0 ),
			'date_modified_gmt' => Fields::uts_to_iso8601( isset( $item['lastupdated'] ) ? $item['lastupdated'] : 0 ),
		);
	}

	protected function to_dal( array $payload ) {
		$allowed = array( 'title', 'value', 'date_str', 'date', 'content', 'notes', 'currency' );
		$data = array();
		foreach ( $allowed as $key ) {
			if ( array_key_exists( $key, $payload ) ) { $data[ $key ] = $payload[ $key ]; }
		}
		return $data;
	}

	protected function sort_field_map() {
		return array( 'id' => 'ID', 'title' => 'zbsc_title', 'date_created_gmt' => 'zbsc_created' );
	}
}
