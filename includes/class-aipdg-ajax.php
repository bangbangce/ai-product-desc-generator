<?php
/**
 * AJAX handling class
 *
 * @package AI_Product_Desc_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AIPDG_Ajax class
 */
class AIPDG_Ajax {

    /**
     * Initialize
     */
    public static function init() {
        add_action( 'wp_ajax_aipdg_generate', array( __CLASS__, 'handle_generate' ) );
        add_action( 'wp_ajax_aipdg_save_description', array( __CLASS__, 'handle_save_description' ) );
    }

    /**
     * Handle generate description AJAX request
     */
    public static function handle_generate() {
        // Verify nonce
        if ( ! check_ajax_referer( 'aipdg_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 
                'message' => __( 'Security check failed. Please refresh the page and try again.', 'ai-product-desc-generator' ) 
            ) );
        }

        // Check permissions
        $capability = AIPDG_Hooks::filter_admin_capability( 'edit_products' );
        if ( ! current_user_can( $capability ) ) {
            wp_send_json_error( array( 
                'message' => __( 'You do not have permission to perform this action.', 'ai-product-desc-generator' ) 
            ) );
        }

        // Get product ID
        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

        if ( ! $product_id ) {
            wp_send_json_error( array( 
                'message' => __( 'Invalid product ID.', 'ai-product-desc-generator' ) 
            ) );
        }

        // Get product data
        $product_data = AIPDG_WooCommerce::get_product_data( $product_id );

        if ( ! $product_data ) {
            wp_send_json_error( array( 
                'message' => __( 'Product not found.', 'ai-product-desc-generator' ) 
            ) );
        }

        // Add custom keywords if provided
        $keywords = isset( $_POST['keywords'] ) ? sanitize_text_field( $_POST['keywords'] ) : '';
        if ( ! empty( $keywords ) ) {
            $product_data['keywords'] = $keywords;
            if ( ! empty( $product_data['attributes'] ) ) {
                $product_data['attributes'] .= '; Keywords: ' . $keywords;
            } else {
                $product_data['attributes'] = 'Keywords: ' . $keywords;
            }
        }

        // Generate description
        $result = AIPDG_API::generate_description( $product_data, $product_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 
                'message' => $result->get_error_message(),
                'code'    => $result->get_error_code(),
            ) );
        }

        // Prepare response
        $response = array(
            'description' => $result['description'],
            'tokens_used' => $result['tokens_used'],
            'model'       => $result['model'] ?? '',
            'provider'    => $result['provider'] ?? '',
        );

        // Add usage info for free version
        if ( ! AIPDG_Hooks::is_pro_active() ) {
            $response['usage'] = array(
                'current' => AIPDG_Usage::get_usage(),
                'limit'   => AIPDG_Hooks::filter_free_usage_limit( 15 ),
            );
        }

        wp_send_json_success( $response );
    }

    /**
     * Handle save description AJAX request
     */
    public static function handle_save_description() {
        // Verify nonce
        if ( ! check_ajax_referer( 'aipdg_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 
                'message' => __( 'Security check failed.', 'ai-product-desc-generator' ) 
            ) );
        }

        // Check permissions
        $capability = AIPDG_Hooks::filter_admin_capability( 'edit_products' );
        if ( ! current_user_can( $capability ) ) {
            wp_send_json_error( array( 
                'message' => __( 'Permission denied.', 'ai-product-desc-generator' ) 
            ) );
        }

        $product_id  = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $description = isset( $_POST['description'] ) ? wp_kses_post( $_POST['description'] ) : '';
        $type        = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : 'long';

        if ( ! $product_id ) {
            wp_send_json_error( array( 
                'message' => __( 'Invalid product ID.', 'ai-product-desc-generator' ) 
            ) );
        }

        $saved = AIPDG_WooCommerce::save_description( $product_id, $description, $type );

        if ( $saved ) {
            wp_send_json_success( array(
                'message' => __( 'Description saved successfully.', 'ai-product-desc-generator' ),
            ) );
        } else {
            wp_send_json_error( array( 
                'message' => __( 'Failed to save description.', 'ai-product-desc-generator' ) 
            ) );
        }
    }
}
