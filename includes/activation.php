<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create and seed the mapping table on activation.
 *
 * @return void
 */
function bigseller_region_map_activate() {
	bigseller_region_map_create_table();
	bigseller_region_map_seed_table_if_empty();
}

/**
 * Create the mapping table if it does not already exist.
 *
 * @return void
 */
function bigseller_region_map_create_table() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table_name      = bigseller_region_map_table_name();
	$charset_collate = $wpdb->get_charset_collate();
	$sql             = "CREATE TABLE {$table_name} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		wc_country char(2) NOT NULL,
		wc_state varchar(32) NOT NULL,
		bs_country varchar(100) NOT NULL,
		bs_state varchar(100) NOT NULL,
		bs_city varchar(150) DEFAULT NULL,
		PRIMARY KEY  (id),
		KEY wc_country_state (wc_country,wc_state)
	) {$charset_collate};";

	dbDelta( $sql );
}

/**
 * Seed the mapping table when it is empty.
 *
 * @return void
 */
function bigseller_region_map_seed_table_if_empty() {
	global $wpdb;

	$table_name = bigseller_region_map_table_name();
	$row_count  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

	if ( $row_count > 0 ) {
		return;
	}

	$rows = bigseller_region_map_get_seed_rows();

	if ( empty( $rows ) ) {
		return;
	}

	foreach ( array_chunk( $rows, 100 ) as $chunk ) {
		$placeholders = array();
		$values       = array();

		foreach ( $chunk as $row ) {
			$row = bigseller_region_map_normalize_seed_row( $row );

			if ( empty( $row ) ) {
				continue;
			}

			if ( null === $row['bs_city'] ) {
				$placeholders[] = '(%s, %s, %s, %s, NULL)';
				$values[]       = $row['wc_country'];
				$values[]       = $row['wc_state'];
				$values[]       = $row['bs_country'];
				$values[]       = $row['bs_state'];
				continue;
			}

			$placeholders[] = '(%s, %s, %s, %s, %s)';
			$values[]       = $row['wc_country'];
			$values[]       = $row['wc_state'];
			$values[]       = $row['bs_country'];
			$values[]       = $row['bs_state'];
			$values[]       = $row['bs_city'];
		}

		if ( empty( $placeholders ) ) {
			continue;
		}

		$sql = "INSERT INTO {$table_name} (wc_country, wc_state, bs_country, bs_state, bs_city) VALUES " . implode( ', ', $placeholders ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sql = $wpdb->prepare( $sql, $values );

		if ( is_string( $sql ) ) {
			$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
	}
}

/**
 * Return the bundled seed rows.
 *
 * @return array
 */
function bigseller_region_map_get_seed_rows() {
	$seed_file = BIGSELLER_REGION_MAP_PLUGIN_DIR . 'data/malaysia-region-map.php';
	$seed_data = file_exists( $seed_file ) ? require $seed_file : array();
	$rows      = bigseller_region_map_expand_seed_rows( $seed_data );

	$rows = apply_filters( 'bigseller_region_map_seed_rows', $rows );

	return is_array( $rows ) ? $rows : array();
}

/**
 * Expand grouped seed data into flat insertable rows.
 *
 * @param array $seed_data Grouped seed data.
 * @return array
 */
function bigseller_region_map_expand_seed_rows( $seed_data ) {
	$rows = array();

	if ( ! is_array( $seed_data ) ) {
		return $rows;
	}

	foreach ( $seed_data as $wc_country => $states ) {
		if ( ! is_array( $states ) ) {
			continue;
		}

		foreach ( $states as $wc_state => $mappings ) {
			if ( ! is_array( $mappings ) ) {
				continue;
			}

			foreach ( $mappings as $mapping ) {
				if ( ! is_array( $mapping ) ) {
					continue;
				}

				$cities = isset( $mapping['cities'] ) && is_array( $mapping['cities'] ) ? $mapping['cities'] : array( null );

				if ( empty( $cities ) ) {
					$cities = array( null );
				}

				foreach ( $cities as $city ) {
					$rows[] = array(
						'wc_country' => $wc_country,
						'wc_state'   => $wc_state,
						'bs_country' => isset( $mapping['bs_country'] ) ? $mapping['bs_country'] : '',
						'bs_state'   => isset( $mapping['bs_state'] ) ? $mapping['bs_state'] : '',
						'bs_city'    => $city,
					);
				}
			}
		}
	}

	return $rows;
}

/**
 * Normalize a seed row before insert.
 *
 * @param array $row Raw row data.
 * @return array
 */
function bigseller_region_map_normalize_seed_row( $row ) {
	if ( ! is_array( $row ) ) {
		return array();
	}

	$normalized = array(
		'wc_country' => bigseller_region_map_normalize_code( isset( $row['wc_country'] ) ? $row['wc_country'] : '' ),
		'wc_state'   => bigseller_region_map_normalize_code( isset( $row['wc_state'] ) ? $row['wc_state'] : '' ),
		'bs_country' => trim( (string) ( isset( $row['bs_country'] ) ? $row['bs_country'] : '' ) ),
		'bs_state'   => trim( (string) ( isset( $row['bs_state'] ) ? $row['bs_state'] : '' ) ),
		'bs_city'    => isset( $row['bs_city'] ) && null !== $row['bs_city'] ? trim( (string) $row['bs_city'] ) : null,
	);

	if ( '' === $normalized['wc_country'] || '' === $normalized['wc_state'] || '' === $normalized['bs_country'] || '' === $normalized['bs_state'] ) {
		return array();
	}

	return $normalized;
}
