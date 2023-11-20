<?php

/**
 * Class WC_Gateway_Chap_Chap_Pay file.
 *
 * @package WooCommerce\Gateways
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Chap Chap Pay Gateway.
 *
 * Provides a Chap Chap Pay Gateway.
 *
 * @class       WC_Gateway_Chap_Chap_Pay
 * @extends     WC_Payment_Gateway
 * @version     2.1.0
 * @package     WooCommerce\Classes\Payment
 */
class WC_Gateway_Chap_Chap_Pay extends WC_Payment_Gateway
{

    /**
     * Gateway instructions that will be added to the thank you page and emails.
     *
     * @var string
     */
    public $instructions;

    /**
     * CCPAY Merchand ID.
     *
     * @var array
     */
    public $api_login;

    /**
     * Notify URI.
     *
     * @var string
     */
    public $notify_url;

    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {
        // Setup general properties.
        $this->setup_properties();

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Get settings.
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->instructions = $this->get_option('instructions');
        $this->api_login = $this->get_option('api_login');

        // Actions.
        if (is_admin()) {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }

        add_action('admin_notices', array($this, 'do_ssl_check'));
        add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'check_response'));
        add_filter('woocommerce_payment_complete_order_status', array($this, 'change_payment_complete_order_status'), 10, 3);
        add_action('woocommerce_before_thankyou', array($this, 'thankyou_page_content'));
    }

    /**
     * Setup general properties for the gateway.
     */
    protected function setup_properties()
    {
        $this->id = 'chap_chap_pay';
        $this->icon = apply_filters('woocommerce_chap_chap_pay_icon', plugins_url('/assets/pay.png', __DIR__));
        $this->method_title = __('Chap Chap Pay', 'chap_chap_pay');
        $this->method_description = wpautop(__(
            "Accept payment using CHAP CHAP PAY.
                This gateway supports the following CURRENCIES only : <b> GNF</b>.
                And the following PAYMEMNT METHODES only : <b>ORANGE MONEY</b>, <b>MTN MOBILE MONEY</b>, <b>PAYCARD</b>, <b>Visa & Master Card</b>,",
            'chap_chap_pay'
        ));
        $this->order_button_text = __('Payez', 'chap_chap_pay');
        $this->has_fields = true;
        $this->notify_url = WC()->api_request_url('WC_Gateway_Chap_Chap_Pay');
        $this->supports = array(
            'products',
        );
    }

    /**
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Activer/Desactiver', 'chap_chap_pay'),
                'label' => __('Activer Chap Chap Pay', 'chap_chap_pay'),
                'type' => 'checkbox',
                'description' => __('Activer ou Desactiver chap chap pay', 'chap_chap_pay'),
                'default' => 'no',
            ),
            'title' => array(
                'title' => __('Title', 'chap_chap_pay'),
                'type' => 'safe_text',
                'description' => __('Le titre du moyen de paiement sur la page checkout.', 'chap_chap_pay'),
                'default' => __('Chap Chap Pay', 'chap_chap_pay'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'chap_chap_pay'),
                'type' => 'textarea',
                'description' => __('La description qui sera affiché sur la page checkout', 'chap_chap_pay'),
                'default' => __('Payer facilement par Orange Money, MTN Mobile, PayCard, Visa/Master Card. <a href="https://www.paycard.co" target="_blank">En savoir plus</a>', 'chap_chap_pay'),
                'desc_tip' => true,
            ),
            'instructions' => array(
                'title' => __('Instructions sur la page de paiement', 'chap_chap_pay'),
                'type' => 'textarea',
                'description' => __('Le message qui sera affiché sur la page de paiement', 'chap_chap_pay'),
                'default' => __('Merci d\'avoir choisi Chap Chap Pay comme méthode de paiement.', 'chap_chap_pay'),
                'desc_tip' => true,
            ),
            'api_login' => array(
                'title' => __('Code E-Commerce', 'chap_chap_pay'),
                'type' => 'text',
                'desc_tip' => __('Votre code E-Commerce est visible en vous connectant sur https://www.paycard.co', 'chap_chap_pay'),
            ),
        );
    }

    public function do_ssl_check()
    {
        if ($this->enabled == "yes") {
            if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
                return;
            }

            if (get_option('woocommerce_force_ssl_checkout') == "no") {
                echo "<div class=\"error\"><p>" . sprintf(__("<strong>%s</strong> est activé et WooCommerce ne force pas le certificat SSL sur votre page de paiement. Veuillez vous assurer d'avoir un certificat SSL valide et que vous <a href=\"%s\">forcez la sécurisation des pages de paiement.</a>"), $this->method_title, admin_url('admin.php?page=wc-settings&tab=checkout')) . "</p></div>";
            }
        }
    }

    /**
     * Process the payment and return the result.
     *
     * @param int $order_id Order ID.
     * @return array
     */
    // public function process_payment($order_id)
    // {
    //     global $woocommerce;

    //     $customer_order = new WC_Order($order_id);

    //     $paycard_epay_url = "https://paycard.co/epay/";
    //     // $paycard_epay_url = "http://localhost:8080/epay/";

    //     $data = array(
    //         'order_id' => $order_id,
    //         'c' => $this->api_login,
    //         'paycard-amount' => $customer_order->order_total,
    //         'paycard-description' => 'Paiement ' . get_bloginfo('name') . ' - Commande : ' . $customer_order->get_order_number(),
    //         'paycard-callback-url' => $this->notify_url,
    //         'paycard-jump-to-paycard' => $this->paycard_jump_to_paycard == 'yes' ? 'on' : 'off',
    //     );

    //     return array(
    //         'result'   => 'success',
    //         'redirect' => $paycard_epay_url . '?' . http_build_query($data),
    //     );
    // }

    public function process_payment($order_id)
    {
        global $woocommerce;

        $customer_order = new WC_Order($order_id);

        /**
         * V1
         * @var string
         */

        // $paycard_epay_url_v1 = "https://paycard.co/epay/";
        $paycard_epay_url = "https://paycard.co/epay/create/";

        $get_payment_link_data = array(
            'c' => $this->api_login,
            'paycard-amount' => $customer_order->order_total,
        );

        $data = array(
            'order_id' => $order_id,
            'c' => $this->api_login,
            'paycard-amount' => $customer_order->order_total,
            'paycard-description' => 'Paiement ' . get_bloginfo('name') . ' - Commande : ' . $customer_order->get_order_number(),
            'paycard-callback-url' => $this->notify_url,
        );

        $response = wp_remote_request($paycard_epay_url, array(
            'method' => 'POST',
            'body' => $data,
        ));

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            echo "Something went wrong: $error_message";
            return;
        }

        // Check if the response is successful.
        $body = wp_remote_retrieve_body($response);
        $response_data = json_decode($body, true);

        if ($response_data && isset($response_data['code']) && $response_data['code'] === 0) {
            // Success
            return array(
                'result' => 'success',
                'redirect' => $response_data['payment_url'],
            );
        } else {
            // Failure
            echo "Payment processing failed. Details: " . json_encode($response_data);
            return;
        }
    }

    // public function payment_fields()
    // {
    //     global $woocommerce;

    //     $order_id = $woocommerce->session->order_awaiting_payment;
    //     $customer_order = wc_get_order($order_id);

    //     // Traitement des erreurs
    //     if (!$customer_order) {
    //         echo '<p style="background-color: red; color:white; padding:5px">' . __('Impossible de traiter la commande.', 'chap_chap_pay') . '</p>';
    //         return;
    //     }

    //     // Récupérer les données nécessaires pour le formulaire
    //     $api_login = $this->api_login;
    //     $paycard_epay_url = "https://mapaycard.com/epay/";
    //     $amount = $customer_order->get_total();
    //     $description = $this->description;

    //     // Construire le formulaire avec les données
    //     echo '<form action="' . esc_url($paycard_epay_url) . '" method="POST" id="chap-chap-pay-custom-form" class="chap-chap-pay-form">';
    //     echo '<input type="hidden" name="order_id" value="' . esc_attr($order_id) . '">';
    //     echo '<input type="hidden" name="c" value="' . esc_attr($api_login) . '">';
    //     echo '<input type="hidden" name="paycard-amount" value="' . esc_attr($amount) . '">';
    //     echo '<input type="hidden" name="paycard-description" value="' . esc_attr($description) . '">';
    //     echo '<input type="hidden" name="paycard-callback-url" value="' . esc_attr($this->notify_url) . '">';
    //     echo '<input type="hidden" name="paycard-jump-to-paycard" value="' . (esc_attr($this->paycard_jump_to_paycard) == "yes" ? "on" : "off") . '">';
    //     echo '<input type="submit" value="' . esc_attr__('Payez', 'chap_chap_pay') . '" style="width: 100%; box-sizing: border-box;">';

    //     echo '</form>';

    //     // JavaScript pour soumettre automatiquement le formulaire après le chargement de la page
    //     echo '<script type="text/javascript">
    //     document.addEventListener("DOMContentLoaded", function() {
    //         console.log("Formulaire Soumis");
    //         document.getElementById("chap-chap-pay-custom-form").submit();
    //     });
    // </script>';
    // }

    /**
     * Change payment complete order status to completed for chap_chap_pay orders.
     *
     * @since  3.1.0
     * @param  string         $status Current order status.
     * @param  int            $order_id Order ID.
     * @param  WC_Order|false $order Order object.
     * @return string
     */
    public function change_payment_complete_order_status($status, $order_id = 0, $order = false)
    {
        if ($order && 'chap_chap_pay' === $order->get_payment_method()) {
            $status = 'completed';
        }
        return $status;
    }

    /**
     * Check Response for PDT.
     */
    public function check_response()
    {
        global $woocommerce;
        $redirect_url = wc_get_checkout_url();

        if (empty($_REQUEST['transactionReference']) || empty($_REQUEST['montant']) || empty($_REQUEST['c'])) {
            wc_add_notice(__('Les données de paiement sont incomplètes.', 'chap_chap_pay'), 'error');
        } else {
            $order_id = $_REQUEST["order_id"];
            $transactionReference = $_REQUEST['transactionReference'];
            $transactionAmount = $_REQUEST['montant'];
            $eCommerceCode = $_REQUEST['c'];
            $paymentMethod = $_REQUEST['paycardPaymentMethod'];
            $paymentStatus = $_REQUEST['status'] ?? 'success';

            $order = wc_get_order($order_id);

            if (!$order) {
                wc_add_notice(__('La commande associée n\'existe pas', 'chap_chap_pay'), 'error');
            } else {

                if (!$order->has_status('pending')) {
                    wc_add_notice(__('La commande associée n\'est pas en attente de paiement.', 'chap_chap_pay'), 'error');
                }

                if ($eCommerceCode !== $this->api_login) {
                    $order->add_order_note(__("ERROR: Le code E-Commerce venant de Chap Chap Pay ne correspond pas au code E-Commerce configuré", 'woocommerce'));
                    wc_add_notice(__('Le code E-Commerce ne correspond pas.', 'chap_chap_pay'), 'error');
                }

                $areAmountsSame = abs(($order->get_total() - floatval($transactionAmount)) / floatval($transactionAmount)) < 0.001;

                if (!$areAmountsSame) {
                    $order->update_status('on-hold', 'Le montant du paiement [' . $transactionAmount . '] ne correspond pas au montant de la commande [' . $order->get_total() . ']. Ref : ' . $transactionReference . ', montant : ' . $transactionAmount . '.');
                    $order->add_order_note(__('Erreur : Le montant du paiement [' . $transactionAmount . '] ne correspond pas au montant de la commande [' . $order->get_total() . ']. Ref : ' . $transactionReference . ', montant : ' . $transactionAmount . '.', 'woocommerce'));
                    wc_add_notice(__('Le montant du paiement ne correspond pas à celui de la commande.', 'chap_chap_pay'), 'error');
                } else {
                    // Ajouter des informations supplémentaires à la commande
                    update_post_meta($order_id, 'Chap Chap Pay Transaction Reference', $transactionReference);
                    update_post_meta($order_id, 'Chap Chap Pay Payment Method', $paymentMethod);
                    update_post_meta($order_id, 'Chap Chap Pay Payment Status', $paymentStatus);

                    $order->payment_complete($transactionReference);
                    $order->add_order_note(__('Payé avec Chap chap Pay via ' . $paymentMethod . 'Ref : ' . $transactionReference . ', montant : ' . $transactionAmount . '.', 'woocommerce'));

                    // Réduire les niveaux de stock
                    $order->reduce_order_stock();

                    // Vider le panier
                    $woocommerce->cart->empty_cart();

                    // wc_add_notice(__('Le paiement a été effectué avec succès.', 'chap_chap_pay'), 'success');
                    $redirect_url = $this->get_return_url($order);
                }
            }
        }

        wp_redirect($redirect_url);
        exit();
    }

    /**
     * Thank you page content.
     */
    public function thankyou_page_content($order_id)
    {
        $order = wc_get_order($order_id);

        // Vérifier si la commande a été payée avec la méthode Chap Chap Pay
        if ('chap_chap_pay' === $order->get_payment_method()) {
            // Récupérer des informations supplémentaires si vous en avez
            $transaction_reference = get_post_meta($order_id, 'Chap Chap Pay Transaction Reference', true);
            $payment_method = get_post_meta($order_id, 'Chap Chap Pay Payment Method', true);
            $status = get_post_meta($order_id, 'Chap Chap Pay Payment Status', true);
            // $status = "success";
            $payment_method = "cc";

            echo '<div class="custom-ccp-thankyou-content">'; // Ouvrir la balise div

            if ($this->instructions) {
                echo "<h3 class='custom-ccp-thankyou-title'>" . $this->instructions . "</h3>";
            } else {
                echo '<h3 class="custom-ccp-thankyou-title">Merci d\'avoir choisi Chap Chap Pay comme méthode de paiement.</h3>';
            }
            
            echo '<div class="custom-ccp-thankyou-content-2">'; // Ouvrir la balise div 2
            echo '<div class="custom-ccp-block">'; // Ouvrir la balise div BLOCK
            echo "<p class='cccp-custom-sutitle'>Satut de paiement:</p>";
            echo "<p class='custom-thankyou-status-color'>" . $status . "</p>";
            echo '</div>'; // Fermer la balise div BLOCK

            if ($transaction_reference) {
                echo '<div class="custom-ccp-block">'; // Ouvrir la balise div BLOCK
                echo '<p class="cccp-custom-sutitle">Référence: </p>';
                echo '<p class="custom-ccp-span-content">' . esc_html($transaction_reference) . '</p>';
                echo '</div>'; // Fermer la balise div BLOCK
            }

            if ($payment_method) {
                echo '<div class="custom-ccp-block">'; // Ouvrir la balise div BLOCK
                echo '<p class="cccp-custom-sutitle">Mode de paiement: </p>';
                echo '<p class="custom-ccp-span-content">' . esc_html($this->ccpay_payment_text($payment_method)) . '</p>';
                echo '</div>'; // Fermer la balise div BLOCK
                
                echo '<div class="custom-ccp-block">'; // Ouvrir la balise div BLOCK
                
                // Display images based on payment method
                $image_url = $this->ccpay_payment_icon($payment_method);

                if ($image_url) {
                    echo '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($payment_method) . '">';
                }
                echo '</div>'; // Fermer la balise div BLOCK

            }

            echo '</div>'; // Fermer la balise div 2
            echo '</div>'; // Fermer la balise div

            echo "<style>
                        .custom-ccp-thankyou-content {
                            background-color: #F3F4F6;
                            margin-bottom: 20px;
                            color: black;
                            display: block;
                            width: 100%;
                            border-width: 1px; 
                            border-color: #000000; 
                            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
                        }

                        .custom-ccp-thankyou-content-2 {
                            display: flex;
                            padding: 20px;
                        }

                        .custom-ccp-block {
                            display: block;
                            width: 100%;
                            padding: 0.5rem;
                            border-left-width: 5px; 
                            border-color: #000000; 
                            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
                            justify-content: center; 
                        }

                        .custom-ccp-block:hover {
                            background-color: #F9FAFB;
                            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
                        }

                        .cccp-custom-sutitle {
                            font-size: 0.75rem;
                            line-height: 1rem; 
                            color: #6B7280;
                            text-transform: uppercase; 
                            font-family: system-ui;
                        }

                        .custom-ccp-thankyou-title {
                            background-color: #ffffff; 
                            padding-top: 1.25rem;
                            padding-bottom: 1.25rem;
                            padding-left: 0.5rem;
                            padding-right: 0.5rem; 
                            border-width: 2px; 
                            border-color: #374151;
                            font-family: 'Times New Roman';
                        }

                        .custom-thankyou-status-color {
                            padding-top: 0.5rem;
                            padding-bottom: 0.5rem;
                            padding-left: 1.25rem;
                            padding-right: 1.25rem;
                            margin: 1rem;
                            width: 8rem;
                            background-color: {$this->transaction_status_color($status)};
                            border-radius: 0.75rem;
                            color:#ffff;
                            text-align: center; 
                        }

                        .custom-ccp-thankyou-content p {
                            margin: 0;
                            line-height: 1.6em;
                        }

                        .custom-ccp-thankyou-content .custom-ccp-span-content {
                            font-weight: bold;
                            color: #0066cc;
                            font-size: 0.875rem;
                            line-height: 1.25rem;
                            text-transform: uppercase;
                            text-align: center; 

                              @media (min-width: 640px) { 
                                text-align: left; 
                               }
                              
                        }
                    </style>
            ";
        }
    }

    private function ccpay_payment_icon($payment_method)
    {
        $image_url = "";
        switch ($payment_method) {
            case 'paycard':
                $image_url = "paywithpaycard.png";
                break;
            case 'cc':
                $image_url = "paywithcc.png";
                break;
            case 'orange_money':
                $image_url = "paywithom.png";
                break;
            case 'mtn_momo':
                $image_url = "paywithmomo.png";
                break;
            default:
                $image_url = "logo.png";
        }

        return plugins_url('/assets/' . $image_url, __DIR__);
    }

    private function transaction_status_color($status)
    {
        $status_color = "";
        switch ($status) {
            case 'new':
                $status_color = "#3B82F6"; //text-blue-500
                break;
            case 'pending':
                $status_color = "#F59E0B"; //text-yellow-500
                break;
            case 'success':
                $status_color = "#10B981"; //text-green-500
                break;
            case 'failed':
                $status_color = "#EF4444"; //text-red-500
                break;
            case 'canceled':
                $status_color = "#991B1B"; //text-red-800
                break;
            case 'error':
                $status_color = "#B45309"; //text-yellow-700
                break;
            default:
                $status_color = "#1E3A8A"; //text-blue-900
        }

        return $status_color;
    }

    private function ccpay_payment_text($payment_method)
    {
        $text = "";
        switch ($payment_method) {
            case 'paycard':
                $text = "PAYCARD";
                break;
            case 'cc':
                $text = "Carte de Credit";
                break;
            case 'orange_money':
                $text = "Orange Money";
                break;
            case 'mtn_momo':
                $text = "MTN Mobile Money";
                break;
            default:
                $text = "----";
        }

        return $text;
    }
}
