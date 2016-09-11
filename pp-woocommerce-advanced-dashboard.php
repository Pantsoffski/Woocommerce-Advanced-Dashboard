<?php

/*
 * Plugin Name: Woocommerce Advanced Dashboard
 * Plugin URI: http://ordin.pl/
 * Description: Plugin that gives you e.g. raports about sales in Wordpress dashboard.
 * Author: Piotr Pesta
 * Version: 0.1 
 * Author URI: http://ordin.pl/
 * License: GPL12
 * Text Domain: woocommerce-advanced-dashboard
 */

define( 'ADVANCED_DASHBOARD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

if ( is_admin() ) {
	require_once( ADVANCED_DASHBOARD_PLUGIN_DIR . 'class.woocommerce-advanced-dashboard-admin.php' );
	add_action( 'init', array( 'Advanced_Dashboard_Admin', 'init' ) );
}

add_action('wp_dashboard_setup', 'pp_advanced_dashboard');