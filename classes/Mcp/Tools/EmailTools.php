<?php
/**
 * Email MCP tools.
 *
 * @package Jetpack_CRM_REST_API_Improved
 */

namespace Jetpack_CRM_REST_API_Improved\Mcp\Tools;

use Jetpack_CRM_REST_API_Improved\Mcp\Dispatcher;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handlers for email sending and thread actions.
 */
class EmailTools {

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
	 * Send a new email to a contact.
	 *
	 * @param array $args Tool arguments.
	 * @return array|WP_Error
	 */
	public function send( $args ) {
		$contact_id      = isset( $args['contact_id'] ) ? (int) $args['contact_id'] : 0;
		$email           = isset( $args['email'] ) ? sanitize_email( (string) $args['email'] ) : '';
		$subject         = isset( $args['subject'] ) ? (string) $args['subject'] : '';
		$content         = isset( $args['content'] ) ? (string) $args['content'] : '';
		$delivery_method = isset( $args['delivery_method'] ) && '' !== $args['delivery_method'] ? (string) $args['delivery_method'] : null;

		if ( '' === trim( $content ) ) {
			return $this->param_error( __( 'Аргумент content обязателен.', 'jetpack-crm-rest-api-improved' ) );
		}

		if ( $contact_id <= 0 && '' === $email ) {
			return $this->param_error( __( 'Укажите contact_id или email.', 'jetpack-crm-rest-api-improved' ) );
		}

		$body = array(
			'subject' => $subject,
			'content' => $content,
		);

		if ( $contact_id > 0 ) {
			$body['contact_id'] = $contact_id;
		} else {
			$body['email'] = $email;
		}

		if ( null !== $delivery_method ) {
			$body['delivery_method'] = $delivery_method;
		}

		return Dispatcher::request( 'POST', '/emails', array(), $body );
	}

	/**
	 * Reply within a thread.
	 *
	 * @param array $args Tool arguments.
	 * @return array|WP_Error
	 */
	public function reply( $args ) {
		$id = $this->id( $args );

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$content = isset( $args['content'] ) ? (string) $args['content'] : '';

		if ( '' === trim( $content ) ) {
			return $this->param_error( __( 'Аргумент content обязателен.', 'jetpack-crm-rest-api-improved' ) );
		}

		$body = array( 'content' => $content );

		if ( isset( $args['subject'] ) && '' !== $args['subject'] ) {
			$body['subject'] = (string) $args['subject'];
		}

		if ( isset( $args['delivery_method'] ) && '' !== $args['delivery_method'] ) {
			$body['delivery_method'] = (string) $args['delivery_method'];
		}

		return Dispatcher::request( 'POST', '/email-threads/' . $id . '/reply', array(), $body );
	}

	/**
	 * Star or unstar a thread.
	 *
	 * @param array $args Tool arguments.
	 * @return array|WP_Error
	 */
	public function star( $args ) {
		$id = $this->id( $args );

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$starred = ! isset( $args['starred'] ) || (bool) $args['starred'];
		$method  = $starred ? 'POST' : 'DELETE';

		return Dispatcher::request( $method, '/email-threads/' . $id . '/star' );
	}

	/**
	 * Mark a thread as read.
	 *
	 * @param array $args Tool arguments.
	 * @return array|WP_Error
	 */
	public function read( $args ) {
		$id = $this->id( $args );

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		return Dispatcher::request( 'POST', '/email-threads/' . $id . '/read' );
	}

	/**
	 * Validate and return a thread id.
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
