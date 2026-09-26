<?php
/**
 * Tags, meta and custom-field MCP tools.
 *
 * @package Jetpack_CRM_REST_API_Improved
 */

namespace Jetpack_CRM_REST_API_Improved\Mcp\Tools;

use Jetpack_CRM_REST_API_Improved\Admin\SettingsPage;
use Jetpack_CRM_REST_API_Improved\Mcp\Dispatcher;
use Jetpack_CRM_REST_API_Improved\Mcp\EntityRegistry;
use Jetpack_CRM_REST_API_Improved\Rest\Permissions;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handlers for object subresources.
 */
class ResourceTools {

	/**
	 * Plugin instance.
	 *
	 * @var object|null
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param object|null $plugin Plugin instance.
	 */
	public function __construct( $plugin = null ) {
		$this->plugin = $plugin;
	}

	/**
	 * Tags of an object.
	 *
	 * @param array $args Tool arguments.
	 * @return array|WP_Error
	 */
	public function tags( $args ) {
		$action = isset( $args['action'] ) ? (string) $args['action'] : 'get';
		$write  = in_array( $action, array( 'set', 'remove' ), true );

		$spec = $this->resolve( $args, $write );

		if ( is_wp_error( $spec ) ) {
			return $spec;
		}

		$id = $this->id( $args );

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		switch ( $action ) {
			case 'set':
				$tags = isset( $args['tags'] ) && is_array( $args['tags'] ) ? $args['tags'] : array();
				$mode = isset( $args['mode'] ) ? (string) $args['mode'] : 'replace';

				return Dispatcher::request(
					'POST',
					$spec['path'] . '/' . $id . '/tags',
					array(),
					array(
						'tags' => $tags,
						'mode' => $mode,
					)
				);

			case 'remove':
				if ( empty( $args['tag_id'] ) ) {
					return $this->param_error( __( 'Для action=remove укажите tag_id.', 'jetpack-crm-rest-api-improved' ) );
				}

				return Dispatcher::request( 'DELETE', $spec['path'] . '/' . $id . '/tags/' . (int) $args['tag_id'] );

			case 'get':
			default:
				return Dispatcher::request( 'GET', $spec['path'] . '/' . $id . '/tags' );
		}
	}

	/**
	 * Meta of an object.
	 *
	 * @param array $args Tool arguments.
	 * @return array|WP_Error
	 */
	public function meta( $args ) {
		$action = isset( $args['action'] ) ? (string) $args['action'] : 'get';
		$write  = in_array( $action, array( 'set', 'delete' ), true );

		$spec = $this->resolve( $args, $write );

		if ( is_wp_error( $spec ) ) {
			return $spec;
		}

		$id = $this->id( $args );

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$key = isset( $args['key'] ) ? (string) $args['key'] : '';

		switch ( $action ) {
			case 'set':
				if ( '' === $key ) {
					return $this->param_error( __( 'Для action=set укажите key.', 'jetpack-crm-rest-api-improved' ) );
				}

				return Dispatcher::request( 'PUT', $spec['path'] . '/' . $id . '/meta/' . rawurlencode( $key ), array(), array( 'value' => isset( $args['value'] ) ? $args['value'] : null ) );

			case 'delete':
				if ( '' === $key ) {
					return $this->param_error( __( 'Для action=delete укажите key.', 'jetpack-crm-rest-api-improved' ) );
				}

				return Dispatcher::request( 'DELETE', $spec['path'] . '/' . $id . '/meta/' . rawurlencode( $key ) );

			case 'get':
			default:
				$path = $spec['path'] . '/' . $id . '/meta';

				if ( '' !== $key ) {
					$path .= '/' . rawurlencode( $key );
				}

				return Dispatcher::request( 'GET', $path );
		}
	}

	/**
	 * Custom field values of an object.
	 *
	 * @param array $args Tool arguments.
	 * @return array|WP_Error
	 */
	public function custom_fields( $args ) {
		$action = isset( $args['action'] ) ? (string) $args['action'] : 'get';
		$write  = ( 'set' === $action );

		$spec = $this->resolve( $args, $write );

		if ( is_wp_error( $spec ) ) {
			return $spec;
		}

		$id = $this->id( $args );

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		if ( 'set' === $action ) {
			if ( isset( $args['values'] ) && is_array( $args['values'] ) ) {
				return Dispatcher::request( 'PUT', $spec['path'] . '/' . $id . '/custom-fields', array(), array( 'values' => $args['values'] ) );
			}

			$key = isset( $args['key'] ) ? (string) $args['key'] : '';

			if ( '' === $key ) {
				return $this->param_error( __( 'Для action=set укажите values (объект) или key+value.', 'jetpack-crm-rest-api-improved' ) );
			}

			return Dispatcher::request( 'PUT', $spec['path'] . '/' . $id . '/custom-fields/' . rawurlencode( $key ), array(), array( 'value' => isset( $args['value'] ) ? $args['value'] : null ) );
		}

		$key  = isset( $args['key'] ) ? (string) $args['key'] : '';
		$path = $spec['path'] . '/' . $id . '/custom-fields';

		if ( '' !== $key ) {
			$path .= '/' . rawurlencode( $key );
		}

		return Dispatcher::request( 'GET', $path );
	}

	/**
	 * External sources of an object.
	 *
	 * @param array $args Tool arguments.
	 * @return array|WP_Error
	 */
	public function external_sources( $args ) {
		$action = isset( $args['action'] ) ? (string) $args['action'] : 'get';
		$write  = in_array( $action, array( 'add', 'delete' ), true );

		$spec = $this->resolve( $args, $write );

		if ( is_wp_error( $spec ) ) {
			return $spec;
		}

		$id = $this->id( $args );

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		switch ( $action ) {
			case 'add':
				$source = isset( $args['source'] ) ? (string) $args['source'] : '';
				$uid    = isset( $args['uid'] ) ? (string) $args['uid'] : '';

				if ( '' === $source || '' === $uid ) {
					return $this->param_error( __( 'Для action=add укажите source и uid.', 'jetpack-crm-rest-api-improved' ) );
				}

				$body = array(
					'source' => $source,
					'uid'    => $uid,
				);

				if ( isset( $args['origin'] ) ) {
					$body['origin'] = (string) $args['origin'];
				}

				return Dispatcher::request( 'POST', $spec['path'] . '/' . $id . '/external-sources', array(), $body );

			case 'delete':
				if ( empty( $args['ext_id'] ) ) {
					return $this->param_error( __( 'Для action=delete укажите ext_id.', 'jetpack-crm-rest-api-improved' ) );
				}

				return Dispatcher::request( 'DELETE', $spec['path'] . '/' . $id . '/external-sources/' . (int) $args['ext_id'] );

			case 'get':
			default:
				return Dispatcher::request( 'GET', $spec['path'] . '/' . $id . '/external-sources' );
		}
	}

	/**
	 * Object links of an object.
	 *
	 * @param array $args Tool arguments.
	 * @return array|WP_Error
	 */
	public function links( $args ) {
		$action = isset( $args['action'] ) ? (string) $args['action'] : 'get';
		$write  = in_array( $action, array( 'add', 'delete' ), true );

		$spec = $this->resolve( $args, $write );

		if ( is_wp_error( $spec ) ) {
			return $spec;
		}

		$id = $this->id( $args );

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$type = $this->object_type( isset( $args['object_type'] ) ? $args['object_type'] : null );

		switch ( $action ) {
			case 'add':
				if ( empty( $args['object_id'] ) || $type <= 0 ) {
					return $this->param_error( __( 'Для action=add укажите object_type и object_id.', 'jetpack-crm-rest-api-improved' ) );
				}

				return Dispatcher::request(
					'POST',
					$spec['path'] . '/' . $id . '/links',
					array(),
					array(
						'object_type' => $type,
						'object_id'   => (int) $args['object_id'],
					)
				);

			case 'delete':
				if ( empty( $args['object_id'] ) || $type <= 0 ) {
					return $this->param_error( __( 'Для action=delete укажите object_type и object_id.', 'jetpack-crm-rest-api-improved' ) );
				}

				return Dispatcher::request( 'DELETE', $spec['path'] . '/' . $id . '/links/' . $type . '/' . (int) $args['object_id'] );

			case 'get':
			default:
				$query = array();

				if ( $type > 0 ) {
					$query['object_type'] = $type;
				}

				return Dispatcher::request( 'GET', $spec['path'] . '/' . $id . '/links', $query );
		}
	}

	/**
	 * Map an entity name or numeric value to a ZBS object type.
	 *
	 * @param mixed $value Entity name or numeric type.
	 * @return int
	 */
	private function object_type( $value ) {
		if ( null === $value || '' === $value ) {
			return 0;
		}

		if ( is_numeric( $value ) ) {
			return (int) $value;
		}

		$map = array(
			'contacts'        => 1,
			'companies'       => 2,
			'quotes'          => 3,
			'invoices'        => 4,
			'transactions'    => 5,
			'tasks'           => 6,
			'forms'           => 7,
			'logs'            => 8,
			'segments'        => 9,
			'quote-templates' => 12,
		);

		return isset( $map[ $value ] ) ? $map[ $value ] : 0;
	}

	/**
	 * Resolve and authorise an entity.
	 *
	 * @param array $args  Tool arguments.
	 * @param bool  $write Whether a write action is requested.
	 * @return array|WP_Error
	 */
	private function resolve( $args, $write ) {
		if ( $write && SettingsPage::is_readonly() ) {
			return new WP_Error( 'jpcrm_mcp_readonly', __( 'MCP-сервер работает в режиме только чтения.', 'jetpack-crm-rest-api-improved' ), array( 'status' => 403 ) );
		}

		if ( empty( $args['entity'] ) || ! is_string( $args['entity'] ) ) {
			return $this->param_error( __( 'Аргумент entity обязателен.', 'jetpack-crm-rest-api-improved' ) );
		}

		$name = sanitize_text_field( $args['entity'] );
		$spec = EntityRegistry::get( $name );

		if ( ! $spec ) {
			return new WP_Error(
				'jpcrm_mcp_unknown_entity',
				sprintf(
					/* translators: %s: entity name */
					__( 'Неизвестная сущность: %s', 'jetpack-crm-rest-api-improved' ),
					$name
				),
				array( 'status' => 404 )
			);
		}

		$action = $write ? 'write' : 'read';

		if ( ! Permissions::can( $spec['permission_resource'], $action ) ) {
			return new WP_Error(
				'jpcrm_mcp_forbidden',
				sprintf(
					/* translators: 1: entity name, 2: action */
					__( 'Операция «%2$s» недоступна для сущности «%1$s».', 'jetpack-crm-rest-api-improved' ),
					$name,
					$action
				),
				array( 'status' => 403 )
			);
		}

		return $spec;
	}

	/**
	 * Validate and return an object id.
	 *
	 * @param array $args Tool arguments.
	 * @return int|WP_Error
	 */
	private function id( $args ) {
		if ( ! isset( $args['id'] ) || ! is_numeric( $args['id'] ) || (int) $args['id'] <= 0 ) {
			return $this->param_error( __( 'Аргумент id обязателен и должен быть положительным числом.', 'jetpack-crm-rest-api-improved' ) );
		}

		return (int) $args['id'];
	}

	/**
	 * Build a parameter error.
	 *
	 * @param string $message Message.
	 * @return WP_Error
	 */
	private function param_error( $message ) {
		return new WP_Error( 'jpcrm_mcp_invalid_param', $message, array( 'status' => 400 ) );
	}
}
