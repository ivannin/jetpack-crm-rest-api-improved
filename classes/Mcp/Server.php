<?php
/**
 * MCP JSON-RPC server.
 *
 * @package Jetpack_CRM_REST_API_Improved
 */

namespace Jetpack_CRM_REST_API_Improved\Mcp;

use Jetpack_CRM_REST_API_Improved\Admin\SettingsPage;
use Jetpack_CRM_REST_API_Improved\Rest\OpenApiGenerator;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dispatches MCP JSON-RPC methods to tools and resources.
 */
class Server {

	const DEFAULT_PROTOCOL_VERSION    = '2025-06-18';
	const SUPPORTED_PROTOCOL_VERSIONS = array( '2025-03-26', '2025-06-18', '2026-07-28' );

	/**
	 * Plugin instance.
	 *
	 * @var object|null
	 */
	private $plugin;

	/**
	 * Tool registry.
	 *
	 * @var ToolRegistry
	 */
	private $tools;

	/**
	 * Constructor.
	 *
	 * @param object|null  $plugin Plugin instance.
	 * @param ToolRegistry $tools  Tool registry.
	 */
	public function __construct( $plugin, ToolRegistry $tools ) {
		$this->plugin = $plugin;
		$this->tools  = $tools;
	}

	/**
	 * Handle an MCP request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function handle( WP_REST_Request $request ) {
		$body = (string) $request->get_body();

		if ( '' === trim( $body ) ) {
			return $this->respond( JsonRpc::error( null, JsonRpc::PARSE_ERROR, 'Empty request body.' ) );
		}

		$decoded = json_decode( $body, true );

		if ( null === $decoded && JSON_ERROR_NONE !== json_last_error() ) {
			return $this->respond( JsonRpc::error( null, JsonRpc::PARSE_ERROR, 'Parse error.' ) );
		}

		if ( $this->is_request_object( $decoded ) ) {
			$response = $this->process( $decoded );

			return null === $response ? new WP_REST_Response( null, 202 ) : $this->respond( $response );
		}

		if ( ! is_array( $decoded ) || empty( $decoded ) ) {
			return $this->respond( JsonRpc::error( null, JsonRpc::INVALID_REQUEST, 'Invalid Request.' ) );
		}

		$responses = array();

		foreach ( $decoded as $message ) {
			if ( ! is_array( $message ) ) {
				$responses[] = JsonRpc::error( null, JsonRpc::INVALID_REQUEST, 'Invalid Request.' );
				continue;
			}

			$result = $this->process( $message );

			if ( null !== $result ) {
				$responses[] = $result;
			}
		}

		if ( empty( $responses ) ) {
			return new WP_REST_Response( null, 202 );
		}

		return $this->respond( $responses );
	}

	/**
	 * Process a single JSON-RPC message.
	 *
	 * @param array $message Decoded message.
	 * @return array|null Response, or null for notifications.
	 */
	private function process( array $message ) {
		$id              = array_key_exists( 'id', $message ) ? $message['id'] : null;
		$is_notification = ! array_key_exists( 'id', $message );
		$method          = isset( $message['method'] ) ? (string) $message['method'] : '';
		$params          = isset( $message['params'] ) && is_array( $message['params'] ) ? $message['params'] : array();

		if ( '' === $method ) {
			return $is_notification ? null : JsonRpc::error( $id, JsonRpc::INVALID_REQUEST, 'Missing method.' );
		}

		if ( $is_notification ) {
			return null;
		}

		switch ( $method ) {
			case 'initialize':
				return JsonRpc::result( $id, $this->initialize( $params ) );

			case 'ping':
				return JsonRpc::result( $id, (object) array() );

			case 'tools/list':
				return JsonRpc::result( $id, $this->list_tools() );

			case 'tools/call':
				return $this->call_tool( $id, $params );

			case 'resources/list':
				return JsonRpc::result( $id, $this->list_resources() );

			case 'resources/templates/list':
				return JsonRpc::result( $id, $this->list_resource_templates() );

			case 'resources/read':
				return $this->read_resource( $id, $params );

			case 'prompts/list':
				return JsonRpc::result( $id, $this->list_prompts() );

			case 'prompts/get':
				return $this->get_prompt( $id, $params );

			default:
				return JsonRpc::error( $id, JsonRpc::METHOD_NOT_FOUND, 'Method not found: ' . $method );
		}
	}

	/**
	 * Build the initialize result.
	 *
	 * @param array $params Request params.
	 * @return array
	 */
	private function initialize( array $params ) {
		$client_version = isset( $params['protocolVersion'] ) ? (string) $params['protocolVersion'] : '';
		$version        = in_array( $client_version, self::SUPPORTED_PROTOCOL_VERSIONS, true )
			? $client_version
			: self::DEFAULT_PROTOCOL_VERSION;

		return array(
			'protocolVersion' => $version,
			'capabilities'    => array(
				'tools'     => array( 'listChanged' => false ),
				'resources' => array(
					'subscribe'   => false,
					'listChanged' => false,
				),
				'prompts'   => array( 'listChanged' => false ),
			),
			'serverInfo'      => array(
				'name'    => 'jetpack-crm-mcp',
				'version' => defined( 'JPCRM_IMPROVED_VERSION' ) ? JPCRM_IMPROVED_VERSION : '0.0.0',
			),
			'instructions'    => $this->instructions(),
		);
	}

	/**
	 * Guidance returned to the client during initialize.
	 *
	 * @return string
	 */
	private function instructions() {
		return implode(
			"\n",
			array(
				__( 'MCP-сервер Jetpack CRM. Работает с CRM через стандартные инструменты.', 'jetpack-crm-rest-api-improved' ),
				__( '1. Вызовите crm_entities, чтобы узнать доступные сущности, поля и фильтры.', 'jetpack-crm-rest-api-improved' ),
				__( '2. Используйте crm_search для поиска/списка и crm_get для чтения объекта по id.', 'jetpack-crm-rest-api-improved' ),
				__( 'Аргумент entity — имя сущности (contacts, companies, invoices, quotes, transactions, tasks, logs, tags, forms, segments, quote-templates, emails, email-templates).', 'jetpack-crm-rest-api-improved' ),
			)
		);
	}

	/**
	 * List tools available to the current user.
	 *
	 * @return array
	 */
	private function list_tools() {
		$tools = array();

		foreach ( $this->tools->for_current_user() as $tool ) {
			$item = array(
				'name'        => $tool['name'],
				'description' => $tool['description'],
				'inputSchema' => $tool['inputSchema'],
			);

			if ( ! empty( $tool['annotations'] ) ) {
				$item['annotations'] = $tool['annotations'];
			}

			$tools[] = $item;
		}

		return array( 'tools' => $tools );
	}

	/**
	 * Execute a tool call.
	 *
	 * @param mixed $id     Request id.
	 * @param array $params Request params.
	 * @return array
	 */
	private function call_tool( $id, array $params ) {
		$name = isset( $params['name'] ) ? (string) $params['name'] : '';
		$args = isset( $params['arguments'] ) && is_array( $params['arguments'] ) ? $params['arguments'] : array();

		$tool = $this->tools->get( $name );

		if ( ! $tool ) {
			return JsonRpc::error( $id, JsonRpc::INVALID_PARAMS, 'Unknown tool: ' . $name );
		}

		if ( $this->tools->is_write( $tool ) && SettingsPage::is_readonly() ) {
			return JsonRpc::result( $id, $this->tool_error( __( 'MCP-сервер работает в режиме только чтения.', 'jetpack-crm-rest-api-improved' ) ) );
		}

		if ( $this->tools->requires_confirmation( $tool ) && empty( $args['confirm'] ) ) {
			return JsonRpc::result( $id, $this->tool_error( __( 'Подтвердите операцию: повторите вызов с аргументом confirm=true.', 'jetpack-crm-rest-api-improved' ) ) );
		}

		if ( ! $this->tools->user_can( $tool ) ) {
			return JsonRpc::result( $id, $this->tool_error( __( 'Недостаточно прав CRM для этого инструмента.', 'jetpack-crm-rest-api-improved' ) ) );
		}

		$result = call_user_func( $tool['handler'], $args );

		if ( is_wp_error( $result ) ) {
			$message = $result->get_error_message();

			if ( '' !== $result->get_error_code() ) {
				$message .= ' (' . $result->get_error_code() . ')';
			}

			$this->log( 'tools/call ' . $name . ' -> error: ' . $message );

			return JsonRpc::result( $id, $this->tool_error( $message ) );
		}

		$this->log( 'tools/call ' . $name . ' -> ok' );

		return JsonRpc::result( $id, $this->tool_success( $result ) );
	}

	/**
	 * Log an MCP event when logging is enabled (and WP_DEBUG is on).
	 *
	 * @param string $message Message (must not contain secrets).
	 * @return void
	 */
	private function log( $message ) {
		if ( ! SettingsPage::is_logging() ) {
			return;
		}

		if ( $this->plugin && method_exists( $this->plugin, 'log' ) ) {
			$this->plugin->log( '[MCP] ' . $message, 'info' );
		}
	}

	/**
	 * Wrap a successful tool result.
	 *
	 * @param mixed $data Result data.
	 * @return array
	 */
	private function tool_success( $data ) {
		return array(
			'content'           => array(
				array(
					'type' => 'text',
					'text' => $this->encode( $data ),
				),
			),
			'structuredContent' => $data,
			'isError'           => false,
		);
	}

	/**
	 * Wrap a failed tool result.
	 *
	 * @param string $message Error message.
	 * @return array
	 */
	private function tool_error( $message ) {
		return array(
			'content' => array(
				array(
					'type' => 'text',
					'text' => (string) $message,
				),
			),
			'isError' => true,
		);
	}

	/**
	 * List static resources.
	 *
	 * @return array
	 */
	private function list_resources() {
		return array(
			'resources' => array(
				array(
					'uri'         => 'jpcrm://entities',
					'name'        => __( 'Каталог сущностей CRM', 'jetpack-crm-rest-api-improved' ),
					'description' => __( 'Сущности, операции, поля и фильтры.', 'jetpack-crm-rest-api-improved' ),
					'mimeType'    => 'application/json',
				),
				array(
					'uri'         => 'jpcrm://me',
					'name'        => __( 'Текущий пользователь', 'jetpack-crm-rest-api-improved' ),
					'description' => __( 'Пользователь WP и права по сущностям CRM.', 'jetpack-crm-rest-api-improved' ),
					'mimeType'    => 'application/json',
				),
				array(
					'uri'         => 'jpcrm://status',
					'name'        => __( 'Статус API', 'jetpack-crm-rest-api-improved' ),
					'description' => __( 'Доступность API и версии.', 'jetpack-crm-rest-api-improved' ),
					'mimeType'    => 'application/json',
				),
				array(
					'uri'         => 'jpcrm://openapi',
					'name'        => __( 'OpenAPI-схема', 'jetpack-crm-rest-api-improved' ),
					'description' => __( 'OpenAPI 3 документ REST API.', 'jetpack-crm-rest-api-improved' ),
					'mimeType'    => 'application/json',
				),
			),
		);
	}

	/**
	 * List resource templates.
	 *
	 * @return array
	 */
	private function list_resource_templates() {
		return array(
			'resourceTemplates' => array(
				array(
					'uriTemplate' => 'jpcrm://{entity}/{id}',
					'name'        => __( 'Объект CRM', 'jetpack-crm-rest-api-improved' ),
					'description' => __( 'Чтение сущности по id (например, contacts/12, invoices/5).', 'jetpack-crm-rest-api-improved' ),
					'mimeType'    => 'application/json',
				),
			),
		);
	}

	/**
	 * List available prompts.
	 *
	 * @return array
	 */
	private function list_prompts() {
		return array(
			'prompts' => array(
				array(
					'name'        => 'summarize_contact',
					'description' => __( 'Сводка по контакту: данные, сделки, счета, активность.', 'jetpack-crm-rest-api-improved' ),
					'arguments'   => array(
						array(
							'name'     => 'id',
							'required' => true,
						),
					),
				),
				array(
					'name'        => 'contact_timeline',
					'description' => __( 'Лента активности контакта: логи, письма, задачи.', 'jetpack-crm-rest-api-improved' ),
					'arguments'   => array(
						array(
							'name'     => 'id',
							'required' => true,
						),
					),
				),
				array(
					'name'        => 'pipeline_review',
					'description' => __( 'Обзор КП и счетов по статусам.', 'jetpack-crm-rest-api-improved' ),
					'arguments'   => array(),
				),
				array(
					'name'        => 'draft_email',
					'description' => __( 'Черновик письма контакту под цель.', 'jetpack-crm-rest-api-improved' ),
					'arguments'   => array(
						array(
							'name'     => 'id',
							'required' => true,
						),
						array(
							'name'     => 'goal',
							'required' => false,
						),
					),
				),
			),
		);
	}

	/**
	 * Build a prompt by name.
	 *
	 * @param mixed $id     Request id.
	 * @param array $params Request params.
	 * @return array
	 */
	private function get_prompt( $id, array $params ) {
		$name = isset( $params['name'] ) ? (string) $params['name'] : '';
		$args = isset( $params['arguments'] ) && is_array( $params['arguments'] ) ? $params['arguments'] : array();

		switch ( $name ) {
			case 'summarize_contact':
				$cid = isset( $args['id'] ) ? (int) $args['id'] : 0;

				if ( $cid <= 0 ) {
					return JsonRpc::error( $id, JsonRpc::INVALID_PARAMS, 'Argument "id" is required.' );
				}

				return $this->prompt_result(
					$id,
					__( 'Сводка по контакту', 'jetpack-crm-rest-api-improved' ),
					"Собери краткую сводку по контакту id={$cid}. " .
					"Вызови crm_get {entity:\"contacts\", id:{$cid}}, затем crm_search по invoices, quotes, transactions, tasks, logs с filters {\"contact\":{$cid}}. " .
					'Опиши: имя, статус, компании, открытые счета/КП, сумму, последнюю активность. Не выдумывай данные.'
				);

			case 'contact_timeline':
				$cid = isset( $args['id'] ) ? (int) $args['id'] : 0;

				if ( $cid <= 0 ) {
					return JsonRpc::error( $id, JsonRpc::INVALID_PARAMS, 'Argument "id" is required.' );
				}

				return $this->prompt_result(
					$id,
					__( 'Лента активности контакта', 'jetpack-crm-rest-api-improved' ),
					"Построй ленту активности контакта id={$cid} по датам. " .
					"Собери логи (entity \"logs\", filters {\"object_type\":1,\"object_id\":{$cid}}), письма (entity \"emails\", filters {\"contact\":{$cid}}) и задачи (entity \"tasks\", filters {\"contact\":{$cid}}). " .
					'Отсортируй по времени, укажи тип события и краткое описание.'
				);

			case 'pipeline_review':
				return $this->prompt_result(
					$id,
					__( 'Обзор воронки', 'jetpack-crm-rest-api-improved' ),
					'Сделай обзор воронки: вызови crm_search по quotes и invoices с разбивкой по status. ' .
					'Выведи количество и суммы по каждому статусу, затем предложи 3 приоритетных действия по «зависшим» сделкам.'
				);

			case 'draft_email':
				$cid  = isset( $args['id'] ) ? (int) $args['id'] : 0;
				$goal = isset( $args['goal'] ) ? sanitize_text_field( (string) $args['goal'] ) : '';

				if ( $cid <= 0 ) {
					return JsonRpc::error( $id, JsonRpc::INVALID_PARAMS, 'Argument "id" is required.' );
				}

				$goal_text = '' !== $goal ? "Цель письма: {$goal}." : 'Цель: поддерживающее деловое письмо.';

				return $this->prompt_result(
					$id,
					__( 'Черновик письма', 'jetpack-crm-rest-api-improved' ),
					"Подготовь черновик письма контакту id={$cid}. {$goal_text} " .
					'Сначала прочитай контакт (crm_get entity "contacts") и его контекст (счета/КП/задачи). ' .
					'Затем верни тему и HTML-тело письма. Отправляй только после подтверждения пользователя через emails_send.'
				);

			default:
				return JsonRpc::error( $id, JsonRpc::INVALID_PARAMS, 'Unknown prompt: ' . $name );
		}
	}

	/**
	 * Build a prompt result.
	 *
	 * @param mixed  $id          Request id.
	 * @param string $description Prompt description.
	 * @param string $text        Prompt text.
	 * @return array
	 */
	private function prompt_result( $id, $description, $text ) {
		return JsonRpc::result(
			$id,
			array(
				'description' => $description,
				'messages'    => array(
					array(
						'role'    => 'user',
						'content' => array(
							'type' => 'text',
							'text' => $text,
						),
					),
				),
			)
		);
	}

	/**
	 * Read a resource by URI.
	 *
	 * @param mixed $id     Request id.
	 * @param array $params Request params.
	 * @return array
	 */
	private function read_resource( $id, array $params ) {
		$uri = isset( $params['uri'] ) ? (string) $params['uri'] : '';

		if ( 'jpcrm://me' === $uri ) {
			$data = Dispatcher::request( 'GET', '/me' );
		} elseif ( 'jpcrm://status' === $uri ) {
			$data = Dispatcher::request( 'GET', '/status' );
		} elseif ( 'jpcrm://openapi' === $uri ) {
			$data = OpenApiGenerator::generate();
		} elseif ( 'jpcrm://entities' === $uri ) {
			$data = array( 'entities' => array_values( EntityRegistry::for_user() ) );
		} elseif ( preg_match( '#^jpcrm://entities/([A-Za-z0-9_-]+)$#', $uri, $matches ) ) {
			$catalog = EntityRegistry::for_user();

			if ( ! isset( $catalog[ $matches[1] ] ) ) {
				return JsonRpc::error( $id, JsonRpc::RESOURCE_NOT_FOUND, 'Resource not found: ' . $uri );
			}

			$data = $catalog[ $matches[1] ];
		} elseif ( preg_match( '#^jpcrm://([A-Za-z0-9_-]+)/(\d+)$#', $uri, $matches ) ) {
			$spec = EntityRegistry::get( $matches[1] );

			if ( ! $spec ) {
				return JsonRpc::error( $id, JsonRpc::RESOURCE_NOT_FOUND, 'Resource not found: ' . $uri );
			}

			$data = Dispatcher::request( 'GET', $spec['path'] . '/' . (int) $matches[2] );
		} else {
			return JsonRpc::error( $id, JsonRpc::RESOURCE_NOT_FOUND, 'Resource not found: ' . $uri );
		}

		if ( is_wp_error( $data ) ) {
			return JsonRpc::error( $id, JsonRpc::INTERNAL_ERROR, $data->get_error_message() );
		}

		return JsonRpc::result(
			$id,
			array(
				'contents' => array(
					array(
						'uri'      => $uri,
						'mimeType' => 'application/json',
						'text'     => $this->encode( $data ),
					),
				),
			)
		);
	}

	/**
	 * Encode data as JSON for text content.
	 *
	 * @param mixed $data Data.
	 * @return string
	 */
	private function encode( $data ) {
		$encoded = wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		return false === $encoded ? '' : $encoded;
	}

	/**
	 * Build a REST response.
	 *
	 * @param mixed $data Response data.
	 * @return WP_REST_Response
	 */
	private function respond( $data ) {
		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Whether a decoded value looks like a single JSON-RPC request.
	 *
	 * @param mixed $value Decoded value.
	 * @return bool
	 */
	private function is_request_object( $value ) {
		return is_array( $value )
			&& ! self::is_list( $value )
			&& ( isset( $value['method'] ) || isset( $value['jsonrpc'] ) );
	}

	/**
	 * Whether an array is a sequential list.
	 *
	 * @param array $array Array.
	 * @return bool
	 */
	private static function is_list( array $array ) {
		if ( array() === $array ) {
			return true;
		}

		return array_keys( $array ) === range( 0, count( $array ) - 1 );
	}
}
