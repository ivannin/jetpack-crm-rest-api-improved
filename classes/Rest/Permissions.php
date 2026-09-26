<?php
/**
 * Capability mapping for REST resources.
 *
 * @package Jetpack_CRM_REST_API_Improved
 */

namespace Jetpack_CRM_REST_API_Improved\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps REST resources and actions to Jetpack CRM capabilities.
 */
class Permissions {

	/**
	 * Resource capability map.
	 *
	 * @var array<string,array<string,string>>
	 */
	const MAP = array(
		'contacts'        => array(
			'read'   => 'admin_zerobs_view_customers',
			'write'  => 'admin_zerobs_customers',
			'delete' => 'admin_zerobs_customers',
		),
		'companies'       => array(
			'read'   => 'admin_zerobs_view_customers',
			'write'  => 'admin_zerobs_customers',
			'delete' => 'admin_zerobs_customers',
		),
		'segments'        => array(
			'read'   => 'admin_zerobs_view_customers',
			'write'  => 'admin_zerobs_customers',
			'delete' => 'admin_zerobs_customers',
		),
		'quotes'          => array(
			'read'   => 'admin_zerobs_view_quotes',
			'write'  => 'admin_zerobs_quotes',
			'delete' => 'admin_zerobs_quotes',
		),
		'quote-templates' => array(
			'read'   => 'admin_zerobs_view_quotes',
			'write'  => 'admin_zerobs_quotes',
			'delete' => 'admin_zerobs_quotes',
		),
		'invoices'        => array(
			'read'   => 'admin_zerobs_view_invoices',
			'write'  => 'admin_zerobs_invoices',
			'delete' => 'admin_zerobs_invoices',
		),
		'transactions'    => array(
			'read'   => 'admin_zerobs_view_transactions',
			'write'  => 'admin_zerobs_transactions',
			'delete' => 'admin_zerobs_transactions',
		),
		'tasks'           => array(
			'read'   => 'admin_zerobs_view_events',
			'write'  => 'admin_zerobs_events',
			'delete' => 'admin_zerobs_events',
		),
		'task-reminders'  => array(
			'read'   => 'admin_zerobs_view_events',
			'write'  => 'admin_zerobs_events',
			'delete' => 'admin_zerobs_events',
		),
		'forms'           => array(
			'read'   => 'admin_zerobs_forms',
			'write'  => 'admin_zerobs_forms',
			'delete' => 'admin_zerobs_forms',
		),
		'logs'            => array(
			'read'   => 'admin_zerobs_logs_addedit',
			'write'  => 'admin_zerobs_logs_addedit',
			'delete' => 'admin_zerobs_logs_delete',
		),
		'line-items'      => array(
			'read'   => 'admin_zerobs_view_invoices',
			'write'  => 'admin_zerobs_invoices',
			'delete' => 'admin_zerobs_invoices',
		),
		'tags'            => array(
			'read'   => 'admin_zerobs_view_customers',
			'write'  => 'admin_zerobs_customers',
			'delete' => 'admin_zerobs_customers',
		),
		'custom-fields'   => array(
			'read'   => 'admin_zerobs_view_customers',
			'write'  => 'admin_zerobs_manage_options',
			'delete' => 'admin_zerobs_manage_options',
		),
		'meta'            => array(
			'read'   => 'admin_zerobs_view_customers',
			'write'  => 'admin_zerobs_customers',
			'delete' => 'admin_zerobs_customers',
		),
		'emails'          => array(
			'read'   => 'admin_zerobs_sendemails_contacts',
			'write'  => 'admin_zerobs_sendemails_contacts',
			'delete' => 'admin_zerobs_sendemails_contacts',
		),
		'email-threads'   => array(
			'read'   => 'admin_zerobs_sendemails_contacts',
			'write'  => 'admin_zerobs_sendemails_contacts',
			'delete' => 'admin_zerobs_sendemails_contacts',
		),
		'email-templates' => array(
			'read'   => 'admin_zerobs_manage_options',
			'write'  => 'admin_zerobs_manage_options',
			'delete' => 'admin_zerobs_manage_options',
		),
		'mail-delivery'   => array(
			'read'   => 'admin_zerobs_manage_options',
		),
		'status'          => array(
			'read' => 'admin_zerobs_usr',
		),
	);

	/**
	 * Get the capability required for a resource action.
	 *
	 * @param string $resource Resource name.
	 * @param string $action   One of read, write, delete.
	 * @return string|false
	 */
	public static function capability( $resource, $action ) {
		if ( ! isset( self::MAP[ $resource ] ) ) {
			return false;
		}

		$caps = self::MAP[ $resource ];

		if ( isset( $caps[ $action ] ) ) {
			return $caps[ $action ];
		}

		if ( 'delete' === $action && isset( $caps['write'] ) ) {
			return $caps['write'];
		}

		return false;
	}

	/**
	 * Whether the current user can perform an action on a resource.
	 *
	 * @param string $resource Resource name.
	 * @param string $action   One of read, write, delete.
	 * @return bool
	 */
	public static function can( $resource, $action ) {
		$capability = self::capability( $resource, $action );

		if ( ! $capability ) {
			return false;
		}

		if ( current_user_can( $capability ) ) {
			return true;
		}

		// Line items belong to either invoices or quotes; accept either capability.
		if ( 'line-items' === $resource ) {
			$alternates = array(
				'read'   => 'admin_zerobs_view_quotes',
				'write'  => 'admin_zerobs_quotes',
				'delete' => 'admin_zerobs_quotes',
			);

			if ( isset( $alternates[ $action ] ) && current_user_can( $alternates[ $action ] ) ) {
				return true;
			}
		}

		return false;
	}
}
