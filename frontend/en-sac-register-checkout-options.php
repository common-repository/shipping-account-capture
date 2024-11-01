<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; 
}

if (!class_exists('EnitureSacRegisterCheckoutOptions')) {

    class EnitureSacRegisterCheckoutOptions
    {
        public function __construct()
        {
            add_action('woocommerce_init', [$this, 'eniture_sac_register_checkout_options']);
        }

        public function eniture_sac_register_checkout_options(){
            
            if(function_exists('woocommerce_register_additional_checkout_field') && !empty(WC_Blocks_Utils::has_block_in_page( wc_get_page_id('checkout'), 'woocommerce/checkout' ))){
                $bill_to_options = $this->get_bill_to_options();
                $bill_to_opt_options_arr = [];
                if(!empty($bill_to_options) && is_array($bill_to_options) && count($bill_to_options) > 0){
                    foreach($bill_to_options as $option){
                        if(isset($option->services) && !empty($option->services) && !empty($option->id) && !empty($option->bill_to_title)){
                            $bill_to_opt_options_arr[] = [
                                'value' => $option->id,
                                'label' => $option->bill_to_title
                            ];
                        }
                    }
                }

                if(!empty($bill_to_opt_options_arr) && $this->checkLicenseStatus()){
                    $this->register_checkout_options($bill_to_opt_options_arr);
                }
            }
        }

        public function register_checkout_options($bill_to_opt_options_arr) {
            woocommerce_register_additional_checkout_field(
                array(
                    'id'       => 'eniture/shipping_account_capture_checkbox',
                    'label'    => 'Do you want to use your bill-to-account number to ship this order?',
                    'location' => 'address',
                    'type'     => 'checkbox',
                )
            );

            woocommerce_register_additional_checkout_field(
                array(
                    'id'       => 'eniture/shipping_account_capture_bill_to_options',
                    'label'    => 'Select an option',
                    'location' => 'address',
                    'type'     => 'select',
                    'options'  => $bill_to_opt_options_arr
                )
            );

            woocommerce_register_additional_checkout_field(
                array(
                    'id'            => 'eniture/shipping_account_capture_account_number',
                    'label'         => 'Account number',
                    'location'      => 'address',
                    'required'      => false,
                    'attributes'    => array(
                        'title'        => 'Account Number',
                    ),
                ),
            );

            woocommerce_register_additional_checkout_field(
                array(
                    'id'            => 'eniture/shipping_account_capture_billing_postal_code',
                    'label'         => 'Billing postal code',
                    'location'      => 'address',
                    'required'      => false,
                    'attributes'    => array(
                        'title'        => 'Billing postal code',
                    ),
                ),
            );

        }

        public function get_bill_to_options() {
            global $wpdb;
            $options = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix . "eniture_sac_bill_to_options");

            if(!empty($options) && is_array($options) && count($options) > 0){
                foreach ($options as $option) {
                    $option->services = $wpdb->get_results(
                        $wpdb->prepare("SELECT * FROM ".$wpdb->prefix . "eniture_sac_services WHERE bill_to_option_id = %d", $option->id)
                    );
                }
            }

            return $options;
        }

        public function checkLicenseStatus(){
            $status = get_option('eniture_sac_license_status');
            $last_date = get_option('eniture_sac_license_last_date_check');
            if (empty($status) || $status == 'no' || empty($last_date) || $last_date < gmdate('Y-m-d')) {

                $licenseKey = get_option('eniture_sac_license_key', '');
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

                $current_date = gmdate('Y-m-d');
                update_option('eniture_sac_license_last_date_check', $current_date);

                if (is_wp_error($response)) {
                    update_option('eniture_sac_license_status', 'no');
                }
    
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
    
                if (isset($data['severity']) && 'SUCCESS' == $data['severity']) {
                    update_option('eniture_sac_license_status', 'yes');
                }else {
                    update_option('eniture_sac_license_status', 'no');
                }
            }

            if(!empty(get_option('eniture_sac_license_status')) && 'yes' == get_option('eniture_sac_license_status')){
                return true;
            }else{
                return false;
            }
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



