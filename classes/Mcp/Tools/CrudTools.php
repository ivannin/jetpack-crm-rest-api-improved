<?php
/**
 * Generic CRUD MCP tools.
 *
 * @package Jetpack_CRM_REST_API_Improved
 */

namespace Jetpack_CRM_REST_API_Improved\Mcp\Tools;

use Jetpack_CRM_REST_API_Improved\Mcp\Dispatcher;
use Jetpack_CRM_REST_API_Improved\Mcp\EntityRegistry;
use Jetpack_CRM_REST_API_Improved\Rest\Permissions;
use WP_Error;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handlers for the entity-agnostic CRUD tools and the raw fallback.
 */
class CrudTools {

	const RAW_OPTION = 'jpcrm_improved_mcp_raw_enabled';

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
	 * Search/list entities.
	 *
	 * @param array $args Tool arguments.
	 * @return array|WP_Error
	 */
	public function search( $args ) {
		$spec = $this->entity( $args, 'list' );

		if ( is_wp_error( $spec ) ) {
			return $spec;
		}

		$query    = $this->build_query( $spec, $args );
		$response = Dispatcher::dispatch( 'GET', $spec['path'], $query );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( ! $response instanceof WP_REST_Response ) {
			return $this->internal_error();
		}

		if ( (int) $response->get_status() >= 400 ) {
			return $this->response_error( $response );
		}

		$data  = $response->get_data();
		$items = is_array( $data ) ? $data : array();

		$total_header = Dispatcher::header( $response, 'X-WP-Total' );
		$total        = null !== $total_header ? (int) $total_header : count( $items );
		$per_page     = isset( $query['per_page'] ) ? (int) $query['per_page'] : 20;
		$page         = isset( $query['page'] ) ? (int) $query['page'] : 1;

		$items = array_map(
			function ( $item ) use ( $spec, $args ) {
				return $this->shape( $spec, $item, $args, 'concise' );
			},
			$items
		);

		return array(
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
			'items'    => array_values( $items ),
		);
	}

	/**
	 * Read one entity by id.
	 *
	 * @param array $args Tool arguments.
	 * @return array|WP_Error
	 */
	public function get( $args ) {
		$spec = $this->entity( $args, 'get' );

		if ( is_wp_error( $spec ) ) {
			return $spec;
		}

		$id = $this->id( $args );

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$item = Dispatcher::request( 'GET', $spec['path'] . '/' . $id );

		if ( is_wp_error( $item ) ) {
			return $item;
		}

		return $this->shape( $spec, $item, $args, 'detailed' );
	}

	/**
	 * Create an entity.
	 *
	 * @param array $args Tool arguments.
	 * @return array|WP_Error
	 */
	public function create( $args ) {
		$spec = $this->entity( $args, 'create' );

		if ( is_wp_error( $spec ) ) {
			return $spec;
		}

		if ( ! isset( $args['data'] ) || ! is_array( $args['data'] ) ) {
			return $this->param_error( __( 'Аргумент data обязателен и должен быть объектом.', 'jetpack-crm-rest-api-improved' ) );
		}

		$item = Dispatcher::request( 'POST', $spec['path'], array(), $args['data'] );

		if ( is_wp_error( $item ) ) {
			return $item;
		}

		return $this->shape( $spec, $item, $args, 'detailed' );
	}

	/**
	 * Update an entity.
	 *
	 * @param array $args Tool arguments.
	 * @return array|WP_Error
	 */
	public function update( $args ) {
		$spec = $this->entity( $args, 'update' );

		if ( is_wp_error( $spec ) ) {
			return $spec;
		}

		$id = $this->id( $args );

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		if ( ! isset( $args['data'] ) || ! is_array( $args['data'] ) ) {
			return $this->param_error( __( 'Аргумент data обязателен и должен быть объектом.', 'jetpack-crm-rest-api-improved' ) );
		}

		$partial = ! isset( $args['partial'] ) || (bool) $args['partial'];
		$method  = $partial ? 'PATCH' : 'PUT';

		$item = Dispatcher::request( $method, $spec['path'] . '/' . $id, array(), $args['data'] );

		if ( is_wp_error( $item ) ) {
			return $item;
		}

		return $this->shape( $spec, $item, $args, 'detailed' );
	}

	/**
	 * Delete an entity.
	 *
	 * @param array $args Tool arguments.
	 * @return array|WP_Error
	 */
	public function delete( $args ) {
		$spec = $this->entity( $args, 'delete' );

		if ( is_wp_error( $spec ) ) {
			return $spec;
		}

		$id = $this->id( $args );

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		return Dispatcher::request( 'DELETE', $spec['path'] . '/' . $id );
	}

	/**
	 * Run several write operations in sequence.
	 *
	 * @param array $args Tool arguments.
	 * @return array|WP_Error
	 */
	public function batch( $args ) {
		$requests = isset( $args['requests'] ) && is_array( $args['requests'] ) ? $args['requests'] : array();

		if ( empty( $requests ) ) {
			return $this->param_error( __( 'Аргумент requests обязателен и не может быть пустым.', 'jetpack-crm-rest-api-improved' ) );
		}

		$max = (int) apply_filters( 'jpcrm_improved_mcp_batch_max_requests', 25 );

		if ( $max > 0 && count( $requests ) > $max ) {
			return $this->param_error(
				sprintf(
					/* translators: %d: maximum number of requests */
					__( 'Слишком много операций в пакете (максимум %d).', 'jetpack-crm-rest-api-improved' ),
					$max
				)
			);
		}

		$stop_on_error = ! isset( $args['stop_on_error'] ) || (bool) $args['stop_on_error'];
		$results       = array();

		foreach ( array_values( $requests ) as $index => $request ) {
			if ( ! is_array( $request ) ) {
				$results[] = array(
					'index' => $index,
					'ok'    => false,
					'error' => __( 'Некорректная операция.', 'jetpack-crm-rest-api-improved' ),
				);

				if ( $stop_on_error ) {
					break;
				}

				continue;
			}

			$operation = isset( $request['operation'] ) ? (string) $request['operation'] : '';
			$sub_args  = array(
				'entity' => isset( $request['entity'] ) ? $request['entity'] : '',
				'id'     => isset( $request['id'] ) ? $request['id'] : null,
				'data'   => isset( $request['data'] ) && is_array( $request['data'] ) ? $request['data'] : array(),
			);

			switch ( $operation ) {
				case 'create':
					$result = $this->create( $sub_args );
					break;
				case 'update':
					$result = $this->update( $sub_args );
					break;
				case 'delete':
					$result = $this->delete( $sub_args );
					break;
				default:
					$result = $this->param_error( __( 'Неизвестная операция: ', 'jetpack-crm-rest-api-improved' ) . $operation );
			}

			if ( is_wp_error( $result ) ) {
				$results[] = array(
					'index'     => $index,
					'operation' => $operation,
					'ok'        => false,
					'error'     => $result->get_error_message(),
					'code'      => $result->get_error_code(),
				);

				if ( $stop_on_error ) {
					break;
				}
			} else {
				$results[] = array(
					'index'     => $index,
					'operation' => $operation,
					'ok'        => true,
					'result'    => $result,
				);
			}
		}

		return array( 'results' => $results );
	}

	/**
	 * Raw REST call inside the plugin namespace.
	 *
	 * @param array $args Tool arguments.
	 * @return array|WP_Error
	 */
	public function raw( $args ) {
		if ( ! get_option( self::RAW_OPTION, false ) ) {
			return $this->param_error( __( 'Инструмент crm_raw отключён в настройках.', 'jetpack-crm-rest-api-improved' ) );
		}

		$method  = isset( $args['method'] ) ? strtoupper( (string) $args['method'] ) : '';
		$allowed = array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' );

		if ( ! in_array( $method, $allowed, true ) ) {
			return $this->param_error( __( 'Некорректный HTTP-метод.', 'jetpack-crm-rest-api-improved' ) );
		}

		$path = isset( $args['path'] ) ? (string) $args['path'] : '';

		if ( '' === $path || '/' !== $path[0] ) {
			return $this->param_error( __( 'Аргумент path должен начинаться с «/».', 'jetpack-crm-rest-api-improved' ) );
		}

		if ( 0 === strpos( $path, '/mcp' ) || false !== strpos( $path, '..' ) ) {
			return $this->param_error( __( 'Недопустимый path.', 'jetpack-crm-rest-api-improved' ) );
		}

		$query = isset( $args['query'] ) && is_array( $args['query'] ) ? $args['query'] : array();
		$body  = isset( $args['body'] ) && is_array( $args['body'] ) ? $args['body'] : array();

		return Dispatcher::request( $method, $path, $query, $body );
	}

	/**
	 * Resolve and authorise an entity for an operation.
	 *
	 * @param array  $args      Tool arguments.
	 * @param string $operation Operation name.
	 * @return array|WP_Error
	 */
	private function entity( $args, $operation ) {
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

		$action = ( 'list' === $operation || 'get' === $operation ) ? 'read' : ( 'delete' === $operation ? 'delete' : 'write' );

		if ( ! in_array( $operation, $spec['operations'], true ) || ! Permissions::can( $spec['permission_resource'], $action ) ) {
			return new WP_Error(
				'jpcrm_mcp_forbidden',
				sprintf(
					/* translators: 1: operation, 2: entity name */
					__( 'Операция «%1$s» недоступна для сущности «%2$s».', 'jetpack-crm-rest-api-improved' ),
					$operation,
					$name
				),
				array( 'status' => 403 )
			);
		}

		return $spec;
	}

	/**
	 * Validate and return an entity id.
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
	 * Build REST query params from tool arguments.
	 *
	 * @param array $spec Entity spec.
	 * @param array $args Tool arguments.
	 * @return array
	 */
	private function build_query( $spec, $args ) {
		$allowed = array();

		foreach ( $spec['filters'] as $filter ) {
			$allowed[] = $filter['name'];
		}

		$query = array();

		foreach ( array( 'orderby', 'order', 'page', 'per_page', 'offset' ) as $key ) {
			if ( isset( $args[ $key ] ) && '' !== $args[ $key ] && null !== $args[ $key ] ) {
				$query[ $key ] = $args[ $key ];
			}
		}

		if ( isset( $args['query'] ) && '' !== $args['query'] ) {
			$query['search'] = sanitize_text_field( (string) $args['query'] );
		}

		if ( isset( $args['filters'] ) && is_array( $args['filters'] ) ) {
			foreach ( $args['filters'] as $key => $value ) {
				if ( ! in_array( $key, $allowed, true ) ) {
					continue;
				}

				if ( null === $value || '' === $value ) {
					continue;
				}

				$query[ $key ] = $value;
			}
		}

		return $query;
	}

	/**
	 * Shape an item according to the requested format/fields.
	 *
	 * @param array  $spec           Entity spec.
	 * @param mixed  $item           Item.
	 * @param array  $args           Tool arguments.
	 * @param string $default_format Default format.
	 * @return mixed
	 */
	private function shape( $spec, $item, $args, $default_format ) {
		if ( ! is_array( $item ) ) {
			return $item;
		}

		if ( isset( $args['fields'] ) && is_array( $args['fields'] ) && ! empty( $args['fields'] ) ) {
			return $this->pick( $item, array_map( 'strval', $args['fields'] ) );
		}

		$format = isset( $args['response_format'] ) && in_array( $args['response_format'], array( 'concise', 'detailed' ), true )
			? $args['response_format']
			: $default_format;

		if ( 'detailed' === $format ) {
			return $item;
		}

		return $this->pick( $item, $spec['compact_fields'] );
	}

	/**
	 * Keep only the requested top-level fields.
	 *
	 * @param array $item   Item.
	 * @param array $fields Field names.
	 * @return array
	 */
	private function pick( $item, $fields ) {
		$out = array();

		foreach ( $fields as $field ) {
			if ( array_key_exists( $field, $item ) ) {
				$out[ $field ] = $item[ $field ];
			}
		}

		return $out;
	}

	/**
	 * Convert an error response into a WP_Error.
	 *
	 * @param WP_REST_Response $response Response.
	 * @return WP_Error
	 */
	private function response_error( WP_REST_Response $response ) {
		$data    = $response->get_data();
		$code    = is_array( $data ) && isset( $data['code'] ) ? $data['code'] : 'jpcrm_mcp_request_failed';
		$message = is_array( $data ) && isset( $data['message'] ) ? $data['message'] : __( 'Ошибка CRM.', 'jetpack-crm-rest-api-improved' );

		return new WP_Error( $code, $message, array( 'status' => (int) $response->get_status() ) );
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

	/**
	 * Build an internal error.
	 *
	 * @return WP_Error
	 */
	private function internal_error() {
		return new WP_Error( 'jpcrm_mcp_internal_error', __( 'Внутренняя ошибка CRM.', 'jetpack-crm-rest-api-improved' ), array( 'status' => 500 ) );
	}
}
