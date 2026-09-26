<?php
namespace Jetpack_CRM_REST_API_Improved\Rest\Controllers;

use Jetpack_CRM_REST_API_Improved\Rest\ObjectController;
use Jetpack_CRM_REST_API_Improved\Rest\Fields;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class FormsController extends ObjectController {
	protected $rest_base      = 'forms';
	protected $dal_layer     = 'forms';
	protected $obj_type      = 7;
	protected $perm_resource = 'forms';
	protected $method_get    = 'getForm';
	protected $method_list   = 'getForms';
	protected $method_save   = 'addUpdateForm';
	protected $method_delete = 'deleteForm';
	protected $method_count  = 'getFormCount';

	protected function format_item( array $item ) {
		return array(
			'id'               => isset( $item['id'] ) ? (int) $item['id'] : 0,
			'owner'            => isset( $item['owner'] ) ? (int) $item['owner'] : 0,
			'title'            => isset( $item['title'] ) ? $item['title'] : '',
			'style'            => isset( $item['style'] ) ? $item['style'] : '',
			'views'            => isset( $item['views'] ) ? (int) $item['views'] : 0,
			'conversions'      => isset( $item['conversions'] ) ? (int) $item['conversions'] : 0,
			'tags'             => ( isset( $item['tags'] ) && is_array( $item['tags'] ) ) ? $item['tags'] : array(),
			'date_created_gmt' => Fields::uts_to_iso8601( isset( $item['createduts'] ) ? $item['createduts'] : 0 ),
			'date_modified_gmt' => Fields::uts_to_iso8601( isset( $item['lastupdated'] ) ? $item['lastupdated'] : 0 ),
		);
	}

	protected function to_dal( array $payload ) {
		$allowed = array( 'title', 'style', 'redir_url', 'font',
			'label_header', 'label_subheader', 'label_firstname', 'label_lastname', 'label_email', 'label_message',
			'label_button', 'label_successmsg', 'label_spammsg',
			'include_terms_check', 'terms_url',
			'colour_bg', 'colour_font', 'colour_emphasis',
		);
		$data = array();
		foreach ( $allowed as $key ) {
			if ( array_key_exists( $key, $payload ) ) { $data[ $key ] = $payload[ $key ]; }
		}
		if ( array_key_exists( 'tags', $payload ) ) { $data['tags'] = $payload['tags']; }
		if ( array_key_exists( 'tag_mode', $payload ) ) { $data['tag_mode'] = $payload['tag_mode']; }
		return $data;
	}

	protected function sort_field_map() {
		return array( 'id' => 'ID', 'title' => 'zbsc_title', 'date_created_gmt' => 'zbsc_created' );
	}
}
