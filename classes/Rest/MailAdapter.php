<?php
/**
 * Adapter for email history and templates (no DAL3).
 *
 * @package Jetpack_CRM_REST_API_Improved
 */

namespace Jetpack_CRM_REST_API_Improved\Rest;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MailAdapter {

	/**
	 * Ensure the core email AJAX functions are available.
	 *
	 * `jpcrm_send_single_email_from_box()` and `zeroBSCRM_mark_as_read()` live in
	 * `admin/email/email.ajax.php`, which is not loaded on REST requests.
	 *
	 * @return void
	 */
	public static function ensure_email_functions() {
		if ( function_exists( 'jpcrm_send_single_email_from_box' ) ) {
			return;
		}

		if ( ! defined( 'ZEROBSCRM_PATH' ) ) {
			return;
		}

		$file = ZEROBSCRM_PATH . 'admin/email/email.ajax.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}

	/**
	 * Send a new email to a contact.
	 *
	 * @param int    $contact_id      Contact ID (optional if email is given).
	 * @param string $email           Recipient email (optional if contact_id is given).
	 * @param string $subject         Subject.
	 * @param string $content         HTML content.
	 * @param mixed  $delivery_method Delivery method key (-1 for default).
	 * @return array|WP_Error Formatted sent message or error.
	 */
	public static function send_email( $contact_id, $email, $subject, $content, $delivery_method = -1 ) {
		self::ensure_email_functions();

		$email      = '' !== (string) $email ? sanitize_email( (string) $email ) : '';
		$contact_id = (int) $contact_id;

		if ( '' === $email && $contact_id > 0 ) {
			$email = self::contact_email( $contact_id );
		}

		if ( '' === $email || ! zeroBSCRM_validateEmail( $email ) ) {
			return new WP_Error(
				'jpcrm_rest_validation_error',
				__( 'Некорректный email получателя.', 'jetpack-crm-rest-api-improved' ),
				array( 'status' => 422 )
			);
		}

		$resolved_id = (int) zeroBS_getCustomerIDWithEmail( $email );

		if ( $resolved_id <= 0 ) {
			return new WP_Error(
				'jpcrm_rest_no_contact',
				__( 'Контакт с таким email не найден. Письма можно отправлять только существующим контактам.', 'jetpack-crm-rest-api-improved' ),
				array( 'status' => 422 )
			);
		}

		$result = self::dispatch_send( $email, -1, $delivery_method, $subject, $content );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$message = self::get_latest_email_for_contact( $resolved_id );

		return null !== $message ? $message : array( 'sent' => true, 'contact_id' => $resolved_id );
	}

	/**
	 * Reply within an existing email thread.
	 *
	 * @param int    $thread_id       Thread ID.
	 * @param string $subject         Subject.
	 * @param string $content         HTML content.
	 * @param mixed  $delivery_method Delivery method key (-1 for thread/default).
	 * @return array|WP_Error Formatted sent message or error.
	 */
	public static function reply_thread( $thread_id, $subject, $content, $delivery_method = -1 ) {
		self::ensure_email_functions();

		global $wpdb;
		$table = self::hist_table();

		if ( ! $table ) {
			return new WP_Error( 'jpcrm_rest_unknown_error', __( 'Хранилище писем недоступно.', 'jetpack-crm-rest-api-improved' ), array( 'status' => 500 ) );
		}

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE zbsmail_sender_thread = %d ORDER BY ID ASC LIMIT 1", (int) $thread_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		if ( ! $row ) {
			return new WP_Error( 'jpcrm_rest_not_found', __( 'Тред не найден.', 'jetpack-crm-rest-api-improved' ), array( 'status' => 404 ) );
		}

		$email      = isset( $row['zbsmail_receiver_email'] ) ? (string) $row['zbsmail_receiver_email'] : '';
		$contact_id = isset( $row['zbsmail_target_objid'] ) ? (int) $row['zbsmail_target_objid'] : 0;

		if ( '' === $email && $contact_id > 0 ) {
			$email = self::contact_email( $contact_id );
		}

		if ( '' === $email || ! zeroBSCRM_validateEmail( $email ) ) {
			return new WP_Error(
				'jpcrm_rest_validation_error',
				__( 'Не удалось определить email получателя в треде.', 'jetpack-crm-rest-api-improved' ),
				array( 'status' => 422 )
			);
		}

		if ( -1 === (int) $delivery_method && ! empty( $row['zbsmail_sender_maildelivery_key'] ) ) {
			$delivery_method = $row['zbsmail_sender_maildelivery_key'];
		}

		$result = self::dispatch_send( $email, (int) $thread_id, $delivery_method, $subject, $content );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$message = self::get_latest_email_for_thread( (int) $thread_id );

		return null !== $message ? $message : array( 'sent' => true, 'thread_id' => (int) $thread_id );
	}

	/**
	 * Call the core send function with the expected request context.
	 *
	 * `jpcrm_send_single_email_from_box()` reads subject/content from `$_POST`
	 * and verifies a nonce, so we temporarily provide them.
	 *
	 * @param string $email           Recipient email.
	 * @param int    $thread_id       Thread ID (-1 for new).
	 * @param mixed  $delivery_method Delivery method.
	 * @param string $subject         Subject.
	 * @param string $content         HTML content.
	 * @return true|WP_Error
	 */
	private static function dispatch_send( $email, $thread_id, $delivery_method, $subject, $content ) {
		if ( ! function_exists( 'jpcrm_send_single_email_from_box' ) || ! function_exists( 'zeroBSCRM_permsSendEmailContacts' ) ) {
			return new WP_Error( 'jpcrm_rest_unknown_error', __( 'Функции отправки писем недоступны.', 'jetpack-crm-rest-api-improved' ), array( 'status' => 500 ) );
		}

		if ( ! zeroBSCRM_permsSendEmailContacts() ) {
			return new WP_Error( 'jpcrm_rest_forbidden', __( 'Недостаточно прав для отправки писем.', 'jetpack-crm-rest-api-improved' ), array( 'status' => 403 ) );
		}

		$saved_post    = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$saved_request = $_REQUEST; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$_POST['zbs-send-email-title']   = (string) $subject;
		$_POST['zbs_send_email_content'] = (string) $content;
		$_REQUEST['sec']                 = wp_create_nonce( 'zbscrmjs-glob-ajax-nonce' );

		$result = jpcrm_send_single_email_from_box( $email, (int) $thread_id, $delivery_method, false, false );

		$_POST    = $saved_post;
		$_REQUEST = $saved_request;

		if ( true !== $result ) {
			return new WP_Error(
				'jpcrm_rest_send_failed',
				__( 'Не удалось отправить письмо.', 'jetpack-crm-rest-api-improved' ),
				array( 'status' => 500 )
			);
		}

		return true;
	}

	/**
	 * Get the most recent message for a contact.
	 *
	 * @param int $contact_id Contact ID.
	 * @return array|null
	 */
	public static function get_latest_email_for_contact( $contact_id ) {
		global $wpdb;
		$table = self::hist_table();

		if ( ! $table ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE zbsmail_target_objid = %d ORDER BY ID DESC LIMIT 1", (int) $contact_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return $row ? self::format_email( $row ) : null;
	}

	/**
	 * Get the most recent message in a thread.
	 *
	 * @param int $thread_id Thread ID.
	 * @return array|null
	 */
	public static function get_latest_email_for_thread( $thread_id ) {
		global $wpdb;
		$table = self::hist_table();

		if ( ! $table ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE zbsmail_sender_thread = %d ORDER BY ID DESC LIMIT 1", (int) $thread_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return $row ? self::format_email( $row ) : null;
	}

	/**
	 * Count distinct threads.
	 *
	 * @return int
	 */
	public static function count_threads() {
		global $wpdb;
		$table = self::hist_table();

		if ( ! $table ) {
			return 0;
		}

		$count = $wpdb->get_var( "SELECT COUNT(DISTINCT zbsmail_sender_thread) FROM `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (int) $count;
	}

	/**
	 * Resolve a contact's email address.
	 *
	 * @param int $contact_id Contact ID.
	 * @return string
	 */
	private static function contact_email( $contact_id ) {
		$contact_id = (int) $contact_id;

		if ( $contact_id <= 0 ) {
			return '';
		}

		if ( function_exists( 'zeroBS_customerEmail' ) ) {
			$email = zeroBS_customerEmail( $contact_id );

			if ( ! empty( $email ) ) {
				return (string) $email;
			}
		}

		if ( function_exists( 'zeroBS_getCustomer' ) ) {
			$contact = zeroBS_getCustomer( $contact_id );

			if ( is_array( $contact ) && ! empty( $contact['email'] ) ) {
				return (string) $contact['email'];
			}
		}

		return '';
	}

	public static function get_emails( array $args = array() ) {
		if ( ! function_exists( 'zeroBSCRM_get_email_history' ) ) {
			return array();
		}
		$defaults = array(
			'page'        => 0,
			'per_page'    => 20,
			'contact_id'  => false,
			'status'      => false,
			'starred'     => false,
			'thread'      => false,
			'type'        => false,
			'search'      => '',
			'sort_order'  => 'DESC',
		);
		$args = wp_parse_args( $args, $defaults );

		$offset = (int) $args['page'];
		$limit  = (int) $args['per_page'];
		$thread = ! empty( $args['thread'] ) || ! empty( $args['status'] ) || ! empty( $args['starred'] );

		$emails = zeroBSCRM_get_email_history( $offset, $limit, $thread );
		return is_array( $emails ) ? $emails : array();
	}

	public static function get_email( $id ) {
		global $wpdb;
		$table = self::hist_table();
		if ( ! $table ) { return null; }
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE ID = %d", (int) $id ),
			ARRAY_A
		);
		return $row ?: null;
	}

	public static function get_threads( array $args = array() ) {
		if ( ! function_exists( 'zeroBSCRM_get_email_history' ) ) {
			return array();
		}
		$offset = isset( $args['page'] ) ? (int) $args['page'] : 0;
		$limit  = isset( $args['per_page'] ) ? (int) $args['per_page'] : 20;
		$rows   = zeroBSCRM_get_email_history( $offset, $limit, true );

		if ( ! is_array( $rows ) ) {
			return array();
		}

		// Keep the latest message per thread.
		$threads = array();

		foreach ( $rows as $row ) {
			$row = (array) $row;
			$tid = isset( $row['zbsmail_sender_thread'] ) ? (int) $row['zbsmail_sender_thread'] : 0;
			$id  = isset( $row['ID'] ) ? (int) $row['ID'] : 0;

			if ( ! isset( $threads[ $tid ] ) || $id > (int) $threads[ $tid ]['ID'] ) {
				$threads[ $tid ] = $row;
			}
		}

		return array_values( $threads );
	}

	public static function get_thread_emails( $thread_id ) {
		global $wpdb;
		$table = self::hist_table();
		if ( ! $table ) { return array(); }
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE zbsmail_sender_thread = %d ORDER BY zbsmail_created ASC",
				(int) $thread_id
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	public static function star_thread( $thread_id ) {
		global $wpdb;
		$table = self::hist_table();
		if ( ! $table ) { return false; }
		$result = $wpdb->update(
			$table,
			array( 'zbsmail_starred' => 1 ),
			array( 'zbsmail_sender_thread' => (int) $thread_id ),
			array( '%d' ),
			array( '%d' )
		);
		return false !== $result;
	}

	public static function unstar_thread( $thread_id ) {
		global $wpdb;
		$table = self::hist_table();
		if ( ! $table ) { return false; }
		$result = $wpdb->update(
			$table,
			array( 'zbsmail_starred' => 0 ),
			array( 'zbsmail_sender_thread' => (int) $thread_id ),
			array( '%d' ),
			array( '%d' )
		);
		return false !== $result;
	}

	public static function delete_thread( $thread_id ) {
		global $wpdb;
		$table = self::hist_table();
		if ( ! $table ) { return false; }
		$result = $wpdb->delete( $table, array( 'zbsmail_sender_thread' => (int) $thread_id ), array( '%d' ) );
		return false !== $result;
	}

	public static function delete_email( $id ) {
		global $wpdb;
		$table = self::hist_table();
		if ( ! $table ) { return false; }
		$result = $wpdb->delete( $table, array( 'ID' => (int) $id ), array( '%d' ) );
		return false !== $result;
	}

	public static function mark_thread_read( $thread_id ) {
		if ( function_exists( 'zeroBSCRM_mark_as_read' ) ) {
			return zeroBSCRM_mark_as_read( (int) $thread_id );
		}
		return false;
	}

	public static function get_email_templates() {
		if ( function_exists( 'zeroBSCRM_mailTemplate_getAll' ) ) {
			$templates = zeroBSCRM_mailTemplate_getAll();
			return is_array( $templates ) ? $templates : array();
		}
		return array();
	}

	public static function get_email_template( $id ) {
		if ( function_exists( 'zeroBSCRM_mailTemplate_get' ) ) {
			return zeroBSCRM_mailTemplate_get( (int) $id );
		}
		return null;
	}

	/**
	 * Create or update an email template.
	 *
	 * @param array $data Template fields (active, delivery_method, from_name,
	 *                    from_address, reply_to, cc_to, bcc_to, subject, body, id).
	 * @return array|WP_Error Formatted template or error.
	 */
	public static function save_email_template( array $data ) {
		global $wpdb;

		$table = self::template_table();

		if ( ! $table ) {
			return new WP_Error( 'jpcrm_rest_unknown_error', __( 'Хранилище шаблонов недоступно.', 'jetpack-crm-rest-api-improved' ), array( 'status' => 500 ) );
		}

		$columns = array(
			'active'          => array( 'zbsmail_active', '%d', 'bool' ),
			'delivery_method' => array( 'zbsmail_deliverymethod', '%s', 'text' ),
			'from_name'       => array( 'zbsmail_fromname', '%s', 'text' ),
			'from_address'    => array( 'zbsmail_fromaddress', '%s', 'text' ),
			'reply_to'        => array( 'zbsmail_replyto', '%s', 'text' ),
			'cc_to'           => array( 'zbsmail_ccto', '%s', 'text' ),
			'bcc_to'          => array( 'zbsmail_bccto', '%s', 'text' ),
			'subject'         => array( 'zbsmail_subject', '%s', 'text' ),
			'body'            => array( 'zbsmail_body', '%s', 'html' ),
		);

		$id = isset( $data['id'] ) ? (int) $data['id'] : -1;

		$row    = array();
		$format = array();

		foreach ( $columns as $key => $meta ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}

			list( $column, $fmt, $type ) = $meta;

			if ( 'bool' === $type ) {
				$row[ $column ] = (int) (bool) $data[ $key ];
			} elseif ( 'html' === $type ) {
				$row[ $column ] = (string) $data[ $key ];
			} else {
				$row[ $column ] = sanitize_text_field( (string) $data[ $key ] );
			}

			$format[] = $fmt;
		}

		if ( $id > 0 ) {
			if ( empty( $row ) ) {
				return new WP_Error( 'jpcrm_rest_validation_error', __( 'Нет полей для обновления.', 'jetpack-crm-rest-api-improved' ), array( 'status' => 422 ) );
			}

			$row['zbsmail_lastupdated'] = time();
			$format[]                   = '%d';

			$updated = $wpdb->update( $table, $row, array( 'zbsmail_id' => $id ), $format, array( '%d' ) );

			if ( false === $updated ) {
				return new WP_Error( 'jpcrm_rest_unknown_error', __( 'Не удалось обновить шаблон.', 'jetpack-crm-rest-api-improved' ), array( 'status' => 500 ) );
			}
		} else {
			if ( $id <= 0 ) {
				$id = (int) $wpdb->get_var( "SELECT COALESCE(MAX(zbsmail_id), 0) + 1 FROM `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}

			$row = array_merge(
				array(
					'zbs_site'          => -1,
					'zbs_team'          => -1,
					'zbs_owner'         => -1,
					'zbsmail_id'        => $id,
					'zbsmail_created'   => time(),
					'zbsmail_lastupdated' => time(),
				),
				$row
			);

			$format = array_merge(
				array( '%d', '%d', '%d', '%d', '%d', '%d' ),
				$format
			);

			$inserted = $wpdb->insert( $table, $row, $format );

			if ( ! $inserted ) {
				return new WP_Error( 'jpcrm_rest_unknown_error', __( 'Не удалось создать шаблон.', 'jetpack-crm-rest-api-improved' ), array( 'status' => 500 ) );
			}
		}

		$template = self::get_email_template( $id );

		return null !== $template ? $template : array( 'id' => $id );
	}

	/**
	 * Delete an email template.
	 *
	 * @param int $id Template id.
	 * @return true|WP_Error
	 */
	public static function delete_email_template( $id ) {
		global $wpdb;

		$table = self::template_table();

		if ( ! $table ) {
			return new WP_Error( 'jpcrm_rest_unknown_error', __( 'Хранилище шаблонов недоступно.', 'jetpack-crm-rest-api-improved' ), array( 'status' => 500 ) );
		}

		$deleted = $wpdb->delete( $table, array( 'zbsmail_id' => (int) $id ), array( '%d' ) );

		if ( ! $deleted ) {
			return new WP_Error( 'jpcrm_rest_not_found', __( 'Шаблон не найден.', 'jetpack-crm-rest-api-improved' ), array( 'status' => 404 ) );
		}

		return true;
	}

	public static function count_emails( array $args = array() ) {
		global $wpdb;
		$table = self::hist_table();
		if ( ! $table ) { return 0; }
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
		return (int) $count;
	}

	private static function hist_table() {
		global $ZBSCRM_t;
		if ( isset( $ZBSCRM_t['system_mail_hist'] ) ) {
			return $ZBSCRM_t['system_mail_hist'];
		}
		return null;
	}

	private static function template_table() {
		global $ZBSCRM_t;
		if ( isset( $ZBSCRM_t['system_mail_templates'] ) ) {
			return $ZBSCRM_t['system_mail_templates'];
		}
		return null;
	}

	public static function format_email( $email ) {
		$email = (array) $email;

		$status    = isset( $email['zbsmail_status'] ) ? (string) $email['zbsmail_status'] : '';
		$sent_flag = isset( $email['zbsmail_sent'] ) ? (int) $email['zbsmail_sent'] : 0;
		$created   = isset( $email['zbsmail_created'] ) ? (int) $email['zbsmail_created'] : 0;

		// The CRM stores `zbsmail_sent` as a flag (-1 = logged, 1 = sent), not a
		// timestamp, so it must never leak into the API as a date. Expose it as a
		// boolean `sent` and use the history creation time for `date_sent`.
		$is_sent = ( $sent_flag > 0 || 'sent' === $status );

		return array(
			'id'                 => isset( $email['ID'] ) ? (int) $email['ID'] : 0,
			'thread_id'          => isset( $email['zbsmail_sender_thread'] ) ? (int) $email['zbsmail_sender_thread'] : 0,
			'contact_id'         => isset( $email['zbsmail_target_objid'] ) ? (int) $email['zbsmail_target_objid'] : 0,
			'status'             => $status,
			'sender_email'       => isset( $email['zbsmail_sender_email'] ) ? $email['zbsmail_sender_email'] : '',
			'receiver_email'     => isset( $email['zbsmail_receiver_email'] ) ? $email['zbsmail_receiver_email'] : '',
			'subject'            => isset( $email['zbsmail_subject'] ) ? $email['zbsmail_subject'] : '',
			'starred'            => isset( $email['zbsmail_starred'] ) ? (bool) $email['zbsmail_starred'] : false,
			'opened'             => isset( $email['zbsmail_opened'] ) ? (bool) $email['zbsmail_opened'] : false,
			'sent'               => $is_sent,
			'date_sent'          => $is_sent ? Fields::uts_to_iso8601( $created ) : null,
			'date_created_gmt'   => Fields::uts_to_iso8601( $created ),
		);
	}
}
