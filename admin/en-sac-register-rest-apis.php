<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; 
}

if (!class_exists('EnitureSacRegisterRestAPI')) {

    class EnitureSacRegisterRestAPI
    {
        public function __construct()
        {
            add_action('rest_api_init', [$this, 'eniture_sac_rest_api_init']);
        }

        public function eniture_sac_rest_api_init()
        {
            register_rest_route('eniture-capture-shipping-account/v1', '/bill-to-options', array(
                'methods' => 'GET',
                'callback' => [$this, 'get_bill_to_options'],
                'permission_callback' => '__return_true'
            ));
        
            register_rest_route('eniture-capture-shipping-account/v1', '/bill-to-option', array(
                'methods' => 'POST',
                'callback' => [$this, 'create_or_update_bill_to_option'],
                'permission_callback' => '__return_true'
            ));
        
            register_rest_route('eniture-capture-shipping-account/v1', '/service', array(
                'methods' => 'POST',
                'callback' => [$this, 'create_or_update_service'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route('eniture-capture-shipping-account/v1', '/test-connection', array(
                'methods' => 'POST',
                'callback' => [$this, 'test_connection'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route('eniture-capture-shipping-account/v1', '/save-license', array(
                'methods' => 'POST',
                'callback' => [$this, 'save_license_key'],
                'permission_callback' => '__return_true'
            ));
            
            register_rest_route('eniture-capture-shipping-account/v1', '/get-license', array(
                'methods' => 'GET',
                'callback' => [$this, 'eniture_sac_get_license'],
                'permission_callback' => '__return_true'
            ));
        }

        public function get_bill_to_options() {
            global $wpdb;
            $options = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix . "eniture_sac_bill_to_options");

            foreach ($options as $option) {
                $option->services = $wpdb->get_results(
                    $wpdb->prepare("SELECT * FROM ".$wpdb->prefix . "eniture_sac_services WHERE bill_to_option_id = %d", $option->id)
                );
            }

            return new WP_REST_Response($options, 200);
        }

        public function create_or_update_bill_to_option(WP_REST_Request $request) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'eniture_sac_bill_to_options';

            $params = $request->get_json_params();
            $id = isset($params['id']) ? (int) $params['id'] : 0;
            $title = isset($params['bill_to_title']) ? sanitize_text_field($params['bill_to_title']) : '';
            $delete = isset($params['delete']) ? (bool) $params['delete'] : false;

            if ($delete) {
                $wpdb->delete(
                    $table_name,
                    array('ID' => $id)
                );

                $services_table = $wpdb->prefix . 'eniture_sac_services';
                $wpdb->delete(
                    $services_table,
                    array('bill_to_option_id' => $id)
                );

                return new WP_REST_Response('Bill-to option and its services deleted', 200);
            }

            if ($id > 0) {
                $wpdb->update(
                    $table_name,
                    array('bill_to_title' => $title),
                    array('ID' => $id)
                );
                $response_msg = 'Bill-to option updated';
            } else {
                $wpdb->insert(
                    $table_name,
                    array('bill_to_title' => $title)
                );
                $response_msg = 'Bill-to option created';
            }

            return new WP_REST_Response($response_msg, 200);
        }

        public function create_or_update_service(WP_REST_Request $request) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'eniture_sac_services';

            $params = $request->get_json_params();
            $id = isset($params['id']) ? (int) $params['id'] : 0;
            $bill_to_option_id = isset($params['bill_to_option_id']) ? (int) $params['bill_to_option_id'] : 0;
            $title = isset($params['service_title']) ? sanitize_text_field($params['service_title']) : '';
            $description = isset($params['service_description']) ? sanitize_textarea_field($params['service_description']) : '';
            $delete = isset($params['delete']) ? (bool) $params['delete'] : false;

            if ($delete) {
                $wpdb->delete(
                    $table_name,
                    array('ID' => $id)
                );
                return new WP_REST_Response('Service deleted', 200);
            }

            if ($id > 0) {
                $wpdb->update(
                    $table_name,
                    array(
                        'service_title' => $title,
                        'service_description' => $description
                    ),
                    array('ID' => $id)
                );
                $response_msg = 'Service updated';
            } else {
                $wpdb->insert(
                    $table_name,
                    array(
                        'bill_to_option_id' => $bill_to_option_id,
                        'service_title' => $title,
                        'service_description' => $description
                    )
                );
                $response_msg = 'Service created';
            }

            return new WP_REST_Response($response_msg, 200);
        }

        public function test_connection(WP_REST_Request $request) {
            $licenseKey = $request->get_param('licenseKey');
  
            if (empty($licenseKey)) {
                return new WP_REST_Response(array(
                'success' => false,
                'message' => 'API key is required',
                ), 400);
            }

            $response = wp_remote_post(ENITURE_SHIPPING_ACCOUNT_CAPTURE_TEST_CONNECTION_URL, array(
                'body' => json_encode(array(
                    'serverName' => $this->get_server_name(),
                    'licenseKey' => $licenseKey,
                    'platform' => 'WordPress',
                    'carrierType' => 'Small',
                    'carrierName' => 'captureAccount',
                    'carrierMode' => 'test',
                    'requestVersion' => '2.0'
                )),
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
                'method' => 'POST',
                'timeout' => 60
            ));

            if (is_wp_error($response)) {
                return new WP_REST_Response(array(
                'success' => false,
                'message' => 'An error occurred while validating the license key',
                ), 500);
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (isset($data['severity']) && 'SUCCESS' == $data['severity']) {
                update_option('eniture_sac_license_status', 'yes');
                return new WP_REST_Response(array(
                'success' => true,
                'message' => isset($data['Message']) ? $data['Message'] : 'The test resulted in a successful connection.',
                ), 200);
            } elseif (isset($data['severity']) && 'ERROR' == $data['severity']) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => isset($data['Message']) ? $data['Message'] : 'Empty response from API',
                    ), 400);
            }else {
                return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Empty response from API',
                ), 400);
            }
        }

        public function save_license_key(WP_REST_Request $request) {
            $licenseKey = $request->get_param('licenseKey');
            
            if (empty($licenseKey)) {
              return new WP_REST_Response(array(
                'success' => false,
                'message' => 'API key is required',
              ), 400);
            }
          
            if (strlen($licenseKey) > 50) {
              return new WP_REST_Response(array(
                'success' => false,
                'message' => 'API key must be 50 characters or less',
              ), 400);
            }
          
            update_option('eniture_sac_license_key', $licenseKey);
          
            return new WP_REST_Response(array(
              'success' => true,
            ), 200);
        }

        public function eniture_sac_get_license() {
            $license_key = get_option('eniture_sac_license_key', '');
            return new WP_REST_Response(['success' => true, 'licenseKey' => $license_key], 200);
        }

        /**
         * Get Domain Name
         */
        public function get_server_name()
        {
            global $wp;
            $wp_request = (isset($wp->request)) ? $wp->request : '';
            $url = home_url($wp_request);
            return $this->get_host($url);
        }

        public function get_host($url)
        {
            $parse_url = wp_parse_url(trim($url));
            if (isset($parse_url['host'])) {
                $host = $parse_url['host'];
            } else {
                $path = explode('/', $parse_url['path']);
                $host = $path[0];
            }

            return trim($host);
        }

    }

}



