=== Advanced Dashboard for WooCommerce ===
Contributors: Piotr Pesta
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=EEDF5TV3M2WVG&lc=US
Tags: woocommerce, dashboard, reports, sales, charts
Requires at least: 7.0.8
Requires PHP: 8.2.35
Requires Plugins: woocommerce
Tested up to: 7.1.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Daily WooCommerce Analytics charts and a bounded order review queue on the main WordPress dashboard.

== Description ==

Advanced Dashboard for WooCommerce displays three responsive SVG charts on the main WordPress dashboard: net sales, orders and net items sold. Charts use local JavaScript without external servers or a chart framework. Keyboard inspection and a daily data table are available.

This working copy is a local 1.0.0 release candidate. Publication requires separate approval from the project owner.

= Dates and metrics =

Choose the last 7, 30 or 90 days, the current month or a custom range of up to 90 inclusive days. Presets include today. Boundaries use the WordPress store timezone and inclusive local calendar days. Future or reversed ranges are rejected.

Reports read WooCommerce Analytics aggregates explicitly by order creation date, independently of the shop's paid/completed-date report preference. Refund adjustments are assigned to the refund creation day, including refunds of older orders.

* Net sales: Analytics net_revenue, after discounts and refunds, excluding tax and shipping; includes fees as defined by Analytics. This is not profit or cash received.
* Orders: original order records in the included Analytics statuses, excluding refund records. A refunded original order can remain counted.
* Net items sold: Analytics line-item quantity after its refund adjustments. Amount-only refunds need not reduce quantity; allocation follows the installed WooCommerce version.

Statuses follow Analytics excluded-status settings and filters. Pending, failed and cancelled orders are excluded by the built-in defaults. Currency and number formatting follow WordPress/WooCommerce settings. Different currencies are never added together or converted.

= Analytics availability and limits =

WooCommerce Analytics and its revenue statistics endpoint must be enabled, available and populated for the requested history. Detected incomplete/failed imports, synchronization gaps, inconsistent data or mixed/unknown currencies withhold charts with an explanation. Valid empty data shows zero values. Operational information remains independently available.

These checks cannot prove that every historical amount is current. Recent changes can lag Analytics, and third-party import/exclusion or currency extensions can cause a conservative unavailable state. The widget does not scan all orders to calculate fallback totals, start imports, repair data or modify orders. Ranges with more than 100 relevant refunds require a shorter period.

= Order review =

The operational section shows processing, on-hold and pending counts across all ages, plus at most ten oldest editable matching orders above a selected age threshold. Rows show order number, status, store-local creation date, elapsed age and a direct editor link.

The default threshold is 72 hours; whole hours from 1 to 8760 are accepted. Open and registered custom statuses can be selected. Completed, cancelled, refunded, failed and draft/trash statuses are excluded from this queue. Age is measured from creation, not time in the current status. An older order is not automatically incorrect or late. Preferences are saved per user and site with permission and nonce checks.

= Access and compatibility =

Charts require view_woocommerce_reports. Queue, preferences and counts additionally require edit_shop_orders; order links also check access to each order. Customer contact details and line items are not included in the widget responses. No external tracking or new external runtime dependency is added.

Tested combinations, each in CPT and HPOS with posts synchronization disabled:

* WordPress 7.0.8 / WooCommerce 11.2.2 / PHP 8.2.35.
* WordPress 7.1.4 / WooCommerce 11.3.0 / PHP 8.3.36.

The exact older patch versions form the conservative tested minimums. Lower versions, arbitrary combinations, multisite, high-volume stores and broad third-party extension compatibility have not been certified. HPOS compatibility is declared before WooCommerce initialization.

The original plugin filename, slug, text domain and advanced-dashboard-admin-widget ID are retained. Updating from 0.2.5 preserves native widget position, visibility and collapse preferences. No database migration or data deletion is performed.

== Installation ==

1. Install and activate WooCommerce meeting the tested requirements above.
2. In Plugins > Add New > Upload Plugin, select the approved plugin ZIP and install it. When replacing 0.2.5, use WordPress's replacement/update workflow; do not install under a different directory name.
3. Activate the plugin if prompted, then open Dashboard with an authorized account.
4. Use Screen Options and native widget controls to show, move or expand the widget. Enable/import WooCommerce Analytics if reports are unavailable.

The local candidate must not be published without separate owner approval.

== Changelog ==

= 1.0.0 =
* Replace legacy reports and external chart scripts with three local SVG charts backed by WooCommerce Analytics.
* Add strict date ranges, store-timezone boundaries, explicit creation-date metrics and honest unavailable/empty states.
* Add bounded order review, status counts and secure per-user age/status preferences.
* Restrict data and assets to authorized dashboard use; validate requests and escape output.
* Support authoritative WooCommerce order APIs and declare HPOS compatibility after CPT/HPOS tests without posts synchronization.
* Preserve the original plugin and widget identity and native dashboard preferences when replacing 0.2.5.

= 0.2.5 =
* Minor bug fix.

= 0.2.4 =
* Minor bug fix.

= 0.2.3 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Requires the tested modern WP/WC/PHP requirements above and available WooCommerce Analytics. Preserves widget identity and dashboard preferences. Local candidate only; publication requires owner approval.
