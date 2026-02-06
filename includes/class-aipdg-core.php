<?php
/**
 * Core functionality class
 *
 * @package AI_Product_Desc_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AIPDG_Core class
 */
class AIPDG_Core {

    /**
     * Initialize
     */
    public static function init() {
        add_filter( 'plugin_action_links_' . AIPDG_PLUGIN_BASENAME, array( __CLASS__, 'plugin_action_links' ) );
    }

    /**
     * Add settings link to plugin page
     */
    public static function plugin_action_links( $links ) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url( 'admin.php?page=aipdg-settings' ),
            __( 'Settings', 'ai-product-desc-generator' )
        );

        array_unshift( $links, $settings_link );
        return $links;
    }

    /**
     * Get option value
     */
    public static function get_option( $key, $default = '' ) {
        $options = get_option( 'aipdg_settings', array() );
        return isset( $options[ $key ] ) ? $options[ $key ] : $default;
    }

    /**
     * Update option value
     */
    public static function update_option( $key, $value ) {
        $options = get_option( 'aipdg_settings', array() );
        $options[ $key ] = $value;
        update_option( 'aipdg_settings', $options );
    }

    /**
     * Get all options
     */
    public static function get_all_options() {
        return get_option( 'aipdg_settings', array() );
    }

    /**
     * Save all options
     */
    public static function save_options( $options ) {
        update_option( 'aipdg_settings', $options );
    }

    /**
     * Get supported languages
     * Use native language names (not translatable) as these are output language options
     */
    public static function get_supported_languages() {
        $languages = array(
            'zh-CN' => '简体中文',
            'en-US' => 'English (US)',
        );

        return AIPDG_Hooks::filter_supported_languages( $languages );
    }

    /**
     * Get tone options
     */
    public static function get_tone_options() {
        $tones = array(
            'professional' => __( 'Professional', 'ai-product-desc-generator' ),
            'casual'       => __( 'Casual & Friendly', 'ai-product-desc-generator' ),
        );

        return AIPDG_Hooks::filter_tone_options( $tones );
    }

    /**
     * Get language name by code
     */
    public static function get_language_name( $code ) {
        $languages = self::get_supported_languages();
        return isset( $languages[ $code ] ) ? $languages[ $code ] : $code;
    }

    /**
     * Get tone name by key
     */
    public static function get_tone_name( $key ) {
        $tones = self::get_tone_options();
        return isset( $tones[ $key ] ) ? $tones[ $key ] : $key;
    }

    /**
     * Get API providers
     */
    public static function get_api_providers() {
        $providers = array(
            'openai'   => array(
                'name'     => 'OpenAI',
                'endpoint' => 'https://api.openai.com/v1/chat/completions',
            ),
            'deepseek' => array(
                'name'     => 'DeepSeek',
                'endpoint' => 'https://api.deepseek.com/v1/chat/completions',
            ),
        );

        return AIPDG_Hooks::filter_api_providers( $providers );
    }

    /**
     * Get models for provider
     */
    public static function get_models( $provider = '' ) {
        if ( empty( $provider ) ) {
            $provider = self::get_option( 'api_provider', 'openai' );
        }

        $models = array(
            'openai' => array(
                'gpt-4o-mini' => 'GPT-4o Mini (Recommended)',
                'gpt-4o'      => 'GPT-4o',
            ),
            'deepseek' => array(
                'deepseek-chat' => 'DeepSeek Chat',
            ),
        );

        $provider_models = isset( $models[ $provider ] ) ? $models[ $provider ] : array();
        return AIPDG_Hooks::filter_api_models( $provider_models, $provider );
    }

    /**
     * Get max length options
     */
    public static function get_max_length_options() {
        $options = array(
            150 => __( 'Short (150 chars)', 'ai-product-desc-generator' ),
            300 => __( 'Medium (300 chars)', 'ai-product-desc-generator' ),
            500 => __( 'Long (500 chars)', 'ai-product-desc-generator' ),
        );

        return AIPDG_Hooks::filter_max_length_options( $options );
    }
}
