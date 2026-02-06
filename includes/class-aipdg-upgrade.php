<?php
/**
 * Upgrade notice class
 *
 * @package AI_Product_Desc_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AIPDG_Upgrade class
 */
class AIPDG_Upgrade {

    /**
     * Initialize
     */
    public static function init() {
        // Don't show upgrade notices if Pro is active
        if ( AIPDG_Hooks::is_pro_active() ) {
            return;
        }

        add_action( 'aipdg_metabox_after_buttons', array( __CLASS__, 'render_metabox_upgrade' ) );
        add_action( 'aipdg_settings_page_bottom', array( __CLASS__, 'render_settings_upgrade' ) );
        add_action( 'admin_notices', array( __CLASS__, 'maybe_show_limit_notice' ) );
    }

    /**
     * Render upgrade notice in product meta box
     */
    public static function render_metabox_upgrade( $product_id ) {
        if ( AIPDG_Hooks::is_pro_active() ) {
            return;
        }

        $remaining = AIPDG_Usage::get_remaining();
        $features = AIPDG_Hooks::filter_pro_features( array() );
        ?>
        <div class="aipdg-upgrade-box">
            <div class="aipdg-upgrade-header">
                <span class="dashicons dashicons-star-filled"></span>
                <strong><?php esc_html_e( 'Upgrade to Pro', 'ai-product-desc-generator' ); ?></strong>
            </div>
            
            <?php if ( $remaining <= 10 ) : ?>
            <p class="aipdg-warning">
                <?php printf( 
                    esc_html__( 'Only %d generations left this month!', 'ai-product-desc-generator' ),
                    $remaining
                ); ?>
            </p>
            <?php endif; ?>
            
            <ul class="aipdg-feature-list">
                <?php 
                $show_features = array_slice( $features, 0, 4 );
                foreach ( $show_features as $feature ) : 
                ?>
                    <li><span class="dashicons dashicons-yes"></span> <?php echo esc_html( $feature ); ?></li>
                <?php endforeach; ?>
            </ul>
            
            <a href="<?php echo esc_url( self::get_upgrade_url() ); ?>" 
               class="button aipdg-upgrade-btn" 
               target="_blank">
                <?php esc_html_e( 'Get Pro - $7.99/month', 'ai-product-desc-generator' ); ?>
            </a>
        </div>
        <?php
    }

    /**
     * Render upgrade notice on settings page
     */
    public static function render_settings_upgrade() {
        if ( AIPDG_Hooks::is_pro_active() ) {
            return;
        }

        $features = AIPDG_Hooks::filter_pro_features( array() );
        ?>
        <div class="aipdg-pro-banner">
            <div class="aipdg-pro-banner-content">
                <h2>
                    <span class="dashicons dashicons-star-filled"></span>
                    <?php esc_html_e( 'Unlock All Features with Pro', 'ai-product-desc-generator' ); ?>
                </h2>
                
                <div class="aipdg-pro-features-grid">
                    <?php foreach ( $features as $key => $feature ) : ?>
                    <div class="aipdg-pro-feature">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php echo esc_html( $feature ); ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="aipdg-pro-cta">
                    <a href="<?php echo esc_url( self::get_upgrade_url() ); ?>" 
                       class="button button-primary button-hero" 
                       target="_blank">
                        <?php esc_html_e( 'Upgrade to Pro - $95.88/year', 'ai-product-desc-generator' ); ?>
                    </a>
                    <p class="aipdg-guarantee">
                        <span class="dashicons dashicons-shield"></span>
                        <?php esc_html_e( '30-day money-back guarantee', 'ai-product-desc-generator' ); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Show admin notice when limit is reached
     */
    public static function maybe_show_limit_notice() {
        if ( AIPDG_Hooks::is_pro_active() ) {
            return;
        }

        // Only show on relevant pages
        $screen = get_current_screen();
        if ( ! $screen || ! in_array( $screen->id, array( 'product', 'edit-product', 'woocommerce_page_aipdg-settings' ), true ) ) {
            return;
        }

        $remaining = AIPDG_Usage::get_remaining();

        // Show warning when 5 or fewer remaining
        if ( $remaining > 5 ) {
            return;
        }

        $class = $remaining === 0 ? 'notice-error' : 'notice-warning';
        ?>
        <div class="notice <?php echo esc_attr( $class ); ?> is-dismissible aipdg-limit-notice">
            <p>
                <strong><?php esc_html_e( 'AI Product Description Generator', 'ai-product-desc-generator' ); ?>:</strong>
                <?php if ( $remaining === 0 ) : ?>
                    <?php esc_html_e( 'You have reached your monthly generation limit.', 'ai-product-desc-generator' ); ?>
                <?php else : ?>
                    <?php printf( 
                        esc_html__( 'You have only %d generations remaining this month.', 'ai-product-desc-generator' ),
                        $remaining
                    ); ?>
                <?php endif; ?>
                <a href="<?php echo esc_url( self::get_upgrade_url() ); ?>" target="_blank">
                    <?php esc_html_e( 'Upgrade to Pro for unlimited generations', 'ai-product-desc-generator' ); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Get upgrade URL
     */
    public static function get_upgrade_url() {
        // If Freemius is configured, use its upgrade URL
        if ( function_exists( 'aipdg_fs' ) && aipdg_fs() ) {
            $fs = aipdg_fs();
            if ( method_exists( $fs, 'get_upgrade_url' ) ) {
                return $fs->get_upgrade_url();
            }
        }

        // Fallback to custom URL
        $url = 'https://your-domain.com/plugins/ai-product-desc-generator-pro/';
        return AIPDG_Hooks::filter_upgrade_url( $url );
    }

    /**
     * Get pricing info
     */
    public static function get_pricing() {
        return array(
            'pro_monthly' => array(
                'name'  => __( 'Pro Monthly', 'ai-product-desc-generator' ),
                'price' => '$7.99',
                'period'=> __( 'month', 'ai-product-desc-generator' ),
                'sites' => 1,
            ),
            'pro_yearly' => array(
                'name'  => __( 'Pro Yearly', 'ai-product-desc-generator' ),
                'price' => '$95.88',
                'period'=> __( 'year', 'ai-product-desc-generator' ),
                'sites' => 1,
            ),
        );
    }
}
