<?php
/**
 * Only rendered after the report capability and WooCommerce checks.
 *
 * @package AdvancedDashboardForWooCommerce
 */

defined( 'ABSPATH' ) || exit;
$adwc_today = current_datetime()->format( 'Y-m-d' );
?>
<div class="adwc" data-endpoint="<?php echo esc_url( rest_url( 'advanced-dashboard-for-woocommerce/v1/summary' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>" data-locale="<?php echo esc_attr( str_replace( '_', '-', get_user_locale() ) ); ?>">
	<form class="adwc-filters">
		<label for="adwc-range"><?php esc_html_e( 'Date range', 'advanced-dashboard-for-woocommerce' ); ?>
			<select id="adwc-range" name="range">
				<option value="7"><?php esc_html_e( 'Last 7 days', 'advanced-dashboard-for-woocommerce' ); ?></option>
				<option value="30"><?php esc_html_e( 'Last 30 days', 'advanced-dashboard-for-woocommerce' ); ?></option>
				<option value="month" selected><?php esc_html_e( 'This month', 'advanced-dashboard-for-woocommerce' ); ?></option>
				<option value="90"><?php esc_html_e( 'Last 90 days', 'advanced-dashboard-for-woocommerce' ); ?></option>
				<option value="custom"><?php esc_html_e( 'Custom range', 'advanced-dashboard-for-woocommerce' ); ?></option>
			</select>
		</label>
		<div class="adwc-custom" hidden>
			<label for="adwc-from"><?php esc_html_e( 'From', 'advanced-dashboard-for-woocommerce' ); ?><input id="adwc-from" type="date" name="from" max="<?php echo esc_attr( $adwc_today ); ?>" value="<?php echo esc_attr( current_datetime()->format( 'Y-m-01' ) ); ?>" disabled></label>
			<label for="adwc-to"><?php esc_html_e( 'To', 'advanced-dashboard-for-woocommerce' ); ?><input id="adwc-to" type="date" name="to" max="<?php echo esc_attr( $adwc_today ); ?>" value="<?php echo esc_attr( $adwc_today ); ?>" disabled></label>
		</div>
		<button class="button button-primary" type="submit"><?php esc_html_e( 'Apply', 'advanced-dashboard-for-woocommerce' ); ?></button>
	</form>
	<p class="adwc-caption"><?php esc_html_e( 'Including today · Store timezone · Up to 90 days', 'advanced-dashboard-for-woocommerce' ); ?></p>
	<p class="adwc-status" role="status" aria-live="polite" data-loading="<?php esc_attr_e( 'Loading store data…', 'advanced-dashboard-for-woocommerce' ); ?>" data-error="<?php esc_attr_e( 'Data could not be loaded. Reload the dashboard or try again.', 'advanced-dashboard-for-woocommerce' ); ?>" data-empty="<?php esc_attr_e( 'No sales activity in this range. Days without sales are shown as zero.', 'advanced-dashboard-for-woocommerce' ); ?>"><?php esc_html_e( 'Apply a date range to load the report.', 'advanced-dashboard-for-woocommerce' ); ?></p>
	<noscript><p><?php esc_html_e( 'Enable JavaScript to load the charts and their accessible data table.', 'advanced-dashboard-for-woocommerce' ); ?></p></noscript>
	<div class="adwc-report" hidden>
		<p class="adwc-period"></p>
		<div class="adwc-totals">
			<?php
			foreach ( array(
				'net_revenue'    => __( 'Net sales', 'advanced-dashboard-for-woocommerce' ),
				'orders_count'   => __( 'Orders', 'advanced-dashboard-for-woocommerce' ),
				'num_items_sold' => __( 'Net items sold', 'advanced-dashboard-for-woocommerce' ),
			) as $adwc_key => $adwc_label ) :
				?>
				<div><span><?php echo esc_html( $adwc_label ); ?></span><strong data-total="<?php echo esc_attr( $adwc_key ); ?>"></strong></div>
			<?php endforeach; ?>
		</div>
		<p id="adwc-chart-help" class="adwc-caption"><?php esc_html_e( 'Focus a chart and use Left/Right, Home/End to inspect days. Exact values are also available in the data table.', 'advanced-dashboard-for-woocommerce' ); ?></p>
		<?php
		foreach ( array(
			'net_revenue'    => __( 'Net sales', 'advanced-dashboard-for-woocommerce' ),
			'orders_count'   => __( 'Orders', 'advanced-dashboard-for-woocommerce' ),
			'num_items_sold' => __( 'Net items sold', 'advanced-dashboard-for-woocommerce' ),
		) as $adwc_key => $adwc_label ) :
			?>
			<section class="adwc-chart" data-metric="<?php echo esc_attr( $adwc_key ); ?>">
				<h3 id="adwc-title-<?php echo esc_attr( $adwc_key ); ?>"><?php echo esc_html( $adwc_label ); ?><span class="adwc-unit"></span></h3>
				<div class="adwc-plot" tabindex="0" role="group" aria-labelledby="adwc-title-<?php echo esc_attr( $adwc_key ); ?>" aria-describedby="adwc-chart-help">
					<svg aria-hidden="true" focusable="false"></svg>
				</div>
				<p class="adwc-point" aria-live="polite" aria-atomic="true"></p>
			</section>
		<?php endforeach; ?>
		<details class="adwc-table-details">
			<summary><?php esc_html_e( 'View daily data table', 'advanced-dashboard-for-woocommerce' ); ?></summary>
			<div class="adwc-table-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Daily sales data', 'advanced-dashboard-for-woocommerce' ); ?>">
				<table class="widefat striped">
					<caption><?php esc_html_e( 'Daily totals for the selected range', 'advanced-dashboard-for-woocommerce' ); ?></caption>
					<thead><tr><th scope="col"><?php esc_html_e( 'Date', 'advanced-dashboard-for-woocommerce' ); ?></th><th scope="col"><?php esc_html_e( 'Net sales', 'advanced-dashboard-for-woocommerce' ); ?></th><th scope="col"><?php esc_html_e( 'Orders', 'advanced-dashboard-for-woocommerce' ); ?></th><th scope="col"><?php esc_html_e( 'Net items sold', 'advanced-dashboard-for-woocommerce' ); ?></th></tr></thead>
					<tbody></tbody>
				</table>
			</div>
		</details>
		<p class="adwc-definition"><?php esc_html_e( 'By order creation date. Net sales exclude tax and shipping and reflect discounts and refunds.', 'advanced-dashboard-for-woocommerce' ); ?></p>
		<details class="adwc-method-details"><summary><?php esc_html_e( 'How these figures are calculated', 'advanced-dashboard-for-woocommerce' ); ?></summary>
		<p class="adwc-definition"><?php esc_html_e( 'By order creation date, not payment date. Net sales: after discounts and refunds, excluding tax and shipping; includes fees as defined by WooCommerce Analytics. Refund amounts and returned quantities are recorded on the refund creation day and may be negative. Orders count excludes refund records. Quantities follow Analytics refund records; amount-only refunds may not reduce item counts.', 'advanced-dashboard-for-woocommerce' ); ?></p>
		<p class="adwc-statuses"></p>
		<p class="adwc-caption adwc-snapshot" data-label="<?php esc_attr_e( 'Analytics snapshot read at', 'advanced-dashboard-for-woocommerce' ); ?>"></p>
		<p class="adwc-caption"><?php esc_html_e( 'Analytics may lag behind recent changes. If figures look incomplete, check synchronization and the historical import in Analytics settings.', 'advanced-dashboard-for-woocommerce' ); ?></p>
		</details>
	</div>
	<?php
	if ( ADWC_Operations::can_view() ) :
		$adwc_preferences = ADWC_Operations::preferences();
		?>
	<section class="adwc-operations" aria-labelledby="adwc-operations-title" data-endpoint="<?php echo esc_url( rest_url( 'advanced-dashboard-for-woocommerce/v1/operations' ) ); ?>" data-preferences="<?php echo esc_url( rest_url( 'advanced-dashboard-for-woocommerce/v1/operations/preferences' ) ); ?>">
		<h3 id="adwc-operations-title"><?php esc_html_e( 'Orders to review', 'advanced-dashboard-for-woocommerce' ); ?></h3>
		<div class="adwc-counts" hidden>
			<div><strong data-count="processing"></strong><span><?php esc_html_e( 'Processing', 'advanced-dashboard-for-woocommerce' ); ?></span></div>
			<div><strong data-count="on_hold"></strong><span><?php esc_html_e( 'On hold', 'advanced-dashboard-for-woocommerce' ); ?></span></div>
			<div><strong data-count="pending"></strong><span><?php esc_html_e( 'Pending payment', 'advanced-dashboard-for-woocommerce' ); ?></span></div>
		</div>
		<p class="adwc-caption"><?php esc_html_e( 'Counts cover all ages. The queue below uses your age threshold and selected statuses, independently of the chart date range.', 'advanced-dashboard-for-woocommerce' ); ?></p>
		<form class="adwc-preferences">
			<label for="adwc-age"><?php esc_html_e( 'Age threshold (hours)', 'advanced-dashboard-for-woocommerce' ); ?>
				<input id="adwc-age" name="age_hours" type="number" min="1" max="8760" step="1" required value="<?php echo esc_attr( $adwc_preferences['age_hours'] ); ?>">
			</label>
			<details>
				<summary><?php esc_html_e( 'Queue statuses', 'advanced-dashboard-for-woocommerce' ); ?></summary>
				<fieldset><legend class="screen-reader-text"><?php esc_html_e( 'Select up to 20 statuses, including custom statuses', 'advanced-dashboard-for-woocommerce' ); ?></legend>
					<?php foreach ( ADWC_Operations::available_statuses() as $adwc_status => $adwc_label ) : ?>
						<label><input type="checkbox" name="statuses[]" value="<?php echo esc_attr( $adwc_status ); ?>" 
						<?php
						if ( in_array( $adwc_status, $adwc_preferences['statuses'], true ) ) {
							echo 'checked'; }
						?>
						> <?php echo esc_html( $adwc_label ); ?></label>
					<?php endforeach; ?>
				</fieldset>
			</details>
			<button type="submit" class="button"><?php esc_html_e( 'Save my preferences', 'advanced-dashboard-for-woocommerce' ); ?></button>
		</form>
		<p class="adwc-caption"><?php esc_html_e( 'Age is measured from order creation, not time in the current status. Older than the threshold does not automatically mean an error or a late order.', 'advanced-dashboard-for-woocommerce' ); ?></p>
		<p class="adwc-operations-status" role="status" aria-live="polite" data-loading="<?php esc_attr_e( 'Loading orders…', 'advanced-dashboard-for-woocommerce' ); ?>" data-error="<?php esc_attr_e( 'Order information could not be loaded or saved. Try again.', 'advanced-dashboard-for-woocommerce' ); ?>" data-saved="<?php esc_attr_e( 'Your preferences have been saved.', 'advanced-dashboard-for-woocommerce' ); ?>" data-empty="<?php esc_attr_e( 'No editable orders match this age threshold and the selected statuses.', 'advanced-dashboard-for-woocommerce' ); ?>"></p>
		<p class="adwc-queue-description"></p>
		<details class="adwc-queue-details" hidden><summary data-label="<?php esc_attr_e( 'Review matching orders', 'advanced-dashboard-for-woocommerce' ); ?>"><?php esc_html_e( 'Review matching orders', 'advanced-dashboard-for-woocommerce' ); ?></summary><ol class="adwc-order-list" hidden></ol></details>
	</section>
	<?php endif; ?>
	<p class="adwc-settings"><a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-admin&path=/analytics/settings' ) ); ?>"><?php esc_html_e( 'Analytics settings and historical import', 'advanced-dashboard-for-woocommerce' ); ?></a></p>
</div>
