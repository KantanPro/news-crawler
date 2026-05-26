<?php
/**
 * X（Twitter）投稿機能クラス
 *
 * X API v2 + OAuth 2.0 / OAuth 1.0a User Context
 *
 * @package News_Crawler
 */

if (!defined('ABSPATH')) {
    exit;
}

class News_Crawler_X_Poster {

    /**
     * @var array<int, bool>
     */
    private static $processing = array();

    /**
     * コンストラクタ
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
    }

    /**
     * 初期化
     */
    public function init() {
        add_action('transition_post_status', array($this, 'handle_post_status_change'), 10, 3);
    }

    /**
     * 投稿公開時に X へ投稿
     *
     * @param string  $new_status 新ステータス
     * @param string  $old_status 旧ステータス
     * @param WP_Post $post       投稿
     */
    public function handle_post_status_change($new_status, $old_status, $post) {
        if ($new_status !== 'publish' || $old_status === 'publish') {
            return;
        }
        if (!($post instanceof WP_Post)) {
            return;
        }
        $this->auto_post_to_x($post->ID);
    }

    /**
     * 自動投稿
     *
     * @param int $post_id 投稿 ID
     */
    public function auto_post_to_x($post_id) {
        $post_id = (int) $post_id;
        if ($post_id <= 0 || isset(self::$processing[$post_id])) {
            return;
        }
        self::$processing[$post_id] = true;

        if (!get_post_meta($post_id, '_news_crawler_created', true)) {
            return;
        }
        if (get_post_meta($post_id, '_x_posted', true)) {
            return;
        }

        $settings = get_option('news_crawler_basic_settings', array());
        if (empty($settings['twitter_enabled'])) {
            return;
        }
        if (!$this->is_connected($settings)) {
            $post_title = get_the_title($post_id);
            News_Crawler_X_Share_Log::add(
                sprintf('「%s」の X シェアに失敗', $post_title ?: ('投稿 ID ' . $post_id)),
                'error',
                array('post_id' => $post_id),
                'X アカウントが接続されていません。'
            );
            $this->log('X 投稿に必要な認証情報が不足しています', 'error');
            return;
        }

        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish' || $post->post_type !== 'post') {
            return;
        }

        $message = $this->generate_post_message($post, $settings);
        $result = $this->post_message($message, $settings);

        if ($result['success']) {
            update_post_meta($post_id, '_x_posted', true);
            update_post_meta($post_id, '_x_post_id', $result['tweet_id']);
            update_post_meta($post_id, '_x_posted_at', current_time('mysql'));
            News_Crawler_X_Share_Log::add(
                sprintf('「%s」を X にシェアしました（Tweet ID: %s）', $post->post_title, $result['tweet_id']),
                'success',
                array(
                    'post_id' => $post_id,
                    'tweet_id' => $result['tweet_id'],
                )
            );
            $this->log('X 投稿成功 - Post ID: ' . $post_id . ', Tweet ID: ' . $result['tweet_id'], 'info');
        } else {
            $error = $result['error'] ?? '不明なエラー';
            News_Crawler_X_Share_Log::add(
                sprintf('「%s」の X シェアに失敗', $post->post_title),
                'error',
                array('post_id' => $post_id),
                $error
            );
            $this->log('X 投稿失敗 - Post ID: ' . $post_id . ', Error: ' . $error, 'error');
        }
    }

    /**
     * テスト投稿
     *
     * @param string $message メッセージ
     * @return array{success:bool,tweet_id?:string,error?:string}
     */
    public function post_test_message($message) {
        $settings = get_option('news_crawler_basic_settings', array());
        if (!$this->is_connected($settings)) {
            return array('success' => false, 'error' => 'X アカウントが接続されていません。');
        }
        return $this->post_message($message, $settings);
    }

    /**
     * 接続済みか
     *
     * @param array|null $settings 設定
     * @return bool
     */
    public function is_connected($settings = null) {
        return News_Crawler_X_OAuth::instance()->is_connected($settings);
    }

    /**
     * 投稿メッセージ生成
     *
     * @param WP_Post $post     投稿
     * @param array   $settings 設定
     * @return string
     */
    private function generate_post_message($post, $settings) {
        $template = !empty($settings['twitter_message_template'])
            ? $settings['twitter_message_template']
            : "%TITLE%\n%URL%";

        $message = $this->replace_placeholders($template, $post);

        if (!empty($settings['twitter_hashtags'])) {
            foreach (preg_split('/\s+/', trim($settings['twitter_hashtags'])) as $tag) {
                $tag = trim($tag);
                if ($tag !== '') {
                    $message .= ' #' . ltrim($tag, '#');
                }
            }
        }

        return $this->adjust_message_length($message, 280);
    }

    /**
     * 文字数調整
     *
     * @param string $message    メッセージ
     * @param int    $max_length 最大文字数
     * @return string
     */
    private function adjust_message_length($message, $max_length) {
        $url_length = 0;
        if (preg_match_all('/https?:\/\/[^\s]+/', $message, $matches)) {
            foreach ($matches[0] as $url) {
                $url_length += min(mb_strlen($url), 23);
            }
        }

        $hashtag_length = 0;
        if (preg_match_all('/#\w+/u', $message, $hashtag_matches)) {
            foreach ($hashtag_matches[0] as $hashtag) {
                $hashtag_length += mb_strlen($hashtag) + 1;
            }
        }

        $text_max_length = $max_length - $url_length - $hashtag_length;
        $text_part = preg_replace('/https?:\/\/[^\s]+/', '', $message);
        $text_part = preg_replace('/#\w+/u', '', $text_part);
        $text_part = trim($text_part);

        if (mb_strlen($text_part) > $text_max_length) {
            $text_part = mb_substr($text_part, 0, max(1, $text_max_length - 3)) . '...';
        }

        $final_message = $text_part;
        if (preg_match_all('/https?:\/\/[^\s]+/', $message, $url_matches)) {
            foreach ($url_matches[0] as $url) {
                $final_message .= ' ' . $url;
            }
        }
        if (preg_match_all('/#\w+/u', $message, $hashtag_matches)) {
            foreach ($hashtag_matches[0] as $hashtag) {
                $final_message .= ' ' . $hashtag;
            }
        }

        return trim($final_message);
    }

    /**
     * プレースホルダー置換
     *
     * @param string  $template テンプレート
     * @param WP_Post $post     投稿
     * @return string
     */
    private function replace_placeholders($template, $post) {
        $template = str_replace(array("\r\n", "\r"), "\n", $template);
        $post_url = get_permalink($post->ID);
        $excerpt = get_the_excerpt($post->ID);

        $replacements = array(
            '%TITLE%' => $post->post_title,
            '%URL%' => $post_url,
            '%SURL%' => $post_url,
            '%EXCERPT%' => $excerpt,
            '%SITENAME%' => get_bloginfo('name'),
            '%AUTHORNAME%' => get_the_author_meta('display_name', $post->post_author),
        );

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * X API へ投稿
     *
     * @param string $message  メッセージ
     * @param array  $settings 設定
     * @return array{success:bool,tweet_id?:string,error?:string}
     */
    private function post_message($message, $settings) {
        News_Crawler_X_OAuth::instance()->maybe_refresh_token();

        $method = News_Crawler_X_OAuth::instance()->get_auth_method($settings);
        if ($method === 'oauth1') {
            return $this->post_via_oauth1($message, $settings);
        }
        return $this->post_via_oauth2($message, $settings);
    }

    /**
     * OAuth 2.0 で投稿
     *
     * @param string $message  メッセージ
     * @param array  $settings 設定
     * @return array{success:bool,tweet_id?:string,error?:string}
     */
    private function post_via_oauth2($message, $settings) {
        $access_token = News_Crawler_X_OAuth::instance()->get_access_token();
        if ($access_token === '') {
            return array('success' => false, 'error' => 'X アカウントが接続されていません。');
        }

        $response = wp_remote_post(
            'https://api.x.com/2/tweets',
            array(
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json',
                ),
                'body' => wp_json_encode(array('text' => $message)),
            )
        );

        if (is_wp_error($response)) {
            return array('success' => false, 'error' => $response->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $data = json_decode((string) wp_remote_retrieve_body($response), true);

        if ($code === 401 && News_Crawler_X_OAuth::instance()->refresh_access_token()) {
            return $this->post_via_oauth2($message, $settings);
        }

        if ($code >= 200 && $code < 300 && !empty($data['data']['id'])) {
            return array('success' => true, 'tweet_id' => (string) $data['data']['id']);
        }

        return array('success' => false, 'error' => $this->extract_api_error_message($data, $code));
    }

    /**
     * OAuth 1.0a で投稿
     *
     * @param string $message  メッセージ
     * @param array  $settings 設定
     * @return array{success:bool,tweet_id?:string,error?:string}
     */
    private function post_via_oauth1($message, $settings) {
        $endpoint = 'https://api.twitter.com/2/tweets';
        $auth_header = $this->build_oauth_authorization_header('POST', $endpoint, $settings);

        $response = wp_remote_post(
            $endpoint,
            array(
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => $auth_header,
                    'Content-Type' => 'application/json',
                ),
                'body' => wp_json_encode(array('text' => $message)),
            )
        );

        if (is_wp_error($response)) {
            return array('success' => false, 'error' => $response->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $data = json_decode((string) wp_remote_retrieve_body($response), true);

        if ($code === 201 && !empty($data['data']['id'])) {
            return array('success' => true, 'tweet_id' => (string) $data['data']['id']);
        }

        return array('success' => false, 'error' => $this->extract_api_error_message($data, $code));
    }

    /**
     * OAuth 1.0a Authorization ヘッダー
     *
     * @param string $method   HTTP メソッド
     * @param string $url      URL
     * @param array  $settings 設定
     * @return string
     */
    private function build_oauth_authorization_header($method, $url, $settings) {
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

        $oauth_params['oauth_signature'] = $this->generate_oauth_signature(
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
    private function generate_oauth_signature($method, $url, $params, $consumer_secret, $token_secret) {
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
     * API エラー抽出
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
     * ログ出力
     */
    private function log($message, $level = 'info') {
        if (class_exists('NewsCrawler_Secure_Logger')) {
            if ($level === 'error') {
                NewsCrawler_Secure_Logger::error($message);
            } else {
                NewsCrawler_Secure_Logger::info($message);
            }
            return;
        }
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('News Crawler X Poster: ' . $message);
        }
    }

    /**
     * 手動テスト
     *
     * @param int $post_id 投稿 ID
     */
    public function manual_test_x_post($post_id) {
        update_post_meta($post_id, '_news_crawler_created', true);
        delete_post_meta($post_id, '_x_posted');
        delete_post_meta($post_id, '_x_post_id');
        delete_post_meta($post_id, '_x_posted_at');
        $this->auto_post_to_x($post_id);
    }
}
