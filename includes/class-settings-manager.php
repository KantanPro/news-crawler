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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('wp_ajax_test_api_connection', array($this, 'test_api_connection'));
        add_action('wp_ajax_reset_plugin_settings', array($this, 'reset_plugin_settings'));
    }
    
    /**
     * 管理メニューを追加
     */
    public function add_admin_menu() {
        // メインメニュー
        add_menu_page(
            'News Crawler ' . NEWS_CRAWLER_VERSION . ' 設定',
            'News Crawler',
            'manage_options',
            'news-crawler-settings',
            array($this, 'settings_page'),
            'dashicons-rss',
            30
        );
        
        // 基本設定サブメニュー
        add_submenu_page(
            'news-crawler-settings',
            'News Crawler ' . NEWS_CRAWLER_VERSION . ' - 基本設定',
            '基本設定',
            'manage_options',
            'news-crawler-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * 設定を初期化
     */
    public function admin_init() {
        register_setting($this->option_name, $this->option_name, array($this, 'sanitize_settings'));
        
        // API設定セクション
        add_settings_section(
            'api_settings',
            'API設定',
            array($this, 'api_section_callback'),
            'news-crawler-settings'
        );
        
        add_settings_field(
            'youtube_api_key',
            'YouTube API キー',
            array($this, 'youtube_api_key_callback'),
            'news-crawler-settings',
            'api_settings'
        );
        
        add_settings_field(
            'openai_api_key',
            'OpenAI API キー',
            array($this, 'openai_api_key_callback'),
            'news-crawler-settings',
            'api_settings'
        );
        
        // 機能設定セクション
        add_settings_section(
            'feature_settings',
            '機能設定',
            array($this, 'feature_section_callback'),
            'news-crawler-settings'
        );
        
        add_settings_field(
            'auto_featured_image',
            'アイキャッチ自動生成',
            array($this, 'auto_featured_image_callback'),
            'news-crawler-settings',
            'feature_settings'
        );
        
        add_settings_field(
            'featured_image_method',
            'アイキャッチ生成方法',
            array($this, 'featured_image_method_callback'),
            'news-crawler-settings',
            'feature_settings'
        );
        
        add_settings_field(
            'auto_summary_generation',
            'AI要約自動生成',
            array($this, 'auto_summary_generation_callback'),
            'news-crawler-settings',
            'feature_settings'
        );
        
        add_settings_field(
            'summary_generation_model',
            '要約生成モデル',
            array($this, 'summary_generation_model_callback'),
            'news-crawler-settings',
            'feature_settings'
        );
        
        // 品質管理設定セクション
        add_settings_section(
            'quality_settings',
            '品質管理設定',
            array($this, 'quality_section_callback'),
            'news-crawler-settings'
        );
        
        add_settings_field(
            'duplicate_check_strictness',
            '重複チェック厳密度',
            array($this, 'duplicate_check_strictness_callback'),
            'news-crawler-settings',
            'quality_settings'
        );
        
        add_settings_field(
            'duplicate_check_period',
            '重複チェック期間',
            array($this, 'duplicate_check_period_callback'),
            'news-crawler-settings',
            'quality_settings'
        );
        
        add_settings_field(
            'age_limit_enabled',
            '期間制限機能',
            array($this, 'age_limit_enabled_callback'),
            'news-crawler-settings',
            'quality_settings'
        );
        
        add_settings_field(
            'age_limit_days',
            '期間制限日数',
            array($this, 'age_limit_days_callback'),
            'news-crawler-settings',
            'quality_settings'
        );
    }
    
    /**
     * 設定ページを表示
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>News Crawler <?php echo esc_html(NEWS_CRAWLER_VERSION); ?> 基本設定</h1>
            
            <?php if (isset($_GET['settings-updated'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p>設定を保存しました。</p>
                </div>
            <?php endif; ?>
            
            <div class="nav-tab-wrapper">
                <a href="#api-settings" class="nav-tab nav-tab-active" data-tab="api-settings">API設定</a>
                <a href="#feature-settings" class="nav-tab" data-tab="feature-settings">機能設定</a>
                <a href="#quality-settings" class="nav-tab" data-tab="quality-settings">品質管理</a>
                <a href="#system-info" class="nav-tab" data-tab="system-info">システム情報</a>
            </div>
            
            <form method="post" action="options.php">
                <?php settings_fields($this->option_name); ?>
                
                <div id="api-settings" class="tab-content active">
                    <?php do_settings_sections('news-crawler-settings'); ?>
                </div>
                
                <div id="feature-settings" class="tab-content">
                    <h2>機能設定</h2>
                    <table class="form-table">
                        <?php
                        $this->render_field('auto_featured_image');
                        $this->render_field('featured_image_method');
                        $this->render_field('auto_summary_generation');
                        $this->render_field('summary_generation_model');
                        ?>
                    </table>
                </div>
                
                <div id="quality-settings" class="tab-content">
                    <h2>品質管理設定</h2>
                    <table class="form-table">
                        <?php
                        $this->render_field('duplicate_check_strictness');
                        $this->render_field('duplicate_check_period');
                        $this->render_field('age_limit_enabled');
                        $this->render_field('age_limit_days');
                        ?>
                    </table>
                </div>
                
                <div id="system-info" class="tab-content">
                    <h2>システム情報</h2>
                    <?php $this->display_system_info(); ?>
                </div>
                
                <?php submit_button(); ?>
            </form>
            
            <div class="card">
                <h2>API接続テスト</h2>
                <p>設定したAPIキーの接続をテストできます。</p>
                <button type="button" id="test-youtube-api" class="button">YouTube API テスト</button>
                <button type="button" id="test-openai-api" class="button">OpenAI API テスト</button>
                <div id="api-test-results" style="margin-top: 10px;"></div>
            </div>
            
            <div class="card">
                <h2>設定リセット</h2>
                <p><strong>注意:</strong> この操作により全ての設定がデフォルト値にリセットされます。</p>
                <button type="button" id="reset-settings" class="button button-secondary">設定をリセット</button>
            </div>
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
}