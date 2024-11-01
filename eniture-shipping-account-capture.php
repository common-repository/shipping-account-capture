<?php
/**
 * Plugin Name:       Shipping Account Capture
 * Description:       The plugin allows the visitor to specify an interest in having the shipping charges billed to its account.
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Version:           1.0.0
 * Author:            Eniture Technology
 * Author URI:        http://eniture.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       shipping-account-capture
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define('ENITURE_SHIPPING_ACCOUNT_CAPTURE_MAIN_DIR', __DIR__);
define('ENITURE_SHIPPING_ACCOUNT_CAPTURE_MAIN_FILE', __FILE__);
define('ENITURE_SHIPPING_ACCOUNT_CAPTURE_TEST_CONNECTION_URL', 'https://ws110.eniture.com/captureAccount/quotes.php');

// check versions compatitblity
require_once 'admin/en-guard.php';
if(empty(EnitureShippingAccountCaptureGuard::eniture_check_prerequisites( 'Shipping Account Capture', '7.4', '6.5', '8.0' ))){

    if (!function_exists('is_plugin_active')) {
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }

    require_once 'admin/en-install-sac.php';
    register_activation_hook(__FILE__, 'eniture_shipping_account_capture_create_database_tables');
    add_action( 'admin_enqueue_scripts', 'eniture_sac_admin_enqueue_scripts' );
    add_action( 'wp_enqueue_scripts', 'eniture_sac_frontend_enqueue_scripts' );

    if ( is_admin() ) {
        add_filter('plugin_action_links', 'eniture_sac_add_action_plugin', 10, 5);
        add_filter( 'woocommerce_settings_tabs_array', 'eniture_sac_add_settings_tab', 50 );
        add_action('woocommerce_settings_tabs_eniture-sac', 'eniture_sac_settings_content');
    }

    require_once 'admin/en-sac-shipping-class.php';
    add_action( 'woocommerce_shipping_init', 'eniture_shipping_account_capture_shipping_init' );
	add_filter( 'woocommerce_shipping_methods', 'eniture_sac_add_shipping_method' );

    // register frontend options
    require_once 'frontend/en-sac-register-checkout-options.php';
    new EnitureSacRegisterCheckoutOptions();

    // register REST APIs
    require_once 'admin/en-sac-register-rest-apis.php';
    new EnitureSacRegisterRestAPI();
}

/**
 * Enqueue scripts and styles.
 */
function eniture_sac_admin_enqueue_scripts( $admin_page ) {
    if ( 'woocommerce_page_wc-settings' !== $admin_page ) {
        return;
    }

    $asset_file = plugin_dir_path( __FILE__ ) . 'build/index.asset.php';

    if ( ! file_exists( $asset_file ) ) {
        return;
    }

    $asset = include $asset_file;

    wp_enqueue_script(
        'eniture-sac-script',
        plugins_url( 'build/index.js', __FILE__ ),
        $asset['dependencies'],
        $asset['version'],
        array(
            'in_footer' => true,
        )
    );
    wp_localize_script( 'eniture-sac-script', 'eniture_sac', [
        'apiUrl' => home_url('/wp-json'),
        'eniture_sac_nonce' => wp_create_nonce('eniture_sac_rest'),
        'eniture_sac_rest_url' => esc_url_raw(rest_url('eniture-capture-shipping-account/v1/')),
    ] );

    wp_enqueue_style(
        'eniture-sac-style', 
        plugins_url( 'build/index.css', __FILE__ ),
        array_filter(
            $asset['dependencies'],
            function ( $style ) {
                return wp_style_is( $style, 'registered' );
            }
        ),
        $asset['version']
    );
}

/**
 * Frontend enqueue script.
 */
function eniture_sac_frontend_enqueue_scripts() {

    $asset_file = plugin_dir_path( __FILE__ ) . 'build/eniture-sac-frontend.asset.php';

    if ( ! file_exists( $asset_file ) ) {
        return;
    }

    $asset = include $asset_file;

    wp_enqueue_script(
        'eniture-sac-frontend-script',
        plugins_url( 'build/eniture-sac-frontend.js', __FILE__ ),
        $asset['dependencies'],
        $asset['version'],
        array(
            'in_footer' => true,
        )
    );
}

/**
 * Plugin Action
 * @staticvar $plugin
 * @param $actions
 * @param $plugin_file
 * @return array
 */
function eniture_sac_add_action_plugin($actions, $plugin_file)
{
    static $plugin;
    if (!isset($plugin))
        $plugin = plugin_basename(__FILE__);
    if ($plugin == $plugin_file) {
        $settings = array('settings' => '<a href="admin.php?page=wc-settings&tab=eniture-sac">' . __('Settings', 'shipping-account-capture') . '</a>');
        $site_link = array('support' => '<a href="https://support.eniture.com/" target="_blank">Support</a>');
        $actions = array_merge($settings, $actions);
        $actions = array_merge($site_link, $actions);
    }

    return $actions;
}

function eniture_sac_add_settings_tab( $settings_tabs ) 
{
    $settings_tabs['eniture-sac'] = __( 'Shipping Account Capture', 'shipping-account-capture' );
    return $settings_tabs;
}

function eniture_sac_add_shipping_method( $methods ) {
    $methods['eniture_sac_rate'] = 'Eniture_Account_Capture_Shipping_Method';
    return $methods;
}

// Display the content of the custom tab
function eniture_sac_settings_content() {
    echo '<div class="eniture_sac_wrapper">';
    echo '<div id="eniture_sac_root"><h1>Loading...</h1></div>';
}


 








