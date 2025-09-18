<?php
/**
 * License Settings class for News Crawler plugin
 *
 * Handles the license settings page display and functionality.
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
 * License Settings class for managing plugin license settings
 *
 * @since 2.1.5
 */
class NewsCrawler_License_Settings {

    /**
     * Single instance of the class
     *
     * @var NewsCrawler_License_Settings
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @since 2.1.5
     * @return NewsCrawler_License_Settings
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
        // メニューとスクリプトの読み込みはclass-genre-settings.phpで統合管理
    }

    /**
     * プラグインのバージョンを動的に取得
     */
    private function get_plugin_version() {
        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }
        $plugin_file = NEWS_CRAWLER_PLUGIN_DIR . 'news-crawler.php';
        $plugin_data = get_plugin_data( $plugin_file, false, false );
        return isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : ( defined( 'NEWS_CRAWLER_VERSION' ) ? NEWS_CRAWLER_VERSION : '' );
    }

    /**
     * Add admin menu
     *
     * @since 2.1.5
     */
    public function add_admin_menu() {
        // このクラスではメニューを追加しない（class-genre-settings.phpで統合管理）
        // ライセンス設定はNews Crawlerメインメニューのサブメニューとして表示される
    }

    /**
     * このクラスではスクリプトの読み込みも行わない（class-genre-settings.phpで統合管理）
     */

    /**
     * ライセンス設定ページの表示
     */
    public function create_license_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'この設定ページにアクセスする権限がありません。', 'news-crawler' ) );
        }

        // ライセンス状態再確認の処理
        if ( isset( $_POST['news_crawler_license_recheck'] ) && wp_verify_nonce( $_POST['news_crawler_license_recheck_nonce'], 'news_crawler_license_recheck' ) ) {
            $this->handle_license_recheck();
        }

        // ライセンスクリアの処理
        if ( isset( $_POST['news_crawler_license_clear'] ) && wp_verify_nonce( $_POST['news_crawler_license_clear_nonce'], 'news_crawler_license_clear' ) ) {
            $this->handle_license_clear();
        }

        // デバッグの処理
        if ( isset( $_POST['news_crawler_license_debug'] ) && wp_verify_nonce( $_POST['news_crawler_license_debug_nonce'], 'news_crawler_license_debug' ) ) {
            $this->handle_license_debug();
        }

        // ライセンスマネージャーのインスタンスを取得
        $license_manager = NewsCrawler_License_Manager::get_instance();
        $license_status = $license_manager->get_license_status();
        
        ?>
        <div class="wrap ktp-admin-wrap">
            <h1><span class="dashicons dashicons-lock" style="margin-right: 10px; font-size: 24px; width: 24px; height: 24px;"></span>News Crawler <?php echo esc_html($this->get_plugin_version()); ?> - <?php echo esc_html__( 'ライセンス設定', 'news-crawler' ); ?></h1>
            
            <?php
            // 通知表示
            settings_errors( 'news_crawler_license' );
            ?>
            
            <div class="ktp-settings-container">
                <div class="ktp-settings-section">
                    <!-- ライセンスステータス表示 -->
                    <div class="ktp-license-status-display" style="margin-bottom: 30px; padding: 20px; background: #fff; border: 1px solid #ddd; border-radius: 5px;">
                        <h3 style="margin-top: 0;">
                            <span class="dashicons <?php echo esc_attr( $license_status['icon'] ); ?>" style="color: <?php echo esc_attr( $license_status['color'] ); ?>;"></span>
                            <?php echo esc_html__( 'ライセンスステータス', 'news-crawler' ); ?>
                        </h3>
                    <p style="font-size: 16px; margin: 10px 0;">
                        <strong><?php echo esc_html( $license_status['message'] ); ?></strong>
                    </p>
                    
                    <?php if ( defined( 'NEWS_CRAWLER_DEVELOPMENT_MODE' ) && NEWS_CRAWLER_DEVELOPMENT_MODE === true ) : ?>
                    <!-- 開発環境モードの説明（開発環境でのみ表示） -->
                    <div class="ktp-dev-mode-info" style="margin: 15px 0; padding: 15px; background: #e7f3ff; border: 1px solid #b3d9ff; border-radius: 5px;">
                        <p style="margin: 0; font-size: 14px; color: #0066cc;">
                            <span class="dashicons dashicons-info" style="margin-right: 5px;"></span>
                            開発者モードで認証されています
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ( ! empty( $license_status['is_dev_mode'] ) ) : ?>
                            <div class="ktp-dev-mode-toggle" style="margin-top: 15px; padding: 10px; background-color: #fff8e1; border: 1px solid #ffecb3; border-radius: 4px;">
                                <p style="margin: 0; display: flex; align-items: center; justify-content: space-between;">
                                    <span><span class="dashicons dashicons-info-outline"></span> 開発環境モードで動作中です。</span>
                                    <button id="toggle-dev-license" class="button button-secondary">
                                        <?php echo $license_manager->is_dev_license_enabled() ? '開発用ライセンスを無効化' : '開発用ライセンスを有効化'; ?>
                                    </button>
                                    <span class="spinner" style="float: none; margin-left: 5px;"></span>
                                </p>
                            </div>
                        <?php endif; ?>
                        <?php if ( isset( $license_status['info'] ) && ! empty( $license_status['info'] ) ) : ?>
                            <div class="ktp-license-info-details" style="margin-top: 15px; padding: 15px; background: #f9f9f9; border-radius: 3px;">
                                <h4 style="margin-top: 0;"><?php echo esc_html__( 'ライセンス詳細', 'news-crawler' ); ?></h4>
                                <table class="form-table" style="margin: 0;">
                                    <?php
                                    // 表示する項目を制限
                                    $display_fields = array(
                                        'user_email' => 'User email',
                                        'start_date' => '開始',
                                        'end_date' => '終了',
                                        'remaining_days' => '残り日数'
                                    );
                                    
                                    foreach ( $display_fields as $key => $label ) :
                                        if ( isset( $license_status['info'][$key] ) ) :
                                    ?>
                                        <tr>
                                            <th style="padding: 5px 0; font-weight: normal;"><?php echo esc_html( $label ); ?></th>
                                            <td style="padding: 5px 0;"><?php echo esc_html( $license_status['info'][$key] ); ?></td>
                                        </tr>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- ライセンス認証フォーム -->
                    <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                        <form method="post" action="" id="news-crawler-license-form" style="display: flex; align-items: center; gap: 10px; margin: 0;">
                            <?php wp_nonce_field( 'news_crawler_license_activation', 'news_crawler_license_nonce' ); ?>
                            <input type="hidden" name="news_crawler_license_activation" value="1">

                            <label for="news_crawler_license_key" style="margin-bottom: 0;"><?php echo esc_html__( 'ライセンスキー', 'news-crawler' ); ?></label>

                            <input type="password"
                                   id="news_crawler_license_key"
                                   name="news_crawler_license_key"
                                   value="<?php echo esc_attr( get_option( 'news_crawler_license_key' ) ); ?>"
                                   style="width: 400px;"
                                   placeholder="KTPM-XXXXXX-XXXXXX-XXXX"
                                   autocomplete="off"
                                   pattern="[A-Z]{3,4}-\d{6}-[A-Z0-9<>\+\=\- ]{7,10}-[A-Z0-9]{4,6}"
                                   title="[プレフィックス]-[6桁数字]-[7-10文字の英数字記号]-[4-6文字の英数字]形式で入力してください">

                            <?php submit_button( __( 'ライセンスを認証', 'news-crawler' ), 'primary', 'submit', false, ['style' => 'margin: 0;'] ); ?>
                            <button type="button" id="test-license-btn" class="button button-secondary" style="margin-left: 10px;">
                                <?php echo esc_html__( 'ライセンスをテスト', 'news-crawler' ); ?>
                            </button>
                        </form>

                        <!-- ライセンス状態再確認フォーム -->
                        <?php if ( ! empty( get_option( 'news_crawler_license_key' ) ) ) : ?>
                            <form method="post" action="" style="margin: 0;">
                                <?php wp_nonce_field( 'news_crawler_license_recheck', 'news_crawler_license_recheck_nonce' ); ?>
                                <input type="hidden" name="news_crawler_license_recheck" value="1">
                                <?php submit_button( __( 'ライセンス状態を再確認', 'news-crawler' ), 'secondary', 'recheck_license', false, ['style' => 'margin: 0;'] ); ?>
                            </form>
                        <?php endif; ?>

                        <!-- ライセンスクリアフォーム -->
                        <?php if ( ! empty( get_option( 'news_crawler_license_key' ) ) ) : ?>
                            <form method="post" action="" style="margin: 0;">
                                <?php wp_nonce_field( 'news_crawler_license_clear', 'news_crawler_license_clear_nonce' ); ?>
                                <input type="hidden" name="news_crawler_license_clear" value="1">
                                <?php submit_button( __( 'ライセンスをクリア', 'news-crawler' ), 'secondary', 'clear_license', false, ['style' => 'margin: 0; background-color: #dc3232; border-color: #dc3232; color: white;'] ); ?>
                            </form>
                        <?php endif; ?>

                        <!-- デバッグフォーム -->
                        <?php if ( ! empty( get_option( 'news_crawler_license_key' ) ) ) : ?>
                            <form method="post" action="" style="margin: 0;">
                                <?php wp_nonce_field( 'news_crawler_license_debug', 'news_crawler_license_debug_nonce' ); ?>
                                <input type="hidden" name="news_crawler_license_debug" value="1">
                                <?php submit_button( __( 'KLMデバッグ実行', 'news-crawler' ), 'secondary', 'debug_license', false, ['style' => 'margin: 0; background-color: #0073aa; border-color: #0073aa; color: white;'] ); ?>
                            </form>
                        <?php endif; ?>

                        <!-- API接続テストボタン -->
                        <button type="button" id="test-api-connection-btn" class="button button-secondary" style="margin: 0; background-color: #00a32a; border-color: #00a32a; color: white;">
                            <?php echo esc_html__( 'API接続テスト', 'news-crawler' ); ?>
                        </button>
                    </div>

                    <p class="description" style="padding-left: 8px; margin-top: 5px;">
                        <?php echo esc_html__( 'KantanPro License Managerから取得したライセンスキーを入力してください。', 'news-crawler' ); ?>
                        <br>
                        <strong><?php echo esc_html__( '対応ライセンス形式', 'news-crawler' ); ?>:</strong> [プレフィックス]-[6桁数字]-[7-10文字の英数字記号（スペース含む）]-[4-6文字の英数字]
                        <br>
                        <strong><?php echo esc_html__( '例', 'news-crawler' ); ?>:</strong> KTPM-227598-MF7RORXLQ9-30381D, NCRL-080629-BRX<9D1+-C69B, NCRL-094228-ZGZ>A E2-FAD0
                    </p>

                    <!-- デバッグ情報（WP_DEBUGが有効な場合のみ表示） -->
                    <?php if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) : ?>
                        <div class="ktp-debug-info" style="margin-top: 30px; padding: 20px; background: #f0f0f1; border: 1px solid #c3c4c7; border-radius: 5px;">
                            <h3 style="margin-top: 0; color: #d63638;">
                                <span class="dashicons dashicons-info"></span>
                                <?php echo esc_html__( 'デバッグ情報', 'news-crawler' ); ?>
                            </h3>
                            <p style="margin: 10px 0; font-size: 14px; color: #646970;">
                                <?php echo esc_html__( '以下の情報は問題の診断に役立ちます。サポートに問い合わせる際は、この情報をお知らせください。', 'news-crawler' ); ?>
                            </p>
                            
                            <div style="background: #fff; padding: 15px; border-radius: 3px; margin-top: 15px;">
                                <h4 style="margin-top: 0;"><?php echo esc_html__( 'システム情報', 'news-crawler' ); ?></h4>
                                <table class="form-table" style="margin: 0;">
                                    <tr>
                                        <th style="padding: 5px 0; font-weight: normal; width: 150px;">WordPressバージョン</th>
                                        <td style="padding: 5px 0;"><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
                                    </tr>
                                    <tr>
                                        <th style="padding: 5px 0; font-weight: normal;">PHPバージョン</th>
                                        <td style="padding: 5px 0;"><?php echo esc_html( PHP_VERSION ); ?></td>
                                    </tr>
                                    <tr>
                                        <th style="padding: 5px 0; font-weight: normal;">プラグインバージョン</th>
                                        <td style="padding: 5px 0;"><?php echo esc_html( defined( 'NEWS_CRAWLER_VERSION' ) ? NEWS_CRAWLER_VERSION : '2.1.5' ); ?></td>
                                    </tr>
                                    <tr>
                                        <th style="padding: 5px 0; font-weight: normal;">サイトURL</th>
                                        <td style="padding: 5px 0;"><?php echo esc_html( get_site_url() ); ?></td>
                                    </tr>
                                    <tr>
                                        <th style="padding: 5px 0; font-weight: normal;">サーバーソフトウェア</th>
                                        <td style="padding: 5px 0;"><?php echo esc_html( $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ); ?></td>
                                    </tr>
                                </table>
                            </div>
                            
                            <div style="background: #fff; padding: 15px; border-radius: 3px; margin-top: 15px;">
                                <h4 style="margin-top: 0;"><?php echo esc_html__( 'ライセンス状態', 'news-crawler' ); ?></h4>
                                <table class="form-table" style="margin: 0;">
                                    <tr>
                                        <th style="padding: 5px 0; font-weight: normal; width: 150px;">ライセンスキー</th>
                                        <td style="padding: 5px 0;">
                                            <?php 
                                            $license_key = get_option( 'news_crawler_license_key' );
                                            if ( ! empty( $license_key ) ) {
                                                echo esc_html( substr( $license_key, 0, 8 ) . '...' . substr( $license_key, -4 ) );
                                            } else {
                                                echo '<em>' . esc_html__( '設定されていません', 'news-crawler' ) . '</em>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th style="padding: 5px 0; font-weight: normal;">ライセンスステータス</th>
                                        <td style="padding: 5px 0;"><?php echo esc_html( get_option( 'news_crawler_license_status', 'not_set' ) ); ?></td>
                                    </tr>
                                    <tr>
                                        <th style="padding: 5px 0; font-weight: normal;">最終検証日時</th>
                                        <td style="padding: 5px 0;">
                                            <?php 
                                            $verified_at = get_option( 'news_crawler_license_verified_at' );
                                            if ( $verified_at ) {
                                                echo esc_html( date( 'Y-m-d H:i:s', $verified_at ) );
                                            } else {
                                                echo '<em>' . esc_html__( '未検証', 'news-crawler' ) . '</em>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th style="padding: 5px 0; font-weight: normal;">開発環境</th>
                                        <td style="padding: 5px 0;">
                                            <?php 
                                            $is_dev = $license_manager->is_development_environment();
                                            echo $is_dev ? '<span style="color: #d63638;">' . esc_html__( 'はい', 'news-crawler' ) . '</span>' : '<span style="color: #00a32a;">' . esc_html__( 'いいえ', 'news-crawler' ) . '</span>';
                                            ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            
                            <div style="background: #fff; padding: 15px; border-radius: 3px; margin-top: 15px;">
                                <h4 style="margin-top: 0;"><?php echo esc_html__( '最近のエラーログ', 'news-crawler' ); ?></h4>
                                <p style="margin: 5px 0; font-size: 13px; color: #646970;">
                                    <?php echo esc_html__( 'WordPressのデバッグログから、News Crawlerライセンス関連のエラーを表示します。', 'news-crawler' ); ?>
                                </p>
                                <div style="background: #f6f7f7; padding: 10px; border-radius: 3px; font-family: monospace; font-size: 12px; max-height: 300px; overflow-y: auto;">
                                    <?php
                                    $log_file = WP_CONTENT_DIR . '/debug.log';
                                    if ( file_exists( $log_file ) ) {
                                        $log_content = file_get_contents( $log_file );
                                        $lines = explode( "\n", $log_content );
                                        
                                        // ライセンス関連のログをフィルタリング（より広範囲に）
                                        $license_lines = array_filter( $lines, function( $line ) {
                                            return strpos( $line, 'NewsCrawler License:' ) !== false || 
                                                   strpos( $line, 'KTP License API:' ) !== false ||
                                                   strpos( $line, 'License verification' ) !== false ||
                                                   strpos( $line, 'ライセンス' ) !== false;
                                        } );
                                        
                                        $recent_lines = array_slice( $license_lines, -20 ); // 最新の20行
                                        
                                        if ( ! empty( $recent_lines ) ) {
                                            foreach ( $recent_lines as $line ) {
                                                // エラーレベルのログをハイライト
                                                if ( strpos( $line, 'ERROR' ) !== false || strpos( $line, 'エラー' ) !== false ) {
                                                    echo '<div style="color: #d63638; background: #fcf0f1; padding: 2px 4px; margin: 1px 0; border-left: 3px solid #d63638;">' . esc_html( $line ) . '</div>';
                                                } elseif ( strpos( $line, 'WARNING' ) !== false || strpos( $line, '警告' ) !== false ) {
                                                    echo '<div style="color: #dba617; background: #fcf9e8; padding: 2px 4px; margin: 1px 0; border-left: 3px solid #dba617;">' . esc_html( $line ) . '</div>';
                                                } else {
                                                    echo '<div style="padding: 2px 4px; margin: 1px 0;">' . esc_html( $line ) . '</div>';
                                                }
                                            }
                                        } else {
                                            echo '<div style="color: #646970; font-style: italic;">' . esc_html__( 'ライセンス関連のログが見つかりません。', 'news-crawler' ) . '</div>';
                                        }
                                    } else {
                                        echo '<div style="color: #d63638;">' . esc_html__( 'デバッグログファイルが見つかりません。WP_DEBUGを有効にしてください。', 'news-crawler' ) . '</div>';
                                    }
                                    ?>
                                </div>
                                <div style="margin-top: 10px; text-align: right;">
                                    <button type="button" class="button button-secondary" onclick="location.reload();" style="font-size: 11px;">
                                        <?php echo esc_html__( 'ログを更新', 'news-crawler' ); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- ライセンス情報 -->
                    <div class="ktp-license-info" style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-radius: 5px;">
                        <h3><?php echo esc_html__( 'ライセンスについて', 'news-crawler' ); ?></h3>
                        <p><?php echo esc_html__( 'News Crawlerプラグインの機能を利用するには有効なライセンスキーが必要です。KantanPro License Manager (KLM) で管理されたライセンスキーをご利用ください。', 'news-crawler' ); ?></p>

                        <!-- 利用可能なライセンスプラン -->
                        <div style="margin: 20px 0; padding: 15px; background: #fff; border-radius: 5px; border-left: 4px solid #0073aa;">
                            <h4 style="margin-top: 0; color: #0073aa;"><?php echo esc_html__( '利用可能なライセンスプラン', 'news-crawler' ); ?></h4>
                            <ul style="margin-left: 20px; line-height: 1.8;">
                                <li><strong><?php echo esc_html__( '月額プラン', 'news-crawler' ); ?></strong>: 980円/月</li>
                                <li><strong><?php echo esc_html__( '年額プラン', 'news-crawler' ); ?></strong>: 9,980円/年</li>
                                <li><strong><?php echo esc_html__( '買い切りプラン', 'news-crawler' ); ?></strong>: 49,900円</li>
                            </ul>
                        </div>

                        <div style="margin: 20px 0; padding: 15px; background: #fff; border-radius: 5px; border-left: 4px solid #46b450;">
                            <h4 style="margin-top: 0; color: #46b450;"><?php echo esc_html__( 'KLMエンドポイント情報', 'news-crawler' ); ?></h4>
                            <ul style="margin-left: 20px; line-height: 1.8;">
                                <li><strong><?php echo esc_html__( 'ベースURL', 'news-crawler' ); ?>:</strong> https://www.kantanpro.com</li>
                                <li><strong><?php echo esc_html__( 'ライセンス検証', 'news-crawler' ); ?>:</strong> POST /wp-json/ktp-license/v1/verify</li>
                                <li><strong><?php echo esc_html__( 'ライセンス情報取得', 'news-crawler' ); ?>:</strong> GET /wp-json/ktp-license/v1/info</li>
                                <li><strong><?php echo esc_html__( 'デバッグエンドポイント', 'news-crawler' ); ?>:</strong> POST /wp-json/ktp-license/v1/debug</li>
                            </ul>
                        </div>

                        <div style="margin: 20px 0; padding: 15px; background: #fff; border-radius: 5px; border-left: 4px solid #dc3232;">
                            <h4 style="margin-top: 0; color: #dc3232;"><?php echo esc_html__( 'HTTP 403エラーのトラブルシューティング', 'news-crawler' ); ?></h4>
                            <ul style="margin-left: 20px; line-height: 1.8;">
                                <li><strong><?php echo esc_html__( 'サーバー側の問題', 'news-crawler' ); ?>:</strong> KantanProライセンスサーバーでアクセス制限が設定されている可能性があります</li>
                                <li><strong><?php echo esc_html__( 'IPアドレス制限', 'news-crawler' ); ?>:</strong> サーバーのIPアドレスがブロックされている可能性があります</li>
                                <li><strong><?php echo esc_html__( 'APIエンドポイント', 'news-crawler' ); ?>:</strong> エンドポイントが正しく設定されているか確認してください</li>
                                <li><strong><?php echo esc_html__( '一時的な問題', 'news-crawler' ); ?>:</strong> サーバーメンテナンス中の可能性があります</li>
                            </ul>
                            <p style="margin-top: 10px; padding: 10px; background: #f0f0f0; border-radius: 3px;">
                                <strong><?php echo esc_html__( '対処法', 'news-crawler' ); ?>:</strong><br>
                                1. しばらく時間をおいてから再試行してください<br>
                                2. サーバー管理者に連絡してIPアドレスの制限を確認してください<br>
                                3. ライセンスキーが正しいか確認してください
                            </p>
                        </div>

                        <ul style="margin-left: 20px;">
                            <li><?php echo esc_html__( 'ライセンスキーはKantanPro公式サイトから購入できます。', 'news-crawler' ); ?></li>
                            <li><?php echo esc_html__( 'ライセンスキーに関する問題がございましたら、サポートまでお問い合わせください。', 'news-crawler' ); ?></li>
                        </ul>
                        <p>
                            <a href="https://www.kantanpro.com/klm-news-crawler" target="_blank" class="button button-primary">
                                <?php echo esc_html__( 'ライセンスを購入', 'news-crawler' ); ?>
                            </a>
                            <a href="mailto:support@kantanpro.com" class="button button-secondary">
                                <?php echo esc_html__( 'サポートに問い合わせる', 'news-crawler' ); ?>
                            </a>
                        </p>
                    </div>

                    <!-- API接続テスト結果モーダル -->
                    <div id="api-test-modal" style="display: none; position: fixed; z-index: 100000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
                        <div style="background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 800px; border-radius: 5px; max-height: 80%; overflow-y: auto;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                <h2 style="margin: 0;">API接続テスト結果</h2>
                                <button type="button" id="close-api-test-modal" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
                            </div>
                            <div id="api-test-results" style="font-family: monospace; font-size: 12px; line-height: 1.4;">
                                <p>テストを実行中...</p>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // API接続テストボタンのクリックイベント
            $('#test-api-connection-btn').on('click', function() {
                $('#api-test-modal').show();
                $('#api-test-results').html('<p>テストを実行中...</p>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'news_crawler_test_api_connection',
                        nonce: '<?php echo wp_create_nonce( 'news_crawler_license_nonce' ); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var results = response.data;
                            var html = '<h3>1. GET リクエストテスト</h3>';
                            html += '<p><strong>結果:</strong> ' + (results.get_test.success ? '成功' : '失敗') + '</p>';
                            html += '<p><strong>メッセージ:</strong> ' + results.get_test.message + '</p>';
                            html += '<p><strong>レスポンスコード:</strong> ' + results.get_test.response_code + '</p>';
                            html += '<p><strong>Content-Type:</strong> ' + results.get_test.content_type + '</p>';
                            html += '<p><strong>レスポンスボディ:</strong></p>';
                            html += '<pre style="background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto;">' + results.get_test.response_body + '</pre>';
                            
                            html += '<h3>2. POST リクエストテスト</h3>';
                            html += '<p><strong>結果:</strong> ' + (results.post_test.success ? '成功' : '失敗') + '</p>';
                            html += '<p><strong>メッセージ:</strong> ' + results.post_test.message + '</p>';
                            html += '<p><strong>レスポンスコード:</strong> ' + results.post_test.response_code + '</p>';
                            html += '<p><strong>レスポンスボディ:</strong></p>';
                            html += '<pre style="background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto;">' + results.post_test.response_body + '</pre>';
                            
                            html += '<h3>3. 環境情報</h3>';
                            html += '<p><strong>WordPressバージョン:</strong> ' + results.environment.wordpress_version + '</p>';
                            html += '<p><strong>PHPバージョン:</strong> ' + results.environment.php_version + '</p>';
                            html += '<p><strong>サーバーソフトウェア:</strong> ' + results.environment.server_software + '</p>';
                            html += '<p><strong>WP_DEBUG:</strong> ' + (results.environment.wp_debug ? 'true' : 'false') + '</p>';
                            html += '<p><strong>サイトURL:</strong> ' + results.environment.site_url + '</p>';
                            html += '<p><strong>APIエンドポイント:</strong></p>';
                            html += '<ul>';
                            for (var key in results.environment.api_endpoints) {
                                html += '<li><strong>' + key + ':</strong> ' + results.environment.api_endpoints[key] + '</li>';
                            }
                            html += '</ul>';
                            
                            $('#api-test-results').html(html);
                        } else {
                            $('#api-test-results').html('<p style="color: red;">エラー: ' + response.data + '</p>');
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#api-test-results').html('<p style="color: red;">AJAXエラー: ' + error + '</p>');
                    }
                });
            });
            
            // モーダルを閉じる
            $('#close-api-test-modal, #api-test-modal').on('click', function(e) {
                if (e.target === this) {
                    $('#api-test-modal').hide();
                }
            });
        });
        </script>
        <?php
    }

    /**
     * ライセンス状態再確認の処理
     */
    private function handle_license_recheck() {
        $license_key = get_option( 'news_crawler_license_key' );
        
        if ( empty( $license_key ) ) {
            add_settings_error( 'news_crawler_license', 'no_license_key', __( 'ライセンスキーが設定されていません。', 'news-crawler' ), 'error' );
            return;
        }

        // ライセンスマネージャーのインスタンスを取得
        $license_manager = NewsCrawler_License_Manager::get_instance();
        
        // 強制的にライセンスを再検証
        $result = $license_manager->verify_license( $license_key );
        
        if ( $result['success'] ) {
            // ライセンスが有効な場合、情報を更新
            update_option( 'news_crawler_license_status', 'active' );
            update_option( 'news_crawler_license_info', $result['data'] );
            update_option( 'news_crawler_license_verified_at', current_time( 'timestamp' ) );
            
            add_settings_error( 'news_crawler_license', 'recheck_success', __( 'ライセンス状態の再確認が完了しました。ライセンスは有効です。', 'news-crawler' ), 'success' );
        } else {
            // ライセンスが無効な場合、ステータスを更新
            update_option( 'news_crawler_license_status', 'invalid' );
            error_log( 'NewsCrawler License: License recheck failed: ' . $result['message'] );
            
            add_settings_error( 'news_crawler_license', 'recheck_failed', __( 'ライセンス状態の再確認が完了しました。ライセンスは無効です。', 'news-crawler' ) . ' (' . $result['message'] . ')', 'error' );
        }
    }

    /**
     * ライセンスクリアの処理
     */
    private function handle_license_clear() {
        // ライセンスマネージャーのインスタンスを取得
        $license_manager = NewsCrawler_License_Manager::get_instance();
        
        // ライセンス情報をクリア
        $license_manager->deactivate_license();
        
        add_settings_error( 'news_crawler_license', 'license_cleared', __( 'ライセンス情報がクリアされました。', 'news-crawler' ), 'success' );
    }

    /**
     * ライセンスデバッグの処理
     */
    private function handle_license_debug() {
        $license_key = get_option( 'news_crawler_license_key' );
        
        if ( empty( $license_key ) ) {
            add_settings_error( 'news_crawler_license', 'no_license_key', __( 'ライセンスキーが設定されていません。', 'news-crawler' ), 'error' );
            return;
        }

        // ライセンスマネージャーのインスタンスを取得
        $license_manager = NewsCrawler_License_Manager::get_instance();
        
        // KLMデバッグエンドポイントを実行
        $result = $license_manager->debug_license_with_klm( $license_key );
        
        if ( $result['success'] ) {
            $debug_info = $result['data'];
            $message = __( 'KLMデバッグ情報を取得しました。', 'news-crawler' );
            if ( isset( $debug_info['message'] ) ) {
                $message .= ' ' . $debug_info['message'];
            }
            add_settings_error( 'news_crawler_license', 'debug_success', $message, 'success' );
        } else {
            add_settings_error( 'news_crawler_license', 'debug_failed', __( 'KLMデバッグの実行に失敗しました。', 'news-crawler' ) . ' ' . $result['message'], 'error' );
        }
    }
}

// Initialize the license settings
NewsCrawler_License_Settings::get_instance();
