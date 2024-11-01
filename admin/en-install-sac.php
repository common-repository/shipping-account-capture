<?php
/**
 * Shipping account capture install script file
 */
if (!defined('ABSPATH')) {
    exit;
}

if(!function_exists('eniture_shipping_account_capture_create_bill_to_options_table')){

    /**
     * Creates eniture_sac_bill_to_options table
     */
    function eniture_shipping_account_capture_create_bill_to_options_table() {
        global $wpdb;
        $eniture_charset_collate = $wpdb->get_charset_collate();
        if ( $wpdb->query( "SHOW TABLES LIKE '{$wpdb->prefix}eniture_sac_bill_to_options'" ) === 0 ) {
            
            $wpdb->query( "CREATE TABLE {$wpdb->prefix}eniture_sac_bill_to_options (
                id int(11) NOT NULL AUTO_INCREMENT,
                bill_to_title varchar(50) NOT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id)
            ) $eniture_charset_collate" );

            // return is_success bit
            return empty( $wpdb->last_error );
        }
    }

}

if(!function_exists('eniture_shipping_account_capture_create_services_table')){

    /**
     * Creates eniture_sac_services table
     */
    function eniture_shipping_account_capture_create_services_table() {
        global $wpdb;
        $eniture_charset_collate = $wpdb->get_charset_collate();
        if ( $wpdb->query( "SHOW TABLES LIKE '{$wpdb->prefix}eniture_sac_services'" ) === 0 ) {
            
            $wpdb->query( "CREATE TABLE {$wpdb->prefix}eniture_sac_services (
                id int(11) NOT NULL AUTO_INCREMENT,
                bill_to_option_id int(11) NOT NULL,
                service_title varchar(50) NOT NULL,
                service_description varchar(50) DEFAULT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id)
            ) $eniture_charset_collate" );

            // return is_success bit
            return empty( $wpdb->last_error );
        }
    }

}
/**
 * Function to create database table
 */
if (!function_exists('eniture_shipping_account_capture_create_database_tables')) {

    function eniture_shipping_account_capture_create_database_tables($network_wide = null){
        if ( is_multisite() && $network_wide ) {
            foreach ( get_sites( array( 'fields' => 'ids' ) ) as $blog_id ) {
                switch_to_blog( $blog_id );

                eniture_shipping_account_capture_create_bill_to_options_table();
                eniture_shipping_account_capture_create_services_table();

                restore_current_blog();
            }
        } else {
            eniture_shipping_account_capture_create_bill_to_options_table();
            eniture_shipping_account_capture_create_services_table();
        }
    }

}