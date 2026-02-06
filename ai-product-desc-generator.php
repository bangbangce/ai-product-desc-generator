<?php
/**
 * Plugin Name: AI Product Description Generator
 * Plugin URI: https://github.com/bangbangce/ai-product-desc-generator
 * Description: AI-powered product description generator for WooCommerce. Generate SEO-friendly, engaging product descriptions with one click using OpenAI, DeepSeek, or other AI services.
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 * Author: shitou
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-product-desc-generator
 * Domain Path: /languages
 *
 * @package AI_Product_Desc_Generator
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'AIPDG_VERSION', '1.0.0' );
define( 'AIPDG_PLUGIN_FILE', __FILE__ );
define( 'AIPDG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AIPDG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AIPDG_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Initialize Freemius SDK
 * 
 * Note: Replace the placeholder values with your actual Freemius credentials
 * after registering your plugin at https://dashboard.freemius.com
 */
if ( ! function_exists( 'aipdg_fs' ) ) {
    function aipdg_fs() {
        global $aipdg_fs;

        if ( ! isset( $aipdg_fs ) ) {
            // Include Freemius SDK
            require_once AIPDG_PLUGIN_DIR . 'freemius/start.php';

            $aipdg_fs = fs_dynamic_init( array(
                'id'                  => '23354',
                'slug'                => 'ai-product-desc-generator',
                'type'                => 'plugin',
                'public_key'          => 'pk_fe2802aaadaa482a38c88ce08e797',
                'is_premium'          => false,
                'has_addons'          => false,  // Enable after Pro add-on is configured in Freemius dashboard
                'has_paid_plans'      => true,
                'menu'                => array(
                    'slug'       => 'ai-product-description-generator',
                    'parent'     => array( 'slug' => 'woocommerce' ),
                    'contact'    => false,
                    'support'    => false,
                    'account'    => false, // 隐藏子菜单，保持清爽
                    'pricing'    => false,
                ),
            ) );
        }

        return isset( $aipdg_fs ) ? $aipdg_fs : null;
    }

    aipdg_fs();
    do_action( 'aipdg_fs_loaded' );
}

/**
 * Main plugin class
 */
final class AI_Product_Desc_Generator {

    /**
     * Single instance
     * @var AI_Product_Desc_Generator
     */
    private static $instance = null;

    /**
     * Get single instance
     * @return AI_Product_Desc_Generator
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once AIPDG_PLUGIN_DIR . 'includes/class-aipdg-hooks.php';
        require_once AIPDG_PLUGIN_DIR . 'includes/class-aipdg-core.php';
        require_once AIPDG_PLUGIN_DIR . 'includes/class-aipdg-api.php';
        require_once AIPDG_PLUGIN_DIR . 'includes/class-aipdg-ajax.php';
        require_once AIPDG_PLUGIN_DIR . 'includes/class-aipdg-usage.php';
        require_once AIPDG_PLUGIN_DIR . 'includes/class-aipdg-upgrade.php';
        require_once AIPDG_PLUGIN_DIR . 'includes/class-aipdg-logger.php';

        if ( is_admin() ) {
            require_once AIPDG_PLUGIN_DIR . 'includes/class-aipdg-admin.php';
        }

        if ( $this->is_woocommerce_active() ) {
            require_once AIPDG_PLUGIN_DIR . 'includes/class-aipdg-woocommerce.php';
        }
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action( 'init', array( $this, 'load_textdomain' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
        add_action( 'admin_notices', array( $this, 'woocommerce_notice' ) );

        register_activation_hook( AIPDG_PLUGIN_FILE, array( $this, 'activate' ) );
        register_deactivation_hook( AIPDG_PLUGIN_FILE, array( $this, 'deactivate' ) );

        // Initialize components
        AIPDG_Core::init();
        AIPDG_Ajax::init();
        AIPDG_Usage::init();
        AIPDG_Upgrade::init();
        AIPDG_Logger::init();

        if ( is_admin() ) {
            AIPDG_Admin::init();
        }

        if ( $this->is_woocommerce_active() ) {
            AIPDG_WooCommerce::init();
        }

        // Fire loaded action for Pro plugin to hook into
        do_action( 'aipdg_loaded' );
    }

    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'ai-product-desc-generator',
            false,
            dirname( AIPDG_PLUGIN_BASENAME ) . '/languages'
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function admin_scripts( $hook ) {
        $allowed_screens = array( 'post.php', 'post-new.php', 'settings_page_aipdg-settings', 'woocommerce_page_aipdg-settings' );

        if ( ! in_array( $hook, $allowed_screens, true ) ) {
            global $post;
            if ( ! $post || 'product' !== $post->post_type ) {
                return;
            }
        }

        wp_enqueue_style(
            'aipdg-admin',
            AIPDG_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            AIPDG_VERSION
        );

        wp_enqueue_script(
            'aipdg-admin',
            AIPDG_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            AIPDG_VERSION,
            true
        );

        wp_localize_script( 'aipdg-admin', 'aipdg_params', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'aipdg_nonce' ),
            'i18n'     => array(
                'generating'       => __( 'Generating...', 'ai-product-desc-generator' ),
                'generate'         => __( 'Generate Description', 'ai-product-desc-generator' ),
                'error'            => __( 'An error occurred. Please try again.', 'ai-product-desc-generator' ),
                'success'          => __( 'Description generated successfully!', 'ai-product-desc-generator' ),
                'limit_reached'    => __( 'Monthly limit reached. Please upgrade to Pro.', 'ai-product-desc-generator' ),
                'no_api_key'       => __( 'Please configure your API key in settings.', 'ai-product-desc-generator' ),
                'usage_text'       => __( 'Usage: %1$d / %2$d this month', 'ai-product-desc-generator' ),
                'generations_left' => __( 'Only %d generations left this month!', 'ai-product-desc-generator' ),
            ),
        ) );
    }

    /**
     * Show notice if WooCommerce is not active
     */
    public function woocommerce_notice() {
        if ( $this->is_woocommerce_active() ) {
            return;
        }

        ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php esc_html_e( 'AI Product Description Generator', 'ai-product-desc-generator' ); ?></strong>:
                <?php esc_html_e( 'WooCommerce is required for full functionality. Some features may be limited.', 'ai-product-desc-generator' ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Check if WooCommerce is active
     */
    public function is_woocommerce_active() {
        return class_exists( 'WooCommerce' );
    }

    /**
     * Plugin activation
     */
    public function activate() {
        $default_options = array(
            'api_provider'     => 'openai',
            'api_key'          => '',
            'model'            => 'gpt-4o-mini',
            'language'         => 'zh-CN',
            'tone'             => 'professional',
            'max_length'       => 300,
            'include_seo'      => true,
        );

        if ( ! get_option( 'aipdg_settings' ) ) {
            add_option( 'aipdg_settings', $default_options );
        }

        // Initialize usage tracking
        if ( ! get_option( 'aipdg_usage' ) ) {
            add_option( 'aipdg_usage', array(
                'month' => current_time( 'Y-m' ),
                'count' => 0,
            ) );
        }

        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
}

/**
 * Get plugin instance
 * @return AI_Product_Desc_Generator
 */
function AIPDG() {
    return AI_Product_Desc_Generator::instance();
}

// Initialize plugin after all plugins loaded
add_action( 'plugins_loaded', 'AIPDG', 10 );