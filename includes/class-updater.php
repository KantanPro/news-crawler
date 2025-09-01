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
        
        // WordPress更新システムにフック（最優先で実行）
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_updates'), 1, 1);
        add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
        add_filter('upgrader_post_install', array($this, 'after_update'), 10, 3);
        add_filter('upgrader_pre_download', array($this, 'upgrader_pre_download'), 10, 3);
        
        // WordPressの標準的な更新チェックの前に実行されるフィルター（最優先）
        add_filter('site_transient_update_plugins', array($this, 'ensure_transient_properties'), 1, 1);
        
        // さらに早期に実行されるフィルター
        add_filter('transient_update_plugins', array($this, 'ensure_transient_properties'), 1, 1);
        
        // さらに早期に実行されるフィルター（WordPressの更新システムの前に実行）
        add_filter('pre_transient_update_plugins', array($this, 'ensure_transient_properties'), 1, 1);
        add_filter('pre_site_transient_update_plugins', array($this, 'ensure_transient_properties'), 1, 1);
        
        // WordPressの更新チェックが実行される前に確実にプロパティを設定
        add_action('wp_update_plugins', array($this, 'pre_update_check'), 1);
        
        // get_site_transientの結果も確実に修正
        add_filter('site_transient_update_plugins', array($this, 'ensure_transient_properties'), 999, 1);
        
        // さらに、WordPressの更新チェックが実行される直前に確実にプロパティを設定
        add_action('wp_update_plugins', array($this, 'force_transient_properties'), 999);
        
        // WordPressの更新システムが実行される直前に確実にプロパティを設定（最終手段）
        add_filter('pre_set_site_transient_update_plugins', array($this, 'ensure_transient_properties'), 999, 1);
        add_filter('pre_set_transient_update_plugins', array($this, 'ensure_transient_properties'), 999, 1);
        
        // WordPressの更新システムが実行される直前に確実にプロパティを設定（最終的な安全網）
        add_action('wp_update_plugins', array($this, 'ensure_wordpress_compatibility'), 999);
        
        // 管理画面での更新通知
        add_action('admin_notices', array($this, 'admin_update_notice'));
        add_action('admin_init', array($this, 'force_update_check'));
        
        // WordPressの更新システムの初期化を確実に行う
        add_action('init', array($this, 'ensure_update_system'), 1);
        
        // プラグイン初期化時にも確実にプロパティを設定
        add_action('plugins_loaded', array($this, 'ensure_update_system'), 1);
        
        // 管理画面での初期化も確実に行う
        add_action('admin_init', array($this, 'ensure_update_system'), 1);
        
        // さらに早期の初期化
        add_action('muplugins_loaded', array($this, 'ensure_update_system'), 1);
        add_action('after_setup_theme', array($this, 'ensure_update_system'), 1);
        
        // 更新チェックのスケジュール（WordPressの標準的な更新チェックの後に実行）
        try {
            if (!wp_next_scheduled('news_crawler_update_check')) {
                // WordPressの標準的な更新チェックの1時間後に実行
                wp_schedule_event(time() + 3600, 'twicedaily', 'news_crawler_update_check');
            }
            add_action('news_crawler_update_check', array($this, 'scheduled_update_check'));
            
            // 管理画面での手動更新チェックにも対応
            add_action('wp_update_plugins', array($this, 'on_wp_update_plugins'), 20);
        } catch (Exception $e) {
            error_log('News Crawler: Failed to schedule update check: ' . $e->getMessage());
        }
        
        // プラグイン情報ページでの表示
        add_filter('plugin_row_meta', array($this, 'plugin_row_meta'), 10, 2);
    }
    
    /**
     * Ensure WordPress update system is properly initialized
     */
    public function ensure_update_system() {
        // WordPressの更新システムが確実に初期化されるようにする
        if (!get_site_transient('update_plugins')) {
            // 初期のtransientオブジェクトを作成
            $initial_transient = new stdClass();
            $initial_transient = $this->ensure_transient_properties($initial_transient);
            set_site_transient('update_plugins', $initial_transient, 12 * HOUR_IN_SECONDS);
        }
    }
    
    /**
     * Force transient properties to exist (last resort)
     */
    public function force_transient_properties() {
        // 最後の手段として、transientオブジェクトのプロパティを強制的に設定
        $transient = get_site_transient('update_plugins');
        if ($transient) {
            // プロパティが存在しない場合は強制的に設定
            if (!isset($transient->version_checked) || empty($transient->version_checked)) {
                $transient->version_checked = get_bloginfo('version');
            }
            if (!isset($transient->checked)) {
                $transient->checked = array();
            }
            if (!isset($transient->response)) {
                $transient->response = array();
            }
            if (!isset($transient->last_checked)) {
                $transient->last_checked = time();
            }
            if (!isset($transient->translations)) {
                $transient->translations = array();
            }
            
            // 修正されたtransientを保存
            set_site_transient('update_plugins', $transient, 12 * HOUR_IN_SECONDS);
        } else {
            // transientが存在しない場合は、新しいものを作成
            $transient = new stdClass();
            $transient = $this->ensure_transient_properties($transient);
            set_site_transient('update_plugins', $transient, 12 * HOUR_IN_SECONDS);
        }
    }
    
    /**
     * Pre-update check to ensure transient properties exist
     */
    public function pre_update_check() {
        // 更新チェックが実行される前に、transientオブジェクトのプロパティを確実に設定
        $transient = get_site_transient('update_plugins');
        if ($transient) {
            $transient = $this->ensure_transient_properties($transient);
            set_site_transient('update_plugins', $transient, 12 * HOUR_IN_SECONDS);
        } else {
            // transientが存在しない場合は、新しいものを作成
            $transient = new stdClass();
            $transient = $this->ensure_transient_properties($transient);
            set_site_transient('update_plugins', $transient, 12 * HOUR_IN_SECONDS);
        }
    }
    
    /**
     * Ensure transient object has all required properties
     */
    public function ensure_transient_properties($transient) {
        if (!$transient || !is_object($transient)) {
            $transient = new stdClass();
        }
        
        // WordPressが期待するすべてのプロパティを確実に初期化
        $required_properties = array(
            'checked' => array(),
            'response' => array(),
            'last_checked' => time(),
            'version_checked' => get_bloginfo('version'),
            'translations' => array()
        );
        
        foreach ($required_properties as $property => $default_value) {
            if (!isset($transient->$property)) {
                $transient->$property = $default_value;
            }
        }
        
        // version_checkedプロパティを特に確実に設定
        if (!isset($transient->version_checked) || empty($transient->version_checked)) {
            $transient->version_checked = get_bloginfo('version');
        }
        
        // さらに、WordPressの更新システムが内部的に使用する可能性のあるプロパティも設定
        $additional_properties = array(
            'no_update' => array(),
            'updates' => array(),
            'counts' => array(
                'plugins' => 0,
                'themes' => 0,
                'wordpress' => 0,
                'translations' => 0
            )
        );
        
        foreach ($additional_properties as $property => $default_value) {
            if (!isset($transient->$property)) {
                $transient->$property = $default_value;
            }
        }
        
        // WordPressの更新システムが内部的に使用する可能性のある追加プロパティ
        $wordpress_internal_properties = array(
            'last_checked' => time(),
            'checked' => array(),
            'response' => array(),
            'version_checked' => get_bloginfo('version'),
            'translations' => array()
        );
        
        foreach ($wordpress_internal_properties as $property => $default_value) {
            if (!isset($transient->$property)) {
                $transient->$property = $default_value;
            }
        }
        
        return $transient;
    }
    
    /**
     * Ensure WordPress compatibility (final safety net)
     */
    public function ensure_wordpress_compatibility() {
        // WordPressの更新システムが実行される前に、確実にプロパティを設定
        $transient = get_site_transient('update_plugins');
        if ($transient) {
            // プロパティが存在しない場合は強制的に設定
            $transient = $this->ensure_transient_properties($transient);
            set_site_transient('update_plugins', $transient, 12 * HOUR_IN_SECONDS);
        } else {
            // transientが存在しない場合は、新しいものを作成
            $transient = new stdClass();
            $transient = $this->ensure_transient_properties($transient);
            set_site_transient('update_plugins', $transient, 12 * HOUR_IN_SECONDS);
        }
        
        // さらに、WordPressの更新システムが内部的に使用する可能性のあるプロパティも設定
        if (!isset($transient->version_checked)) {
            $transient->version_checked = get_bloginfo('version');
        }
        if (!isset($transient->checked)) {
            $transient->checked = array();
        }
        if (!isset($transient->response)) {
            $transient->response = array();
        }
        if (!isset($transient->last_checked)) {
            $transient->last_checked = time();
        }
        if (!isset($transient->translations)) {
            $transient->translations = array();
        }
        
        // 修正されたtransientを保存
        set_site_transient('update_plugins', $transient, 12 * HOUR_IN_SECONDS);
    }
    
    /**
     * Check for updates
     */
    public function check_for_updates($transient) {
        try {
            // プロパティの初期化を確実に行う
            $transient = $this->ensure_transient_properties($transient);
            
            // version_checkedプロパティを確実に設定
            if (!isset($transient->version_checked)) {
                $transient->version_checked = get_bloginfo('version');
            }
            
            // 現在のバージョンを取得
            $current_version = NEWS_CRAWLER_VERSION;
        
        // GitHubから最新バージョンを取得
        $latest_version = $this->get_latest_version();
        
        if (!$latest_version || !isset($latest_version['version'])) {
            return $transient;
        }
        
        // バージョン比較（デバッグ情報を追加）
        $version_comparison = version_compare($current_version, $latest_version['version'], '<');
        
        // デバッグログ（開発環境でのみ有効）
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("News Crawler Update Check: Current: {$current_version}, Latest: {$latest_version['version']}, Has Update: " . ($version_comparison ? 'Yes' : 'No'));
        }
        
        if ($version_comparison) {
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
        }
        
        return $transient;
        
        } catch (Exception $e) {
            error_log('News Crawler: Error during update check: ' . $e->getMessage());
            // エラーが発生した場合は、元のtransientをそのまま返す
            return $transient;
        }
    }
    
    /**
     * WordPressの標準的な更新チェック完了後に実行
     */
    public function on_wp_update_plugins() {
        // 少し遅延させてから実行（WordPressの処理完了を待つ）
        wp_schedule_single_event(time() + 60, 'news_crawler_delayed_update_check');
        add_action('news_crawler_delayed_update_check', array($this, 'delayed_update_check'));
    }
    
    /**
     * 遅延更新チェック
     */
    public function delayed_update_check() {
        $transient = get_site_transient('update_plugins');
        if ($transient) {
            $this->check_for_updates($transient);
        } else {
            // transientが存在しない場合は、新しいものを作成して更新チェックを実行
            $transient = new stdClass();
            $this->check_for_updates($transient);
        }
    }
    
    /**
     * Scheduled update check
     */
    public function scheduled_update_check() {
        // WordPressの標準的な更新チェックを先に実行
        wp_update_plugins();
        
        // その後でカスタム更新チェックを実行
        $transient = get_site_transient('update_plugins');
        if ($transient) {
            $this->check_for_updates($transient);
        } else {
            // transientが存在しない場合は、新しいものを作成して更新チェックを実行
            $transient = new stdClass();
            $this->check_for_updates($transient);
        }
    }
    
    /**
     * Force update check
     */
    public function force_update_check() {
        if (isset($_GET['force-check']) && $_GET['force-check'] == '1') {
            delete_transient('news_crawler_latest_version');
            delete_site_transient('update_plugins');
            delete_transient('news_crawler_last_check');
        }
        
        // 管理画面での手動更新チェック
        if (isset($_GET['page']) && $_GET['page'] === 'news-crawler-settings') {
            // 設定画面にアクセスした際にキャッシュをクリア
            delete_transient('news_crawler_latest_version');
            
            // 更新チェックを実行（安全に）
            $transient = get_site_transient('update_plugins');
            if ($transient) {
                $this->check_for_updates($transient);
            }
        }
        
        // キャッシュクリア機能
        if (isset($_GET['clear-cache']) && $_GET['clear-cache'] === '1') {
            delete_transient('news_crawler_latest_version');
            delete_site_transient('update_plugins');
            delete_transient('news_crawler_last_check');
            wp_clean_plugins_cache();
            
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
        if (!current_user_can('update_plugins')) {
            return;
        }
        
        $latest_version = $this->get_latest_version();
        if (!$latest_version || !isset($latest_version['version'])) {
            return;
        }
        
        $current_version = NEWS_CRAWLER_VERSION;
        
        if (version_compare($current_version, $latest_version['version'], '<')) {
            $update_url = wp_nonce_url(
                admin_url('update.php?action=upgrade-plugin&plugin=' . $this->plugin_basename),
                'upgrade-plugin_' . $this->plugin_basename
            );
            
            $force_check_url = add_query_arg('force-check', '1', admin_url('update-core.php'));
            $clear_cache_url = add_query_arg('clear-cache', '1', admin_url('admin.php?page=news-crawler-settings'));
            
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>News Crawler</strong> の新しいバージョン <strong>' . esc_html($latest_version['version']) . '</strong> が利用可能です。';
            echo ' <a href="' . esc_url($update_url) . '" class="button button-primary">今すぐ更新</a> ';
            echo ' <a href="' . esc_url($this->github_repo_url . '/releases') . '" target="_blank" class="button">詳細を確認</a> ';
            echo ' <a href="' . esc_url($force_check_url) . '" class="button">更新を再チェック</a>';
            echo ' <a href="' . esc_url($clear_cache_url) . '" class="button">キャッシュクリア</a></p>';
            echo '<p><small>更新後はページを再読み込みして、バージョン情報が正しく表示されることを確認してください。</small></p>';
            echo '</div>';
        }
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
            'last_checked' => get_transient('news_crawler_last_check'),
            'download_url' => $latest_version['download_url']
        );
    }
    
    /**
     * Cleanup on deactivation
     */
    public static function cleanup() {
        wp_clear_scheduled_hook('news_crawler_update_check');
        delete_transient('news_crawler_latest_version');
        delete_transient('news_crawler_last_check');
    }
}
