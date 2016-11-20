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
        add_action('admin_head', array('Advanced_Dashboard_Chart_Scripts', 'advanced_dashboard_chart_head1')); # Script1 to admin head
        add_action('admin_head', array('Advanced_Dashboard_Chart_Scripts', 'advanced_dashboard_chart_head2')); # Script2 to admin head
        add_action('admin_enqueue_scripts', array('Advanced_Dashboard_Chart_Scripts', 'advanced_dashboard_chart_custom_script')); # Custom script to admin head
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

    public static function advanced_dashboard_draw() { # Graph call
        $advanced_dashboard_call_month = Advanced_Dashboard_Call_And_Chart::advanced_dashboard_call('month'); # Month interval
        $advanced_dashboard_call_day = Advanced_Dashboard_Call_And_Chart::advanced_dashboard_call('day'); # 1 Day interval
        ?>
        <form id="form1">
            <input type="hidden" id="gifDir" value="<?php echo plugins_url('cat_loading.gif', __FILE__) ?>" />
            <input type="date" name="startdate" class="datepicker" id="startdate" />
            <input type="date" name="enddate" class="datepicker" id="enddate" />
            <input type="button" onclick="change_data_ad()" value="Apply" id="submitdates" />
        </form>
        <ul id="advanced-dashboard-ul">
            <li>
                <div><?php echo Advanced_Dashboard_Call_And_Chart::advanced_dashboard_chart1_view(); ?></div>
                <div><?php printf(__("<strong>%s</strong> net sales this month", 'woocommerce'), wc_price($advanced_dashboard_call_month[1]->net_sales)); ?></div>
                <?php printf(__("<strong>%s</strong> net sales this day", 'woocommerce'), wc_price($advanced_dashboard_call_day[1]->net_sales)); ?>
            </li>
            <li>
                <div><?php echo Advanced_Dashboard_Call_And_Chart::advanced_dashboard_chart2_view(); ?></div>
            </li>
            <?php
            if (!current_user_can('edit_shop_orders')) {
                return;
            }
            $on_hold_count = 0;
            $processing_count = 0;

            foreach (wc_get_order_types('order-count') as $type) {
                $counts = (array) wp_count_posts($type);
                $on_hold_count += isset($counts['wc-on-hold']) ? $counts['wc-on-hold'] : 0;
                $processing_count += isset($counts['wc-processing']) ? $counts['wc-processing'] : 0;
            }
            ?>
            <li class="processing-orders">
                <a href="<?php echo admin_url('edit.php?post_status=wc-processing&post_type=shop_order'); ?>">
                    <?php printf(_n("<strong>%s order</strong> awaiting processing", "<strong>%s orders</strong> awaiting processing", $processing_count, 'woocommerce'), $processing_count); ?>
                </a>
            </li>
            <li class="on-hold-orders">
                <a href="<?php echo admin_url('edit.php?post_status=wc-on-hold&post_type=shop_order'); ?>">
                    <?php printf(_n("<strong>%s order</strong> on-hold", "<strong>%s orders</strong> on-hold", $on_hold_count, 'woocommerce'), $on_hold_count); ?>
                </a>
            </li>

        </ul>
        <?php
    }

}

class Advanced_Dashboard_Call_And_Chart {

    public static function advanced_dashboard_call($interval) { # Returns SQL data, sales value, data, number of orders
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

    public static function advanced_dashboard_chart_loop_value_orders() { # Loop for value and number of orders chart
        $advanced_dashboard_call_month = self::advanced_dashboard_call('month'); # Month interval
        $date = $advanced_dashboard_call_month[1]->orders[0]->post_date;
        $formatDate = DateTime::createFromFormat('Y-m-d H:i:s', $date);
        $yearForNumber = $formatDate->format('Y');
        $monthForNumber = $formatDate->format('m');
        $numberOfDays = cal_days_in_month(CAL_GREGORIAN, $monthForNumber, $yearForNumber);
        $JStableValue = array(); # For days with orders

        for ($i = 0; $i <= count($advanced_dashboard_call_month[1]->orders); ++$i) {
            $value = $advanced_dashboard_call_month[1]->orders[$i]->post_date; #Dates of orders
            $orderCount = $advanced_dashboard_call_month[1]->order_counts[$i]->count; #Number of orders in each day
            if (isset($value)) {
                $valueFormat = DateTime::createFromFormat('Y-m-d H:i:s', $value);
                $year = $valueFormat->format('Y');
                $month = $valueFormat->format('m');
                $day = $valueFormat->format('j');
                $valueSet = $year . "/" . $month . "/" . $day;
                $JStableValue[$i]['date'] = $valueSet;
                $JStableValue[$i]['value'] = $advanced_dashboard_call_month[1]->orders[$i]->total_sales;
                $JStableValue[$i]['orders'] = $orderCount;
            }
        }

        $JStable = self::advanced_dashboard_zero_days($numberOfDays, $JStableValue, $formatDate, $type = 'value'); # Add days with no orders to chart

        foreach ($JStable as $JSdata) {
            $valueFormat = DateTime::createFromFormat('Y/m/j', $JSdata['date']);
            $year = $valueFormat->format('Y');
            $month = $valueFormat->format('m') - 1;
            $day = $valueFormat->format('j');
            $valueSet = $year . ", " . $month . ", " . $day;
            echo "[new Date(" . $valueSet . "), " . $JSdata['value'] . ", " . $JSdata['orders'] . "],";
        }
    }

    public static function advanced_dashboard_chart_loop_qty() {
        $qtyData = self::advanced_dashboard_sql_qty(); # SQL qty data miner
        $formatDate = DateTime::createFromFormat('Y-m-d', $qtyData[0]->date);
        $yearForNumber = $formatDate->format('Y');
        $monthForNumber = $formatDate->format('m');
        $numberOfDays = cal_days_in_month(CAL_GREGORIAN, $monthForNumber, $yearForNumber);
        $JStableValue = array(); # For days with qty
        $i = 0;
        foreach ($qtyData as $JSdataQty) { # Poulate chart with non-zero qty
            $formatDateLoop = DateTime::createFromFormat('Y-m-d', $JSdataQty->date);
            $year = $formatDateLoop->format('Y');
            $month = $formatDateLoop->format('m');
            $day = $formatDateLoop->format('j');
            $valueSet = $year . "/" . $month . "/" . $day;
            $JStableValue[$i]['date'] = $valueSet;
            $JStableValue[$i]['qty'] = $JSdataQty->qty;
            ++$i;
        }

        $JStable = self::advanced_dashboard_zero_days($numberOfDays, $JStableValue, $formatDate, $type = 'qty'); # Add days with no orders to chart
        //print_r($JStable);
        foreach ($JStable as $JSdataQty) {
            $formatDate = DateTime::createFromFormat('Y/m/d', $JSdataQty['date']);
            $year = $formatDate->format('Y');
            $month = $formatDate->format('m') - 1;
            $day = $formatDate->format('j');
            $valueSet = $year . ", " . $month . ", " . $day;
            echo "[new Date(" . $valueSet . "), " . $JSdataQty['qty'] . "],";
        }
    }

    public static function advanced_dashboard_sql_qty() { # SQL data miner
        global $wpdb;

        $query = array();
        $query['fields'] = "SELECT SUM({$wpdb->prefix}woocommerce_order_itemmeta.meta_value) AS qty, DATE({$wpdb->prefix}posts.post_date) AS date
			FROM  {$wpdb->prefix}woocommerce_order_itemmeta";
        $query['join'] = "INNER JOIN {$wpdb->prefix}woocommerce_order_items ON {$wpdb->prefix}woocommerce_order_itemmeta.order_item_id = {$wpdb->prefix}woocommerce_order_items.order_item_id ";
        $query['join'] .= "INNER JOIN {$wpdb->prefix}posts ON {$wpdb->prefix}woocommerce_order_items.order_id = {$wpdb->prefix}posts.ID ";
        $query['where'] = "WHERE {$wpdb->prefix}posts.post_status IN ( 'wc-" . implode("','wc-", array('completed', 'processing', 'on-hold')) . "' ) ";
        $query['where'] = "AND {$wpdb->prefix}woocommerce_order_itemmeta.meta_key = '_qty' ";
        $query['where'] .= "AND {$wpdb->prefix}posts.post_date >= '" . date('Y-m-01', current_time('timestamp')) . "' ";
        $query['where'] .= "AND {$wpdb->prefix}posts.post_date <= '" . date('Y-m-d H:i:s', current_time('timestamp')) . "' ";
        $query['groupby'] = "GROUP BY date ";
        $query['orderby'] = "ORDER BY date ASC";

        $qtyData = $wpdb->get_results(implode(' ', $query));

        return $qtyData;
    }

    public static function advanced_dashboard_zero_days($numberOfDays, $JStableValue, $formatDate, $type) { # Show zero days on chart (days without orders)
        if (!function_exists('in_array_r')) { # Avoid redeclaring function

            function in_array_r($needle, $haystack, $strict = false) { # Remove duplicate dates from "no value" array
                foreach ($haystack as $item) {
                    if (($strict ? $item === $needle : $item == $needle) || (is_array($item) && in_array_r($needle, $item, $strict))) {
                        return true;
                    }
                }

                return false;
            }

        }

        $JStableNoValue = array(); # For empty days, without orders

        for ($i = 1; $i <= $numberOfDays; ++$i) {
            $year = $formatDate->format('Y');
            $month = $formatDate->format('m');
            $day = $i;
            $valueSet = $year . "/" . $month . "/" . $day;
            if (!in_array_r($valueSet, $JStableValue) && $type == 'value') {
                $JStableNoValue[$i]['date'] = $valueSet;
                $JStableNoValue[$i]['value'] = 0;
                $JStableNoValue[$i]['orders'] = 0;
            }
            if (!in_array_r($valueSet, $JStableValue) && $type == 'qty') {
                $JStableNoValue[$i]['date'] = $valueSet;
                $JStableNoValue[$i]['qty'] = 0;
            }
        }

        $JStable = array_merge($JStableValue, $JStableNoValue);

        if (!function_exists('date_compare')) { # Avoid redeclaring function

            function date_compare($a, $b) {
                return strtotime($a['date']) - strtotime($b['date']);
            }

        }

        usort($JStable, 'date_compare');

        return $JStable;
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

class Advanced_Dashboard_Chart_Scripts { # Google Charts JS

    public static function advanced_dashboard_chart_head1() { # Sales & orders chart script
        ?>

        <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
        <script type="text/javascript">
                google.charts.load('current', {'packages': ['corechart', 'line']});
                google.charts.setOnLoadCallback(drawChart);

                function drawChart() {
                    var data = new google.visualization.DataTable();
                    data.addColumn('date', 'Date');
                    data.addColumn('number', 'Sales');
                    data.addColumn('number', 'Orders');

                    data.addRows([
        <?php
        $advanced_dashboard_call_month = Advanced_Dashboard_Call_And_Chart::advanced_dashboard_call('month');
        if ($advanced_dashboard_call_month[1]->orders[0]->post_date) { # If there is any orders start data loop
            Advanced_Dashboard_Call_And_Chart::advanced_dashboard_chart_loop_value_orders();
        }
        ?>
                    ]);
                    var options = {
                        series: {
                            0: {targetAxisIndex: 0, lineWidth: 5, color: '#FFD700'},
                            1: {targetAxisIndex: 1}
                        },
                        hAxis: {
                            title: 'Time'
                        },
                        vAxis: {
                            0: {title: 'Value'},
                            1: {title: 'Orders'}
                        },
                        title: 'This Month Sales and Orders',
                        legend: {position: 'bottom'}
                    };

                    var formatter = new google.visualization.NumberFormat(
                            {pattern: '###,###', suffix: " zł"});
                    formatter.format(data, 1);

                    var chart1 = new google.visualization.LineChart(document.getElementById('chart1'));

                    chart1.draw(data, options);
                }
        </script>

        <?php
    }

    public static function advanced_dashboard_chart_head2() { # Qty chart script
        ?>

        <script type="text/javascript">
            google.charts.load('current', {'packages': ['corechart', 'line']});
            google.charts.setOnLoadCallback(drawChart);

            function drawChart() {
                var data2 = new google.visualization.DataTable();
                data2.addColumn('date', 'Date');
                data2.addColumn('number', 'Qty');

                data2.addRows([
        <?php
        $advanced_dashboard_call_month = Advanced_Dashboard_Call_And_Chart::advanced_dashboard_call('month');
        if ($advanced_dashboard_call_month[1]->orders[0]->post_date) { # If there is any orders start data loop
            Advanced_Dashboard_Call_And_Chart::advanced_dashboard_chart_loop_qty();
        }
        ?>
                ]);
                var options2 = {
                    hAxis: {
                        title: 'Time'
                    },
                    vAxis: {
                        title: 'Quantity'
                    },
                    lineWidth: 5,
                    color: 'blue',
                    title: 'This Month Quantity Sold',
                    legend: {position: 'bottom'}
                };

                var chart2 = new google.visualization.LineChart(document.getElementById('chart2'));

                chart2.draw(data2, options2);
            }
        </script>

        <?php
    }

    public static function advanced_dashboard_chart_custom_script() { # Custom script to header
        wp_enqueue_script('jquery-ui-datepicker');
        wp_register_style('jquery-ui', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/base/jquery-ui.css');
        wp_enqueue_style('jquery-ui');
        wp_enqueue_script('custom-js', plugins_url('scripts.js', __FILE__));
    }

}
