<?php
/**
 * Registry of MCP tools.
 *
 * @package Jetpack_CRM_REST_API_Improved
 */

namespace Jetpack_CRM_REST_API_Improved\Mcp;

use Jetpack_CRM_REST_API_Improved\Admin\SettingsPage;
use Jetpack_CRM_REST_API_Improved\Mcp\Tools\ActionTools;
use Jetpack_CRM_REST_API_Improved\Mcp\Tools\CrudTools;
use Jetpack_CRM_REST_API_Improved\Mcp\Tools\EmailTools;
use Jetpack_CRM_REST_API_Improved\Mcp\Tools\ResourceTools;
use Jetpack_CRM_REST_API_Improved\Mcp\Tools\SystemTools;
use Jetpack_CRM_REST_API_Improved\Rest\Permissions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Holds tool definitions and resolves which are available to the current user.
 */
class ToolRegistry {

	/**
	 * Plugin instance.
	 *
	 * @var object|null
	 */
	private $plugin;

	/**
	 * Registered tools keyed by name.
	 *
	 * @var array<string,array>
	 */
	private $tools = array();

	/**
	 * Constructor.
	 *
	 * @param object|null $plugin Plugin instance.
	 */
	public function __construct( $plugin = null ) {
		$this->plugin = $plugin;
		$this->register_system_tools();
		$this->register_crud_tools();
		$this->register_email_tools();
		$this->register_resource_tools();
		$this->register_action_tools();

		/**
		 * Fires after the built-in MCP tools have been registered.
		 *
		 * @param ToolRegistry $registry Tool registry.
		 */
		do_action( 'jpcrm_improved_mcp_register_tools', $this );
	}

	/**
	 * Register a tool.
	 *
	 * @param array $tool Tool definition.
	 * @return void
	 */
	public function add( array $tool ) {
		if ( empty( $tool['name'] ) ) {
			return;
		}

		$this->tools[ $tool['name'] ] = $tool;
	}

	/**
	 * Get a tool by name.
	 *
	 * @param string $name Tool name.
	 * @return array|null
	 */
	public function get( $name ) {
		return isset( $this->tools[ $name ] ) ? $this->tools[ $name ] : null;
	}

	/**
	 * Get all tools.
	 *
	 * @return array<string,array>
	 */
	public function all() {
		return $this->tools;
	}

	/**
	 * Whether the current user may use a tool.
	 *
	 * @param array $tool Tool definition.
	 * @return bool
	 */
	public function user_can( array $tool ) {
		if ( ! empty( $tool['setting'] ) && ! get_option( $tool['setting'], false ) ) {
			return false;
		}

		if ( $this->is_write( $tool ) && SettingsPage::is_readonly() ) {
			return false;
		}

		if ( ! empty( $tool['capability'] ) ) {
			return Permissions::can( $tool['capability'][0], $tool['capability'][1] );
		}

		if ( ! empty( $tool['requires_any'] ) ) {
			$action = $tool['requires_any'];

			foreach ( EntityRegistry::all() as $spec ) {
				if ( Permissions::can( $spec['permission_resource'], $action ) ) {
					return true;
				}
			}

			return false;
		}

		return true;
	}

	/**
	 * Whether a tool performs writes.
	 *
	 * @param array $tool Tool definition.
	 * @return bool
	 */
	public function is_write( array $tool ) {
		return isset( $tool['annotations']['readOnlyHint'] ) && false === $tool['annotations']['readOnlyHint'];
	}

	/**
	 * Whether a tool requires explicit confirmation (and it is enabled).
	 *
	 * @param array $tool Tool definition.
	 * @return bool
	 */
	public function requires_confirmation( array $tool ) {
		return ! empty( $tool['confirm'] ) && SettingsPage::requires_confirm();
	}

	/**
	 * Get tools available to the current user.
	 *
	 * @return array
	 */
	public function for_current_user() {
		$available = array();

		foreach ( $this->tools as $tool ) {
			if ( $this->user_can( $tool ) ) {
				$available[] = $tool;
			}
		}

		return $available;
	}

	/**
	 * Register built-in system tools.
	 *
	 * @return void
	 */
	private function register_system_tools() {
		$system = new SystemTools( $this->plugin );

		$this->add(
			array(
				'name'        => 'crm_status',
				'description' => __( 'Проверить доступность Jetpack CRM REST API и получить версии.', 'jetpack-crm-rest-api-improved' ),
				'inputSchema' => self::empty_schema(),
				'capability'  => array( 'status', 'read' ),
				'annotations' => self::read_only_annotations(),
				'handler'     => array( $system, 'status' ),
			)
		);

		$this->add(
			array(
				'name'        => 'crm_me',
				'description' => __( 'Вернуть текущего пользователя WP и его права по сущностям CRM.', 'jetpack-crm-rest-api-improved' ),
				'inputSchema' => self::empty_schema(),
				'capability'  => array( 'status', 'read' ),
				'annotations' => self::read_only_annotations(),
				'handler'     => array( $system, 'me' ),
			)
		);

		$this->add(
			array(
				'name'        => 'crm_entities',
				'description' => __( 'Каталог сущностей CRM: доступные операции, поля, фильтры. Без аргумента — список; с entity — подробно.', 'jetpack-crm-rest-api-improved' ),
				'inputSchema' => array(
					'type'                 => 'object',
					'properties'           => array(
						'entity' => array(
							'type'        => 'string',
							'description' => __( 'Имя сущности (contacts, invoices, ...).', 'jetpack-crm-rest-api-improved' ),
						),
					),
					'additionalProperties' => false,
				),
				'capability'  => array( 'status', 'read' ),
				'annotations' => self::read_only_annotations(),
				'handler'     => array( $system, 'entities' ),
			)
		);
	}

	/**
	 * Register generic CRUD tools and the raw fallback.
	 *
	 * @return void
	 */
	private function register_crud_tools() {
		$crud = new CrudTools( $this->plugin );

		$entity_prop = array(
			'type'        => 'string',
			'description' => __( 'Имя сущности (contacts, companies, invoices, quotes, transactions, tasks, logs, tags, forms, segments, quote-templates, emails, email-templates).', 'jetpack-crm-rest-api-improved' ),
		);

		$this->add(
			array(
				'name'         => 'crm_search',
				'description'  => __( 'Найти или вывести список объектов CRM. Аргумент entity обязателен. Доступные фильтры см. в crm_entities. По умолчанию компактный вывод.', 'jetpack-crm-rest-api-improved' ),
				'inputSchema'  => array(
					'type'                 => 'object',
					'required'             => array( 'entity' ),
					'properties'           => array(
						'entity'          => $entity_prop,
						'query'           => array(
							'type'        => 'string',
							'description' => __( 'Поисковая фраза.', 'jetpack-crm-rest-api-improved' ),
						),
						'filters'         => array(
							'type'        => 'object',
							'description' => __( 'Фильтры сущности, напр. {"status":"Lead","company":7}.', 'jetpack-crm-rest-api-improved' ),
						),
						'orderby'         => array( 'type' => 'string' ),
						'order'           => array(
							'type' => 'string',
							'enum' => array( 'asc', 'desc' ),
						),
						'page'            => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'per_page'        => array( 'type' => 'integer' ),
						'fields'          => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'response_format' => array(
							'type' => 'string',
							'enum' => array( 'concise', 'detailed' ),
						),
					),
					'additionalProperties' => false,
				),
				'requires_any' => 'read',
				'annotations'  => self::read_only_annotations(),
				'handler'      => array( $crud, 'search' ),
			)
		);

		$this->add(
			array(
				'name'         => 'crm_get',
				'description'  => __( 'Прочитать один объект CRM по id. По умолчанию полный объект.', 'jetpack-crm-rest-api-improved' ),
				'inputSchema'  => array(
					'type'                 => 'object',
					'required'             => array( 'entity', 'id' ),
					'properties'           => array(
						'entity'          => $entity_prop,
						'id'              => array( 'type' => 'integer' ),
						'fields'          => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'response_format' => array(
							'type' => 'string',
							'enum' => array( 'concise', 'detailed' ),
						),
					),
					'additionalProperties' => false,
				),
				'requires_any' => 'read',
				'annotations'  => self::read_only_annotations(),
				'handler'      => array( $crud, 'get' ),
			)
		);

		$this->add(
			array(
				'name'         => 'crm_create',
				'description'  => __( 'Создать объект CRM. Поля — в аргументе data (REST-формат, см. crm_entities).', 'jetpack-crm-rest-api-improved' ),
				'inputSchema'  => array(
					'type'                 => 'object',
					'required'             => array( 'entity', 'data' ),
					'properties'           => array(
						'entity' => $entity_prop,
						'data'   => array(
							'type'        => 'object',
							'description' => __( 'Поля объекта.', 'jetpack-crm-rest-api-improved' ),
						),
					),
					'additionalProperties' => false,
				),
				'requires_any' => 'write',
				'annotations'  => self::write_annotations( false, false ),
				'handler'      => array( $crud, 'create' ),
			)
		);

		$this->add(
			array(
				'name'         => 'crm_update',
				'description'  => __( 'Обновить объект CRM. По умолчанию частичное обновление (partial=true).', 'jetpack-crm-rest-api-improved' ),
				'inputSchema'  => array(
					'type'                 => 'object',
					'required'             => array( 'entity', 'id', 'data' ),
					'properties'           => array(
						'entity'  => $entity_prop,
						'id'      => array( 'type' => 'integer' ),
						'data'    => array(
							'type'        => 'object',
							'description' => __( 'Поля для обновления.', 'jetpack-crm-rest-api-improved' ),
						),
						'partial' => array(
							'type'        => 'boolean',
							'description' => __( 'true — PATCH (частично), false — PUT (полностью).', 'jetpack-crm-rest-api-improved' ),
						),
					),
					'additionalProperties' => false,
				),
				'requires_any' => 'write',
				'annotations'  => self::write_annotations( false, true ),
				'handler'      => array( $crud, 'update' ),
			)
		);

		$this->add(
			array(
				'name'         => 'crm_delete',
				'description'  => __( 'Удалить объект CRM по id.', 'jetpack-crm-rest-api-improved' ),
				'inputSchema'  => array(
					'type'                 => 'object',
					'required'             => array( 'entity', 'id' ),
					'properties'           => array(
						'entity'  => $entity_prop,
						'id'      => array( 'type' => 'integer' ),
						'confirm' => array(
							'type'        => 'boolean',
							'description' => __( 'Подтверждение удаления (если включено в настройках).', 'jetpack-crm-rest-api-improved' ),
						),
					),
					'additionalProperties' => false,
				),
				'requires_any' => 'delete',
				'confirm'      => true,
				'annotations'  => self::write_annotations( true, true ),
				'handler'      => array( $crud, 'delete' ),
			)
		);

		$this->add(
			array(
				'name'         => 'crm_batch',
				'description'  => __( 'Выполнить несколько операций записи (create/update/delete) за один вызов.', 'jetpack-crm-rest-api-improved' ),
				'inputSchema'  => array(
					'type'                 => 'object',
					'required'             => array( 'requests' ),
					'properties'           => array(
						'requests'      => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'required'   => array( 'entity', 'operation' ),
								'properties' => array(
									'entity'    => array( 'type' => 'string' ),
									'operation' => array(
										'type' => 'string',
										'enum' => array( 'create', 'update', 'delete' ),
									),
									'id'        => array( 'type' => 'integer' ),
									'data'      => array( 'type' => 'object' ),
								),
							),
						),
						'stop_on_error' => array( 'type' => 'boolean' ),
						'confirm'       => array(
							'type'        => 'boolean',
							'description' => __( 'Подтверждение пакета (если включено в настройках).', 'jetpack-crm-rest-api-improved' ),
						),
					),
					'additionalProperties' => false,
				),
				'requires_any' => 'write',
				'confirm'      => true,
				'annotations'  => self::write_annotations( true, false ),
				'handler'      => array( $crud, 'batch' ),
			)
		);

		$this->add(
			array(
				'name'        => 'crm_raw',
				'description' => __( 'Прямой вызов REST-эндпоинта плагина (jpcrm-improved/v1). Для отладки и непокрытых возможностей; включается в настройках.', 'jetpack-crm-rest-api-improved' ),
				'inputSchema' => array(
					'type'                 => 'object',
					'required'             => array( 'method', 'path' ),
					'properties'           => array(
						'method'  => array(
							'type' => 'string',
							'enum' => array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ),
						),
						'path'    => array(
							'type'        => 'string',
							'description' => __( 'Путь относительно /jpcrm-improved/v1, напр. /contacts.', 'jetpack-crm-rest-api-improved' ),
						),
						'query'   => array( 'type' => 'object' ),
						'body'    => array( 'type' => 'object' ),
						'confirm' => array(
							'type'        => 'boolean',
							'description' => __( 'Подтверждение прямого вызова (если включено в настройках).', 'jetpack-crm-rest-api-improved' ),
						),
					),
					'additionalProperties' => false,
				),
				'setting'     => CrudTools::RAW_OPTION,
				'capability'  => array( 'status', 'read' ),
				'confirm'     => true,
				'annotations' => self::write_annotations( true, false ),
				'handler'     => array( $crud, 'raw' ),
			)
		);
	}

	/**
	 * Register email tools (send, reply, star, read).
	 *
	 * @return void
	 */
	private function register_email_tools() {
		$email = new EmailTools( $this->plugin );

		$this->add(
			array(
				'name'        => 'emails_send',
				'description' => __( 'Отправить письмо контакту. Укажите contact_id или email, а также subject и content (HTML).', 'jetpack-crm-rest-api-improved' ),
				'inputSchema' => array(
					'type'                 => 'object',
					'required'             => array( 'subject', 'content' ),
					'properties'           => array(
						'contact_id'      => array( 'type' => 'integer' ),
						'email'           => array( 'type' => 'string' ),
						'subject'         => array( 'type' => 'string' ),
						'content'         => array( 'type' => 'string' ),
						'delivery_method' => array( 'type' => 'string' ),
					),
					'additionalProperties' => false,
				),
				'capability'  => array( 'emails', 'write' ),
				'annotations' => self::write_annotations( false, false ),
				'handler'     => array( $email, 'send' ),
			)
		);

		$this->add(
			array(
				'name'        => 'email_threads_reply',
				'description' => __( 'Ответить в существующем треде писем.', 'jetpack-crm-rest-api-improved' ),
				'inputSchema' => array(
					'type'                 => 'object',
					'required'             => array( 'id', 'content' ),
					'properties'           => array(
						'id'              => array( 'type' => 'integer' ),
						'subject'         => array( 'type' => 'string' ),
						'content'         => array( 'type' => 'string' ),
						'delivery_method' => array( 'type' => 'string' ),
					),
					'additionalProperties' => false,
				),
				'capability'  => array( 'emails', 'write' ),
				'annotations' => self::write_annotations( false, false ),
				'handler'     => array( $email, 'reply' ),
			)
		);

		$this->add(
			array(
				'name'        => 'email_threads_star',
				'description' => __( 'Добавить тред в избранное или убрать из него (starred).', 'jetpack-crm-rest-api-improved' ),
				'inputSchema' => array(
					'type'                 => 'object',
					'required'             => array( 'id' ),
					'properties'           => array(
						'id'      => array( 'type' => 'integer' ),
						'starred' => array(
							'type'        => 'boolean',
							'description' => __( 'true — в избранное, false — убрать.', 'jetpack-crm-rest-api-improved' ),
						),
					),
					'additionalProperties' => false,
				),
				'capability'  => array( 'emails', 'write' ),
				'annotations' => self::write_annotations( false, true ),
				'handler'     => array( $email, 'star' ),
			)
		);

		$this->add(
			array(
				'name'        => 'email_threads_read',
				'description' => __( 'Отметить тред как прочитанный.', 'jetpack-crm-rest-api-improved' ),
				'inputSchema' => array(
					'type'                 => 'object',
					'required'             => array( 'id' ),
					'properties'           => array(
						'id' => array( 'type' => 'integer' ),
					),
					'additionalProperties' => false,
				),
				'capability'  => array( 'emails', 'write' ),
				'annotations' => self::write_annotations( false, true ),
				'handler'     => array( $email, 'read' ),
			)
		);
	}

	/**
	 * Register subresource tools (tags, meta, custom fields).
	 *
	 * @return void
	 */
	private function register_resource_tools() {
		$resource = new ResourceTools( $this->plugin );

		$entity_prop = array(
			'type'        => 'string',
			'description' => __( 'Имя сущности (contacts, companies, invoices, ...).', 'jetpack-crm-rest-api-improved' ),
		);

		$this->add(
			array(
				'name'         => 'crm_tags',
				'description'  => __( 'Теги объекта: get — список, set — задать (tags + mode), remove — убрать один тег (tag_id).', 'jetpack-crm-rest-api-improved' ),
				'inputSchema'  => array(
					'type'                 => 'object',
					'required'             => array( 'entity', 'id' ),
					'properties'           => array(
						'entity' => $entity_prop,
						'id'     => array( 'type' => 'integer' ),
						'action' => array(
							'type' => 'string',
							'enum' => array( 'get', 'set', 'remove' ),
						),
						'tags'   => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'tag_id' => array( 'type' => 'integer' ),
						'mode'   => array(
							'type' => 'string',
							'enum' => array( 'replace', 'append', 'remove' ),
						),
					),
					'additionalProperties' => false,
				),
				'requires_any' => 'read',
				'annotations'  => self::read_only_annotations(),
				'handler'      => array( $resource, 'tags' ),
			)
		);

		$this->add(
			array(
				'name'         => 'crm_meta',
				'description'  => __( 'Meta объекта: get — все или по key, set — записать key/value, delete — удалить key.', 'jetpack-crm-rest-api-improved' ),
				'inputSchema'  => array(
					'type'                 => 'object',
					'required'             => array( 'entity', 'id' ),
					'properties'           => array(
						'entity' => $entity_prop,
						'id'     => array( 'type' => 'integer' ),
						'action' => array(
							'type' => 'string',
							'enum' => array( 'get', 'set', 'delete' ),
						),
						'key'    => array( 'type' => 'string' ),
						'value'  => array( 'description' => __( 'Любое JSON-значение.', 'jetpack-crm-rest-api-improved' ) ),
					),
					'additionalProperties' => false,
				),
				'requires_any' => 'read',
				'annotations'  => self::read_only_annotations(),
				'handler'      => array( $resource, 'meta' ),
			)
		);

		$this->add(
			array(
				'name'         => 'crm_custom_fields',
				'description'  => __( 'Кастомные поля объекта: get — все или по key, set — записать key/value или объект values.', 'jetpack-crm-rest-api-improved' ),
				'inputSchema'  => array(
					'type'                 => 'object',
					'required'             => array( 'entity', 'id' ),
					'properties'           => array(
						'entity' => $entity_prop,
						'id'     => array( 'type' => 'integer' ),
						'action' => array(
							'type' => 'string',
							'enum' => array( 'get', 'set' ),
						),
						'key'    => array( 'type' => 'string' ),
						'value'  => array( 'description' => __( 'Значение одного поля.', 'jetpack-crm-rest-api-improved' ) ),
						'values' => array(
							'type'        => 'object',
							'description' => __( 'Набор значений {ключ: значение}.', 'jetpack-crm-rest-api-improved' ),
						),
					),
					'additionalProperties' => false,
				),
				'requires_any' => 'read',
				'annotations'  => self::read_only_annotations(),
				'handler'      => array( $resource, 'custom_fields' ),
			)
		);

		$this->add(
			array(
				'name'         => 'crm_external_sources',
				'description'  => __( 'Внешние источники объекта: get — список, add — добавить (source+uid), delete — удалить по ext_id.', 'jetpack-crm-rest-api-improved' ),
				'inputSchema'  => array(
					'type'                 => 'object',
					'required'             => array( 'entity', 'id' ),
					'properties'           => array(
						'entity' => $entity_prop,
						'id'     => array( 'type' => 'integer' ),
						'action' => array(
							'type' => 'string',
							'enum' => array( 'get', 'add', 'delete' ),
						),
						'source' => array( 'type' => 'string' ),
						'uid'    => array( 'type' => 'string' ),
						'origin' => array( 'type' => 'string' ),
						'ext_id' => array( 'type' => 'integer' ),
					),
					'additionalProperties' => false,
				),
				'requires_any' => 'read',
				'annotations'  => self::read_only_annotations(),
				'handler'      => array( $resource, 'external_sources' ),
			)
		);

		$this->add(
			array(
				'name'         => 'crm_links',
				'description'  => __( 'Связи объекта: get — список (object_type можно сузить), add — создать, delete — удалить.', 'jetpack-crm-rest-api-improved' ),
				'inputSchema'  => array(
					'type'                 => 'object',
					'required'             => array( 'entity', 'id' ),
					'properties'           => array(
						'entity'      => $entity_prop,
						'id'          => array( 'type' => 'integer' ),
						'action'      => array(
							'type' => 'string',
							'enum' => array( 'get', 'add', 'delete' ),
						),
						'object_type' => array(
							'type'        => array( 'string', 'integer' ),
							'description' => __( 'Имя сущности (companies) или числовой ZBS-тип.', 'jetpack-crm-rest-api-improved' ),
						),
						'object_id'   => array( 'type' => 'integer' ),
					),
					'additionalProperties' => false,
				),
				'requires_any' => 'read',
				'annotations'  => self::read_only_annotations(),
				'handler'      => array( $resource, 'links' ),
			)
		);
	}

	/**
	 * Register action tools (quote accept, segment compile).
	 *
	 * @return void
	 */
	private function register_action_tools() {
		$action = new ActionTools( $this->plugin );

		$this->add(
			array(
				'name'        => 'quotes_accept',
				'description' => __( 'Пометить КП принятым (accept=true) или отменить принятие (accept=false).', 'jetpack-crm-rest-api-improved' ),
				'inputSchema' => array(
					'type'                 => 'object',
					'required'             => array( 'id' ),
					'properties'           => array(
						'id'        => array( 'type' => 'integer' ),
						'signed_by' => array( 'type' => 'string' ),
						'accept'    => array(
							'type'        => 'boolean',
							'description' => __( 'true — принять, false — отменить.', 'jetpack-crm-rest-api-improved' ),
						),
					),
					'additionalProperties' => false,
				),
				'capability'  => array( 'quotes', 'write' ),
				'annotations' => self::write_annotations( false, true ),
				'handler'     => array( $action, 'quote_accept' ),
			)
		);

		$this->add(
			array(
				'name'        => 'segments_compile',
				'description' => __( 'Пересобрать сегмент и получить количество совпадений.', 'jetpack-crm-rest-api-improved' ),
				'inputSchema' => array(
					'type'                 => 'object',
					'required'             => array( 'id' ),
					'properties'           => array(
						'id' => array( 'type' => 'integer' ),
					),
					'additionalProperties' => false,
				),
				'capability'  => array( 'segments', 'write' ),
				'annotations' => self::write_annotations( false, true ),
				'handler'     => array( $action, 'segment_compile' ),
			)
		);
	}

	/**
	 * Annotations for write tools.
	 *
	 * @param bool $destructive Whether the tool is destructive.
	 * @param bool $idempotent  Whether the tool is idempotent.
	 * @return array
	 */
	private static function write_annotations( $destructive, $idempotent ) {
		return array(
			'readOnlyHint'    => false,
			'destructiveHint' => (bool) $destructive,
			'idempotentHint'  => (bool) $idempotent,
			'openWorldHint'   => false,
		);
	}

	/**
	 * JSON Schema for a tool without arguments.
	 *
	 * @return array
	 */
	private static function empty_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => (object) array(),
			'additionalProperties' => false,
		);
	}

	/**
	 * Standard annotations for read-only tools.
	 *
	 * @return array
	 */
	private static function read_only_annotations() {
		return array(
			'readOnlyHint'    => true,
			'destructiveHint' => false,
			'idempotentHint'  => true,
			'openWorldHint'   => false,
		);
	}
}
