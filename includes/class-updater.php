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
        add_filter('upgrader_pre_download', array($this, 'upgrader_pre_download'), 5, 3);
        add_action('upgrader_process_complete', array($this, 'handle_auto_activation'), 10, 2);
        add_action('admin_init', array($this, 'maybe_reload_admin_after_activation'));
        add_action('admin_init', array($this, 'maybe_refresh_on_admin_screens'));
        add_action('admin_notices', array($this, 'show_update_admin_notice'));

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
        if ($transient === null || $transient === false) {
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

        $force_refresh = $this->should_force_refresh()
            || current_filter() === 'pre_set_site_transient_update_plugins'
            || (defined('DOING_CRON') && DOING_CRON);
        $latest = $this->get_latest_version($force_refresh);
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
        if ($reply !== false || empty($package) || !$this->is_github_url($package)) {
            return $reply;
        }

        if (!$this->is_target_upgrader_package($package, $upgrader)) {
            return $reply;
        }

        $package = $this->normalize_package_url($package);
        if ($package === '') {
            return new WP_Error(
                'download_failed',
                'GitHub からのダウンロード URL を解決できませんでした。しばらく待ってから再度お試しください。'
            );
        }

        add_filter('http_request_args', array($this, 'github_download_args'), 10, 2);
        $temp_file = download_url($package, 300);
        remove_filter('http_request_args', array($this, 'github_download_args'), 10);

        if (is_wp_error($temp_file)) {
            error_log(
                'News Crawler Updater: GitHub download failed - '
                . $temp_file->get_error_message()
                . ' (url=' . $package . ')'
            );
        }

        return $temp_file;
    }

    public function github_download_args($args, $url) {
        if (!$this->is_github_url($url)) {
            return $args;
        }

        $args['timeout'] = 60;
        $args['headers'] = isset($args['headers']) && is_array($args['headers']) ? $args['headers'] : array();
        $args['headers']['User-Agent'] = 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url');

        // 公開アーカイブ URL には API トークンを送らない（403 / レート制限回避）
        if (strpos($url, 'api.github.com') === false) {
            unset($args['headers']['Authorization']);
            $args['headers']['Accept'] = '*/*';
            return $args;
        }

        $token = $this->get_github_token();
        if ($token) {
            $args['headers']['Authorization'] = 'Bearer ' . $token;
        }

        return $args;
    }

    public function before_update($response, $hook_extra, $result = null) {
        if (!$this->is_target_plugin_update($hook_extra)) {
            return $response;
        }

        $was_network_active = is_multisite() && is_plugin_active_for_network($this->plugin_basename);
        $was_active = is_plugin_active($this->plugin_basename) || $was_network_active;

        set_site_transient($this->key('pre_update_state'), array(
            'was_active'     => $was_active,
            'network_active' => $was_network_active,
        ), 30 * MINUTE_IN_SECONDS);

        if ($was_active) {
            deactivate_plugins($this->plugin_basename, true, $was_network_active);
        }

        return $response;
    }

    public function rename_github_source($response, $hook_extra, $result) {
        if (!$this->is_target_plugin_update($hook_extra)) {
            return $response;
        }
        if (empty($result) || is_wp_error($result)) {
            return $response;
        }

        $expected_dir = trailingslashit(WP_PLUGIN_DIR) . $this->plugin_slug . '/';
        $expected_main = $expected_dir . basename($this->plugin_file);

        if (file_exists($expected_main)) {
            return $response;
        }

        $installed_dir = $this->find_installed_plugin_directory($result);
        if (!$installed_dir || !file_exists($installed_dir . basename($this->plugin_file))) {
            error_log('News Crawler Updater: Could not locate plugin files after extract');
            return $response;
        }

        if (untrailingslashit($installed_dir) === untrailingslashit($expected_dir)) {
            return $response;
        }

        if (is_dir($expected_dir)) {
            $this->rmdir_recursive($expected_dir);
        }

        if (@rename(untrailingslashit($installed_dir), untrailingslashit($expected_dir))) {
            error_log('News Crawler Updater: Renamed ' . basename(untrailingslashit($installed_dir)) . ' to ' . $this->plugin_slug);
            $result['destination'] = $expected_dir;
            return $result;
        }

        error_log('News Crawler Updater: Failed to rename extracted folder to ' . $this->plugin_slug);
        return $response;
    }

    public function after_update($response, $hook_extra, $result) {
        if (!$this->is_target_plugin_update($hook_extra)) {
            return $response;
        }

        wp_clean_plugins_cache(true);

        $was = get_site_transient($this->key('pre_update_state'));
        if ($was && !empty($was['was_active'])) {
            $this->reactivate_plugin($was);
        }

        set_transient(
            $this->key('admin_reload'),
            admin_url('admin.php?page=news-crawler-main&nc_updated=1'),
            5 * MINUTE_IN_SECONDS
        );

        $this->clear_version_cache();
        delete_site_transient('update_plugins');
        delete_site_transient('update_plugins_checked');
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        return $response;
    }

    public function handle_auto_activation($upgrader_object, $options) {
        if ($options['action'] !== 'update' || $options['type'] !== 'plugin') {
            return;
        }
        if (!$this->is_target_plugin_in_options($options)) {
            return;
        }

        $was = get_site_transient($this->key('pre_update_state'));
        if ($was && !empty($was['was_active'])) {
            $this->reactivate_plugin($was);
        }

        set_transient(
            $this->key('admin_reload'),
            admin_url('admin.php?page=news-crawler-main&nc_updated=1'),
            5 * MINUTE_IN_SECONDS
        );
        delete_site_transient($this->key('pre_update_state'));
    }

    public function maybe_reload_admin_after_activation() {
        if (!is_admin()) {
            return;
        }
        if (!current_user_can('activate_plugins')) {
            return;
        }

        $redirect_url = get_transient($this->key('admin_reload'));
        if (!$redirect_url) {
            return;
        }

        if (!isset($_GET['nc_reloaded'])) {
            $url = add_query_arg('nc_reloaded', '1', $redirect_url);
            if ($url) {
                wp_safe_redirect($url);
                exit;
            }
        }

        delete_transient($this->key('admin_reload'));
        delete_site_transient($this->key('pre_update_state'));
    }

    public function scheduled_update_check() {
        $this->clear_version_cache();
        wp_update_plugins();
    }

    /**
     * プラグイン一覧・更新画面表示時に古いキャッシュを避ける
     */
    public function maybe_refresh_on_admin_screens() {
        if (!$this->should_force_refresh()) {
            return;
        }

        $this->clear_version_cache();
        delete_site_transient('update_plugins');
        delete_site_transient('update_plugins_checked');

        if (function_exists('wp_update_plugins')) {
            wp_update_plugins();
        }
    }

    /**
     * WordPress標準通知が出ない場合のフォールバック
     */
    public function show_update_admin_notice() {
        if (!current_user_can('update_plugins')) {
            return;
        }

        global $pagenow;
        if ($pagenow === 'update-core.php') {
            return;
        }

        $status = $this->get_update_status();
        if (empty($status['has_update'])) {
            return;
        }

        $update_url = admin_url('update-core.php');
        echo '<div class="notice notice-warning is-dismissible"><p>';
        echo '<strong>News Crawler:</strong> 新しいバージョン ';
        echo esc_html($status['latest_version']);
        echo ' が利用可能です（現在: ';
        echo esc_html($status['current_version']);
        echo '）。<a href="' . esc_url($update_url) . '">更新画面へ</a>';
        echo '</p></div>';
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
        $latest = $this->get_latest_version(true);
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

        if (is_admin() && function_exists('wp_update_plugins')) {
            wp_update_plugins();
        }
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
    private function get_latest_version($force_refresh = false) {
        if (!$force_refresh && !$this->should_force_refresh()) {
            $cached = get_transient($this->key('latest_version'));
            if ($cached !== false && is_array($cached) && !empty($cached['version'])) {
                return $cached;
            }
            $legacy = get_transient('news_crawler_latest_version');
            if ($legacy !== false && is_array($legacy) && !empty($legacy['version'])) {
                return $legacy;
            }
        }

        $data = $this->fetch_github_latest_release();
        if (!$data) {
            $data = $this->fetch_github_highest_release();
        }

        if (!$data || !isset($data['tag_name'])) {
            return $this->get_recent_backup_version();
        }

        $version_info = $this->build_version_info($data);
        $this->store_version_cache($version_info);

        return $version_info;
    }

    /**
     * 更新チェック時にキャッシュを無視する条件
     */
    private function should_force_refresh() {
        if (!is_admin()) {
            return false;
        }

        if (isset($_GET['force-check']) && (string) $_GET['force-check'] === '1') {
            return true;
        }

        global $pagenow;
        if (in_array($pagenow, array('plugins.php', 'update-core.php', 'update.php'), true)) {
            return true;
        }

        if (isset($_GET['page'])) {
            $page = sanitize_text_field(wp_unslash($_GET['page']));
            if (strpos($page, 'news-crawler') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * GitHub /releases/latest を取得
     */
    private function fetch_github_latest_release() {
        $latest_url = 'https://api.github.com/repos/' . $this->repo_owner . '/' . $this->repo_name . '/releases/latest';
        $response = $this->github_api_get($latest_url);
        if (is_wp_error($response)) {
            error_log('News Crawler Updater: GitHub latest release error - ' . $response->get_error_message());
            return null;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $this->log_github_api_failure('latest release', $status_code, $response);
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data) || empty($data['tag_name']) || !empty($data['draft']) || !empty($data['prerelease'])) {
            return null;
        }

        return $data;
    }

    /**
     * GitHub リリース一覧から semver 最大を選ぶ
     */
    private function fetch_github_highest_release() {
        $list_url = 'https://api.github.com/repos/' . $this->repo_owner . '/' . $this->repo_name . '/releases?per_page=30';
        $response = $this->github_api_get($list_url);
        if (is_wp_error($response)) {
            error_log('News Crawler Updater: GitHub releases list error - ' . $response->get_error_message());
            return null;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $this->log_github_api_failure('releases list', $status_code, $response);
            return null;
        }

        $list = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($list) || empty($list)) {
            return null;
        }

        $best = null;
        $best_version = '';

        foreach ($list as $rel) {
            if (!is_array($rel) || empty($rel['tag_name']) || !empty($rel['draft']) || !empty($rel['prerelease'])) {
                continue;
            }

            $candidate = ltrim((string) $rel['tag_name'], 'v');
            if ($best === null || version_compare($candidate, $best_version, '>')) {
                $best = $rel;
                $best_version = $candidate;
            }
        }

        return $best;
    }

    /**
     * GitHub API GET（認証トークン・User-Agent 付き）
     */
    private function github_api_get($url) {
        $headers = array(
            'User-Agent'    => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
            'Accept'        => 'application/vnd.github.v3+json',
            'Cache-Control' => 'no-cache',
        );
        $token = $this->get_github_token();
        if ($token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return wp_remote_get($url, array(
            'timeout' => 15,
            'headers' => $headers,
        ));
    }

    /**
     * GitHub release オブジェクトから内部形式へ変換
     */
    private function build_version_info(array $data) {
        $normalized_version = ltrim($data['tag_name'], 'v');
        $release_tag = isset($data['tag_name']) ? (string) $data['tag_name'] : '';

        $download_url = $this->build_public_release_download_url($release_tag);
        if (isset($data['assets']) && is_array($data['assets'])) {
            foreach ($data['assets'] as $asset) {
                if (empty($asset['browser_download_url']) || !preg_match('/\.zip$/i', $asset['browser_download_url'])) {
                    continue;
                }

                $asset_url = (string) $asset['browser_download_url'];
                if (strpos($asset_url, '/releases/download/') === false) {
                    continue;
                }

                if (!empty($asset['name']) && stripos($asset['name'], $this->plugin_slug) !== false) {
                    $download_url = $asset_url;
                    break;
                }

                $download_url = $asset_url;
            }
        }

        return array(
            'version'      => $normalized_version,
            'release_tag'  => $release_tag,
            'download_url' => $download_url,
            'published_at' => isset($data['published_at']) ? $data['published_at'] : '',
            'description'  => !empty($data['body']) ? $data['body'] : '',
            'changelog'    => $this->get_changelog_for_version($normalized_version),
            'prerelease'   => isset($data['prerelease']) ? $data['prerelease'] : false,
            'draft'        => isset($data['draft']) ? $data['draft'] : false,
            'fetched_at'   => time(),
        );
    }

    /**
     * GitHub Release の公開アーカイブ URL（API 不要）
     *
     * @param string $tag_name リリースタグ（例: v3.3.8）
     * @return string
     */
    private function build_public_release_download_url($tag_name) {
        $tag_name = trim((string) $tag_name);
        if ($tag_name === '') {
            return '';
        }

        return sprintf(
            'https://github.com/%s/%s/archive/refs/tags/%s.zip',
            $this->repo_owner,
            $this->repo_name,
            rawurlencode($tag_name)
        );
    }

    /**
     * zipball / API URL を公開ダウンロード URL に正規化
     *
     * @param string $package ダウンロード URL
     * @return string
     */
    private function normalize_package_url($package) {
        $package = trim((string) $package);
        if ($package === '') {
            return '';
        }

        if (
            strpos($package, '/archive/refs/tags/') !== false
            || strpos($package, '/releases/download/') !== false
        ) {
            return $package;
        }

        if (preg_match('#api\.github\.com/repos/[^/]+/[^/]+/zipball/([^/?#]+)#i', $package, $matches)) {
            return $this->build_public_release_download_url(rawurldecode($matches[1]));
        }

        if (preg_match('#github\.com/[^/]+/[^/]+/zipball/([^/?#]+)#i', $package, $matches)) {
            return $this->build_public_release_download_url(rawurldecode($matches[1]));
        }

        foreach ($this->get_cached_version_candidates() as $cached) {
            if (!is_array($cached)) {
                continue;
            }

            if (!empty($cached['release_tag'])) {
                $url = $this->build_public_release_download_url((string) $cached['release_tag']);
                if ($url !== '') {
                    return $url;
                }
            }

            if (!empty($cached['download_url'])) {
                $cached_url = $this->normalize_package_url((string) $cached['download_url']);
                if ($cached_url !== '' && strpos($cached_url, 'api.github.com') === false) {
                    return $cached_url;
                }
            }
        }

        return $package;
    }

    /**
     * GitHub 関連 URL かどうか
     *
     * @param string $url URL
     * @return bool
     */
    private function is_github_url($url) {
        return is_string($url) && (
            strpos($url, 'github.com') !== false
            || strpos($url, 'githubusercontent.com') !== false
            || strpos($url, 'codeload.github.com') !== false
        );
    }

    /**
     * 更新対象が自プラグインかどうか
     *
     * @param string $package  パッケージ URL
     * @param object $upgrader アップグレーダー
     * @return bool
     */
    private function is_target_upgrader_package($package, $upgrader) {
        $hook_extra = $this->get_upgrader_hook_extra($upgrader);
        if ($this->is_target_plugin_update($hook_extra) || $this->is_target_plugin_in_options($hook_extra)) {
            return true;
        }

        $repo_path = $this->repo_owner . '/' . $this->repo_name;
        return stripos($package, $repo_path) !== false;
    }

    /**
     * アップグレーダーから hook_extra を取得
     *
     * @param object $upgrader アップグレーダー
     * @return array
     */
    private function get_upgrader_hook_extra($upgrader) {
        $hook_extra = array();

        if (!is_object($upgrader) || !isset($upgrader->skin) || !is_object($upgrader->skin)) {
            return $hook_extra;
        }

        if (!empty($upgrader->skin->plugin)) {
            $hook_extra['plugin'] = (string) $upgrader->skin->plugin;
        }

        if (!empty($upgrader->skin->options['hook_extra']) && is_array($upgrader->skin->options['hook_extra'])) {
            $hook_extra = array_merge($hook_extra, $upgrader->skin->options['hook_extra']);
        }

        return $hook_extra;
    }

    /**
     * キャッシュ済みバージョン情報候補を取得
     *
     * @return array<int, array>
     */
    private function get_cached_version_candidates() {
        return array(
            get_transient($this->key('latest_version')),
            get_transient($this->key('latest_version_backup')),
            get_transient('news_crawler_latest_version'),
            get_transient('news_crawler_latest_version_backup'),
        );
    }

    /**
     * バージョン情報キャッシュを保存
     */
    private function store_version_cache(array $version_info) {
        set_transient($this->key('latest_version'), $version_info, 15 * MINUTE_IN_SECONDS);
        set_transient($this->key('latest_version_backup'), $version_info, DAY_IN_SECONDS);
        set_transient('news_crawler_latest_version', $version_info, 15 * MINUTE_IN_SECONDS);
        set_transient('news_crawler_latest_version_backup', $version_info, DAY_IN_SECONDS);
    }

    /**
     * API 失敗時のバックアップ（現在版より新しい場合のみ）
     */
    private function get_recent_backup_version() {
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_data = get_plugin_data($this->plugin_file, false, false);
        $current_version = isset($plugin_data['Version']) ? $plugin_data['Version'] : '0.0.0';

        $candidates = array(
            get_transient($this->key('latest_version_backup')),
            get_transient('news_crawler_latest_version_backup'),
        );

        foreach ($candidates as $cached) {
            if (!is_array($cached) || empty($cached['version'])) {
                continue;
            }

            $fetched_at = isset($cached['fetched_at']) ? intval($cached['fetched_at']) : 0;
            if ($fetched_at > 0 && (time() - $fetched_at) > (7 * DAY_IN_SECONDS)) {
                continue;
            }

            if (version_compare($current_version, $cached['version'], '<')) {
                return $cached;
            }
        }

        return false;
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

    private function log_github_api_failure($context, $status_code, $response) {
        $message = 'News Crawler Updater: GitHub ' . $context . ' HTTP ' . $status_code;
        if ((int) $status_code === 403) {
            $message .= ' (rate limit or token issue — define KP_GITHUB_TOKEN in wp-config.php)';
        }
        $body = wp_remote_retrieve_body($response);
        if (is_string($body) && $body !== '') {
            $decoded = json_decode($body, true);
            if (is_array($decoded) && !empty($decoded['message'])) {
                $message .= ' - ' . $decoded['message'];
            }
        }
        error_log($message);
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

    private function is_target_plugin_update($hook_extra) {
        return isset($hook_extra['plugin']) && $hook_extra['plugin'] === $this->plugin_basename;
    }

    private function is_target_plugin_in_options($options) {
        if (isset($options['plugins']) && is_array($options['plugins'])) {
            return in_array($this->plugin_basename, $options['plugins'], true);
        }
        if (isset($options['plugin']) && $options['plugin'] === $this->plugin_basename) {
            return true;
        }
        return false;
    }

    /**
     * GitHub zipball 展開後のフォルダ（例: KantanPro-news-crawler-{hash}）を検出
     */
    private function find_installed_plugin_directory($result) {
        $main_file = basename($this->plugin_file);
        $paths = array();

        if (!empty($result['source'])) {
            $paths[] = trailingslashit($result['source']);
        }
        if (!empty($result['destination'])) {
            $paths[] = trailingslashit($result['destination']);
        }

        foreach ($paths as $path) {
            if (file_exists($path . $main_file)) {
                return $path;
            }

            if (!is_dir($path)) {
                continue;
            }

            $entries = @scandir($path);
            if (!is_array($entries)) {
                continue;
            }

            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $subdir = trailingslashit($path) . $entry;
                if (is_dir($subdir) && file_exists(trailingslashit($subdir) . $main_file)) {
                    return trailingslashit($subdir);
                }
            }
        }

        return false;
    }

    private function reactivate_plugin($state) {
        if (!function_exists('activate_plugin')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        wp_clean_plugins_cache(true);

        $plugin_path = WP_PLUGIN_DIR . '/' . $this->plugin_basename;
        if (!file_exists($plugin_path)) {
            error_log('News Crawler Updater: Plugin file missing after update: ' . $plugin_path);
            return false;
        }

        if (is_plugin_active($this->plugin_basename)) {
            return true;
        }

        $network = !empty($state['network_active']);
        $activation = activate_plugin($this->plugin_basename, '', $network);
        if (is_wp_error($activation)) {
            error_log('News Crawler Updater: Reactivation failed: ' . $activation->get_error_message());
            return false;
        }

        return true;
    }
}
