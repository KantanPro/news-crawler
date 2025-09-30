<?php
/**
 * X（Twitter）投稿機能クラス
 * 
 * @package News_Crawler
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class News_Crawler_X_Poster {
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        error_log('X Poster: コンストラクタが呼び出されました');
        
        // initフックを登録
        add_action('init', array($this, 'init'));
        error_log('X Poster: initフックを登録しました');
        
        // より早いタイミングでもフックを登録
        add_action('wp_loaded', array($this, 'init'), 5);
        
        // AJAXハンドラーを直接登録（確実性を高める）
        add_action('wp_ajax_test_x_connection', array($this, 'test_x_connection'));
        add_action('wp_ajax_nopriv_test_x_connection', array($this, 'test_x_connection'));
        
        // 投稿公開時のフックを直接登録（確実性を高める）
        add_action('publish_post', array($this, 'auto_post_to_x'), 10, 1);
        
        // より確実なフック：投稿ステータス変更時
        add_action('transition_post_status', array($this, 'handle_post_status_change'), 10, 3);
        
        // より早いタイミングでも登録
        add_action('wp_loaded', array($this, 'register_hooks_early'), 5);
        
        // さらに確実にするため、wp_loadedでも直接登録
        add_action('wp_loaded', array($this, 'register_hooks_direct'), 10);
        
        error_log('X Poster: コンストラクタでAJAXハンドラーと投稿フックを登録しました');
    }
    
    /**
     * 初期化
     */
    public function init() {
        error_log('X Poster: init() が呼び出されました');
        
        // 投稿公開時の自動投稿フック（init内でも登録）
        add_action('publish_post', array($this, 'auto_post_to_x'), 10, 1);
        
        // より確実なフック：投稿ステータス変更時
        add_action('transition_post_status', array($this, 'handle_post_status_change'), 10, 3);
        
        // 投稿挿入時
        add_action('wp_insert_post', array($this, 'handle_wp_insert_post'), 10, 3);
        
        // 投稿保存時
        add_action('save_post', array($this, 'handle_save_post'), 10, 3);
        
        // AJAXハンドラーを管理画面でのみ登録
        add_action('admin_init', array($this, 'register_ajax_handlers'));
        
        error_log('X Poster: init() でフックを再登録しました');
        
        // フック登録確認
        global $wp_filter;
        if (isset($wp_filter['publish_post'])) {
            error_log('X Poster: publish_post フックが登録されています');
        } else {
            error_log('X Poster: publish_post フックが登録されていません');
        }
        
        if (isset($wp_filter['transition_post_status'])) {
            error_log('X Poster: transition_post_status フックが登録されています');
        } else {
            error_log('X Poster: transition_post_status フックが登録されていません');
        }
    }
    
    /**
     * 早期フック登録
     */
    public function register_hooks_early() {
        error_log('X Poster: register_hooks_early が呼び出されました');
        
        // 投稿関連フックを再登録
        add_action('publish_post', array($this, 'auto_post_to_x'), 10, 1);
        
        // より確実なフック：投稿ステータス変更時
        add_action('transition_post_status', array($this, 'handle_post_status_change'), 10, 3);
        
        // 投稿挿入時
        add_action('wp_insert_post', array($this, 'handle_wp_insert_post'), 10, 3);
        
        // 投稿保存時
        add_action('save_post', array($this, 'handle_save_post'), 10, 3);
        
        error_log('X Poster: 早期フック登録完了');
    }
    
    /**
     * 直接フック登録
     */
    public function register_hooks_direct() {
        error_log('X Poster: register_hooks_direct が呼び出されました');
        
        // 投稿関連フックを直接登録
        add_action('publish_post', array($this, 'auto_post_to_x'), 10, 1);
        add_action('transition_post_status', array($this, 'handle_post_status_change'), 10, 3);
        add_action('wp_insert_post', array($this, 'handle_wp_insert_post'), 10, 3);
        add_action('save_post', array($this, 'handle_save_post'), 10, 3);
        
        // より確実なフックを追加
        add_action('wp_after_insert_post', array($this, 'handle_wp_after_insert_post'), 10, 4);
        add_action('post_updated', array($this, 'handle_post_updated'), 10, 3);
        
        // デバッグ用：すべての投稿関連フックを監視
        add_action('wp_insert_post', array($this, 'debug_wp_insert_post'), 5, 3);
        add_action('save_post', array($this, 'debug_save_post'), 5, 3);
        add_action('publish_post', array($this, 'debug_publish_post'), 5, 1);
        add_action('transition_post_status', array($this, 'debug_transition_post_status'), 5, 3);
        
        // より広範囲なフックを監視
        add_action('wp_after_insert_post', array($this, 'debug_wp_after_insert_post'), 5, 4);
        add_action('post_updated', array($this, 'debug_post_updated'), 5, 3);
        add_action('wp_update_post', array($this, 'debug_wp_update_post'), 5, 3);
        add_action('wp_publish_post', array($this, 'debug_wp_publish_post'), 5, 1);
        
        // さらに広範囲なフックを監視
        add_action('wp_insert_post', array($this, 'debug_wp_insert_post_general'), 1, 3);
        add_action('save_post', array($this, 'debug_save_post_general'), 1, 3);
        add_action('publish_post', array($this, 'debug_publish_post_general'), 1, 1);
        add_action('transition_post_status', array($this, 'debug_transition_post_status_general'), 1, 3);
        
        // さらに広範囲なフックを監視（すべてのフック）
        add_action('wp_insert_post', array($this, 'debug_wp_insert_post_all'), 999, 3);
        add_action('save_post', array($this, 'debug_save_post_all'), 999, 3);
        add_action('publish_post', array($this, 'debug_publish_post_all'), 999, 1);
        add_action('transition_post_status', array($this, 'debug_transition_post_status_all'), 999, 3);
        
        // さらに広範囲なフックを監視（すべてのフック）
        add_action('wp_insert_post', array($this, 'debug_wp_insert_post_ultra'), 0, 3);
        add_action('save_post', array($this, 'debug_save_post_ultra'), 0, 3);
        add_action('publish_post', array($this, 'debug_publish_post_ultra'), 0, 1);
        add_action('transition_post_status', array($this, 'debug_transition_post_status_ultra'), 0, 3);
        
        // さらに広範囲なフックを監視（すべてのフック）
        add_action('wp_insert_post', array($this, 'debug_wp_insert_post_super'), -1, 3);
        add_action('save_post', array($this, 'debug_save_post_super'), -1, 3);
        add_action('publish_post', array($this, 'debug_publish_post_super'), -1, 1);
        add_action('transition_post_status', array($this, 'debug_transition_post_status_super'), -1, 3);
        
        error_log('X Poster: 直接フック登録完了');
        
        // フック登録確認
        global $wp_filter;
        if (isset($wp_filter['publish_post'])) {
            error_log('X Poster: publish_post フックが登録されています（直接登録後）');
        } else {
            error_log('X Poster: publish_post フックが登録されていません（直接登録後）');
        }
        
        if (isset($wp_filter['transition_post_status'])) {
            error_log('X Poster: transition_post_status フックが登録されています（直接登録後）');
        } else {
            error_log('X Poster: transition_post_status フックが登録されていません（直接登録後）');
        }
    }
    
    /**
     * AJAXハンドラーを登録
     */
    public function register_ajax_handlers() {
        add_action('wp_ajax_test_x_connection', array($this, 'test_x_connection'));
        add_action('wp_ajax_nopriv_test_x_connection', array($this, 'test_x_connection'));
        
        error_log('X Poster: AJAXハンドラーを登録しました');
    }
    
    /**
     * X（Twitter）への自動投稿
     * 
     * @param int $post_id 投稿ID
     */
    public function auto_post_to_x($post_id) {
        error_log('X Poster: auto_post_to_x が呼び出されました - Post ID: ' . $post_id);
        
        // News Crawlerで作成された投稿かチェック（デバッグ用に一時的に緩和）
        $is_news_crawler_post = get_post_meta($post_id, '_news_crawler_created', true);
        error_log('X Poster: News Crawler投稿チェック - is_news_crawler_post: ' . ($is_news_crawler_post ? 'true' : 'false'));
        
        // デバッグ用：すべての投稿でX投稿をテスト
        if (!$is_news_crawler_post) {
            error_log('X Poster: News Crawlerで作成された投稿ではありませんが、デバッグ用にX投稿を実行します');
            // return; // デバッグ用にコメントアウト
        }
        
        // 基本設定を取得
        $settings = get_option('news_crawler_basic_settings', array());
        error_log('X Poster: 設定取得 - twitter_enabled: ' . (isset($settings['twitter_enabled']) ? ($settings['twitter_enabled'] ? 'true' : 'false') : 'not set'));
        
        // X投稿が有効でない場合は終了
        if (empty($settings['twitter_enabled'])) {
            error_log('X Poster: X投稿が無効のため、処理を終了します');
            return;
        }
        
        // X投稿機能は開発段階の機能のためライセンスチェック不要
        
        // 必要な認証情報が不足している場合は終了
        if (empty($settings['twitter_bearer_token']) || 
            empty($settings['twitter_api_key']) || 
            empty($settings['twitter_api_secret']) || 
            empty($settings['twitter_access_token']) || 
            empty($settings['twitter_access_token_secret'])) {
            error_log('X Poster: 必要な認証情報が不足しています');
            error_log('X Poster: Bearer Token: ' . (empty($settings['twitter_bearer_token']) ? 'empty' : 'set'));
            error_log('X Poster: API Key: ' . (empty($settings['twitter_api_key']) ? 'empty' : 'set'));
            error_log('X Poster: API Secret: ' . (empty($settings['twitter_api_secret']) ? 'empty' : 'set'));
            error_log('X Poster: Access Token: ' . (empty($settings['twitter_access_token']) ? 'empty' : 'set'));
            error_log('X Poster: Access Token Secret: ' . (empty($settings['twitter_access_token_secret']) ? 'empty' : 'set'));
            return;
        }
        
        // 投稿情報を取得
        $post = get_post($post_id);
        if (!$post) {
            return;
        }
        
        // 投稿メッセージを生成
        $message = $this->generate_post_message($post, $settings);
        
        // Xに投稿
        $result = $this->post_to_x($message, $settings);
        
        if ($result['success']) {
            // 投稿成功時のメタデータ保存
            update_post_meta($post_id, '_x_posted', true);
            update_post_meta($post_id, '_x_post_id', $result['tweet_id']);
            update_post_meta($post_id, '_x_posted_at', current_time('mysql'));
            
            error_log('News Crawler X Poster: 投稿成功 - Post ID: ' . $post_id . ', Tweet ID: ' . $result['tweet_id']);
        } else {
            error_log('News Crawler X Poster: 投稿失敗 - Post ID: ' . $post_id . ', Error: ' . $result['error']);
        }
    }
    
    /**
     * 投稿メッセージを生成
     * 
     * @param WP_Post $post 投稿オブジェクト
     * @param array $settings 設定配列
     * @return string 生成されたメッセージ
     */
    private function generate_post_message($post, $settings) {
        $template = isset($settings['twitter_message_template']) ? $settings['twitter_message_template'] : '%TITLE%';
        
        // プレースホルダーを置換
        $message = $this->replace_placeholders($template, $post);
        
        // ハッシュタグを追加
        if (!empty($settings['twitter_hashtags'])) {
            $hashtags = explode(' ', $settings['twitter_hashtags']);
            $hashtag_text = '';
            foreach ($hashtags as $tag) {
                $tag = trim($tag);
                if (!empty($tag)) {
                    $hashtag_text .= ' #' . $tag;
                }
            }
            $message .= $hashtag_text;
        }
        
        // 文字数制限に合わせて調整（URLの長さを考慮）
        $max_length = isset($settings['twitter_max_length']) ? intval($settings['twitter_max_length']) : 280;
        $message = $this->adjust_message_length($message, $max_length);
        
        // 改行を保持したまま返す
        return $message;
    }
    
    /**
     * メッセージの文字数を調整（URLの長さを考慮）
     * 
     * @param string $message メッセージ
     * @param int $max_length 最大文字数
     * @return string 調整後のメッセージ
     */
    private function adjust_message_length($message, $max_length) {
        // URLの長さを予測（短縮URLは約23文字）
        $url_length = 0;
        if (preg_match_all('/https?:\/\/[^\s]+/', $message, $matches)) {
            foreach ($matches[0] as $url) {
                // 実際のURL長と短縮URL長の小さい方を採用
                $actual_length = mb_strlen($url);
                $shortened_length = 23; // Xの短縮URL長
                $url_length += min($actual_length, $shortened_length);
            }
        }
        
        // ハッシュタグの長さを計算
        $hashtag_length = 0;
        if (preg_match_all('/#\w+/', $message, $hashtag_matches)) {
            foreach ($hashtag_matches[0] as $hashtag) {
                $hashtag_length += mb_strlen($hashtag) + 1; // +1はスペース
            }
        }
        
        // テキスト部分の最大長を計算
        $text_max_length = $max_length - $url_length - $hashtag_length;
        
        // テキスト部分を抽出（URLとハッシュタグを除く）
        $text_part = preg_replace('/https?:\/\/[^\s]+/', '', $message);
        $text_part = preg_replace('/#\w+/', '', $text_part);
        $text_part = trim($text_part);
        
        // テキスト部分が制限を超える場合は切り詰め
        if (mb_strlen($text_part) > $text_max_length) {
            $text_part = mb_substr($text_part, 0, max(1, $text_max_length - 3)) . '...';
        }
        
        // URLとハッシュタグを再構築
        $final_message = $text_part;
        
        // URLを追加
        if (preg_match_all('/https?:\/\/[^\s]+/', $message, $url_matches)) {
            foreach ($url_matches[0] as $url) {
                $final_message .= ' ' . $url;
            }
        }
        
        // ハッシュタグを追加
        if (preg_match_all('/#\w+/', $message, $hashtag_matches)) {
            foreach ($hashtag_matches[0] as $hashtag) {
                $final_message .= ' ' . $hashtag;
            }
        }
        
        return trim($final_message);
    }
    
    /**
     * プレースホルダーを置換
     * 
     * @param string $template テンプレート文字列
     * @param WP_Post $post 投稿オブジェクト
     * @return string 置換後の文字列
     */
    private function replace_placeholders($template, $post) {
        // 改行文字を保持
        $template = str_replace(array("\r\n", "\r"), "\n", $template);
        $post_url = get_permalink($post->ID);
        $excerpt = get_the_excerpt($post->ID);
        $raw_excerpt = $post->post_excerpt;
        $content = wp_strip_all_tags($post->post_content);
        $raw_content = $post->post_content;
        $tags = get_the_tags($post->ID);
        $categories = get_the_category($post->ID);
        $author = get_the_author_meta('display_name', $post->post_author);
        $site_name = get_bloginfo('name');
        $featured_image = get_the_post_thumbnail_url($post->ID, 'full');
        
        // タグをハッシュタグ形式に変換
        $hashtags = '';
        if ($tags) {
            foreach ($tags as $tag) {
                $hashtags .= ' #' . $tag->name;
            }
        }
        
        // カテゴリーをハッシュタグ形式に変換
        $hashtag_categories = '';
        if ($categories) {
            foreach ($categories as $category) {
                $hashtag_categories .= ' #' . $category->name;
            }
        }
        
        // タグを通常の文字列に変換
        $tag_names = '';
        if ($tags) {
            $tag_names = implode(', ', wp_list_pluck($tags, 'name'));
        }
        
        // カテゴリーを通常の文字列に変換
        $category_names = '';
        if ($categories) {
            $category_names = implode(', ', wp_list_pluck($categories, 'name'));
        }
        
        // プレースホルダーを置換
        $replacements = array(
            '%TITLE%' => $post->post_title,
            '%URL%' => $post_url,
            '%SURL%' => $post_url, // 短縮URLは通常のURLと同じ
            '%IMG%' => $featured_image ? $featured_image : '',
            '%EXCERPT%' => $excerpt,
            '%RAWEXCERPT%' => $raw_excerpt,
            '%ANNOUNCE%' => $this->get_announce_text($post),
            '%FULLTEXT%' => $content,
            '%RAWTEXT%' => $raw_content,
            '%TAGS%' => $tag_names,
            '%CATS%' => $category_names,
            '%HTAGS%' => $hashtags,
            '%HCATS%' => $hashtag_categories,
            '%AUTHORNAME%' => $author,
            '%SITENAME%' => $site_name
        );
        
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
    
    /**
     * アナウンステキストを取得（<!--more-->タグまで、または最初のN文字）
     * 
     * @param WP_Post $post 投稿オブジェクト
     * @return string アナウンステキスト
     */
    private function get_announce_text($post) {
        // <!--more-->タグがあるかチェック
        if (strpos($post->post_content, '<!--more-->') !== false) {
            $parts = explode('<!--more-->', $post->post_content);
            return wp_strip_all_tags($parts[0]);
        }
        
        // なければ最初の200文字
        $content = wp_strip_all_tags($post->post_content);
        return mb_substr($content, 0, 200);
    }
    
    /**
     * X（Twitter）API v2を使用して投稿
     * 
     * @param string $message 投稿メッセージ
     * @param array $settings 設定配列
     * @return array 結果配列
     */
    private function post_to_x($message, $settings) {
        $endpoint = 'https://api.twitter.com/2/tweets';
        
        // Bearer Tokenを使用した認証
        $headers = array(
            'Authorization: Bearer ' . $settings['twitter_bearer_token'],
            'Content-Type: application/json'
        );
        
        // 投稿データを準備
        $post_data = array(
            'text' => $message
        );
        
        // 直接cURLを使用して投稿
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'WordPress/' . get_bloginfo('version') . '; ' . home_url());
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        
        $body = curl_exec($ch);
        $response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($body === false || !empty($curl_error)) {
            return array(
                'success' => false,
                'error' => 'cURL Error: ' . $curl_error
            );
        }
        $data = json_decode($body, true);
        
        if ($response_code === 201 && isset($data['data']['id'])) {
            return array(
                'success' => true,
                'tweet_id' => $data['data']['id']
            );
        } else {
            $error_message = '不明なエラー';
            if (isset($data['errors'][0]['detail'])) {
                $error_message = $data['errors'][0]['detail'];
            } elseif (isset($data['errors'][0]['message'])) {
                $error_message = $data['errors'][0]['message'];
            } elseif (isset($data['error'])) {
                $error_message = $data['error'];
            }
            return array(
                'success' => false,
                'error' => $error_message
            );
        }
    }
    
    /**
     * OAuth 1.0a署名を生成
     * 
     * @param string $method HTTPメソッド
     * @param string $url エンドポイントURL
     * @param array $params パラメータ
     * @param string $consumer_secret コンシューマーシークレット
     * @param string $token_secret トークンシークレット
     * @return string 署名
     */
    private function generate_oauth_signature($method, $url, $params, $consumer_secret, $token_secret) {
        // パラメータをソート
        ksort($params);
        
        // クエリ文字列を作成
        $query_string = '';
        foreach ($params as $key => $value) {
            $query_string .= rawurlencode($key) . '=' . rawurlencode($value) . '&';
        }
        $query_string = rtrim($query_string, '&');
        
        // 署名ベース文字列を作成
        $signature_base_string = $method . '&' . rawurlencode($url) . '&' . rawurlencode($query_string);
        
        // 署名キーを作成
        $signature_key = rawurlencode($consumer_secret) . '&' . rawurlencode($token_secret);
        
        // HMAC-SHA1で署名を生成
        return base64_encode(hash_hmac('sha1', $signature_base_string, $signature_key, true));
    }
    
    /**
     * X（Twitter）接続テスト
     */
    public function test_x_connection() {
        error_log('X Poster: test_x_connection が呼び出されました');
        
        // nonceチェックを緩和してテスト
        if (!wp_verify_nonce($_POST['nonce'], 'twitter_connection_test_nonce')) {
            error_log('X Poster: nonce検証失敗');
            wp_send_json_error(array('message' => 'セキュリティチェックに失敗しました'));
        }
        
        if (!current_user_can('manage_options')) {
            error_log('X Poster: 権限不足');
            wp_send_json_error(array('message' => '権限がありません'));
        }
        
        $settings = get_option('news_crawler_basic_settings', array());
        
        if (empty($settings['twitter_enabled'])) {
            wp_send_json_error(array('message' => 'X（Twitter）自動シェアが無効になっています'));
        }
        
        // 認証情報の詳細チェック（OAuth 1.0a用）
        $missing_fields = array();
        if (empty($settings['twitter_api_key'])) $missing_fields[] = 'API Key';
        if (empty($settings['twitter_api_secret'])) $missing_fields[] = 'API Secret';
        if (empty($settings['twitter_access_token'])) $missing_fields[] = 'Access Token';
        if (empty($settings['twitter_access_token_secret'])) $missing_fields[] = 'Access Token Secret';
        
        if (!empty($missing_fields)) {
            wp_send_json_error(array('message' => '以下の認証情報が不足しています: ' . implode(', ', $missing_fields)));
        }
        
        try {
            error_log('X Poster: 接続テスト開始 - OAuth 1.0a認証を使用');
            
            // OAuth 1.0a認証を使用して接続をテスト
            $endpoint = 'https://api.twitter.com/1.1/account/verify_credentials.json';
            
            // OAuth 1.0a認証を使用
            $oauth_params = array(
                'oauth_consumer_key' => $settings['twitter_api_key'],
                'oauth_nonce' => wp_generate_password(32, false),
                'oauth_signature_method' => 'HMAC-SHA1',
                'oauth_timestamp' => time(),
                'oauth_token' => $settings['twitter_access_token'],
                'oauth_version' => '1.0'
            );
            
            // 署名を生成
            $signature = $this->generate_oauth_signature('GET', $endpoint, $oauth_params, $settings['twitter_api_secret'], $settings['twitter_access_token_secret']);
            $oauth_params['oauth_signature'] = $signature;
            
            // Authorizationヘッダーを構築
            $auth_header = 'OAuth ';
            $auth_parts = array();
            foreach ($oauth_params as $key => $value) {
                $auth_parts[] = $key . '="' . rawurlencode($value) . '"';
            }
            $auth_header .= implode(', ', $auth_parts);
            
            $headers = array(
                'Authorization: ' . $auth_header,
                'Content-Type: application/json'
            );
            
            // 直接cURLを使用して接続テスト
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'WordPress/' . get_bloginfo('version') . '; ' . home_url());
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
            
            $body = curl_exec($ch);
            $response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            if ($body === false || !empty($curl_error)) {
                error_log('X Poster: cURL Error - ' . $curl_error);
                throw new Exception('リクエストエラー: ' . $curl_error);
            }
            
            $data = json_decode($body, true);
            
            error_log('X Poster: レスポンスコード: ' . $response_code);
            error_log('X Poster: レスポンスボディ: ' . $body);
            
            if ($response_code === 200 && isset($data['screen_name'])) {
                error_log('X Poster: 接続テスト成功 - ユーザー名: @' . $data['screen_name']);
                wp_send_json_success(array(
                    'message' => '接続成功！アカウント: @' . $data['screen_name']
                ));
            } else {
                $error_message = '不明なエラー';
                if (isset($data['errors'][0]['message'])) {
                    $error_message = $data['errors'][0]['message'];
                } elseif (isset($data['error'])) {
                    $error_message = $data['error'];
                }
                
                error_log('X Poster: 接続テスト失敗 - ' . $error_message);
                throw new Exception($error_message . ' (HTTP ' . $response_code . ')');
            }
            
        } catch (Exception $e) {
            error_log('X Poster: 例外発生 - ' . $e->getMessage());
            wp_send_json_error(array('message' => '接続エラー: ' . $e->getMessage()));
        }
    }
    
    
    /**
     * 手動でX投稿をテスト（デバッグ用）
     */
    public function manual_test_x_post($post_id) {
        error_log('X Poster: 手動テスト開始 - Post ID: ' . $post_id);
        
        // News Crawler投稿フラグを強制的に設定
        update_post_meta($post_id, '_news_crawler_created', true);
        
        // X投稿を実行
        $this->auto_post_to_x($post_id);
    }
    
    /**
     * 投稿ステータス変更時の処理
     * 
     * @param string $new_status 新しいステータス
     * @param string $old_status 古いステータス
     * @param WP_Post $post 投稿オブジェクト
     */
    public function handle_post_status_change($new_status, $old_status, $post) {
        error_log('X Poster: handle_post_status_change が呼び出されました - Post ID: ' . $post->ID . ', Status: ' . $old_status . ' -> ' . $new_status);
        
        // 投稿が公開された場合のみX投稿を実行
        if ($new_status === 'publish' && $old_status !== 'publish') {
            error_log('X Poster: 投稿が公開されました - X投稿を実行します');
            $this->auto_post_to_x($post->ID);
        } else {
            error_log('X Poster: 投稿は公開されていません - スキップします');
        }
    }
    
    /**
     * 投稿挿入時の処理
     * 
     * @param int $post_id 投稿ID
     * @param WP_Post $post 投稿オブジェクト
     * @param bool $update 更新かどうか
     */
    public function handle_wp_insert_post($post_id, $post, $update) {
        error_log('X Poster: handle_wp_insert_post が呼び出されました - Post ID: ' . $post_id . ', Update: ' . ($update ? 'true' : 'false') . ', Status: ' . $post->post_status);
        
        // 新規投稿で公開状態の場合のみX投稿を実行
        if (!$update && $post->post_status === 'publish') {
            error_log('X Poster: 新規投稿が公開されました - X投稿を実行します');
            $this->auto_post_to_x($post_id);
        } else {
            error_log('X Poster: 新規投稿ではありません - スキップします');
        }
    }
    
    /**
     * 投稿保存時の処理
     * 
     * @param int $post_id 投稿ID
     * @param WP_Post $post 投稿オブジェクト
     * @param bool $update 更新かどうか
     */
    public function handle_save_post($post_id, $post, $update) {
        error_log('X Poster: handle_save_post が呼び出されました - Post ID: ' . $post_id . ', Update: ' . ($update ? 'true' : 'false') . ', Status: ' . $post->post_status);
        
        // 自動保存やリビジョンの場合はスキップ
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            error_log('X Poster: 自動保存またはリビジョンのためスキップします');
            return;
        }
        
        // 投稿が公開状態の場合のみX投稿を実行
        if ($post->post_status === 'publish') {
            error_log('X Poster: 投稿が保存されました - X投稿を実行します');
            $this->auto_post_to_x($post_id);
        } else {
            error_log('X Poster: 投稿は公開状態ではありません - スキップします');
        }
    }
    
    /**
     * 投稿挿入後の処理
     * 
     * @param int $post_id 投稿ID
     * @param WP_Post $post 投稿オブジェクト
     * @param bool $update 更新かどうか
     * @param WP_Post $post_before 更新前の投稿オブジェクト
     */
    public function handle_wp_after_insert_post($post_id, $post, $update, $post_before) {
        error_log('X Poster: handle_wp_after_insert_post が呼び出されました - Post ID: ' . $post_id . ', Update: ' . ($update ? 'true' : 'false') . ', Status: ' . $post->post_status);
        
        // 投稿が公開状態の場合のみX投稿を実行
        if ($post->post_status === 'publish') {
            error_log('X Poster: 投稿挿入後が公開されました - X投稿を実行します');
            $this->auto_post_to_x($post_id);
        } else {
            error_log('X Poster: 投稿挿入後は公開状態ではありません - スキップします');
        }
    }
    
    /**
     * 投稿更新時の処理
     * 
     * @param int $post_id 投稿ID
     * @param WP_Post $post_after 更新後の投稿オブジェクト
     * @param WP_Post $post_before 更新前の投稿オブジェクト
     */
    public function handle_post_updated($post_id, $post_after, $post_before) {
        error_log('X Poster: handle_post_updated が呼び出されました - Post ID: ' . $post_id . ', Status: ' . $post_after->post_status);
        
        // 投稿が公開状態の場合のみX投稿を実行
        if ($post_after->post_status === 'publish') {
            error_log('X Poster: 投稿更新後が公開されました - X投稿を実行します');
            $this->auto_post_to_x($post_id);
        } else {
            error_log('X Poster: 投稿更新後は公開状態ではありません - スキップします');
        }
    }
    
    /**
     * デバッグ用：wp_insert_postフック
     */
    public function debug_wp_insert_post($post_id, $post, $update) {
        error_log('X Poster: DEBUG wp_insert_post - Post ID: ' . $post_id . ', Update: ' . ($update ? 'true' : 'false') . ', Status: ' . $post->post_status);
    }
    
    /**
     * デバッグ用：save_postフック
     */
    public function debug_save_post($post_id, $post, $update) {
        error_log('X Poster: DEBUG save_post - Post ID: ' . $post_id . ', Update: ' . ($update ? 'true' : 'false') . ', Status: ' . $post->post_status);
    }
    
    /**
     * デバッグ用：publish_postフック
     */
    public function debug_publish_post($post_id) {
        error_log('X Poster: DEBUG publish_post - Post ID: ' . $post_id);
    }
    
    /**
     * デバッグ用：transition_post_statusフック
     */
    public function debug_transition_post_status($new_status, $old_status, $post) {
        error_log('X Poster: DEBUG transition_post_status - Post ID: ' . $post->ID . ', Status: ' . $old_status . ' -> ' . $new_status);
    }
    
    /**
     * デバッグ用：wp_after_insert_postフック
     */
    public function debug_wp_after_insert_post($post_id, $post, $update, $post_before) {
        error_log('X Poster: DEBUG wp_after_insert_post - Post ID: ' . $post_id . ', Update: ' . ($update ? 'true' : 'false') . ', Status: ' . $post->post_status);
    }
    
    /**
     * デバッグ用：post_updatedフック
     */
    public function debug_post_updated($post_id, $post_after, $post_before) {
        error_log('X Poster: DEBUG post_updated - Post ID: ' . $post_id . ', Status: ' . $post_after->post_status);
    }
    
    /**
     * デバッグ用：wp_update_postフック
     */
    public function debug_wp_update_post($post_id, $post, $update) {
        error_log('X Poster: DEBUG wp_update_post - Post ID: ' . $post_id . ', Update: ' . ($update ? 'true' : 'false') . ', Status: ' . $post->post_status);
    }
    
    /**
     * デバッグ用：wp_publish_postフック
     */
    public function debug_wp_publish_post($post_id) {
        error_log('X Poster: DEBUG wp_publish_post - Post ID: ' . $post_id);
    }
    
    /**
     * デバッグ用：wp_insert_postフック（一般）
     */
    public function debug_wp_insert_post_general($post_id, $post, $update) {
        error_log('X Poster: DEBUG GENERAL wp_insert_post - Post ID: ' . $post_id . ', Update: ' . ($update ? 'true' : 'false') . ', Status: ' . $post->post_status);
    }
    
    /**
     * デバッグ用：save_postフック（一般）
     */
    public function debug_save_post_general($post_id, $post, $update) {
        error_log('X Poster: DEBUG GENERAL save_post - Post ID: ' . $post_id . ', Update: ' . ($update ? 'true' : 'false') . ', Status: ' . $post->post_status);
    }
    
    /**
     * デバッグ用：publish_postフック（一般）
     */
    public function debug_publish_post_general($post_id) {
        error_log('X Poster: DEBUG GENERAL publish_post - Post ID: ' . $post_id);
    }
    
    /**
     * デバッグ用：transition_post_statusフック（一般）
     */
    public function debug_transition_post_status_general($new_status, $old_status, $post) {
        error_log('X Poster: DEBUG GENERAL transition_post_status - Post ID: ' . $post->ID . ', Status: ' . $old_status . ' -> ' . $new_status);
    }
    
    /**
     * デバッグ用：wp_insert_postフック（すべて）
     */
    public function debug_wp_insert_post_all($post_id, $post, $update) {
        error_log('X Poster: DEBUG ALL wp_insert_post - Post ID: ' . $post_id . ', Update: ' . ($update ? 'true' : 'false') . ', Status: ' . $post->post_status);
    }
    
    /**
     * デバッグ用：save_postフック（すべて）
     */
    public function debug_save_post_all($post_id, $post, $update) {
        error_log('X Poster: DEBUG ALL save_post - Post ID: ' . $post_id . ', Update: ' . ($update ? 'true' : 'false') . ', Status: ' . $post->post_status);
    }
    
    /**
     * デバッグ用：publish_postフック（すべて）
     */
    public function debug_publish_post_all($post_id) {
        error_log('X Poster: DEBUG ALL publish_post - Post ID: ' . $post_id);
    }
    
    /**
     * デバッグ用：transition_post_statusフック（すべて）
     */
    public function debug_transition_post_status_all($new_status, $old_status, $post) {
        error_log('X Poster: DEBUG ALL transition_post_status - Post ID: ' . $post->ID . ', Status: ' . $old_status . ' -> ' . $new_status);
    }
    
    /**
     * デバッグ用：wp_insert_postフック（ウルトラ）
     */
    public function debug_wp_insert_post_ultra($post_id, $post, $update) {
        error_log('X Poster: DEBUG ULTRA wp_insert_post - Post ID: ' . $post_id . ', Update: ' . ($update ? 'true' : 'false') . ', Status: ' . $post->post_status);
    }
    
    /**
     * デバッグ用：save_postフック（ウルトラ）
     */
    public function debug_save_post_ultra($post_id, $post, $update) {
        error_log('X Poster: DEBUG ULTRA save_post - Post ID: ' . $post_id . ', Update: ' . ($update ? 'true' : 'false') . ', Status: ' . $post->post_status);
    }
    
    /**
     * デバッグ用：publish_postフック（ウルトラ）
     */
    public function debug_publish_post_ultra($post_id) {
        error_log('X Poster: DEBUG ULTRA publish_post - Post ID: ' . $post_id);
    }
    
    /**
     * デバッグ用：transition_post_statusフック（ウルトラ）
     */
    public function debug_transition_post_status_ultra($new_status, $old_status, $post) {
        error_log('X Poster: DEBUG ULTRA transition_post_status - Post ID: ' . $post->ID . ', Status: ' . $old_status . ' -> ' . $new_status);
    }
    
    /**
     * デバッグ用：wp_insert_postフック（スーパー）
     */
    public function debug_wp_insert_post_super($post_id, $post, $update) {
        error_log('X Poster: DEBUG SUPER wp_insert_post - Post ID: ' . $post_id . ', Update: ' . ($update ? 'true' : 'false') . ', Status: ' . $post->post_status);
    }
    
    /**
     * デバッグ用：save_postフック（スーパー）
     */
    public function debug_save_post_super($post_id, $post, $update) {
        error_log('X Poster: DEBUG SUPER save_post - Post ID: ' . $post_id . ', Update: ' . ($update ? 'true' : 'false') . ', Status: ' . $post->post_status);
    }
    
    /**
     * デバッグ用：publish_postフック（スーパー）
     */
    public function debug_publish_post_super($post_id) {
        error_log('X Poster: DEBUG SUPER publish_post - Post ID: ' . $post_id);
    }
    
    /**
     * デバッグ用：transition_post_statusフック（スーパー）
     */
    public function debug_transition_post_status_super($new_status, $old_status, $post) {
        error_log('X Poster: DEBUG SUPER transition_post_status - Post ID: ' . $post->ID . ', Status: ' . $old_status . ' -> ' . $new_status);
    }
}
