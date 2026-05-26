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
        add_action('admin_post_nc_x_disconnect', array($this, 'handle_disconnect'));
        add_action('admin_post_nc_x_test_tweet', array($this, 'handle_test_tweet'));
        add_action('admin_post_nc_x_clear_share_log', array($this, 'handle_clear_share_log'));
        add_action('admin_post_nc_x_refresh_profile', array($this, 'handle_refresh_profile'));
        add_action('admin_post_nc_x_save_manual_username', array($this, 'handle_save_manual_username'));
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
     * 設定を取得
     *
     * @return array
     */
    public function get_settings() {
        $settings = get_option('news_crawler_basic_settings', array());
        return is_array($settings) ? $settings : array();
    }

    /**
     * 設定を保存
     *
     * @param array $settings 設定
     */
    public function update_settings(array $settings) {
        update_option('news_crawler_basic_settings', $settings);
    }

    /**
     * 接続済みか
     *
     * @param array|null $settings 設定
     * @return bool
     */
    public function is_connected($settings = null) {
        $settings = $settings ?: $this->get_settings();
        $method = $this->get_auth_method($settings);

        if ($method === 'oauth1') {
            return !empty($settings['twitter_api_key'])
                && !empty($settings['twitter_api_secret'])
                && !empty($settings['twitter_access_token'])
                && !empty($settings['twitter_access_token_secret']);
        }

        return !empty($settings['twitter_client_id'])
            && !empty($settings['twitter_oauth2_access_token']);
    }

    /**
     * 認証方式を取得
     *
     * @param array|null $settings 設定
     * @return string
     */
    public function get_auth_method($settings = null) {
        $settings = $settings ?: $this->get_settings();
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
     * 認可 URL を生成
     *
     * @return string
     */
    public function get_authorization_url() {
        $settings = $this->get_settings();
        $client_id = trim((string) ($settings['twitter_client_id'] ?? ''));

        if ($client_id === '') {
            return '';
        }

        $code_verifier = $this->generate_code_verifier();
        $code_challenge = $this->generate_code_challenge($code_verifier);
        $state = wp_generate_password(32, false, false);

        set_transient('nc_x_oauth_code_verifier', $code_verifier, 15 * MINUTE_IN_SECONDS);
        set_transient('nc_x_oauth_state', $state, 15 * MINUTE_IN_SECONDS);

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
        $saved = get_transient('nc_x_oauth_state');
        if ($state === '' || $saved === false || !hash_equals((string) $saved, $state)) {
            $this->redirect_with_notice('error', 'OAuth state が一致しません。もう一度お試しください。');
        }
        delete_transient('nc_x_oauth_state');

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
        if ($code === '') {
            $this->redirect_with_notice('error', '認可コードが取得できませんでした。');
        }

        $code_verifier = get_transient('nc_x_oauth_code_verifier');
        if ($code_verifier === false || $code_verifier === '') {
            $this->redirect_with_notice('error', 'code_verifier の有効期限が切れました。');
        }
        delete_transient('nc_x_oauth_code_verifier');

        $result = $this->exchange_code_for_tokens($code, (string) $code_verifier);
        if (!$result['success']) {
            $this->redirect_with_notice('error', $result['error'] ?? 'トークン取得に失敗しました。');
        }

        $display_label = $this->get_connected_display_label();
        if ($display_label !== '') {
            $message = sprintf('X アカウント %s に接続しました。', $display_label);
        } else {
            $verify_error = isset($result['verify_error']) ? (string) $result['verify_error'] : '';
            if ($verify_error !== '') {
                $message = sprintf(
                    'X アカウントを接続しましたが、アカウント名の取得に失敗しました：%s（接続操作欄から手動で再取得できます）',
                    $verify_error
                );
            } else {
                $message = 'X アカウントを接続しました。アカウント名の自動取得に失敗したため、接続操作欄から「アカウント名を再取得」してください。';
            }
        }
        $this->redirect_with_notice('success', $message);
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
        $client_secret = News_Crawler_X_Crypto::decrypt((string) ($settings['twitter_client_secret'] ?? ''));

        if ($client_id === '') {
            return array('success' => false, 'error' => 'Client ID が設定されていません。');
        }

        $body = array(
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->get_redirect_uri(),
            'code_verifier' => $code_verifier,
            'client_id' => $client_id,
        );

        $headers = array('Content-Type' => 'application/x-www-form-urlencoded');
        if ($client_secret !== '') {
            $headers['Authorization'] = 'Basic ' . base64_encode($client_id . ':' . $client_secret);
        }

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

        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($data) || empty($data['access_token'])) {
            $error = !empty($data['error_description']) ? (string) $data['error_description'] : 'アクセストークンの取得に失敗しました。';
            return array('success' => false, 'error' => $error);
        }

        $this->store_tokens($data);

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
        $client_secret = News_Crawler_X_Crypto::decrypt((string) ($settings['twitter_client_secret'] ?? ''));
        $refresh_token = News_Crawler_X_Crypto::decrypt((string) ($settings['twitter_oauth2_refresh_token'] ?? ''));

        if ($client_id === '' || $refresh_token === '') {
            return false;
        }

        $body = array(
            'grant_type' => 'refresh_token',
            'refresh_token' => $refresh_token,
            'client_id' => $client_id,
        );

        $headers = array('Content-Type' => 'application/x-www-form-urlencoded');
        if ($client_secret !== '') {
            $headers['Authorization'] = 'Basic ' . base64_encode($client_id . ':' . $client_secret);
        }

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
        if ($expires > 0 && time() >= $expires) {
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

        $settings = $settings ?: $this->get_settings();
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
        $settings = $settings ?: $this->get_settings();
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
        $settings = $settings ?: $this->get_settings();
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
                        'User-Agent' => 'NewsCrawler/' . (defined('NEWS_CRAWLER_VERSION') ? NEWS_CRAWLER_VERSION : '1.0') . '; ' . home_url(),
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
        $endpoints = array(
            'https://api.x.com/2/users/me',
            'https://api.twitter.com/2/users/me',
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
        $settings = $this->get_settings();
        $settings['twitter_oauth2_access_token'] = '';
        $settings['twitter_oauth2_refresh_token'] = '';
        $settings['twitter_oauth2_token_expires'] = 0;
        $settings['twitter_connected_username'] = '';
        $settings['twitter_connected_name'] = '';
        $this->update_settings($settings);
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
     * 管理画面通知
     */
    public function render_admin_notices() {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (empty($_GET['page']) || $_GET['page'] !== 'news-crawler-cron-settings') {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $notice = isset($_GET['nc_x_notice']) ? sanitize_key(wp_unslash($_GET['nc_x_notice'])) : '';
        if ($notice === '') {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $message = isset($_GET['nc_x_message']) ? rawurldecode(wp_unslash($_GET['nc_x_message'])) : '';
        $class = 'notice notice-info';
        if ($notice === 'success') {
            $class = 'notice notice-success is-dismissible';
        } elseif ($notice === 'error') {
            $class = 'notice notice-error';
        }

        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
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
        $settings = $this->get_settings();
        $settings['twitter_oauth2_access_token'] = News_Crawler_X_Crypto::encrypt((string) $data['access_token']);

        if (!empty($data['refresh_token'])) {
            $settings['twitter_oauth2_refresh_token'] = News_Crawler_X_Crypto::encrypt((string) $data['refresh_token']);
        }

        $expires_in = isset($data['expires_in']) ? (int) $data['expires_in'] : 7200;
        $settings['twitter_oauth2_token_expires'] = time() + max(60, $expires_in - 60);
        $settings['twitter_auth_method'] = 'oauth2';

        $this->update_settings($settings);
    }

    /**
     * OAuth 1.0a Authorization ヘッダー
     *
     * @param string $method   HTTP メソッド
     * @param string $url      URL
     * @param array  $settings 設定
     * @return string
     */
    private function build_oauth1_authorization_header($method, $url, $settings) {
        $parsed_url = wp_parse_url($url);
        $base_url = $parsed_url['scheme'] . '://' . $parsed_url['host'] . ($parsed_url['path'] ?? '');

        $oauth_params = array(
            'oauth_consumer_key' => $settings['twitter_api_key'],
            'oauth_nonce' => wp_generate_password(32, false),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => (string) time(),
            'oauth_token' => $settings['twitter_access_token'],
            'oauth_version' => '1.0',
        );

        $signature_params = $oauth_params;
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
            News_Crawler_X_Crypto::decrypt((string) ($settings['twitter_api_secret'] ?? '')),
            News_Crawler_X_Crypto::decrypt((string) ($settings['twitter_access_token_secret'] ?? ''))
        );

        $auth_parts = array();
        foreach ($oauth_params as $key => $value) {
            $auth_parts[] = rawurlencode($key) . '="' . rawurlencode($value) . '"';
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
        wp_safe_redirect(
            add_query_arg(
                array(
                    'page' => 'news-crawler-cron-settings',
                    'nc_x_notice' => $type,
                    'nc_x_message' => rawurlencode($message),
                ),
                admin_url('admin.php')
            )
        );
        exit;
    }
}
