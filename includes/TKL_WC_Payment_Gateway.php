<?php
require_once 'API/TKL_API.php';
require_once 'API/TKL_Endpoints.php';

class TKL_WC_Payment_Gateway extends WC_Payment_Gateway
{

    public $API;

    /**
     * Class constructor
     */
    public function __construct()
    {

        $this->id = 'turnkeylendergateway'; // payment gateway plugin ID
        $this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
        $this->has_fields = false; // in case you need a custom credit card form
        $this->method_title = 'TurnKey Lender Gateway';
        $this->method_description = '<p>Connect your online shop with TurnKey Lender software.</p>
 <p>To set up your TurnKey Lender instance, contact us by email: <strong>hyampolskyy@turnkey-lender.com</strong> or phone: <strong>+1 888 509 0280</strong>.</p>';

        // gateways can support subscriptions, refunds, saved payment methods,
        $this->supports = array(
            'products'
        );

        // Method with all the options fields
        $this->init_form_fields();

        // Load the settings.
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');

        // This action hook saves the settings
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        // custom JavaScript
        //add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );

        add_action('woocommerce_api_tklhook', array($this, 'webhook'));

        $this->API = TKL_API::getInstance()->get_api();
    }

    /**
     * Plugin options
     */
    public function init_form_fields()
    {

        $this->form_fields = array(
            'enabled' => array(
                'title' => 'Enable/Disable',
                'label' => 'Enable TurnKey Lender Gateway',
                'type' => 'checkbox',
                'description' => '',
                'default' => 'no'
            ),
            'title' => array(
                'title' => 'Title',
                'type' => 'text',
                'description' => 'This controls the title which the user sees during checkout.',
                'default' => 'TurnKey Lender',
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => 'Description',
                'type' => 'textarea',
                'description' => 'This controls the description which the user sees during checkout.',
                'default' => 'Pay in credit using TurnKey Lender.',
                'desc_tip' => true,
            ),
            'instructions' => array(
                'title' => 'Instructions',
                'type' => 'textarea',
                'description' => 'Instructions that will be added to the thank you page.',
                'default' => 'Pay in credit using TurnKey Lender.',
                'desc_tip' => true,
            ),
            'tkl_api_base' => array(
                'title' => 'TurnKey Lender API base',
                'type' => 'text'
            ),
            'tkl_api_key' => array(
                'title' => 'API Key',
                'type' => 'text'
            ),
            'enabled_calculation' => array(
                'title' => 'Enable/Disable',
                'label' => 'Enable TurnKey Lender Loan Calculation',
                'type' => 'checkbox',
                'description' => '',
                'default' => 'no'
            ),
            'place_calculation' => array(
                'title' => 'Select place on a single page',
                'label' => 'Select place where will locate',
                'type' => 'select',
                'description' => '',
                'options' => [
                    'woocommerce_after_add_to_cart_button' => 'After Add to Cart Button',
                    'woocommerce_after_add_to_cart_form' => 'After Add to Cart Form',
                    'woocommerce_product_meta_start' => 'Product Meta Start',
                    'woocommerce_product_meta_end' => 'Product Meta End'
                ]
            ),
            'exclude_styles' => array(
                'title' => 'Disable styles',
                'label' => 'Disable default popup`s styles',
                'type' => 'checkbox',
                'description' => '',
                'default' => 'no'
            ),
        );

    }

    public function process_payment($order_id)
    {
        global $woocommerce;

        // we need it to get any order detailes
        $order = wc_get_order($order_id);

        $products = [];
        foreach ( $order->get_items() as $item_id => $item ) {
            $products[] = [
                'Title' => $item->get_name(),
                'Price' => $item->get_total(),
                'Quantity' => $item->get_quantity(),
                'ProductUrl' => get_permalink($item->get_product_id()),
                'ImageUrl' => get_the_post_thumbnail_url($item->get_product_id())
            ];
        }
        
        $order_data = $order->get_data();

        $params = [
            'Order' => [
                'Amount' => $order_data['total'],
                'Number' => $order_id,
                'Products' => $products
            ],
            'Customer' => [
                'FirstName' => $order_data['billing']['first_name'],
                'LastName' => $order_data['billing']['last_name'],
                'CompanyName' => $order_data['billing']['company'],
                'Phone' => $order_data['billing']['phone'],
                'Email' => $order_data['billing']['email'],
                'Address' => [
                    'StreetAddress1' => $order_data['billing']['address_1'],
                    'StreetArresss2' => $order_data['billing']['address_2'] ?? '',
                    'City' => $order_data['billing']['city'],
                    'State' => $order_data['billing']['state'],
                    'ZipCode' => $order_data['billing']['postcode'],
                ]
            ],
            'CallbackUrl' => get_site_url() . '/wc-api/tklhook/'
        ];
        $response = $this->API->request(TKL_Endpoints::ApplyForLoan, $params);

        if ($response['code'] == 200) {
            return [
                'result' => 'success',
                'redirect' => str_replace('"', '', $response['body'])
            ];
        } elseif ($response['code'] == 400) {
            wc_add_notice(esc_html($response['body']->Message), 'error');
            return;
        } else {
            if ($response['body']) {
                wc_add_notice(esc_html($response['body']->Message), 'error');
            } else {
                wc_add_notice('Please try again.', 'error');
            }
            return;
        }
    }

    public function webhook()
    {
        global $woocommerce;

        if (isset($_GET) && isset($_GET['key']) && isset($_GET['status'])) {
            $key = sanitize_text_field($_GET['key']);
            $order = wc_get_order($key);

            if ($order && $status = sanitize_text_field($_GET['status'])) {
                if ($status == 'confirmed') {
                    $order->payment_complete();
                    $order->reduce_order_stock();

                    // Empty cart
                    $woocommerce->cart->empty_cart();

                    $order->get_order_key();

                    update_post_meta($order->get_id(), 'loanId', absint($_GET['loadId']));

                    $order->update_status('completed');

                    wp_safe_redirect($this->get_return_url($order));
                } elseif ($status == 'rejected') {
                    $order->update_status('failed');
                    wc_add_notice('Your payment has been rejected', 'error');
                    wp_safe_redirect(wc_get_cart_url());
                } elseif ($status == 'cancelled') {
                    $order->update_status('cancelled');
                    wc_add_notice('Your payment has been cancelled', 'error');
                    wp_safe_redirect(wc_get_cart_url());
                } else {
                    $order->update_status('failed');
                    wc_add_notice('Invalid order status', 'error');
                    wp_safe_redirect(wc_get_cart_url());
                }
            } else {
                wc_add_notice('Invalid order key', 'error');
                wp_safe_redirect(wc_get_cart_url());
            }
        } else {
            wc_add_notice('Invalid request', 'error');
            wp_safe_redirect(wc_get_cart_url());
        }

    }
}