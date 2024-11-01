<?php
require_once 'API/TKL_API.php';
require_once 'API/TKL_Endpoints.php';

class TKL_LoanEstimations
{

    private $settings_key = 'woocommerce_turnkeylendergateway_settings';
    private $settings;

    public function __construct()
    {
        $this->init_settings();

        add_action('wp_enqueue_scripts', [$this, 'tkl_enqueue_scripts']);
        add_action('wp_footer', [$this, 'tkl_add_popup_body']);
        add_action('wp_ajax_get_tkl_loan_data', [$this, 'get_tkl_loan_data']);
        add_action('wp_ajax_nopriv_get_tkl_loan_data', [$this, 'get_tkl_loan_data']);
        add_action('wp', [$this, 'place_button']);
        add_action('woocommerce_proceed_to_checkout', [$this, 'insert_link'], 20);
    }

    protected function init_settings()
    {
        $this->settings = get_option($this->settings_key);
    }

    function place_button()
    {
        if (is_product()) {
            $product_obj = wc_get_product(get_the_ID());
            if ($product_obj) {
                if ($product_obj->get_type() == 'external') {
                    return;
                }
                if ($this->settings['enabled_calculation']) {
                    if ($this->settings['place_calculation']) {
                        add_action($this->settings['place_calculation'], [$this, 'insert_link']);
                    }
                }
            }
        }
    }

    function insert_link()
    {
        require_once WOO_TKL_PATH . '/templates/single_product_button.php';
    }

    function tkl_add_popup_body()
    {
        if (is_product() || is_cart() || is_checkout()) {
            require_once WOO_TKL_PATH . '/templates/popup.php';
        }
    }

    function tkl_enqueue_scripts()
    {
        if (is_product() || is_cart() || is_checkout()) {
            if ($this->settings['exclude_styles'] !== 'yes') {
                wp_enqueue_style('tkl-styles', WOO_TKL_URL . 'assets/css/style.css');
            }

            wp_enqueue_script(
                'tkl-scripts',
                WOO_TKL_URL . 'assets/js/scripts.js',
                ['jquery'],
                filemtime(WOO_TKL_PATH . '/assets/js/scripts.js'),
                true
            );
            wp_localize_script('tkl-scripts', 'tkl_params', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('tkl-loans-ajax')
            ]);
        }
    }

    function get_tkl_loan_data()
    {
        check_ajax_referer('tkl-loans-ajax', 'nonce');

        if (isset($_POST['data'])) {
            $type = sanitize_text_field($_POST['data']['type']) ?? false;
            $qty = absint($_POST['data']['qty']) ?? 1;
            $product_id = absint($_POST['data']['product']) ?? false;

            if (isset($_POST['data']['checkout']) && isset($_POST['data']['price'])) {
                $price = sanitize_text_field($_POST['data']['price']);
                $price = ltrim($price, '$');
                $price = str_replace(',', '', $price);
            }

            if ($type == 'simple' && $product = wc_get_product($product_id)) {
                $price = $product->get_price() * $qty;
            }

            if ($type == 'variable' && $product = wc_get_product($product_id)) {
                $variation = new WC_Product_Variation(absint($_POST['data']['variation_id']));
                $price = $variation->get_price() * $qty;
            }

            if(isset($price)){
                $this->request_calculation_offers($price);
            }

        }

        wp_send_json_error(['body' => 'Product not found.']);
    }

    function request_calculation_offers($price)
    {
        $response = TKL_API::getInstance()->get_api()->request(TKL_Endpoints::CalculateOffers, ['loanAmount' => $price], 'GET');

        if($response['code'] == 200){
            wp_send_json_success($this->get_loan_amount_from_response($response['body'], $price));
        }else{
            wp_send_json_error($response['body']);
        }
    }

    function get_loan_amount_from_response($response, $amount)
    {
        $min = $response['Offers'][0]['PaymentAmount'];

        foreach ($response['Offers'] as $item) {
            if ($item['PaymentAmount'] < $min) {
                $min = $item['PaymentAmount'];
            }
        }

        return ['success' => true, 'body' => $response['Offers'], 'min' => $min, 'loanAmount' => $amount];
    }

}

new TKL_LoanEstimations();

