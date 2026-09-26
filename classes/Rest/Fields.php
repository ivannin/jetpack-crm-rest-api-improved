<?php
/**
 * Field mapping between the DAL and the REST representation.
 *
 * @package Jetpack_CRM_REST_API_Improved
 */

namespace Jetpack_CRM_REST_API_Improved\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts DAL objects to REST resources and back.
 */
class Fields {

	/**
	 * DAL contact output keys that are not custom fields.
	 *
	 * @var string[]
	 */
	const CONTACT_KNOWN_KEYS = array(
		'id',
		'owner',
		'status',
		'email',
		'prefix',
		'fname',
		'lname',
		'addr1',
		'addr2',
		'city',
		'county',
		'country',
		'postcode',
		'secaddr_addr1',
		'secaddr_addr2',
		'secaddr_city',
		'secaddr_county',
		'secaddr_country',
		'secaddr_postcode',
		'hometel',
		'worktel',
		'mobtel',
		'wpid',
		'avatar',
		'tw',
		'li',
		'fb',
		'fullname',
		'name',
		'aliases',
		'tags',
		'dnd',
		'meta',
		'created',
		'createduts',
		'created_date',
		'lastcontacted',
		'lastcontacteduts',
		'lastcontacted_date',
		'lastupdated',
		'lastupdated_date',
		'lastlog',
		'lastcontactlog',
		'quotes_total',
		'invoices_total',
		'invoices_total_inc_deleted',
		'invoices_count',
		'invoices_count_inc_deleted',
		'transactions_total',
		'transactions_paid_total',
		'total_value',
	);

	/**
	 * DAL contact input keys accepted as-is.
	 *
	 * @var string[]
	 */
	const CONTACT_INPUT_KEYS = array(
		'email',
		'status',
		'prefix',
		'fname',
		'lname',
		'addr1',
		'addr2',
		'city',
		'county',
		'country',
		'postcode',
		'secaddr1',
		'secaddr2',
		'seccity',
		'seccounty',
		'seccountry',
		'secpostcode',
		'hometel',
		'worktel',
		'mobtel',
		'wpid',
		'avatar',
		'tw',
		'fb',
		'li',
		'lastcontacted',
		'created',
	);

	/**
	 * Convert a DAL contact into its REST representation.
	 *
	 * @param array $contact DAL contact array.
	 * @return array
	 */
	public static function contact_from_dal( array $contact ) {
		$first = isset( $contact['fname'] ) ? $contact['fname'] : '';
		$last  = isset( $contact['lname'] ) ? $contact['lname'] : '';

		$full_name = isset( $contact['fullname'] ) ? $contact['fullname'] : trim( $first . ' ' . $last );

		$out = array(
			'id'                      => isset( $contact['id'] ) ? (int) $contact['id'] : 0,
			'owner'                   => isset( $contact['owner'] ) ? (int) $contact['owner'] : 0,
			'status'                  => isset( $contact['status'] ) ? $contact['status'] : '',
			'email'                   => isset( $contact['email'] ) ? $contact['email'] : '',
			'prefix'                  => isset( $contact['prefix'] ) ? $contact['prefix'] : '',
			'first_name'              => $first,
			'last_name'               => $last,
			'full_name'               => $full_name,
			'address'                 => array(
				'line1'    => isset( $contact['addr1'] ) ? $contact['addr1'] : '',
				'line2'    => isset( $contact['addr2'] ) ? $contact['addr2'] : '',
				'city'     => isset( $contact['city'] ) ? $contact['city'] : '',
				'county'   => isset( $contact['county'] ) ? $contact['county'] : '',
				'postcode' => isset( $contact['postcode'] ) ? $contact['postcode'] : '',
				'country'  => isset( $contact['country'] ) ? $contact['country'] : '',
			),
			'secondary_address'       => array(
				'line1'    => isset( $contact['secaddr_addr1'] ) ? $contact['secaddr_addr1'] : '',
				'line2'    => isset( $contact['secaddr_addr2'] ) ? $contact['secaddr_addr2'] : '',
				'city'     => isset( $contact['secaddr_city'] ) ? $contact['secaddr_city'] : '',
				'county'   => isset( $contact['secaddr_county'] ) ? $contact['secaddr_county'] : '',
				'postcode' => isset( $contact['secaddr_postcode'] ) ? $contact['secaddr_postcode'] : '',
				'country'  => isset( $contact['secaddr_country'] ) ? $contact['secaddr_country'] : '',
			),
			'telephones'              => array(
				'home'   => isset( $contact['hometel'] ) ? $contact['hometel'] : '',
				'work'   => isset( $contact['worktel'] ) ? $contact['worktel'] : '',
				'mobile' => isset( $contact['mobtel'] ) ? $contact['mobtel'] : '',
			),
			'social'                  => array(
				'twitter'  => isset( $contact['tw'] ) ? $contact['tw'] : '',
				'facebook' => isset( $contact['fb'] ) ? $contact['fb'] : '',
				'linkedin' => isset( $contact['li'] ) ? $contact['li'] : '',
			),
			'wp_user_id'              => isset( $contact['wpid'] ) ? (int) $contact['wpid'] : 0,
			'avatar'                  => isset( $contact['avatar'] ) ? $contact['avatar'] : '',
			'aliases'                 => ( isset( $contact['aliases'] ) && is_array( $contact['aliases'] ) ) ? $contact['aliases'] : array(),
			'tags'                    => ( isset( $contact['tags'] ) && is_array( $contact['tags'] ) ) ? $contact['tags'] : array(),
			'custom_fields'           => self::extract_custom_fields( $contact, self::CONTACT_KNOWN_KEYS ),
			'date_created_gmt'        => self::uts_to_iso8601( isset( $contact['createduts'] ) ? $contact['createduts'] : 0 ),
			'date_modified_gmt'       => self::uts_to_iso8601( isset( $contact['lastupdated'] ) ? $contact['lastupdated'] : 0 ),
			'date_last_contacted_gmt' => self::uts_to_iso8601( isset( $contact['lastcontacteduts'] ) ? $contact['lastcontacteduts'] : 0 ),
		);

		$totals = array( 'quotes_total', 'invoices_total', 'invoices_count', 'transactions_total', 'total_value' );

		foreach ( $totals as $key ) {
			if ( isset( $contact[ $key ] ) ) {
				$out[ $key ] = $contact[ $key ];
			}
		}

		return $out;
	}

	/**
	 * Convert a REST payload into a DAL `data` array for contacts.
	 *
	 * @param array $payload REST payload.
	 * @return array
	 */
	public static function contact_to_dal( array $payload ) {
		$data = array();

		foreach ( self::CONTACT_INPUT_KEYS as $key ) {
			if ( array_key_exists( $key, $payload ) ) {
				$data[ $key ] = $payload[ $key ];
			}
		}

		$aliases = array(
			'first_name' => 'fname',
			'last_name'  => 'lname',
			'wp_user_id' => 'wpid',
			'twitter'    => 'tw',
			'facebook'   => 'fb',
			'linkedin'   => 'li',
		);

		foreach ( $aliases as $rest_key => $dal_key ) {
			if ( array_key_exists( $rest_key, $payload ) ) {
				$data[ $dal_key ] = $payload[ $rest_key ];
			}
		}

		$nested = array(
			'address'           => array(
				'line1'    => 'addr1',
				'line2'    => 'addr2',
				'city'     => 'city',
				'county'   => 'county',
				'postcode' => 'postcode',
				'country'  => 'country',
			),
			'secondary_address' => array(
				'line1'    => 'secaddr1',
				'line2'    => 'secaddr2',
				'city'     => 'seccity',
				'county'   => 'seccounty',
				'postcode' => 'secpostcode',
				'country'  => 'seccountry',
			),
			'telephones'        => array(
				'home'   => 'hometel',
				'work'   => 'worktel',
				'mobile' => 'mobtel',
			),
			'social'            => array(
				'twitter'  => 'tw',
				'facebook' => 'fb',
				'linkedin' => 'li',
			),
		);

		foreach ( $nested as $rest_group => $map ) {
			if ( ! isset( $payload[ $rest_group ] ) || ! is_array( $payload[ $rest_group ] ) ) {
				continue;
			}

			foreach ( $map as $rest_key => $dal_key ) {
				if ( array_key_exists( $rest_key, $payload[ $rest_group ] ) ) {
					$data[ $dal_key ] = $payload[ $rest_group ][ $rest_key ];
				}
			}
		}

		if ( array_key_exists( 'tags', $payload ) ) {
			$data['tags'] = $payload['tags'];
		}
		if ( array_key_exists( 'tag_mode', $payload ) ) {
			$data['tag_mode'] = $payload['tag_mode'];
		}
		if ( array_key_exists( 'companies', $payload ) ) {
			$data['companies'] = $payload['companies'];
		}
		if ( array_key_exists( 'external_sources', $payload ) ) {
			$data['externalSources'] = $payload['external_sources'];
		}
		if ( array_key_exists( 'aliases', $payload ) ) {
			$data['aliases'] = $payload['aliases'];
		}

		if ( isset( $payload['custom_fields'] ) && is_array( $payload['custom_fields'] ) ) {
			foreach ( $payload['custom_fields'] as $key => $value ) {
				$data[ $key ] = $value;
			}
		}

		return $data;
	}

	/**
	 * Extract custom fields from a DAL object.
	 *
	 * @param array $object     DAL object.
	 * @param array $known_keys Known (non-custom) keys.
	 * @return array
	 */
	public static function extract_custom_fields( array $object, array $known_keys ) {
		$known = array_flip( $known_keys );

		return array_diff_key( $object, $known );
	}

	const COMPANY_KNOWN_KEYS = array(
		'id', 'owner', 'status', 'name', 'email',
		'addr1', 'addr2', 'city', 'county', 'country', 'postcode',
		'secaddr_addr1', 'secaddr_addr2', 'secaddr_city', 'secaddr_county', 'secaddr_country', 'secaddr_postcode',
		'maintel', 'sectel', 'wpid', 'avatar', 'tw', 'li', 'fb',
		'fullname', 'created', 'createduts', 'created_date',
		'lastcontacted', 'lastcontacteduts', 'lastcontacted_date',
		'lastupdated', 'lastupdated_date',
		'tags', 'contacts', 'meta', 'dnd',
		'quotes_total', 'invoices_total', 'invoices_count',
		'transactions_total', 'transactions_paid_total', 'total_value',
	);

	const COMPANY_INPUT_KEYS = array(
		'status', 'name', 'email',
		'addr1', 'addr2', 'city', 'county', 'country', 'postcode',
		'secaddr1', 'secaddr2', 'seccity', 'seccounty', 'seccountry', 'secpostcode',
		'maintel', 'sectel', 'wpid', 'avatar', 'tw', 'fb', 'li',
		'lastcontacted', 'created', 'lastupdated',
	);

	public static function company_from_dal( array $company ) {
		$out = array(
			'id'                      => isset( $company['id'] ) ? (int) $company['id'] : 0,
			'owner'                   => isset( $company['owner'] ) ? (int) $company['owner'] : 0,
			'status'                  => isset( $company['status'] ) ? $company['status'] : '',
			'name'                    => isset( $company['name'] ) ? $company['name'] : '',
			'email'                   => isset( $company['email'] ) ? $company['email'] : '',
			'address'                 => array(
				'line1'    => isset( $company['addr1'] ) ? $company['addr1'] : '',
				'line2'    => isset( $company['addr2'] ) ? $company['addr2'] : '',
				'city'     => isset( $company['city'] ) ? $company['city'] : '',
				'county'   => isset( $company['county'] ) ? $company['county'] : '',
				'postcode' => isset( $company['postcode'] ) ? $company['postcode'] : '',
				'country'  => isset( $company['country'] ) ? $company['country'] : '',
			),
			'secondary_address'       => array(
				'line1'    => isset( $company['secaddr_addr1'] ) ? $company['secaddr_addr1'] : '',
				'line2'    => isset( $company['secaddr_addr2'] ) ? $company['secaddr_addr2'] : '',
				'city'     => isset( $company['secaddr_city'] ) ? $company['secaddr_city'] : '',
				'county'   => isset( $company['secaddr_county'] ) ? $company['secaddr_county'] : '',
				'postcode' => isset( $company['secaddr_postcode'] ) ? $company['secaddr_postcode'] : '',
				'country'  => isset( $company['secaddr_country'] ) ? $company['secaddr_country'] : '',
			),
			'telephones'              => array(
				'main'      => isset( $company['maintel'] ) ? $company['maintel'] : '',
				'secondary' => isset( $company['sectel'] ) ? $company['sectel'] : '',
			),
			'social'                  => array(
				'twitter'  => isset( $company['tw'] ) ? $company['tw'] : '',
				'facebook' => isset( $company['fb'] ) ? $company['fb'] : '',
				'linkedin' => isset( $company['li'] ) ? $company['li'] : '',
			),
			'wp_user_id'              => isset( $company['wpid'] ) ? (int) $company['wpid'] : 0,
			'avatar'                  => isset( $company['avatar'] ) ? $company['avatar'] : '',
			'tags'                    => ( isset( $company['tags'] ) && is_array( $company['tags'] ) ) ? $company['tags'] : array(),
			'contacts'                => ( isset( $company['contacts'] ) && is_array( $company['contacts'] ) ) ? $company['contacts'] : array(),
			'custom_fields'           => self::extract_custom_fields( $company, self::COMPANY_KNOWN_KEYS ),
			'date_created_gmt'        => self::uts_to_iso8601( isset( $company['createduts'] ) ? $company['createduts'] : 0 ),
			'date_modified_gmt'       => self::uts_to_iso8601( isset( $company['lastupdated'] ) ? $company['lastupdated'] : 0 ),
			'date_last_contacted_gmt' => self::uts_to_iso8601( isset( $company['lastcontacteduts'] ) ? $company['lastcontacteduts'] : 0 ),
		);

		return $out;
	}

	public static function company_to_dal( array $payload ) {
		$data = array();

		foreach ( self::COMPANY_INPUT_KEYS as $key ) {
			if ( array_key_exists( $key, $payload ) ) {
				$data[ $key ] = $payload[ $key ];
			}
		}

		$nested = array(
			'address'           => array(
				'line1' => 'addr1', 'line2' => 'addr2', 'city' => 'city',
				'county' => 'county', 'postcode' => 'postcode', 'country' => 'country',
			),
			'secondary_address' => array(
				'line1' => 'secaddr1', 'line2' => 'secaddr2', 'city' => 'seccity',
				'county' => 'seccounty', 'postcode' => 'secpostcode', 'country' => 'seccountry',
			),
			'telephones'        => array(
				'main' => 'maintel', 'secondary' => 'sectel',
			),
			'social'            => array(
				'twitter' => 'tw', 'facebook' => 'fb', 'linkedin' => 'li',
			),
		);

		foreach ( $nested as $group => $map ) {
			if ( ! isset( $payload[ $group ] ) || ! is_array( $payload[ $group ] ) ) {
				continue;
			}
			foreach ( $map as $rest_key => $dal_key ) {
				if ( array_key_exists( $rest_key, $payload[ $group ] ) ) {
					$data[ $dal_key ] = $payload[ $group ][ $rest_key ];
				}
			}
		}

		if ( array_key_exists( 'tags', $payload ) ) {
			$data['tags'] = $payload['tags'];
		}
		if ( array_key_exists( 'tag_mode', $payload ) ) {
			$data['tag_mode'] = $payload['tag_mode'];
		}
		if ( array_key_exists( 'contacts', $payload ) ) {
			$data['contacts'] = $payload['contacts'];
		}
		if ( array_key_exists( 'external_sources', $payload ) ) {
			$data['externalSources'] = $payload['external_sources'];
		}

		if ( isset( $payload['custom_fields'] ) && is_array( $payload['custom_fields'] ) ) {
			foreach ( $payload['custom_fields'] as $key => $value ) {
				$data[ $key ] = $value;
			}
		}

		return $data;
	}

	const INVOICE_KNOWN_KEYS = array(
		'id', 'owner', 'status', 'hash', 'id_override', 'parent',
		'no', 'ref', 'date', 'due_date', 'paid_date',
		'currency', 'addressed_from', 'addressed_to', 'address_to_objtype',
		'net', 'discount', 'discount_type', 'shipping', 'shipping_taxes', 'shipping_tax',
		'taxes', 'tax', 'total',
		'hours_or_quantity', 'pdf_template', 'portal_template', 'email_template',
		'allow_partial', 'allow_tip', 'send_attachments', 'logo_url',
		'pay_via', 'invoice_frequency',
		'hash_viewed', 'hash_viewed_count', 'portal_viewed', 'portal_viewed_count',
		'lineitems', 'contacts', 'companies', 'tags', 'meta',
		'created', 'createduts', 'created_date', 'lastupdated', 'lastupdated_date',
		'externalSources',
	);

	const INVOICE_INPUT_KEYS = array(
		'status', 'id_override', 'hash', 'parent',
		'no', 'ref', 'date', 'due_date', 'paid_date',
		'currency', 'addressed_from', 'addressed_to', 'address_to_objtype',
		'net', 'discount', 'discount_type', 'shipping', 'shipping_taxes', 'shipping_tax',
		'taxes', 'tax', 'total',
		'hours_or_quantity', 'pdf_template', 'portal_template', 'email_template',
		'allow_partial', 'allow_tip', 'send_attachments', 'logo_url',
		'pay_via', 'invoice_frequency',
		'hash_viewed', 'hash_viewed_count', 'portal_viewed', 'portal_viewed_count',
		'created', 'lastupdated',
	);

	public static function invoice_from_dal( array $inv ) {
		$out = array(
			'id'                => isset( $inv['id'] ) ? (int) $inv['id'] : 0,
			'owner'             => isset( $inv['owner'] ) ? (int) $inv['owner'] : 0,
			'status'            => isset( $inv['status'] ) ? $inv['status'] : '',
			'number'            => isset( $inv['no'] ) ? $inv['no'] : '',
			'reference'         => isset( $inv['ref'] ) ? $inv['ref'] : '',
			'date'              => isset( $inv['date'] ) ? $inv['date'] : '',
			'due_date'          => isset( $inv['due_date'] ) ? $inv['due_date'] : '',
			'paid_date'         => isset( $inv['paid_date'] ) ? $inv['paid_date'] : '',
			'currency'          => isset( $inv['currency'] ) ? $inv['currency'] : '',
			'net'               => isset( $inv['net'] ) ? (float) $inv['net'] : 0,
			'discount'          => isset( $inv['discount'] ) ? (float) $inv['discount'] : 0,
			'shipping'          => isset( $inv['shipping'] ) ? (float) $inv['shipping'] : 0,
			'taxes'             => isset( $inv['taxes'] ) ? (float) $inv['taxes'] : 0,
			'total'             => isset( $inv['total'] ) ? (float) $inv['total'] : 0,
			'line_items'        => ( isset( $inv['lineitems'] ) && is_array( $inv['lineitems'] ) ) ? $inv['lineitems'] : array(),
			'contacts'          => ( isset( $inv['contacts'] ) && is_array( $inv['contacts'] ) ) ? $inv['contacts'] : array(),
			'companies'         => ( isset( $inv['companies'] ) && is_array( $inv['companies'] ) ) ? $inv['companies'] : array(),
			'tags'              => ( isset( $inv['tags'] ) && is_array( $inv['tags'] ) ) ? $inv['tags'] : array(),
			'custom_fields'     => self::extract_custom_fields( $inv, self::INVOICE_KNOWN_KEYS ),
			'date_created_gmt'  => self::uts_to_iso8601( isset( $inv['createduts'] ) ? $inv['createduts'] : 0 ),
			'date_modified_gmt' => self::uts_to_iso8601( isset( $inv['lastupdated'] ) ? $inv['lastupdated'] : 0 ),
		);

		return $out;
	}

	public static function invoice_to_dal( array $payload ) {
		$data = array();
		foreach ( self::INVOICE_INPUT_KEYS as $key ) {
			if ( array_key_exists( $key, $payload ) ) {
				$data[ $key ] = $payload[ $key ];
			}
		}
		$aliases = array( 'number' => 'no', 'reference' => 'ref', 'due_date' => 'due_date' );
		foreach ( $aliases as $rest => $dal ) {
			if ( array_key_exists( $rest, $payload ) ) {
				$data[ $dal ] = $payload[ $rest ];
			}
		}
		if ( array_key_exists( 'line_items', $payload ) ) {
			$data['lineitems'] = $payload['line_items'];
		}
		if ( array_key_exists( 'contacts', $payload ) ) {
			$data['contacts'] = $payload['contacts'];
		}
		if ( array_key_exists( 'companies', $payload ) ) {
			$data['companies'] = $payload['companies'];
		}
		if ( array_key_exists( 'tags', $payload ) ) {
			$data['tags'] = $payload['tags'];
		}
		if ( array_key_exists( 'tag_mode', $payload ) ) {
			$data['tag_mode'] = $payload['tag_mode'];
		}
		if ( array_key_exists( 'external_sources', $payload ) ) {
			$data['externalSources'] = $payload['external_sources'];
		}
		if ( isset( $payload['custom_fields'] ) && is_array( $payload['custom_fields'] ) ) {
			foreach ( $payload['custom_fields'] as $key => $value ) {
				$data[ $key ] = $value;
			}
		}
		return $data;
	}

	const QUOTE_KNOWN_KEYS = array(
		'id', 'owner', 'status', 'id_override', 'hash',
		'title', 'currency', 'value', 'date', 'template', 'content', 'notes',
		'send_attachments', 'lastviewed', 'viewed_count',
		'accepted', 'acceptedsigned', 'acceptedip',
		'lineitems', 'contacts', 'companies', 'tags', 'meta',
		'created', 'createduts', 'created_date', 'lastupdated', 'lastupdated_date',
		'externalSources',
	);

	const QUOTE_INPUT_KEYS = array(
		'id_override', 'title', 'currency', 'value', 'date', 'template',
		'content', 'notes', 'send_attachments', 'hash',
		'lastviewed', 'viewed_count', 'accepted', 'acceptedsigned', 'acceptedip',
		'created', 'lastupdated',
	);

	public static function quote_from_dal( array $q ) {
		$out = array(
			'id'                => isset( $q['id'] ) ? (int) $q['id'] : 0,
			'owner'             => isset( $q['owner'] ) ? (int) $q['owner'] : 0,
			'title'             => isset( $q['title'] ) ? $q['title'] : '',
			'status'            => isset( $q['status'] ) ? $q['status'] : '',
			'currency'          => isset( $q['currency'] ) ? $q['currency'] : '',
			'value'             => isset( $q['value'] ) ? (float) $q['value'] : 0,
			'date'              => isset( $q['date'] ) ? $q['date'] : '',
			'template'          => isset( $q['template'] ) ? $q['template'] : '',
			'content'           => isset( $q['content'] ) ? $q['content'] : '',
			'notes'             => isset( $q['notes'] ) ? $q['notes'] : '',
			'send_attachments'  => isset( $q['send_attachments'] ) ? (bool) $q['send_attachments'] : false,
			'hash'              => isset( $q['hash'] ) ? $q['hash'] : '',
			'viewed_count'      => isset( $q['viewed_count'] ) ? (int) $q['viewed_count'] : 0,
			'accepted'          => isset( $q['accepted'] ) ? (bool) $q['accepted'] : false,
			'date_accepted_gmt' => self::uts_to_iso8601( isset( $q['accepted'] ) && $q['accepted'] ? strtotime( $q['accepted'] ) : 0 ),
			'line_items'        => ( isset( $q['lineitems'] ) && is_array( $q['lineitems'] ) ) ? $q['lineitems'] : array(),
			'contacts'          => ( isset( $q['contacts'] ) && is_array( $q['contacts'] ) ) ? $q['contacts'] : array(),
			'companies'         => ( isset( $q['companies'] ) && is_array( $q['companies'] ) ) ? $q['companies'] : array(),
			'tags'              => ( isset( $q['tags'] ) && is_array( $q['tags'] ) ) ? $q['tags'] : array(),
			'custom_fields'     => self::extract_custom_fields( $q, self::QUOTE_KNOWN_KEYS ),
			'date_created_gmt'  => self::uts_to_iso8601( isset( $q['createduts'] ) ? $q['createduts'] : 0 ),
			'date_modified_gmt' => self::uts_to_iso8601( isset( $q['lastupdated'] ) ? $q['lastupdated'] : 0 ),
		);
		return $out;
	}

	public static function quote_to_dal( array $payload ) {
		$data = array();
		foreach ( self::QUOTE_INPUT_KEYS as $key ) {
			if ( array_key_exists( $key, $payload ) ) {
				$data[ $key ] = $payload[ $key ];
			}
		}
		if ( array_key_exists( 'line_items', $payload ) ) {
			$data['lineitems'] = $payload['line_items'];
		}
		if ( array_key_exists( 'contacts', $payload ) ) {
			$data['contacts'] = $payload['contacts'];
		}
		if ( array_key_exists( 'companies', $payload ) ) {
			$data['companies'] = $payload['companies'];
		}
		if ( array_key_exists( 'tags', $payload ) ) {
			$data['tags'] = $payload['tags'];
		}
		if ( array_key_exists( 'tag_mode', $payload ) ) {
			$data['tag_mode'] = $payload['tag_mode'];
		}
		if ( array_key_exists( 'external_sources', $payload ) ) {
			$data['externalSources'] = $payload['external_sources'];
		}
		if ( isset( $payload['custom_fields'] ) && is_array( $payload['custom_fields'] ) ) {
			foreach ( $payload['custom_fields'] as $key => $value ) {
				$data[ $key ] = $value;
			}
		}
		return $data;
	}

	const TRANSACTION_KNOWN_KEYS = array(
		'id', 'owner', 'status', 'type', 'ref', 'origin', 'parent', 'hash',
		'title', 'desc', 'date', 'customer_ip', 'currency',
		'net', 'fee', 'discount', 'shipping', 'shipping_taxes', 'shipping_tax',
		'taxes', 'tax', 'total',
		'date_paid', 'date_completed',
		'lineitems', 'contacts', 'companies', 'invoice_id', 'tags', 'meta',
		'created', 'createduts', 'created_date', 'lastupdated', 'lastupdated_date',
		'externalSources',
	);

	const TRANSACTION_INPUT_KEYS = array(
		'status', 'type', 'ref', 'origin', 'parent', 'hash',
		'title', 'desc', 'date', 'customer_ip', 'currency',
		'net', 'fee', 'discount', 'shipping', 'shipping_taxes', 'shipping_tax',
		'taxes', 'tax', 'total',
		'date_paid', 'date_completed',
		'created', 'lastupdated',
	);

	public static function transaction_from_dal( array $t ) {
		$out = array(
			'id'                => isset( $t['id'] ) ? (int) $t['id'] : 0,
			'owner'             => isset( $t['owner'] ) ? (int) $t['owner'] : 0,
			'status'            => isset( $t['status'] ) ? $t['status'] : '',
			'type'              => isset( $t['type'] ) ? $t['type'] : '',
			'reference'         => isset( $t['ref'] ) ? $t['ref'] : '',
			'origin'            => isset( $t['origin'] ) ? $t['origin'] : '',
			'parent'            => isset( $t['parent'] ) ? $t['parent'] : '',
			'title'             => isset( $t['title'] ) ? $t['title'] : '',
			'description'       => isset( $t['desc'] ) ? $t['desc'] : '',
			'date'              => isset( $t['date'] ) ? $t['date'] : '',
			'currency'          => isset( $t['currency'] ) ? $t['currency'] : '',
			'net'               => isset( $t['net'] ) ? (float) $t['net'] : 0,
			'fee'               => isset( $t['fee'] ) ? (float) $t['fee'] : 0,
			'discount'          => isset( $t['discount'] ) ? (float) $t['discount'] : 0,
			'shipping'          => isset( $t['shipping'] ) ? (float) $t['shipping'] : 0,
			'taxes'             => isset( $t['taxes'] ) ? (float) $t['taxes'] : 0,
			'total'             => isset( $t['total'] ) ? (float) $t['total'] : 0,
			'date_paid'         => isset( $t['date_paid'] ) ? $t['date_paid'] : null,
			'date_completed'    => isset( $t['date_completed'] ) ? $t['date_completed'] : null,
			'invoice'           => isset( $t['invoice_id'] ) ? (int) $t['invoice_id'] : 0,
			'contacts'          => ( isset( $t['contacts'] ) && is_array( $t['contacts'] ) ) ? $t['contacts'] : array(),
			'companies'         => ( isset( $t['companies'] ) && is_array( $t['companies'] ) ) ? $t['companies'] : array(),
			'tags'              => ( isset( $t['tags'] ) && is_array( $t['tags'] ) ) ? $t['tags'] : array(),
			'custom_fields'     => self::extract_custom_fields( $t, self::TRANSACTION_KNOWN_KEYS ),
			'date_created_gmt'  => self::uts_to_iso8601( isset( $t['createduts'] ) ? $t['createduts'] : 0 ),
			'date_modified_gmt' => self::uts_to_iso8601( isset( $t['lastupdated'] ) ? $t['lastupdated'] : 0 ),
		);
		return $out;
	}

	public static function transaction_to_dal( array $payload ) {
		$data = array();
		foreach ( self::TRANSACTION_INPUT_KEYS as $key ) {
			if ( array_key_exists( $key, $payload ) ) {
				$data[ $key ] = $payload[ $key ];
			}
		}
		if ( array_key_exists( 'reference', $payload ) ) {
			$data['ref'] = $payload['reference'];
		}
		if ( array_key_exists( 'description', $payload ) ) {
			$data['desc'] = $payload['description'];
		}
		if ( array_key_exists( 'invoice', $payload ) ) {
			$data['invoice_id'] = $payload['invoice'];
		}
		if ( array_key_exists( 'contacts', $payload ) ) {
			$data['contacts'] = $payload['contacts'];
		}
		if ( array_key_exists( 'companies', $payload ) ) {
			$data['companies'] = $payload['companies'];
		}
		if ( array_key_exists( 'tags', $payload ) ) {
			$data['tags'] = $payload['tags'];
		}
		if ( array_key_exists( 'tag_mode', $payload ) ) {
			$data['tag_mode'] = $payload['tag_mode'];
		}
		if ( array_key_exists( 'external_sources', $payload ) ) {
			$data['externalSources'] = $payload['external_sources'];
		}
		if ( isset( $payload['custom_fields'] ) && is_array( $payload['custom_fields'] ) ) {
			foreach ( $payload['custom_fields'] as $key => $value ) {
				$data[ $key ] = $value;
			}
		}
		return $data;
	}

	const TASK_KNOWN_KEYS = array(
		'id', 'owner', 'title', 'desc', 'start', 'end', 'complete',
		'show_on_portal', 'show_on_cal',
		'reminders', 'contacts', 'companies', 'tags', 'meta',
		'created', 'createduts', 'created_date', 'lastupdated', 'lastupdated_date',
		'externalSources',
	);

	const TASK_INPUT_KEYS = array(
		'title', 'desc', 'start', 'end', 'complete',
		'show_on_portal', 'show_on_cal',
		'created', 'lastupdated',
	);

	public static function task_from_dal( array $t ) {
		$out = array(
			'id'                => isset( $t['id'] ) ? (int) $t['id'] : 0,
			'owner'             => isset( $t['owner'] ) ? (int) $t['owner'] : 0,
			'title'             => isset( $t['title'] ) ? $t['title'] : '',
			'description'       => isset( $t['desc'] ) ? $t['desc'] : '',
			'start'             => isset( $t['start'] ) ? $t['start'] : '',
			'end'               => isset( $t['end'] ) ? $t['end'] : '',
			'complete'          => isset( $t['complete'] ) ? (bool) $t['complete'] : false,
			'show_on_portal'    => isset( $t['show_on_portal'] ) ? (bool) $t['show_on_portal'] : false,
			'show_on_calendar'  => isset( $t['show_on_cal'] ) ? (bool) $t['show_on_cal'] : true,
			'reminders'         => ( isset( $t['reminders'] ) && is_array( $t['reminders'] ) ) ? $t['reminders'] : array(),
			'contacts'          => ( isset( $t['contacts'] ) && is_array( $t['contacts'] ) ) ? $t['contacts'] : array(),
			'companies'         => ( isset( $t['companies'] ) && is_array( $t['companies'] ) ) ? $t['companies'] : array(),
			'tags'              => ( isset( $t['tags'] ) && is_array( $t['tags'] ) ) ? $t['tags'] : array(),
			'custom_fields'     => self::extract_custom_fields( $t, self::TASK_KNOWN_KEYS ),
			'date_created_gmt'  => self::uts_to_iso8601( isset( $t['createduts'] ) ? $t['createduts'] : 0 ),
			'date_modified_gmt' => self::uts_to_iso8601( isset( $t['lastupdated'] ) ? $t['lastupdated'] : 0 ),
		);
		return $out;
	}

	public static function task_to_dal( array $payload ) {
		$data = array();
		foreach ( self::TASK_INPUT_KEYS as $key ) {
			if ( array_key_exists( $key, $payload ) ) {
				$data[ $key ] = $payload[ $key ];
			}
		}
		if ( array_key_exists( 'description', $payload ) ) {
			$data['desc'] = $payload['description'];
		}
		if ( array_key_exists( 'show_on_calendar', $payload ) ) {
			$data['show_on_cal'] = $payload['show_on_calendar'];
		}
		if ( array_key_exists( 'reminders', $payload ) ) {
			$data['reminders'] = $payload['reminders'];
		}
		if ( array_key_exists( 'contacts', $payload ) ) {
			$data['contacts'] = $payload['contacts'];
		}
		if ( array_key_exists( 'companies', $payload ) ) {
			$data['companies'] = $payload['companies'];
		}
		if ( array_key_exists( 'tags', $payload ) ) {
			$data['tags'] = $payload['tags'];
		}
		if ( array_key_exists( 'tag_mode', $payload ) ) {
			$data['tag_mode'] = $payload['tag_mode'];
		}
		if ( array_key_exists( 'external_sources', $payload ) ) {
			$data['externalSources'] = $payload['external_sources'];
		}
		if ( isset( $payload['custom_fields'] ) && is_array( $payload['custom_fields'] ) ) {
			foreach ( $payload['custom_fields'] as $key => $value ) {
				$data[ $key ] = $value;
			}
		}
		return $data;
	}

	const LOG_KNOWN_KEYS = array(
		'id', 'owner', 'objtype', 'objid', 'type',
		'shortdesc', 'longdesc', 'pinned', 'meta',
		'created', 'createduts', 'created_date', 'lastupdated',
	);

	const LOG_INPUT_KEYS = array(
		'objtype', 'objid', 'type', 'shortdesc', 'longdesc', 'pinned', 'created',
	);

	public static function log_from_dal( array $l ) {
		return array(
			'id'               => isset( $l['id'] ) ? (int) $l['id'] : 0,
			'owner'            => isset( $l['owner'] ) ? (int) $l['owner'] : 0,
			'object_type'      => isset( $l['objtype'] ) ? (int) $l['objtype'] : 0,
			'object_id'        => isset( $l['objid'] ) ? (int) $l['objid'] : 0,
			'type'             => isset( $l['type'] ) ? $l['type'] : '',
			'short_description' => isset( $l['shortdesc'] ) ? $l['shortdesc'] : '',
			'long_description'  => isset( $l['longdesc'] ) ? $l['longdesc'] : '',
			'pinned'           => isset( $l['pinned'] ) ? (bool) $l['pinned'] : false,
			'date_created_gmt' => self::uts_to_iso8601( isset( $l['createduts'] ) ? $l['createduts'] : 0 ),
		);
	}

	public static function log_to_dal( array $payload ) {
		$data = array();
		foreach ( self::LOG_INPUT_KEYS as $key ) {
			if ( array_key_exists( $key, $payload ) ) {
				$data[ $key ] = $payload[ $key ];
			}
		}
		$aliases = array(
			'object_type' => 'objtype', 'object_id' => 'objid',
			'short_description' => 'shortdesc', 'long_description' => 'longdesc',
		);
		foreach ( $aliases as $rest => $dal ) {
			if ( array_key_exists( $rest, $payload ) ) {
				$data[ $dal ] = $payload[ $rest ];
			}
		}
		return $data;
	}

	public static function generic_from_dal( array $item, $type ) {
		switch ( $type ) {
			case 'invoices':       return self::invoice_from_dal( $item );
			case 'quotes':         return self::quote_from_dal( $item );
			case 'transactions':   return self::transaction_from_dal( $item );
			case 'tasks':          return self::task_from_dal( $item );
			case 'logs':           return self::log_from_dal( $item );
			case 'companies':      return self::company_from_dal( $item );
			case 'contacts':
			default:               return self::contact_from_dal( $item );
		}
	}

	/**
	 * Convert a Unix timestamp into an ISO-8601 UTC string.
	 *
	 * @param mixed $uts Unix timestamp.
	 * @return string|null
	 */
	public static function uts_to_iso8601( $uts ) {
		$uts = (int) $uts;

		if ( $uts <= 0 ) {
			return null;
		}

		return gmdate( 'Y-m-d\TH:i:s', $uts );
	}
}
