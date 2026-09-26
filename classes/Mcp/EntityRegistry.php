<?php
/**
 * Entity registry for MCP discovery.
 *
 * @package Jetpack_CRM_REST_API_Improved
 */

namespace Jetpack_CRM_REST_API_Improved\Mcp;

use Jetpack_CRM_REST_API_Improved\Rest\Permissions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Describes CRM entities, their REST paths, fields, filters and operations.
 */
class EntityRegistry {

	/**
	 * Return all entity specifications.
	 *
	 * @return array<string,array>
	 */
	public static function all() {
		$full = array( 'list', 'get', 'create', 'update', 'delete' );

		return array(
			'contacts'        => array(
				'name'                => 'contacts',
				'label'               => __( 'Контакты', 'jetpack-crm-rest-api-improved' ),
				'path'                => '/contacts',
				'permission_resource' => 'contacts',
				'operations'          => $full,
				'compact_fields'      => array( 'id', 'first_name', 'last_name', 'email', 'status' ),
				'required_on_create'  => array( 'email' ),
				'filters'             => self::filters( array( 'search', 'status', 'owner', 'company', 'tags', 'has_email' ) ),
				'fields'              => self::fields(
					array(
						'id'                      => 'integer',
						'owner'                   => 'integer',
						'status'                  => 'string',
						'email'                   => 'string',
						'prefix'                  => 'string',
						'first_name'              => 'string',
						'last_name'               => 'string',
						'full_name'               => 'string',
						'address'                 => 'object',
						'secondary_address'       => 'object',
						'telephones'              => 'object',
						'social'                  => 'object',
						'wp_user_id'              => 'integer',
						'avatar'                  => 'string',
						'aliases'                 => 'array',
						'tags'                    => 'array',
						'companies'               => 'array',
						'custom_fields'           => 'object',
						'date_created_gmt'        => 'string',
						'date_modified_gmt'       => 'string',
						'date_last_contacted_gmt' => 'string',
					)
				),
			),
			'companies'       => array(
				'name'                => 'companies',
				'label'               => __( 'Компании', 'jetpack-crm-rest-api-improved' ),
				'path'                => '/companies',
				'permission_resource' => 'companies',
				'operations'          => $full,
				'compact_fields'      => array( 'id', 'name', 'status', 'email' ),
				'required_on_create'  => array( 'name' ),
				'filters'             => self::filters( array( 'search', 'status', 'owner', 'tags' ) ),
				'fields'              => self::fields(
					array(
						'id'                      => 'integer',
						'owner'                   => 'integer',
						'status'                  => 'string',
						'name'                    => 'string',
						'email'                   => 'string',
						'address'                 => 'object',
						'secondary_address'       => 'object',
						'telephones'              => 'object',
						'social'                  => 'object',
						'tags'                    => 'array',
						'contacts'                => 'array',
						'custom_fields'           => 'object',
						'date_created_gmt'        => 'string',
						'date_modified_gmt'       => 'string',
						'date_last_contacted_gmt' => 'string',
					)
				),
			),
			'invoices'        => array(
				'name'                => 'invoices',
				'label'               => __( 'Счета', 'jetpack-crm-rest-api-improved' ),
				'path'                => '/invoices',
				'permission_resource' => 'invoices',
				'operations'          => $full,
				'compact_fields'      => array( 'id', 'number', 'status', 'total', 'date' ),
				'required_on_create'  => array(),
				'filters'             => self::filters( array( 'search', 'status', 'contact', 'company', 'date_after', 'date_before' ) ),
				'fields'              => self::fields(
					array(
						'id'                => 'integer',
						'owner'             => 'integer',
						'status'            => 'string',
						'number'            => 'string',
						'reference'         => 'string',
						'date'              => 'string',
						'due_date'          => 'string',
						'paid_date'         => 'string',
						'currency'          => 'string',
						'net'               => 'number',
						'discount'          => 'number',
						'shipping'          => 'number',
						'taxes'             => 'number',
						'total'             => 'number',
						'line_items'        => 'array',
						'contacts'          => 'array',
						'companies'         => 'array',
						'tags'              => 'array',
						'custom_fields'     => 'object',
						'date_created_gmt'  => 'string',
						'date_modified_gmt' => 'string',
					)
				),
			),
			'quotes'          => array(
				'name'                => 'quotes',
				'label'               => __( 'Коммерческие предложения', 'jetpack-crm-rest-api-improved' ),
				'path'                => '/quotes',
				'permission_resource' => 'quotes',
				'operations'          => $full,
				'compact_fields'      => array( 'id', 'title', 'status', 'value', 'date' ),
				'required_on_create'  => array( 'title' ),
				'filters'             => self::filters( array( 'search', 'status', 'contact', 'company' ) ),
				'fields'              => self::fields(
					array(
						'id'                => 'integer',
						'owner'             => 'integer',
						'title'             => 'string',
						'status'            => 'string',
						'currency'          => 'string',
						'value'             => 'number',
						'date'              => 'string',
						'template'          => 'string',
						'content'           => 'string',
						'notes'             => 'string',
						'send_attachments'  => 'boolean',
						'hash'              => 'string',
						'viewed_count'      => 'integer',
						'accepted'          => 'boolean',
						'date_accepted_gmt' => 'string',
						'line_items'        => 'array',
						'contacts'          => 'array',
						'companies'         => 'array',
						'tags'              => 'array',
						'custom_fields'     => 'object',
						'date_created_gmt'  => 'string',
						'date_modified_gmt' => 'string',
					)
				),
			),
			'transactions'    => array(
				'name'                => 'transactions',
				'label'               => __( 'Транзакции', 'jetpack-crm-rest-api-improved' ),
				'path'                => '/transactions',
				'permission_resource' => 'transactions',
				'operations'          => $full,
				'compact_fields'      => array( 'id', 'title', 'status', 'total', 'date' ),
				'required_on_create'  => array(),
				'filters'             => self::filters( array( 'search', 'status', 'type', 'contact', 'company', 'invoice' ) ),
				'fields'              => self::fields(
					array(
						'id'                => 'integer',
						'owner'             => 'integer',
						'status'            => 'string',
						'type'              => 'string',
						'reference'         => 'string',
						'origin'            => 'string',
						'parent'            => 'string',
						'title'             => 'string',
						'description'       => 'string',
						'date'              => 'string',
						'currency'          => 'string',
						'net'               => 'number',
						'fee'               => 'number',
						'discount'          => 'number',
						'shipping'          => 'number',
						'taxes'             => 'number',
						'total'             => 'number',
						'date_paid'         => 'string',
						'date_completed'    => 'string',
						'invoice'           => 'integer',
						'contacts'          => 'array',
						'companies'         => 'array',
						'tags'              => 'array',
						'custom_fields'     => 'object',
						'date_created_gmt'  => 'string',
						'date_modified_gmt' => 'string',
					)
				),
			),
			'tasks'           => array(
				'name'                => 'tasks',
				'label'               => __( 'Задачи и события', 'jetpack-crm-rest-api-improved' ),
				'path'                => '/tasks',
				'permission_resource' => 'tasks',
				'operations'          => $full,
				'compact_fields'      => array( 'id', 'title', 'start', 'complete' ),
				'required_on_create'  => array( 'title' ),
				'filters'             => self::filters( array( 'search', 'owner', 'contact', 'company' ) ),
				'fields'              => self::fields(
					array(
						'id'                => 'integer',
						'owner'             => 'integer',
						'title'             => 'string',
						'description'       => 'string',
						'start'             => 'string',
						'end'               => 'string',
						'complete'          => 'boolean',
						'show_on_portal'    => 'boolean',
						'show_on_calendar'  => 'boolean',
						'reminders'         => 'array',
						'contacts'          => 'array',
						'companies'         => 'array',
						'tags'              => 'array',
						'custom_fields'     => 'object',
						'date_created_gmt'  => 'string',
						'date_modified_gmt' => 'string',
					)
				),
			),
			'task-reminders'  => array(
				'name'                => 'task-reminders',
				'label'               => __( 'Напоминания задач', 'jetpack-crm-rest-api-improved' ),
				'path'                => '/task-reminders',
				'permission_resource' => 'task-reminders',
				'operations'          => $full,
				'compact_fields'      => array( 'id', 'event', 'remind_at', 'sent' ),
				'required_on_create'  => array( 'event', 'remind_at' ),
				'filters'             => self::filters( array( 'event' ) ),
				'fields'              => self::fields(
					array(
						'id'               => 'integer',
						'event'            => 'integer',
						'remind_at'        => 'string',
						'sent'             => 'boolean',
						'date_created_gmt' => 'string',
					)
				),
			),
			'logs'            => array(
				'name'                => 'logs',
				'label'               => __( 'Логи и активность', 'jetpack-crm-rest-api-improved' ),
				'path'                => '/logs',
				'permission_resource' => 'logs',
				'operations'          => $full,
				'compact_fields'      => array( 'id', 'object_type', 'object_id', 'type', 'short_description', 'date_created_gmt' ),
				'required_on_create'  => array( 'object_type', 'object_id' ),
				'filters'             => self::filters( array( 'object_type', 'object_id', 'type', 'pinned', 'owner' ) ),
				'fields'              => self::fields(
					array(
						'id'                => 'integer',
						'owner'             => 'integer',
						'object_type'       => 'integer',
						'object_id'         => 'integer',
						'type'              => 'string',
						'short_description' => 'string',
						'long_description'  => 'string',
						'pinned'            => 'boolean',
						'date_created_gmt'  => 'string',
					)
				),
			),
			'forms'           => array(
				'name'                => 'forms',
				'label'               => __( 'Формы', 'jetpack-crm-rest-api-improved' ),
				'path'                => '/forms',
				'permission_resource' => 'forms',
				'operations'          => $full,
				'compact_fields'      => array( 'id', 'title' ),
				'required_on_create'  => array( 'title' ),
				'filters'             => self::filters( array( 'search' ) ),
				'fields'              => self::fields(
					array(
						'id'          => 'integer',
						'owner'       => 'integer',
						'title'       => 'string',
						'tags'        => 'array',
						'views'       => 'integer',
						'conversions' => 'integer',
					)
				),
			),
			'segments'        => array(
				'name'                => 'segments',
				'label'               => __( 'Сегменты', 'jetpack-crm-rest-api-improved' ),
				'path'                => '/segments',
				'permission_resource' => 'segments',
				'operations'          => $full,
				'compact_fields'      => array( 'id', 'name', 'slug' ),
				'required_on_create'  => array( 'name' ),
				'filters'             => self::filters( array( 'search' ) ),
				'fields'              => self::fields(
					array(
						'id'                     => 'integer',
						'name'                   => 'string',
						'slug'                   => 'string',
						'match_type'             => 'string',
						'conditions'             => 'array',
						'compile_count'          => 'integer',
						'date_last_compiled_gmt' => 'string',
					)
				),
			),
			'quote-templates' => array(
				'name'                => 'quote-templates',
				'label'               => __( 'Шаблоны КП', 'jetpack-crm-rest-api-improved' ),
				'path'                => '/quote-templates',
				'permission_resource' => 'quote-templates',
				'operations'          => $full,
				'compact_fields'      => array( 'id', 'title' ),
				'required_on_create'  => array( 'title' ),
				'filters'             => self::filters( array( 'search' ) ),
				'fields'              => self::fields(
					array(
						'id'       => 'integer',
						'owner'    => 'integer',
						'title'    => 'string',
						'value'    => 'number',
						'date'     => 'string',
						'content'  => 'string',
						'notes'    => 'string',
						'currency' => 'string',
					)
				),
			),
			'line-items'      => array(
				'name'                => 'line-items',
				'label'               => __( 'Позиции счетов/КП', 'jetpack-crm-rest-api-improved' ),
				'path'                => '/line-items',
				'permission_resource' => 'line-items',
				'operations'          => $full,
				'compact_fields'      => array( 'id', 'title', 'quantity', 'price', 'total' ),
				'required_on_create'  => array( 'parent_object_type', 'parent_object_id', 'title' ),
				'filters'             => self::filters( array( 'search', 'parent_object_type', 'parent_object_id' ) ),
				'fields'              => self::fields(
					array(
						'id'                 => 'integer',
						'order'              => 'integer',
						'title'              => 'string',
						'description'        => 'string',
						'quantity'           => 'number',
						'price'              => 'number',
						'currency'           => 'string',
						'net'                => 'number',
						'discount'           => 'number',
						'fee'                => 'number',
						'shipping'           => 'number',
						'tax'                => 'number',
						'total'              => 'number',
						'parent_object_type' => 'integer',
						'parent_object_id'   => 'integer',
						'date_created_gmt'   => 'string',
					)
				),
			),
			'tags'            => array(
				'name'                => 'tags',
				'label'               => __( 'Теги', 'jetpack-crm-rest-api-improved' ),
				'path'                => '/tags',
				'permission_resource' => 'tags',
				'operations'          => $full,
				'compact_fields'      => array( 'id', 'name', 'slug', 'object_type' ),
				'required_on_create'  => array( 'name' ),
				'filters'             => self::filters( array( 'object_type', 'search' ) ),
				'fields'              => self::fields(
					array(
						'id'          => 'integer',
						'name'        => 'string',
						'slug'        => 'string',
						'object_type' => 'string',
						'count'       => 'integer',
					)
				),
			),
			'emails'          => array(
				'name'                => 'emails',
				'label'               => __( 'Письма', 'jetpack-crm-rest-api-improved' ),
				'path'                => '/emails',
				'permission_resource' => 'emails',
				'operations'          => array( 'list', 'get', 'create', 'delete' ),
				'compact_fields'      => array( 'id', 'subject', 'status', 'contact_id', 'date_created_gmt' ),
				'required_on_create'  => array( 'subject', 'content' ),
				'filters'             => self::filters( array( 'contact', 'status', 'starred', 'thread', 'type', 'search', 'date_after', 'date_before' ) ),
				'fields'              => self::fields(
					array(
						'id'               => 'integer',
						'thread_id'        => 'integer',
						'contact_id'       => 'integer',
						'assoc_object_id'  => 'integer',
						'type'             => 'integer',
						'status'           => 'string',
						'sender_email'     => 'string',
						'receiver_email'   => 'string',
						'subject'          => 'string',
						'content'          => 'string',
						'starred'          => 'boolean',
						'opened'           => 'boolean',
						'clicked'          => 'boolean',
						'date_created_gmt' => 'string',
					)
				),
			),
			'email-threads'   => array(
				'name'                => 'email-threads',
				'label'               => __( 'Треды писем', 'jetpack-crm-rest-api-improved' ),
				'path'                => '/email-threads',
				'permission_resource' => 'email-threads',
				'operations'          => array( 'list', 'get', 'delete' ),
				'compact_fields'      => array( 'id', 'thread_id', 'contact_id', 'subject', 'status', 'date_created_gmt' ),
				'required_on_create'  => array(),
				'filters'             => self::filters( array( 'contact', 'status', 'starred', 'search' ) ),
				'fields'              => self::fields(
					array(
						'id'               => 'integer',
						'thread_id'        => 'integer',
						'contact_id'       => 'integer',
						'assoc_object_id'  => 'integer',
						'type'             => 'integer',
						'status'           => 'string',
						'sender_email'     => 'string',
						'receiver_email'   => 'string',
						'subject'          => 'string',
						'content'          => 'string',
						'starred'          => 'boolean',
						'opened'           => 'boolean',
						'clicked'          => 'boolean',
						'date_created_gmt' => 'string',
					)
				),
			),
			'email-templates' => array(
				'name'                => 'email-templates',
				'label'               => __( 'Шаблоны писем', 'jetpack-crm-rest-api-improved' ),
				'path'                => '/email-templates',
				'permission_resource' => 'email-templates',
				'operations'          => $full,
				'compact_fields'      => array( 'id', 'active', 'subject' ),
				'required_on_create'  => array( 'subject' ),
				'filters'             => self::filters( array( 'search' ) ),
				'fields'              => self::fields(
					array(
						'id'                => 'integer',
						'active'            => 'boolean',
						'delivery_method'   => 'string',
						'from_name'         => 'string',
						'from_address'      => 'string',
						'reply_to'          => 'string',
						'subject'           => 'string',
						'body'              => 'string',
						'date_created_gmt'  => 'string',
						'date_modified_gmt' => 'string',
					)
				),
			),
		);
	}

	/**
	 * Get a single entity specification by name.
	 *
	 * @param string $name Entity name.
	 * @return array|null
	 */
	public static function get( $name ) {
		$all = self::all();

		return isset( $all[ $name ] ) ? $all[ $name ] : null;
	}

	/**
	 * Return the catalog filtered by the current user's capabilities.
	 *
	 * @return array<string,array>
	 */
	public static function for_user() {
		$catalog = array();

		foreach ( self::all() as $name => $spec ) {
			$operations = self::allowed_operations( $spec );

			if ( empty( $operations ) ) {
				continue;
			}

			$spec['operations'] = $operations;

			$catalog[ $name ] = $spec;
		}

		return $catalog;
	}

	/**
	 * Filter an entity's operations by the current user's capabilities.
	 *
	 * @param array $spec Entity specification.
	 * @return string[]
	 */
	private static function allowed_operations( array $spec ) {
		$resource   = $spec['permission_resource'];
		$operations = array();

		foreach ( $spec['operations'] as $operation ) {
			$action = ( 'list' === $operation || 'get' === $operation ) ? 'read' : ( 'delete' === $operation ? 'delete' : 'write' );

			if ( Permissions::can( $resource, $action ) ) {
				$operations[] = $operation;
			}
		}

		return $operations;
	}

	/**
	 * Build filter descriptors from names.
	 *
	 * @param string[] $names Filter names.
	 * @return array
	 */
	private static function filters( array $names ) {
		$types = array(
			'search'             => 'string',
			'status'             => 'string',
			'owner'              => 'integer',
			'company'            => 'integer',
			'contact'            => 'integer',
			'invoice'            => 'integer',
			'event'              => 'integer',
			'parent_object_type' => 'integer',
			'parent_object_id'   => 'integer',
			'tags'               => 'array',
			'has_email'          => 'boolean',
			'type'               => 'string',
			'object_type'        => 'integer',
			'object_id'          => 'integer',
			'pinned'             => 'boolean',
			'starred'            => 'boolean',
			'thread'             => 'integer',
			'date_after'         => 'string',
			'date_before'        => 'string',
		);

		$filters = array();

		foreach ( $names as $name ) {
			$filters[] = array(
				'name' => $name,
				'type' => isset( $types[ $name ] ) ? $types[ $name ] : 'string',
			);
		}

		return $filters;
	}

	/**
	 * Build field descriptors from a name => type map.
	 *
	 * @param array $map Field name => type.
	 * @return array
	 */
	private static function fields( array $map ) {
		$fields = array();

		foreach ( $map as $name => $type ) {
			$fields[] = array(
				'name' => $name,
				'type' => $type,
			);
		}

		return $fields;
	}
}
