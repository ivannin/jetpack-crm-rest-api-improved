<?php
/**
 * OpenAPI 3 specification generator for the plugin namespace.
 *
 * @package Jetpack_CRM_REST_API_Improved
 */

namespace Jetpack_CRM_REST_API_Improved\Rest;

use Jetpack_CRM_REST_API_Improved\Mcp\EntityRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds an OpenAPI document from the registered REST routes and entity schemas.
 */
class OpenApiGenerator {

	const REST_NAMESPACE = 'jpcrm-improved/v1';

	/**
	 * Generate the OpenAPI document.
	 *
	 * @return array
	 */
	public static function generate() {
		$routes = rest_get_server()->get_routes();
		$paths  = array();
		$tags   = array();

		foreach ( $routes as $route => $handlers ) {
			if ( 0 !== strpos( $route, '/' . self::REST_NAMESPACE ) ) {
				continue;
			}

			$oa_path = self::openapi_path( $route );

			foreach ( $handlers as $handler ) {
				if ( ! isset( $handler['methods'] ) ) {
					continue;
				}

				foreach ( self::http_methods( $handler['methods'] ) as $method ) {
					$paths[ $oa_path ][ strtolower( $method ) ] = self::operation( $oa_path, $handler, $method );
				}
			}

			$tag = self::tag_for( $oa_path );

			if ( ! in_array( $tag, $tags, true ) ) {
				$tags[] = $tag;
			}
		}

		ksort( $paths );

		$tag_objects = array();

		foreach ( $tags as $tag ) {
			$tag_objects[] = array( 'name' => $tag );
		}

		return array(
			'openapi'    => '3.0.3',
			'info'       => array(
				'title'       => 'Jetpack CRM REST API Improved',
				'version'     => defined( 'JPCRM_IMPROVED_VERSION' ) ? JPCRM_IMPROVED_VERSION : '0.0.0',
				'description' => 'Стандартный WordPress REST API для Jetpack CRM. Аутентификация — Application Password (Basic).',
			),
			'servers'    => array(
				array( 'url' => untrailingslashit( rest_url( self::REST_NAMESPACE ) ) ),
			),
			'tags'       => $tag_objects,
			'paths'      => $paths,
			'components' => array(
				'securitySchemes' => array(
					'basicAuth' => array(
						'type'        => 'http',
						'scheme'      => 'basic',
						'description' => 'WordPress Application Password: base64(login:app_password).',
					),
				),
				'schemas'         => self::schemas(),
			),
			'security'   => array(
				array( 'basicAuth' => array() ),
			),
		);
	}

	/**
	 * Convert a WP route regex into an OpenAPI path.
	 *
	 * @param string $route WP route.
	 * @return string
	 */
	private static function openapi_path( $route ) {
		$path = substr( $route, strlen( '/' . self::REST_NAMESPACE ) );

		if ( '' === $path ) {
			$path = '/';
		}

		return preg_replace( '#\(\?P<([a-zA-Z0-9_]+)>[^)]+\)#', '{$1}', $path );
	}

	/**
	 * Decode the methods of a route handler.
	 *
	 * @param mixed $methods Registered methods.
	 * @return string[]
	 */
	private static function http_methods( $methods ) {
		if ( is_array( $methods ) ) {
			return array_keys( $methods );
		}

		if ( is_int( $methods ) ) {
			$map   = array();
			$known = array(
				'GET'    => \WP_REST_Server::READABLE,
				'POST'   => \WP_REST_Server::CREATABLE,
				'PUT'    => \WP_REST_Server::EDITABLE,
				'PATCH'  => \WP_REST_Server::EDITABLE,
				'DELETE' => \WP_REST_Server::DELETABLE,
			);

			foreach ( $known as $name => $bit ) {
				if ( $methods & $bit ) {
					$map[] = $name;
				}
			}

			return $map;
		}

		return array();
	}

	/**
	 * Tag (group) for a path.
	 *
	 * @param string $oa_path OpenAPI path.
	 * @return string
	 */
	private static function tag_for( $oa_path ) {
		$segments = array_values( array_filter( explode( '/', trim( $oa_path, '/' ) ) ) );

		if ( empty( $segments ) || 0 === strpos( $segments[0], '{' ) ) {
			return 'system';
		}

		return $segments[0];
	}

	/**
	 * Build an operation object.
	 *
	 * @param string $oa_path OpenAPI path.
	 * @param array  $handler WP route handler.
	 * @param string $method  HTTP method.
	 * @return array
	 */
	private static function operation( $oa_path, $handler, $method ) {
		$tag       = self::tag_for( $oa_path );
		$method    = strtoupper( $method );
		$is_write  = in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true );
		$operation = array(
			'summary'    => $method . ' ' . $oa_path,
			'tags'       => array( $tag ),
			'parameters' => self::parameters( $oa_path, $handler ),
			'responses'  => self::responses( $method, $tag ),
		);

		if ( $is_write ) {
			$operation['requestBody'] = array(
				'required' => true,
				'content'  => array(
					'application/json' => array(
						'schema' => self::request_schema( $method, $tag, $oa_path ),
					),
				),
			);
		}

		return $operation;
	}

	/**
	 * Build parameters for an operation.
	 *
	 * @param string $oa_path OpenAPI path.
	 * @param array  $handler WP route handler.
	 * @return array
	 */
	private static function parameters( $oa_path, $handler ) {
		$params = array();

		if ( preg_match_all( '#\{([a-zA-Z0-9_]+)\}#', $oa_path, $matches ) ) {
			foreach ( $matches[1] as $name ) {
				$params[] = array(
					'name'     => $name,
					'in'       => 'path',
					'required' => true,
					'schema'   => array( 'type' => 'integer' ),
				);
			}
		}

		$skip = array( 'id', 'context', '_fields', '_embed', '_envelope', 'rest_route', '_locale' );
		$args = isset( $handler['args'] ) && is_array( $handler['args'] ) ? $handler['args'] : array();

		foreach ( $args as $name => $arg ) {
			if ( in_array( $name, $skip, true ) || ! is_array( $arg ) ) {
				continue;
			}

			$type = isset( $arg['type'] ) ? $arg['type'] : 'string';
			if ( is_array( $type ) ) {
				$type = 'string';
			}

			$param = array(
				'name'   => $name,
				'in'     => 'query',
				'schema' => array( 'type' => $type ),
			);

			if ( isset( $arg['description'] ) ) {
				$param['description'] = $arg['description'];
			}
			if ( isset( $arg['required'] ) ) {
				$param['required'] = (bool) $arg['required'];
			}
			if ( isset( $arg['enum'] ) ) {
				$param['schema']['enum'] = $arg['enum'];
			}
			if ( isset( $arg['minimum'] ) ) {
				$param['schema']['minimum'] = $arg['minimum'];
			}
			if ( isset( $arg['default'] ) ) {
				$param['schema']['default'] = $arg['default'];
			}

			$params[] = $param;
		}

		return $params;
	}

	/**
	 * Build responses for an operation.
	 *
	 * @param string $method HTTP method.
	 * @param string $tag    Entity tag.
	 * @return array
	 */
	private static function responses( $method, $tag ) {
		$schema = self::schema_ref( $tag );

		$responses = array(
			'401'     => array(
				'description' => 'Не аутентифицирован',
				'content'     => self::error_content(),
			),
			'403'     => array(
				'description' => 'Нет прав',
				'content'     => self::error_content(),
			),
			'default' => array(
				'description' => 'Ошибка',
				'content'     => array( 'application/json' => array( 'schema' => array( '$ref' => '#/components/schemas/Error' ) ) ),
			),
		);

		switch ( $method ) {
			case 'DELETE':
				$responses['200'] = array(
					'description' => 'Удалено',
					'content'     => array( 'application/json' => array( 'schema' => array( 'type' => 'object' ) ) ),
				);
				break;
			case 'POST':
				$responses['201'] = array(
					'description' => 'Создано',
					'content'     => array( 'application/json' => array( 'schema' => $schema ) ),
				);
				break;
			case 'GET':
				$responses['200'] = array(
					'description' => 'Успех',
					'content'     => array( 'application/json' => array( 'schema' => $schema ) ),
				);
				break;
			default:
				$responses['200'] = array(
					'description' => 'Успех',
					'content'     => array( 'application/json' => array( 'schema' => $schema ) ),
				);
		}

		return $responses;
	}

	/**
	 * Request body schema.
	 *
	 * @param string $method  HTTP method.
	 * @param string $tag     Entity tag.
	 * @param string $oa_path OpenAPI path.
	 * @return array
	 */
	private static function request_schema( $method, $tag, $oa_path ) {
		if ( preg_match( '#/(tags|meta|custom-fields|external-sources|links)$#', $oa_path ) ) {
			return array( 'type' => 'object' );
		}

		return self::schema_ref( $tag );
	}

	/**
	 * Reference (or generic object) for an entity schema.
	 *
	 * @param string $tag Entity tag.
	 * @return array
	 */
	private static function schema_ref( $tag ) {
		return array( '$ref' => '#/components/schemas/' . self::schema_name( $tag ) );
	}

	/**
	 * Error response content.
	 *
	 * @return array
	 */
	private static function error_content() {
		return array(
			'application/json' => array(
				'schema' => array( '$ref' => '#/components/schemas/Error' ),
			),
		);
	}

	/**
	 * Build all entity schemas.
	 *
	 * @return array
	 */
	private static function schemas() {
		$schemas = array(
			'Error' => array(
				'type'       => 'object',
				'properties' => array(
					'code'    => array( 'type' => 'string' ),
					'message' => array( 'type' => 'string' ),
					'data'    => array( 'type' => 'object' ),
				),
			),
		);

		foreach ( EntityRegistry::all() as $name => $spec ) {
			$properties = array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'Уникальный идентификатор.',
				),
			);

			foreach ( $spec['fields'] as $field ) {
				$type = $field['type'];
				if ( 'number' === $type ) {
					$type = 'number';
				} elseif ( ! in_array( $type, array( 'integer', 'number', 'boolean', 'array', 'object', 'string' ), true ) ) {
					$type = 'string';
				}

				$properties[ $field['name'] ] = array( 'type' => $type );
			}

			$schemas[ self::schema_name( $name ) ] = array(
				'type'       => 'object',
				'properties' => $properties,
			);
		}

		return $schemas;
	}

	/**
	 * PascalCase schema name for an entity.
	 *
	 * @param string $tag Entity tag.
	 * @return string
	 */
	private static function schema_name( $tag ) {
		$name = str_replace( ' ', '', ucwords( str_replace( array( '-', '_' ), ' ', $tag ) ) );

		return '' === $name ? 'Entity' : $name;
	}
}
