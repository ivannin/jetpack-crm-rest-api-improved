<?php
namespace Jetpack_CRM_REST_API_Improved\Rest\Controllers;

use Jetpack_CRM_REST_API_Improved\Rest\ObjectController;
use Jetpack_CRM_REST_API_Improved\Rest\Fields;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class QuotesController extends ObjectController {
	protected $rest_base      = 'quotes';
	protected $dal_layer     = 'quotes';
	protected $obj_type      = 3;
	protected $perm_resource = 'quotes';
	protected $method_get    = 'getQuote';
	protected $method_list   = 'getQuotes';
	protected $method_save   = 'addUpdateQuote';
	protected $method_delete = 'deleteQuote';
	protected $method_count  = 'getQuoteCount';

	/**
	 * Register standard routes plus the accept/unaccept actions.
	 *
	 * @return void
	 */
	public function register_routes() {
		parent::register_routes();

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/accept',
			array(
				'args'   => array(
					'id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'accept_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'unaccept_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Mark a quote as accepted.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function accept_item( $request ) {
		$layer = $this->get_layer();
		if ( ! $layer ) { return $this->dal_error(); }

		$id   = (int) $request->get_param( 'id' );
		$item = $layer->getQuote( $id, $this->single_args() );

		if ( ! $item ) { return $this->error_not_found(); }

		if ( ! function_exists( 'zeroBS_markQuoteAccepted' ) ) {
			return $this->error( 'jpcrm_rest_unknown_error', __( 'Функция принятия КП недоступна.', 'jetpack-crm-rest-api-improved' ), 500 );
		}

		$signed_by = $request->get_param( 'signed_by' );
		zeroBS_markQuoteAccepted( $id, is_string( $signed_by ) ? sanitize_text_field( $signed_by ) : '' );

		$item = $layer->getQuote( $id, $this->single_args() );

		return rest_ensure_response( $this->format_item( $item ) );
	}

	/**
	 * Unmark a quote as accepted.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function unaccept_item( $request ) {
		$layer = $this->get_layer();
		if ( ! $layer ) { return $this->dal_error(); }

		$id   = (int) $request->get_param( 'id' );
		$item = $layer->getQuote( $id, $this->single_args() );

		if ( ! $item ) { return $this->error_not_found(); }

		if ( ! function_exists( 'zeroBS_markQuoteUnAccepted' ) ) {
			return $this->error( 'jpcrm_rest_unknown_error', __( 'Функция отмены принятия КП недоступна.', 'jetpack-crm-rest-api-improved' ), 500 );
		}

		zeroBS_markQuoteUnAccepted( $id );

		$item = $layer->getQuote( $id, $this->single_args() );

		return rest_ensure_response( $this->format_item( $item ) );
	}

	protected function format_item( array $item ) { return Fields::quote_from_dal( $item ); }
	protected function to_dal( array $payload ) { return Fields::quote_to_dal( $payload ); }

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
		return array( 'id' => 'ID', 'status' => 'zbsc_status', 'date_created_gmt' => 'zbsc_created' );
	}

	protected function apply_extra_filters( &$args, $request ) {
		$contact = $request->get_param( 'contact' );
		if ( ! empty( $contact ) ) { $args['assignedContact'] = (int) $contact; }
		$company = $request->get_param( 'company' );
		if ( ! empty( $company ) ) { $args['assignedCompany'] = (int) $company; }
	}
}
