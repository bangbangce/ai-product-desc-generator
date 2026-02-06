<?php
/**
 * WooCommerce integration class
 *
 * @package AI_Product_Desc_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AIPDG_WooCommerce class
 */
class AIPDG_WooCommerce {

    /**
     * Initialize
     */
    public static function init() {
        add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
    }

    /**
     * Add meta box to product edit page
     */
    public static function add_meta_box() {
        add_meta_box(
            'aipdg_generate_box',
            __( 'AI Description Generator', 'ai-product-desc-generator' ),
            array( __CLASS__, 'render_meta_box' ),
            'product',
            'side',
            'high'
        );
    }

    /**
     * Render meta box content
     */
    public static function render_meta_box( $post ) {
        $api_key = AIPDG_Core::get_option( 'api_key' );
        $is_pro  = AIPDG_Hooks::is_pro_active();
        $usage   = AIPDG_Usage::get_usage();
        $limit   = AIPDG_Hooks::filter_free_usage_limit( 15 );

        // Show warning if no API key
        if ( empty( $api_key ) ) {
            ?>
            <div class="aipdg-notice aipdg-notice-warning">
                <p>
                    <?php esc_html_e( 'Please configure your API key first.', 'ai-product-desc-generator' ); ?>
                </p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=aipdg-settings' ) ); ?>" class="button">
                    <?php esc_html_e( 'Go to Settings', 'ai-product-desc-generator' ); ?>
                </a>
            </div>
            <?php
            return;
        }

        wp_nonce_field( 'aipdg_generate', 'aipdg_nonce' );
        ?>
        <div class="aipdg-metabox">
            <?php if ( ! $is_pro ) : ?>
            <div class="aipdg-usage-info">
                <small>
                    <?php printf( 
                        esc_html__( 'Usage: %1$d / %2$d this month', 'ai-product-desc-generator' ),
                        $usage,
                        $limit
                    ); ?>
                </small>
            </div>
            <?php endif; ?>

            <!-- Additional Keywords -->
            <div class="aipdg-field">
                <label for="aipdg_keywords">
                    <?php esc_html_e( 'Additional Keywords:', 'ai-product-desc-generator' ); ?>
                </label>
                <input type="text" 
                    id="aipdg_keywords" 
                    class="widefat" 
                    placeholder="<?php esc_attr_e( 'e.g., eco-friendly, handmade', 'ai-product-desc-generator' ); ?>">
                <small class="aipdg-hint">
                    <?php esc_html_e( 'Optional: Add keywords to include in the description', 'ai-product-desc-generator' ); ?>
                </small>
            </div>

            <!-- Generate Options -->
            <div class="aipdg-options">
                <label class="aipdg-checkbox">
                    <input type="checkbox" id="aipdg_generate_short" checked>
                    <?php esc_html_e( 'Short description', 'ai-product-desc-generator' ); ?>
                </label>
                <label class="aipdg-checkbox">
                    <input type="checkbox" id="aipdg_generate_long" checked>
                    <?php esc_html_e( 'Full description', 'ai-product-desc-generator' ); ?>
                </label>
            </div>

            <!-- Generate Button -->
            <div class="aipdg-actions">
                <button type="button" 
                    id="aipdg_generate_btn" 
                    class="button button-primary button-large aipdg-generate-btn"
                    data-product-id="<?php echo esc_attr( $post->ID ); ?>">
                    <span class="dashicons dashicons-welcome-write-blog"></span>
                    <?php esc_html_e( 'Generate Description', 'ai-product-desc-generator' ); ?>
                </button>
            </div>

            <!-- Status -->
            <div id="aipdg_status" class="aipdg-status" style="display:none;"></div>

            <?php
            // Hook for Pro plugin to add more controls
            AIPDG_Hooks::metabox_after_buttons( $post->ID );
            ?>
        </div>
        <?php
    }

    /**
     * Get product data for AI generation
     *
     * @param int $product_id Product ID
     * @return array|false
     */
    public static function get_product_data( $product_id ) {
        $product = wc_get_product( $product_id );

        if ( ! $product ) {
            return false;
        }

        // Get categories
        $categories = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'names' ) );
        $category_string = is_array( $categories ) ? implode( ', ', $categories ) : '';

        // Get tags
        $tags = wp_get_post_terms( $product_id, 'product_tag', array( 'fields' => 'names' ) );
        $tags_string = is_array( $tags ) ? implode( ', ', $tags ) : '';

        // Get attributes
        $attributes = array();
        foreach ( $product->get_attributes() as $attr_name => $attr ) {
            if ( is_object( $attr ) && method_exists( $attr, 'get_options' ) ) {
                $options = $attr->get_options();
                if ( ! empty( $options ) ) {
                    // Get attribute label
                    $label = wc_attribute_label( $attr_name );
                    $values = array();
                    
                    foreach ( $options as $option ) {
                        if ( is_numeric( $option ) ) {
                            $term = get_term( $option );
                            if ( $term && ! is_wp_error( $term ) ) {
                                $values[] = $term->name;
                            }
                        } else {
                            $values[] = $option;
                        }
                    }
                    
                    if ( ! empty( $values ) ) {
                        $attributes[] = $label . ': ' . implode( ', ', $values );
                    }
                }
            }
        }
        $attributes_string = implode( '; ', $attributes );

        // Get price
        $price = '';
        if ( $product->get_price() ) {
            $price = strip_tags( wc_price( $product->get_price() ) );
        }

        // Build product data array
        $data = array(
            'id'                => $product_id,
            'name'              => $product->get_name(),
            'category'          => $category_string,
            'tags'              => $tags_string,
            'price'             => $price,
            'attributes'        => $attributes_string,
            'short_description' => $product->get_short_description(),
            'description'       => $product->get_description(),
            'sku'               => $product->get_sku(),
            'type'              => $product->get_type(),
            'weight'            => $product->get_weight(),
            'dimensions'        => array(
                'length' => $product->get_length(),
                'width'  => $product->get_width(),
                'height' => $product->get_height(),
            ),
        );

        return $data;
    }

    /**
     * Save description to product
     *
     * @param int    $product_id  Product ID
     * @param string $description Description content
     * @param string $type        Type: short or long
     * @return bool
     */
    public static function save_description( $product_id, $description, $type = 'long' ) {
        $product = wc_get_product( $product_id );

        if ( ! $product ) {
            return false;
        }

        // Fire before save hook
        AIPDG_Hooks::before_save_description( $description, $product_id, $type );

        if ( $type === 'short' ) {
            $product->set_short_description( $description );
        } else {
            $product->set_description( $description );
        }

        $product->save();

        // Fire after save hook
        AIPDG_Hooks::after_save_description( $description, $product_id, $type );

        return true;
    }
}
