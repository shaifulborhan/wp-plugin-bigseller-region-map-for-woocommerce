=== BigSeller Region Map for WooCommerce ===
Contributors: shaifulborhan
Tags: woocommerce, bigseller, regions, rest-api
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Maps WooCommerce order REST API country and state values to BigSeller region labels using a database-backed lookup table.

== Description ==

BigSeller Region Map for WooCommerce rewrites billing and shipping country/state values in WooCommerce order REST API responses so BigSeller receives the region labels it expects. For example:

MY → Malaysia
SGR → Selangor

The plugin does not modify WooCommerce order data stored in the database. It only adjusts REST API responses for allowed BigSeller API requests.

On activation, the plugin:

1. Creates the `{prefix}bigseller_region_map` table if it does not exist.
2. Seeds a bundled Malaysia dataset when the table is missing or empty.

If the table already contains rows, activation leaves them intact. Deactivation and plugin removal also leave the table and its data in place so custom mappings are preserved.

The plugin is generic and can be used outside Malaysia. The bundled seed dataset only covers Malaysia, but merchants can manually add rows for Singapore, Indonesia, Thailand, Vietnam, the Philippines, or any other country by inserting data into the same table.

Do not run multiple plugins that rewrite BigSeller order regions from the same table at the same time.

== Installation ==

1. Upload the plugin ZIP or copy the plugin folder into `wp-content/plugins`.
2. Activate `BigSeller Region Map for WooCommerce`.
3. Go to `WooCommerce > BigSeller Region Map`.
4. Choose the WooCommerce REST API key used by BigSeller.
5. Save changes.

If no API key is selected, the plugin does not rewrite order REST responses.

== Frequently Asked Questions ==

= What table does the plugin use? =

The plugin reads from `{prefix}bigseller_region_map`, where `{prefix}` is your site's active WordPress database prefix.

= What columns does the table use? =

The plugin expects:

- `wc_country`
- `wc_state`
- `bs_country`
- `bs_state`
- `bs_city` (optional)

Example row structure:

`wc_country = MY`
`wc_state = SGR`
`bs_country = Malaysia`
`bs_state = Selangor`
`bs_city = Shah Alam`

= Can I use this for non-Malaysia stores? =

Yes. The bundled seed data is Malaysia-only, but the plugin will use whatever rows exist in `{prefix}bigseller_region_map`. You can manually insert rows for other countries and the runtime lookup will continue to work.

= Will uninstall remove my mapping data? =

No. The plugin does not drop the mapping table on deactivation or removal.

== Changelog ==

= 1.0.0 =

* Initial standalone release.
* Adds activation-time table creation and Malaysia seed data.
* Adds WooCommerce settings page for request targeting.
