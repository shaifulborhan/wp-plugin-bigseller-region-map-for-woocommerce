<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the BigSeller mapping table name.
 *
 * @return string
 */
function bigseller_region_map_table_name() {
	global $wpdb;

	return $wpdb->prefix . 'bigseller_region_map';
}

/**
 * Return whether the WooCommerce API keys table exists.
 *
 * @return bool
 */
function bigseller_region_map_woocommerce_api_keys_table_exists() {
	global $wpdb;

	static $table_exists = null;

	if ( null !== $table_exists ) {
		return $table_exists;
	}

	$table_name   = $wpdb->prefix . 'woocommerce_api_keys';
	$table_exists = ( $table_name === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) );

	return $table_exists;
}

/**
 * Normalize a WooCommerce country/state code for lookups.
 *
 * @param string $value Raw country/state code.
 * @return string
 */
function bigseller_region_map_normalize_code( $value ) {
	return strtoupper( trim( (string) $value ) );
}

/**
 * Sanitize a string array received from settings or filters.
 *
 * @param mixed $values Raw values.
 * @return string[]
 */
function bigseller_region_map_sanitize_string_array( $values ) {
	if ( ! is_array( $values ) ) {
		return array();
	}

	$values = array_map( 'strval', $values );
	$values = array_map( 'trim', $values );
	$values = array_map( 'sanitize_text_field', $values );
	$values = array_values( array_filter( $values, 'strlen' ) );

	return array_values( array_unique( $values ) );
}

/**
 * Resolve BigSeller country/state labels from WooCommerce country/state codes.
 *
 * @param string $wc_country WooCommerce country code.
 * @param string $wc_state   WooCommerce state code.
 * @return array|null
 */
function bigseller_region_map_get_region_mapping( $wc_country, $wc_state ) {
	global $wpdb;

	static $table_exists = null;
	static $cache        = array();

	$wc_country = bigseller_region_map_normalize_code( $wc_country );
	$wc_state   = bigseller_region_map_normalize_code( $wc_state );

	if ( '' === $wc_country || '' === $wc_state ) {
		return null;
	}

	$cache_key = $wc_country . '|' . $wc_state;

	if ( array_key_exists( $cache_key, $cache ) ) {
		return $cache[ $cache_key ];
	}

	$table_name = bigseller_region_map_table_name();

	if ( null === $table_exists ) {
		$table_exists = ( $table_name === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) );
	}

	if ( ! $table_exists ) {
		$cache[ $cache_key ] = null;
		return null;
	}

	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT bs_country, bs_state, COUNT(*) AS row_count
			FROM {$table_name}
			WHERE wc_country = %s
			  AND wc_state = %s
			GROUP BY bs_country, bs_state
			ORDER BY row_count DESC, bs_country ASC, bs_state ASC
			LIMIT 1",
			$wc_country,
			$wc_state
		),
		ARRAY_A
	);

	if ( empty( $row['bs_country'] ) || empty( $row['bs_state'] ) ) {
		$cache[ $cache_key ] = null;
		return null;
	}

	$cache[ $cache_key ] = array(
		'wc_country' => $wc_country,
		'wc_state'   => $wc_state,
		'bs_country' => (string) $row['bs_country'],
		'bs_state'   => (string) $row['bs_state'],
	);

	return $cache[ $cache_key ];
}

/**
 * Apply region mapping to an order address block.
 *
 * @param array $address Address payload.
 * @return array
 */
function bigseller_region_map_map_address_region( array $address ) {
	if ( empty( $address['country'] ) || empty( $address['state'] ) ) {
		return $address;
	}

	$mapping = bigseller_region_map_get_region_mapping( $address['country'], $address['state'] );

	if ( ! $mapping ) {
		return $address;
	}

	$address['country'] = $mapping['bs_country'];
	$address['state']   = $mapping['bs_state'];

	return $address;
}

/**
 * Extract the consumer key from a REST request.
 *
 * @param WP_REST_Request $request REST request.
 * @return string
 */
function bigseller_region_map_get_rest_consumer_key( $request ) {
	if ( ! $request instanceof WP_REST_Request ) {
		return '';
	}

	$consumer_key = (string) $request->get_param( 'consumer_key' );

	if ( '' !== $consumer_key ) {
		return trim( $consumer_key );
	}

	if ( ! empty( $_SERVER['PHP_AUTH_USER'] ) ) {
		return trim( sanitize_text_field( wp_unslash( $_SERVER['PHP_AUTH_USER'] ) ) );
	}

	$authorization = (string) $request->get_header( 'authorization' );

	if ( 0 === stripos( $authorization, 'basic ' ) ) {
		$encoded = trim( substr( $authorization, 6 ) );
		$decoded = base64_decode( $encoded, true );

		if ( is_string( $decoded ) && '' !== $decoded ) {
			$parts = explode( ':', $decoded, 2 );

			if ( ! empty( $parts[0] ) ) {
				return trim( $parts[0] );
			}
		}
	}

	return '';
}

/**
 * Check whether a REST consumer key belongs to a WooCommerce API key description.
 *
 * @param string $consumer_key Incoming REST consumer key.
 * @param string $description  WooCommerce API key description.
 * @return bool
 */
function bigseller_region_map_consumer_key_matches_description( $consumer_key, $description ) {
	global $wpdb;

	static $cache = array();

	$consumer_key = trim( (string) $consumer_key );
	$description  = trim( (string) $description );

	if ( '' === $consumer_key || '' === $description ) {
		return false;
	}

	if ( ! bigseller_region_map_woocommerce_api_keys_table_exists() ) {
		return false;
	}

	$consumer_key_hash = function_exists( 'wc_api_hash' )
		? wc_api_hash( sanitize_text_field( $consumer_key ) )
		: hash_hmac( 'sha256', sanitize_text_field( $consumer_key ), 'wc-api' );

	$cache_key = $consumer_key_hash . '|' . strtolower( $description );

	if ( array_key_exists( $cache_key, $cache ) ) {
		return (bool) $cache[ $cache_key ];
	}

	$table_name = $wpdb->prefix . 'woocommerce_api_keys';
	$key_id     = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT key_id
			FROM {$table_name}
			WHERE consumer_key = %s
			  AND description = %s
			LIMIT 1",
			$consumer_key_hash,
			$description
		)
	);

	$cache[ $cache_key ] = ! empty( $key_id );

	return (bool) $cache[ $cache_key ];
}

/**
 * Check whether a REST consumer key matches a specific WooCommerce API key ID.
 *
 * @param string $consumer_key Incoming REST consumer key.
 * @param int    $key_id       WooCommerce API key ID.
 * @return bool
 */
function bigseller_region_map_consumer_key_matches_key_id( $consumer_key, $key_id ) {
	global $wpdb;

	static $cache = array();

	$consumer_key = trim( (string) $consumer_key );
	$key_id       = absint( $key_id );

	if ( '' === $consumer_key || $key_id <= 0 ) {
		return false;
	}

	if ( ! bigseller_region_map_woocommerce_api_keys_table_exists() ) {
		return false;
	}

	$consumer_key_hash = function_exists( 'wc_api_hash' )
		? wc_api_hash( sanitize_text_field( $consumer_key ) )
		: hash_hmac( 'sha256', sanitize_text_field( $consumer_key ), 'wc-api' );

	$cache_key = $consumer_key_hash . '|' . $key_id;

	if ( array_key_exists( $cache_key, $cache ) ) {
		return (bool) $cache[ $cache_key ];
	}

	$table_name = $wpdb->prefix . 'woocommerce_api_keys';
	$match_id   = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT key_id
			FROM {$table_name}
			WHERE key_id = %d
			  AND consumer_key = %s
			LIMIT 1",
			$key_id,
			$consumer_key_hash
		)
	);

	$cache[ $cache_key ] = ! empty( $match_id );

	return (bool) $cache[ $cache_key ];
}

/**
 * Determine whether the current request should receive mapped BigSeller regions.
 *
 * @param WP_REST_Request $request REST request.
 * @return bool
 */
function bigseller_region_map_is_target_order_rest_request( $request ) {
	if ( ! $request instanceof WP_REST_Request ) {
		return false;
	}

	$route = (string) $request->get_route();

	if ( 0 !== strpos( $route, '/wc/v3/orders' ) && 0 !== strpos( $route, '/wc/v2/orders' ) && 0 !== strpos( $route, '/wc/v1/orders' ) ) {
		return false;
	}

	$consumer_key = bigseller_region_map_get_rest_consumer_key( $request );

	if ( '' === $consumer_key ) {
		return false;
	}

	$settings              = bigseller_region_map_get_settings();
	$allowed_consumer_keys = apply_filters(
		'bigseller_region_map_rest_consumer_keys',
		$settings['consumer_keys']
	);
	$allowed_consumer_keys = bigseller_region_map_sanitize_string_array( $allowed_consumer_keys );

	if ( ! empty( $allowed_consumer_keys ) ) {
		return in_array( $consumer_key, $allowed_consumer_keys, true );
	}

	$selected_key_id = apply_filters(
		'bigseller_region_map_rest_api_key_id',
		(int) $settings['api_key_id']
	);
	$selected_key_id = absint( $selected_key_id );

	if ( $selected_key_id > 0 ) {
		return bigseller_region_map_consumer_key_matches_key_id( $consumer_key, $selected_key_id );
	}

	$allowed_descriptions = apply_filters(
		'bigseller_region_map_rest_api_key_descriptions',
		array()
	);
	$allowed_descriptions = bigseller_region_map_sanitize_string_array( $allowed_descriptions );

	if ( empty( $allowed_descriptions ) ) {
		return false;
	}

	foreach ( $allowed_descriptions as $description ) {
		if ( bigseller_region_map_consumer_key_matches_description( $consumer_key, $description ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Rewrite WooCommerce order REST response regions for BigSeller requests.
 *
 * @param WP_REST_Response $response REST response object.
 * @param mixed            $order    Order object.
 * @param WP_REST_Request  $request  REST request object.
 * @return WP_REST_Response
 */
function bigseller_region_map_filter_rest_order_regions( $response, $order, $request ) {
	unset( $order );

	if ( ! $response instanceof WP_REST_Response ) {
		return $response;
	}

	if ( ! bigseller_region_map_is_target_order_rest_request( $request ) ) {
		return $response;
	}

	$data = $response->get_data();

	if ( ! is_array( $data ) ) {
		return $response;
	}

	if ( isset( $data['billing'] ) && is_array( $data['billing'] ) ) {
		$data['billing'] = bigseller_region_map_map_address_region( $data['billing'] );
	}

	if ( isset( $data['shipping'] ) && is_array( $data['shipping'] ) ) {
		$data['shipping'] = bigseller_region_map_map_address_region( $data['shipping'] );
	}

	$response->set_data( $data );

	return $response;
}
add_filter( 'woocommerce_rest_prepare_shop_order_object', 'bigseller_region_map_filter_rest_order_regions', 20, 3 );
add_filter( 'woocommerce_rest_prepare_shop_order', 'bigseller_region_map_filter_rest_order_regions', 20, 3 );
