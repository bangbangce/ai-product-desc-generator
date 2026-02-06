<?php
/**
 * API Logger class
 *
 * Logs all API calls for audit purposes.
 * Retains logs for 90+ days as per commercial requirements.
 *
 * @package AI_Product_Desc_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AIPDG_Logger class
 */
class AIPDG_Logger {

    /**
     * Log retention days
     */
    const RETENTION_DAYS = 90;

    /**
     * Option name for logs
     */
    const OPTION_NAME = 'aipdg_api_logs';

    /**
     * Initialize
     */
    public static function init() {
        // Schedule daily cleanup
        if ( ! wp_next_scheduled( 'aipdg_cleanup_logs' ) ) {
            wp_schedule_event( time(), 'daily', 'aipdg_cleanup_logs' );
        }
        add_action( 'aipdg_cleanup_logs', array( __CLASS__, 'cleanup_old_logs' ) );
    }

    /**
     * Log an API call
     *
     * @param int    $product_id WooCommerce product ID
     * @param string $provider   API provider name
     * @param string $model      AI model used
     * @param int    $tokens     Tokens used
     * @param bool   $success    Whether the call was successful
     * @param string $error      Error message if failed
     */
    public static function log( $product_id, $provider, $model, $tokens = 0, $success = true, $error = '' ) {
        $logs = get_option( self::OPTION_NAME, array() );

        $log_entry = array(
            'timestamp'  => current_time( 'mysql' ),
            'product_id' => absint( $product_id ),
            'provider'   => sanitize_text_field( $provider ),
            'model'      => sanitize_text_field( $model ),
            'tokens'     => absint( $tokens ),
            'success'    => (bool) $success,
            'error'      => sanitize_text_field( $error ),
            'ip_address' => self::get_client_ip(),
            'user_id'    => get_current_user_id(),
        );

        // Add to beginning of array (newest first)
        array_unshift( $logs, $log_entry );

        // Keep only last 10000 entries to prevent bloat
        if ( count( $logs ) > 10000 ) {
            $logs = array_slice( $logs, 0, 10000 );
        }

        update_option( self::OPTION_NAME, $logs, false );

        // Also write to debug log if enabled
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            $log_message = sprintf(
                '[AIPDG] Product: %d | Provider: %s | Model: %s | Tokens: %d | Success: %s | IP: %s',
                $product_id,
                $provider,
                $model,
                $tokens,
                $success ? 'Yes' : 'No (' . $error . ')',
                self::get_client_ip()
            );
            error_log( $log_message );
        }
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    private static function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        );

        foreach ( $ip_keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
                // Handle comma-separated IPs (X-Forwarded-For)
                if ( strpos( $ip, ',' ) !== false ) {
                    $ips = explode( ',', $ip );
                    $ip  = trim( $ips[0] );
                }
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }

        return 'unknown';
    }

    /**
     * Cleanup logs older than retention period
     */
    public static function cleanup_old_logs() {
        $logs = get_option( self::OPTION_NAME, array() );

        if ( empty( $logs ) ) {
            return;
        }

        $cutoff_date = date( 'Y-m-d H:i:s', strtotime( '-' . self::RETENTION_DAYS . ' days' ) );

        $logs = array_filter( $logs, function( $log ) use ( $cutoff_date ) {
            return isset( $log['timestamp'] ) && $log['timestamp'] >= $cutoff_date;
        } );

        update_option( self::OPTION_NAME, array_values( $logs ), false );
    }

    /**
     * Get logs
     *
     * @param int    $limit  Number of logs to retrieve
     * @param int    $offset Offset for pagination
     * @param string $filter Filter by 'success' or 'failed'
     * @return array
     */
    public static function get_logs( $limit = 50, $offset = 0, $filter = '' ) {
        $logs = get_option( self::OPTION_NAME, array() );

        if ( ! empty( $filter ) ) {
            $logs = array_filter( $logs, function( $log ) use ( $filter ) {
                if ( 'success' === $filter ) {
                    return ! empty( $log['success'] );
                } elseif ( 'failed' === $filter ) {
                    return empty( $log['success'] );
                }
                return true;
            } );
        }

        return array_slice( $logs, $offset, $limit );
    }

    /**
     * Get total log count
     *
     * @return int
     */
    public static function get_count() {
        $logs = get_option( self::OPTION_NAME, array() );
        return count( $logs );
    }

    /**
     * Clear all logs
     */
    public static function clear_logs() {
        delete_option( self::OPTION_NAME );
    }
}
