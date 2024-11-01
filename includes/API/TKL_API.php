<?php
require_once 'TKL_Endpoints.php';

class TKL_API
{
    /**
     * Class instance.
     */
    protected static $_instance = null;

    protected $API_KEY;

    protected $base_url;

    private $settings_key = 'woocommerce_turnkeylendergateway_settings';

    private $settings;

    protected function setAPIKey()
    {
        $key = $this->settings['tkl_api_key'];
        if ($key) {
            $this->API_KEY = $key;
            return true;
        }

        return false;
    }

    public function setBaseUrl()
    {
        $key = $this->settings['tkl_api_base'];
        if ($key) {
            $this->base_url = $key;
            return true;
        }

        return false;
    }

    protected function set_settings()
    {
        $this->settings = get_option($this->settings_key);

        if ($this->settings) {
            return true;
        }

        return false;
    }

    public function get_api()
    {
        if ($this->set_settings() && $this->setBaseUrl() && $this->setAPIKey()) {
            return self::getInstance();
        }

        return false;
    }

    public function request($endpoint, $params = [], $method = 'POST')
    {

        $args = [
            'headers' => [
                'Host' => parse_url($this->base_url, PHP_URL_HOST),
                'tkLender_ApiKey' => $this->API_KEY,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 45,
            'body' => wp_json_encode($params)
        ];

        if ($method == 'POST') {
            $req = wp_remote_post($this->base_url . $endpoint, $args);
        } else {
            $req = wp_remote_get($this->base_url . $endpoint . '?' . http_build_query($params), $args);
        }

        return $this->get_response($req);
    }

    function get_response_body($response)
    {
        $body = wp_remote_retrieve_body($response);

        return json_decode($body, ARRAY_A);
    }

    function get_response_code($response)
    {
        return wp_remote_retrieve_response_code($response);
    }

    function get_response($response)
    {
        if (is_wp_error($response)) {
            return ['code' => $response->get_error_code(), 'body' => $response->get_error_message()];
        }

        return ['code' => $this->get_response_code($response), 'body' => $this->get_response_body($response)];
    }


    /**
     * Get class instance
     * @return static
     */
    public static function getInstance()
    {
        if (is_null(static::$_instance)) {
            static::$_instance = new static();
        }

        return static::$_instance;
    }

    /**
     * prevent the instance from being cloned
     *
     * @return void
     */
    protected function __clone()
    {
    }

    /**
     * prevent from being unserialized
     *
     * @return void
     */
    protected function __wakeup()
    {
    }

    /**
     * Singleton constructor.
     */
    protected function __construct()
    {
    }
}