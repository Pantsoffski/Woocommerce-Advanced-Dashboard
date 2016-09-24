<?php
if (!defined('ABSPATH')) {
    exit; # Exit if accessed directly
}

//print_r(in_array( 'woocommerce/woocommerce.php', get_option( 'active_plugins' ) )); # Sprawdzanie aktywacji woocommerce
require_once(plugin_dir_path(__FILE__) . '/../woocommerce/includes/admin/reports/class-wc-admin-report.php');
require_once(plugin_dir_path(__FILE__) . '/../woocommerce/includes/admin/reports/class-wc-report-sales-by-date.php');

class Advanced_Dashboard_Admin_Init { # Initialization

    private static $initiated = false;

    public static function init() {
        if (!self::$initiated) {
            self::init_hooks();
        }
    }

    public static function init_hooks() {

        self::$initiated = true;

        add_action('admin_init', array('Advanced_Dashboard_Admin_Init', 'admin_init'));
        add_action('admin_menu', array('Advanced_Dashboard_Admin_Init', 'admin_menu'), 5); # Priority 5
        add_action('wp_dashboard_setup', array('Advanced_Dashboard_Admin_Init', 'wp_dashboard_setup'));
        add_action('admin_head-index.php', array('Advanced_Dashboard_Admin_Init', 'two_columns'));
    }

    public static function admin_init() {
        
    }

    public static function admin_menu() {
        
    }

    public static function two_columns() { # Two Columns Dashboard Layout
        add_screen_option('layout_columns', array('max' => 2, 'default' => 1));
    }

    public static function wp_dashboard_setup() {
        wp_add_dashboard_widget('advanced-dashboard-admin-widget', 'Woocommerce Advanced Dashboard', array('Advanced_Dashboard_View', 'advanced_dashboard_draw'));
    }

}

class Advanced_Dashboard_View { # Dashboard view

    public static function advanced_dashboard_draw() {

        $advanced_dashboard_call_month = Advanced_Dashboard_Calls::advanced_dashboard_call('month'); # Month interval
        $advanced_dashboard_call_day = Advanced_Dashboard_Calls::advanced_dashboard_call('day'); # 1 Day interval
        ?>
        <ul>
            <li>
                <?php printf(__("<strong>%s</strong> net sales this month", 'woocommerce'), wc_price($advanced_dashboard_call_month[1]->net_sales)); ?>
            </li>
            <li>
                <?php printf(__("<strong>%s</strong> net sales this day", 'woocommerce'), wc_price($advanced_dashboard_call_day[1]->net_sales)); ?>
            </li>
        </ul>
        <?php
    }

}

class Advanced_Dashboard_Calls {

    public static function advanced_dashboard_call($interval) {
        $reports = new WC_Admin_Report();
        $sales_by_date = new WC_Report_Sales_By_Date();
        if ($interval == 'month') {
            $sales_by_date->start_date = strtotime(date('Y-m-01', current_time('timestamp')));
        } elseif ($interval == 'day') {
            $sales_by_date->start_date = current_time('timestamp');
        }
        $sales_by_date->end_date = current_time('timestamp');
        $sales_by_date->chart_groupby = 'day';
        $sales_by_date->group_by_query = 'YEAR(posts.post_date), MONTH(posts.post_date), DAY(posts.post_date)';
        $report_data = $sales_by_date->get_report_data();

        return array($reports, $report_data);
    }

}
