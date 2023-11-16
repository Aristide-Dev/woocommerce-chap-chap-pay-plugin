<?php

/**
 * Plugin Name: Chap Chap Pay
 * Plugin URI:  https://www.chapchappay.com/
 * Author:      CHAP CHAP PAY S.A
 * Author URI:  https://www.chapchappay.com/
 * Description: Allow users to pay using their Orange Money, MTN Mobile,PayCard or Visa/Master Card.
 * Version:     1.0.1
 * License:     GPL-2.0+
 * License URL: http://www.gnu.org/licenses/gpl-2.0.txt
 * text-domain: chap-chap-pay
 * @package Woocommerce/ccpay
 */

if (!defined('ABSPATH')) {
    exit;
}

//if condition use to do nothin while WooCommerce is not installed
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) return;

add_action('plugins_loaded', 'chap_chap_pay_init', 11);

function chap_chap_pay_init()
{
    //if condition use to do nothin while WooCommerce is not installed
    if (!class_exists('WC_Payment_Gateway')) return;

    include_once('includes/ccp-gateway.php');
    // class add it too WooCommerce
    add_filter('woocommerce_payment_gateways', 'add_chap_chap_pay_gateway');
    function add_chap_chap_pay_gateway($methods)
    {
        $methods[] = 'WC_Gateway_Chap_Chap_Pay';
        return $methods;
    }
}

// Add custom action links
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wc_gateway_ccpay_action_links');
function wc_gateway_ccpay_action_links($links)
{
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . __('Settings', 'cwoa-chapcha_pay-aim') . '</a>',
    );
    return array_merge($plugin_links, $links);
}
