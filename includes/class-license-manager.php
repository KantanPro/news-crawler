<?php
/**
 * License Manager class for News Crawler plugin
 *
 * Handles license verification and management with KantanPro License Manager.
 *
 * @package NewsCrawler
 * @subpackage Includes
 * @since 2.1.5
 * @author Kantan Pro
 * @copyright 2024 Kantan Pro
 * @license GPL-2.0+
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * License Manager class for managing plugin licenses
 *
 * @since 2.1.5
 */
class NewsCrawler_License_Manager {

    /**
     * Single instance of the class
     *
     * @var NewsCrawler_License_Manager
     */
    private static $instance = null;

    /**
     * License API endpoints
     *
     * @var array
     */
    private $api_endpoints = array(
        'verify' => '/wp-json/ktp-license/v1/verify',
        'info'   => '/wp-json/ktp-license/v1/info',
        'create' => '/wp-json/ktp-license/v1/create'
    );

    /**
     * Rate limit settings
     *
     * @var array
     */
    private $rate_limit = array(
        'max_requests' => 100,
        'time_window'  => 3600 // 1 hour in seconds
    );

    /**
     * Get singleton instance
     *
     * @since 2.1.5
     * @return NewsCrawler_License_Manager
     */
    public static function get_instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     *
     * @since 2.1.5
     */
    private function __construct() {
        // Initialize hooks
        add_action( 'admin_init', array( $this, 'handle_license_activation' ) );
        add_action( 'wp_ajax_news_crawler_verify_license', array( $this, 'ajax_verify_license' ) );
        add_action( 'wp_ajax_news_crawler_get_license_info', array( $this, 'ajax_get_license_info' ) );
        add_action( 'wp_ajax_news_crawler_toggle_dev_license', array( $this, 'ajax_toggle_dev_license' ) );
        
        // ライセンス状態の初期化
        $this->initialize_license_state();
    }

    /**
     * Initialize license state
     *
     * @since 2.1.5
     */
    private function initialize_license_state() {
        $license_key = get_option( 'news_crawler_license_key' );
        $license_status = get_option( 'news_crawler_license_status' );
        
        // ライセンスキーが設定されていない場合、明示的に無効な状態にする
        if ( empty( $license_key ) ) {
            if ( $license_status !== 'not_set' ) {
                update_option( 'news_crawler_license_status', 'not_set' );
                update_option( 'news_crawler_license_info', array(
                    'message' => '一部の機能を利用するにはライセンスキーが必要です。',
                    'features' => array(
                        'ai_summary' => false,
                        'advanced_features' => false
                    )
                ));
                error_log( 'NewsCrawler License: Initializing license status to not_set (no license key)' );
            }
        }
    }

    /**
     * Handle license activation form submission
     *
     * @since 2.1.5
     */
    public function handle_license_activation() {
        if ( ! isset( $_POST['news_crawler_license_activation'] ) || ! wp_verify_nonce( $_POST['news_crawler_license_nonce'], 'news_crawler_license_activation' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'この操作を実行する権限がありません。', 'news-crawler' ) );
        }

        $license_key = sanitize_text_field( $_POST['news_crawler_license_key'] ?? '' );
        
        if ( empty( $license_key ) ) {
            add_settings_error( 'news_crawler_license', 'empty_key', __( 'ライセンスキーを入力してください。', 'news-crawler' ), 'error' );
            return;
        }

        $result = $this->verify_license( $license_key );
        
        if ( $result['success'] ) {
            // Save license key
            update_option( 'news_crawler_license_key', $license_key );
            update_option( 'news_crawler_license_status', 'active' );
            update_option( 'news_crawler_license_info', $result['data'] );
            update_option( 'news_crawler_license_verified_at', current_time( 'timestamp' ) );
            
            add_settings_error( 'news_crawler_license', 'activation_success', __( 'ライセンスが正常に認証されました。', 'news-crawler' ), 'success' );
        } else {
            add_settings_error( 'news_crawler_license', 'activation_failed', $result['message'], 'error' );
        }
    }

    /**
     * Verify license with KantanPro License Manager
     *
     * @since 2.1.5
     * @param string $license_key License key to verify
     * @return array Verification result
     */
    public function verify_license( $license_key ) {
        // 開発環境でのテスト用ライセンスチェック
        if ( $this->is_development_environment() ) {
            $dev_license_key = $this->get_development_license_key();
            if ( $license_key === $dev_license_key ) {
                error_log( 'NewsCrawler License: Development license key accepted' );
                return array(
                    'success' => true,
                    'data'    => array(
                        'user_email' => 'dev@localhost',
                        'start_date' => date('Y-m-d'),
                        'end_date'   => date('Y-m-d', strtotime('+1 year')),
                        'remaining_days' => 365
                    ),
                    'message' => __( '開発環境用ライセンスが認証されました。', 'news-crawler' )
                );
            }
            
            // 開発環境では、実際のライセンスサーバーへの接続をスキップ
            error_log( 'NewsCrawler License: Development environment detected, skipping actual server verification' );
            return array(
                'success' => false,
                'message' => __( '開発環境では、テスト用ライセンスキー「DEV-TEST-KEY-12345」を使用してください。', 'news-crawler' )
            );
        }
        
        // Check rate limit
        if ( ! $this->check_rate_limit() ) {
            return array(
                'success' => false,
                'message' => __( 'レート制限に達しました。1時間後に再試行してください。', 'news-crawler' )
            );
        }

        $site_url = get_site_url();
        
        // APIエンドポイントの接続テスト
        error_log( 'NewsCrawler License: Attempting to connect to ' . get_site_url() . $this->api_endpoints['verify'] );
        error_log( 'NewsCrawler License: Site URL: ' . $site_url );
        error_log( 'NewsCrawler License: License key: ' . substr( $license_key, 0, 8 ) . '...' );
        
        $response = wp_remote_post( get_site_url() . $this->api_endpoints['verify'], array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent'   => 'NewsCrawler/' . NEWS_CRAWLER_VERSION
            ),
            'body' => json_encode( array(
                'license_key' => $license_key,
                'site_url'    => $site_url,
                'plugin_slug' => 'news-crawler'
            ) ),
            'timeout' => 30,
            'sslverify' => false  // 開発環境でのSSL証明書問題を回避
        ) );

        if ( is_wp_error( $response ) ) {
            error_log( 'NewsCrawler License: WP_Error during verification - ' . $response->get_error_message() );
            return array(
                'success' => false,
                'message' => __( 'ライセンスサーバーとの通信に失敗しました。', 'news-crawler' ) . ' ' . $response->get_error_message()
            );
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        
        error_log( 'NewsCrawler License: Response code: ' . $response_code . ', Body: ' . $body );
        
        // HTTPステータスコードのチェック
        if ( $response_code !== 200 ) {
            error_log( 'NewsCrawler License: HTTP error response - ' . $response_code );
            return array(
                'success' => false,
                'message' => __( 'ライセンスサーバーからエラーレスポンスが返されました。', 'news-crawler' ) . ' (HTTP ' . $response_code . ')'
            );
        }
        
        $data = json_decode( $body, true );

        if ( ! $data ) {
            return array(
                'success' => false,
                'message' => __( 'ライセンスサーバーからの応答が無効です。', 'news-crawler' )
            );
        }

        if ( isset( $data['success'] ) && $data['success'] ) {
            error_log( 'NewsCrawler License: Verification successful - ' . json_encode( $data ) );
            return array(
                'success' => true,
                'data'    => $data['data'] ?? array(),
                'message' => $data['message'] ?? __( 'ライセンスが正常に認証されました。', 'news-crawler' )
            );
        } else {
            // エラーメッセージの詳細な処理
            $error_message = '';
            if ( isset( $data['message'] ) && ! empty( $data['message'] ) ) {
                $error_message = $data['message'];
            } elseif ( isset( $data['error'] ) && ! empty( $data['error'] ) ) {
                $error_message = $data['error'];
            } elseif ( isset( $data['code'] ) && ! empty( $data['code'] ) ) {
                $error_message = 'エラーコード: ' . $data['code'];
            } else {
                $error_message = __( 'ライセンスの認証に失敗しました。', 'news-crawler' );
            }
            
            error_log( 'NewsCrawler License: Verification failed - Response: ' . json_encode( $data ) . ', Error: ' . $error_message );
            return array(
                'success' => false,
                'message' => $error_message
            );
        }
    }

    /**
     * Get license information
     *
     * @since 2.1.5
     * @param string $license_key License key
     * @return array License information
     */
    public function get_license_info( $license_key ) {
        $response = wp_remote_post( get_site_url() . $this->api_endpoints['info'], array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent'   => 'NewsCrawler/' . NEWS_CRAWLER_VERSION
            ),
            'body' => json_encode( array(
                'license_key' => $license_key,
                'plugin_slug' => 'news-crawler'
            ) ),
            'timeout' => 30,
            'sslverify' => true
        ) );

        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! $data ) {
            return array(
                'success' => false,
                'message' => __( 'ライセンスサーバーからの応答が無効です。', 'news-crawler' )
            );
        }

        return $data;
    }

    /**
     * Check if license is valid
     *
     * @since 2.1.5
     * @return bool True if license is valid
     */
    public function is_license_valid() {
        $license_key = get_option( 'news_crawler_license_key' );
        $license_status = get_option( 'news_crawler_license_status' );
        
        // ライセンスキーが空の場合、ステータスを確実に'not_set'にする
        if ( empty( $license_key ) ) {
            if ( $license_status !== 'not_set' ) {
                update_option( 'news_crawler_license_status', 'not_set' );
                update_option( 'news_crawler_license_info', array(
                    'message' => 'プラグインを利用するには有効なライセンスキーが必要です。',
                    'features' => array(
                        'ai_summary' => false,
                        'advanced_features' => false,
                        'basic_features' => false
                    )
                ));
                error_log( 'NewsCrawler License Check: License key is empty, setting status to not_set' );
            }
            return false;
        }

        // 開発環境の判定
        if ( $this->is_development_environment() ) {
            // 開発ライセンスが無効化されている場合は false
            if ( ! $this->is_dev_license_enabled() ) {
                error_log( 'NewsCrawler License Check: Development license is disabled by setting.' );
                return false;
            }
            
            // 開発環境でテスト用ライセンスキーが設定されている場合のみ有効
            $dev_license_key = $this->get_development_license_key();
            if ( $license_key === $dev_license_key ) {
                error_log( 'NewsCrawler License Check: Development environment with valid dev license key.' );
                return true;
            }
            
            // 開発環境でも他のライセンスキーの場合は検証が必要
            error_log( 'NewsCrawler License Check: Development environment with non-dev license key, proceeding with verification.' );
        }

        // --- 本番環境のライセンスチェックロジック ---

        $verified_at = get_option( 'news_crawler_license_verified_at' );

        // デバッグログを追加
        error_log( 'NewsCrawler License Check: license_key = set, status = ' . $license_status );

        if ( $license_status !== 'active' ) {
            error_log( 'NewsCrawler License Check: License status is not active: ' . $license_status );
            return false;
        }

        // not_setステータスの場合も明示的に無効とする
        if ( $license_status === 'not_set' ) {
            error_log( 'NewsCrawler License Check: License status is not_set' );
            return false;
        }

        // ライセンスの有効期限チェック
        if ( $verified_at && ( current_time( 'timestamp' ) - $verified_at ) > 86400 ) {
            // 24時間以上経過している場合は再検証
            $result = $this->verify_license( $license_key );
            if ( $result['success'] ) {
                update_option( 'news_crawler_license_verified_at', current_time( 'timestamp' ) );
                update_option( 'news_crawler_license_info', $result['data'] );
            } else {
                update_option( 'news_crawler_license_status', 'invalid' );
                error_log( 'NewsCrawler License Check: License verification failed during renewal check' );
                return false;
            }
        }

        return true;
    }

    /**
     * Check if AI summary functionality should be enabled
     *
     * @since 2.1.5
     * @return bool True if AI summary should be enabled
     */
    public function is_ai_summary_enabled() {
        // 開発環境では常に有効
        if ( $this->is_development_environment() ) {
            return true;
        }
        
        // 本番環境ではライセンスキーが必要
        return $this->is_license_valid();
    }

    /**
     * Check if advanced features should be enabled
     *
     * @since 2.1.5
     * @return bool True if advanced features should be enabled
     */
    public function is_advanced_features_enabled() {
        return $this->is_license_valid();
    }

    /**
     * Check if basic features should be enabled
     * Basic features require a valid license
     *
     * @since 2.1.5
     * @return bool True if basic features should be enabled
     */
    public function is_basic_features_enabled() {
        return $this->is_license_valid();
    }

    /**
     * Check if news crawling functionality should be enabled
     * News crawling requires a valid license
     *
     * @since 2.1.5
     * @return bool True if news crawling should be enabled
     */
    public function is_news_crawling_enabled() {
        return $this->is_license_valid();
    }

    /**
     * Check rate limit
     *
     * @since 2.1.5
     * @return bool True if within rate limit
     */
    private function check_rate_limit() {
        $current_time = current_time( 'timestamp' );
        $requests = get_option( 'news_crawler_license_requests', array() );
        
        // 古いリクエストを削除
        $requests = array_filter( $requests, function( $time ) use ( $current_time ) {
            return ( $current_time - $time ) < $this->rate_limit['time_window'];
        } );
        
        // リクエスト数が上限に達しているかチェック
        if ( count( $requests ) >= $this->rate_limit['max_requests'] ) {
            return false;
        }
        
        // 現在のリクエストを記録
        $requests[] = $current_time;
        update_option( 'news_crawler_license_requests', $requests );
        
        return true;
    }

    /**
     * Check if current environment is development
     *
     * @since 2.1.5
     * @return bool True if development environment
     */
    public function is_development_environment() {
        // 開発環境の判定ロジック
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $is_localhost = in_array( $host, array( 'localhost', '127.0.0.1', '::1' ) );
        $is_dev_domain = strpos( $host, '.local' ) !== false || strpos( $host, '.test' ) !== false;
        $is_dev_constant = defined( 'WP_DEBUG' ) && WP_DEBUG;
        
        // Docker環境も開発環境として認識
        $is_docker = strpos( $host, 'docker' ) !== false || strpos( $host, 'container' ) !== false;
        
        // より柔軟な開発環境判定
        $is_dev = $is_localhost || $is_dev_domain || $is_dev_constant || $is_docker;
        
        error_log('NewsCrawler License: Environment check - Host: ' . $host . ', Localhost: ' . ($is_localhost ? 'true' : 'false') . ', Dev domain: ' . ($is_dev_domain ? 'true' : 'false') . ', WP_DEBUG: ' . ($is_dev_constant ? 'true' : 'false') . ', Docker: ' . ($is_docker ? 'true' : 'false') . ', Is Dev: ' . ($is_dev ? 'true' : 'false'));
        
        return $is_dev;
    }

    /**
     * Get development license key
     *
     * @since 2.1.5
     * @return string Development license key
     */
    public function get_development_license_key() {
        // より簡単に覚えられるテスト用キー
        return 'DEV-TEST-KEY-12345';
    }

    /**
     * Check if development license is valid
     *
     * @since 2.1.5
     * @return bool True if valid
     */
    public function is_development_license_valid() {
        if ( ! $this->is_development_environment() ) {
            return false;
        }
        
        $license_key = get_option( 'news_crawler_license_key' );
        $dev_license_key = $this->get_development_license_key();
        
        return $license_key === $dev_license_key;
    }

    /**
     * AJAX: Verify license
     *
     * @since 2.1.5
     */
    public function ajax_verify_license() {
        check_ajax_referer( 'news_crawler_license_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( '権限がありません。', 'news-crawler' ) ) );
        }
        
        $license_key = sanitize_text_field( $_POST['license_key'] ?? '' );
        
        if ( empty( $license_key ) ) {
            wp_send_json_error( array( 'message' => __( 'ライセンスキーを入力してください。', 'news-crawler' ) ) );
        }
        
        $result = $this->verify_license( $license_key );
        
        if ( $result['success'] ) {
            wp_send_json_success( array( 'message' => $result['message'] ) );
        } else {
            wp_send_json_error( array( 'message' => $result['message'] ) );
        }
    }

    /**
     * AJAX: Get license info
     *
     * @since 2.1.5
     */
    public function ajax_get_license_info() {
        check_ajax_referer( 'news_crawler_license_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( '権限がありません。', 'news-crawler' ) ) );
        }
        
        $license_key = get_option( 'news_crawler_license_key' );
        
        if ( empty( $license_key ) ) {
            wp_send_json_error( array( 'message' => __( 'ライセンスキーが設定されていません。', 'news-crawler' ) ) );
        }
        
        $result = $this->get_license_info( $license_key );
        wp_send_json( $result );
    }

    /**
     * AJAX: Toggle development license
     *
     * @since 2.1.5
     */
    public function ajax_toggle_dev_license() {
        check_ajax_referer( 'news_crawler_license_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( '権限がありません。', 'news-crawler' ) ) );
        }
        
        if ( ! $this->is_development_environment() ) {
            wp_send_json_error( array( 'message' => __( '開発環境でのみ利用できます。', 'news-crawler' ) ) );
        }
        
        $enabled = get_option( 'news_crawler_dev_license_enabled', '1' );
        $new_status = ( $enabled === '1' ) ? '0' : '1';
        
        update_option( 'news_crawler_dev_license_enabled', $new_status );
        
        wp_send_json_success( array(
            'new_status' => ! $new_status
        ) );
    }

    /**
     * Check if development license is enabled via settings
     *
     * @since 2.1.5
     * @return bool True if enabled
     */
    public function is_dev_license_enabled() {
        // オプションが存在しない場合や '1' の場合は有効とみなす
        return get_option( 'news_crawler_dev_license_enabled', '1' ) === '1';
    }

    /**
     * Get license status for display
     *
     * @since 2.1.5
     * @return array License status information
     */
    public function get_license_status() {
        $license_key = get_option( 'news_crawler_license_key' );
        $license_status = get_option( 'news_crawler_license_status' );
        $license_info = get_option( 'news_crawler_license_info', array() );
        $verified_at = get_option( 'news_crawler_license_verified_at' );

        if ( empty( $license_key ) ) {
            return array(
                'status' => 'not_set',
                'message' => __( 'ライセンスキーが設定されていません。プラグインを利用するには有効なライセンスキーが必要です。', 'news-crawler' ),
                'icon' => 'dashicons-warning',
                'color' => '#f56e28'
            );
        }

        // 開発環境の特別な処理
        if ( $this->is_development_environment() ) {
            if ( $this->is_dev_license_enabled() ) {
                return array(
                    'status' => 'active_dev',
                    'message' => __( 'ライセンスが有効です。（開発環境）', 'news-crawler' ),
                    'icon' => 'dashicons-yes-alt',
                    'color' => '#46b450',
                    'info' => array_merge( $license_info, array(
                        'type' => 'development',
                        'environment' => 'development'
                    ) ),
                    'is_dev_mode' => true
                );
            } else {
                return array(
                    'status' => 'inactive_dev',
                    'message' => __( 'ライセンスが無効です。（開発環境モードで無効化中）', 'news-crawler' ),
                    'icon' => 'dashicons-warning',
                    'color' => '#f56e28',
                    'is_dev_mode' => true
                );
            }
        }

        // 本番環境、または開発用ライセンスキーが設定されていない場合

        // ライセンスステータスがactiveの場合、KLMサーバーで最新の状態を確認
        if ( $license_status === 'active' ) {
            // 検証が24時間以上古い場合、または強制再検証が必要な場合
            $needs_verification = false;
            
            if ( ! $verified_at || ( current_time( 'timestamp' ) - $verified_at ) > 86400 ) {
                $needs_verification = true;
            }
            
            // 設定ページでの表示時は常に最新状態を確認（KLMでの無効化を検出するため）
            if ( isset( $_GET['page'] ) && $_GET['page'] === 'news-crawler-license' ) {
                $needs_verification = true;
            }
            
            if ( $needs_verification ) {
                $result = $this->verify_license( $license_key );
                
                if ( $result['success'] ) {
                    // ライセンスが有効な場合、情報を更新
                    update_option( 'news_crawler_license_info', $result['data'] );
                    update_option( 'news_crawler_license_verified_at', current_time( 'timestamp' ) );
                    $license_info = $result['data'];
                } else {
                    // ライセンスが無効な場合、ステータスを更新
                    update_option( 'news_crawler_license_status', 'invalid' );
                    error_log( 'NewsCrawler License: License verification failed in get_license_status: ' . $result['message'] );
                    
                    return array(
                        'status' => 'invalid',
                        'message' => __( 'ライセンスが無効です。', 'news-crawler' ) . ' (' . $result['message'] . ')',
                        'icon' => 'dashicons-no-alt',
                        'color' => '#dc3232'
                    );
                }
            }
        }

        if ( $license_status === 'active' ) {
            return array(
                'status' => 'active',
                'message' => __( 'ライセンスが有効です。', 'news-crawler' ),
                'icon' => 'dashicons-yes-alt',
                'color' => '#46b450',
                'info' => $license_info
            );
        } else {
            return array(
                'status' => 'invalid',
                'message' => __( 'ライセンスが無効です。', 'news-crawler' ),
                'icon' => 'dashicons-no-alt',
                'color' => '#dc3232'
            );
        }
    }

    /**
     * Deactivate license
     *
     * @since 2.1.5
     */
    public function deactivate_license() {
        delete_option( 'news_crawler_license_key' );
        delete_option( 'news_crawler_license_status' );
        delete_option( 'news_crawler_license_info' );
        delete_option( 'news_crawler_license_verified_at' );
        error_log( 'NewsCrawler License: License deactivated' );
    }

    /**
     * Reset license to invalid state for testing
     *
     * @since 2.1.5
     */
    public function reset_license_for_testing() {
        update_option( 'news_crawler_license_status', 'not_set' );
        error_log( 'NewsCrawler License: License reset to not_set for testing' );
    }

    /**
     * Clear all license data for testing
     *
     * @since 2.1.5
     */
    public function clear_all_license_data() {
        delete_option( 'news_crawler_license_key' );
        delete_option( 'news_crawler_license_status' );
        delete_option( 'news_crawler_license_info' );
        delete_option( 'news_crawler_license_verified_at' );
        error_log( 'NewsCrawler License: All license data cleared for testing' );
    }

    /**
     * Set development license for testing
     *
     * @since 2.1.5
     */
    public function set_development_license() {
        if ( ! $this->is_development_environment() ) {
            error_log( 'NewsCrawler License: Cannot set development license in production environment' );
            return false;
        }

        $dev_license_key = $this->get_development_license_key();
        
        update_option( 'news_crawler_license_key', $dev_license_key );
        update_option( 'news_crawler_license_status', 'active' );
        update_option( 'news_crawler_license_info', array(
            'type' => 'development',
            'expires' => '2099-12-31',
            'sites' => 'unlimited',
            'features' => 'all'
        ) );
        update_option( 'news_crawler_license_verified_at', current_time( 'timestamp' ) );
        
        error_log( 'NewsCrawler License: Development license set successfully' );
        return true;
    }

    /**
     * Get development environment info
     *
     * @since 2.1.5
     * @return array Development environment information
     */
    public function get_development_info() {
        return array(
            'is_development' => $this->is_development_environment(),
            'host' => $_SERVER['HTTP_HOST'] ?? 'unknown',
            'dev_license_key' => $this->get_development_license_key(),
            'current_license_key' => get_option( 'news_crawler_license_key' ),
            'license_status' => get_option( 'news_crawler_license_status' ),
            'is_dev_license_active' => $this->is_development_license_valid()
        );
    }
    
    /**
     * Get development license key for display
     *
     * @since 2.1.5
     * @return string Development license key
     */
    public function get_display_dev_license_key() {
        if ( $this->is_development_environment() ) {
            return $this->get_development_license_key();
        }
        return '';
    }
}

// Initialize the license manager
NewsCrawler_License_Manager::get_instance();
