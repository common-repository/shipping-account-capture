<?php
/**
 * Shiping Accout Capture Shipping Method
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shiping Accout Capture
 */

if(!function_exists('eniture_shipping_account_capture_shipping_init')){

    function eniture_shipping_account_capture_shipping_init() {

		if (!class_exists('Eniture_Account_Capture_Shipping_Method')) {

			/**
			 * ABF Shipping Calculation Class
			 */
			class Eniture_Account_Capture_Shipping_Method extends WC_Shipping_Method
			{
				public function __construct($instance_id = 0)
                {
                    $this->id = 'eniture_sac_rate';
                    $this->instance_id = absint($instance_id);
                    $this->method_title = __('Shipping Account Capture', 'shipping-account-capture');
                    $this->method_description = __('Allow customers to use their account number to choose from various carrier options for shipping.', 'shipping-account-capture');

                    $this->supports = array(
                        'shipping-zones',
                        'instance-settings',
                        'instance-settings-modal',
                    );

                    $this->init();
                }

				public function init()
                {
                    // Load the settings.
                    $this->init_form_fields();
                    $this->init_settings();

                    // Define user set variables.
                    $this->title = isset($this->settings['title']) ? $this->settings['title'] : __('Shipping Account Capture', 'shipping-account-capture');

                    add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
                }

                /**
                 * Init form fields.
                 */
                public function init_form_fields()
                {
                    $this->instance_form_fields = array(
                        'enabled' => array(
                            'title' => __('Enable / Disable', 'shipping-account-capture'),
                            'type' => 'checkbox',
                            'label' => __('Enable This Shipping Service', 'shipping-account-capture'),
                            'default' => 'yes',
                            'id' => 'eniture_sac_rate_method_enabled'
                        )
                    );
                }

                /**
                 * Get setting form fields for instances of this shipping method within zones.
                 *
                 * @return array
                 */
                public function get_instance_form_fields()
                {
                    return parent::get_instance_form_fields();
                }

                /**
                 * Always return shipping method is available
                 *
                 * @param array $package Shipping package.
                 * @return bool
                 */
                public function is_available($package)
                {
                    $is_available = true;
                    return apply_filters('woocommerce_shipping_' . $this->id . '_is_available', $is_available, $package, $this);
                }

                
                public function calculate_shipping($package = array())
                {
					if(!empty(WC()->customer->get_meta('_wc_shipping/eniture/shipping_account_capture_checkbox')) 
					&& !empty(WC()->customer->get_meta('_wc_shipping/eniture/shipping_account_capture_account_number')) 
					&& !empty(WC()->customer->get_meta('_wc_shipping/eniture/shipping_account_capture_bill_to_options'))){

                        $status = get_option('eniture_sac_license_status');
                        if(!empty($status) && 'no' == $status){
                            return [];
                        }

						$sac_checkbox = WC()->customer->get_meta('_wc_shipping/eniture/shipping_account_capture_checkbox');
						$sac_account_number = WC()->customer->get_meta('_wc_shipping/eniture/shipping_account_capture_account_number');
						$sac_bill_to_options = WC()->customer->get_meta('_wc_shipping/eniture/shipping_account_capture_bill_to_options');
						$services = $this->set_services_from_bill_to_id($sac_bill_to_options);
						$rates_array = [];
						if(!empty($services) && is_array($services)){
							foreach($services as $service){
								$rates = array(
									'id' => $this->id . ':' . $service->id,
									'label' => $service->service_title. ' Bill Receiver - Account '. $sac_account_number,
									'cost' => 0,
									'plugin_name' => 'Shipping Account Capture',
									'owned_by' => 'eniture'
								);
								$rates_array[] = $rates;
								$this->add_rate($rates);
							}
						}
						return $rates_array;
					}
                }

				public function set_services_from_bill_to_id($bill_to_id) {
					global $wpdb;
					$services = $wpdb->get_results(
						$wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "eniture_sac_services WHERE bill_to_option_id = %d", $bill_to_id)
					);
					
		
					return $services;
				}

			}
		}

    }

}
