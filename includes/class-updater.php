<?php
/**
 * News Crawler Updater Class
 *
 * GitHubリリース連携の標準更新通知＋自動再有効化＋安全リロード＋GitHub資産ZIP優先＋展開後リネーム
 *
 * @package NewsCrawler
 * @since 2.0.4
 */

if (!defined('ABSPATH')) {
    exit;
}

class NewsCrawlerUpdater {

    private static $instance = null;

    private $plugin_file;
    private $plugin_basename;
    private $plugin_slug;
    private $repo_owner;
    private $repo_name;
    private $github_repo_url;
    private $requires_wp;
    private $requires_php;
    private $tested_wp;

    /**
     * @param array $args 初期化引数（省略時は news-crawler 向けデフォルト）
     */
    public function __construct(array $args = array()) {
        $default_file = defined('NEWS_CRAWLER_PLUGIN_DIR')
            ? NEWS_CRAWLER_PLUGIN_DIR . 'news-crawler.php'
            : __FILE__;

        $this->plugin_file   = isset($args['plugin_file']) ? $args['plugin_file'] : $default_file;
        $this->plugin_basename = plugin_basename($this->plugin_file);
        $this->plugin_slug   = isset($args['plugin_slug']) ? $args['plugin_slug'] : 'news-crawler';
        $this->repo_owner    = isset($args['repo_owner']) ? $args['repo_owner'] : 'KantanPro';
        $this->repo_name     = isset($args['repo_name']) ? $args['repo_name'] : 'news-crawler';
        $this->github_repo_url = 'https://github.com/' . $this->repo_owner . '/' . $this->repo_name;
        $this->requires_wp   = isset($args['requires_wp']) ? $args['requires_wp'] : '5.0';
        $this->requires_php  = isset($args['requires_php']) ? $args['requires_php'] : '7.4';
        $this->tested_wp     = isset($args['tested_wp']) ? $args['tested_wp'] : '6.9.1';

        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_updates'));
        add_filter('site_transient_update_plugins', array($this, 'check_for_updates'));
        add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
        add_filter('upgrader_pre_install', array($this, 'before_update'), 10, 3);
        add_filter('upgrader_post_install', array($this, 'rename_github_source'), 9, 3);
        add_filter('upgrader_post_install', array($this, 'after_update'), 10, 3);
        add_filter('upgrader_pre_download', array($this, 'upgrader_pre_download'), 10, 3);
        add_action('upgrader_process_complete', array($this, 'handle_auto_activation'), 10, 2);
        add_action('admin_init', array($this, 'maybe_reload_admin_after_activation'));

        add_filter('plugin_row_meta', array($this, 'plugin_row_meta'), 10, 2);
        add_action('wp_ajax_news_crawler_debug_updates', array($this, 'ajax_debug_updates'));

        if (!wp_next_scheduled('news_crawler_update_check')) {
            wp_schedule_event(time(), 'twicedaily', 'news_crawler_update_check');
        }
        add_action('news_crawler_update_check', array($this, 'scheduled_update_check'));
    }

    /**
     * シングルトン取得
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * WordPress標準の更新チェック
     */
    public function check_for_updates($transient) {
        if (!is_admin() && !(defined('DOING_CRON') && DOING_CRON)) {
            return $transient;
        }
        if ($transient === null) {
            $transient = new stdClass();
        }
        if (!isset($transient->checked)) {
            $transient->checked = array();
        }

        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugin_data = get_plugin_data($this->plugin_file, false, false);
        $current_version = isset($plugin_data['Version']) ? $plugin_data['Version'] : '0.0.0';

        $transient->checked[$this->plugin_basename] = $current_version;

        $latest = $this->get_latest_version();
        if (!$latest || empty($latest['version'])) {
            return $transient;
        }

        if (version_compare($current_version, $latest['version'], '<')) {
            if (!isset($transient->response)) {
                $transient->response = array();
            }
            $transient->response[$this->plugin_basename] = (object) array(
                'id'           => $this->plugin_slug,
                'slug'         => $this->plugin_slug,
                'plugin'       => $this->plugin_basename,
                'new_version'  => $latest['version'],
                'url'          => $this->github_repo_url,
                'package'      => $latest['download_url'],
                'requires'     => $this->requires_wp,
                'requires_php' => $this->requires_php,
                'tested'       => $this->tested_wp,
                'last_updated' => $latest['published_at'],
                'sections'     => array(
                    'description' => $latest['description'],
                    'changelog'   => $latest['changelog'],
                ),
            );
            if (isset($transient->no_update[$this->plugin_basename])) {
                unset($transient->no_update[$this->plugin_basename]);
            }
        } else {
            if (!isset($transient->no_update)) {
                $transient->no_update = array();
            }
            $transient->no_update[$this->plugin_basename] = (object) array(
                'id'          => $this->plugin_slug,
                'slug'        => $this->plugin_slug,
                'plugin'      => $this->plugin_basename,
                'new_version' => $current_version,
                'url'         => $this->github_repo_url,
                'package'     => '',
            );
            if (isset($transient->response[$this->plugin_basename])) {
                unset($transient->response[$this->plugin_basename]);
            }
        }

        return $transient;
    }

    /**
     * プラグイン詳細モーダル用情報
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }
        if (!isset($args->slug) || $args->slug !== $this->plugin_slug) {
            return $result;
        }

        $latest = $this->get_latest_version();
        if (!$latest) {
            return $result;
        }

        $info = new stdClass();
        $info->name          = 'News Crawler';
        $info->slug          = $this->plugin_slug;
        $info->version       = $latest['version'];
        $info->last_updated  = $latest['published_at'];
        $info->requires      = $this->requires_wp;
        $info->requires_php  = $this->requires_php;
        $info->tested        = $this->tested_wp;
        $info->download_link = $latest['download_url'];
        $info->sections      = array(
            'description' => $latest['description'],
            'changelog'   => $latest['changelog'],
        );

        return $info;
    }

    public function upgrader_pre_download($reply, $package, $upgrader) {
        if (strpos($package, 'github.com') !== false) {
            add_filter('http_request_args', array($this, 'github_download_args'), 10, 2);
        }
        return $reply;
    }

    public function github_download_args($args, $url) {
        if (strpos($url, 'github.com') !== false) {
            $args['timeout'] = 60;
            $args['headers']['User-Agent'] = 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url');
            $token = $this->get_github_token();
            if ($token) {
                $args['headers']['Authorization'] = 'Bearer ' . $token;
            }
        }
        return $args;
    }

    public function before_update($response, $hook_extra, $result = null) {
        if (isset($hook_extra['plugin']) && $hook_extra['plugin'] === $this->plugin_basename) {
            $was_network_active = is_multisite() && is_plugin_active_for_network($this->plugin_basename);
            $was_active = is_plugin_active($this->plugin_basename) || $was_network_active;

            set_site_transient($this->key('pre_update_state'), array(
                'was_active'     => $was_active,
                'network_active' => $was_network_active,
            ), 30 * MINUTE_IN_SECONDS);

            if ($was_active) {
                deactivate_plugins($this->plugin_basename, true, $was_network_active);
            }
        }
        return $response;
    }

    public function rename_github_source($response, $hook_extra, $result) {
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_basename) {
            return $response;
        }
        if (empty($result) || empty($result['destination']) || empty($result['source'])) {
            return $response;
        }

        $destination  = trailingslashit($result['destination']);
        $source       = trailingslashit($result['source']);
        $expected_dir = trailingslashit(WP_PLUGIN_DIR) . $this->plugin_slug . '/';

        if (untrailingslashit($destination) === untrailingslashit($expected_dir)) {
            return $response;
        }
        if (strpos(basename($source), $this->plugin_slug) === 0) {
            if (is_dir($expected_dir)) {
                $this->rmdir_recursive($expected_dir);
            }
            @rename($source, $expected_dir);
            $result['destination'] = $expected_dir;
            $response = $result;
        }
        return $response;
    }

    public function after_update($response, $hook_extra, $result) {
        if (isset($hook_extra['plugin']) && $hook_extra['plugin'] === $this->plugin_basename) {
            $this->clear_version_cache();
            delete_site_transient('update_plugins');
            delete_site_transient('update_plugins_checked');
            wp_clean_plugins_cache();
            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }
        }
        return $response;
    }

    public function handle_auto_activation($upgrader_object, $options) {
        if ($options['action'] === 'update' && $options['type'] === 'plugin') {
            if (isset($options['plugins']) && in_array($this->plugin_basename, $options['plugins'])) {
                $was = get_site_transient($this->key('pre_update_state'));

                if ($was && !empty($was['was_active'])) {
                    if (!is_plugin_active($this->plugin_basename)) {
                        if (!empty($was['network_active'])) {
                            activate_plugin($this->plugin_basename, '', true);
                        } else {
                            activate_plugin($this->plugin_basename);
                        }
                    }
                }
                set_transient($this->key('admin_reload'), 1, 5 * MINUTE_IN_SECONDS);
                delete_site_transient($this->key('pre_update_state'));
            }
        }
    }

    public function maybe_reload_admin_after_activation() {
        if (!is_admin()) {
            return;
        }
        if (!current_user_can('activate_plugins')) {
            return;
        }
        $needs = get_transient($this->key('admin_reload'));
        if (!$needs) {
            return;
        }

        if (!isset($_GET['nc_reloaded'])) {
            $url = add_query_arg('nc_reloaded', '1');
            if ($url) {
                wp_safe_redirect($url);
                exit;
            }
        } else {
            delete_transient($this->key('admin_reload'));
        }
    }

    public function scheduled_update_check() {
        wp_update_plugins();
    }

    public function plugin_row_meta($links, $file) {
        if ($file === $this->plugin_basename) {
            $links[] = '<a href="' . esc_url($this->github_repo_url) . '" target="_blank">GitHub</a>';
            $links[] = '<a href="' . esc_url($this->github_repo_url . '/releases') . '" target="_blank">リリース</a>';
        }
        return $links;
    }

    /**
     * 設定画面向け更新状況
     */
    public function get_update_status() {
        $latest = $this->get_latest_version();
        if (!$latest) {
            return array(
                'status'  => 'error',
                'message' => 'GitHubからの更新情報の取得に失敗しました',
            );
        }

        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugin_data = get_plugin_data($this->plugin_file, false, false);
        $current_version = isset($plugin_data['Version']) ? $plugin_data['Version'] : '0.0.0';

        return array(
            'status'          => 'success',
            'current_version' => $current_version,
            'latest_version'  => $latest['version'],
            'has_update'      => version_compare($current_version, $latest['version'], '<'),
            'download_url'    => $latest['download_url'],
            'description'     => $latest['description'],
            'published_at'    => $latest['published_at'],
        );
    }

    public function debug_update_system() {
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_data = get_plugin_data($this->plugin_file, false, false);
        $transient = get_site_transient('update_plugins');

        $headers = array(
            'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
            'Accept'     => 'application/vnd.github.v3+json',
        );
        $token = $this->get_github_token();
        if ($token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $test_url = 'https://api.github.com/repos/' . $this->repo_owner . '/' . $this->repo_name . '/releases/latest';
        $response = wp_remote_get($test_url, array(
            'timeout' => 10,
            'headers' => $headers,
        ));

        $debug_info = array(
            'current_version'       => isset($plugin_data['Version']) ? $plugin_data['Version'] : '',
            'plugin_basename'       => $this->plugin_basename,
            'cached_version'        => get_transient($this->key('latest_version')),
            'backup_cached_version' => get_transient($this->key('latest_version_backup')),
            'wp_transient_exists'   => !empty($transient),
            'wp_transient_response' => isset($transient->response[$this->plugin_basename]) ? $transient->response[$this->plugin_basename] : null,
            'scheduled_check'       => wp_next_scheduled('news_crawler_update_check'),
        );

        if (is_wp_error($response)) {
            $debug_info['github_api_error'] = $response->get_error_message();
        } else {
            $debug_info['github_api_status'] = wp_remote_retrieve_response_code($response);
            $debug_info['github_api_response'] = wp_remote_retrieve_body($response);
        }

        return $debug_info;
    }

    public function ajax_debug_updates() {
        if (!current_user_can('manage_options')) {
            wp_die('権限がありません。');
        }
        wp_send_json_success($this->debug_update_system());
    }

    /**
     * バージョン情報キャッシュをクリア（設定画面・手動更新用）
     */
    public function clear_version_caches() {
        $this->clear_version_cache();
        delete_site_transient('update_plugins');
        delete_site_transient('update_plugins_checked');
        wp_clean_plugins_cache();
    }

    public static function cleanup() {
        wp_clear_scheduled_hook('news_crawler_update_check');
        if (self::$instance instanceof self) {
            self::$instance->clear_version_cache();
        }
        delete_transient('news_crawler_latest_version');
        delete_transient('news_crawler_latest_version_backup');
    }

    /**
     * GitHub Releases API から最新版情報を取得
     */
    private function get_latest_version() {
        $force_refresh = (is_admin() && isset($_GET['force-check']) && $_GET['force-check'] == '1');

        if (!$force_refresh) {
            $cached = get_transient($this->key('latest_version'));
            if ($cached !== false) {
                return $cached;
            }
            // 旧キャッシュキー互換
            $legacy = get_transient('news_crawler_latest_version');
            if ($legacy !== false) {
                return $legacy;
            }
        }

        $headers = array(
            'User-Agent'    => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
            'Accept'        => 'application/vnd.github.v3+json',
            'Cache-Control' => 'no-cache',
        );
        $token = $this->get_github_token();
        if ($token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $latest_url = 'https://api.github.com/repos/' . $this->repo_owner . '/' . $this->repo_name . '/releases/latest';
        $response = wp_remote_get($latest_url, array('timeout' => 15, 'headers' => $headers));

        $data = null;
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
        }

        if (!$data || !isset($data['tag_name']) || !empty($data['draft']) || !empty($data['prerelease'])) {
            $list_url = 'https://api.github.com/repos/' . $this->repo_owner . '/' . $this->repo_name . '/releases';
            $resp2 = wp_remote_get($list_url, array('timeout' => 15, 'headers' => $headers));
            if (!is_wp_error($resp2) && wp_remote_retrieve_response_code($resp2) === 200) {
                $list = json_decode(wp_remote_retrieve_body($resp2), true);
                if (is_array($list)) {
                    foreach ($list as $rel) {
                        if (!empty($rel['draft']) || !empty($rel['prerelease'])) {
                            continue;
                        }
                        if (isset($rel['tag_name'])) {
                            $data = $rel;
                            break;
                        }
                    }
                }
            }
        }

        if (!$data || !isset($data['tag_name'])) {
            $old_cached = get_transient($this->key('latest_version_backup'));
            if ($old_cached !== false) {
                return $old_cached;
            }
            $legacy_backup = get_transient('news_crawler_latest_version_backup');
            if ($legacy_backup !== false) {
                return $legacy_backup;
            }
            return false;
        }

        $normalized_version = ltrim($data['tag_name'], 'v');

        // zipball をデフォルト（asset なしリリース対応）。ZIP asset があれば優先
        $download_url = isset($data['zipball_url']) ? $data['zipball_url'] : '';
        if (isset($data['assets']) && is_array($data['assets'])) {
            foreach ($data['assets'] as $asset) {
                if (!empty($asset['browser_download_url']) && preg_match('/\.zip$/i', $asset['browser_download_url'])) {
                    if (!empty($asset['name']) && stripos($asset['name'], $this->plugin_slug) !== false) {
                        $download_url = $asset['browser_download_url'];
                        break;
                    }
                    $download_url = $asset['browser_download_url'];
                }
            }
        }

        $version_info = array(
            'version'      => $normalized_version,
            'download_url' => $download_url,
            'published_at' => isset($data['published_at']) ? $data['published_at'] : '',
            'description'  => !empty($data['body']) ? $data['body'] : '',
            'changelog'    => $this->get_changelog_for_version($normalized_version),
            'prerelease'   => isset($data['prerelease']) ? $data['prerelease'] : false,
            'draft'        => isset($data['draft']) ? $data['draft'] : false,
        );

        set_transient($this->key('latest_version'), $version_info, 15 * MINUTE_IN_SECONDS);
        set_transient($this->key('latest_version_backup'), $version_info, DAY_IN_SECONDS);
        // 旧キーにも書き込み（設定画面フォールバック互換）
        set_transient('news_crawler_latest_version', $version_info, 15 * MINUTE_IN_SECONDS);
        set_transient('news_crawler_latest_version_backup', $version_info, DAY_IN_SECONDS);

        return $version_info;
    }

    private function get_changelog_for_version($version) {
        $changelog_file = dirname($this->plugin_file) . '/CHANGELOG.md';
        if (!file_exists($changelog_file)) {
            return '';
        }
        $content = file_get_contents($changelog_file);
        if (!$content) {
            return '';
        }
        $pattern = '/## \[' . preg_quote($version, '/') . '\](.*?)(?=## \[|$)/s';
        if (preg_match($pattern, $content, $matches)) {
            return trim($matches[1]);
        }
        return '';
    }

    private function get_github_token() {
        if (defined('NEWS_CRAWLER_GITHUB_TOKEN') && NEWS_CRAWLER_GITHUB_TOKEN) {
            return NEWS_CRAWLER_GITHUB_TOKEN;
        }
        if (defined('KP_GITHUB_TOKEN') && KP_GITHUB_TOKEN) {
            return KP_GITHUB_TOKEN;
        }
        return '';
    }

    private function clear_version_cache() {
        delete_transient($this->key('latest_version'));
        delete_transient($this->key('latest_version_backup'));
        delete_transient('news_crawler_latest_version');
        delete_transient('news_crawler_latest_version_backup');
    }

    private function rmdir_recursive($dir) {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->rmdir_recursive($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    private function key($suffix) {
        return 'nc_upd_' . md5($this->plugin_basename) . '_' . $suffix;
    }
}
