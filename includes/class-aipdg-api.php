<?php
/**
 * API handling class
 *
 * @package AI_Product_Desc_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AIPDG_API class
 */
class AIPDG_API {

    /**
     * Generate product description
     *
     * @param array $product_data Product data
     * @param int   $product_id   Product ID
     * @return array|WP_Error
     */
    public static function generate_description( $product_data, $product_id ) {
        $provider = AIPDG_Core::get_option( 'api_provider', 'openai' );
        $api_key  = AIPDG_Core::get_option( 'api_key' );

        // Check API key
        if ( empty( $api_key ) ) {
            return new WP_Error( 
                'no_api_key', 
                __( 'API key not configured. Please add your API key in settings.', 'ai-product-desc-generator' ) 
            );
        }

        // Check usage limit (for free version)
        if ( ! AIPDG_Usage::can_generate() ) {
            $limit = AIPDG_Hooks::filter_free_usage_limit( 15 );
            AIPDG_Hooks::usage_limit_reached( AIPDG_Usage::get_usage(), $limit );
            
            return new WP_Error( 
                'usage_limit', 
                __( 'Monthly usage limit reached. Upgrade to Pro for unlimited generations.', 'ai-product-desc-generator' ) 
            );
        }

        // Filter product data
        $product_data = AIPDG_Hooks::filter_product_data( $product_data, $product_id );

        // Fire before generate hook
        AIPDG_Hooks::before_generate( $product_data, $product_id );

        // Build prompt
        $prompt = self::build_prompt( $product_data );

        // Fire before API call hook
        AIPDG_Hooks::before_api_call( $provider, $prompt );

        // Make API call
        $result = self::call_api( $provider, $api_key, $prompt );

        // Fire after API call hook
        AIPDG_Hooks::after_api_call( $result, $provider );

        if ( is_wp_error( $result ) ) {
            // Log failed API call
            AIPDG_Logger::log(
                $product_id,
                $provider,
                AIPDG_Core::get_option( 'model', 'gpt-4o-mini' ),
                0,
                false,
                $result->get_error_message()
            );
            return $result;
        }

        // Filter API response
        $result = AIPDG_Hooks::filter_api_response( $result, $provider );

        // Filter generated description
        $description = AIPDG_Hooks::filter_generated_description(
            $result['description'],
            $product_data,
            $product_id
        );

        $result['description'] = $description;

        // Fire after generate hook
        AIPDG_Hooks::after_generate( $description, $product_data, $product_id, $result );

        // Log successful API call
        AIPDG_Logger::log(
            $product_id,
            $result['provider'],
            $result['model'],
            $result['tokens_used'],
            true
        );

        // Increment usage counter
        AIPDG_Usage::increment();

        return $result;
    }

    /**
     * Build the prompt
     *
     * @param array $product_data Product data
     * @return string
     */
    private static function build_prompt( $product_data ) {
        $settings = array(
            'language'    => AIPDG_Core::get_option( 'language', 'zh-CN' ),
            'tone'        => AIPDG_Core::get_option( 'tone', 'professional' ),
            'max_length'  => AIPDG_Core::get_option( 'max_length', 300 ),
            'include_seo' => AIPDG_Core::get_option( 'include_seo', true ),
        );

        $template = self::get_default_template();
        $template = AIPDG_Hooks::filter_prompt_template( $template, 'default' );

        $prompt = sprintf(
            $template,
            sanitize_text_field( $product_data['name'] ?? '' ),
            sanitize_text_field( $product_data['category'] ?? '' ),
            sanitize_text_field( $product_data['price'] ?? '' ),
            sanitize_text_field( $product_data['attributes'] ?? '' ),
            sanitize_text_field( $product_data['short_description'] ?? '' ),
            AIPDG_Core::get_language_name( $settings['language'] ),
            AIPDG_Core::get_tone_name( $settings['tone'] ),
            intval( $settings['max_length'] ),
            $settings['include_seo'] 
                ? __( 'Include SEO-friendly keywords naturally', 'ai-product-desc-generator' )
                : __( 'Focus on readability over SEO', 'ai-product-desc-generator' )
        );

        return AIPDG_Hooks::filter_prompt( $prompt, $product_data, $settings );
    }

    /**
     * Get default prompt template
     *
     * @return string
     */
    private static function get_default_template() {
        return "You are an expert e-commerce copywriter. Generate a compelling product description based on the following information:\n\n" .
            "Product Name: %s\n" .
            "Category: %s\n" .
            "Price: %s\n" .
            "Attributes: %s\n" .
            "Existing Description: %s\n\n" .
            "Requirements:\n" .
            "- Language: %s\n" .
            "- Tone: %s\n" .
            "- Maximum Length: approximately %d characters\n" .
            "- %s\n" .
            "- Include key benefits and features\n" .
            "- Make it engaging and conversion-focused\n\n" .
            "Generate ONLY the product description, no additional text or explanations.";
    }

    /**
     * Make API call
     *
     * @param string $provider API provider
     * @param string $api_key  API key
     * @param string $prompt   Prompt text
     * @return array|WP_Error
     */
    private static function call_api( $provider, $api_key, $prompt ) {
        $providers = AIPDG_Core::get_api_providers();

        if ( ! isset( $providers[ $provider ] ) ) {
            return new WP_Error( 
                'invalid_provider', 
                __( 'Invalid API provider.', 'ai-product-desc-generator' ) 
            );
        }

        $model    = AIPDG_Core::get_option( 'model', 'gpt-4o-mini' );
        $endpoint = $providers[ $provider ]['endpoint'];

        // Build request arguments
        $args = array(
            'model'       => $model,
            'messages'    => array(
                array(
                    'role'    => 'user',
                    'content' => $prompt,
                ),
            ),
            'max_tokens'  => 1000,
            'temperature' => 0.7,
        );

        // Allow filtering request args
        $args = AIPDG_Hooks::filter_api_request_args( $args, $provider );

        // Make request
        $response = wp_remote_post( $endpoint, array(
            'timeout' => 60,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( $args ),
        ) );

        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'api_connection_error',
                sprintf(
                    __( 'Failed to connect to %s API: %s', 'ai-product-desc-generator' ),
                    $providers[ $provider ]['name'],
                    $response->get_error_message()
                )
            );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = json_decode( wp_remote_retrieve_body( $response ), true );

        // Check for API errors
        if ( $status_code !== 200 ) {
            $error_message = isset( $body['error']['message'] ) 
                ? $body['error']['message'] 
                : __( 'Unknown API error occurred.', 'ai-product-desc-generator' );
            
            return new WP_Error( 'api_error', $error_message );
        }

        // Extract description from response
        if ( isset( $body['choices'][0]['message']['content'] ) ) {
            return array(
                'description' => trim( $body['choices'][0]['message']['content'] ),
                'tokens_used' => isset( $body['usage']['total_tokens'] ) ? $body['usage']['total_tokens'] : 0,
                'model'       => $model,
                'provider'    => $provider,
            );
        }

        return new WP_Error( 
            'invalid_response', 
            __( 'Invalid response from API. Please try again.', 'ai-product-desc-generator' ) 
        );
    }

    /**
     * Test API connection
     *
     * @param string $provider API provider
     * @param string $api_key  API key
     * @return bool|WP_Error
     */
    public static function test_connection( $provider, $api_key ) {
        $providers = AIPDG_Core::get_api_providers();

        if ( ! isset( $providers[ $provider ] ) ) {
            return new WP_Error( 'invalid_provider', __( 'Invalid API provider.', 'ai-product-desc-generator' ) );
        }

        // Get default model for the provider
        $models = AIPDG_Core::get_models( $provider );
        $model = ! empty( $models ) ? array_key_first( $models ) : 'gpt-4o-mini';

        $test_prompt = 'Say "Connection successful" in exactly those two words.';

        $response = wp_remote_post( $providers[ $provider ]['endpoint'], array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( array(
                'model'       => $model,
                'messages'    => array(
                    array( 'role' => 'user', 'content' => $test_prompt ),
                ),
                'max_tokens'  => 20,
            ) ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );

        if ( $status_code === 200 ) {
            return true;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $error_message = isset( $body['error']['message'] ) 
            ? $body['error']['message'] 
            : __( 'Connection failed.', 'ai-product-desc-generator' );

        return new WP_Error( 'connection_failed', $error_message );
    }
}
