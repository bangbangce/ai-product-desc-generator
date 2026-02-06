<?php
/**
 * Admin settings class
 *
 * @package AI_Product_Desc_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AIPDG_Admin class
 */
class AIPDG_Admin {

    /**
     * Initialize
     */
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
        add_action( 'wp_ajax_aipdg_test_connection', array( __CLASS__, 'ajax_test_connection' ) );
    }

    /**
     * Add admin menu
     */
    public static function add_menu() {
        // Add under WooCommerce menu if available
        $parent_slug = class_exists( 'WooCommerce' ) ? 'woocommerce' : 'options-general.php';
        
        add_submenu_page(
            $parent_slug,
            __( 'AI Description Generator', 'ai-product-desc-generator' ),
            __( 'AI Descriptions', 'ai-product-desc-generator' ),
            'manage_options',
            'aipdg-settings',
            array( __CLASS__, 'render_settings_page' )
        );
    }

    /**
     * Register settings
     */
    public static function register_settings() {
        register_setting( 
            'aipdg_settings_group', 
            'aipdg_settings',
            array( __CLASS__, 'sanitize_settings' )
        );
    }

    /**
     * Sanitize settings before save
     */
    public static function sanitize_settings( $input ) {
        $sanitized = array();

        $sanitized['api_provider'] = isset( $input['api_provider'] ) 
            ? sanitize_text_field( $input['api_provider'] ) 
            : 'openai';

        $sanitized['api_key'] = isset( $input['api_key'] ) 
            ? sanitize_text_field( $input['api_key'] ) 
            : '';

        $sanitized['model'] = isset( $input['model'] ) 
            ? sanitize_text_field( $input['model'] ) 
            : 'gpt-4o-mini';

        $sanitized['language'] = isset( $input['language'] ) 
            ? sanitize_text_field( $input['language'] ) 
            : 'zh-CN';

        $sanitized['tone'] = isset( $input['tone'] ) 
            ? sanitize_text_field( $input['tone'] ) 
            : 'professional';

        $sanitized['max_length'] = isset( $input['max_length'] ) 
            ? absint( $input['max_length'] ) 
            : 300;

        $sanitized['include_seo'] = isset( $input['include_seo'] ) ? true : false;

        // Allow Pro plugin to add more sanitization
        $sanitized = apply_filters( 'aipdg_sanitize_settings', $sanitized, $input );

        return $sanitized;
    }

    /**
     * Render settings page
     */
    public static function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $options   = AIPDG_Core::get_all_options();
        $providers = AIPDG_Core::get_api_providers();
        $languages = AIPDG_Core::get_supported_languages();
        $tones     = AIPDG_Core::get_tone_options();
        $lengths   = AIPDG_Core::get_max_length_options();
        $is_pro    = AIPDG_Hooks::is_pro_active();
        $usage     = AIPDG_Usage::get_usage();
        $limit     = AIPDG_Hooks::filter_free_usage_limit( 15 );
        ?>
        <div class="wrap aipdg-settings-wrap">
            <h1>
                <?php esc_html_e( 'AI Product Description Generator', 'ai-product-desc-generator' ); ?>
                <?php if ( $is_pro ) : ?>
                    <span class="aipdg-pro-badge">PRO</span>
                <?php endif; ?>
            </h1>

            <?php if ( ! $is_pro ) : ?>
            <div class="aipdg-usage-notice">
                <p>
                    <strong><?php esc_html_e( 'Free Plan Usage:', 'ai-product-desc-generator' ); ?></strong>
                    <?php printf( 
                        esc_html__( '%1$d / %2$d generations this month', 'ai-product-desc-generator' ),
                        $usage,
                        $limit
                    ); ?>
                </p>
                <div class="aipdg-usage-bar">
                    <div class="aipdg-usage-fill" style="width: <?php echo esc_attr( min( 100, ( $usage / $limit ) * 100 ) ); ?>%"></div>
                </div>
            </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields( 'aipdg_settings_group' ); ?>

                <table class="form-table aipdg-settings-table">
                    <tbody>
                        <!-- API Provider -->
                        <tr>
                            <th scope="row">
                                <label for="aipdg_api_provider">
                                    <?php esc_html_e( 'AI Provider', 'ai-product-desc-generator' ); ?>
                                </label>
                            </th>
                            <td>
                                <select name="aipdg_settings[api_provider]" id="aipdg_api_provider" class="regular-text">
                                    <?php foreach ( $providers as $key => $provider ) : ?>
                                        <option value="<?php echo esc_attr( $key ); ?>" 
                                            <?php selected( $options['api_provider'] ?? 'openai', $key ); ?>>
                                            <?php echo esc_html( $provider['name'] ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">
                                    <?php esc_html_e( 'Select your AI service provider.', 'ai-product-desc-generator' ); ?>
                                </p>
                            </td>
                        </tr>

                        <!-- API Key -->
                        <tr>
                            <th scope="row">
                                <label for="aipdg_api_key">
                                    <?php esc_html_e( 'API Key', 'ai-product-desc-generator' ); ?>
                                </label>
                            </th>
                            <td>
                                <input type="password" 
                                    name="aipdg_settings[api_key]" 
                                    id="aipdg_api_key" 
                                    value="<?php echo esc_attr( $options['api_key'] ?? '' ); ?>" 
                                    class="regular-text"
                                    autocomplete="off">
                                <button type="button" id="aipdg_toggle_key" class="button">
                                    <?php esc_html_e( 'Show', 'ai-product-desc-generator' ); ?>
                                </button>
                                <button type="button" id="aipdg_test_connection" class="button">
                                    <?php esc_html_e( 'Test Connection', 'ai-product-desc-generator' ); ?>
                                </button>
                                <span id="aipdg_connection_status"></span>
                                <p class="description" id="aipdg_api_key_hint">
                                    <?php 
                                    $current_provider = $options['api_provider'] ?? 'openai';
                                    $provider_links = array(
                                        'openai'    => '<a href="https://openai.com" target="_blank">OpenAI</a>',
                                        'deepseek'  => '<a href="https://deepseek.com" target="_blank">DeepSeek</a>',
                                        'anthropic' => '<a href="https://anthropic.com" target="_blank">Anthropic</a>',
                                        'gemini'    => '<a href="https://gemini.google.com" target="_blank">Google Gemini</a>',
                                    );
                                    $link = isset( $provider_links[ $current_provider ] ) ? $provider_links[ $current_provider ] : $provider_links['openai'];
                                    printf(
                                        esc_html__( 'Get your API key from %s', 'ai-product-desc-generator' ),
                                        $link
                                    ); 
                                    ?>
                                </p>
                            </td>
                        </tr>

                        <!-- Model -->
                        <tr>
                            <th scope="row">
                                <label for="aipdg_model">
                                    <?php esc_html_e( 'AI Model', 'ai-product-desc-generator' ); ?>
                                </label>
                            </th>
                            <td>
                                <select name="aipdg_settings[model]" id="aipdg_model" class="regular-text">
                                    <?php 
                                    $current_provider = $options['api_provider'] ?? 'openai';
                                    $models = AIPDG_Core::get_models( $current_provider );
                                    foreach ( $models as $key => $name ) : 
                                    ?>
                                        <option value="<?php echo esc_attr( $key ); ?>" 
                                            <?php selected( $options['model'] ?? 'gpt-4o-mini', $key ); ?>>
                                            <?php echo esc_html( $name ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>

                        <!-- Language -->
                        <tr>
                            <th scope="row">
                                <label for="aipdg_language">
                                    <?php esc_html_e( 'Output Language', 'ai-product-desc-generator' ); ?>
                                </label>
                            </th>
                            <td>
                                <select name="aipdg_settings[language]" id="aipdg_language" class="regular-text">
                                    <?php foreach ( $languages as $key => $name ) : ?>
                                        <option value="<?php echo esc_attr( $key ); ?>" 
                                            <?php selected( $options['language'] ?? 'zh-CN', $key ); ?>>
                                            <?php echo esc_html( $name ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ( ! $is_pro && count( $languages ) <= 2 ) : ?>
                                    <span class="aipdg-pro-tag"><?php esc_html_e( 'Pro: 9+ languages', 'ai-product-desc-generator' ); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <!-- Tone -->
                        <tr>
                            <th scope="row">
                                <label for="aipdg_tone">
                                    <?php esc_html_e( 'Writing Style', 'ai-product-desc-generator' ); ?>
                                </label>
                            </th>
                            <td>
                                <select name="aipdg_settings[tone]" id="aipdg_tone" class="regular-text">
                                    <?php foreach ( $tones as $key => $name ) : ?>
                                        <option value="<?php echo esc_attr( $key ); ?>" 
                                            <?php selected( $options['tone'] ?? 'professional', $key ); ?>>
                                            <?php echo esc_html( $name ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ( ! $is_pro && count( $tones ) <= 2 ) : ?>
                                    <span class="aipdg-pro-tag"><?php esc_html_e( 'Pro: 8+ styles', 'ai-product-desc-generator' ); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <!-- Max Length -->
                        <tr>
                            <th scope="row">
                                <label for="aipdg_max_length">
                                    <?php esc_html_e( 'Description Length', 'ai-product-desc-generator' ); ?>
                                </label>
                            </th>
                            <td>
                                <select name="aipdg_settings[max_length]" id="aipdg_max_length" class="regular-text">
                                    <?php foreach ( $lengths as $value => $label ) : ?>
                                        <option value="<?php echo esc_attr( $value ); ?>" 
                                            <?php selected( $options['max_length'] ?? 300, $value ); ?>>
                                            <?php echo esc_html( $label ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>

                        <!-- Include SEO -->
                        <tr>
                            <th scope="row">
                                <?php esc_html_e( 'SEO Optimization', 'ai-product-desc-generator' ); ?>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                        name="aipdg_settings[include_seo]" 
                                        value="1" 
                                        <?php checked( $options['include_seo'] ?? true ); ?>>
                                    <?php esc_html_e( 'Include SEO-friendly keywords in descriptions', 'ai-product-desc-generator' ); ?>
                                </label>
                            </td>
                        </tr>

                        <?php 
                        // Hook for Pro plugin to add settings
                        AIPDG_Hooks::settings_after_general(); 
                        ?>
                    </tbody>
                </table>

                <?php submit_button(); ?>

                <?php 
                // Hook for Pro plugin to add content at bottom
                AIPDG_Hooks::settings_page_bottom(); 
                ?>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Toggle API key visibility
            $('#aipdg_toggle_key').on('click', function() {
                var $input = $('#aipdg_api_key');
                var $btn = $(this);
                if ($input.attr('type') === 'password') {
                    $input.attr('type', 'text');
                    $btn.text('<?php esc_html_e( 'Hide', 'ai-product-desc-generator' ); ?>');
                } else {
                    $input.attr('type', 'password');
                    $btn.text('<?php esc_html_e( 'Show', 'ai-product-desc-generator' ); ?>');
                }
            });

            // Test connection
            $('#aipdg_test_connection').on('click', function() {
                var $btn = $(this);
                var $status = $('#aipdg_connection_status');
                var provider = $('#aipdg_api_provider').val();
                var apiKey = $('#aipdg_api_key').val();

                if (!apiKey) {
                    $status.html('<span style="color:red;">✗ <?php esc_html_e( 'Please enter API key', 'ai-product-desc-generator' ); ?></span>');
                    return;
                }

                $btn.prop('disabled', true);
                $status.html('<span style="color:#666;"><?php esc_html_e( 'Testing...', 'ai-product-desc-generator' ); ?></span>');

                $.post(ajaxurl, {
                    action: 'aipdg_test_connection',
                    provider: provider,
                    api_key: apiKey,
                    nonce: '<?php echo wp_create_nonce( 'aipdg_test_connection' ); ?>'
                }, function(response) {
                    if (response.success) {
                        $status.html('<span style="color:green;">✓ <?php esc_html_e( 'Connection successful!', 'ai-product-desc-generator' ); ?></span>');
                    } else {
                        $status.html('<span style="color:red;">✗ ' + response.data.message + '</span>');
                    }
                }).fail(function() {
                    $status.html('<span style="color:red;">✗ <?php esc_html_e( 'Connection failed', 'ai-product-desc-generator' ); ?></span>');
                }).always(function() {
                    $btn.prop('disabled', false);
                });
            });

            // Update models and API key hint when provider changes
            $('#aipdg_api_provider').on('change', function() {
                var provider = $(this).val();
                var $modelSelect = $('#aipdg_model');
                var $apiKeyHint = $('#aipdg_api_key_hint');
                
                // Provider links for API key hint
                var providerLinks = {
                    'openai': {
                        name: 'OpenAI',
                        url: 'https://openai.com'
                    },
                    'deepseek': {
                        name: 'DeepSeek',
                        url: 'https://deepseek.com'
                    },
                    'anthropic': {
                        name: 'Anthropic',
                        url: 'https://anthropic.com'
                    },
                    'gemini': {
                        name: 'Google Gemini',
                        url: 'https://gemini.google.com'
                    }
                };
                
                // Update API key hint
                if (providerLinks[provider]) {
                    var link = providerLinks[provider];
                    var hintText = '<?php echo esc_js( __( "Get your API key from", "ai-product-desc-generator" ) ); ?> ' +
                        '<a href="' + link.url + '" target="_blank">' + link.name + '</a>';
                    $apiKeyHint.html(hintText);
                }
                
                // Update models
                var models = <?php echo wp_json_encode( array(
                    'openai' => AIPDG_Core::get_models( 'openai' ),
                    'deepseek' => AIPDG_Core::get_models( 'deepseek' ),
                ) ); ?>;

                $modelSelect.empty();
                if (models[provider]) {
                    $.each(models[provider], function(key, name) {
                        $modelSelect.append($('<option>', { value: key, text: name }));
                    });
                }
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX: Test API connection
     */
    public static function ajax_test_connection() {
        check_ajax_referer( 'aipdg_test_connection', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-product-desc-generator' ) ) );
        }

        $provider = sanitize_text_field( $_POST['provider'] ?? '' );
        $api_key  = sanitize_text_field( $_POST['api_key'] ?? '' );

        $result = AIPDG_API::test_connection( $provider, $api_key );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success();
    }
}
