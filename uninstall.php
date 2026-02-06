<?php
/**
 * Uninstall script for AI Product Description Generator
 *
 * This file runs when the plugin is deleted through the WordPress admin.
 * It cleans up all plugin data from the database.
 *
 * @package AI_Product_Desc_Generator
 */

// Exit if uninstall not called from WordPress
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Clean up plugin data
 */
function aipdg_uninstall_cleanup() {
    global $wpdb;

    // Delete plugin options
    $options_to_delete = array(
        'aipdg_settings',
        'aipdg_usage',
        'aipdg_pro_settings',
        'aipdg_api_logs',
    );

    foreach ( $options_to_delete as $option ) {
        delete_option( $option );
        // Also delete from network if multisite
        delete_site_option( $option );
    }

    // Delete transients
    $wpdb->query(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_aipdg_%' OR option_name LIKE '_transient_timeout_aipdg_%'"
    );

    // Delete post meta related to the plugin
    $wpdb->query(
        "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_aipdg_%'"
    );

    // Delete user meta related to the plugin
    $wpdb->query(
        "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '_aipdg_%' OR meta_key LIKE 'aipdg_%'"
    );

    // Drop custom tables if any (for Pro version history)
    $table_name = $wpdb->prefix . 'aipdg_history';
    $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

    // Clear any cached data
    wp_cache_flush();
}

// Run cleanup
aipdg_uninstall_cleanup();
