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
        add_action('admin_head', array('Advanced_Dashboard_Call_And_Chart', 'advanced_dashboard_chart_head')); # Script to admin head
    }

    public static function admin_init() {
        wp_enqueue_style('advanced-dashboard-admin-style', plugins_url('advanced-dashboard-style.css', __FILE__));
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

        $advanced_dashboard_call_month = Advanced_Dashboard_Call_And_Chart::advanced_dashboard_call('month'); # Month interval
        $advanced_dashboard_call_day = Advanced_Dashboard_Call_And_Chart::advanced_dashboard_call('day'); # 1 Day interval
        ?>
        <ul>
            <li>
                <div><?php //echo Advanced_Dashboard_Call_And_Chart::advanced_dashboard_chart1_view();    ?></div>
                <div><?php printf(__("<strong>%s</strong> net sales this month", 'woocommerce'), wc_price($advanced_dashboard_call_month[1]->net_sales)); ?></div>
            </li>
            <li>
                <?php //echo Advanced_Dashboard_Call_And_Chart::advanced_dashboard_chart2_view(); ?>
                <?php printf(__("<strong>%s</strong> net sales this day", 'woocommerce'), wc_price($advanced_dashboard_call_day[1]->net_sales)); ?>
                <?php echo "<br/>"; ?>
                <?php
                //print_r($advanced_dashboard_call_month[1]->order_counts);
                foreach ($advanced_dashboard_call_month[1]->orders as $order_dates) {
                    echo print_r($order_dates->post_date) . "<br/>";
                }
                foreach ($advanced_dashboard_call_month[1]->orders as $order_dates) {
                    echo print_r($order_dates->total_sales) . "<br/>";
                }
                ?>
            </li>
        </ul>
        <?php
    }

}

class Advanced_Dashboard_Call_And_Chart {

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

    public static function advanced_dashboard_chart_head() { # Chart script
        ?>

        <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
        <script type="text/javascript">
            google.charts.load('current', {'packages': ['line']});
            google.charts.setOnLoadCallback(drawChart);

            function drawChart() {

                var data = new google.visualization.DataTable();
                data.addColumn('number', 'Day');
                data.addColumn('number', 'Guardians of the Galaxy');
                data.addColumn('number', 'The Avengers');
                data.addColumn('number', 'Transformers: Age of Extinction');

                data.addRows([
                    [1, 37.8, 80.8, 41.8],
                    [2, 30.9, 69.5, 32.4],
                    [3, 25.4, 57, 25.7],
                    [4, 11.7, 18.8, 10.5],
                    [5, 11.9, 17.6, 10.4],
                    [6, 8.8, 13.6, 7.7],
                    [7, 7.6, 12.3, 9.6],
                    [8, 12.3, 29.2, 10.6],
                    [9, 16.9, 42.9, 14.8],
                    [10, 12.8, 30.9, 11.6],
                    [11, 5.3, 7.9, 4.7],
                    [12, 6.6, 8.4, 5.2],
                    [13, 4.8, 6.3, 3.6],
                    [14, 4.2, 6.2, 3.4]
                ]);

                var options = {
                    chart: {
                        title: 'Box Office Earnings in First Two Weeks of Opening',
                        subtitle: 'in millions of dollars (USD)'
                    },
                    width: 900,
                    height: 500
                };

                var chart1 = new google.charts.Line(document.getElementById('chart1'));
                var chart2 = new google.charts.Line(document.getElementById('chart2'));

                chart1.draw(data, options);
                chart2.draw(data, options);
            }
        </script>

        <?php
    }

    public static function advanced_dashboard_chart1_view() { # Div of chart1 hook
        ?>

        <div id="chart1"></div>

        <?php
    }

    public static function advanced_dashboard_chart2_view() { # Div of chart2 hook
        ?>

        <div id="chart2"></div>

        <?php
    }

}
