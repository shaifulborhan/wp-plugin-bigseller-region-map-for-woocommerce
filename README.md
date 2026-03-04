# BigSeller Region Map for WooCommerce

Maps WooCommerce order REST API country and state values to the BigSeller region labels it expects, using a database-backed lookup table. The plugin ships with a Malaysia seed dataset.

## What It Does

This plugin rewrites billing and shipping country and state values in WooCommerce order REST API responses before BigSeller receives them.

Examples:

- `MY` -> `Malaysia`
- `SGR` -> `Selangor`

The plugin does not modify WooCommerce order data stored in the database. It only adjusts REST API responses for allowed BigSeller API requests.

## How It Works

On activation, the plugin:

1. Creates the `{prefix}bigseller_region_map` table if it does not exist.
2. Seeds a bundled Malaysia dataset when the table is missing or empty.

If the table already contains rows, activation leaves them intact. Deactivation and plugin removal also leave the table and its data in place so custom mappings are preserved.

## Configuration

After activation:

1. Go to `WooCommerce > BigSeller Region Map`.
2. Choose the WooCommerce REST API key used by BigSeller.
3. Save changes.

If no API key is selected, the plugin does not rewrite order REST responses.

## Database Table

The plugin reads from `{prefix}bigseller_region_map`, where `{prefix}` is your site's active WordPress database prefix.

Expected columns:

- `wc_country`
- `wc_state`
- `bs_country`
- `bs_state`
- `bs_city` (optional)

Example row:

```text
wc_country = MY
wc_state   = SGR
bs_country = Malaysia
bs_state   = Selangor
bs_city    = Shah Alam
```

## Country Coverage

The plugin is generic and can be used outside Malaysia. The bundled seed dataset only covers Malaysia, but you can manually insert rows for Singapore, Indonesia, Thailand, Vietnam, the Philippines, or any other country into the same mapping table.

Do not run multiple plugins that rewrite BigSeller order regions from the same table at the same time.

## Installation

1. Upload the plugin ZIP or copy the plugin folder into `wp-content/plugins`.
2. Activate `BigSeller Region Map for WooCommerce`.
3. Configure the BigSeller API key mapping in WooCommerce settings.

## Uninstall Behavior

The plugin does not drop the mapping table on deactivation or removal.

## License

Licensed under [GPL v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).
