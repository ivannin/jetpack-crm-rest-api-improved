<?php
/**
 * Admin settings page.
 *
 * @package Jetpack_CRM_REST_API_Improved
 */

namespace Jetpack_CRM_REST_API_Improved\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings page for the REST API and MCP endpoint.
 */
class SettingsPage {

	const OPTION_ENABLED  = 'jpcrm_improved_mcp_enabled';
	const OPTION_RAW      = 'jpcrm_improved_mcp_raw_enabled';
	const OPTION_READONLY = 'jpcrm_improved_mcp_readonly';
	const OPTION_LOGGING  = 'jpcrm_improved_mcp_logging';
	const OPTION_CONFIRM  = 'jpcrm_improved_mcp_confirm';
	const OPTION_GROUP    = 'jpcrm_improved_mcp';
	const CAPABILITY      = 'admin_zerobs_manage_options';

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

		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register the setting.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_ENABLED,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_enabled' ),
				'default'           => false,
				'show_in_rest'      => false,
			)
		);

		register_setting(
			self::OPTION_GROUP,
			self::OPTION_RAW,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_enabled' ),
				'default'           => false,
				'show_in_rest'      => false,
			)
		);

		foreach ( array( self::OPTION_READONLY, self::OPTION_LOGGING, self::OPTION_CONFIRM ) as $option ) {
			register_setting(
				self::OPTION_GROUP,
				$option,
				array(
					'type'              => 'boolean',
					'sanitize_callback' => array( $this, 'sanitize_enabled' ),
					'default'           => false,
					'show_in_rest'      => false,
				)
			);
		}
	}

	/**
	 * Sanitize the enabled flag.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	public function sanitize_enabled( $value ) {
		return ! empty( $value );
	}

	/**
	 * Whether the MCP endpoint is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return (bool) get_option( self::OPTION_ENABLED, false );
	}

	/**
	 * Whether the MCP server runs in read-only mode.
	 *
	 * @return bool
	 */
	public static function is_readonly() {
		return (bool) get_option( self::OPTION_READONLY, false );
	}

	/**
	 * Whether MCP request logging is enabled.
	 *
	 * @return bool
	 */
	public static function is_logging() {
		return (bool) get_option( self::OPTION_LOGGING, false );
	}

	/**
	 * Whether destructive tools require explicit confirmation.
	 *
	 * @return bool
	 */
	public static function requires_confirm() {
		return (bool) get_option( self::OPTION_CONFIRM, false );
	}

	/**
	 * MCP endpoint URL.
	 *
	 * @return string
	 */
	public static function endpoint_url() {
		return rest_url( 'jpcrm-improved/v1/mcp' );
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'У вас нет прав для доступа к этой странице.', 'jetpack-crm-rest-api-improved' ) );
		}

		$enabled     = self::is_enabled();
		$raw_enabled = (bool) get_option( self::OPTION_RAW, false );
		$readonly    = self::is_readonly();
		$logging     = self::is_logging();
		$confirm     = self::requires_confirm();
		?>
		<div class="wrap zbs-admin-wrap">
			<h1><?php echo esc_html__( 'Jetpack CRM REST API / MCP', 'jetpack-crm-rest-api-improved' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION_GROUP ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php echo esc_html__( 'MCP-сервер', 'jetpack-crm-rest-api-improved' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_ENABLED ); ?>" value="1" <?php checked( $enabled ); ?> />
								<?php echo esc_html__( 'Включить MCP-эндпоинт', 'jetpack-crm-rest-api-improved' ); ?>
							</label>
							<p class="description">
								<?php echo esc_html__( 'Эндпоинт:', 'jetpack-crm-rest-api-improved' ); ?>
								<code><?php echo esc_html( self::endpoint_url() ); ?></code>
							</p>
							<p class="description">
								<?php echo esc_html__( 'Аутентификация: Application Password (заголовок Authorization: Basic ...).', 'jetpack-crm-rest-api-improved' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'crm_raw', 'jetpack-crm-rest-api-improved' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_RAW ); ?>" value="1" <?php checked( $raw_enabled ); ?> />
								<?php echo esc_html__( 'Разрешить инструмент crm_raw (прямой вызов REST-эндпоинтов)', 'jetpack-crm-rest-api-improved' ); ?>
							</label>
							<p class="description">
								<?php echo esc_html__( 'Для отладки и непокрытых возможностей. Не рекомендуется в проде.', 'jetpack-crm-rest-api-improved' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Только чтение', 'jetpack-crm-rest-api-improved' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_READONLY ); ?>" value="1" <?php checked( $readonly ); ?> />
								<?php echo esc_html__( 'Запретить операции записи (read-only)', 'jetpack-crm-rest-api-improved' ); ?>
							</label>
							<p class="description">
								<?php echo esc_html__( 'Агенту доступны только чтение и поиск. Запись, удаление, письма и crm_raw отключаются.', 'jetpack-crm-rest-api-improved' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Подтверждение', 'jetpack-crm-rest-api-improved' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_CONFIRM ); ?>" value="1" <?php checked( $confirm ); ?> />
								<?php echo esc_html__( 'Требовать confirm=true для разрушительных операций', 'jetpack-crm-rest-api-improved' ); ?>
							</label>
							<p class="description">
								<?php echo esc_html__( 'Касается crm_delete, crm_batch и crm_raw.', 'jetpack-crm-rest-api-improved' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Логирование', 'jetpack-crm-rest-api-improved' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_LOGGING ); ?>" value="1" <?php checked( $logging ); ?> />
								<?php echo esc_html__( 'Логировать вызовы MCP', 'jetpack-crm-rest-api-improved' ); ?>
							</label>
							<p class="description">
								<?php echo esc_html__( 'Пишет метод и инструмент (без секретов) в debug.log при WP_DEBUG.', 'jetpack-crm-rest-api-improved' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
