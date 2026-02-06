<?php
/**
 * Usage tracking class
 *
 * @package AI_Product_Desc_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AIPDG_Usage class
 */
class AIPDG_Usage {

    /**
     * Option key for usage data
     */
    const OPTION_KEY = 'aipdg_usage';

    /**
     * Initialize
     */
    public static function init() {
        // Reset usage monthly via cron or on check
        add_action( 'admin_init', array( __CLASS__, 'maybe_reset_monthly' ) );
    }

    /**
     * Check if user can generate (within limit)
     *
     * @return bool
     */
    public static function can_generate() {
        // Pro users have no limit
        if ( AIPDG_Hooks::is_pro_active() ) {
            return true;
        }

        $limit = AIPDG_Hooks::filter_free_usage_limit( 15 );
        $usage = self::get_usage();

        return $usage < $limit;
    }

    /**
     * Get current month's usage count
     *
     * @return int
     */
    public static function get_usage() {
        self::maybe_reset_monthly();
        
        $data = get_option( self::OPTION_KEY, array() );
        return isset( $data['count'] ) ? absint( $data['count'] ) : 0;
    }

    /**
     * Increment usage counter
     */
    public static function increment() {
        // Don't track for Pro users (optional - could still track for stats)
        if ( AIPDG_Hooks::is_pro_active() ) {
            return;
        }

        self::maybe_reset_monthly();

        $data = get_option( self::OPTION_KEY, array() );
        
        if ( ! isset( $data['count'] ) ) {
            $data['count'] = 0;
        }
        
        $data['count']++;
        
        update_option( self::OPTION_KEY, $data );
    }

    /**
     * Get remaining generations
     *
     * @return int
     */
    public static function get_remaining() {
        if ( AIPDG_Hooks::is_pro_active() ) {
            return PHP_INT_MAX;
        }

        $limit = AIPDG_Hooks::filter_free_usage_limit( 15 );
        $usage = self::get_usage();

        return max( 0, $limit - $usage );
    }

    /**
     * Check and reset monthly if needed
     */
    public static function maybe_reset_monthly() {
        $data = get_option( self::OPTION_KEY, array() );
        $current_month = current_time( 'Y-m' );

        if ( ! isset( $data['month'] ) || $data['month'] !== $current_month ) {
            $data = array(
                'month' => $current_month,
                'count' => 0,
            );
            update_option( self::OPTION_KEY, $data );
        }
    }

    /**
     * Get usage percentage
     *
     * @return float
     */
    public static function get_usage_percentage() {
        if ( AIPDG_Hooks::is_pro_active() ) {
            return 0;
        }

        $limit = AIPDG_Hooks::filter_free_usage_limit( 15 );
        $usage = self::get_usage();

        if ( $limit <= 0 ) {
            return 100;
        }

        return min( 100, ( $usage / $limit ) * 100 );
    }

    /**
     * Get usage data array
     *
     * @return array
     */
    public static function get_usage_data() {
        $limit = AIPDG_Hooks::filter_free_usage_limit( 15 );
        $usage = self::get_usage();

        return array(
            'current'    => $usage,
            'limit'      => $limit,
            'remaining'  => max( 0, $limit - $usage ),
            'percentage' => self::get_usage_percentage(),
            'is_pro'     => AIPDG_Hooks::is_pro_active(),
        );
    }
}
