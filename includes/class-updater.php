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
     * Repository owner
     */
    private $repo_owner = 'KantanPro';
    
    /**
     * Repository name
     */
    private $repo_name = 'news-crawler';
    
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
        
        // WordPress更新システムにフック（2.3.91の安定した実装に基づく）
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_updates'));
        add_filter('site_transient_update_plugins', array($this, 'check_for_updates'));
        add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
        add_filter('upgrader_pre_install', array($this, 'before_update'), 10, 3);
        // コピー直後にGitHub由来のフォルダ名を正規のスラッグへ統一
        add_filter('upgrader_post_install', array($this, 'rename_github_source'), 9, 3);
        add_filter('upgrader_post_install', array($this, 'after_update'), 10, 3);
        add_filter('upgrader_pre_download', array($this, 'upgrader_pre_download'), 10, 3);
        
        // 管理画面での更新通知（WordPress標準のみ使用）
        add_action('admin_init', array($this, 'force_update_check'));
        
        // デバッグ用のAJAXハンドラー
        add_action('wp_ajax_news_crawler_debug_updates', array($this, 'ajax_debug_updates'));
        
        // 更新チェックのスケジュール
        if (!wp_next_scheduled('news_crawler_update_check')) {
            wp_schedule_event(time(), 'twicedaily', 'news_crawler_update_check');
        }
        add_action('news_crawler_update_check', array($this, 'scheduled_update_check'));
        
        // プラグイン情報ページでの表示
        add_filter('plugin_row_meta', array($this, 'plugin_row_meta'), 10, 2);
        
        // 更新後の自動有効化処理
        add_action('upgrader_process_complete', array($this, 'handle_auto_activation'), 10, 2);

        // 有効化直後の管理画面リロード処理
        add_action('admin_init', array($this, 'maybe_reload_admin_after_activation'));
    }
    
    
    /**
     * Check for updates
     */
    public function check_for_updates($transient) {
        // 管理画面またはWP-Cron以外では更新チェックを行わない
        if (!is_admin() && !(defined('DOING_CRON') && DOING_CRON)) {
            return $transient;
        }
        
        // $transient が null または false の場合は防御的に初期化
        if ($transient === null || $transient === false) {
            $transient = new stdClass();
        }
        
        // プラグインの基本情報を取得
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $plugin_data = get_plugin_data($this->plugin_file, false, false);
        $current_version = isset($plugin_data['Version']) ? $plugin_data['Version'] : NEWS_CRAWLER_VERSION;
        
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
                'id' => $this->plugin_slug,
                'slug' => $this->plugin_slug,
                'plugin' => $this->plugin_basename,
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
            
            // 古いno_updateエントリをクリア
            if (isset($transient->no_update[$this->plugin_basename])) {
                unset($transient->no_update[$this->plugin_basename]);
            }
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
            $this->clear_all_caches();
            wp_update_plugins();
        }
        
        // 更新通知の表示を確実にするため、定期的にキャッシュをクリア
        $last_clear = get_option('news_crawler_last_cache_clear', 0);
        if (time() - $last_clear > 3600) { // 1時間ごと
            $this->clear_all_caches();
            update_option('news_crawler_last_cache_clear', time());
        }
        
        // 管理画面での更新チェックを強化
        if (is_admin() && !wp_doing_ajax()) {
            // プラグイン一覧ページで更新チェックを実行
            $screen = get_current_screen();
            if ($screen && $screen->id === 'plugins') {
                $this->ensure_update_check();
            }
        }
    }
    
    /**
     * 更新チェックを確実に実行
     */
    private function ensure_update_check() {
        // 最後の更新チェックから一定時間経過している場合のみ実行
        $last_check = get_option('news_crawler_last_update_check', 0);
        if (time() - $last_check > 300) { // 5分間隔
            // キャッシュをクリアして更新チェックを実行
            $this->clear_all_caches();
            wp_update_plugins();
            update_option('news_crawler_last_update_check', time());
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
        // 強制更新チェックが指定されている場合はキャッシュを無視
        $force_refresh = (is_admin() && isset($_GET['force-check']) && $_GET['force-check'] == '1');
        
        // キャッシュをチェック（15分）
        if (!$force_refresh) {
            $cached = get_transient('news_crawler_latest_version');
            if ($cached !== false) {
                return $cached;
            }
        }
        
        // GitHub API共通ヘッダー
        $headers = array(
            'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
            'Accept' => 'application/vnd.github.v3+json',
            'Cache-Control' => 'no-cache'
        );
        // オプションのトークン対応（レート制限回避）
        if (defined('NEWS_CRAWLER_GITHUB_TOKEN') && NEWS_CRAWLER_GITHUB_TOKEN) {
            $headers['Authorization'] = 'Bearer ' . NEWS_CRAWLER_GITHUB_TOKEN;
        } elseif (defined('KP_GITHUB_TOKEN') && KP_GITHUB_TOKEN) {
            $headers['Authorization'] = 'Bearer ' . KP_GITHUB_TOKEN;
        }
        
        // /releases/latest で取得を試行
        $latest_url = 'https://api.github.com/repos/' . $this->repo_owner . '/' . $this->repo_name . '/releases/latest';
        $response = wp_remote_get($latest_url, array(
            'timeout' => 15,
            'headers' => $headers,
        ));
        
        $data = null;
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
        }
        
        // latestが取れない、または下書き/プレリリースの場合は /releases から安定版を探索
        if (!$data || !isset($data['tag_name']) || !empty($data['draft']) || !empty($data['prerelease'])) {
            $releases_url = 'https://api.github.com/repos/' . $this->repo_owner . '/' . $this->repo_name . '/releases';
            $resp2 = wp_remote_get($releases_url, array(
                'timeout' => 15,
                'headers' => $headers,
            ));
            if (!is_wp_error($resp2) && wp_remote_retrieve_response_code($resp2) === 200) {
                $list = json_decode(wp_remote_retrieve_body($resp2), true);
                if (is_array($list)) {
                    foreach ($list as $release) {
                        if (!empty($release['draft']) || !empty($release['prerelease'])) {
                            continue;
                        }
                        if (isset($release['tag_name'])) {
                            $data = $release;
                            break;
                        }
                    }
                }
            }
        }
        
        if (!$data || !isset($data['tag_name'])) {
            // エラー時は古いキャッシュがあれば返す
            $old_cached = get_transient('news_crawler_latest_version_backup');
            if ($old_cached !== false) {
                return $old_cached;
            }
            return false;
        }
        
        // バージョン情報を整理
        $normalized_version = ltrim($data['tag_name'], 'v');
        // 配布アセット(zip)があればそれを優先（ルートフォルダ名を正しく保つため）
        $download_url = isset($data['zipball_url']) ? $data['zipball_url'] : '';
        if (isset($data['assets']) && is_array($data['assets'])) {
            foreach ($data['assets'] as $asset) {
                if (!empty($asset['browser_download_url']) && preg_match('/\.zip$/i', $asset['browser_download_url'])) {
                    // 名前に news-crawler を含むzipを優先
                    if (!empty($asset['name']) && stripos($asset['name'], 'news-crawler') !== false) {
                        $download_url = $asset['browser_download_url'];
                        break;
                    }
                    // どれでもzipがあれば最後に採用
                    $download_url = $asset['browser_download_url'];
                }
            }
        }
        $version_info = array(
            'version' => $normalized_version,
            'download_url' => $download_url,
            'published_at' => isset($data['published_at']) ? $data['published_at'] : '',
            'description' => isset($data['body']) && $data['body'] ? $data['body'] : '',
            'changelog' => $this->get_changelog_for_version($normalized_version),
            'prerelease' => isset($data['prerelease']) ? $data['prerelease'] : false,
            'draft' => isset($data['draft']) ? $data['draft'] : false
        );
        
        // 15分キャッシュ
        set_transient('news_crawler_latest_version', $version_info, 15 * MINUTE_IN_SECONDS);
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
            // 認証トークンの追加
            if (defined('NEWS_CRAWLER_GITHUB_TOKEN') && NEWS_CRAWLER_GITHUB_TOKEN) {
                $args['headers']['Authorization'] = 'Bearer ' . NEWS_CRAWLER_GITHUB_TOKEN;
            } elseif (defined('KP_GITHUB_TOKEN') && KP_GITHUB_TOKEN) {
                $args['headers']['Authorization'] = 'Bearer ' . KP_GITHUB_TOKEN;
            }
        }
        return $args;
    }
    
    /**
     * Before update actions
     */
    public function before_update($response, $hook_extra, $result = null) {
        if (isset($hook_extra['plugin']) && $hook_extra['plugin'] === $this->plugin_basename) {
            // プラグインの一時的な無効化（更新中）
            $was_network_active = is_multisite() && is_plugin_active_for_network($this->plugin_basename);
            $was_active = is_plugin_active($this->plugin_basename) || $was_network_active;

            // 更新前の有効状態を保存（30分間有効）
            set_site_transient('news_crawler_pre_update_state', array(
                'was_active' => $was_active,
                'network_active' => $was_network_active,
            ), 30 * MINUTE_IN_SECONDS);

            if ($was_active) {
                deactivate_plugins($this->plugin_basename, true, $was_network_active);
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
        }
        
        return $response;
    }

    /**
     * GitHub由来のフォルダ名を正規スラッグに統一
     */
    public function rename_github_source($response, $hook_extra, $result) {
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_basename) {
            return $response;
        }
        if (empty($result) || empty($result['destination']) || empty($result['source'])) {
            return $response;
        }
        $destination = trailingslashit($result['destination']);
        $source      = trailingslashit($result['source']);

        // 期待する最終パス
        $expected_dir = trailingslashit(WP_PLUGIN_DIR) . 'news-crawler/';

        // すでに正しい場所にある場合は何もしない
        if (untrailingslashit($destination) === untrailingslashit($expected_dir)) {
            return $response;
        }

        // source が news-crawler-* のような一時ディレクトリなら、expected_dir へリネーム
        if (strpos(basename($source), 'news-crawler') === 0) {
            // 既存の expected_dir を削除（古い残骸回避）
            if (is_dir($expected_dir)) {
                // 安全に削除
                $this->rmdir_recursive($expected_dir);
            }
            // 上位に移動/リネーム
            @rename($source, $expected_dir);
            // destination を更新
            $result['destination'] = $expected_dir;
            $response = $result;
        }

        return $response;
    }

    private function rmdir_recursive($dir) {
        if (!is_dir($dir)) return;
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->rmdir_recursive($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
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
        
        // get_plugin_data()を使ってローカルのプラグインバージョンを取得
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $plugin_data = get_plugin_data($this->plugin_file, false, false);
        $current_version = isset($plugin_data['Version']) ? $plugin_data['Version'] : NEWS_CRAWLER_VERSION;
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
        
        // get_plugin_data()を使ってローカルのプラグインバージョンを取得
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $plugin_data = get_plugin_data($this->plugin_file, false, false);
        $debug_info['current_version'] = isset($plugin_data['Version']) ? $plugin_data['Version'] : NEWS_CRAWLER_VERSION;
        
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
        $test_url = 'https://api.github.com/repos/' . $this->repo_owner . '/' . $this->repo_name . '/releases/latest';
        $response = wp_remote_get($test_url, array(
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
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $plugin_data = get_plugin_data($this->plugin_file, false, false);
        if (!isset($plugin_data['Version']) || empty($plugin_data['Version'])) {
            error_log('News Crawler: Update integrity check failed. Version not found in plugin data.');
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
     * 更新後の自動有効化処理
     */
    public function handle_auto_activation($upgrader_object, $options) {
        // プラグインの更新が完了した場合のみ処理
        if ($options['action'] === 'update' && $options['type'] === 'plugin') {
            // 対象プラグインが更新されたかチェック
            if (isset($options['plugins']) && in_array($this->plugin_basename, $options['plugins'])) {
                // 更新前の有効状態を取得
                $was_active = get_site_transient('news_crawler_pre_update_state');
                
                // キャッシュクリア
                delete_transient('news_crawler_latest_version');
                delete_transient('news_crawler_latest_version_backup');
                delete_site_transient('update_plugins');
                delete_site_transient('update_plugins_checked');
                wp_clean_plugins_cache();
                
                // 更新前の状態に応じてプラグインを再有効化
                if ($was_active && isset($was_active['was_active']) && $was_active['was_active']) {
                    // プラグインが無効化されている場合のみ再有効化
                    if (!is_plugin_active($this->plugin_basename)) {
                        if (isset($was_active['network_active']) && $was_active['network_active']) {
                            // ネットワーク有効化
                            activate_plugin($this->plugin_basename, '', true);
                        } else {
                            // 通常の有効化
                            activate_plugin($this->plugin_basename);
                        }
                    }
                }
                
                // 次回の管理画面読み込み時に一度だけリロードさせるフラグをセット
                set_transient('news_crawler_admin_reload', 1, 5 * MINUTE_IN_SECONDS);
                
                // 更新前状態のキャッシュをクリア
                delete_site_transient('news_crawler_pre_update_state');
            }
        }
    }

    /**
     * 有効化直後に一度だけ管理画面を安全にリロード
     */
    public function maybe_reload_admin_after_activation() {
        if (!is_admin()) {
            return;
        }
        if (!current_user_can('activate_plugins')) {
            return;
        }
        $needs_reload = get_transient('news_crawler_admin_reload');
        if (!$needs_reload) {
            return;
        }
        // ループ回避のため一度だけフラグ付きで同一URLに遷移
        if (!isset($_GET['nc_reloaded'])) {
            $url = add_query_arg('nc_reloaded', '1');
            if ($url) {
                wp_safe_redirect($url);
                exit;
            }
        } else {
            delete_transient('news_crawler_admin_reload');
        }
    }
    
    /**
     * AJAX debug updates
     */
    public function ajax_debug_updates() {
        // 権限チェック
        if (!current_user_can('manage_options')) {
            wp_die('権限がありません。');
        }
        
        // デバッグ情報を取得
        $debug_info = $this->debug_update_system();
        
        // JSON形式でレスポンス
        wp_send_json_success($debug_info);
    }
    
    /**
     * Cleanup on deactivation
     */
    public static function cleanup() {
        wp_clear_scheduled_hook('news_crawler_update_check');
        delete_transient('news_crawler_latest_version');
    }
}
