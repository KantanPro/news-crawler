<?php
/**
 * News Crawler Cron設定管理クラス
 * 生成されたCronジョブ設定のみを表示
 */

if (!defined('ABSPATH')) {
    exit;
}

class NewsCrawlerCronSettings {
    
    private $option_name = 'news_crawler_cron_settings';
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * 管理メニューに追加
     */
    public function add_admin_menu() {
        add_submenu_page(
            'news-crawler-settings',
            '自動投稿設定',
            '自動投稿設定',
            'manage_options',
            'news-crawler-cron-settings',
            array($this, 'admin_page')
        );
    }
    
    /**
     * 管理画面の初期化
     */
    public function admin_init() {
        // X（Twitter）設定セクション
        add_settings_section(
            'twitter_settings',
            'X（旧Twitter）自動シェア設定',
            array($this, 'twitter_section_callback'),
            'news-crawler-cron-settings'
        );
        
        add_settings_field(
            'twitter_enabled',
            'X（Twitter）への自動シェアを有効にする',
            array($this, 'twitter_enabled_callback'),
            'news-crawler-cron-settings',
            'twitter_settings'
        );
        
        add_settings_field(
            'twitter_bearer_token',
            'Bearer Token',
            array($this, 'twitter_bearer_token_callback'),
            'news-crawler-cron-settings',
            'twitter_settings'
        );
        
        add_settings_field(
            'twitter_api_key',
            'API Key（Consumer Key）',
            array($this, 'twitter_api_key_callback'),
            'news-crawler-cron-settings',
            'twitter_settings'
        );
        
        add_settings_field(
            'twitter_api_secret',
            'API Secret（Consumer Secret）',
            array($this, 'twitter_api_secret_callback'),
            'news-crawler-cron-settings',
            'twitter_settings'
        );
        
        add_settings_field(
            'twitter_access_token',
            'Access Token',
            array($this, 'twitter_access_token_callback'),
            'news-crawler-cron-settings',
            'twitter_settings'
        );
        
        add_settings_field(
            'twitter_access_token_secret',
            'Access Token Secret',
            array($this, 'twitter_access_token_secret_callback'),
            'news-crawler-cron-settings',
            'twitter_settings'
        );
        
        add_settings_field(
            'twitter_hashtags',
            'ハッシュタグ',
            array($this, 'twitter_hashtags_callback'),
            'news-crawler-cron-settings',
            'twitter_settings'
        );
        
        add_settings_field(
            'twitter_include_url',
            'URLを含める',
            array($this, 'twitter_include_url_callback'),
            'news-crawler-cron-settings',
            'twitter_settings'
        );
        
        add_settings_field(
            'twitter_max_length',
            '最大文字数',
            array($this, 'twitter_max_length_callback'),
            'news-crawler-cron-settings',
            'twitter_settings'
        );
        
        add_settings_field(
            'twitter_message_template',
            'メッセージテンプレート',
            array($this, 'twitter_message_template_callback'),
            'news-crawler-cron-settings',
            'twitter_settings'
        );
        
        add_settings_field(
            'twitter_include_link',
            '投稿へのリンクを含める',
            array($this, 'twitter_include_link_callback'),
            'news-crawler-cron-settings',
            'twitter_settings'
        );
        
        // 設定の登録
        register_setting('news_crawler_cron_settings', 'news_crawler_basic_settings');
    }
    
    /**
     * 管理画面用スクリプトの読み込み
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'news-crawler_page_news-crawler-cron-settings') {
            return;
        }
        
        wp_enqueue_style('ktp-admin-style', plugin_dir_url(__FILE__) . '../assets/css/auto-posting-admin.css', array(), '1.0.0');
    }
    
    /**
     * 管理画面の表示
     */
    public function admin_page() {
        ?>
        <div class="wrap ktp-admin-wrap">
            <h1 class="ktp-admin-title">
                    <span class="ktp-icon">⚙️</span>
                    News Crawler <?php echo esc_html($this->get_plugin_version()); ?> - 自動投稿設定
                </h1>
            
            <div class="ktp-admin-content">
                <!-- ブログ自動投稿設定カード -->
                <div class="ktp-settings-card">
                    <div class="ktp-card-header">
                        <h2 class="ktp-card-title">
                            <span class="ktp-icon">📋</span>
                            ブログ自動投稿設定
                        </h2>
                    </div>
                    <div class="ktp-card-content">
                        <?php $this->display_generated_cron_settings(); ?>
                    </div>
                </div>
                
                <!-- X（Twitter）設定カード -->
                <div class="ktp-settings-card">
                    <div class="ktp-card-header">
                        <h2 class="ktp-card-title">
                            <span class="ktp-icon">🐦</span>
                            X（旧Twitter）自動シェア設定
                        </h2>
                    </div>
                    <div class="ktp-card-content">
                        <form method="post" action="options.php">
                            <?php 
                            settings_fields('news_crawler_cron_settings');
                            do_settings_sections('news-crawler-cron-settings');
                            ?>
                            <?php submit_button('X設定を保存'); ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 生成されたCronジョブ設定を表示
     */
    private function display_generated_cron_settings() {
        $script_path = dirname(plugin_dir_path(__FILE__)) . '/news-crawler-cron.sh';
        $script_exists = file_exists($script_path);
        
        if (!$script_exists) {
            echo '<div class="ktp-error-box">';
            echo '<span class="ktp-error-icon">❌</span>';
            echo '<p><strong>エラー：</strong>cronスクリプトが見つかりません。</p>';
            echo '<p>スクリプトパス: ' . esc_html($script_path) . '</p>';
            echo '</div>';
            return;
        }
        
        
        // 生成されたcronコマンドを表示
        $cron_command = $this->generate_cron_command();
        
        echo '<div class="ktp-command-box">';
        echo '<h3 style="margin-top: 0;">📝 Cronスクリプトパス</h3>';
        echo '<p style="margin-bottom: 16px;">以下のパスをサーバーのcrontabに追加してください：</p>';
        echo '<div class="ktp-code-block" style="background: #f8f9fa; border: 1px solid #dee2e6; padding: 16px; border-radius: 6px; margin-bottom: 16px; overflow-x: auto;">';
        echo '<code style="color: #333; font-family: Monaco, Menlo, Ubuntu Mono, monospace; font-size: 14px; line-height: 1.6; word-break: break-all;">' . esc_html($cron_command) . '</code>';
        echo '</div>';
        echo '<button type="button" class="button button-secondary" onclick="copyToClipboard(\'' . esc_js($cron_command) . '\')">パスをコピー</button>';
        echo '</div>';
        
        // 設定手順を表示
        echo '<div class="ktp-instructions-box" style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">';
        echo '<h3 style="margin-top: 0;">📋 設定手順</h3>';
        echo '<ol style="line-height: 1.6;">';
        echo '<li>上記のパスをコピーします</li>';
        echo '<li>実行頻度とパスを組み合わせてcrontabに追加します（例：<code style="background: #e9ecef; padding: 2px 6px; border-radius: 4px; font-family: Monaco, Menlo, Ubuntu Mono, monospace;">0 * * * * /path/to/script</code>）</li>';
        echo '</ol>';
        echo '</div>';
        
        
        // JavaScript
        ?>
        <script>
        function copyToClipboard(text) {
                navigator.clipboard.writeText(text).then(function() {
                alert('パスをクリップボードにコピーしました');
            }, function(err) {
                console.error('コピーに失敗しました: ', err);
            });
        }
        </script>
        <?php
    }
    
    /**
     * Cronコマンドを生成
     */
    private function generate_cron_command() {
        $script_path = dirname(plugin_dir_path(__FILE__)) . '/news-crawler-cron.sh';
        
        // スクリプトのパスのみを返す
        return $script_path;
    }
    
    
    /**
     * X（Twitter）設定のコールバック関数
     */
    public function twitter_section_callback() {
        echo '<p>X（旧Twitter）への自動投稿に関する設定です。投稿作成後に自動的にXにシェアされます。</p>';
        echo '<p><button type="button" id="test-x-connection" class="button button-secondary">接続テスト</button></p>';
        wp_nonce_field('twitter_connection_test_nonce', 'twitter_connection_test_nonce');
    }
    
    public function twitter_enabled_callback() {
        $settings = get_option('news_crawler_basic_settings', array());
        $value = isset($settings['twitter_enabled']) ? $settings['twitter_enabled'] : false;
        echo '<input type="hidden" name="news_crawler_basic_settings[twitter_enabled]" value="0" />';
        echo '<input type="checkbox" name="news_crawler_basic_settings[twitter_enabled]" value="1" ' . checked(1, $value, false) . ' />';
        echo '<p class="description">投稿作成後に自動的にXにシェアされます。</p>';
    }
    
    public function twitter_bearer_token_callback() {
        $settings = get_option('news_crawler_basic_settings', array());
        $value = isset($settings['twitter_bearer_token']) ? $settings['twitter_bearer_token'] : '';
        echo '<input type="text" name="news_crawler_basic_settings[twitter_bearer_token]" value="' . esc_attr($value) . '" size="50" />';
        echo '<p class="description">X Developer Portalで取得したBearer Tokenを入力してください。</p>';
    }
    
    public function twitter_api_key_callback() {
        $settings = get_option('news_crawler_basic_settings', array());
        $value = isset($settings['twitter_api_key']) ? $settings['twitter_api_key'] : '';
        echo '<input type="text" name="news_crawler_basic_settings[twitter_api_key]" value="' . esc_attr($value) . '" size="50" />';
        echo '<p class="description">X Developer Portalで取得したAPI Key（Consumer Key）を入力してください。</p>';
    }
    
    public function twitter_api_secret_callback() {
        $settings = get_option('news_crawler_basic_settings', array());
        $value = isset($settings['twitter_api_secret']) ? $settings['twitter_api_secret'] : '';
        echo '<input type="text" name="news_crawler_basic_settings[twitter_api_secret]" value="' . esc_attr($value) . '" size="50" />';
        echo '<p class="description">X Developer Portalで取得したAPI Secret（Consumer Secret）を入力してください。</p>';
    }
    
    public function twitter_access_token_callback() {
        $settings = get_option('news_crawler_basic_settings', array());
        $value = isset($settings['twitter_access_token']) ? $settings['twitter_access_token'] : '';
        echo '<input type="text" name="news_crawler_basic_settings[twitter_access_token]" value="' . esc_attr($value) . '" size="50" />';
        echo '<p class="description">X Developer Portalで取得したAccess Tokenを入力してください。</p>';
    }
    
    public function twitter_access_token_secret_callback() {
        $settings = get_option('news_crawler_basic_settings', array());
        $value = isset($settings['twitter_access_token_secret']) ? $settings['twitter_access_token_secret'] : '';
        echo '<input type="text" name="news_crawler_basic_settings[twitter_access_token_secret]" value="' . esc_attr($value) . '" size="50" />';
        echo '<p class="description">X Developer Portalで取得したAccess Token Secretを入力してください。</p>';
    }
    
    public function twitter_hashtags_callback() {
        $settings = get_option('news_crawler_basic_settings', array());
        $value = isset($settings['twitter_hashtags']) ? $settings['twitter_hashtags'] : '';
        echo '<input type="text" name="news_crawler_basic_settings[twitter_hashtags]" value="' . esc_attr($value) . '" size="50" />';
        echo '<p class="description">投稿に追加するハッシュタグを入力してください（例：#ニュース #自動投稿）。</p>';
    }
    
    public function twitter_include_url_callback() {
        $settings = get_option('news_crawler_basic_settings', array());
        $value = isset($settings['twitter_include_url']) ? $settings['twitter_include_url'] : true;
        echo '<input type="hidden" name="news_crawler_basic_settings[twitter_include_url]" value="0" />';
        echo '<input type="checkbox" name="news_crawler_basic_settings[twitter_include_url]" value="1" ' . checked(1, $value, false) . ' />';
        echo '<p class="description">投稿にURLを含めるかどうかを設定します。</p>';
    }
    
    public function twitter_max_length_callback() {
        $settings = get_option('news_crawler_basic_settings', array());
        $value = isset($settings['twitter_max_length']) ? $settings['twitter_max_length'] : 280;
        echo '<input type="number" name="news_crawler_basic_settings[twitter_max_length]" value="' . esc_attr($value) . '" min="1" max="280" />';
        echo '<p class="description">投稿の最大文字数を設定します（1-280文字）。</p>';
    }
    
    public function twitter_message_template_callback() {
        $settings = get_option('news_crawler_basic_settings', array());
        if (!is_array($settings)) {
            $settings = array();
        }
        $value = isset($settings['twitter_message_template']) ? $settings['twitter_message_template'] : '%TITLE%';
        
        // 旧形式の{title}を%TITLE%に自動変換
        if ($value === '{title}') {
            $value = '%TITLE%';
            // 設定を更新
            $settings['twitter_message_template'] = $value;
            update_option('news_crawler_basic_settings', $settings);
        }
        
        echo '<textarea name="news_crawler_basic_settings[twitter_message_template]" rows="3" cols="50">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">X投稿用のメッセージテンプレートを入力してください。以下のプレースホルダーが使用できます：</p>';
        echo '<div class="description" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 5px; margin: 10px 0;">';
        echo '<div><strong>%TITLE%</strong> - 投稿タイトル</div>';
        echo '<div><strong>%URL%</strong> - 投稿URL</div>';
        echo '<div><strong>%SURL%</strong> - 短縮URL</div>';
        echo '<div><strong>%IMG%</strong> - アイキャッチ画像URL</div>';
        echo '<div><strong>%EXCERPT%</strong> - 抜粋（処理済み）</div>';
        echo '<div><strong>%RAWEXCERPT%</strong> - 抜粋（生）</div>';
        echo '<div><strong>%ANNOUNCE%</strong> - アナウンス文</div>';
        echo '<div><strong>%FULLTEXT%</strong> - 本文（処理済み）</div>';
        echo '<div><strong>%RAWTEXT%</strong> - 本文（生）</div>';
        echo '<div><strong>%TAGS%</strong> - タグ</div>';
        echo '<div><strong>%CATS%</strong> - カテゴリー</div>';
        echo '<div><strong>%HTAGS%</strong> - タグ（ハッシュタグ）</div>';
        echo '<div><strong>%HCATS%</strong> - カテゴリー（ハッシュタグ）</div>';
        echo '<div><strong>%AUTHORNAME%</strong> - 投稿者名</div>';
        echo '<div><strong>%SITENAME%</strong> - サイト名</div>';
        echo '</div>';
    }
    
    public function twitter_include_link_callback() {
        $settings = get_option('news_crawler_basic_settings', array());
        $value = isset($settings['twitter_include_link']) ? $settings['twitter_include_link'] : true;
        echo '<input type="hidden" name="news_crawler_basic_settings[twitter_include_link]" value="0" />';
        echo '<input type="checkbox" name="news_crawler_basic_settings[twitter_include_link]" value="1" ' . checked(1, $value, false) . ' />';
        echo '<p class="description">X投稿に投稿へのリンクを含めます。</p>';
    }
    
    /**
     * 強制的にcronスクリプトを作成
     */
    public function force_create_cron_script() {
        $script_path = dirname(plugin_dir_path(__FILE__)) . '/news-crawler-cron.sh';
        
        // スクリプトが既に存在する場合は何もしない
        if (file_exists($script_path)) {
            error_log('News Crawler: cronスクリプトは既に存在します - パス: ' . $script_path);
            return;
        }
        
        error_log('News Crawler: cronスクリプトを作成中 - パス: ' . $script_path);
        
        // 既存のcronスクリプトをコピーして作成
        $source_script = dirname(plugin_dir_path(__FILE__)) . '/news-crawler-cron-backup.sh';
        
        if (file_exists($source_script)) {
            // バックアップスクリプトからコピー
            $result = copy($source_script, $script_path);
            if ($result) {
                chmod($script_path, 0755);
                error_log('News Crawler: バックアップからcronスクリプトを復元しました - パス: ' . $script_path);
                return;
            }
        }
        
        // バックアップが存在しない場合は基本的なスクリプトを作成
        $script_content = '#!/bin/bash
# News Crawler Auto Posting Script
# Generated automatically by News Crawler plugin

# WordPress root directory
WP_ROOT="' . ABSPATH . '"

# Change to WordPress directory
cd "$WP_ROOT"

# Run WordPress cron
php wp-cron.php

# Log the execution
echo "$(date): News Crawler cron executed" >> wp-content/plugins/news-crawler/news-crawler-cron.log
';
        
        // スクリプトファイルを作成
        $result = file_put_contents($script_path, $script_content);
        
        if ($result === false) {
            error_log('News Crawler: cronスクリプトの作成に失敗しました - パス: ' . $script_path);
            return;
        }
        
        // 実行権限を付与
        chmod($script_path, 0755);
        
        error_log('News Crawler: cronスクリプトを作成しました - パス: ' . $script_path);
    }
    
    /**
     * プラグインバージョンを取得
     */
    private function get_plugin_version() {
        if (defined('NEWS_CRAWLER_VERSION')) {
            return NEWS_CRAWLER_VERSION;
        }
        return '2.9.3';
    }
}

