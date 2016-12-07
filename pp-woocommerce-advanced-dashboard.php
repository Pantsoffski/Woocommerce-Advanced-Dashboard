<?php

/*
 * Plugin Name: WooCommerce Advanced Dashboard
 * Plugin URI: http://ordin.pl/
 * Description: Plugin that gives you e.g. raports about sales in Wordpress dashboard.
 * Author: Piotr Pesta
 * Version: 0.2.3
 * Author URI: http://ordin.pl/
 * License: GPL12
 * Text Domain: woocommerce-advanced-dashboard
 */

define('ADVANCED_DASHBOARD_PLUGIN_DIR', plugin_dir_path(__FILE__));

//register_activation_hook(__FILE__, array('Woocommerce Advanced Dashboard', 'plugin_activation'));
//register_deactivation_hook(__FILE__, array('Woocommerce Advanced Dashboard', 'plugin_deactivation'));

add_action('plugins_loaded', 'pp_advanced_dashboard_main_init');

function pp_advanced_dashboard_main_init() {
    if (is_admin()) {
        require_once( ADVANCED_DASHBOARD_PLUGIN_DIR . 'classes.php' );
        add_action('init', array('Advanced_Dashboard_Admin_Init', 'init'));
    }
}

//add_action('wp_dashboard_setup', 'pp_advanced_dashboard');