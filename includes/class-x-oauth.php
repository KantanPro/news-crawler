<?php
/**
 * X OAuth 2.0 (PKCE) 認証
 *
 * @package News_Crawler
 */

if (!defined('ABSPATH')) {
    exit;
}

class News_Crawler_X_OAuth {

    const OAUTH_OPTION_KEY = 'news_crawler_x_oauth';

    /**
     * @var self|null
     */
    private static $instance = null;

    /**
     * @return self
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_init', array($this, 'handle_oauth_callback'));
        add_action('admin_post_nc_x_connect', array($this, 'handle_connect'));
        add_action('admin_post_nc_x_disconnect', array($this, 'handle_disconnect'));
        add_action('admin_post_nc_x_test_tweet', array($this, 'handle_test_tweet'));
        add_action('admin_post_nc_x_clear_share_log', array($this, 'handle_clear_share_log'));
        add_action('admin_post_nc_x_refresh_profile', array($this, 'handle_refresh_profile'));
        add_action('admin_post_nc_x_save_manual_username', array($this, 'handle_save_manual_username'));
        add_action('admin_post_nc_x_retry_pending_shares', array($this, 'handle_retry_pending_shares'));
        add_action('admin_notices', array($this, 'render_admin_notices'));
    }

    /**
     * OAuth コールバック URL
     *
     * @return string
     */
    public function get_redirect_uri() {
        return admin_url('admin.php?page=news-crawler-cron-settings&nc_x_oauth=callback');
    }

    /**
     * OAuth トークン等を専用オプションに保存するキー
     *
     * @return array<int, string>
     */
    private function get_oauth_option_keys() {
        return array(
            'twitter_oauth2_access_token',
            'twitter_oauth2_refresh_token',
            'twitter_oauth2_token_expires',
            'twitter_connected_username',
            'twitter_connected_name',
        );
    }

    /**
     * OAuth 専用オプションを取得（旧 basic_settings からの移行を含む）
     *
     * @return array
     */
    private function get_oauth_option_settings() {
        $oauth = get_option(self::OAUTH_OPTION_KEY, array());
        if (!is_array($oauth)) {
            $oauth = array();
        }

        if (!empty($oauth['twitter_oauth2_access_token'])) {
            return $oauth;
        }

        $basic = get_option('news_crawler_basic_settings', array());
        if (!is_array($basic)) {
            return $oauth;
        }

        $migrated = false;
        foreach ($this->get_oauth_option_keys() as $key) {
            if (!empty($basic[$key])) {
                $oauth[$key] = $basic[$key];
                $migrated = true;
            }
        }

        if ($migrated) {
            $this->write_oauth_option($oauth);
            $this->strip_oauth_keys_from_basic_settings();
        }

        return $oauth;
    }

    /**
     * OAuth 専用オプションを保存
     *
     * @param array $oauth OAuth 設定
     * @return bool
     */
    private function write_oauth_option(array $oauth) {
        update_option(self::OAUTH_OPTION_KEY, $oauth, false);
        wp_cache_delete(self::OAUTH_OPTION_KEY, 'options');
    }

    /**
     * OAuth Access Token が DB に保存されているか
     *
     * @return bool
     */
    private function verify_oauth_access_token_persisted() {
        wp_cache_delete(self::OAUTH_OPTION_KEY, 'options');
        wp_cache_delete('news_crawler_basic_settings', 'options');

        $oauth = get_option(self::OAUTH_OPTION_KEY, array());
        if (is_array($oauth) && !empty($oauth['twitter_oauth2_access_token'])) {
            return true;
        }

        $basic = get_option('news_crawler_basic_settings', array());
        return is_array($basic) && !empty($basic['twitter_oauth2_access_token']);
    }

    /**
     * basic_settings から OAuth キーを削除（移行後の重複防止）
     */
    private function strip_oauth_keys_from_basic_settings() {
        $basic = get_option('news_crawler_basic_settings', array());
        if (!is_array($basic)) {
            return;
        }

        $changed = false;
        foreach ($this->get_oauth_option_keys() as $key) {
            if (array_key_exists($key, $basic)) {
                unset($basic[$key]);
                $changed = true;
            }
        }

        if ($changed) {
            update_option('news_crawler_basic_settings', $basic);
            wp_cache_delete('news_crawler_basic_settings', 'options');
        }
    }

    /**
     * 設定を取得
     *
     * @return array
     */
    public function get_settings() {
        $settings = get_option('news_crawler_basic_settings', array());
        if (!is_array($settings)) {
            $settings = array();
        }

        return array_merge($settings, $this->get_oauth_option_settings());
    }

    /**
     * 基本設定に OAuth 専用オプションをマージして返す
     *
     * @param array|null $settings news_crawler_basic_settings のみの配列、または null
     * @return array
     */
    private function resolve_settings($settings = null) {
        if ($settings === null) {
            return $this->get_settings();
        }

        if (!is_array($settings)) {
            $settings = array();
        }

        return array_merge($settings, $this->get_oauth_option_settings());
    }

    /**
     * 設定を保存
     *
     * @param array $settings 設定
     */
    public function update_settings(array $settings) {
        $oauth_patch = array();
        foreach ($this->get_oauth_option_keys() as $key) {
            if (array_key_exists($key, $settings)) {
                $oauth_patch[$key] = $settings[$key];
            }
        }

        if (!empty($oauth_patch)) {
            $current_oauth = $this->get_oauth_option_settings();
            $this->write_oauth_option(array_merge($current_oauth, $oauth_patch));
        }

        $basic_settings = $settings;
        foreach ($this->get_oauth_option_keys() as $key) {
            unset($basic_settings[$key]);
        }

        $existing = get_option('news_crawler_basic_settings', array());
        if (!is_array($existing)) {
            $existing = array();
        }

        $merged = array_merge($existing, $basic_settings);
        update_option('news_crawler_basic_settings', $merged);
        wp_cache_delete('news_crawler_basic_settings', 'options');
    }

    /**
     * 接続済みか
     *
     * @param array|null $settings 設定
     * @return bool
     */
    public function is_connected($settings = null) {
        $settings = $this->resolve_settings($settings);
        $method = $this->get_auth_method($settings);

        if ($method === 'oauth1') {
            return $this->has_usable_oauth1_credentials($settings);
        }

        return !empty($settings['twitter_client_id'])
            && !empty($settings['twitter_oauth2_access_token']);
    }

    /**
     * OAuth 1.0a 認証情報を復号して取得
     *
     * @param array|null $settings 設定
     * @return array{api_key:string,api_secret:string,access_token:string,access_token_secret:string}
     */
    public function get_oauth1_credentials($settings = null) {
        $settings = $this->resolve_settings($settings);

        return array(
            'api_key' => trim((string) ($settings['twitter_api_key'] ?? '')),
            'api_secret' => $this->get_decrypted_secret((string) ($settings['twitter_api_secret'] ?? '')),
            'access_token' => trim((string) ($settings['twitter_access_token'] ?? '')),
            'access_token_secret' => $this->get_decrypted_secret((string) ($settings['twitter_access_token_secret'] ?? '')),
        );
    }

    /**
     * OAuth 1.0a 認証情報が利用可能か
     *
     * @param array|null $settings 設定
     * @return bool
     */
    public function has_usable_oauth1_credentials($settings = null) {
        $settings = $this->resolve_settings($settings);
        $credentials = $this->get_oauth1_credentials($settings);

        if ($credentials['api_key'] === ''
            || $credentials['access_token'] === ''
            || $credentials['api_secret'] === ''
            || $credentials['access_token_secret'] === '') {
            return false;
        }

        return $this->has_usable_stored_secret((string) ($settings['twitter_api_secret'] ?? ''))
            && $this->has_usable_stored_secret((string) ($settings['twitter_access_token_secret'] ?? ''));
    }

    /**
     * 保存済み Secret が復号可能か
     *
     * @param string $stored 保存値
     * @return bool
     */
    public function has_usable_stored_secret($stored) {
        $stored = trim((string) $stored);
        if ($stored === '') {
            return false;
        }

        $decrypted = $this->get_decrypted_secret($stored);
        if ($decrypted === '') {
            return false;
        }

        if ($decrypted === $stored && strlen($stored) > 24) {
            return false;
        }

        return true;
    }

    /**
     * 暗号化 Secret を復号
     *
     * @param string $stored 保存値
     * @return string
     */
    public function get_decrypted_secret($stored) {
        return trim(News_Crawler_X_Crypto::decrypt((string) $stored));
    }

    /**
     * OAuth 1.0a Authorization ヘッダー
     *
     * @param string $method   HTTP メソッド
     * @param string $url      URL
     * @param array|null $settings 設定
     * @return string
     */
    public function build_oauth1_authorization_header($method, $url, $settings = null) {
        $settings = $this->resolve_settings($settings);
        $credentials = $this->get_oauth1_credentials($settings);

        return $this->build_oauth1_authorization_header_from_credentials(
            $method,
            $url,
            array(),
            $credentials['api_key'],
            $credentials['api_secret'],
            $credentials['access_token'],
            $credentials['access_token_secret']
        );
    }

    /**
     * Client Secret を復号して取得
     *
     * @param array|null $settings 設定
     * @return string
     */
    public function get_client_secret($settings = null) {
        $settings = $this->resolve_settings($settings);
        return trim(News_Crawler_X_Crypto::decrypt((string) ($settings['twitter_client_secret'] ?? '')));
    }

    /**
     * Client Secret が利用可能か
     *
     * @param array|null $settings 設定
     * @return bool
     */
    public function has_usable_client_secret($settings = null) {
        $settings = $this->resolve_settings($settings);
        if (empty($settings['twitter_client_secret'])) {
            return false;
        }

        return trim($this->get_client_secret($settings)) !== '';
    }

    /**
     * 接続状態の診断情報を取得
     *
     * @param array|null $settings 設定
     * @return array
     */
    public function get_connection_diagnostics($settings = null) {
        $settings = $this->resolve_settings($settings);
        $method = $this->get_auth_method($settings);

        $diagnostics = array(
            'method' => $method,
            'connected' => $this->is_connected($settings),
            'client_id_saved' => !empty($settings['twitter_client_id']),
            'client_secret_saved' => !empty($settings['twitter_client_secret']),
            'client_secret_usable' => $this->has_usable_client_secret($settings),
            'oauth2_access_token_saved' => !empty($settings['twitter_oauth2_access_token']),
            'oauth2_refresh_token_saved' => !empty($settings['twitter_oauth2_refresh_token']),
            'oauth2_storage_option' => self::OAUTH_OPTION_KEY,
            'oauth2_token_expires' => isset($settings['twitter_oauth2_token_expires']) ? (int) $settings['twitter_oauth2_token_expires'] : 0,
            'oauth1_api_key_saved' => !empty($settings['twitter_api_key']),
            'oauth1_api_secret_saved' => !empty($settings['twitter_api_secret']),
            'oauth1_api_secret_usable' => $this->has_usable_stored_secret((string) ($settings['twitter_api_secret'] ?? '')),
            'oauth1_access_token_saved' => !empty($settings['twitter_access_token']),
            'oauth1_access_token_secret_saved' => !empty($settings['twitter_access_token_secret']),
            'oauth1_access_token_secret_usable' => $this->has_usable_stored_secret((string) ($settings['twitter_access_token_secret'] ?? '')),
            'oauth1_credentials_usable' => $this->has_usable_oauth1_credentials($settings),
            'username_saved' => !empty($settings['twitter_connected_username']),
        );

        if ($diagnostics['oauth2_token_expires'] > 0) {
            $diagnostics['oauth2_token_expired'] = time() >= $diagnostics['oauth2_token_expires'];
        } else {
            $diagnostics['oauth2_token_expired'] = null;
        }

        return $diagnostics;
    }

    /**
     * 認証方式を取得
     *
     * @param array|null $settings 設定
     * @return string
     */
    public function get_auth_method($settings = null) {
        $settings = $this->resolve_settings($settings);
        $method = isset($settings['twitter_auth_method']) ? $settings['twitter_auth_method'] : '';

        if (in_array($method, array('oauth1', 'oauth2'), true)) {
            return $method;
        }

        if (!empty($settings['twitter_api_key']) && !empty($settings['twitter_access_token'])) {
            return 'oauth1';
        }

        return 'oauth2';
    }

    /**
     * OAuth 接続を開始できるか
     *
     * @param array|null $settings 設定
     * @return bool
     */
    public function can_start_connect($settings = null) {
        $settings = $this->resolve_settings($settings);
        if ($this->get_auth_method($settings) !== 'oauth2') {
            return false;
        }

        return trim((string) ($settings['twitter_client_id'] ?? '')) !== ''
            && $this->has_usable_client_secret($settings);
    }

    /**
     * 認可 URL を生成（接続ボタン押下時のみ呼ぶ）
     *
     * @return string
     */
    private function build_authorization_url() {
        $settings = $this->get_settings();
        $client_id = trim((string) ($settings['twitter_client_id'] ?? ''));

        if ($client_id === '') {
            return '';
        }

        $code_verifier = $this->generate_code_verifier();
        $code_challenge = $this->generate_code_challenge($code_verifier);
        $state = wp_generate_password(32, false, false);

        set_transient($this->get_oauth_transient_key('code_verifier'), $code_verifier, 15 * MINUTE_IN_SECONDS);
        set_transient($this->get_oauth_transient_key('state'), $state, 15 * MINUTE_IN_SECONDS);

        $params = array(
            'response_type' => 'code',
            'client_id' => $client_id,
            'redirect_uri' => $this->get_redirect_uri(),
            'scope' => 'tweet.read tweet.write users.read offline.access',
            'state' => $state,
            'code_challenge' => $code_challenge,
            'code_challenge_method' => 'S256',
        );

        return 'https://x.com/i/oauth2/authorize?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * X 接続開始ハンドラ
     */
    public function handle_connect() {
        if (!current_user_can('manage_options')) {
            wp_die('権限がありません');
        }
        check_admin_referer('nc_x_connect');

        if (!$this->can_start_connect()) {
            $this->redirect_with_notice(
                'error',
                'Client ID / Client Secret を入力して「設定を保存」してから、もう一度「X アカウントを接続」をクリックしてください。'
            );
        }

        $auth_url = $this->build_authorization_url();
        if ($auth_url === '') {
            $this->redirect_with_notice('error', '認可 URL を生成できませんでした。Client ID を確認してください。');
        }

        // X 認可画面は外部 URL のため wp_redirect を使用（wp_safe_redirect は同一サイトのみ）
        wp_redirect($auth_url);
        exit;
    }

    /**
     * OAuth コールバック処理
     */
    public function handle_oauth_callback() {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (empty($_GET['page']) || $_GET['page'] !== 'news-crawler-cron-settings') {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (empty($_GET['nc_x_oauth']) || $_GET['nc_x_oauth'] !== 'callback') {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $error = isset($_GET['error']) ? sanitize_text_field(wp_unslash($_GET['error'])) : '';
        if ($error !== '') {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $description = isset($_GET['error_description']) ? sanitize_text_field(wp_unslash($_GET['error_description'])) : $error;
            $this->redirect_with_notice('error', $description);
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';
        $saved = get_transient($this->get_oauth_transient_key('state'));
        if ($state === '' || $saved === false || !hash_equals((string) $saved, $state)) {
            $this->redirect_with_notice('error', 'OAuth state が一致しません。もう一度「X アカウントを接続」からやり直してください。');
        }
        delete_transient($this->get_oauth_transient_key('state'));

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
        if ($code === '') {
            $this->redirect_with_notice('error', '認可コードが取得できませんでした。');
        }

        $code_verifier = get_transient($this->get_oauth_transient_key('code_verifier'));
        if ($code_verifier === false || $code_verifier === '') {
            $this->redirect_with_notice('error', 'code_verifier の有効期限が切れました。「X アカウントを接続」からやり直してください。');
        }
        delete_transient($this->get_oauth_transient_key('code_verifier'));

        $result = $this->exchange_code_for_tokens($code, (string) $code_verifier);
        if (!$result['success']) {
            $this->redirect_with_notice('error', $result['error'] ?? 'トークン取得に失敗しました。');
        }

        if (!$this->is_connected()) {
            $this->redirect_with_notice('error', 'アクセストークンの保存に失敗しました。もう一度「X アカウントを接続」を試してください。');
        }

        $display_label = $this->get_connected_display_label();
        if ($display_label !== '') {
            $message = sprintf('X アカウント %s に接続しました。', $display_label);
            $this->redirect_with_notice('success', $message);
        } else {
            $verify_error = isset($result['verify_error']) ? (string) $result['verify_error'] : '';
            if ($verify_error !== '') {
                error_log('News Crawler X OAuth: connected but username lookup failed - ' . $verify_error);
            }
            $this->redirect_with_notice('success', 'X アカウントに接続しました。');
        }
    }

    /**
     * 認可コードをトークンに交換
     *
     * @param string $code          認可コード
     * @param string $code_verifier PKCE verifier
     * @return array{success:bool,error?:string}
     */
    public function exchange_code_for_tokens($code, $code_verifier) {
        $settings = $this->get_settings();
        $client_id = trim((string) ($settings['twitter_client_id'] ?? ''));
        $client_secret = $this->get_client_secret($settings);

        if ($client_id === '') {
            return array('success' => false, 'error' => 'Client ID が設定されていません。');
        }

        if ($client_secret === '') {
            return array(
                'success' => false,
                'error' => 'Client Secret が未設定、または復号できません。X Developer Portal の OAuth 2.0 Client Secret を再入力して「設定を保存」してください。',
            );
        }

        $body = array(
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->get_redirect_uri(),
            'code_verifier' => $code_verifier,
            'client_id' => $client_id,
        );

        $headers = array(
            'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
            'Authorization' => $this->build_oauth2_basic_auth_header($client_id, $client_secret),
        );

        $response = wp_remote_post(
            'https://api.x.com/2/oauth2/token',
            array(
                'timeout' => 30,
                'headers' => $headers,
                'body' => $body,
            )
        );

        if (is_wp_error($response)) {
            return array('success' => false, 'error' => $response->get_error_message());
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($data) || empty($data['access_token'])) {
            error_log('News Crawler X OAuth: token exchange failed HTTP ' . $status_code . ' - ' . wp_json_encode($data));
            return array(
                'success' => false,
                'error' => $this->format_token_exchange_error($data, $status_code),
            );
        }

        $this->store_tokens($data);

        wp_cache_delete(self::OAUTH_OPTION_KEY, 'options');
        wp_cache_delete('news_crawler_basic_settings', 'options');
        if (!$this->verify_oauth_access_token_persisted()) {
            global $wpdb;
            error_log(
                'News Crawler X OAuth: store_tokens completed but access token not persisted - '
                . ($wpdb->last_error ?: 'no db error')
            );
            return array(
                'success' => false,
                'error' => 'アクセストークンの保存に失敗しました。サーバーのオプション保存制限の可能性があります。もう一度「X アカウントを接続」を試してください。',
            );
        }

        $verify = $this->verify_credentials(null, (string) $data['access_token']);
        if ($verify['success']) {
            $this->save_connected_profile($verify);
            return array('success' => true);
        }

        $error_message = isset($verify['error']) ? (string) $verify['error'] : '';
        if ($error_message !== '') {
            error_log('News Crawler X OAuth: verify_credentials failed after token exchange - ' . $error_message);
        }

        return array(
            'success' => true,
            'verify_error' => $error_message,
        );
    }

    /**
     * リフレッシュトークンで更新
     *
     * @return bool
     */
    public function refresh_access_token() {
        $settings = $this->get_settings();
        $client_id = trim((string) ($settings['twitter_client_id'] ?? ''));
        $client_secret = $this->get_client_secret($settings);
        $refresh_token = News_Crawler_X_Crypto::decrypt((string) ($settings['twitter_oauth2_refresh_token'] ?? ''));

        if ($client_id === '' || $client_secret === '' || $refresh_token === '') {
            return false;
        }

        $body = array(
            'grant_type' => 'refresh_token',
            'refresh_token' => $refresh_token,
            'client_id' => $client_id,
        );

        $headers = array(
            'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
            'Authorization' => $this->build_oauth2_basic_auth_header($client_id, $client_secret),
        );

        $response = wp_remote_post(
            'https://api.x.com/2/oauth2/token',
            array(
                'timeout' => 30,
                'headers' => $headers,
                'body' => $body,
            )
        );

        if (is_wp_error($response)) {
            return false;
        }

        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($data) || empty($data['access_token'])) {
            return false;
        }

        $this->store_tokens($data);
        return true;
    }

    /**
     * 期限切れ前にトークン更新
     */
    public function maybe_refresh_token() {
        if ($this->get_auth_method() !== 'oauth2') {
            return;
        }

        $settings = $this->get_settings();
        $expires = (int) ($settings['twitter_oauth2_token_expires'] ?? 0);
        // 期限切れ 5 分前から更新（cron 長時間実行時の 403 回避）
        if ($expires > 0 && time() >= ($expires - 300)) {
            $this->refresh_access_token();
        }
    }

    /**
     * OAuth2 アクセストークンを取得
     *
     * @return string
     */
    public function get_access_token() {
        $settings = $this->get_settings();
        return News_Crawler_X_Crypto::decrypt((string) ($settings['twitter_oauth2_access_token'] ?? ''));
    }

    /**
     * 接続中アカウントの表示名（@username または表示名）
     *
     * @param array|null $settings 設定
     * @return string
     */
    public function get_connected_display_label($settings = null) {
        $username = $this->get_connected_username($settings);
        if ($username !== '') {
            return '@' . $username;
        }

        $settings = $this->resolve_settings($settings);
        $name = trim((string) ($settings['twitter_connected_name'] ?? ''));
        return $name;
    }

    /**
     * 接続中アカウント名（@username）を取得
     *
     * @param array|null $settings 設定
     * @return string
     */
    public function get_connected_username($settings = null) {
        $settings = $this->resolve_settings($settings);
        $username = trim((string) ($settings['twitter_connected_username'] ?? ''));
        if ($username !== '') {
            return $username;
        }

        if (!$this->is_connected($settings)) {
            return '';
        }

        $verify = $this->verify_credentials($settings);
        if ($verify['success']) {
            $this->save_connected_profile($verify);
            return trim((string) ($verify['username'] ?? ''));
        }

        return '';
    }

    /**
     * 接続プロフィールを保存
     *
     * @param array $profile username/name を含む配列
     */
    private function save_connected_profile(array $profile) {
        $settings = $this->get_settings();

        if (!empty($profile['username'])) {
            $settings['twitter_connected_username'] = (string) $profile['username'];
        }
        if (!empty($profile['name'])) {
            $settings['twitter_connected_name'] = (string) $profile['name'];
        }

        $this->update_settings($settings);
    }

    /**
     * 接続情報を確認
     *
     * @param array|null $settings             設定
     * @param string     $access_token_override 平文アクセストークン（接続直後用）
     * @return array{success:bool,username?:string,name?:string,error?:string}
     */
    public function verify_credentials($settings = null, $access_token_override = '') {
        $settings = $this->resolve_settings($settings);
        $method = $this->get_auth_method($settings);

        if ($method === 'oauth1') {
            return $this->verify_credentials_oauth1($settings);
        }

        $access_token = trim((string) $access_token_override);
        if ($access_token === '') {
            $access_token = $this->get_access_token();
        }
        if ($access_token === '') {
            return array('success' => false, 'error' => 'X アカウントが接続されていません。');
        }

        $profile = $this->fetch_oauth2_user_profile($access_token);
        if ($profile['success']) {
            return $profile;
        }

        if ($access_token_override === '' && $this->refresh_access_token()) {
            return $this->verify_credentials($settings);
        }

        return $profile;
    }

    /**
     * OAuth 2.0 でユーザー情報を取得
     *
     * @param string $access_token アクセストークン
     * @return array{success:bool,username?:string,name?:string,error?:string}
     */
    private function fetch_oauth2_user_profile($access_token) {
        $endpoints = array(
            'https://api.x.com/2/users/me?user.fields=username,name',
            'https://api.twitter.com/2/users/me?user.fields=username,name',
        );

        $last_error = 'アカウント情報の取得に失敗しました。';

        foreach ($endpoints as $url) {
            $response = wp_remote_get(
                $url,
                array(
                    'timeout' => 20,
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $access_token,
                        'Accept' => 'application/json',
                        'User-Agent' => 'NewsCrawler/' . news_crawler_get_version() . '; ' . home_url(),
                    ),
                )
            );

            if (is_wp_error($response)) {
                $last_error = $response->get_error_message();
                error_log('News Crawler X OAuth: users/me request error (' . $url . ') - ' . $last_error);
                continue;
            }

            $code = (int) wp_remote_retrieve_response_code($response);
            $body = (string) wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if ($code >= 200 && $code < 300 && is_array($data) && !empty($data['data']['username'])) {
                return array(
                    'success' => true,
                    'username' => (string) $data['data']['username'],
                    'name' => !empty($data['data']['name']) ? (string) $data['data']['name'] : '',
                );
            }

            $last_error = $this->extract_api_error_message($data, $code);
            error_log(sprintf(
                'News Crawler X OAuth: users/me failed (%s) HTTP %d - %s | body: %s',
                $url,
                $code,
                $last_error,
                substr($body, 0, 500)
            ));
        }

        return array('success' => false, 'error' => $last_error);
    }

    /**
     * OAuth 1.0a で接続情報を確認
     *
     * @param array $settings 設定
     * @return array{success:bool,username?:string,error?:string}
     */
    private function verify_credentials_oauth1(array $settings) {
        if (!$this->has_usable_oauth1_credentials($settings)) {
            return array(
                'success' => false,
                'error' => 'OAuth 1.0a の API Key / Secret / Access Token / Access Token Secret が未設定、または復号できません。',
            );
        }

        $endpoints = array(
            'https://api.x.com/2/users/me?user.fields=username,name',
            'https://api.twitter.com/2/users/me?user.fields=username,name',
        );

        $last_error = 'アカウント情報の取得に失敗しました。';

        foreach ($endpoints as $endpoint) {
            $auth_header = $this->build_oauth1_authorization_header('GET', $endpoint, $settings);

            $response = wp_remote_get(
                $endpoint,
                array(
                    'timeout' => 20,
                    'headers' => array(
                        'Authorization' => $auth_header,
                    ),
                )
            );

            if (is_wp_error($response)) {
                $last_error = $response->get_error_message();
                continue;
            }

            $code = (int) wp_remote_retrieve_response_code($response);
            $data = json_decode((string) wp_remote_retrieve_body($response), true);

            if ($code >= 200 && $code < 300 && !empty($data['data']['username'])) {
                return array(
                    'success' => true,
                    'username' => (string) $data['data']['username'],
                    'name' => !empty($data['data']['name']) ? (string) $data['data']['name'] : '',
                );
            }

            $last_error = $this->extract_api_error_message($data, $code);
        }

        return array('success' => false, 'error' => $last_error);
    }

    /**
     * 接続解除
     */
    public function disconnect() {
        $this->write_oauth_option(array(
            'twitter_oauth2_access_token' => '',
            'twitter_oauth2_refresh_token' => '',
            'twitter_oauth2_token_expires' => 0,
            'twitter_connected_username' => '',
            'twitter_connected_name' => '',
        ));
        $this->strip_oauth_keys_from_basic_settings();
    }

    /**
     * 接続解除ハンドラ
     */
    public function handle_disconnect() {
        if (!current_user_can('manage_options')) {
            wp_die('権限がありません');
        }
        check_admin_referer('nc_x_disconnect');
        $this->disconnect();
        $this->redirect_with_notice('success', 'X 接続を解除しました。');
    }

    /**
     * テスト投稿ハンドラ
     */
    public function handle_test_tweet() {
        if (!current_user_can('manage_options')) {
            wp_die('権限がありません');
        }
        check_admin_referer('nc_x_test_tweet');

        $poster = new News_Crawler_X_Poster();
        $result = $poster->post_test_message(
            sprintf('News Crawler 接続テスト from %s', get_bloginfo('name'))
        );

        if ($result['success']) {
            News_Crawler_X_Share_Log::add(
                '接続テスト投稿に成功しました。',
                'success',
                array(
                    'tweet_id' => $result['tweet_id'] ?? '',
                )
            );
            $this->redirect_with_notice('success', 'テスト投稿に成功しました。');
        }

        $error = $result['error'] ?? 'テスト投稿に失敗しました。';
        News_Crawler_X_Share_Log::add(
            '接続テスト投稿に失敗しました。',
            'error',
            array(),
            $error
        );
        $this->redirect_with_notice('error', $error);
    }

    /**
     * アカウント名再取得ハンドラ
     */
    public function handle_refresh_profile() {
        if (!current_user_can('manage_options')) {
            wp_die('権限がありません');
        }
        check_admin_referer('nc_x_refresh_profile');

        if (!$this->is_connected()) {
            $this->redirect_with_notice('error', 'X アカウントが接続されていません。');
        }

        $verify = $this->verify_credentials();
        if ($verify['success']) {
            $this->save_connected_profile($verify);
            $label = $this->get_connected_display_label();
            $message = $label !== ''
                ? sprintf('アカウント名を取得しました：%s', $label)
                : 'アカウント名を取得しました。';
            $this->redirect_with_notice('success', $message);
        }

        $error = $verify['error'] ?? 'アカウント名の取得に失敗しました。';
        $this->redirect_with_notice('error', $error . '（手動入力欄から @username を直接登録できます）');
    }

    /**
     * アカウント名手動入力ハンドラ
     */
    public function handle_save_manual_username() {
        if (!current_user_can('manage_options')) {
            wp_die('権限がありません');
        }
        check_admin_referer('nc_x_save_manual_username');

        $username = isset($_POST['nc_x_manual_username'])
            ? sanitize_text_field(wp_unslash($_POST['nc_x_manual_username']))
            : '';
        $username = ltrim(trim($username), '@');

        if ($username === '' || !preg_match('/^[A-Za-z0-9_]{1,15}$/', $username)) {
            $this->redirect_with_notice('error', '有効な X アカウント名（@username）を入力してください。');
        }

        $settings = $this->get_settings();
        $settings['twitter_connected_username'] = $username;
        if (empty($settings['twitter_connected_name'])) {
            $settings['twitter_connected_name'] = $username;
        }
        $this->update_settings($settings);

        $this->redirect_with_notice('success', sprintf('アカウント名 @%s を登録しました。', $username));
    }

    /**
     * 未シェア投稿の再試行ハンドラ
     */
    public function handle_retry_pending_shares() {
        if (!current_user_can('manage_options')) {
            wp_die('権限がありません');
        }
        check_admin_referer('nc_x_retry_pending_shares');

        if (!class_exists('News_Crawler_X_Poster')) {
            $this->redirect_with_notice('error', 'X 投稿機能が利用できません。');
        }

        $post_ids = News_Crawler_X_Poster::get_pending_post_ids(20);
        if (empty($post_ids)) {
            $this->redirect_with_notice('info', '未シェアの News Crawler 投稿は見つかりませんでした。');
        }

        $index = 0;
        foreach ($post_ids as $post_id) {
            if ($index > 0) {
                sleep(3);
            }
            News_Crawler_X_Poster::share_post($post_id, true);
            $index++;
        }

        $this->redirect_with_notice(
            'success',
            sprintf('未シェア投稿 %d 件の X シェアを再試行しました。シェアログを確認してください。', count($post_ids))
        );
    }

    /**
     * シェアログクリアハンドラ
     */
    public function handle_clear_share_log() {
        if (!current_user_can('manage_options')) {
            wp_die('権限がありません');
        }
        check_admin_referer('nc_x_clear_share_log');

        News_Crawler_X_Share_Log::clear();
        $this->redirect_with_notice('success', 'シェアログをクリアしました。');
    }

    /**
     * 管理画面通知（トランジェントから1回だけ表示）
     */
    public function render_admin_notices() {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (empty($_GET['page']) || $_GET['page'] !== 'news-crawler-cron-settings') {
            return;
        }

        $user_id = get_current_user_id();
        $key = $this->get_notice_transient_key($user_id);
        $notice = get_transient($key);

        // 旧バージョン互換: URL クエリ経由の通知も読む
        if (!is_array($notice) || empty($notice['type'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $legacy_type = isset($_GET['nc_x_notice']) ? sanitize_key(wp_unslash($_GET['nc_x_notice'])) : '';
            if ($legacy_type === '') {
                return;
            }
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $legacy_message = isset($_GET['nc_x_message']) ? rawurldecode(wp_unslash($_GET['nc_x_message'])) : '';
            $notice = array(
                'type' => $legacy_type,
                'message' => $legacy_message,
            );
            // 旧クエリを消すための JS を出力
            add_action('admin_footer', array($this, 'print_clear_legacy_notice_script'));
        } else {
            delete_transient($key);
        }

        $type = (string) ($notice['type'] ?? 'info');
        $message = (string) ($notice['message'] ?? '');
        if ($this->should_suppress_notice($message)) {
            return;
        }

        $class = 'notice notice-info is-dismissible';
        if ($type === 'success') {
            $class = 'notice notice-success is-dismissible';
        } elseif ($type === 'error') {
            $class = 'notice notice-error is-dismissible';
        }

        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), wp_kses_post($message));
    }

    /**
     * 旧バージョン通知 URL を JS で消す
     */
    public function print_clear_legacy_notice_script() {
        ?>
        <script>
        (function(){
            if (window.history && window.history.replaceState) {
                var url = new URL(window.location.href);
                if (url.searchParams.has('nc_x_notice') || url.searchParams.has('nc_x_message')) {
                    url.searchParams.delete('nc_x_notice');
                    url.searchParams.delete('nc_x_message');
                    window.history.replaceState({}, document.title, url.toString());
                }
            }
        })();
        </script>
        <?php
    }

    /**
     * 通知トランジェントキー
     *
     * @param int $user_id
     * @return string
     */
    private function get_notice_transient_key($user_id) {
        return 'nc_x_oauth_notice_' . intval($user_id);
    }

    /**
     * OAuth 一時データ用トランジェントキー（ユーザー単位）
     *
     * @param string $suffix
     * @return string
     */
    private function get_oauth_transient_key($suffix) {
        return 'nc_x_oauth_' . sanitize_key($suffix) . '_' . get_current_user_id();
    }

    /**
     * 表示しない通知かどうか
     *
     * @param string $message 通知メッセージ
     * @return bool
     */
    private function should_suppress_notice($message) {
        return strpos($message, 'X アカウントを接続しましたが、アカウント名の取得に失敗しました') !== false
            || strpos($message, 'アカウント名の自動取得に失敗') !== false;
    }

    /**
     * OAuth 2.0 Basic 認証ヘッダー（Client ID の : 含む形式に対応）
     *
     * @param string $client_id     Client ID
     * @param string $client_secret Client Secret
     * @return string
     */
    private function build_oauth2_basic_auth_header($client_id, $client_secret) {
        $credentials = rawurlencode($client_id) . ':' . rawurlencode($client_secret);

        return 'Basic ' . base64_encode($credentials);
    }

    /**
     * トークン交換エラーを日本語メッセージに変換
     *
     * @param mixed $data          API レスポンス
     * @param int   $status_code   HTTP コード
     * @return string
     */
    private function format_token_exchange_error($data, $status_code) {
        $error_code = is_array($data) && !empty($data['error']) ? (string) $data['error'] : '';
        $description = is_array($data) && !empty($data['error_description'])
            ? (string) $data['error_description']
            : 'アクセストークンの取得に失敗しました。';

        if ($error_code === 'invalid_client' || $status_code === 401) {
            return 'Client ID または Client Secret が正しくありません。Developer Portal の「Keys and Tokens」にある OAuth 2.0 Client ID / Client Secret（API Key ではありません）を再入力し、「Regenerate」した場合は両方を最新値に更新して「設定を保存」してください。 (HTTP ' . $status_code . ')';
        }

        if ($error_code === 'invalid_grant') {
            return '認可コードの有効期限が切れたか、Callback URL が一致していません。Developer Portal の Callback URL が診断パネルの URL と完全一致しているか確認し、もう一度「X アカウントを接続」してください。 (HTTP ' . $status_code . ')';
        }

        if ($error_code === 'unauthorized_client') {
            return 'このアプリ種別では OAuth 2.0 接続が許可されていません。Developer Portal で「Web App, Automated App or Bot（機密クライアント）」になっているか確認してください。 (HTTP ' . $status_code . ')';
        }

        if (stripos($description, 'Missing valid authorization header') !== false) {
            return 'Client Secret が送信されていません。OAuth 2.0 Client Secret を再入力して「設定を保存」してから、もう一度「X アカウントを接続」してください。 (HTTP ' . $status_code . ')';
        }

        return $description . ($status_code > 0 ? ' (HTTP ' . $status_code . ')' : '');
    }

    /**
     * API エラー抽出
     *
     * @param mixed $data          レスポンス
     * @param int   $response_code HTTP コード
     * @return string
     */
    private function extract_api_error_message($data, $response_code) {
        if (is_array($data)) {
            if (!empty($data['errors'][0]['detail'])) {
                return $data['errors'][0]['detail'] . ' (HTTP ' . $response_code . ')';
            }
            if (!empty($data['errors'][0]['message'])) {
                return $data['errors'][0]['message'] . ' (HTTP ' . $response_code . ')';
            }
            if (!empty($data['detail'])) {
                return $data['detail'] . ' (HTTP ' . $response_code . ')';
            }
            if (!empty($data['title'])) {
                return $data['title'] . ' (HTTP ' . $response_code . ')';
            }
        }

        return '不明なエラー (HTTP ' . $response_code . ')';
    }

    /**
     * トークン保存
     *
     * @param array $data トークンレスポンス
     */
    private function store_tokens(array $data) {
        $oauth = $this->get_oauth_option_settings();
        $oauth['twitter_oauth2_access_token'] = News_Crawler_X_Crypto::encrypt((string) $data['access_token']);

        if (!empty($data['refresh_token'])) {
            $oauth['twitter_oauth2_refresh_token'] = News_Crawler_X_Crypto::encrypt((string) $data['refresh_token']);
        }

        $expires_in = isset($data['expires_in']) ? (int) $data['expires_in'] : 7200;
        $oauth['twitter_oauth2_token_expires'] = time() + max(60, $expires_in - 60);

        $this->write_oauth_option($oauth);
        $this->update_settings(array('twitter_auth_method' => 'oauth2'));
        $this->strip_oauth_keys_from_basic_settings();
    }

    /**
     * OAuth 1.0a Authorization ヘッダー（資格情報指定）
     *
     * @param string               $method          HTTP メソッド
     * @param string               $url             URL
     * @param array<string, mixed> $params          クエリパラメータ
     * @param string               $consumer_key    API Key
     * @param string               $consumer_secret API Secret
     * @param string               $token           Access Token
     * @param string               $token_secret    Access Token Secret
     * @return string
     */
    private function build_oauth1_authorization_header_from_credentials(
        $method,
        $url,
        array $params,
        $consumer_key,
        $consumer_secret,
        $token,
        $token_secret
    ) {
        $parsed_url = wp_parse_url($url);
        $base_url = $parsed_url['scheme'] . '://' . $parsed_url['host'] . ($parsed_url['path'] ?? '');

        $oauth_params = array(
            'oauth_consumer_key' => $consumer_key,
            'oauth_nonce' => wp_generate_password(32, false, false),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => (string) time(),
            'oauth_token' => $token,
            'oauth_version' => '1.0',
        );

        $signature_params = array_merge($params, $oauth_params);
        if (!empty($parsed_url['query'])) {
            parse_str($parsed_url['query'], $query_params);
            if (is_array($query_params)) {
                $signature_params = array_merge($signature_params, $query_params);
            }
        }

        $oauth_params['oauth_signature'] = $this->generate_oauth1_signature(
            strtoupper($method),
            $base_url,
            $signature_params,
            $consumer_secret,
            $token_secret
        );

        $auth_parts = array();
        foreach ($oauth_params as $key => $value) {
            $auth_parts[] = rawurlencode($key) . '="' . rawurlencode((string) $value) . '"';
        }

        return 'OAuth ' . implode(', ', $auth_parts);
    }

    /**
     * OAuth 1.0a 署名
     */
    private function generate_oauth1_signature($method, $url, $params, $consumer_secret, $token_secret) {
        ksort($params);
        $query_parts = array();
        foreach ($params as $key => $value) {
            $query_parts[] = rawurlencode($key) . '=' . rawurlencode($value);
        }
        $signature_base_string = strtoupper($method) . '&' . rawurlencode($url) . '&' . rawurlencode(implode('&', $query_parts));
        $signature_key = rawurlencode($consumer_secret) . '&' . rawurlencode($token_secret);
        return base64_encode(hash_hmac('sha1', $signature_base_string, $signature_key, true));
    }

    /**
     * code_verifier 生成
     *
     * @return string
     */
    private function generate_code_verifier() {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    /**
     * code_challenge 生成
     *
     * @param string $code_verifier verifier
     * @return string
     */
    private function generate_code_challenge($code_verifier) {
        return rtrim(strtr(base64_encode(hash('sha256', $code_verifier, true)), '+/', '-_'), '=');
    }

    /**
     * リダイレクト通知
     *
     * @param string $type    通知タイプ
     * @param string $message メッセージ
     */
    private function redirect_with_notice($type, $message) {
        $user_id = get_current_user_id();
        if ($user_id > 0) {
            set_transient(
                $this->get_notice_transient_key($user_id),
                array(
                    'type' => (string) $type,
                    'message' => (string) $message,
                ),
                5 * MINUTE_IN_SECONDS
            );
        }

        wp_safe_redirect(
            add_query_arg(
                array('page' => 'news-crawler-cron-settings'),
                admin_url('admin.php')
            )
        );
        exit;
    }

    /**
     * 通知なしで自動投稿設定へ戻る
     */
    private function redirect_without_notice() {
        $user_id = get_current_user_id();
        if ($user_id > 0) {
            delete_transient($this->get_notice_transient_key($user_id));
        }

        wp_safe_redirect(
            add_query_arg(
                array('page' => 'news-crawler-cron-settings'),
                admin_url('admin.php')
            )
        );
        exit;
    }
}
