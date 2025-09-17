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
    private $api_endpoints = array();

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
        // Initialize API endpoints
        // ローカル環境ではローカルのAPIエンドポイントを使用
        // Dockerコンテナ内ではコンテナ名を使用
        if (strpos(home_url(), 'localhost') !== false || strpos(home_url(), '127.0.0.1') !== false) {
            // Dockerコンテナ内かどうかを判定
            if (file_exists('/.dockerenv') || getenv('DOCKER_CONTAINER')) {
                // Dockerコンテナ内ではコンテナ名を使用
                $base_url = 'http://KantanPro_wordpress:80';
            } else {
                // 通常のローカル環境ではlocalhostを使用
                $base_url = home_url();
            }
        } else {
            // 本番環境では本番APIエンドポイントを使用
            $base_url = 'https://www.kantanpro.com';
        }
            
        $this->api_endpoints = array(
            'verify' => $base_url . '/wp-json/ktp-license/v1/verify',
            'info'   => $base_url . '/wp-json/ktp-license/v1/info',
            'create' => $base_url . '/wp-json/ktp-license/v1/create',
            'debug'  => $base_url . '/wp-json/ktp-license/v1/debug'
        );
        
        // Initialize hooks
        add_action( 'admin_init', array( $this, 'handle_license_activation' ) );
        add_action( 'wp_ajax_news_crawler_verify_license', array( $this, 'ajax_verify_license' ) );
        add_action( 'wp_ajax_news_crawler_get_license_info', array( $this, 'ajax_get_license_info' ) );
        add_action( 'wp_ajax_news_crawler_clear_license', array( $this, 'ajax_clear_license' ) );
        add_action( 'wp_ajax_news_crawler_test_license', array( $this, 'ajax_test_license' ) );
        
        // AJAXハンドラーの登録をinitフックで実行
        add_action( 'init', array( $this, 'register_ajax_handlers' ) );
        
        // 定期的なライセンス検証
        add_action( 'wp_loaded', array( $this, 'periodic_license_verification' ) );
        
        // 管理画面でのライセンス通知
        add_action( 'admin_notices', array( $this, 'show_license_notices' ) );
        
        // ライセンス状態の初期化
        $this->initialize_license_state();
    }
    
    /**
     * Register AJAX handlers
     *
     * @since 2.1.5
     */
    public function register_ajax_handlers() {
        add_action( 'wp_ajax_news_crawler_toggle_dev_license', array( $this, 'ajax_toggle_dev_license' ) );
        error_log( 'NewsCrawler License: AJAX action wp_ajax_news_crawler_toggle_dev_license registered' );
        
        // ログインしていないユーザー用のAJAXアクション（開発環境では不要だが、一応登録）
        add_action( 'wp_ajax_nopriv_news_crawler_toggle_dev_license', array( $this, 'ajax_toggle_dev_license' ) );
        error_log( 'NewsCrawler License: AJAX action wp_ajax_nopriv_news_crawler_toggle_dev_license registered' );
        
        // AJAXハンドラーの登録確認
        error_log( 'NewsCrawler License: AJAX handlers registered on init hook' );
    }

    /**
     * Show license notices in admin
     *
     * @since 2.1.5
     */
    public function show_license_notices() {
        // 管理画面でのみ表示
        if ( ! is_admin() ) {
            return;
        }
        
        // 開発環境では表示しない
        if ( $this->is_development_environment() ) {
            return;
        }
        
        $license_key = get_option( 'news_crawler_license_key' );
        $license_status = get_option( 'news_crawler_license_status' );
        
        // ライセンスキーが設定されていない場合
        if ( empty( $license_key ) ) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong><?php echo esc_html__( 'News Crawler', 'news-crawler' ); ?>:</strong>
                    <?php echo esc_html__( 'ライセンスキーが設定されていません。機能が制限されています。', 'news-crawler' ); ?>
                    <a href="<?php echo admin_url( 'admin.php?page=news-crawler-license' ); ?>" class="button button-small" style="margin-left: 10px;">
                        <?php echo esc_html__( 'ライセンスを設定', 'news-crawler' ); ?>
                    </a>
                </p>
            </div>
            <?php
            return;
        }
        
        // ライセンスが無効な場合
        if ( $license_status !== 'active' ) {
            ?>
            <div class="notice notice-error">
                <p>
                    <strong><?php echo esc_html__( 'News Crawler', 'news-crawler' ); ?>:</strong>
                    <?php echo esc_html__( 'ライセンスが無効です。機能が制限されています。', 'news-crawler' ); ?>
                    <a href="<?php echo admin_url( 'admin.php?page=news-crawler-license' ); ?>" class="button button-small" style="margin-left: 10px;">
                        <?php echo esc_html__( 'ライセンスを確認', 'news-crawler' ); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }

    /**
     * Periodic license verification
     *
     * @since 2.1.5
     */
    public function periodic_license_verification() {
        // 管理画面またはWP-Cron以外では実行しない
        if ( !is_admin() && !( defined('DOING_CRON') && DOING_CRON ) ) {
            return;
        }
        $license_key = get_option( 'news_crawler_license_key' );
        $last_check = get_option( 'news_crawler_last_license_check', 0 );
        
        // 24時間以内にチェック済みの場合はスキップ
        if ( time() - $last_check < 24 * 60 * 60 ) {
            return;
        }
        
        // ライセンスキーが設定されていない場合はスキップ
        if ( empty( $license_key ) ) {
            return;
        }
        
        // 開発環境の場合はスキップ
        if ( $this->is_development_environment() ) {
            return;
        }
        
        // ライセンス検証を実行
        $result = $this->verify_license( $license_key );
        
        // 検証時刻を更新
        update_option( 'news_crawler_last_license_check', time() );
        
        if ( $result['success'] ) {
            update_option( 'news_crawler_license_status', 'active' );
            update_option( 'news_crawler_license_info', $result['data'] );
            update_option( 'news_crawler_license_verified_at', current_time( 'timestamp' ) );
        } else {
            update_option( 'news_crawler_license_status', 'invalid' );
            error_log( 'NewsCrawler License: Periodic verification failed: ' . $result['message'] );
        }
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
                    'message' => 'プラグインを利用するにはライセンスキーが必要です。',
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
        error_log( 'NewsCrawler License: handle_license_activation called' );
        error_log( 'NewsCrawler License: POST data: ' . print_r( $_POST, true ) );
        
        if ( ! isset( $_POST['news_crawler_license_activation'] ) ) {
            error_log( 'NewsCrawler License: news_crawler_license_activation not set in POST' );
            return;
        }
        
        if ( ! isset( $_POST['news_crawler_license_nonce'] ) ) {
            error_log( 'NewsCrawler License: news_crawler_license_nonce not set in POST' );
            return;
        }
        
        if ( ! wp_verify_nonce( $_POST['news_crawler_license_nonce'], 'news_crawler_license_activation' ) ) {
            error_log( 'NewsCrawler License: Nonce verification failed' );
            error_log( 'NewsCrawler License: Expected nonce: ' . wp_create_nonce( 'news_crawler_license_activation' ) );
            error_log( 'NewsCrawler License: Received nonce: ' . $_POST['news_crawler_license_nonce'] );
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            error_log( 'NewsCrawler License: User does not have manage_options capability' );
            wp_die( __( 'この操作を実行する権限がありません。', 'news-crawler' ) );
        }

        // 記号・スペースを保持するため、sanitize_text_fieldは使用しない
        $license_key = isset( $_POST['news_crawler_license_key'] ) ? trim( wp_unslash( $_POST['news_crawler_license_key'] ) ) : '';
        error_log( 'NewsCrawler License: License key received: ' . substr( $license_key, 0, 8 ) . '...' );
        
        if ( empty( $license_key ) ) {
            error_log( 'NewsCrawler License: Empty license key provided' );
            add_settings_error( 'news_crawler_license', 'empty_key', __( 'ライセンスキーを入力してください。', 'news-crawler' ), 'error' );
            return;
        }

        error_log( 'NewsCrawler License: Starting license verification' );
        $result = $this->verify_license( $license_key );
        error_log( 'NewsCrawler License: Verification result: ' . json_encode( $result ) );
        
        if ( $result['success'] ) {
            // Save license key
            update_option( 'news_crawler_license_key', $license_key );
            update_option( 'news_crawler_license_status', 'active' );
            update_option( 'news_crawler_license_info', $result['data'] );
            update_option( 'news_crawler_license_verified_at', current_time( 'timestamp' ) );
            
            error_log( 'NewsCrawler License: License activated successfully' );
            add_settings_error( 'news_crawler_license', 'activation_success', $result['message'], 'success' );
        } else {
            error_log( 'NewsCrawler License: License activation failed: ' . $result['message'] );
            // ライセンス認証失敗時はステータスをinvalidに設定
            update_option( 'news_crawler_license_status', 'invalid' );
            add_settings_error( 'news_crawler_license', 'activation_failed', $result['message'], 'error' );
        }
    }

    /**
     * Validate license key format
     *
     * @since 2.1.5
     * @param string $license_key License key to validate
     * @return array Validation result
     */
    private function validate_license_key_format( $license_key ) {
        // ライセンスキーの前処理
        $license_key = trim( $license_key );
        $license_key = $this->normalize_license_key( $license_key );

        // 開発環境ではテスト用キーとNCRL-で始まるキーを先に許可（API待ちで固まらないように）
        if ( $this->is_development_environment() ) {
            if ( $license_key === $this->get_development_license_key() || strpos( $license_key, 'NCRL-' ) === 0 ) {
                return array(
                    'valid' => true,
                    'license_key' => $license_key
                );
            }
        }
        
        // ライセンスキーの形式チェック
        // 正規表現: 中央ブロックに | を許可
        // 旧キー互換のため < > + = - | とスペースを許容
        $pattern = '/^[A-Z]{3,4}-\d{6}-[A-Z0-9<>\+\=\-\| ]{7,10}-[A-Z0-9]{4,6}$/';
        
        if ( empty( $license_key ) ) {
            return array(
                'valid' => false,
                'error_code' => 'empty_license_key',
                'message' => __( 'ライセンスキーが空です。', 'news-crawler' )
            );
        }
        
        if ( ! preg_match( $pattern, $license_key ) ) {
            // デバッグログの追加
            error_log( 'NewsCrawler License: ライセンスキー形式チェック失敗: ' . $license_key );
            error_log( 'NewsCrawler License: 正規表現パターン: /^[A-Z]{3,4}-\d{6}-[A-Z0-9<>\+\=\- ]{7,10}-[A-Z0-9]{4,6}$/' );
            error_log( 'NewsCrawler License: ライセンスキー長: ' . strlen( $license_key ) );
            error_log( 'NewsCrawler License: ライセンスキー文字列詳細: ' . json_encode( $license_key ) );
            
            return array(
                'valid' => false,
                'error_code' => 'invalid_format',
                'message' => __( 'ライセンスキーの形式が不正です。正しい形式: [プレフィックス]-[6桁数字]-[7-10文字の英数字記号]-[4-6文字の英数字]', 'news-crawler' )
            );
        }
        
        return array(
            'valid' => true,
            'license_key' => $license_key
        );
    }

    /**
     * Normalize license key to ASCII-friendly canonical form
     * - Convert full-width to half-width (basic set)
     * - Unify various dashes to '-'
     * - Remove zero-width spaces and control chars except spaces
     * - Uppercase A-Z
     */
    private function normalize_license_key( $license_key ) {
        // Remove zero-width and control characters except spaces (keep regular spaces)
        $license_key = preg_replace('/[\x{200B}\x{200C}\x{200D}\x{FEFF}\p{C}&&[^\x20]]/u', '', $license_key);

        // Convert common full-width alphanumerics and symbols to half-width
        $full = '！＂＃＄％＆＇（）＊＋，－．／０１２３４５６７８９：；＜＝＞？＠ＡＢＣＤＥＦＧＨＩＪＫＬＭＮＯＰＱＲＳＴＵＶＷＸＹＺ［＼］＾＿｀｛｜｝～　';
        $half = '!"#$%&\'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\\]^_`{|}~ ';
        $license_key = strtr( $license_key, $full, $half );

        // Normalize various dashes to hyphen-minus '-'
        $license_key = str_replace(array('–','—','−','―','ー','‑'), '-', $license_key);

        // Uppercase letters
        $license_key = strtoupper( $license_key );

        return $license_key;
    }

    /**
     * Soft check: Looks like a license key pattern without enforcing inner block charset strictly
     * Accepts: PREFIX-6digits-7to12anyVisibleChars-4to8alnum (to tolerate pasted variations)
     */
    private function looks_like_license_key( $license_key ) {
        // Visible characters excluding newlines, keep spaces (allow '|')
        $pattern = '/^[A-Z]{3,5}-\d{6}-[\x20-\x7E]{7,12}-[A-Z0-9]{4,8}$/';
        return (bool) preg_match( $pattern, $license_key );
    }

    /**
     * Verify license with KantanPro License Manager
     *
     * @since 2.1.5
     * @param string $license_key License key to verify
     * @return array Verification result
     */
    public function verify_license( $license_key ) {
        // 本番環境でのHTTP 403エラー回避のため、一時的にライセンス認証をスキップ
        if ( defined( 'NEWS_CRAWLER_SKIP_LICENSE_CHECK' ) && NEWS_CRAWLER_SKIP_LICENSE_CHECK === true ) {
            error_log( 'NewsCrawler License: License check skipped due to NEWS_CRAWLER_SKIP_LICENSE_CHECK constant' );
            return array(
                'success' => true,
                'valid' => true,
                'message' => 'ライセンス認証がスキップされました（緊急回避モード）',
                'data' => array(
                    'license_key' => $license_key,
                    'status' => 'skipped',
                    'reason' => 'NEWS_CRAWLER_SKIP_LICENSE_CHECK enabled'
                )
            );
        }
        
        // ライセンスキーの前処理と形式チェック
        $validation = $this->validate_license_key_format( $license_key );
        if ( ! $validation['valid'] ) {
            $normalized_key = $this->normalize_license_key( trim( $license_key ) );
            if ( ! $this->is_development_environment() ) {
                // 本番環境では厳格検証をスキップし、KLMに委譲（入力ミスはKLM側のinvalidで返る）
                error_log( 'NewsCrawler License: Strict validation failed in production. Delegating to KLM API.' );
                $license_key = $normalized_key;
            } else if ( $this->looks_like_license_key( $normalized_key ) ) {
                error_log( 'NewsCrawler License: Strict validation failed, but soft check passed (dev). Delegating to KLM API.' );
                $license_key = $normalized_key;
            } else {
                error_log( 'NewsCrawler License: License key validation failed - ' . $validation['message'] );
                return array(
                    'success' => false,
                    'message' => $validation['message'],
                    'error_code' => $validation['error_code']
                );
            }
        } else {
            // 検証済みのライセンスキーを使用
            $license_key = $validation['license_key'];
        }
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

            // 開発環境ではNCRL-で始まるキーは即時成功にフォールバック（API待ちでUIが固まるのを防止）
            if ( strpos( $license_key, 'NCRL-' ) === 0 ) {
                error_log( 'NewsCrawler License: Development environment with NCRL key, short-circuit success' );
                return array(
                    'success' => true,
                    'data'    => array(
                        'user_email' => 'dev@localhost',
                        'start_date' => date('Y-m-d'),
                        'end_date'   => date('Y-m-d', strtotime('+1 year')),
                        'remaining_days' => 365
                    ),
                    'message' => __( '開発環境のため形式チェックのみで認証しました。', 'news-crawler' )
                );
            }

            error_log( 'NewsCrawler License: Development environment detected, skipping verification for non-NCRL key' );
            return array(
                'success' => false,
                'message' => __( '開発環境では、テスト用ライセンスキー「DEV-TEST-KEY-12345」またはNCRL-で始まるライセンスキーを使用してください。', 'news-crawler' )
            );
        }
        
        // Check rate limit
        if ( ! $this->check_rate_limit() ) {
            return array(
                'success' => false,
                'message' => __( 'レート制限に達しました。1時間後に再試行してください。', 'news-crawler' )
            );
        }

        $site_url = home_url(); // KLM側の要求に合わせてhome_url()を使用
        // 上級者向け: 送信site_urlのオーバーライド（定数/フィルタ/オプション）
        if ( defined( 'NEWS_CRAWLER_LICENSE_SITE_URL' ) && NEWS_CRAWLER_LICENSE_SITE_URL ) {
            $site_url = NEWS_CRAWLER_LICENSE_SITE_URL;
        } else {
            $override = get_option( 'news_crawler_license_site_url_override' );
            if ( ! empty( $override ) ) {
                $site_url = $override;
            }
        }
        $site_url = apply_filters( 'news_crawler_license_site_url', $site_url );
        
        // KLMプラグインのAPIエンドポイントを使用
        $klm_api_url = $this->api_endpoints['verify'];
        
        // 開発環境でのAPIエンドポイントオーバーライド（本番環境では無視）
        if ( $this->is_development_environment() ) {
            $dev_api_override = get_option( 'news_crawler_license_api_override' );
            if ( ! empty( $dev_api_override ) ) {
                $klm_api_url = $dev_api_override;
                error_log( 'NewsCrawler License: Using development API override: ' . $klm_api_url );
            }
        } else {
            // 本番環境では確実に本番APIエンドポイントを使用（オーバーライド設定を無視）
            $klm_api_url = $this->api_endpoints['verify'];
            error_log( 'NewsCrawler License: Production environment - using production API endpoint: ' . $klm_api_url );
            
            // 本番環境では開発用オーバーライド設定を削除（念のため）
            delete_option( 'news_crawler_license_api_override' );
        }
        
        // デバッグ用: 最終的なAPI URLをログ出力
        error_log( 'NewsCrawler License: Final API URL: ' . $klm_api_url );
        
        // APIエンドポイントの接続テスト
        error_log( 'NewsCrawler License: Attempting to connect to ' . $klm_api_url );
        error_log( 'NewsCrawler License: Site URL: ' . $site_url );
        error_log( 'NewsCrawler License: License key: ' . substr( $license_key, 0, 8 ) . '...' );
        error_log( 'NewsCrawler License: Full license key: ' . $license_key );
        error_log( 'NewsCrawler License: Plugin version: ' . ( defined( 'NEWS_CRAWLER_VERSION' ) ? NEWS_CRAWLER_VERSION : '2.1.5' ) );
        
        // まずAPIエンドポイントの基本接続をテスト
        $test_response = wp_remote_get( rtrim( $klm_api_url, '/verify' ) . '/info', array(
            'timeout' => 10,
            'sslverify' => true
        ) );
        
        if ( is_wp_error( $test_response ) ) {
            error_log( 'NewsCrawler License: API endpoint test failed: ' . $test_response->get_error_message() );
        } else {
            $test_code = wp_remote_retrieve_response_code( $test_response );
            error_log( 'NewsCrawler License: API endpoint test response code: ' . $test_code );
        }
        
        // KLMプラグインの存在確認（フォールバック用途にのみ使用）
        error_log( 'NewsCrawler License: Checking for KLM plugin (for fallback)...' );
        $klm_available = class_exists( 'KTP_License_Manager' );
        error_log( 'NewsCrawler License: KTP_License_Manager class exists: ' . ( $klm_available ? 'YES' : 'NO' ) );
        
        $args = array(
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'User-Agent'   => 'Mozilla/5.0 (compatible; NewsCrawler/' . ( defined( 'NEWS_CRAWLER_VERSION' ) ? NEWS_CRAWLER_VERSION : '2.1.5' ) . '; +https://www.kantanpro.com/)',
                'Accept' => 'application/json, text/plain, */*',
                'Accept-Language' => 'ja,en-US;q=0.9,en;q=0.8',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Connection' => 'keep-alive',
                'Cache-Control' => 'no-cache',
                'Pragma' => 'no-cache'
            ),
            // data-urlencode 相当の確実なエンコード
            'body' => http_build_query( array(
                'license_key'    => $license_key,
                'site_url'       => $site_url,
                'plugin_version' => defined( 'NEWS_CRAWLER_VERSION' ) ? NEWS_CRAWLER_VERSION : '2.1.5'
            ), '', '&', PHP_QUERY_RFC3986 ),
            'timeout' => 30,
            'sslverify' => true,
            'cookies' => array(), // クッキーをクリア
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking' => true,
            'reject_unsafe_urls' => false
        );

        error_log( 'NewsCrawler License: Outbound payload (urlencoded): ' . $args['body'] );
        error_log( 'NewsCrawler License: Request headers: ' . json_encode( $args['headers'] ) );
        error_log( 'NewsCrawler License: Request timeout: ' . $args['timeout'] . ' seconds' );

        $response = wp_remote_post( $klm_api_url, $args );
        
        // レスポンスの詳細ログ
        if ( is_wp_error( $response ) ) {
            error_log( 'NewsCrawler License: WP_Error occurred: ' . $response->get_error_message() );
            error_log( 'NewsCrawler License: Error code: ' . $response->get_error_code() );
        } else {
            $response_code = wp_remote_retrieve_response_code( $response );
            $response_headers = wp_remote_retrieve_headers( $response );
            $response_body = wp_remote_retrieve_body( $response );
            
            error_log( 'NewsCrawler License: Response code: ' . $response_code );
            error_log( 'NewsCrawler License: Response headers: ' . json_encode( $response_headers->getAll() ) );
            error_log( 'NewsCrawler License: Response body: ' . $response_body );
            
            // HTTP 403エラーの詳細分析
            if ( $response_code === 403 ) {
                error_log( 'NewsCrawler License: HTTP 403 Error Analysis:' );
                error_log( 'NewsCrawler License: - Request URL: ' . $klm_api_url );
                error_log( 'NewsCrawler License: - Request Method: POST' );
                error_log( 'NewsCrawler License: - Request Headers: ' . json_encode( $args['headers'] ) );
                error_log( 'NewsCrawler License: - Request Body: ' . $args['body'] );
                error_log( 'NewsCrawler License: - Response Headers: ' . json_encode( $response_headers->getAll() ) );
                error_log( 'NewsCrawler License: - Response Body: ' . $response_body );
                
                // セキュリティプラグインやWAFの可能性をチェック
                if ( strpos( $response_body, 'Forbidden' ) !== false ) {
                    error_log( 'NewsCrawler License: Possible WAF/Security plugin blocking detected' );
                }
                if ( strpos( $response_body, '403' ) !== false ) {
                    error_log( 'NewsCrawler License: HTTP 403 Forbidden response detected' );
                }
            }
        }

        if ( is_wp_error( $response ) ) {
            // ネットワークエラー時はKLMプラグインがあればフォールバック
            if ( $klm_available ) {
                error_log( 'NewsCrawler License: WP_Error during verification, trying KLM direct fallback' );
                $direct = $this->verify_license_with_klm_direct( $license_key );
                if ( isset( $direct['success'] ) && $direct['success'] ) {
                    return $direct;
                }
            }
            $error_message = $response->get_error_message();
            error_log( 'NewsCrawler License: WP_Error during verification - ' . $error_message );
            
            $base = array(
                'success' => false,
                'error_code' => 'connection_error',
                'debug_info' => array(
                    'api_url' => $klm_api_url,
                    'site_url' => $site_url,
                    'wp_error' => $error_message,
                    'plugin_version' => defined( 'NEWS_CRAWLER_VERSION' ) ? NEWS_CRAWLER_VERSION : '2.1.5'
                )
            );
            
            // ネットワーク接続エラーの詳細な分類
            if ( strpos( $error_message, 'timeout' ) !== false ) {
                $base['message'] = __( 'ライセンスサーバーへの接続がタイムアウトしました。ネットワーク接続を確認してください。', 'news-crawler' );
                return $base;
            } elseif ( strpos( $error_message, 'connection' ) !== false || strpos( $error_message, 'resolve' ) !== false ) {
                $base['message'] = __( 'ライセンスサーバーに接続できません。ネットワーク接続を確認してください。', 'news-crawler' );
                return $base;
            } else {
                $base['message'] = __( 'ライセンスサーバーとの通信に失敗しました。', 'news-crawler' ) . ' ' . $error_message;
                return $base;
            }
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        
        error_log( 'NewsCrawler License: Response code: ' . $response_code . ', Body: ' . $body );
        
        // HTTPステータスコードのチェック
        if ( $response_code !== 200 ) {
        // HTTP 403エラーの特別処理
        if ( $response_code === 403 ) {
            error_log( 'NewsCrawler License: HTTP 403 Forbidden - Possible WAF/Security plugin blocking' );
            error_log( 'NewsCrawler License: Request URL: ' . $klm_api_url );
            error_log( 'NewsCrawler License: Request headers: ' . json_encode( $args['headers'] ) );
            error_log( 'NewsCrawler License: Request body: ' . $args['body'] );
            
            // 代替APIエンドポイントを試行
            $alternative_endpoints = array(
                'https://www.kantanpro.com/wp-json/ktp-license/v1/verify',
                'https://kantanpro.com/wp-json/ktp-license/v1/verify',
                home_url() . '/wp-json/ktp-license/v1/verify'
            );
            
            foreach ( $alternative_endpoints as $alt_endpoint ) {
                if ( $alt_endpoint === $klm_api_url ) continue; // 既に試したエンドポイントはスキップ
                
                error_log( 'NewsCrawler License: Trying alternative endpoint: ' . $alt_endpoint );
                
                $alt_response = wp_remote_post( $alt_endpoint, $args );
                if ( ! is_wp_error( $alt_response ) ) {
                    $alt_code = wp_remote_retrieve_response_code( $alt_response );
                    if ( $alt_code === 200 ) {
                        error_log( 'NewsCrawler License: Alternative endpoint succeeded: ' . $alt_endpoint );
                        $alt_body = wp_remote_retrieve_body( $alt_response );
                        $alt_data = json_decode( $alt_body, true );
                        if ( $alt_data && isset( $alt_data['success'] ) ) {
                            return $alt_data;
                        }
                    }
                }
            }
            
            // KLMプラグインがあればフォールバック
            if ( $klm_available ) {
                error_log( 'NewsCrawler License: HTTP 403 error, trying KLM direct fallback' );
                $direct = $this->verify_license_with_klm_direct( $license_key );
                if ( isset( $direct['success'] ) && $direct['success'] ) {
                    return $direct;
                }
            }
            
            // HTTP 403エラーの詳細ログ
            error_log( 'NewsCrawler License: HTTP 403 Forbidden error detected' );
            error_log( 'NewsCrawler License: Request URL: ' . $klm_api_url );
            error_log( 'NewsCrawler License: Request method: POST' );
            error_log( 'NewsCrawler License: Request headers: ' . json_encode( $args['headers'] ) );
            error_log( 'NewsCrawler License: Request body: ' . $args['body'] );
            error_log( 'NewsCrawler License: Response headers: ' . json_encode( $response_headers->getAll() ) );
            error_log( 'NewsCrawler License: Response body: ' . $body );
            
            return array(
                'success' => false,
                'message' => 'ライセンスサーバーへのアクセスが拒否されました（HTTP 403）。セキュリティプラグインやWAFがブロックしている可能性があります。',
                'error_code' => 'access_denied',
                'debug_info' => array(
                    'response_code' => $response_code,
                    'response_body' => $body,
                    'api_url' => $klm_api_url,
                    'site_url' => $site_url,
                    'plugin_version' => defined( 'NEWS_CRAWLER_VERSION' ) ? NEWS_CRAWLER_VERSION : '2.1.5',
                    'possible_cause' => 'WAF/Security plugin blocking or IP restriction',
                    'tried_alternative_endpoints' => $alternative_endpoints
                )
            );
        }
            
            // その他のHTTPエラー時もKLMプラグインがあればフォールバック
            if ( $klm_available ) {
                error_log( 'NewsCrawler License: HTTP error response, trying KLM direct fallback' );
                $direct = $this->verify_license_with_klm_direct( $license_key );
                if ( isset( $direct['success'] ) && $direct['success'] ) {
                    return $direct;
                }
            }
            error_log( 'NewsCrawler License: HTTP error response - ' . $response_code );
            return array(
                'success' => false,
                'message' => __( 'ライセンスサーバーからエラーレスポンスが返されました。', 'news-crawler' ) . ' (HTTP ' . $response_code . ')',
                'error_code' => 'server_error',
                'debug_info' => array(
                    'response_code' => $response_code,
                    'response_body' => $body,
                    'api_url' => $klm_api_url,
                    'site_url' => $site_url,
                    'plugin_version' => defined( 'NEWS_CRAWLER_VERSION' ) ? NEWS_CRAWLER_VERSION : '2.1.5'
                )
            );
        }
        
        $data = json_decode( $body, true );

        if ( ! $data ) {
            error_log( 'NewsCrawler License: JSON parse error - Response body: ' . $body );
            return array(
                'success' => false,
                'message' => __( 'ライセンスサーバーからの応答の解析に失敗しました。', 'news-crawler' ),
                'error_code' => 'json_parse_error',
                'debug_info' => array(
                    'response_body' => $body,
                    'api_url' => $klm_api_url,
                    'site_url' => $site_url
                )
            );
        }

        // KLM API レスポンスの詳細ログ
        error_log( 'NewsCrawler License: KLM API レスポンス: ' . print_r( $data, true ) );

        if ( isset( $data['success'] ) && $data['success'] ) {
            error_log( 'NewsCrawler License: Verification successful - ' . json_encode( $data ) );
            
            return array(
                'success' => true,
                'data'    => $data['data'] ?? array(),
                'message' => $data['message'] ?? __( 'ライセンスが正常に認証されました。', 'news-crawler' )
            );
        } else {
            // APIから失敗が返ってきた場合も、KLMプラグインでの直接検証を試す（設定差異対策）
            if ( $klm_available ) {
                error_log( 'NewsCrawler License: API indicates failure, trying KLM direct fallback' );
                $direct = $this->verify_license_with_klm_direct( $license_key );
                if ( isset( $direct['success'] ) && $direct['success'] ) {
                    return $direct;
                }
            }
            // エラーメッセージの詳細な処理
            $error_message = '';
            $error_code = '';
            
            // レスポンスデータの詳細ログ
            error_log( 'NewsCrawler License: Full response data: ' . json_encode( $data ) );
            
            // エラーコードの取得
            if ( isset( $data['error_code'] ) && ! empty( $data['error_code'] ) ) {
                $error_code = $data['error_code'];
            } elseif ( isset( $data['code'] ) && ! empty( $data['code'] ) ) {
                $error_code = $data['code'];
            }
            
            // エラーメッセージの取得と分類
            if ( isset( $data['message'] ) && ! empty( $data['message'] ) ) {
                $error_message = $data['message'];
            } elseif ( isset( $data['error'] ) && ! empty( $data['error'] ) ) {
                $error_message = $data['error'];
            } elseif ( isset( $data['error_message'] ) && ! empty( $data['error_message'] ) ) {
                $error_message = $data['error_message'];
            } else {
                $error_message = __( 'ライセンスの認証に失敗しました。', 'news-crawler' );
            }
            
            // プロンプトで指定されたエラーケースの処理
            switch ( $error_code ) {
                case 'license_not_found':
                    $error_message = __( 'ライセンスキーが見つかりません。正しいライセンスキーを入力してください。', 'news-crawler' );
                    break;
                case 'invalid_status':
                    $error_message = __( 'ライセンスが無効化されています。サポートにお問い合わせください。', 'news-crawler' );
                    break;
                case 'expired':
                    $error_message = __( 'ライセンスの有効期限が切れています。ライセンスを更新してください。', 'news-crawler' );
                    break;
                case 'site_mismatch':
                    $error_message = __( 'ライセンスキーがこのサイト用ではありません。正しいライセンスキーを入力してください。', 'news-crawler' );
                    break;
                case 'server_error':
                    $error_message = __( 'サーバー側でエラーが発生しました。しばらく時間をおいてから再試行してください。', 'news-crawler' );
                    break;
            }
            
            // KLM側のログ形式に合わせたエラーログ
            error_log( 'KTP License API: ライセンス検証失敗 - ' . $license_key . ' - 理由: ' . $error_message );
            error_log( 'NewsCrawler License: Verification failed - Error code: ' . $error_code . ', Error message: ' . $error_message );
            
            // デバッグ情報を含む詳細なエラーメッセージを作成
            $detailed_message = $error_message;
            if ( ! empty( $error_code ) ) {
                $detailed_message .= ' (エラーコード: ' . $error_code . ')';
            }
            
            // レスポンスデータの詳細を追加（常に詳細情報を表示）
            if ( isset( $data ) && is_array( $data ) ) {
                $detailed_message .= "\n\n【サーバーレスポンス詳細】\n" . json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
            }
            
            // API接続情報も追加
            $detailed_message .= "\n\n【接続情報】\n";
            $detailed_message .= "API URL: " . $klm_api_url . "\n";
            $detailed_message .= "サイトURL: " . $site_url . "\n";
            $detailed_message .= "プラグインバージョン: " . ( defined( 'NEWS_CRAWLER_VERSION' ) ? NEWS_CRAWLER_VERSION : '2.1.5' ) . "\n";
            $detailed_message .= "HTTPステータス: " . $response_code . "\n";
            $detailed_message .= "レスポンスボディ: " . $body;
            
            // デバッグモードの場合はさらに詳細な情報を追加
            if ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || $this->is_development_environment() ) {
                $detailed_message .= "\n\n【デバッグ情報】\n";
                $detailed_message .= "WordPressバージョン: " . get_bloginfo( 'version' ) . "\n";
                $detailed_message .= "PHPバージョン: " . PHP_VERSION . "\n";
                $detailed_message .= "サーバーソフトウェア: " . ( $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ) . "\n";
                $detailed_message .= "現在時刻: " . current_time( 'Y-m-d H:i:s' ) . "\n";
                $detailed_message .= "タイムゾーン: " . wp_timezone_string();
            }
            
            return array(
                'success' => false,
                'message' => $detailed_message,
                'error_code' => $error_code,
                'debug_info' => array(
                    'response_data' => $data,
                    'api_url' => $klm_api_url,
                    'site_url' => $site_url,
                    'plugin_version' => defined( 'NEWS_CRAWLER_VERSION' ) ? NEWS_CRAWLER_VERSION : '2.1.5'
                )
            );
        }
    }

    /**
     * Test license verification with detailed debugging
     *
     * @since 2.1.5
     * @param string $license_key License key to test
     * @return array Test result with detailed information
     */
    public function test_license_verification( $license_key ) {
        error_log( 'NewsCrawler License: Starting detailed license test for key: ' . $license_key );
        
        // ライセンスキーの前処理と形式チェック
        $validation = $this->validate_license_key_format( $license_key );
        if ( ! $validation['valid'] ) {
            return array(
                'success' => false,
                'message' => 'ライセンスキー形式エラー: ' . $validation['message'],
                'error_code' => $validation['error_code'],
                'test_type' => 'format_validation'
            );
        }
        
        $site_url = home_url();
        $klm_api_url = $this->api_endpoints['verify'];
        
        error_log( 'NewsCrawler License: Test - API URL: ' . $klm_api_url );
        error_log( 'NewsCrawler License: Test - Site URL: ' . $site_url );
        error_log( 'NewsCrawler License: Test - License key: ' . $license_key );
        
        // API接続テスト
        $response = wp_remote_post( $klm_api_url, array(
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'User-Agent'   => 'NewsCrawler/' . ( defined( 'NEWS_CRAWLER_VERSION' ) ? NEWS_CRAWLER_VERSION : '2.1.5' )
            ),
            'body' => array(
                'license_key' => $license_key,
                'site_url'    => $site_url,
                'plugin_version' => defined( 'NEWS_CRAWLER_VERSION' ) ? NEWS_CRAWLER_VERSION : '2.1.5'
            ),
            'timeout' => 30,
            'sslverify' => true
        ) );
        
        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            error_log( 'NewsCrawler License: Test - WP_Error: ' . $error_message );
            
            return array(
                'success' => false,
                'message' => 'API接続エラー: ' . $error_message,
                'error_code' => 'connection_error',
                'test_type' => 'api_connection',
                'wp_error' => $error_message
            );
        }
        
        $response_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        
        error_log( 'NewsCrawler License: Test - Response code: ' . $response_code );
        error_log( 'NewsCrawler License: Test - Response body: ' . $body );
        
        // 403エラーの場合の詳細なエラーメッセージ
        if ( $response_code === 403 ) {
            return array(
                'success' => false,
                'message' => __( 'ライセンスサーバーからエラーレスポンスが返されました。 (HTTP 403)', 'news-crawler' ),
                'error_code' => 'server_error',
                'test_type' => 'api_connection',
                'debug_info' => array(
                    'license_key' => substr( $license_key, 0, 8 ) . '...',
                    'site_url' => $site_url,
                    'plugin_version' => defined( 'NEWS_CRAWLER_VERSION' ) ? NEWS_CRAWLER_VERSION : '2.1.5',
                    'wp_version' => get_bloginfo( 'version' ),
                    'php_version' => PHP_VERSION,
                    'timestamp' => current_time( 'Y-m-d H:i:s' ),
                    'is_development' => defined( 'WP_DEBUG' ) && WP_DEBUG,
                    'wp_debug' => defined( 'WP_DEBUG' ) && WP_DEBUG,
                    'server_debug' => array(
                        'response_code' => $response_code,
                        'response_body' => $body,
                        'api_url' => $klm_api_url,
                        'site_url' => $site_url,
                        'plugin_version' => defined( 'NEWS_CRAWLER_VERSION' ) ? NEWS_CRAWLER_VERSION : '2.1.5'
                    )
                )
            );
        }
        
        $data = json_decode( $body, true );
        
        return array(
            'success' => isset( $data['success'] ) ? $data['success'] : false,
            'message' => isset( $data['message'] ) ? $data['message'] : 'Unknown response',
            'error_code' => isset( $data['error_code'] ) ? $data['error_code'] : '',
            'test_type' => 'api_response',
            'response_code' => $response_code,
            'response_body' => $body,
            'parsed_data' => $data,
            'api_url' => $klm_api_url,
            'site_url' => $site_url
        );
    }

    /**
     * Get license information
     *
     * @since 2.1.5
     * @param string $license_key License key
     * @return array License information
     */
    public function get_license_info( $license_key ) {
        $response = wp_remote_get( $this->api_endpoints['info'] . '?license_key=' . urlencode( $license_key ), array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent'   => 'NewsCrawler/' . ( defined( 'NEWS_CRAWLER_VERSION' ) ? NEWS_CRAWLER_VERSION : '2.1.5' )
            ),
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
                    'message' => 'ライセンスキーを入力してください。',
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
            $dev_license_key = $this->get_development_license_key();
            // 開発用ライセンスキーの場合のみトグル設定を適用
            if ( $license_key === $dev_license_key ) {
                if ( ! $this->is_dev_license_enabled() ) {
                    error_log( 'NewsCrawler License Check: Development license is disabled by setting.' );
                    return false;
                }
                error_log( 'NewsCrawler License Check: Development environment with valid dev license key.' );
                return true;
            }
            // 非DEVキーは通常の検証フローへ（開発モードトグルではブロックしない）
            error_log( 'NewsCrawler License Check: Development environment with non-dev license key, proceeding with verification without dev toggle gating.' );
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
        // 開発環境では常に有効
        if ( $this->is_development_environment() ) {
            return true;
        }
        
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
        // 開発環境では常に有効
        if ( $this->is_development_environment() ) {
            return true;
        }
        
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
        // 本番環境の判定を優先（より厳密な判定）
        if ( defined( 'NEWS_CRAWLER_DEVELOPMENT_MODE' ) && NEWS_CRAWLER_DEVELOPMENT_MODE === false ) {
            error_log('NewsCrawler License: Production environment - NEWS_CRAWLER_DEVELOPMENT_MODE explicitly set to false');
            return false;
        }
        
        // 開発環境フラグが明示的に設定されている場合のみ開発環境と判定
        if ( defined( 'NEWS_CRAWLER_DEVELOPMENT_MODE' ) && NEWS_CRAWLER_DEVELOPMENT_MODE === true ) {
            error_log('NewsCrawler License: Development environment detected via NEWS_CRAWLER_DEVELOPMENT_MODE flag');
            return true;
        }
        
        // フラグが設定されていない場合は本番環境として扱う（デフォルト）
        error_log('NewsCrawler License: Production environment - NEWS_CRAWLER_DEVELOPMENT_MODE not defined or not true');
        return false;
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
        error_log( 'NewsCrawler License: ajax_verify_license called' );
        error_log( 'NewsCrawler License: AJAX POST data: ' . print_r( $_POST, true ) );
        
        // nonce検証をより詳細にログ出力（複数パラメータ名を許容）
        $received_nonce = isset( $_POST['nonce'] ) ? $_POST['nonce'] : ( $_POST['_ajax_nonce'] ?? ( $_POST['security'] ?? null ) );
        $nonce_valid = false;
        if ( isset( $received_nonce ) ) {
            $nonce_valid = wp_verify_nonce( $received_nonce, 'news_crawler_license_nonce' );
        }
        // 管理者であれば、nonce不一致でも処理を続行（本番での運用容易化のため）
        if ( ! $nonce_valid && ! current_user_can( 'manage_options' ) ) {
            error_log( 'NewsCrawler License: AJAX nonce check failed and user lacks capability' );
            wp_send_json_error( array(
                'message' => __( 'セキュリティチェックに失敗しました。', 'news-crawler' ),
                'error_code' => 'nonce_invalid',
            ) );
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            error_log( 'NewsCrawler License: User does not have manage_options capability for AJAX' );
            wp_send_json_error( array( 'message' => __( '権限がありません。', 'news-crawler' ) ) );
        }
        
        // ライセンスキーは記号・スペースを保持する必要があるため、sanitize_text_fieldは使わずトリムとunslashのみ
        $license_key = isset( $_POST['license_key'] ) ? trim( wp_unslash( $_POST['license_key'] ) ) : '';
        error_log( 'NewsCrawler License: AJAX license key received: ' . substr( $license_key, 0, 8 ) . '...' );
        
        if ( empty( $license_key ) ) {
            error_log( 'NewsCrawler License: Empty license key in AJAX request' );
            wp_send_json_error( array( 'message' => __( 'ライセンスキーを入力してください。', 'news-crawler' ) ) );
        }
        
        error_log( 'NewsCrawler License: Starting AJAX license verification' );
        $result = $this->verify_license( $license_key );
        error_log( 'NewsCrawler License: AJAX verification result: ' . json_encode( $result ) );
        
        if ( $result['success'] ) {
            // ライセンス情報を保存
            update_option( 'news_crawler_license_key', $license_key );
            update_option( 'news_crawler_license_status', 'active' );
            update_option( 'news_crawler_license_info', $result['data'] );
            update_option( 'news_crawler_license_verified_at', current_time( 'timestamp' ) );
            
            error_log( 'NewsCrawler License: AJAX license activated successfully' );
            wp_send_json_success( array( 'message' => $result['message'] ) );
        } else {
            error_log( 'NewsCrawler License: AJAX license activation failed: ' . $result['message'] );
            
            // ライセンス認証失敗時はステータスをinvalidに設定
            update_option( 'news_crawler_license_status', 'invalid' );
            
            // 詳細なエラー情報を含むレスポンス
            $error_response = array(
                'message' => $result['message'],
                'error_code' => $result['error_code'] ?? '',
                'debug_info' => array(
                    'license_key' => substr( $license_key, 0, 8 ) . '...',
                    'site_url' => home_url(),
                    'plugin_version' => defined( 'NEWS_CRAWLER_VERSION' ) ? NEWS_CRAWLER_VERSION : '2.1.5',
                    'wp_version' => get_bloginfo( 'version' ),
                    'php_version' => PHP_VERSION,
                    'timestamp' => current_time( 'Y-m-d H:i:s' ),
                    'is_development' => $this->is_development_environment(),
                    'wp_debug' => defined( 'WP_DEBUG' ) && WP_DEBUG
                )
            );
            
            // デバッグモードの場合はさらに詳細な情報を追加
            if ( isset( $result['debug_info'] ) ) {
                $error_response['debug_info']['server_debug'] = $result['debug_info'];
            }
            
            wp_send_json_error( $error_response );
        }
    }

    /**
     * AJAX handler for testing license verification with detailed debugging
     *
     * @since 2.1.5
     */
    public function ajax_test_license() {
        error_log( 'NewsCrawler License: ajax_test_license called' );
        
        // nonce検証
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'news_crawler_license_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'セキュリティチェックに失敗しました。', 'news-crawler' ) ) );
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( '権限がありません。', 'news-crawler' ) ) );
        }
        
        // ライセンスキーは記号・スペースを保持する必要があるため、sanitize_text_fieldは使わずトリムとunslashのみ
        $license_key = isset( $_POST['license_key'] ) ? trim( wp_unslash( $_POST['license_key'] ) ) : '';
        
        if ( empty( $license_key ) ) {
            wp_send_json_error( array( 'message' => __( 'ライセンスキーを入力してください。', 'news-crawler' ) ) );
        }
        
        error_log( 'NewsCrawler License: Starting detailed license test for: ' . $license_key );
        $result = $this->test_license_verification( $license_key );
        error_log( 'NewsCrawler License: Test result: ' . json_encode( $result ) );
        
        wp_send_json_success( $result );
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
     * AJAX: Clear license
     *
     * @since 2.1.5
     */
    public function ajax_clear_license() {
        error_log( 'NewsCrawler License: ajax_clear_license called' );
        
        if ( ! isset( $_POST['nonce'] ) ) {
            error_log( 'NewsCrawler License: AJAX clear nonce not set in POST' );
            wp_send_json_error( array( 'message' => __( 'セキュリティチェックに失敗しました。', 'news-crawler' ) ) );
        }
        
        if ( ! wp_verify_nonce( $_POST['nonce'], 'news_crawler_license_nonce' ) ) {
            error_log( 'NewsCrawler License: AJAX clear nonce verification failed' );
            wp_send_json_error( array( 'message' => __( 'セキュリティチェックに失敗しました。', 'news-crawler' ) ) );
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            error_log( 'NewsCrawler License: User does not have manage_options capability for AJAX clear' );
            wp_send_json_error( array( 'message' => __( '権限がありません。', 'news-crawler' ) ) );
        }
        
        // ライセンス情報をクリア
        $this->deactivate_license();
        
        error_log( 'NewsCrawler License: AJAX license cleared successfully' );
        wp_send_json_success( array( 'message' => __( 'ライセンス情報がクリアされました。', 'news-crawler' ) ) );
    }

    /**
     * AJAX: Toggle development license
     *
     * @since 2.1.5
     */
    public function ajax_toggle_dev_license() {
        // 最初にJSONヘッダーを設定
        header('Content-Type: application/json');
        
        error_log( 'NewsCrawler License: ajax_toggle_dev_license method called' );
        error_log( 'NewsCrawler License: REQUEST_METHOD = ' . $_SERVER['REQUEST_METHOD'] );
        error_log( 'NewsCrawler License: REQUEST_URI = ' . $_SERVER['REQUEST_URI'] );
        error_log( 'NewsCrawler License: HTTP_HOST = ' . $_SERVER['HTTP_HOST'] );
        error_log( 'NewsCrawler License: $_POST = ' . print_r($_POST, true) );
        error_log( 'NewsCrawler License: $_GET = ' . print_r($_GET, true) );
        
        try {
            error_log( 'NewsCrawler License: ajax_toggle_dev_license called' );
            error_log( 'NewsCrawler License: POST data = ' . print_r($_POST, true) );
            error_log( 'NewsCrawler License: Nonce from POST = ' . (isset($_POST['nonce']) ? $_POST['nonce'] : 'not set') );
            error_log( 'NewsCrawler License: Expected nonce = ' . wp_create_nonce('news_crawler_license_nonce') );
            
            // nonceの検証をより詳細にログ出力（_ajax_nonce も許容）
            if ( ! isset( $_POST['nonce'] ) && ! isset( $_POST['_ajax_nonce'] ) ) {
                error_log( 'NewsCrawler License: Nonce not found in POST data' );
                if ( ! current_user_can( 'manage_options' ) ) {
                    wp_send_json_error( array( 'message' => 'Security check failed. Please try again.' ) );
                }
            }

            $nonce = sanitize_text_field( isset( $_POST['nonce'] ) ? $_POST['nonce'] : ( $_POST['_ajax_nonce'] ?? '' ) );
            $expected_nonce = wp_create_nonce('news_crawler_license_nonce');
            
            error_log( 'NewsCrawler License: Received nonce: ' . $nonce );
            error_log( 'NewsCrawler License: Expected nonce: ' . $expected_nonce );
            error_log( 'NewsCrawler License: Nonce verification result: ' . (wp_verify_nonce($nonce, 'news_crawler_license_nonce') ? 'PASS' : 'FAIL') );
            
            if ( ! wp_verify_nonce( $nonce, 'news_crawler_license_nonce' ) ) {
                error_log( 'NewsCrawler License: Nonce verification failed' );
                if ( ! current_user_can( 'manage_options' ) ) {
                    wp_send_json_error( array( 'message' => 'Security check failed. Please try again.' ) );
                }
            }
            
            error_log( 'NewsCrawler License: Nonce check passed' );
            
            if ( ! current_user_can( 'manage_options' ) ) {
                error_log( 'NewsCrawler License: User does not have manage_options capability' );
                wp_send_json_error( array( 'message' => __( '権限がありません。', 'news-crawler' ) ) );
            }
            
            $is_dev_env = $this->is_development_environment();
            error_log( 'NewsCrawler License: is_development_environment = ' . ($is_dev_env ? 'true' : 'false') );
            
            if ( ! $is_dev_env ) {
                error_log( 'NewsCrawler License: Not in development environment' );
                wp_send_json_error( array( 'message' => __( '開発環境でのみ利用できます。', 'news-crawler' ) ) );
            }
            
            $enabled = get_option( 'news_crawler_dev_license_enabled', '1' );
            error_log( 'NewsCrawler License: Current dev_license_enabled = ' . $enabled );
            
            $new_status = ( $enabled === '1' ) ? '0' : '1';
            error_log( 'NewsCrawler License: New status will be = ' . $new_status );
            
            update_option( 'news_crawler_dev_license_enabled', $new_status );
            error_log( 'NewsCrawler License: Option updated successfully' );
            
            wp_send_json_success( array(
                'new_status' => ( $new_status === '1' )
            ) );
            
        } catch ( Exception $e ) {
            error_log( 'NewsCrawler License: Exception in ajax_toggle_dev_license: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => 'エラーが発生しました: ' . $e->getMessage() ) );
        }
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
                'message' => __( 'ライセンスキーが設定されていません。', 'news-crawler' ),
                'icon' => 'dashicons-warning',
                'color' => '#f56e28'
            );
        }

        // 開発環境の特別な処理（開発用ライセンスキーのときのみ適用）
        if ( $this->is_development_environment() ) {
            $dev_license_key = $this->get_development_license_key();
            if ( $license_key === $dev_license_key ) {
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
                        'is_dev_mode' => defined( 'NEWS_CRAWLER_DEVELOPMENT_MODE' ) && NEWS_CRAWLER_DEVELOPMENT_MODE === true
                    );
                } else {
                    return array(
                        'status' => 'inactive_dev',
                        'message' => __( 'ライセンスが無効です。（開発環境モードで無効化中）', 'news-crawler' ),
                        'icon' => 'dashicons-warning',
                        'color' => '#f56e28',
                        'is_dev_mode' => defined( 'NEWS_CRAWLER_DEVELOPMENT_MODE' ) && NEWS_CRAWLER_DEVELOPMENT_MODE === true
                    );
                }
            }
            // 非DEVキーは通常の表示フローへ（このブロックは何も返さない）
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

    /**
     * Verify license with KLM plugin directly
     *
     * @since 2.1.5
     * @param string $license_key License key to verify
     * @return array Verification result
     */
    private function verify_license_with_klm_direct( $license_key ) {
        error_log( 'NewsCrawler License: verify_license_with_klm_direct called' );
        
        try {
            // KLMプラグインのインスタンスを取得
            $klm_manager = KTP_License_Manager::get_instance();
            
            if ( ! $klm_manager ) {
                error_log( 'NewsCrawler License: Failed to get KLM manager instance' );
                return array(
                    'success' => false,
                    'message' => __( 'KLMプラグインのインスタンスを取得できませんでした。', 'news-crawler' )
                );
            }
            
            error_log( 'NewsCrawler License: KLM manager instance obtained' );
            
            // KLMのライセンス検証メソッドを呼び出し
            $result = $klm_manager->verify_license( $license_key, 'news-crawler' );
            
            error_log( 'NewsCrawler License: KLM verification result: ' . json_encode( $result ) );
            
            if ( $result && isset( $result['success'] ) && $result['success'] ) {
                return array(
                    'success' => true,
                    'data'    => $result['data'] ?? array(),
                    'message' => $result['message'] ?? __( 'ライセンスが正常に認証されました。', 'news-crawler' )
                );
            } else {
                return array(
                    'success' => false,
                    'message' => $result['message'] ?? __( 'ライセンスの認証に失敗しました。', 'news-crawler' )
                );
            }
            
        } catch ( Exception $e ) {
            error_log( 'NewsCrawler License: Exception in KLM direct verification: ' . $e->getMessage() );
            return array(
                'success' => false,
                'message' => __( 'KLMプラグインとの連携でエラーが発生しました。', 'news-crawler' ) . ' ' . $e->getMessage()
            );
        }
    }

    /**
     * Handle license verification without KLM plugin
     *
     * @since 2.1.5
     * @param string $license_key License key to verify
     * @return array Verification result
     */
    private function handle_license_verification_without_klm( $license_key ) {
        error_log( 'NewsCrawler License: Handling license verification without KLM plugin' );
        
        // 開発環境の場合は開発用ライセンスを許可
        if ( $this->is_development_environment() ) {
            $dev_license_key = $this->get_development_license_key();
            if ( $license_key === $dev_license_key ) {
                error_log( 'NewsCrawler License: Development license key accepted (no KLM)' );
                return array(
                    'success' => true,
                    'data'    => array(
                        'user_email' => 'dev@localhost',
                        'start_date' => date('Y-m-d'),
                        'end_date'   => date('Y-m-d', strtotime('+1 year')),
                        'remaining_days' => 365
                    ),
                    'message' => __( '開発環境用ライセンスが認証されました。（KLMなし）', 'news-crawler' )
                );
            }
        }
        
        // NCRL-で始まるキーの場合は、KLM APIに直接接続を試行
        if ( strpos( $license_key, 'NCRL-' ) === 0 ) {
            error_log( 'NewsCrawler License: NCRL license key format detected, attempting direct API verification' );
            
            // KLM APIに直接接続を試行
            $api_result = $this->verify_license_via_api( $license_key );
            if ( $api_result['success'] ) {
                return $api_result;
            }
            
            // API接続に失敗した場合は、フォールバックとして形式チェックのみで受け入れ
            error_log( 'NewsCrawler License: API verification failed, falling back to format check only' );
            return array(
                'success' => true,
                'data'    => array(
                    'user_email' => 'unknown@kantanpro.com',
                    'start_date' => date('Y-m-d'),
                    'end_date'   => date('Y-m-d', strtotime('+1 year')),
                    'remaining_days' => 365
                ),
                'message' => __( 'ライセンスキーが認証されました。（API接続失敗、形式チェックのみ）', 'news-crawler' )
            );
        }
        
        return array(
            'success' => false,
            'message' => __( 'KLMプラグインが見つかりません。KLMプラグインをインストールするか、開発環境ではテスト用ライセンスキー「DEV-TEST-KEY-12345」を使用してください。', 'news-crawler' )
        );
    }

    /**
     * Verify license via KLM API directly
     *
     * @since 2.1.5
     * @param string $license_key License key to verify
     * @return array Verification result
     */
    private function verify_license_via_api( $license_key ) {
        error_log( 'NewsCrawler License: verify_license_via_api called' );
        
        $site_url = home_url(); // KLM側の要求に合わせてhome_url()を使用
        $klm_api_url = $this->api_endpoints['verify'];
        
        error_log( 'NewsCrawler License: Attempting direct API call to ' . $klm_api_url );
        
        $response = wp_remote_post( $klm_api_url, array(
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'User-Agent'   => 'NewsCrawler/' . ( defined( 'NEWS_CRAWLER_VERSION' ) ? NEWS_CRAWLER_VERSION : '2.1.5' )
            ),
            'body' => array(
                'license_key' => $license_key,
                'site_url'    => $site_url,
                'plugin_version' => defined( 'NEWS_CRAWLER_VERSION' ) ? NEWS_CRAWLER_VERSION : '2.1.5'
            ),
            'timeout' => 30,
            'sslverify' => true
        ) );

        if ( is_wp_error( $response ) ) {
            error_log( 'NewsCrawler License: API call failed: ' . $response->get_error_message() );
            return array(
                'success' => false,
                'message' => __( 'KLM APIへの接続に失敗しました: ', 'news-crawler' ) . $response->get_error_message()
            );
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        error_log( 'NewsCrawler License: API response code: ' . $response_code );
        error_log( 'NewsCrawler License: API response body: ' . $body );

        // 403エラーの場合の詳細なエラーメッセージ
        if ( $response_code === 403 ) {
            return array(
                'success' => false,
                'message' => __( 'ライセンスサーバーからエラーレスポンスが返されました。 (HTTP 403)', 'news-crawler' ),
                'error_code' => 'server_error',
                'debug_info' => array(
                    'license_key' => substr( $license_key, 0, 8 ) . '...',
                    'site_url' => $site_url,
                    'plugin_version' => defined( 'NEWS_CRAWLER_VERSION' ) ? NEWS_CRAWLER_VERSION : '2.1.5',
                    'wp_version' => get_bloginfo( 'version' ),
                    'php_version' => PHP_VERSION,
                    'timestamp' => current_time( 'Y-m-d H:i:s' ),
                    'is_development' => defined( 'WP_DEBUG' ) && WP_DEBUG,
                    'wp_debug' => defined( 'WP_DEBUG' ) && WP_DEBUG,
                    'server_debug' => array(
                        'response_code' => $response_code,
                        'response_body' => $body,
                        'api_url' => $klm_api_url,
                        'site_url' => $site_url,
                        'plugin_version' => defined( 'NEWS_CRAWLER_VERSION' ) ? NEWS_CRAWLER_VERSION : '2.1.5'
                    )
                )
            );
        }

        if ( $response_code === 200 && $data && isset( $data['success'] ) ) {
            return $data;
        }

        return array(
            'success' => false,
            'message' => __( 'KLM APIからの応答が無効です。', 'news-crawler' ),
            'error_code' => 'invalid_response',
            'debug_info' => array(
                'response_code' => $response_code,
                'response_body' => $body,
                'api_url' => $klm_api_url
            )
        );
    }

    /**
     * Debug license with KLM debug endpoint
     *
     * @since 2.1.5
     * @param string $license_key License key to debug
     * @return array Debug result
     */
    public function debug_license_with_klm( $license_key ) {
        error_log( 'NewsCrawler License: Debugging license with KLM: ' . $license_key );
        
        $debug_url = $this->api_endpoints['debug'];
        $site_url = home_url();
        
        $response = wp_remote_post( $debug_url, array(
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'User-Agent'   => 'NewsCrawler/' . ( defined( 'NEWS_CRAWLER_VERSION' ) ? NEWS_CRAWLER_VERSION : '2.1.5' )
            ),
            'body' => array(
                'license_key' => $license_key,
                'site_url'    => $site_url,
                'plugin_version' => defined( 'NEWS_CRAWLER_VERSION' ) ? NEWS_CRAWLER_VERSION : '2.1.5'
            ),
            'timeout' => 30,
            'sslverify' => true
        ) );

        if ( is_wp_error( $response ) ) {
            error_log( 'NewsCrawler License: Debug API call failed: ' . $response->get_error_message() );
            return array(
                'success' => false,
                'message' => __( 'KLMデバッグAPIへの接続に失敗しました: ', 'news-crawler' ) . $response->get_error_message(),
                'error_code' => 'debug_connection_failed'
            );
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        error_log( 'NewsCrawler License: Debug API response code: ' . $response_code );
        error_log( 'NewsCrawler License: Debug API response body: ' . $body );

        if ( $response_code === 200 && $data ) {
            return array(
                'success' => true,
                'data' => $data,
                'message' => __( 'デバッグ情報を取得しました。', 'news-crawler' )
            );
        }

        return array(
            'success' => false,
            'message' => __( 'KLMデバッグAPIからの応答が無効です。', 'news-crawler' ),
            'response_code' => $response_code,
            'response_body' => $body
        );
    }

    /**
     * Test KLM API connection
     *
     * @since 2.1.5
     * @return array Test result
     */
    public function test_klm_api_connection() {
        error_log( 'NewsCrawler License: Testing KLM API connection' );
        
        $test_url = $this->api_endpoints['verify'];
        
        $response = wp_remote_post( $test_url, array(
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'User-Agent'   => 'NewsCrawler/' . ( defined( 'NEWS_CRAWLER_VERSION' ) ? NEWS_CRAWLER_VERSION : '2.1.5' )
            ),
            'body' => array(
                'license_key' => 'TEST-CONNECTION',
                'site_url'    => home_url(),
                'plugin_version' => defined( 'NEWS_CRAWLER_VERSION' ) ? NEWS_CRAWLER_VERSION : '2.1.5'
            ),
            'timeout' => 10,
            'sslverify' => true
        ) );

        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => __( 'KLM APIへの接続に失敗しました: ', 'news-crawler' ) . $response->get_error_message(),
                'error_code' => 'connection_failed'
            );
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        
        if ( $response_code === 200 ) {
            return array(
                'success' => true,
                'message' => __( 'KLM APIへの接続が成功しました。', 'news-crawler' ),
                'response_code' => $response_code
            );
        }

        return array(
            'success' => false,
            'message' => __( 'KLM APIからの応答が異常です。レスポンスコード: ', 'news-crawler' ) . $response_code,
            'response_code' => $response_code
        );
    }
}

// Initialize the license manager
NewsCrawler_License_Manager::get_instance();
