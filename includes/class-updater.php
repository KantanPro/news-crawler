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
        add_filter('upgrader_post_install', array($this, 'after_update'), 10, 3);
        add_filter('upgrader_pre_download', array($this, 'upgrader_pre_download'), 10, 3);
        
        // 管理画面での更新通知
        add_action('admin_notices', array($this, 'admin_update_notice'));
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
                'tested' => '6.4',
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
            delete_transient('news_crawler_latest_version');
            delete_site_transient('update_plugins');
            wp_clean_plugins_cache();
            wp_update_plugins();
        }
        
        // キャッシュクリア機能
        if (isset($_GET['clear-cache']) && $_GET['clear-cache'] === '1') {
            delete_transient('news_crawler_latest_version');
            delete_site_transient('update_plugins');
            wp_clean_plugins_cache();
            wp_update_plugins();
            
            // 成功メッセージ
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p><strong>News Crawler</strong> のキャッシュがクリアされました。更新情報を再チェックしてください。</p>';
                echo '</div>';
            });
        }
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
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
                'Accept' => 'application/vnd.github.v3+json',
                'Cache-Control' => 'no-cache'
            )
        ));
        
        if (is_wp_error($response)) {
            error_log('News Crawler: GitHub API request failed: ' . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('News Crawler: GitHub API returned status code: ' . $response_code);
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
        
        // 6時間キャッシュ（より頻繁なチェック）
        set_transient('news_crawler_latest_version', $version_info, 6 * HOUR_IN_SECONDS);
        
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
     * After update actions
     */
    public function after_update($response, $hook_extra, $result) {
        if (isset($hook_extra['plugin']) && $hook_extra['plugin'] === $this->plugin_basename) {
            // 更新後の処理
            delete_transient('news_crawler_latest_version');
            delete_site_transient('update_plugins');
            delete_transient('news_crawler_last_check');
            
            // プラグイン情報の再読み込みを強制
            wp_clean_plugins_cache();
            
            // 更新完了メッセージ
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p><strong>News Crawler</strong> が正常に更新されました。新しい機能や改善点については、<a href="' . esc_url($this->github_repo_url . '/releases') . '" target="_blank">リリースノート</a>をご確認ください。</p>';
                echo '<p>ページを再読み込みして、バージョン情報が正しく表示されることを確認してください。</p>';
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
        if (!current_user_can('update_plugins') && !current_user_can('manage_options')) {
            return;
        }
        
        // WordPressの標準的な更新通知システムを使用
        $transient = get_site_transient('update_plugins');
        if (!$transient || !is_object($transient) || !isset($transient->response[$this->plugin_basename])) {
            return;
        }
        
        $update = $transient->response[$this->plugin_basename];
        $new_version = isset($update->new_version) ? $update->new_version : '';
        $current_version = NEWS_CRAWLER_VERSION;
        
        // バージョンチェック
        if (!$new_version || version_compare($current_version, $new_version, '>=')) {
            return;
        }
        
        $update_url = wp_nonce_url(
            admin_url('update.php?action=upgrade-plugin&plugin=' . $this->plugin_basename),
            'upgrade-plugin_' . $this->plugin_basename
        );
        
        $force_check_url = add_query_arg('force-check', '1', admin_url('update-core.php'));
        $clear_cache_url = add_query_arg('clear-cache', '1', admin_url('admin.php?page=news-crawler-main'));
        
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>News Crawler</strong> の新しいバージョン <strong>' . esc_html($new_version) . '</strong> が利用可能です。';
        echo ' <a href="' . esc_url($update_url) . '" class="button button-primary">今すぐ更新</a> ';
        echo ' <a href="' . esc_url($this->github_repo_url . '/releases') . '" target="_blank" class="button">詳細を確認</a> ';
        echo ' <a href="' . esc_url($force_check_url) . '" class="button">更新を再チェック</a>';
        echo ' <a href="' . esc_url($clear_cache_url) . '" class="button">キャッシュクリア</a></p>';
        echo '<p><small>更新後はページを再読み込みして、バージョン情報が正しく表示されることを確認してください。</small></p>';
        echo '</div>';
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
     * Cleanup on deactivation
     */
    public static function cleanup() {
        wp_clear_scheduled_hook('news_crawler_update_check');
        delete_transient('news_crawler_latest_version');
    }
}
