=== Advanced Dashboard for WooCommerce ===
Contributors: piotr-pesta
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=EEDF5TV3M2WVG&lc=US
Tags: woocommerce, dashboard, analytics, sales, orders
Requires at least: 7.0.8
Requires PHP: 8.2.35
Requires Plugins: woocommerce
Tested up to: 7.1.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

See net sales, orders, net items sold and orders to review directly on your main WordPress dashboard.

== Description ==

Open **Dashboard → Home** to see how sales have changed and which orders are ready for a closer look. Advanced Dashboard for WooCommerce brings three daily WooCommerce Analytics charts and a separate order review queue to the main WordPress dashboard.

* **See three daily metrics together.** Compare net sales, order count and net items sold. Hover, tap or use the keyboard to inspect a date, or open the daily data table for exact values.
* **Compare the periods you care about.** Choose the last 7, 30 or 90 days, the current month, or a custom range of up to 90 days. Presets include today. Dates use the store's time zone.
* **Review open orders without leaving the dashboard.** See processing, on-hold and pending payment counts, then open a queue of up to ten oldest matching orders you can edit. Choose an age threshold and eligible statuses, including registered custom statuses.
* **Use either WooCommerce order storage mode.** The widget was tested with legacy post storage (CPT) and High-Performance Order Storage (HPOS), including HPOS with posts synchronization disabled.

The charts use local SVG and JavaScript. An empty period shows zero totals and a daily table. If Analytics is unavailable or the totals cannot be verified, the widget explains why and keeps order review available independently.

= Requirements and tested configurations =

Minimum versions are WordPress 7.0.8, WooCommerce 11.2.2 and PHP 8.2.35. Activate WooCommerce and enable WooCommerce Analytics with the required order history imported to use the charts.

Integration tests covered WordPress 7.0.8 / WooCommerce 11.2.2 / PHP 8.2.35 and WordPress 7.1.4 / WooCommerce 11.3.0 / PHP 8.3.36 in both storage modes. Other combinations, multisite, large-store load and third-party extensions were outside the tested scope.

== Installation ==

1. Install and activate WooCommerce on a site that meets the requirements above.
2. In WordPress, go to **Plugins → Add New** and install **Advanced Dashboard for WooCommerce**. You can also use **Upload Plugin** with the plugin ZIP.
3. Activate the plugin and open **Dashboard → Home**. Use **Screen Options** if the widget is hidden.
4. If the charts are unavailable, open the widget's Analytics settings link and check that Analytics is enabled and its import covers the selected dates.

= Updating from 0.2.5 =

Check the new minimum versions before updating. Use the normal WordPress update flow or upload the ZIP and replace the existing plugin. The widget keeps its existing placement, visibility and collapse preferences. Activate the plugin if WordPress prompts you to do so.

== Frequently Asked Questions ==

= How are the three metrics calculated? =

* **Net sales** use the WooCommerce Analytics net revenue figure. It reflects discounts and refunds, excludes tax and shipping, and includes fees as defined by Analytics.
* **Orders** count original order records in the statuses included by Analytics. Refund records do not count as orders. An original order can remain counted after a refund.
* **Net items sold** are the line-item quantities reported by Analytics after quantity adjustments for refunds. A refund for an amount alone may leave the item count unchanged.

The widget groups orders by their creation day in the store's time zone. Refund adjustments appear on the day the refund was created, even when the original order falls outside the range. Included order statuses follow the Analytics settings and filters.

= Why might charts be unavailable? =

Analytics can lag behind recent sales or changes. Incomplete or failed imports, inconsistent records and mixed or unknown currencies can prevent the widget from confirming totals. The widget does not convert currencies or repair Analytics data. Ranges with more than 100 relevant refund records require a shorter period.

= Does an older order count as late? =

No. The default threshold is 72 hours from order creation. It helps you decide where to look first. You can change it from 1 to 8,760 hours and choose which open statuses appear. Preferences are saved for your user on the current site. Chart dates do not filter the queue or status counts.

= Who can see the widget and order queue? =

Viewing the charts requires the view_woocommerce_reports capability. Order review and its controls require edit_shop_orders. The queue also checks that you can edit each linked order.

== Screenshots ==

1. The main WordPress dashboard widget with net sales, orders and net items sold, plus three daily charts.
2. A custom start and end date applied to the dashboard report.
3. The expanded order review queue with status counts, age, creation date and links to edit orders.
4. The same dashboard widget on a narrow mobile screen.

== Changelog ==

= 1.0.0 =
* Replaced the legacy reports and external chart scripts with local SVG charts backed by WooCommerce Analytics.
* Added daily net sales, order count and net items sold, with presets, custom dates and a daily data table.
* Added order status counts and a bounded queue with user-specific age and status preferences.
* Added HPOS compatibility and verified both HPOS without posts synchronization and legacy CPT storage.
* Added explicit empty and unavailable states, date validation and permission checks.
* Preserved the existing widget identity and dashboard preferences when updating from 0.2.5.

= 0.2.5 =
* Minor bug fix.

= 0.2.4 =
* Minor bug fix.

= 0.2.3 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Requires WordPress 7.0.8, WooCommerce 11.2.2 and PHP 8.2.35 or later. Charts need WooCommerce Analytics. Your existing dashboard widget preferences are retained.
