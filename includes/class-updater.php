<?php
/**
 * News Crawler Updater Class
 * 
 * Handles automatic updates from GitHub releases using WordPress standard update system
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
     * Plugin file path
     */
    private $plugin_file;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->plugin_basename = plugin_basename(NEWS_CRAWLER_PLUGIN_DIR . 'news-crawler.php');
        $this->plugin_file = NEWS_CRAWLER_PLUGIN_DIR . 'news-crawler.php';
        
        // WordPress更新システムにフック
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_updates'));
        add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
        add_filter('upgrader_pre_install', array($this, 'before_update'), 10, 3);
        add_filter('upgrader_post_install', array($this, 'after_update'), 10, 3);
        add_filter('upgrader_pre_download', array($this, 'upgrader_pre_download'), 10, 3);
        
        // 管理画面での更新通知（WordPress標準のみ使用）
        add_action('admin_init', array($this, 'force_update_check'));
        
        // 更新チェックのスケジュール
        if (!wp_next_scheduled('news_crawler_update_check')) {
            wp_schedule_event(time(), 'twicedaily', 'news_crawler_update_check');
        }
        add_action('news_crawler_update_check', array($this, 'scheduled_update_check'));
        
        // プラグイン情報ページでの表示
        add_filter('plugin_row_meta', array($this, 'plugin_row_meta'), 10, 2);
    }
    
    
    /**
     * Check for updates
     */
    public function check_for_updates($transient) {
        // プラグインの更新チェックが無効化されている場合はスキップ
        if (isset($transient->no_update) && is_array($transient->no_update) && isset($transient->no_update[$this->plugin_basename])) {
            return $transient;
        }
        
        // 現在のバージョンを取得
        $current_version = NEWS_CRAWLER_VERSION;
        
        // インストール済みバージョンをWP側のcheckedに登録
        if (!isset($transient->checked)) {
            $transient->checked = array();
        }
        $transient->checked[$this->plugin_basename] = $current_version;
        
        // GitHubから最新バージョンを取得
        $latest_version = $this->get_latest_version();
        
        if (!$latest_version || !isset($latest_version['version'])) {
            // エラー時はログを記録
            error_log('News Crawler: Failed to get latest version information');
            return $transient;
        }
        
        // バージョン比較
        $has_update = version_compare($current_version, $latest_version['version'], '<');
        
        if ($has_update) {
            // 更新が利用可能な場合
            if (!isset($transient->response)) {
                $transient->response = array();
            }
            
            $transient->response[$this->plugin_basename] = (object) array(
                'slug' => $this->plugin_slug,
                'new_version' => $latest_version['version'],
                'url' => $this->github_repo_url,
                'package' => $latest_version['download_url'],
                'requires' => '5.0',
                'requires_php' => '7.4',
                'tested' => '6.9.1',
                'last_updated' => $latest_version['published_at'],
                'sections' => array(
                    'description' => $latest_version['description'],
                    'changelog' => $latest_version['changelog']
                ),
                'banners' => array(
                    'high' => '',
                    'low' => ''
                )
            );
            
            // 更新通知のログを記録
            error_log('News Crawler: Update available - Current: ' . $current_version . ', Latest: ' . $latest_version['version']);
        } else {
            // 最新バージョンの場合、no_updateに登録
            if (!isset($transient->no_update)) {
                $transient->no_update = array();
            }
            
            $transient->no_update[$this->plugin_basename] = (object) array(
                'id' => $this->plugin_slug,
                'slug' => $this->plugin_slug,
                'plugin' => $this->plugin_basename,
                'new_version' => $current_version,
                'url' => $this->github_repo_url,
                'package' => ''
            );
            
            // 古いresponseエントリをクリア
            if (isset($transient->response[$this->plugin_basename])) {
                unset($transient->response[$this->plugin_basename]);
            }
        }
        
        // キャッシュクリアを強制実行してバージョン情報を更新
        if (isset($_GET['force-check']) && $_GET['force-check'] == '1') {
            delete_transient('news_crawler_latest_version');
            delete_site_transient('update_plugins');
            wp_clean_plugins_cache();
        }
        
        return $transient;
    }
    
    /**
     * Scheduled update check
     */
    public function scheduled_update_check() {
        // WordPressの標準的な更新チェックを実行
        wp_update_plugins();
    }
    
    /**
     * Force update check
     */
    public function force_update_check() {
        // 権限チェック
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // 強制更新チェック
        if (isset($_GET['force-check']) && $_GET['force-check'] == '1') {
            $this->clear_all_caches();
            wp_update_plugins();
        }
        
        // キャッシュクリア機能
        if (isset($_GET['clear-cache']) && $_GET['clear-cache'] === '1') {
            $this->clear_all_caches();
            wp_update_plugins();
        }
    }
    
    /**
     * すべてのキャッシュをクリア
     */
    private function clear_all_caches() {
        // プラグイン関連のキャッシュをクリア
        delete_transient('news_crawler_latest_version');
        delete_transient('news_crawler_latest_version_backup');
        delete_site_transient('update_plugins');
        delete_site_transient('update_plugins_checked');
        
        // WordPressのキャッシュをクリア
        wp_clean_plugins_cache();
        wp_cache_flush();
        
        // オブジェクトキャッシュをクリア
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('plugins');
        }
    }
    
    /**
     * Get latest version from GitHub
     */
    private function get_latest_version() {
        // キャッシュをチェック（1時間に短縮）
        $cached = get_transient('news_crawler_latest_version');
        if ($cached !== false) {
            return $cached;
        }
        
        // GitHub APIから最新リリース情報を取得
        $response = wp_remote_get($this->github_api_url, array(
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
                'Accept' => 'application/vnd.github.v3+json',
                'Cache-Control' => 'no-cache'
            )
        ));
        
        if (is_wp_error($response)) {
            error_log('News Crawler: GitHub API request failed: ' . $response->get_error_message());
            // エラー時は古いキャッシュがあれば返す
            $old_cached = get_transient('news_crawler_latest_version_backup');
            if ($old_cached !== false) {
                return $old_cached;
            }
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('News Crawler: GitHub API returned status code: ' . $response_code);
            // レート制限の場合は古いキャッシュを返す
            if ($response_code === 403) {
                $old_cached = get_transient('news_crawler_latest_version_backup');
                if ($old_cached !== false) {
                    return $old_cached;
                }
            }
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || !isset($data['tag_name'])) {
            error_log('News Crawler: Invalid GitHub API response');
            return false;
        }
        
        // バージョン情報を整理
        $version_info = array(
            'version' => ltrim($data['tag_name'], 'v'),
            'download_url' => $data['zipball_url'],
            'published_at' => $data['published_at'],
            'description' => $data['body'] ?: '',
            'changelog' => $this->get_changelog_for_version(ltrim($data['tag_name'], 'v')),
            'prerelease' => isset($data['prerelease']) ? $data['prerelease'] : false,
            'draft' => isset($data['draft']) ? $data['draft'] : false
        );
        
        // 1時間キャッシュ（より頻繁なチェック）
        set_transient('news_crawler_latest_version', $version_info, HOUR_IN_SECONDS);
        // バックアップキャッシュ（24時間）
        set_transient('news_crawler_latest_version_backup', $version_info, DAY_IN_SECONDS);
        
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
        $result->tested = '6.9.1';
        $result->download_link = $latest_version['download_url'];
        $result->sections = array(
            'description' => $latest_version['description'],
            'changelog' => $latest_version['changelog']
        );
        
        return $result;
    }
    
    /**
     * Pre-download filter
     */
    public function upgrader_pre_download($reply, $package, $upgrader) {
        if (strpos($package, 'github.com') !== false) {
            // GitHubからのダウンロードの場合の特別な処理
            add_filter('http_request_args', array($this, 'github_download_args'), 10, 2);
        }
        return $reply;
    }
    
    /**
     * GitHub download arguments
     */
    public function github_download_args($args, $url) {
        if (strpos($url, 'github.com') !== false) {
            $args['timeout'] = 60;
            $args['headers']['User-Agent'] = 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url');
        }
        return $args;
    }
    
    /**
     * Before update actions
     */
    public function before_update($response, $hook_extra, $result = null) {
        if (isset($hook_extra['plugin']) && $hook_extra['plugin'] === $this->plugin_basename) {
            // 更新前の処理
            $this->cleanup_old_files();
            
            // プラグインの一時的な無効化（更新中）
            if (is_plugin_active($this->plugin_basename)) {
                deactivate_plugins($this->plugin_basename, true);
            }
        }
        
        return $response;
    }
    
    /**
     * After update actions
     */
    public function after_update($response, $hook_extra, $result) {
        if (isset($hook_extra['plugin']) && $hook_extra['plugin'] === $this->plugin_basename) {
            // 更新後の処理
            delete_transient('news_crawler_latest_version');
            delete_transient('news_crawler_latest_version_backup');
            delete_site_transient('update_plugins');
            delete_site_transient('update_plugins_checked');
            delete_transient('news_crawler_last_check');
            
            // プラグイン情報の再読み込みを強制
            wp_clean_plugins_cache();
            
            // プラグインの再読み込みを強制
            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }
            
            // オブジェクトキャッシュをクリア
            if (function_exists('wp_cache_flush_group')) {
                wp_cache_flush_group('plugins');
            }
            
            // 更新後の整合性チェック
            $this->verify_update_integrity();
            
            // プラグインの状態をリセット（無効化/有効化は行わない）
            // WordPressの更新システムが適切に処理するため
            
        }
        
        return $response;
    }
    
    
    /**
     * Plugin row meta
     */
    public function plugin_row_meta($links, $file) {
        if ($file === $this->plugin_basename) {
            $links[] = '<a href="' . esc_url($this->github_repo_url) . '" target="_blank">GitHub</a>';
            $links[] = '<a href="' . esc_url($this->github_repo_url . '/releases') . '" target="_blank">リリース</a>';
        }
        return $links;
    }
    
    /**
     * Get update status
     */
    public function get_update_status() {
        $latest_version = $this->get_latest_version();
        if (!$latest_version) {
            return array(
                'status' => 'error',
                'message' => 'GitHubからの更新情報の取得に失敗しました'
            );
        }
        
        $current_version = NEWS_CRAWLER_VERSION;
        $has_update = version_compare($current_version, $latest_version['version'], '<');
        
        return array(
            'status' => 'success',
            'current_version' => $current_version,
            'latest_version' => $latest_version['version'],
            'has_update' => $has_update,
            'download_url' => $latest_version['download_url']
        );
    }
    
    /**
     * Debug update system
     */
    public function debug_update_system() {
        $debug_info = array();
        
        // 現在のバージョン
        $debug_info['current_version'] = NEWS_CRAWLER_VERSION;
        
        // プラグインベース名
        $debug_info['plugin_basename'] = $this->plugin_basename;
        
        // キャッシュ状況
        $debug_info['cached_version'] = get_transient('news_crawler_latest_version');
        $debug_info['backup_cached_version'] = get_transient('news_crawler_latest_version_backup');
        
        // WordPress更新システムの状況
        $transient = get_site_transient('update_plugins');
        $debug_info['wp_transient_exists'] = !empty($transient);
        $debug_info['wp_transient_response'] = isset($transient->response[$this->plugin_basename]) ? $transient->response[$this->plugin_basename] : null;
        $debug_info['wp_transient_checked'] = isset($transient->checked[$this->plugin_basename]) ? $transient->checked[$this->plugin_basename] : null;
        
        // GitHub APIテスト
        $response = wp_remote_get($this->github_api_url, array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
                'Accept' => 'application/vnd.github.v3+json'
            )
        ));
        
        if (is_wp_error($response)) {
            $debug_info['github_api_error'] = $response->get_error_message();
        } else {
            $debug_info['github_api_status'] = wp_remote_retrieve_response_code($response);
            $debug_info['github_api_response'] = wp_remote_retrieve_body($response);
        }
        
        // スケジュール状況
        $debug_info['scheduled_check'] = wp_next_scheduled('news_crawler_update_check');
        
        return $debug_info;
    }
    
    /**
     * Verify update integrity
     */
    private function verify_update_integrity() {
        $plugin_dir = NEWS_CRAWLER_PLUGIN_DIR;
        
        // 必須ファイルの存在チェック
        $required_files = array(
            'news-crawler.php',
            'includes/class-updater.php',
            'includes/class-settings-manager.php',
            'includes/class-youtube-crawler.php'
        );
        
        $missing_files = array();
        foreach ($required_files as $file) {
            if (!file_exists($plugin_dir . $file)) {
                $missing_files[] = $file;
            }
        }
        
        if (!empty($missing_files)) {
            error_log('News Crawler: Update integrity check failed. Missing files: ' . implode(', ', $missing_files));
            return false;
        }
        
        // プラグインのバージョン情報をチェック
        if (!defined('NEWS_CRAWLER_VERSION')) {
            error_log('News Crawler: Update integrity check failed. Version constant not defined.');
            return false;
        }
        
        // プラグインの基本機能が動作するかチェック
        if (!class_exists('NewsCrawlerUpdater')) {
            error_log('News Crawler: Update integrity check failed. Updater class not found.');
            return false;
        }
        
        return true;
    }
    
    /**
     * Cleanup old files before update
     */
    private function cleanup_old_files() {
        $plugin_dir = NEWS_CRAWLER_PLUGIN_DIR;
        
        // 古いファイルやディレクトリをクリーンアップ
        $old_files = array(
            'news-crawler-cron.log',
            'news-crawler-cron.sh',
            'CHANGELOG.md',
            'README.md'
        );
        
        foreach ($old_files as $file) {
            $file_path = $plugin_dir . $file;
            if (file_exists($file_path)) {
                if (is_file($file_path)) {
                    unlink($file_path);
                } elseif (is_dir($file_path)) {
                    $this->recursive_rmdir($file_path);
                }
            }
        }
        
        // 一時ファイルのクリーンアップ
        $temp_files = glob($plugin_dir . '*.tmp');
        foreach ($temp_files as $temp_file) {
            if (file_exists($temp_file)) {
                unlink($temp_file);
            }
        }
    }
    
    /**
     * Recursively remove directory
     */
    private function recursive_rmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        $this->recursive_rmdir($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
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
