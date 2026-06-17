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

    /** 未シェア投稿を再試行ボタンで一度にシェアする件数 */
    const RETRY_PENDING_SHARE_BATCH_SIZE = 3;

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
        add_action('news_crawler_share_to_x', array($this, 'auto_post_to_x'));
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

        $post_id = (int) $post->ID;
        add_action('shutdown', function () use ($post_id) {
            if (function_exists('news_crawler_trigger_x_share')) {
                news_crawler_trigger_x_share($post_id);
            } else {
                self::share_post($post_id);
            }
        });
    }

    /**
     * 指定投稿を X にシェア
     *
     * @param int  $post_id 投稿 ID
     * @param bool $force   既にシェア済みでも再試行する
     */
    public static function share_post($post_id, $force = false, $skip_daily_limit = false) {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            return;
        }

        if ($force) {
            delete_post_meta($post_id, '_x_posted');
            delete_post_meta($post_id, '_x_post_id');
            delete_post_meta($post_id, '_x_posted_at');
        }

        static $poster = null;
        if ($poster === null) {
            $poster = new self();
        }

        $poster->auto_post_to_x($post_id, $skip_daily_limit);
    }

    /**
     * 本日（サイトタイムゾーン）の X 自動シェア成功件数
     *
     * @return int
     */
    public static function count_today_x_shares() {
        $today_start = current_time('Y-m-d') . ' 00:00:00';
        $today_end = current_time('Y-m-d') . ' 23:59:59';

        $query = new WP_Query(array(
            'post_type' => 'any',
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => false,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_x_posted',
                    'value' => '1',
                ),
                array(
                    'key' => '_x_posted_at',
                    'value' => array($today_start, $today_end),
                    'compare' => 'BETWEEN',
                    'type' => 'DATETIME',
                ),
            ),
        ));

        return (int) $query->found_posts;
    }

    /**
     * 未シェアの News Crawler 投稿 ID 一覧
     *
     * @param int $limit 取得件数
     * @return array<int, int>
     */
    public static function get_pending_post_ids($limit = null) {
        if ($limit === null) {
            $limit = self::RETRY_PENDING_SHARE_BATCH_SIZE;
        }
        $args = self::get_pending_posts_query_args($limit);
        $args['no_found_rows'] = true;
        $query = new WP_Query($args);

        return array_map('intval', $query->posts);
    }

    /**
     * 未シェアの News Crawler 投稿件数
     *
     * @return int
     */
    public static function get_pending_post_count() {
        $args = self::get_pending_posts_query_args(1);
        $args['no_found_rows'] = false;
        $query = new WP_Query($args);
        return (int) $query->found_posts;
    }

    /**
     * 未シェア投稿クエリの共通引数
     *
     * @param int $limit 取得件数
     * @return array<string, mixed>
     */
    private static function get_pending_posts_query_args($limit) {
        return array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => max(1, min(50, (int) $limit)),
            'fields' => 'ids',
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_news_crawler_created',
                    'value' => '1',
                ),
                array(
                    'relation' => 'OR',
                    array(
                        'key' => '_x_posted',
                        'compare' => 'NOT EXISTS',
                    ),
                    array(
                        'key' => '_x_posted',
                        'value' => '',
                    ),
                    array(
                        'key' => '_x_posted',
                        'value' => '0',
                    ),
                ),
            ),
        );
    }

    /**
     * 設定を取得（OAuth 専用オプションを含む）
     *
     * @return array
     */
    private function get_x_settings() {
        if (class_exists('News_Crawler_X_OAuth')) {
            return News_Crawler_X_OAuth::instance()->get_settings();
        }

        $settings = get_option('news_crawler_basic_settings', array());
        return is_array($settings) ? $settings : array();
    }

    /**
     * 自動投稿
     *
     * @param int  $post_id          投稿 ID
     * @param bool $skip_daily_limit 日次上限チェックをスキップする（手動再試行・テスト用）
     */
    public function auto_post_to_x($post_id, $skip_daily_limit = false) {
        $post_id = (int) $post_id;
        if ($post_id <= 0 || isset(self::$processing[$post_id])) {
            return;
        }

        if (!get_post_meta($post_id, '_news_crawler_created', true)) {
            $this->log_share_skip($post_id, 'News Crawler 作成投稿ではないため X シェア対象外です。');
            return;
        }
        if (get_post_meta($post_id, '_x_posted', true)) {
            $this->log_share_skip($post_id, 'この投稿は既に X シェア済みです。');
            return;
        }

        $settings = $this->get_x_settings();
        if (empty($settings['twitter_enabled'])) {
            $this->log_share_skip($post_id, 'X 自動シェアが無効です。自動投稿設定で有効にしてください。');
            return;
        }

        if (!$skip_daily_limit) {
            $daily_limit = isset($settings['twitter_max_daily_shares'])
                ? max(0, (int) $settings['twitter_max_daily_shares'])
                : 0;
            if ($daily_limit > 0 && self::count_today_x_shares() >= $daily_limit) {
                $this->log_share_skip(
                    $post_id,
                    sprintf('本日の X 自動シェア上限（%d 件）に達したためスキップしました。', $daily_limit)
                );
                return;
            }
        }

        self::$processing[$post_id] = true;

        try {
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
                $reason = !$post
                    ? '投稿が見つかりません。'
                    : ($post->post_status !== 'publish'
                        ? '投稿が公開状態ではありません（現在: ' . $post->post_status . '）。'
                        : '投稿タイプが post ではありません。');
                $this->log_share_skip($post_id, $reason);
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
        } finally {
            unset(self::$processing[$post_id]);
        }
    }

    /**
     * テスト投稿
     *
     * @param string $message メッセージ
     * @return array{success:bool,tweet_id?:string,error?:string}
     */
    public function post_test_message($message) {
        $settings = $this->get_x_settings();
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
    private function post_via_oauth2($message, $settings, $attempt = 1) {
        $max_attempts = 3;
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
            return $this->post_via_oauth2($message, $settings, 1);
        }

        if ($code >= 200 && $code < 300 && !empty($data['data']['id'])) {
            return array('success' => true, 'tweet_id' => (string) $data['data']['id']);
        }

        if ($attempt < $max_attempts && in_array($code, array(403, 429), true)) {
            $delay = min(8, (int) pow(2, $attempt));
            $this->log(
                'X 投稿を再試行します (HTTP ' . $code . ', 試行 ' . ($attempt + 1) . '/' . $max_attempts . ', ' . $delay . '秒待機)',
                'info'
            );
            sleep($delay);
            News_Crawler_X_OAuth::instance()->maybe_refresh_token();
            return $this->post_via_oauth2($message, $settings, $attempt + 1);
        }

        $error = $this->extract_api_error_message($data, $code);
        if ($code === 403) {
            $error .= '（投稿文字数: ' . mb_strlen($message) . ' 文字）';
        }

        return array('success' => false, 'error' => $error);
    }

    /**
     * OAuth 1.0a で投稿
     *
     * @param string $message  メッセージ
     * @param array  $settings 設定
     * @return array{success:bool,tweet_id?:string,error?:string}
     */
    private function post_via_oauth1($message, $settings, $attempt = 1) {
        $max_attempts = 3;
        $oauth = News_Crawler_X_OAuth::instance();
        if (!$oauth->has_usable_oauth1_credentials($settings)) {
            return array(
                'success' => false,
                'error' => 'OAuth 1.0a の認証情報が未設定、または復号できません。API Key / Secret / Access Token / Access Token Secret を再入力して「設定を保存」してください。',
            );
        }

        $endpoint = 'https://api.x.com/2/tweets';
        $auth_header = $oauth->build_oauth1_authorization_header('POST', $endpoint, $settings);

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

        if ($code >= 200 && $code < 300 && !empty($data['data']['id'])) {
            return array('success' => true, 'tweet_id' => (string) $data['data']['id']);
        }

        if ($attempt < $max_attempts && in_array($code, array(403, 429), true)) {
            $delay = min(8, (int) pow(2, $attempt));
            $this->log(
                'X 投稿を再試行します (HTTP ' . $code . ', 試行 ' . ($attempt + 1) . '/' . $max_attempts . ', ' . $delay . '秒待機)',
                'info'
            );
            sleep($delay);
            return $this->post_via_oauth1($message, $settings, $attempt + 1);
        }

        $error = $this->format_oauth1_post_error($data, $code);
        if ($code === 403) {
            $error .= '（投稿文字数: ' . mb_strlen($message) . ' 文字）';
        }

        return array(
            'success' => false,
            'error' => $error,
        );
    }

    /**
     * OAuth 1.0a 投稿エラーを日本語化
     *
     * @param mixed $data          API レスポンス
     * @param int   $response_code HTTP コード
     * @return string
     */
    private function format_oauth1_post_error($data, $response_code) {
        $message = $this->extract_api_error_message($data, $response_code);

        if ($response_code === 401) {
            return $message . ' — Developer Portal の OAuth 1.0a（API Key / API Secret / Access Token / Access Token Secret）が正しいか確認してください。OAuth 2.0 Client ID/Secret ではありません。Read and Write 権限のトークンを Regenerate して再入力してください。';
        }

        return $message;
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
     * シェアスキップをシェアログに記録
     *
     * @param int    $post_id 投稿 ID
     * @param string $reason  理由
     */
    private function log_share_skip($post_id, $reason) {
        if (!class_exists('News_Crawler_X_Share_Log')) {
            return;
        }

        $title = get_the_title($post_id);
        News_Crawler_X_Share_Log::add(
            sprintf('「%s」の X シェアをスキップ', $title ?: ('投稿 ID ' . $post_id)),
            'info',
            array('post_id' => $post_id),
            $reason
        );
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
        self::share_post($post_id, true, true);
    }
}
