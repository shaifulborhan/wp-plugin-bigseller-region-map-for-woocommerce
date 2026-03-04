<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the plugin option name.
 *
 * @return string
 */
function bigseller_region_map_settings_option_name() {
	return 'bigseller_region_map_settings';
}

/**
 * Return the default plugin settings.
 *
 * @return array
 */
function bigseller_region_map_get_default_settings() {
	return array(
		'api_key_id' => 0,
	);
}

/**
 * Return the settings page URL.
 *
 * @return string
 */
function bigseller_region_map_settings_page_url() {
	return admin_url( 'admin.php?page=bigseller-region-map' );
}

/**
 * Return the WooCommerce REST API keys page URL.
 *
 * @return string
 */
function bigseller_region_map_rest_api_keys_page_url() {
	return admin_url( 'admin.php?page=wc-settings&tab=advanced&section=keys' );
}

/**
 * Return the WooCommerce REST API keys available on the site.
 *
 * @return array[]
 */
function bigseller_region_map_get_available_api_keys() {
	global $wpdb;

	if ( ! bigseller_region_map_woocommerce_api_keys_table_exists() ) {
		return array();
	}

	$table_name = $wpdb->prefix . 'woocommerce_api_keys';
	$keys       = $wpdb->get_results(
		"SELECT key_id, user_id, description, permissions, truncated_key, last_access
		FROM {$table_name}
		ORDER BY key_id DESC",
		ARRAY_A
	); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

	if ( ! is_array( $keys ) ) {
		return array();
	}

	foreach ( $keys as &$key ) {
		$key['key_id']        = isset( $key['key_id'] ) ? (int) $key['key_id'] : 0;
		$key['user_id']       = isset( $key['user_id'] ) ? (int) $key['user_id'] : 0;
		$key['description']   = isset( $key['description'] ) ? (string) $key['description'] : '';
		$key['permissions']   = isset( $key['permissions'] ) ? (string) $key['permissions'] : '';
		$key['truncated_key'] = isset( $key['truncated_key'] ) ? (string) $key['truncated_key'] : '';

		$user = $key['user_id'] > 0 ? get_userdata( $key['user_id'] ) : false;

		if ( $user instanceof WP_User ) {
			$key['user_label'] = $user->display_name ? $user->display_name : $user->user_login;
		} else {
			$key['user_label'] = '';
		}
	}
	unset( $key );

	return $keys;
}

/**
 * Build a readable label for a WooCommerce REST API key.
 *
 * @param array $key API key record.
 * @return string
 */
function bigseller_region_map_format_api_key_label( array $key ) {
	$label = '';

	if ( '' !== $key['description'] ) {
		$label = $key['description'];
	} else {
		$label = sprintf( 'API Key #%d', (int) $key['key_id'] );
	}

	if ( '' !== $key['permissions'] ) {
		$label .= sprintf( ' [%s]', ucfirst( $key['permissions'] ) );
	}

	if ( '' !== $key['truncated_key'] ) {
		$label .= sprintf( ' (%s)', $key['truncated_key'] );
	}

	if ( '' !== $key['user_label'] ) {
		$label .= sprintf( ' - %s', $key['user_label'] );
	}

	return $label;
}

/**
 * Sanitize plugin settings before saving.
 *
 * @param array|string $input Raw settings.
 * @return array
 */
function bigseller_region_map_sanitize_settings( $input ) {
	$input = is_array( $input ) ? $input : array();

	return array(
		'api_key_id' => isset( $input['api_key_id'] ) ? absint( $input['api_key_id'] ) : 0,
	);
}

/**
 * Return the saved plugin settings merged with defaults.
 *
 * @return array
 */
function bigseller_region_map_get_settings() {
	$settings = get_option( bigseller_region_map_settings_option_name(), array() );
	$settings = is_array( $settings ) ? $settings : array();

	return wp_parse_args( $settings, bigseller_region_map_get_default_settings() );
}

/**
 * Register the plugin settings.
 *
 * @return void
 */
function bigseller_region_map_register_settings() {
	register_setting(
		'bigseller_region_map_settings',
		bigseller_region_map_settings_option_name(),
		array(
			'type'              => 'array',
			'sanitize_callback' => 'bigseller_region_map_sanitize_settings',
			'default'           => bigseller_region_map_get_default_settings(),
		)
	);

	add_settings_section(
		'bigseller_region_map_main',
		'BigSeller request targeting',
		'bigseller_region_map_render_settings_intro',
		'bigseller-region-map'
	);

	add_settings_field(
		'bigseller_region_map_api_key_id',
		'WooCommerce REST API key',
		'bigseller_region_map_render_api_key_select_field',
		'bigseller-region-map',
		'bigseller_region_map_main'
	);
}
add_action( 'admin_init', 'bigseller_region_map_register_settings' );

/**
 * Register the WooCommerce submenu page.
 *
 * @return void
 */
function bigseller_region_map_register_settings_page() {
	add_submenu_page(
		'woocommerce',
		'BigSeller Region Map',
		'BigSeller Region Map',
		'manage_woocommerce',
		'bigseller-region-map',
		'bigseller_region_map_render_settings_page'
	);
}
add_action( 'admin_menu', 'bigseller_region_map_register_settings_page', 99 );

/**
 * Add a direct Settings link on the Plugins page.
 *
 * @param string[] $links Existing row action links.
 * @return string[]
 */
function bigseller_region_map_plugin_action_links( $links ) {
	array_unshift(
		$links,
		sprintf(
			'<a href="%s">%s</a>',
			esc_url( bigseller_region_map_settings_page_url() ),
			esc_html__( 'Settings', 'bigseller-region-map-for-woocommerce' )
		)
	);

	return $links;
}
add_filter(
	'plugin_action_links_' . plugin_basename( BIGSELLER_REGION_MAP_PLUGIN_FILE ),
	'bigseller_region_map_plugin_action_links'
);

/**
 * Render the settings section intro.
 *
 * @return void
 */
function bigseller_region_map_render_settings_intro() {
	$table_name = bigseller_region_map_table_name();
	?>
	<p>The mapping table for this site is <code><?php echo esc_html( $table_name ); ?></code>.</p>
	<p>The plugin seeds Malaysia rows on activation only when the table is missing or empty. Existing rows are preserved, and you can manually add rows for other countries in the same table.</p>
	<p>Select the WooCommerce REST API key used by BigSeller. If no key is selected, the plugin will not rewrite order REST responses.</p>
	<?php
}

/**
 * Render the API key select field.
 *
 * @return void
 */
function bigseller_region_map_render_api_key_select_field() {
	$settings = bigseller_region_map_get_settings();
	$keys     = bigseller_region_map_get_available_api_keys();
	?>
	<?php if ( empty( $keys ) ) : ?>
		<p>No WooCommerce REST API keys were found.</p>
		<p class="description">
			<a href="<?php echo esc_url( bigseller_region_map_rest_api_keys_page_url() ); ?>">Create or manage API keys in WooCommerce.</a>
		</p>
	<?php else : ?>
		<select
			name="<?php echo esc_attr( bigseller_region_map_settings_option_name() ); ?>[api_key_id]"
			id="bigseller-region-map-api-key-id"
			class="regular-text"
		>
			<option value="0">Select an API key</option>
			<?php foreach ( $keys as $key ) : ?>
				<option value="<?php echo esc_attr( $key['key_id'] ); ?>" <?php selected( (int) $settings['api_key_id'], (int) $key['key_id'] ); ?>>
					<?php echo esc_html( bigseller_region_map_format_api_key_label( $key ) ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description">
			Choose the WooCommerce REST API key used by BigSeller.
			<a href="<?php echo esc_url( bigseller_region_map_rest_api_keys_page_url() ); ?>">Manage API keys</a>.
		</p>
	<?php endif; ?>
	<?php
}

/**
 * Render the settings page.
 *
 * @return void
 */
function bigseller_region_map_render_settings_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'bigseller-region-map-for-woocommerce' ) );
	}
	?>
	<div class="wrap">
		<h1>BigSeller Region Map</h1>
		<?php settings_errors(); ?>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'bigseller_region_map_settings' );
			do_settings_sections( 'bigseller-region-map' );
			submit_button( 'Save Changes' );
			?>
		</form>
	</div>
	<?php
}
