<?php
if (!defined('ABSPATH')) {
    exit; # Exit if accessed directly
}

class Advanced_Dashboard_Admin {

    private static $initiated = false;

    public static function init() {
        if (!self::$initiated) {
            self::init_hooks();
        }
    }

    public static function init_hooks() {

        self::$initiated = true;

        add_action('admin_init', array('Advanced_Dashboard_Admin', 'admin_init'));
        add_action('admin_menu', array('Advanced_Dashboard_Admin', 'admin_menu'), 5); # Priority 5
        add_action('wp_dashboard_setup', array('Advanced_Dashboard_Admin', 'wp_dashboard_setup'));
        add_action('admin_head-index.php', array('Advanced_Dashboard_Admin', 'two_columns'));
    }

    public static function admin_init() {
        
    }

    public static function admin_menu() {
        
    }

    public static function two_columns() { # Two Columns Dashboard Layout
        add_screen_option('layout_columns', array('max' => 2, 'default' => 1));
    }

    public static function wp_dashboard_setup() {
        wp_add_dashboard_widget('advanced-dashboard-admin-widget', 'Advanced Dashboard Admin', array('Advanced_Dashboard_Admin', 'advanced_dashboard_admin_main'));
    }

    public static function advanced_dashboard_admin_main() {
        $current_user = wp_get_current_user();
        echo 'Username: ' . $current_user->user_login . '<br />';
        //print_r(in_array( 'woocommerce/woocommerce.php', get_option( 'active_plugins' ) )); # Sprawdzanie aktywacji woocommerce
        include_once('/../woocommerce/includes/admin/reports/class-wc-admin-report.php');
        include_once('/../woocommerce/includes/admin/reports/class-wc-report-sales-by-date.php');
        $reports = new WC_Admin_Report();
        $sales_by_date = new WC_Report_Sales_By_Date();
        $report_data = $sales_by_date->get_report_data();
        ?>
        <ul class="wc_status_list">
            <li class="sales-this-month">
                <a href="<?php echo admin_url('admin.php?page=wc-reports&tab=orders&range=month'); ?>">
                    <?php echo $reports->sales_sparkline('', max(7, date('d', current_time('timestamp')))); ?>
                    <?php printf(__("<strong>%s</strong> net sales this month", 'woocommerce'), wc_price($report_data->net_sales)); ?>
                </a>
            </li>
        </ul>
        <?php
    }

}
