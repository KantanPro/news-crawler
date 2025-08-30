<?php
/**
 * News Crawler Updater Class
 * 
 * Handles automatic updates from GitHub releases
 * 
 * @package NewsCrawler
 * @since 2.0.4
 */

// 直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

class NewsCrawlerUpdater {
    
    /**
     * GitHub API URL
     */
    private $github_api_url = 'https://api.github.com/repos/KantanPro/news-crawler/releases/latest';
    
    /**
     * GitHub repository URL
     */
    private $github_repo_url = 'https://github.com/KantanPro/news-crawler';
    
    /**
     * Plugin slug
     */
    private $plugin_slug = 'news-crawler';
    
    /**
     * Plugin basename
     */
    private $plugin_basename;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->plugin_basename = plugin_basename(NEWS_CRAWLER_PLUGIN_DIR . 'news-crawler.php');
        
        // WordPress更新システムにフック
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_updates'));
        add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
        add_filter('upgrader_post_install', array($this, 'after_update'), 10, 3);
        
        // 管理画面での更新通知
        add_action('admin_notices', array($this, 'admin_update_notice'));
        
        // 更新チェックのスケジュール
        if (!wp_next_scheduled('news_crawler_update_check')) {
            wp_schedule_event(time(), 'daily', 'news_crawler_update_check');
        }
        add_action('news_crawler_update_check', array($this, 'check_for_updates'));
    }
    
    /**
     * Check for updates
     */
    public function check_for_updates($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        // 現在のバージョンを取得
        $current_version = NEWS_CRAWLER_VERSION;
        
        // GitHubから最新バージョンを取得
        $latest_version = $this->get_latest_version();
        
        if (!$latest_version) {
            return $transient;
        }
        
        // バージョン比較
        if (version_compare($current_version, $latest_version['version'], '<')) {
            $transient->response[$this->plugin_basename] = (object) array(
                'slug' => $this->plugin_slug,
                'new_version' => $latest_version['version'],
                'url' => $this->github_repo_url,
                'package' => $latest_version['download_url'],
                'requires' => '5.0',
                'requires_php' => '7.4',
                'tested' => '6.4',
                'last_updated' => $latest_version['published_at'],
                'sections' => array(
                    'description' => $latest_version['description'],
                    'changelog' => $latest_version['changelog']
                )
            );
        }
        
        return $transient;
    }
    
    /**
     * Get latest version from GitHub
     */
    private function get_latest_version() {
        // キャッシュをチェック
        $cached = get_transient('news_crawler_latest_version');
        if ($cached !== false) {
            return $cached;
        }
        
        // GitHub APIから最新リリース情報を取得
        $response = wp_remote_get($this->github_api_url, array(
            'timeout' => 15,
            'headers' => array(
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
                'Accept' => 'application/vnd.github.v3+json'
            )
        ));
        
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || !isset($data['tag_name'])) {
            return false;
        }
        
        // バージョン情報を整理
        $version_info = array(
            'version' => ltrim($data['tag_name'], 'v'),
            'download_url' => $data['zipball_url'],
            'published_at' => $data['published_at'],
            'description' => $data['body'] ?: '',
            'changelog' => $this->get_changelog_for_version(ltrim($data['tag_name'], 'v'))
        );
        
        // 12時間キャッシュ
        set_transient('news_crawler_latest_version', $version_info, 12 * HOUR_IN_SECONDS);
        
        return $version_info;
    }
    
    /**
     * Get changelog for specific version
     */
    private function get_changelog_for_version($version) {
        $changelog_file = NEWS_CRAWLER_PLUGIN_DIR . 'CHANGELOG.md';
        
        if (!file_exists($changelog_file)) {
            return '';
        }
        
        $content = file_get_contents($changelog_file);
        if (!$content) {
            return '';
        }
        
        // 特定バージョンのチャンジログを抽出
        $pattern = '/## \[' . preg_quote($version, '/') . '\](.*?)(?=## \[|$)/s';
        if (preg_match($pattern, $content, $matches)) {
            return trim($matches[1]);
        }
        
        return '';
    }
    
    /**
     * Plugin information for WordPress
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }
        
        if (!isset($args->slug) || $args->slug !== $this->plugin_slug) {
            return $result;
        }
        
        $latest_version = $this->get_latest_version();
        if (!$latest_version) {
            return $result;
        }
        
        $result = new stdClass();
        $result->name = 'News Crawler';
        $result->slug = $this->plugin_slug;
        $result->version = $latest_version['version'];
        $result->last_updated = $latest_version['published_at'];
        $result->requires = '5.0';
        $result->requires_php = '7.4';
        $result->tested = '6.4';
        $result->download_link = $latest_version['download_url'];
        $result->sections = array(
            'description' => $latest_version['description'],
            'changelog' => $latest_version['changelog']
        );
        
        return $result;
    }
    
    /**
     * After update actions
     */
    public function after_update($response, $hook_extra, $result) {
        if ($hook_extra['plugin'] === $this->plugin_basename) {
            // 更新後の処理
            delete_transient('news_crawler_latest_version');
            
            // 更新完了メッセージ
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p><strong>News Crawler</strong> が正常に更新されました。</p>';
                echo '</div>';
            });
        }
        
        return $response;
    }
    
    /**
     * Admin update notice
     */
    public function admin_update_notice() {
        // 管理者のみに表示
        if (!current_user_can('update_plugins')) {
            return;
        }
        
        $latest_version = $this->get_latest_version();
        if (!$latest_version) {
            return;
        }
        
        $current_version = NEWS_CRAWLER_VERSION;
        
        if (version_compare($current_version, $latest_version['version'], '<')) {
            $update_url = wp_nonce_url(
                admin_url('update.php?action=upgrade-plugin&plugin=' . $this->plugin_basename),
                'upgrade-plugin_' . $this->plugin_basename
            );
            
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>News Crawler</strong> の新しいバージョン ' . esc_html($latest_version['version']) . ' が利用可能です。';
            echo ' <a href="' . esc_url($update_url) . '">今すぐ更新</a> または ';
            echo '<a href="' . esc_url($this->github_repo_url . '/releases') . '" target="_blank">詳細を確認</a></p>';
            echo '</div>';
        }
    }
    
    /**
     * Cleanup on deactivation
     */
    public static function cleanup() {
        wp_clear_scheduled_hook('news_crawler_update_check');
        delete_transient('news_crawler_latest_version');
    }
}
