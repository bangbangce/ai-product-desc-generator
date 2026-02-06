<?php
/**
 * Hooks definition class - Defines all extensible hooks for Pro plugin
 *
 * @package AI_Product_Desc_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AIPDG_Hooks class
 * 
 * This class defines all action and filter hooks that the Pro plugin can use.
 */
class AIPDG_Hooks {

    /**
     * ============================================
     * ACTION HOOKS (do_action)
     * ============================================
     */

    /**
     * Fired when plugin is fully loaded
     * 
     * @hook aipdg_loaded
     */
    public static function plugin_loaded() {
        do_action( 'aipdg_loaded' );
    }

    /**
     * Fired before generating description
     * 
     * @hook aipdg_before_generate
     * @param array $product_data Product data array
     * @param int   $product_id   Product ID
     */
    public static function before_generate( $product_data, $product_id ) {
        do_action( 'aipdg_before_generate', $product_data, $product_id );
    }

    /**
     * Fired after generating description
     * 
     * @hook aipdg_after_generate
     * @param string $description  Generated description
     * @param array  $product_data Product data
     * @param int    $product_id   Product ID
     * @param array  $api_response Full API response
     */
    public static function after_generate( $description, $product_data, $product_id, $api_response ) {
        do_action( 'aipdg_after_generate', $description, $product_data, $product_id, $api_response );
    }

    /**
     * Fired before making API call
     * 
     * @hook aipdg_before_api_call
     * @param string $provider API provider name
     * @param string $prompt   The prompt being sent
     */
    public static function before_api_call( $provider, $prompt ) {
        do_action( 'aipdg_before_api_call', $provider, $prompt );
    }

    /**
     * Fired after API call completes
     * 
     * @hook aipdg_after_api_call
     * @param array|WP_Error $response API response
     * @param string         $provider API provider name
     */
    public static function after_api_call( $response, $provider ) {
        do_action( 'aipdg_after_api_call', $response, $provider );
    }

    /**
     * Fired before saving description to product
     * 
     * @hook aipdg_before_save_description
     * @param string $description Description content
     * @param int    $product_id  Product ID
     * @param string $field_type  Field type (short|long)
     */
    public static function before_save_description( $description, $product_id, $field_type ) {
        do_action( 'aipdg_before_save_description', $description, $product_id, $field_type );
    }

    /**
     * Fired after saving description to product
     * 
     * @hook aipdg_after_save_description
     * @param string $description Description content
     * @param int    $product_id  Product ID
     * @param string $field_type  Field type
     */
    public static function after_save_description( $description, $product_id, $field_type ) {
        do_action( 'aipdg_after_save_description', $description, $product_id, $field_type );
    }

    /**
     * Fired on settings page - after general settings section
     * Pro plugin can add its settings here
     * 
     * @hook aipdg_settings_after_general
     */
    public static function settings_after_general() {
        do_action( 'aipdg_settings_after_general' );
    }

    /**
     * Fired at the bottom of settings page
     * 
     * @hook aipdg_settings_page_bottom
     */
    public static function settings_page_bottom() {
        do_action( 'aipdg_settings_page_bottom' );
    }

    /**
     * Fired in product meta box after main buttons
     * Pro plugin can add additional controls here
     * 
     * @hook aipdg_metabox_after_buttons
     * @param int $product_id Product ID
     */
    public static function metabox_after_buttons( $product_id ) {
        do_action( 'aipdg_metabox_after_buttons', $product_id );
    }

    /**
     * Fired when bulk action is triggered
     * 
     * @hook aipdg_bulk_actions
     * @param array $product_ids Array of product IDs
     */
    public static function bulk_actions( $product_ids ) {
        do_action( 'aipdg_bulk_actions', $product_ids );
    }

    /**
     * Fired when usage limit is reached
     * 
     * @hook aipdg_usage_limit_reached
     * @param int $current_usage Current usage count
     * @param int $limit         Usage limit
     */
    public static function usage_limit_reached( $current_usage, $limit ) {
        do_action( 'aipdg_usage_limit_reached', $current_usage, $limit );
    }

    /**
     * ============================================
     * FILTER HOOKS (apply_filters)
     * ============================================
     */

    /**
     * Filter available API providers
     * 
     * @filter aipdg_api_providers
     * @param array $providers Provider list
     * @return array
     */
    public static function filter_api_providers( $providers ) {
        return apply_filters( 'aipdg_api_providers', $providers );
    }

    /**
     * Filter available models for a provider
     * 
     * @filter aipdg_api_models
     * @param array  $models   Model list
     * @param string $provider Provider name
     * @return array
     */
    public static function filter_api_models( $models, $provider ) {
        return apply_filters( 'aipdg_api_models', $models, $provider );
    }

    /**
     * Filter supported languages
     * 
     * @filter aipdg_supported_languages
     * @param array $languages Language list
     * @return array
     */
    public static function filter_supported_languages( $languages ) {
        return apply_filters( 'aipdg_supported_languages', $languages );
    }

    /**
     * Filter tone/style options
     * 
     * @filter aipdg_tone_options
     * @param array $tones Tone options
     * @return array
     */
    public static function filter_tone_options( $tones ) {
        return apply_filters( 'aipdg_tone_options', $tones );
    }

    /**
     * Filter the prompt before sending to AI
     * 
     * @filter aipdg_prompt
     * @param string $prompt       Original prompt
     * @param array  $product_data Product data
     * @param array  $settings     Current settings
     * @return string
     */
    public static function filter_prompt( $prompt, $product_data, $settings ) {
        return apply_filters( 'aipdg_prompt', $prompt, $product_data, $settings );
    }

    /**
     * Filter prompt template
     * 
     * @filter aipdg_prompt_template
     * @param string $template    Template content
     * @param string $template_id Template identifier
     * @return string
     */
    public static function filter_prompt_template( $template, $template_id = 'default' ) {
        return apply_filters( 'aipdg_prompt_template', $template, $template_id );
    }

    /**
     * Filter API request arguments
     * 
     * @filter aipdg_api_request_args
     * @param array  $args     Request arguments
     * @param string $provider API provider
     * @return array
     */
    public static function filter_api_request_args( $args, $provider ) {
        return apply_filters( 'aipdg_api_request_args', $args, $provider );
    }

    /**
     * Filter API response
     * 
     * @filter aipdg_api_response
     * @param array  $response API response
     * @param string $provider API provider
     * @return array
     */
    public static function filter_api_response( $response, $provider ) {
        return apply_filters( 'aipdg_api_response', $response, $provider );
    }

    /**
     * Filter generated description
     * 
     * @filter aipdg_generated_description
     * @param string $description  Generated description
     * @param array  $product_data Product data
     * @param int    $product_id   Product ID
     * @return string
     */
    public static function filter_generated_description( $description, $product_data, $product_id ) {
        return apply_filters( 'aipdg_generated_description', $description, $product_data, $product_id );
    }

    /**
     * Filter product data before sending to AI
     * 
     * @filter aipdg_product_data
     * @param array $data       Product data
     * @param int   $product_id Product ID
     * @return array
     */
    public static function filter_product_data( $data, $product_id ) {
        return apply_filters( 'aipdg_product_data', $data, $product_id );
    }

    /**
     * Filter free version usage limit
     * 
     * @filter aipdg_free_usage_limit
     * @param int $limit Default limit (30)
     * @return int
     */
    public static function filter_free_usage_limit( $limit ) {
        return apply_filters( 'aipdg_free_usage_limit', $limit );
    }

    /**
     * Filter max length options
     * 
     * @filter aipdg_max_length_options
     * @param array $options Length options
     * @return array
     */
    public static function filter_max_length_options( $options ) {
        return apply_filters( 'aipdg_max_length_options', $options );
    }

    /**
     * Filter settings fields
     * 
     * @filter aipdg_settings_fields
     * @param array $fields Settings fields
     * @return array
     */
    public static function filter_settings_fields( $fields ) {
        return apply_filters( 'aipdg_settings_fields', $fields );
    }

    /**
     * Filter admin capability requirement
     * 
     * @filter aipdg_admin_capability
     * @param string $capability Required capability
     * @return string
     */
    public static function filter_admin_capability( $capability ) {
        return apply_filters( 'aipdg_admin_capability', $capability );
    }

    /**
     * Check if Pro version is active
     * 
     * @filter aipdg_is_pro_active
     * @param bool $is_active Default false
     * @return bool
     */
    public static function is_pro_active( $is_active = false ) {
        return apply_filters( 'aipdg_is_pro_active', $is_active );
    }

    /**
     * Filter Pro features list (for upgrade prompts)
     * 
     * @filter aipdg_pro_features
     * @param array $features Feature list
     * @return array
     */
    public static function filter_pro_features( $features ) {
        $default_features = array(
            'unlimited'   => __( 'Unlimited generations', 'ai-product-desc-generator' ),
            'batch'       => __( 'Batch generate for all products', 'ai-product-desc-generator' ),
            'templates'   => __( 'Custom prompt templates', 'ai-product-desc-generator' ),
            'history'     => __( 'Generation history', 'ai-product-desc-generator' ),
            'languages'   => __( '9+ languages supported', 'ai-product-desc-generator' ),
            'tones'       => __( '8+ writing styles', 'ai-product-desc-generator' ),
            'providers'   => __( 'Claude, Gemini and more AI models', 'ai-product-desc-generator' ),
            'seo'         => __( 'SEO analysis & scoring', 'ai-product-desc-generator' ),
        );

        return apply_filters( 'aipdg_pro_features', array_merge( $default_features, $features ) );
    }

    /**
     * Filter upgrade URL
     * 
     * @filter aipdg_upgrade_url
     * @param string $url Default upgrade URL
     * @return string
     */
    public static function filter_upgrade_url( $url ) {
        return apply_filters( 'aipdg_upgrade_url', $url );
    }
}
