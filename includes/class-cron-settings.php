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
        // 設定は不要（生成されたCronジョブ設定のみ表示）
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
                <!-- 生成されたCronジョブ設定カード -->
                <div class="ktp-settings-card">
                    <div class="ktp-card-header">
                        <h2 class="ktp-card-title">
                            <span class="ktp-icon">📋</span>
                            生成されたCronジョブ設定
                        </h2>
                    </div>
                    <div class="ktp-card-content">
                        <?php $this->display_generated_cron_settings(); ?>
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
        $script_path = plugin_dir_path(__FILE__) . '../news-crawler-cron.sh';
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
        echo '<h3 style="color: #ecf0f1; margin-top: 0;">📝 Cronスクリプトパス</h3>';
        echo '<p style="color: #bdc3c7; margin-bottom: 16px;">以下のパスをサーバーのcrontabに追加してください：</p>';
        echo '<div class="ktp-code-block" style="background: #34495e; padding: 16px; border-radius: 6px; margin-bottom: 16px; overflow-x: auto;">';
        echo '<code style="color: #ecf0f1; font-family: Monaco, Menlo, Ubuntu Mono, monospace; font-size: 14px; line-height: 1.6; word-break: break-all;">' . esc_html($cron_command) . '</code>';
        echo '</div>';
        echo '<button type="button" class="button button-secondary" onclick="copyToClipboard(\'' . esc_js($cron_command) . '\')">パスをコピー</button>';
        echo '</div>';
        
        // 設定手順を表示
        echo '<div class="ktp-instructions-box" style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">';
        echo '<h3 style="color: #2c3e50; margin-top: 0;">📋 設定手順</h3>';
        echo '<ol style="color: #495057; line-height: 1.6;">';
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
        $script_path = plugin_dir_path(__FILE__) . '../news-crawler-cron.sh';
        $script_path = realpath($script_path);
        
        // スクリプトのパスのみを返す
        return $script_path;
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

