<?php
/**
 * 統合設定管理クラス
 * 
 * 全ての設定を一元管理し、重複を排除
 */

// 直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

class NewsCrawlerSettingsManager {
    
    private $option_name = 'news_crawler_settings';
    
    public function __construct() {
        error_log( 'NewsCrawler Settings: Constructor called' );
        
        // メニュー登録は無効化（class-genre-settings.phpで統合管理）
        // add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_test_api_connection', array($this, 'test_api_connection'));
        add_action('wp_ajax_reset_plugin_settings', array($this, 'reset_plugin_settings'));
        
        // ライセンス認証の処理を追加
        add_action('admin_init', array($this, 'handle_license_activation'));
        
        error_log( 'NewsCrawler Settings: Constructor completed' );
    }
    
    /**
     * 管理メニューを追加
     */
    public function add_admin_menu() {
        error_log( 'NewsCrawler Settings: add_admin_menu() called - DISABLED to avoid menu conflicts' );
        
        // メニューの重複を避けるため、このクラスのメニュー登録を無効化
        // メニューは class-genre-settings.php で統一管理
        return;
    }
    
    /**
     * 管理画面スクリプトを読み込み
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook === 'toplevel_page_news-crawler-settings') {
            wp_enqueue_script(
                'news-crawler-settings-admin',
                NEWS_CRAWLER_PLUGIN_URL . 'assets/js/settings-admin.js',
                array('jquery'),
                NEWS_CRAWLER_VERSION,
                true
            );
            
            wp_localize_script('news-crawler-settings-admin', 'newsCrawlerSettings', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('check_for_updates')
            ));
        }
        
        // News Crawler関連のページでスクリプトを読み込み
        if (strpos($hook, 'news-crawler') !== false) {
            wp_enqueue_script(
                'news-crawler-license-manager',
                NEWS_CRAWLER_PLUGIN_URL . 'assets/js/license-manager.js',
                array('jquery'),
                NEWS_CRAWLER_VERSION,
                true
            );
            
            // AJAX用のデータをローカライズ
            $nonce = wp_create_nonce('news_crawler_license_nonce');
            error_log('NewsCrawler Settings: Generated nonce for license manager: ' . $nonce);
            
            // 開発環境かどうかを厳密にチェック
            $is_dev = false;
            $dev_license_key = '';
            if (class_exists('NewsCrawler_License_Manager')) {
                $license_manager = NewsCrawler_License_Manager::get_instance();
                $is_dev = $license_manager->is_development_environment();
                if ($is_dev) {
                    $dev_license_key = $license_manager->get_display_dev_license_key();
                }
            }
            
            wp_localize_script('news-crawler-license-manager', 'news_crawler_license_ajax', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce'   => $nonce,
                'dev_license_key' => $dev_license_key,
                'is_development' => $is_dev,
                'debug_mode' => (defined('WP_DEBUG') && WP_DEBUG) || $is_dev,
                'plugin_version' => defined('NEWS_CRAWLER_VERSION') ? NEWS_CRAWLER_VERSION : '2.1.5',
                'strings' => array(
                    'verifying' => __( '認証中...', 'news-crawler' ),
                    'success'   => __( 'ライセンスが正常に認証されました。', 'news-crawler' ),
                    'error'     => __( 'ライセンスの認証に失敗しました。', 'news-crawler' ),
                    'network_error' => __( '通信エラーが発生しました。', 'news-crawler' )
                )
            ));
        }
    }
    
    /**
     * 設定を初期化
     */
    public function admin_init() {
        // WordPress 5.5+ では配列形式で sanitize_callback を渡すのが推奨/安全
        register_setting(
            $this->option_name,
            $this->option_name,
            array(
                'sanitize_callback' => array($this, 'sanitize_settings')
            )
        );
        
        // API設定セクション（APIタブ用スラッグ）
        add_settings_section(
            'api_settings',
            'API設定',
            array($this, 'api_section_callback'),
            'news-crawler-settings-api'
        );
        
        add_settings_field(
            'youtube_api_key',
            'YouTube API キー',
            array($this, 'youtube_api_key_callback'),
            'news-crawler-settings-api',
            'api_settings'
        );
        
        add_settings_field(
            'openai_api_key',
            'OpenAI API キー',
            array($this, 'openai_api_key_callback'),
            'news-crawler-settings-api',
            'api_settings'
        );
        
        // 機能設定セクション（機能タブ用スラッグ）
        add_settings_section(
            'feature_settings',
            '機能設定',
            array($this, 'feature_section_callback'),
            'news-crawler-settings-features'
        );
        
        add_settings_field(
            'auto_featured_image',
            'アイキャッチ自動生成',
            array($this, 'auto_featured_image_callback'),
            'news-crawler-settings-features',
            'feature_settings'
        );
        
        add_settings_field(
            'featured_image_method',
            'アイキャッチ生成方法',
            array($this, 'featured_image_method_callback'),
            'news-crawler-settings-features',
            'feature_settings'
        );
        
        // 更新情報セクション（更新情報タブ用スラッグ）
        add_settings_section(
            'update_info',
            '更新情報',
            array($this, 'update_info_section_callback'),
            'news-crawler-settings-update'
        );
        
        add_settings_field(
            'auto_summary_generation',
            'AI要約自動生成',
            array($this, 'auto_summary_generation_callback'),
            'news-crawler-settings-features',
            'feature_settings'
        );
        
        add_settings_field(
            'summary_generation_model',
            '要約生成モデル',
            array($this, 'summary_generation_model_callback'),
            'news-crawler-settings-features',
            'feature_settings'
        );
        
        // 品質管理設定セクション（品質タブ用スラッグ）
        add_settings_section(
            'quality_settings',
            '品質管理設定',
            array($this, 'quality_section_callback'),
            'news-crawler-settings-quality'
        );
        
        add_settings_field(
            'duplicate_check_strictness',
            '重複チェック厳密度',
            array($this, 'duplicate_check_strictness_callback'),
            'news-crawler-settings-quality',
            'quality_settings'
        );
        
        add_settings_field(
            'duplicate_check_period',
            '重複チェック期間',
            array($this, 'duplicate_check_period_callback'),
            'news-crawler-settings-quality',
            'quality_settings'
        );
        
        add_settings_field(
            'age_limit_enabled',
            '期間制限機能',
            array($this, 'age_limit_enabled_callback'),
            'news-crawler-settings-quality',
            'quality_settings'
        );
        
        add_settings_field(
            'age_limit_days',
            '期間制限日数',
            array($this, 'age_limit_days_callback'),
            'news-crawler-settings-quality',
            'quality_settings'
        );
    }
    
    /**
     * 設定ページを表示
     */
    public function settings_page() {
        error_log( 'NewsCrawler Settings: settings_page() called' );
        
        // ライセンス管理クラスが存在するかチェック
        if (!class_exists('NewsCrawler_License_Manager')) {
            error_log( 'NewsCrawler Settings: NewsCrawler_License_Manager class not found' );
            $this->display_license_input_page(array(
                'status' => 'error',
                'message' => 'ライセンス管理機能が利用できません。',
                'icon' => 'dashicons-warning',
                'color' => '#f56e28'
            ));
            return;
        }
        
        // ライセンス状態をチェック
        $license_manager = NewsCrawler_License_Manager::get_instance();
        $license_key = get_option( 'news_crawler_license_key' );
        $is_license_valid = $license_manager->is_license_valid();
        $license_status = $license_manager->get_license_status();
        
        // デバッグログを追加
        error_log( 'NewsCrawler Settings: license_key = ' . (empty($license_key) ? 'empty' : 'set') . ', is_license_valid = ' . ($is_license_valid ? 'true' : 'false') );
        error_log( 'NewsCrawler Settings: license_status = ' . print_r($license_status, true) );
        
        // ライセンスキーがないか無効な場合はライセンス入力画面を表示
        if (empty($license_key) || !$is_license_valid) {
            error_log( 'NewsCrawler Settings: Displaying license input page' );
            $this->display_license_input_page($license_status);
            return;
        }
        
        // 有効なライセンスキーがある場合は投稿設定画面を表示
        error_log( 'NewsCrawler Settings: Displaying post settings page' );
        $this->display_post_settings_page();
    }
    
    /**
     * ライセンス入力画面を表示
     */
    private function display_license_input_page($license_status) {
        ?>
        <div class="wrap">
            <h1><span class="dashicons dashicons-lock" style="margin-right: 10px; font-size: 24px; width: 24px; height: 24px;"></span>News Crawler <?php echo esc_html($this->get_plugin_version()); ?> - ライセンス認証</h1>
            
            <?php
            // 通知表示
            settings_errors( 'news_crawler_license' );
            ?>
            
            <div class="ktp-license-container" style="max-width: 800px; margin: 20px 0;">
                <!-- ライセンスステータス表示 -->
                <div class="ktp-license-status-display" style="margin-bottom: 30px; padding: 20px; background: #fff; border: 1px solid #ddd; border-radius: 5px;">
                    <h3 style="margin-top: 0;">
                        <span class="dashicons <?php echo esc_attr( $license_status['icon'] ); ?>" style="color: <?php echo esc_attr( $license_status['color'] ); ?>;"></span>
                        <?php echo esc_html__( 'ライセンスステータス', 'news-crawler' ); ?>
                    </h3>
                    <p style="font-size: 16px; margin: 10px 0;">
                        <strong><?php echo esc_html( $license_status['message'] ); ?></strong>
                    </p>
                </div>

                <!-- ライセンス認証フォーム -->
                <div class="ktp-license-form-container" style="padding: 20px; background: #f9f9f9; border-radius: 5px;">
                    <h3><?php echo esc_html__( 'ライセンスキーを入力してください', 'news-crawler' ); ?></h3>
                    
                    <form method="post" action="" id="news-crawler-license-form" style="margin-top: 20px;">
                        <?php wp_nonce_field( 'news_crawler_license_activation', 'news_crawler_license_nonce' ); ?>
                        <input type="hidden" name="news_crawler_license_activation" value="1">

                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="news_crawler_license_key"><?php echo esc_html__( 'ライセンスキー', 'news-crawler' ); ?></label>
                                </th>
                                <td>
                                    <input type="password"
                                           id="news_crawler_license_key"
                                           name="news_crawler_license_key"
                                           value="<?php echo esc_attr( get_option( 'news_crawler_license_key' ) ); ?>"
                                           class="regular-text"
                                           placeholder="NCR-XXXXXX-XXXXXX-XXXX"
                                           autocomplete="off"
                                           required>
                                    <p class="description"><?php echo esc_html__( 'KantanPro License Managerから取得したライセンスキーを入力してください。', 'news-crawler' ); ?></p>
                                </td>
                            </tr>
                        </table>

                        <?php submit_button( __( 'ライセンスを認証', 'news-crawler' ), 'primary', 'submit' ); ?>
                    </form>
                </div>

                <!-- ライセンス情報 -->
                <div class="ktp-license-info" style="margin-top: 30px; padding: 20px; background: #fff; border-radius: 5px; border-left: 4px solid #0073aa;">
                                            <h3><?php echo esc_html__( 'ライセンスについて', 'news-crawler' ); ?></h3>
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

            </div>
        </div>
        <?php
    }
    
    /**
     * 投稿設定画面を表示（ライセンス認証後）
     */
    public function display_post_settings_page() {
        ?>
        <div class="wrap">
            <h1>News Crawler <?php echo esc_html(NEWS_CRAWLER_VERSION); ?> 投稿設定</h1>
            
            <?php if (isset($_GET['settings-updated'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p>設定を保存しました。</p>
                </div>
            <?php endif; ?>
            
            <div class="nav-tab-wrapper">
                <a href="#api-settings" class="nav-tab nav-tab-active" data-tab="api-settings">API設定</a>
                <a href="#feature-settings" class="nav-tab" data-tab="feature-settings">機能設定</a>
                <a href="#quality-settings" class="nav-tab" data-tab="quality-settings">品質管理</a>
                <a href="#update-info" class="nav-tab" data-tab="update-info">更新情報</a>
                <a href="#system-info" class="nav-tab" data-tab="system-info">システム情報</a>
            </div>
            
            <form method="post" action="options.php">
                <?php settings_fields($this->option_name); ?>
                
                <div id="api-settings" class="tab-content active">
                    <?php do_settings_sections('news-crawler-settings-api'); ?>

                    <div class="card">
                        <h2>API接続テスト</h2>
                        <p>設定したAPIキーの接続をテストできます。</p>
                        <button type="button" id="test-youtube-api" class="button">YouTube API テスト</button>
                        <button type="button" id="test-openai-api" class="button">OpenAI API テスト</button>
                        <div id="api-test-results" style="margin-top: 10px;"></div>
                    </div>

                    
                </div>
                
                <div id="feature-settings" class="tab-content">
                    <?php do_settings_sections('news-crawler-settings-features'); ?>
                </div>
                
                <div id="quality-settings" class="tab-content">
                    <?php do_settings_sections('news-crawler-settings-quality'); ?>
                </div>
                
                <div id="update-info" class="tab-content">
                    <?php do_settings_sections('news-crawler-settings-update'); ?>
                </div>
                
                <div id="system-info" class="tab-content">
                    <h2>システム情報</h2>
                    <?php $this->display_system_info(); ?>
                </div>
                
                <?php submit_button(); ?>
            </form>
            
            
        </div>
        
        <style>
        .nav-tab-wrapper {
            margin-bottom: 20px;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
        }
        .system-info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .system-info-table th,
        .system-info-table td {
            padding: 8px 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .system-info-table th {
            background-color: #f9f9f9;
            font-weight: bold;
        }
        .status-ok {
            color: #46b450;
        }
        .status-warning {
            color: #ffb900;
        }
        .status-error {
            color: #dc3232;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // タブ切り替え
            $('.nav-tab').click(function(e) {
                e.preventDefault();
                var target = $(this).data('tab');
                
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                $('.tab-content').removeClass('active');
                $('#' + target).addClass('active');
            });
            
            // API接続テスト
            $('#test-youtube-api').click(function() {
                testApiConnection('youtube');
            });
            
            $('#test-openai-api').click(function() {
                testApiConnection('openai');
            });
            
            function testApiConnection(apiType) {
                var button = $('#test-' + apiType + '-api');
                var resultsDiv = $('#api-test-results');
                
                button.prop('disabled', true).text('テスト中...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'test_api_connection',
                        api_type: apiType,
                        nonce: '<?php echo wp_create_nonce('test_api_connection'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            resultsDiv.html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
                        } else {
                            resultsDiv.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                        }
                    },
                    error: function() {
                        resultsDiv.html('<div class="notice notice-error"><p>テストに失敗しました。</p></div>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text(apiType === 'youtube' ? 'YouTube API テスト' : 'OpenAI API テスト');
                    }
                });
            }
            
            // 設定リセット
            $('#reset-settings').click(function() {
                if (confirm('本当に全ての設定をリセットしますか？この操作は元に戻せません。')) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'reset_plugin_settings',
                            nonce: '<?php echo wp_create_nonce('reset_plugin_settings'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('設定をリセットしました。ページを再読み込みします。');
                                location.reload();
                            } else {
                                alert('設定のリセットに失敗しました: ' + response.data);
                            }
                        }
                    });
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * 設定フィールドをレンダリング
     */
    private function render_field($field_name) {
        $settings = get_option($this->option_name, array());
        $value = isset($settings[$field_name]) ? $settings[$field_name] : '';
        
        echo '<tr>';
        echo '<th scope="row">' . $this->get_field_label($field_name) . '</th>';
        echo '<td>';
        
        switch ($field_name) {
            case 'auto_featured_image':
            case 'auto_summary_generation':
            case 'age_limit_enabled':
                echo '<input type="checkbox" name="' . $this->option_name . '[' . $field_name . ']" value="1" ' . checked(1, $value, false) . ' />';
                break;
                
            case 'featured_image_method':
                $options = array(
                    'ai_generated' => 'AI生成画像',
                    'template_based' => 'テンプレートベース',
                    'external_api' => '外部API'
                );
                echo '<select name="' . $this->option_name . '[' . $field_name . ']">';
                foreach ($options as $key => $label) {
                    echo '<option value="' . $key . '" ' . selected($key, $value, false) . '>' . $label . '</option>';
                }
                echo '</select>';
                break;
                
            case 'summary_generation_model':
                $options = array(
                    'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
                    'gpt-4' => 'GPT-4',
                    'gpt-4-turbo' => 'GPT-4 Turbo'
                );
                echo '<select name="' . $this->option_name . '[' . $field_name . ']">';
                foreach ($options as $key => $label) {
                    echo '<option value="' . $key . '" ' . selected($key, $value, false) . '>' . $label . '</option>';
                }
                echo '</select>';
                break;
                
            case 'duplicate_check_strictness':
                $options = array(
                    'low' => '低（タイトルのみ）',
                    'medium' => '中（タイトル + 一部内容）',
                    'high' => '高（詳細チェック）'
                );
                echo '<select name="' . $this->option_name . '[' . $field_name . ']">';
                foreach ($options as $key => $label) {
                    echo '<option value="' . $key . '" ' . selected($key, $value, false) . '>' . $label . '</option>';
                }
                echo '</select>';
                break;
                
            case 'duplicate_check_period':
            case 'age_limit_days':
                echo '<input type="number" name="' . $this->option_name . '[' . $field_name . ']" value="' . esc_attr($value) . '" min="1" max="365" />';
                echo '<span class="description">日</span>';
                break;
        }
        
        echo '<p class="description">' . $this->get_field_description($field_name) . '</p>';
        echo '</td>';
        echo '</tr>';
    }
    
    /**
     * フィールドラベルを取得
     */
    private function get_field_label($field_name) {
        $labels = array(
            'auto_featured_image' => 'アイキャッチ自動生成',
            'featured_image_method' => 'アイキャッチ生成方法',
            'auto_summary_generation' => 'AI要約自動生成',
            'summary_generation_model' => '要約生成モデル',
            'duplicate_check_strictness' => '重複チェック厳密度',
            'duplicate_check_period' => '重複チェック期間',
            'age_limit_enabled' => '期間制限機能',
            'age_limit_days' => '期間制限日数'
        );
        
        return isset($labels[$field_name]) ? $labels[$field_name] : $field_name;
    }
    
    /**
     * フィールド説明を取得
     */
    private function get_field_description($field_name) {
        $descriptions = array(
            'auto_featured_image' => '投稿作成時に自動でアイキャッチ画像を生成します',
            'featured_image_method' => 'アイキャッチ画像の生成方法を選択します',
            'auto_summary_generation' => '投稿作成時に自動でAI要約を生成します',
            'summary_generation_model' => 'AI要約に使用するモデルを選択します',
            'duplicate_check_strictness' => '重複記事のチェック厳密度を設定します',
            'duplicate_check_period' => '重複チェックを行う期間を設定します',
            'age_limit_enabled' => '古い記事・動画をスキップする機能を有効にします',
            'age_limit_days' => 'この日数より古いコンテンツをスキップします'
        );
        
        return isset($descriptions[$field_name]) ? $descriptions[$field_name] : '';
    }
    
    /**
     * システム情報を表示
     */
    private function display_system_info() {
        $info = array(
            'WordPress バージョン' => get_bloginfo('version'),
            'PHP バージョン' => PHP_VERSION,
            'プラグイン バージョン' => NEWS_CRAWLER_VERSION,
            'GD ライブラリ' => extension_loaded('gd') ? '有効' : '無効',
            'cURL' => extension_loaded('curl') ? '有効' : '無効',
            'JSON' => extension_loaded('json') ? '有効' : '無効',
            'メモリ制限' => ini_get('memory_limit'),
            '最大実行時間' => ini_get('max_execution_time') . '秒'
        );
        
        echo '<table class="system-info-table">';
        foreach ($info as $label => $value) {
            $status_class = '';
            if (strpos($label, 'ライブラリ') !== false || $label === 'cURL' || $label === 'JSON') {
                $status_class = ($value === '有効') ? 'status-ok' : 'status-error';
            }
            
            echo '<tr>';
            echo '<th>' . esc_html($label) . '</th>';
            echo '<td class="' . $status_class . '">' . esc_html($value) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    
    // セクションコールバック関数
    public function api_section_callback() {
        echo '<p>各種APIキーを設定してください。</p>';
    }
    
    public function feature_section_callback() {
        echo '<p>プラグインの機能設定を行います。</p>';
    }
    
    public function quality_section_callback() {
        echo '<p>コンテンツの品質管理に関する設定を行います。</p>';
    }
    
    // フィールドコールバック関数
    public function youtube_api_key_callback() {
        $settings = get_option($this->option_name, array());
        $value = isset($settings['youtube_api_key']) ? $settings['youtube_api_key'] : '';
        echo '<input type="text" name="' . $this->option_name . '[youtube_api_key]" value="' . esc_attr($value) . '" size="50" />';
        echo '<p class="description">YouTube Data API v3のAPIキーを入力してください。</p>';
    }
    
    public function openai_api_key_callback() {
        $settings = get_option($this->option_name, array());
        $value = isset($settings['openai_api_key']) ? $settings['openai_api_key'] : '';
        echo '<input type="password" name="' . $this->option_name . '[openai_api_key]" value="' . esc_attr($value) . '" size="50" />';
        echo '<p class="description">OpenAI APIキーを入力してください。</p>';
    }
    
    public function auto_featured_image_callback() {
        $settings = get_option($this->option_name, array());
        $value = isset($settings['auto_featured_image']) ? $settings['auto_featured_image'] : false;
        echo '<input type="checkbox" name="' . $this->option_name . '[auto_featured_image]" value="1" ' . checked(1, $value, false) . ' />';
        echo '<p class="description">投稿作成時に自動でアイキャッチ画像を生成します。</p>';
    }
    
    public function featured_image_method_callback() {
        $settings = get_option($this->option_name, array());
        $value = isset($settings['featured_image_method']) ? $settings['featured_image_method'] : 'ai_generated';
        $options = array(
            'ai_generated' => 'AI生成画像',
            'template_based' => 'テンプレートベース'
        );
        echo '<select name="' . $this->option_name . '[featured_image_method]">';
        foreach ($options as $key => $label) {
            echo '<option value="' . $key . '" ' . selected($key, $value, false) . '>' . $label . '</option>';
        }
        echo '</select>';
        echo '<p class="description">アイキャッチ画像の生成方法を選択してください。</p>';
    }
    
    public function auto_summary_generation_callback() {
        $settings = get_option($this->option_name, array());
        $value = isset($settings['auto_summary_generation']) ? $settings['auto_summary_generation'] : false;
        echo '<input type="checkbox" name="' . $this->option_name . '[auto_summary_generation]" value="1" ' . checked(1, $value, false) . ' />';
        echo '<p class="description">投稿作成時に自動でAI要約を生成します。</p>';
    }
    
    public function summary_generation_model_callback() {
        $settings = get_option($this->option_name, array());
        $value = isset($settings['summary_generation_model']) ? $settings['summary_generation_model'] : 'gpt-3.5-turbo';
        $options = array(
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo（推奨）',
            'gpt-4' => 'GPT-4',
            'gpt-4-turbo' => 'GPT-4 Turbo'
        );
        echo '<select name="' . $this->option_name . '[summary_generation_model]">';
        foreach ($options as $key => $label) {
            echo '<option value="' . $key . '" ' . selected($key, $value, false) . '>' . $label . '</option>';
        }
        echo '</select>';
        echo '<p class="description">AI要約に使用するモデルを選択してください。</p>';
    }
    
    public function duplicate_check_strictness_callback() {
        $settings = get_option($this->option_name, array());
        $value = isset($settings['duplicate_check_strictness']) ? $settings['duplicate_check_strictness'] : 'medium';
        $options = array(
            'low' => '低（タイトルのみ）',
            'medium' => '中（タイトル + 一部内容）',
            'high' => '高（詳細チェック）'
        );
        echo '<select name="' . $this->option_name . '[duplicate_check_strictness]">';
        foreach ($options as $key => $label) {
            echo '<option value="' . $key . '" ' . selected($key, $value, false) . '>' . $label . '</option>';
        }
        echo '</select>';
        echo '<p class="description">重複記事のチェック厳密度を設定してください。</p>';
    }
    
    public function duplicate_check_period_callback() {
        $settings = get_option($this->option_name, array());
        $value = isset($settings['duplicate_check_period']) ? $settings['duplicate_check_period'] : 30;
        echo '<input type="number" name="' . $this->option_name . '[duplicate_check_period]" value="' . esc_attr($value) . '" min="1" max="365" />';
        echo '<span class="description">日間</span>';
        echo '<p class="description">重複チェックを行う期間を設定してください。</p>';
    }
    
    public function age_limit_enabled_callback() {
        $settings = get_option($this->option_name, array());
        $value = isset($settings['age_limit_enabled']) ? $settings['age_limit_enabled'] : true;
        echo '<input type="checkbox" name="' . $this->option_name . '[age_limit_enabled]" value="1" ' . checked(1, $value, false) . ' />';
        echo '<p class="description">古い記事・動画をスキップする機能を有効にします。</p>';
    }
    
    public function age_limit_days_callback() {
        $settings = get_option($this->option_name, array());
        $value = isset($settings['age_limit_days']) ? $settings['age_limit_days'] : 7;
        echo '<input type="number" name="' . $this->option_name . '[age_limit_days]" value="' . esc_attr($value) . '" min="1" max="365" />';
        echo '<span class="description">日</span>';
        echo '<p class="description">この日数より古いコンテンツをスキップします。</p>';
    }
    
    /**
     * 設定をサニタイズ
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // APIキー
        if (isset($input['youtube_api_key'])) {
            $sanitized['youtube_api_key'] = sanitize_text_field($input['youtube_api_key']);
        }
        
        if (isset($input['openai_api_key'])) {
            $sanitized['openai_api_key'] = sanitize_text_field($input['openai_api_key']);
        }
        
        // チェックボックス
        $checkboxes = array('auto_featured_image', 'auto_summary_generation', 'age_limit_enabled');
        foreach ($checkboxes as $checkbox) {
            $sanitized[$checkbox] = isset($input[$checkbox]) ? true : false;
        }
        
        // セレクトボックス
        $selects = array('featured_image_method', 'summary_generation_model', 'duplicate_check_strictness');
        foreach ($selects as $select) {
            if (isset($input[$select])) {
                $sanitized[$select] = sanitize_text_field($input[$select]);
            }
        }
        
        // 数値
        $numbers = array('duplicate_check_period', 'age_limit_days');
        foreach ($numbers as $number) {
            if (isset($input[$number])) {
                $sanitized[$number] = max(1, min(365, intval($input[$number])));
            }
        }
        
        return $sanitized;
    }
    
    /**
     * API接続テスト
     */
    public function test_api_connection() {
        if (!wp_verify_nonce($_POST['nonce'], 'test_api_connection')) {
            wp_die('Security check failed');
        }
        
        $api_type = sanitize_text_field($_POST['api_type']);
        $settings = get_option($this->option_name, array());
        
        switch ($api_type) {
            case 'youtube':
                $api_key = isset($settings['youtube_api_key']) ? $settings['youtube_api_key'] : '';
                if (empty($api_key)) {
                    wp_send_json_error('YouTube APIキーが設定されていません。');
                }
                
                $url = "https://www.googleapis.com/youtube/v3/channels?part=snippet&mine=true&key=" . urlencode($api_key);
                $response = wp_remote_get($url);
                
                if (is_wp_error($response)) {
                    wp_send_json_error('API接続エラー: ' . $response->get_error_message());
                }
                
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                
                if (isset($data['error'])) {
                    wp_send_json_error('YouTube API エラー: ' . $data['error']['message']);
                } else {
                    wp_send_json_success('YouTube API接続成功！');
                }
                break;
                
            case 'openai':
                $api_key = isset($settings['openai_api_key']) ? $settings['openai_api_key'] : '';
                if (empty($api_key)) {
                    wp_send_json_error('OpenAI APIキーが設定されていません。');
                }
                
                $response = wp_remote_post('https://api.openai.com/v1/models', array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type' => 'application/json'
                    ),
                    'timeout' => 30
                ));
                
                if (is_wp_error($response)) {
                    wp_send_json_error('API接続エラー: ' . $response->get_error_message());
                }
                
                $status_code = wp_remote_retrieve_response_code($response);
                if ($status_code === 200) {
                    wp_send_json_success('OpenAI API接続成功！');
                } else {
                    wp_send_json_error('OpenAI API エラー: HTTP ' . $status_code);
                }
                break;
                
            default:
                wp_send_json_error('不明なAPIタイプです。');
        }
    }
    
    /**
     * 設定リセット
     */
    public function reset_plugin_settings() {
        if (!wp_verify_nonce($_POST['nonce'], 'reset_plugin_settings')) {
            wp_die('Security check failed');
        }
        
        // デフォルト設定
        $default_settings = array(
            'youtube_api_key' => '',
            'openai_api_key' => '',
            'auto_featured_image' => true,
            'featured_image_method' => 'ai_generated',
            'auto_summary_generation' => true,
            'summary_generation_model' => 'gpt-3.5-turbo',
            'duplicate_check_strictness' => 'medium',
            'duplicate_check_period' => 30,
            'age_limit_enabled' => true,
            'age_limit_days' => 7
        );
        
        update_option($this->option_name, $default_settings);
        
        wp_send_json_success('設定をリセットしました。');
    }
    
    /**
     * 設定値を取得
     */
    public static function get_setting($key, $default = null) {
        $settings = get_option('news_crawler_settings', array());
        return isset($settings[$key]) ? $settings[$key] : $default;
    }
    
    /**
     * 設定値を更新
     */
    public static function update_setting($key, $value) {
        $settings = get_option('news_crawler_settings', array());
        $settings[$key] = $value;
        return update_option('news_crawler_settings', $settings);
    }
    
    /**
     * 更新情報を表示
     */
    public function display_update_info() {
        $current_version = NEWS_CRAWLER_VERSION;
        $latest_version = get_transient('news_crawler_latest_version');
        
        // GitHub APIから取得できない場合は、現在のバージョンを最新として表示
        if (!$latest_version) {
            $latest_version = array(
                'version' => $current_version,
                'published_at' => date('Y-m-d H:i:s'),
                'description' => '現在のバージョン情報'
            );
        }
        
        // バージョンが現在のバージョンより古い場合は、現在のバージョンを使用
        if (version_compare($latest_version['version'], $current_version, '<')) {
            $latest_version = array(
                'version' => $current_version,
                'published_at' => date('Y-m-d H:i:s'),
                'description' => '現在のバージョン情報'
            );
        }
        
        $needs_update = version_compare($current_version, $latest_version['version'], '<');
        
        echo '<div class="card">';
        echo '<h3>バージョン情報</h3>';
        echo '<table class="system-info-table">';
        echo '<tr><th>現在のバージョン</th><td>' . esc_html($current_version) . '</td></tr>';
        echo '<tr><th>最新バージョン</th><td>' . esc_html($latest_version['version']) . '</td></tr>';
        echo '<tr><th>最終更新日</th><td>' . esc_html(date('Y-m-d H:i:s', strtotime($latest_version['published_at']))) . '</td></tr>';
        echo '</table>';
        
        if ($needs_update) {
            echo '<div class="notice notice-warning" style="margin: 15px 0;">';
            echo '<p><strong>新しいバージョンが利用可能です！</strong></p>';
            echo '<p><a href="' . admin_url('update-core.php') . '" class="button button-primary">今すぐ更新</a></p>';
            echo '</div>';
        } else {
            echo '<div class="notice notice-success" style="margin: 15px 0;">';
            echo '<p><strong>最新バージョンを使用しています。</strong></p>';
            echo '</div>';
        }
        
        if (!empty($latest_version['description'])) {
            echo '<div class="card">';
            echo '<h3>リリースノート</h3>';
            echo '<div style="max-height: 300px; overflow-y: auto; padding: 15px; background: #f9f9f9; border-radius: 4px;">';
            echo '<pre style="white-space: pre-wrap; font-family: inherit; margin: 0;">' . esc_html($latest_version['description']) . '</pre>';
            echo '</div>';
            echo '</div>';
        }
        
        // キャッシュクリアボタンを追加
        echo '<div class="card">';
        echo '<h3>キャッシュ管理</h3>';
        echo '<p>バージョン情報のキャッシュをクリアできます。</p>';
        echo '<button type="button" id="clear-cache" class="button">キャッシュクリア</button>';
        echo '<input type="hidden" id="news_crawler_nonce" value="' . wp_create_nonce('news_crawler_nonce') . '">';
        echo '</div>';
        
    }
    
    
    /**
     * 更新情報セクションのコールバック
     */
    public function update_info_section_callback() {
        echo '<p>プラグインの更新状況と最新バージョン情報を表示します。</p>';
        $this->display_update_info();
    }
    
    
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

        // ライセンスマネージャーのインスタンスを取得
        if (class_exists('NewsCrawler_License_Manager')) {
            $license_manager = NewsCrawler_License_Manager::get_instance();
            $license_status = $license_manager->get_license_status();
        } else {
            $license_status = array(
                'status' => 'not_set',
                'message' => 'ライセンス管理機能が利用できません。',
                'icon' => 'dashicons-warning',
                'color' => '#f56e28'
            );
        }
        
        ?>
        <div class="wrap ktp-admin-wrap">
            <h1><span class="dashicons dashicons-lock" style="margin-right: 10px; font-size: 24px; width: 24px; height: 24px;"></span><?php echo esc_html__( 'ライセンス設定', 'news-crawler' ); ?></h1>
            
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
                        <?php if ( isset($license_status['is_dev_mode']) && ! empty( $license_status['is_dev_mode'] ) ) : ?>
                            <div class="ktp-dev-mode-toggle" style="margin-top: 15px; padding: 10px; background-color: #fff8e1; border: 1px solid #ffecb3; border-radius: 4px;">
                                <p style="margin: 0; display: flex; align-items: center; justify-content: space-between;">
                                    <span><span class="dashicons dashicons-info-outline"></span> 開発環境モードで動作中です。</span>
                                    <button id="toggle-dev-license" class="button button-secondary">
                                        <?php echo isset($license_manager) && $license_manager->is_dev_license_enabled() ? '開発用ライセンスを無効化' : '開発用ライセンスを有効化'; ?>
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
                                   placeholder="NCR-XXXXXX-XXXXXX-XXXX"
                                   autocomplete="off">

                            <?php submit_button( __( 'ライセンスを認証', 'news-crawler' ), 'primary', 'submit', false, ['style' => 'margin: 0;'] ); ?>
                        </form>

                        <?php if ( isset($license_manager) && $license_manager->is_development_environment() ) : ?>
                            <button id="use-dev-license" class="button button-secondary" type="button" style="margin: 0;">
                                <?php echo esc_html__( 'テスト用ライセンスを自動入力', 'news-crawler' ); ?>
                            </button>
                        <?php endif; ?>

                        <!-- ライセンス状態再確認フォーム -->
                        <?php if ( ! empty( get_option( 'news_crawler_license_key' ) ) ) : ?>
                            <form method="post" action="" style="margin: 0;">
                                <?php wp_nonce_field( 'news_crawler_license_recheck', 'news_crawler_license_recheck_nonce' ); ?>
                                <input type="hidden" name="news_crawler_license_recheck" value="1">
                                <?php submit_button( __( 'ライセンス状態を再確認', 'news-crawler' ), 'secondary', 'recheck_license', false, ['style' => 'margin: 0;'] ); ?>
                            </form>
                        <?php endif; ?>
                    </div>

                    <p class="description" style="padding-left: 8px; margin-top: 5px;">
                        <?php echo esc_html__( 'KantanPro License Managerから取得したライセンスキーを入力してください。', 'news-crawler' ); ?>
                    </p>

                    <!-- ライセンス情報 -->
                    <div class="ktp-license-info" style="margin-top: 30px; padding: 20px; background: #fff; border-radius: 5px; border-left: 4px solid #0073aa;">
                        <h3><?php echo esc_html__( 'ライセンスについて', 'news-crawler' ); ?></h3>
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


                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * ライセンス状態再確認の処理
     */
    private function handle_license_recheck() {
        $license_key = get_option( 'news_crawler_license_key' );
        
        if ( empty( $license_key ) ) {
            add_settings_error( 'news_crawler_license', 'empty_key', __( 'ライセンスキーが設定されていません。', 'news-crawler' ), 'error' );
            return;
        }

        // ライセンスマネージャーのインスタンスを取得
        if (class_exists('NewsCrawler_License_Manager')) {
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
        } else {
            add_settings_error( 'news_crawler_license', 'license_manager_not_found', __( 'ライセンス管理機能が利用できません。', 'news-crawler' ), 'error' );
        }
    }
    
    /**
     * ライセンス認証の処理
     */
    public function handle_license_activation() {
        if ( ! isset( $_POST['news_crawler_license_activation'] ) || ! wp_verify_nonce( $_POST['news_crawler_license_nonce'], 'news_crawler_license_activation' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'この操作を実行する権限がありません。', 'news-crawler' ) );
        }

        // ライセンスキーは記号・スペースを保持
        $license_key = isset( $_POST['news_crawler_license_key'] ) ? trim( wp_unslash( $_POST['news_crawler_license_key'] ) ) : '';
        
        if ( empty( $license_key ) ) {
            add_settings_error( 'news_crawler_license', 'empty_key', __( 'ライセンスキーを入力してください。', 'news-crawler' ), 'error' );
            return;
        }

        if (class_exists('NewsCrawler_License_Manager')) {
            $license_manager = NewsCrawler_License_Manager::get_instance();
            $result = $license_manager->verify_license( $license_key );
            
            if ( $result['success'] ) {
                // Save license key
                update_option( 'news_crawler_license_key', $license_key );
                update_option( 'news_crawler_license_status', 'active' );
                update_option( 'news_crawler_license_info', $result['data'] );
                update_option( 'news_crawler_license_verified_at', current_time( 'timestamp' ) );
                // 開発環境の通知抑止（開発用ライセンスを有効化）
                update_option( 'news_crawler_dev_license_enabled', '1' );
                
                add_settings_error( 'news_crawler_license', 'activation_success', __( 'ライセンスが正常に認証されました。', 'news-crawler' ), 'success' );
            } else {
                add_settings_error( 'news_crawler_license', 'activation_failed', $result['message'], 'error' );
            }
        } else {
            add_settings_error( 'news_crawler_license', 'license_manager_not_found', __( 'ライセンス管理機能が利用できません。', 'news-crawler' ), 'error' );
        }
    }
    
    /**
     * プラグインのバージョンを動的に取得
     */
    private function get_plugin_version() {
        // 定数から直接取得（より確実）
        return NEWS_CRAWLER_VERSION;
    }
    
    /**
     * キャッシュクリアのAJAX処理
     */
    public function clear_cache_ajax() {
        // セキュリティチェック
        if (!wp_verify_nonce($_POST['nonce'], 'news_crawler_nonce')) {
            wp_die('セキュリティチェックに失敗しました。');
        }
        
        // 管理者権限チェック
        if (!current_user_can('manage_options')) {
            wp_die('権限がありません。');
        }
        
        // キャッシュをクリア
        delete_transient('news_crawler_latest_version');
        delete_transient('news_crawler_latest_version_backup');
        
        wp_send_json_success('キャッシュをクリアしました。');
    }
}