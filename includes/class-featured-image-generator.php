<?php
/**
 * Featured Image Generator Class
 * 
 * 投稿の内容からアイキャッチを自動生成する機能
 */

// 直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

class NewsCrawlerFeaturedImageGenerator {
    private $option_name = 'news_crawler_featured_image_settings';
    
    public function __construct() {
        add_action('admin_init', array($this, 'admin_init'));
        
        // 投稿編集画面にアイキャッチ生成メタボックスを追加
        add_action('add_meta_boxes', array($this, 'add_featured_image_meta_box'));
        
        // AJAXハンドラーを追加
        add_action('wp_ajax_generate_featured_image', array($this, 'ajax_generate_featured_image'));
        add_action('wp_ajax_regenerate_featured_image', array($this, 'ajax_regenerate_featured_image'));
        add_action('wp_ajax_check_featured_image_status', array($this, 'ajax_check_featured_image_status'));
    }
    
    public function admin_init() {
        register_setting($this->option_name, $this->option_name, array($this, 'sanitize_settings'));
    }
    
    /**
     * アイキャッチ画像を生成して投稿に設定
     * 
     * @param int $post_id 投稿ID
     * @param string $title 投稿タイトル
     * @param array $keywords キーワード配列
     * @param string $method 生成方法 ('ai', 'template', 'unsplash')
     * @return bool|int 成功時はattachment_id、失敗時はfalse
     */
    public function generate_and_set_featured_image($post_id, $title, $keywords = array(), $method = 'template') {
        // 投稿にカテゴリーが設定されているかチェック
        $current_categories = wp_get_post_categories($post_id);
        if (empty($current_categories)) {
            return array('error' => 'カテゴリーを設定してください');
        }
        
        // 現在のカテゴリーを保存
        $saved_categories = $current_categories;
        
        // ライセンスチェック - AI画像生成などの高度な機能が有効かどうかを確認
        if ($method === 'ai' && class_exists('NewsCrawler_License_Manager')) {
            $license_manager = NewsCrawler_License_Manager::get_instance();
            if (!$license_manager->is_advanced_features_enabled()) {
                error_log('NewsCrawlerFeaturedImageGenerator: ライセンスが無効なため、AI画像生成機能をスキップします');
                // AI画像生成が無効な場合は、テンプレートベースの生成にフォールバック
                $method = 'template';
            }
        }
        
        $settings = get_option($this->option_name, array());
        
        $result = false;
        $original_method = $method;
        
        switch ($method) {
            case 'ai':
                $result = $this->generate_ai_image($post_id, $title, $keywords, $settings);
                // AI画像生成に失敗した場合は何も生成しない
                if (is_array($result) && isset($result['error'])) {
                    error_log('NewsCrawlerFeaturedImageGenerator: AI画像生成に失敗、アイキャッチ画像生成をスキップ - エラー: ' . $result['error']);
                    $result = false;
                }
                break;
            case 'unsplash':
                $result = $this->fetch_unsplash_image($post_id, $title, $keywords, $settings);
                // Unsplash画像取得に失敗した場合は何も生成しない
                if (is_array($result) && isset($result['error'])) {
                    error_log('NewsCrawlerFeaturedImageGenerator: Unsplash画像取得に失敗、アイキャッチ画像生成をスキップ - エラー: ' . $result['error']);
                    $result = false;
                }
                break;
            case 'template':
                $result = $this->generate_template_image($post_id, $title, $keywords, $settings);
                break;
            default:
                // デフォルトはAI画像生成を使用し、失敗時は何も生成しない
                $result = $this->generate_ai_image($post_id, $title, $keywords, $settings);
                if (is_array($result) && isset($result['error'])) {
                    error_log('NewsCrawlerFeaturedImageGenerator: デフォルトAI画像生成に失敗、アイキャッチ画像生成をスキップ - エラー: ' . $result['error']);
                    $result = false;
                }
                break;
        }
        
        // 最終確認：アイキャッチ画像が正しく設定されているかチェック
        if ($result) {
            $final_check = has_post_thumbnail($post_id);
            $final_thumbnail_id = get_post_thumbnail_id($post_id);
            
            if (!$final_check || $final_thumbnail_id != $result) {
                set_post_thumbnail($post_id, $result);
            }
            
            // カテゴリーを復元
            if (!empty($saved_categories)) {
                wp_set_post_categories($post_id, $saved_categories);
                error_log('NewsCrawlerFeaturedImageGenerator: カテゴリーを復元しました。投稿ID: ' . $post_id);
            }
        }
        
        return $result;
    }
    
    /**
     * テンプレートベースの画像生成
     */
    private function generate_template_image($post_id, $title, $keywords, $settings) {
        // GD拡張の確認
        if (!extension_loaded('gd')) {
            return false;
        }
        
        // 基本設定から値を取得
        $basic_settings = get_option('news_crawler_basic_settings', array());
        
        // 画像サイズ設定（デフォルト値を使用）
        $width = 1200;
        $height = 630;
        
        // 画像を作成
        $image = imagecreatetruecolor($width, $height);
        
        // 背景色設定（グラデーション）- デフォルト値を使用
        $bg_color1 = '#4F46E5';
        $bg_color2 = '#7C3AED';
        
        $this->create_gradient_background($image, $width, $height, $bg_color1, $bg_color2);
        
        // テキスト設定 - デフォルト値を使用
        $text_color = '#FFFFFF';
        $font_size = 48;
        
        // 日本語タイトルを生成（キーワード + ニュースまとめ + 日付）
        $display_title = $this->create_japanese_title($title, $keywords);
        
        // 日本語テキストを画像に描画
        $this->draw_japanese_text_on_image($image, $display_title, $font_size, $text_color, $width, $height);
        
        // キーワードタグを追加
        if (!empty($keywords)) {
            $this->draw_keywords_on_image($image, $keywords, $width, $height, $text_color);
        }
        
        // 画像を保存
        $result = $this->save_image_as_attachment($image, $post_id, $title);
        return $result;
    }
    
    /**
     * AI画像生成（OpenAI DALL-E使用）- 強化版通信エラーハンドリング
     */
    private function generate_ai_image($post_id, $title, $keywords, $settings) {
        // 基本設定からAPIキーを取得
        $basic_settings = get_option('news_crawler_basic_settings', array());
        $api_key = isset($basic_settings['openai_api_key']) ? $basic_settings['openai_api_key'] : '';

        if (empty($api_key)) {
            return array('error' => 'OpenAI APIキーが設定されていません。基本設定でAPIキーを設定してください。');
        }

        // APIキーの形式検証
        if (!is_string($api_key) || strlen($api_key) < 20) {
            return array('error' => 'OpenAI APIキーの形式が無効です。正しいAPIキーを設定してください。');
        }

        // プロンプト生成
        $prompt = $this->create_ai_prompt($title, $keywords, $settings);

        // OpenAI DALL-E API呼び出し（強化版指数バックオフ付き）
        $max_retries = 3;
        $base_delay = 2;
        $max_delay = 60;

        for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
            error_log('NewsCrawlerFeaturedImageGenerator: DALL-E API試行回数 ' . $attempt . '/' . $max_retries);

            // リクエスト間の待機（2回目以降）
            if ($attempt > 1) {
                $delay = min($base_delay * pow(2, $attempt - 2), $max_delay);
                $jitter = mt_rand(0, 1000) / 1000;
                $total_delay = $delay + $jitter;

                error_log('NewsCrawlerFeaturedImageGenerator: DALL-E通信エラー対策で ' . round($total_delay, 2) . '秒待機します');
                usleep($total_delay * 1000000);
            }

            // タイムアウトを動的に設定
            $timeout = 60 + ($attempt * 15); // 60秒から開始、試行ごとに15秒延ばす
            $timeout = min($timeout, 180); // 最大180秒

            $response = wp_remote_post('https://api.openai.com/v1/images/generations', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ),
                'body' => json_encode(array(
                    'model' => 'dall-e-3',
                    'prompt' => $prompt,
                    'n' => 1,
                    'size' => '1024x1024',
                    'quality' => 'standard',
                    'response_format' => 'url'
                )),
                'timeout' => $timeout,
                'redirection' => 5,
                'httpversion' => '1.1',
                'user-agent' => 'NewsCrawler/1.0'
            ));

            // ネットワークエラーの詳細な処理
            if (is_wp_error($response)) {
                $error_code = $response->get_error_code();
                $error_message = $response->get_error_message();

                error_log('NewsCrawlerFeaturedImageGenerator: DALL-E試行' . $attempt . ' - ネットワークエラー: ' . $error_code . ' - ' . $error_message);

                // エラーの種類に応じた処理
                if (strpos($error_message, 'timed out') !== false || strpos($error_message, 'timeout') !== false) {
                    $user_message = 'OpenAI DALL-E APIとの通信がタイムアウトしました。インターネット接続を確認してください。';
                } elseif (strpos($error_message, 'could not resolve host') !== false) {
                    $user_message = 'OpenAI DALL-E APIサーバーに接続できません。DNSまたはネットワーク設定を確認してください。';
                } elseif (strpos($error_message, 'SSL') !== false) {
                    $user_message = 'SSL接続エラーが発生しました。証明書の有効性を確認してください。';
                } else {
                    $user_message = 'OpenAI DALL-E APIへの通信に失敗しました: ' . $error_message;
                }

                // ネットワークエラーの場合は再試行
                if ($attempt < $max_retries) {
                    continue;
                }
                return array('error' => $user_message);
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            error_log('NewsCrawlerFeaturedImageGenerator: DALL-E試行' . $attempt . ' - APIレスポンスコード: ' . $response_code);

            // HTTPステータスコードに応じた処理
            if ($response_code === 429) {
                // レート制限エラー
                error_log('NewsCrawlerFeaturedImageGenerator: DALL-Eレート制限エラーが発生しました。試行' . $attempt . '/' . $max_retries);

                // レスポンスヘッダーからリトライ時間を取得
                $retry_after = wp_remote_retrieve_header($response, 'retry-after');
                if ($retry_after) {
                    $wait_time = min(intval($retry_after), $max_delay);
                    error_log('NewsCrawlerFeaturedImageGenerator: DALL-E Retry-Afterヘッダーに従い ' . $wait_time . '秒待機します');
                    sleep($wait_time);
                } elseif ($attempt < $max_retries) {
                    // 指数バックオフ
                    $rate_limit_delay = min($base_delay * pow(2, $attempt), $max_delay);
                    error_log('NewsCrawlerFeaturedImageGenerator: DALL-Eレート制限対策で ' . $rate_limit_delay . '秒待機します');
                    sleep($rate_limit_delay);
                    continue;
                }

                if ($attempt >= $max_retries) {
                    $user_friendly_message = 'OpenAI DALL-E APIのレート制限に達しました。しばらく時間をおいてから再度お試しください。';
                    error_log('NewsCrawlerFeaturedImageGenerator: DALL-Eレート制限エラー - 最大再試行回数に達しました');
                    return array('error' => $user_friendly_message);
                }
            } elseif ($response_code === 401) {
                // 認証エラー
                error_log('NewsCrawlerFeaturedImageGenerator: DALL-E APIキー認証エラー');
                return array('error' => 'OpenAI DALL-E APIキーが無効です。正しいAPIキーを設定してください。');
            } elseif ($response_code === 403) {
                // アクセス拒否
                error_log('NewsCrawlerFeaturedImageGenerator: DALL-E APIアクセス拒否エラー');
                return array('error' => 'OpenAI DALL-E APIへのアクセスが拒否されました。アカウントの状態を確認してください。');
            } elseif ($response_code >= 500 && $response_code < 600) {
                // サーバーエラー
                error_log('NewsCrawlerFeaturedImageGenerator: DALL-Eサーバーエラー: ' . $response_code);

                if ($attempt < $max_retries) {
                    continue;
                }

                $user_message = 'OpenAI DALL-Eサーバーで一時的なエラーが発生しています。しばらく時間をおいてから再度お試しください。';
                return array('error' => $user_message);
            } elseif ($response_code >= 400 && $response_code < 500) {
                // クライアントエラー（429以外）
                error_log('NewsCrawlerFeaturedImageGenerator: DALL-Eクライアントエラー: ' . $response_code);
                break; // 再試行せず終了
            }

            // 成功または4xxエラーの場合はループを抜ける
            if ($response_code === 200 || ($response_code >= 400 && $response_code < 500)) {
                break;
            }
        }

        // 最終的なレスポンスを評価
        if (is_wp_error($response)) {
            return array('error' => 'OpenAI DALL-E API呼び出しエラー: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $json_error = json_last_error_msg();
            error_log('NewsCrawlerFeaturedImageGenerator: DALL-E JSONデコードエラー: ' . $json_error);
            return array('error' => 'OpenAI DALL-E APIからの応答が不正です。JSONデコードエラー: ' . $json_error);
        }

        if (isset($data['data'][0]['url'])) {
            error_log('NewsCrawlerFeaturedImageGenerator: DALL-E画像生成成功 - URL: ' . $data['data'][0]['url']);
            return $this->download_and_attach_image($data['data'][0]['url'], $post_id, $title);
        }

        // APIレスポンスの解析に失敗した場合
        if (isset($data['error'])) {
            $error_message = $data['error']['message'];
            $error_type = isset($data['error']['type']) ? $data['error']['type'] : 'unknown';

            // 課金制限エラーの場合は詳細な情報を提供
            if (strpos($error_message, 'billing') !== false || strpos($error_message, 'limit') !== false || strpos($error_message, 'quota') !== false) {
                error_log('NewsCrawlerFeaturedImageGenerator: DALL-E課金制限エラー: ' . $error_message);
                return array(
                    'error' => 'OpenAI DALL-E APIの課金制限に達しました。',
                    'details' => 'エラー詳細: ' . $error_message,
                    'suggestion' => '以下の点を確認してください：' . "\n" .
                                   '1. OpenAIアカウントの課金設定（https://platform.openai.com/account/billing）' . "\n" .
                                   '2. APIキーが正しく設定されているか' . "\n" .
                                   '3. アカウントに十分なクレジットがあるか' . "\n" .
                                   '4. 月間使用制限に達していないか' . "\n" .
                                   '5. クレジットカードの有効期限が切れていないか'
                );
            }

            return array('error' => 'OpenAI DALL-E APIエラー: ' . $error_message);
        }

        return array('error' => 'OpenAI DALL-E APIからの応答が不正です。しばらく時間をおいてから再試行してください。');
    }
    
    /**
     * Unsplash画像取得 - 強化版通信エラーハンドリング
     */
    private function fetch_unsplash_image($post_id, $title, $keywords, $settings) {
        // 複数の設定からAccess Keyを取得（優先順位付き）
        $access_key = '';

        // 1. 基本設定から取得（最優先）
        $basic_settings = get_option('news_crawler_basic_settings', array());
        if (!empty($basic_settings['unsplash_access_key'])) {
            $access_key = $basic_settings['unsplash_access_key'];
        }

        // 2. フィーチャー画像設定から取得
        if (empty($access_key) && !empty($settings['unsplash_access_key'])) {
            $access_key = $settings['unsplash_access_key'];
        }

        // 3. ジャンル設定から取得
        if (empty($access_key)) {
            $genre_settings = get_option('news_crawler_genre_settings', array());
            foreach ($genre_settings as $setting) {
                if (!empty($setting['unsplash_access_key'])) {
                    $access_key = $setting['unsplash_access_key'];
                    break;
                }
            }
        }

        if (empty($access_key)) {
            return array('error' => 'Unsplash Access Keyが設定されていません。基本設定、フィーチャー画像設定、またはジャンル設定でAccess Keyを設定してください。');
        }

        // Access Keyの形式検証
        if (!is_string($access_key) || strlen($access_key) < 20) {
            return array('error' => 'Unsplash Access Keyの形式が無効です。正しいAccess Keyを設定してください。');
        }

        // 検索キーワード生成
        $search_query = $this->create_unsplash_query($title, $keywords);

        // Unsplash API呼び出し（強化版指数バックオフ付き）
        $max_retries = 3;
        $base_delay = 2;
        $max_delay = 30;

        for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
            error_log('NewsCrawlerFeaturedImageGenerator: Unsplash API試行回数 ' . $attempt . '/' . $max_retries);

            // リクエスト間の待機（2回目以降）
            if ($attempt > 1) {
                $delay = min($base_delay * pow(2, $attempt - 2), $max_delay);
                $jitter = mt_rand(0, 1000) / 1000;
                $total_delay = $delay + $jitter;

                error_log('NewsCrawlerFeaturedImageGenerator: Unsplash通信エラー対策で ' . round($total_delay, 2) . '秒待機します');
                usleep($total_delay * 1000000);
            }

            // タイムアウトを動的に設定
            $timeout = 20 + ($attempt * 10); // 20秒から開始、試行ごとに10秒延ばす
            $timeout = min($timeout, 60); // 最大60秒

            $api_url = 'https://api.unsplash.com/search/photos?' . http_build_query(array(
                'query' => $search_query,
                'per_page' => 1,
                'orientation' => 'landscape',
                'content_filter' => 'high'
            ));

            $response = wp_remote_get($api_url, array(
                'headers' => array(
                    'Authorization' => 'Client-ID ' . $access_key,
                    'User-Agent' => 'NewsCrawler/1.0'
                ),
                'timeout' => $timeout,
                'redirection' => 5,
                'httpversion' => '1.1'
            ));

            // ネットワークエラーの詳細な処理
            if (is_wp_error($response)) {
                $error_code = $response->get_error_code();
                $error_message = $response->get_error_message();

                error_log('NewsCrawlerFeaturedImageGenerator: Unsplash試行' . $attempt . ' - ネットワークエラー: ' . $error_code . ' - ' . $error_message);

                // エラーの種類に応じた処理
                if (strpos($error_message, 'timed out') !== false || strpos($error_message, 'timeout') !== false) {
                    $user_message = 'Unsplash APIとの通信がタイムアウトしました。インターネット接続を確認してください。';
                } elseif (strpos($error_message, 'could not resolve host') !== false) {
                    $user_message = 'Unsplash APIサーバーに接続できません。DNSまたはネットワーク設定を確認してください。';
                } elseif (strpos($error_message, 'SSL') !== false) {
                    $user_message = 'SSL接続エラーが発生しました。証明書の有効性を確認してください。';
                } else {
                    $user_message = 'Unsplash APIへの通信に失敗しました: ' . $error_message;
                }

                // ネットワークエラーの場合は再試行
                if ($attempt < $max_retries) {
                    continue;
                }
                return array('error' => $user_message);
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            error_log('NewsCrawlerFeaturedImageGenerator: Unsplash試行' . $attempt . ' - APIレスポンスコード: ' . $response_code);

            // HTTPステータスコードに応じた処理
            if ($response_code === 429) {
                // レート制限エラー
                error_log('NewsCrawlerFeaturedImageGenerator: Unsplashレート制限エラーが発生しました。試行' . $attempt . '/' . $max_retries);

                // レスポンスヘッダーからリトライ時間を取得
                $retry_after = wp_remote_retrieve_header($response, 'retry-after');
                if ($retry_after) {
                    $wait_time = min(intval($retry_after), $max_delay);
                    error_log('NewsCrawlerFeaturedImageGenerator: Unsplash Retry-Afterヘッダーに従い ' . $wait_time . '秒待機します');
                    sleep($wait_time);
                } elseif ($attempt < $max_retries) {
                    // 指数バックオフ
                    $rate_limit_delay = min($base_delay * pow(2, $attempt), $max_delay);
                    error_log('NewsCrawlerFeaturedImageGenerator: Unsplashレート制限対策で ' . $rate_limit_delay . '秒待機します');
                    sleep($rate_limit_delay);
                    continue;
                }

                if ($attempt >= $max_retries) {
                    $user_friendly_message = 'Unsplash APIのレート制限に達しました。しばらく時間をおいてから再度お試しください。';
                    error_log('NewsCrawlerFeaturedImageGenerator: Unsplashレート制限エラー - 最大再試行回数に達しました');
                    return array('error' => $user_friendly_message);
                }
            } elseif ($response_code === 401) {
                // 認証エラー
                error_log('NewsCrawlerFeaturedImageGenerator: Unsplash APIキー認証エラー');
                return array('error' => 'Unsplash Access Keyが無効です。正しいAccess Keyを設定してください。');
            } elseif ($response_code === 403) {
                // アクセス拒否
                error_log('NewsCrawlerFeaturedImageGenerator: Unsplash APIアクセス拒否エラー');
                return array('error' => 'Unsplash APIへのアクセスが拒否されました。アカウントの状態を確認してください。');
            } elseif ($response_code >= 500 && $response_code < 600) {
                // サーバーエラー
                error_log('NewsCrawlerFeaturedImageGenerator: Unsplashサーバーエラー: ' . $response_code);

                if ($attempt < $max_retries) {
                    continue;
                }

                $user_message = 'Unsplashサーバーで一時的なエラーが発生しています。しばらく時間をおいてから再度お試しください。';
                return array('error' => $user_message);
            } elseif ($response_code >= 400 && $response_code < 500) {
                // クライアントエラー（429以外）
                error_log('NewsCrawlerFeaturedImageGenerator: Unsplashクライアントエラー: ' . $response_code);
                break; // 再試行せず終了
            }

            // 成功または4xxエラーの場合はループを抜ける
            if ($response_code === 200 || ($response_code >= 400 && $response_code < 500)) {
                break;
            }
        }

        // 最終的なレスポンスを評価
        if (is_wp_error($response)) {
            return array('error' => 'Unsplash API呼び出しエラー: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $json_error = json_last_error_msg();
            error_log('NewsCrawlerFeaturedImageGenerator: Unsplash JSONデコードエラー: ' . $json_error);
            return array('error' => 'Unsplash APIからの応答が不正です。JSONデコードエラー: ' . $json_error);
        }

        if (isset($data['results']) && is_array($data['results']) && !empty($data['results'])) {
            if (isset($data['results'][0]['urls']['regular'])) {
                $image_url = $data['results'][0]['urls']['regular'];
                error_log('NewsCrawlerFeaturedImageGenerator: Unsplash画像取得成功 - URL: ' . $image_url);
                return $this->download_and_attach_image($image_url, $post_id, $title);
            }
        }

        // APIレスポンスの解析に失敗した場合
        if (isset($data['errors'])) {
            $error_messages = is_array($data['errors']) ? implode(', ', $data['errors']) : $data['errors'];
            error_log('NewsCrawlerFeaturedImageGenerator: Unsplash APIエラー: ' . $error_messages);
            return array('error' => 'Unsplash APIエラー: ' . $error_messages);
        }

        // 画像が見つからない場合
        error_log('NewsCrawlerFeaturedImageGenerator: Unsplashで画像が見つからない - 検索クエリ: ' . $search_query);
        return array('error' => 'キーワード「' . $search_query . '」に一致する画像が見つかりませんでした。別のキーワードを試してください。');
    }
    
/**
     * グラデーション背景を作成
     */
    private function create_gradient_background($image, $width, $height, $color1, $color2) {
        // 16進数カラーをRGBに変換
        $rgb1 = $this->hex_to_rgb($color1);
        $rgb2 = $this->hex_to_rgb($color2);
        
        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / $height;
            $r = intval($rgb1['r'] * (1 - $ratio) + $rgb2['r'] * $ratio);
            $g = intval($rgb1['g'] * (1 - $ratio) + $rgb2['g'] * $ratio);
            $b = intval($rgb1['b'] * (1 - $ratio) + $rgb2['b'] * $ratio);
            
            $color = imagecolorallocate($image, $r, $g, $b);
            imageline($image, 0, $y, $width, $y, $color);
        }
    }
    
    /**
     * 16進数カラーをRGBに変換
     */
    private function hex_to_rgb($hex) {
        $hex = ltrim($hex, '#');
        return array(
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2))
        );
    }
    
    /**
     * 日本語テキストを画像に描画
     */
    private function draw_japanese_text_on_image($image, $text, $font_size, $text_color, $width, $height) {
        $rgb = $this->hex_to_rgb($text_color);
        $color = imagecolorallocate($image, $rgb['r'], $rgb['g'], $rgb['b']);
        
        // 日本語フォントファイルのパス
        $font_path = $this->get_japanese_font_path();
        
        error_log('Featured Image Generator: Font path returned: ' . ($font_path ?: 'false'));
        error_log('Featured Image Generator: imagettftext function exists: ' . (function_exists('imagettftext') ? 'yes' : 'no'));
        
        if ($font_path) {
            error_log('Featured Image Generator: Testing font: ' . $font_path);
            $font_test_result = $this->test_japanese_font($font_path);
            error_log('Featured Image Generator: Font test result: ' . ($font_test_result ? 'success' : 'failed'));
            
            if ($font_test_result && function_exists('imagettftext')) {
                // 日本語TrueTypeフォントを使用して日本語を直接描画
                error_log('Featured Image Generator: Using TTF font for Japanese text');
                $this->draw_japanese_text_with_ttf($image, $text, $font_size, $color, $width, $height, $font_path);
        } else {
                // 日本語フォントが利用できない場合はエラーメッセージを描画
                error_log('Featured Image Generator: Font test failed or imagettftext not available');
                $error_message = 'Japanese Font Required';
                $this->draw_text_with_builtin($image, $error_message, $color, $width, $height);
                error_log('Featured Image Generator: Japanese font not available. Please install Noto Sans JP font.');
            }
        } else {
            error_log('Featured Image Generator: No font path found');
            $error_message = 'Japanese Font Required';
            $this->draw_text_with_builtin($image, $error_message, $color, $width, $height);
            error_log('Featured Image Generator: Japanese font not available. Please install Noto Sans JP font.');
        }
    }
    
    /**
     * 日本語フォントのテスト
     */
    private function test_japanese_font($font_path) {
        error_log('Featured Image Generator: Testing font: ' . $font_path);
        
        if (!file_exists($font_path)) {
            error_log('Featured Image Generator: Font file does not exist: ' . $font_path);
            return false;
        }
        
        if (!is_readable($font_path)) {
            error_log('Featured Image Generator: Font file is not readable: ' . $font_path);
            return false;
        }
        
        try {
            // 簡単な日本語文字でテスト
            error_log('Featured Image Generator: Testing with character: あ');
            $test_bbox = imagettfbbox(20, 0, $font_path, 'あ');
            
            if ($test_bbox === false) {
                error_log('Featured Image Generator: imagettfbbox returned false');
                return false;
            }
            
            if (!is_array($test_bbox)) {
                error_log('Featured Image Generator: imagettfbbox returned non-array: ' . gettype($test_bbox));
                return false;
            }
            
            error_log('Featured Image Generator: Font test successful, bbox: ' . implode(', ', $test_bbox));
            return true;
            
        } catch (Exception $e) {
            error_log('Featured Image Generator: Exception during font test: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 画像にテキストを描画（旧版・互換性のため残す）
     */
    private function draw_text_on_image($image, $text, $font_size, $text_color, $width, $height) {
        $rgb = $this->hex_to_rgb($text_color);
        $color = imagecolorallocate($image, $rgb['r'], $rgb['g'], $rgb['b']);
        
        // フォントファイルのパス（システムフォントまたはWebフォント）
        $font_path = $this->get_font_path();
        
        if ($font_path && function_exists('imagettftext')) {
            // TrueTypeフォントを使用
            $this->draw_text_with_ttf($image, $text, $font_size, $color, $width, $height, $font_path);
        } else {
            // 内蔵フォントを使用
            $this->draw_text_with_builtin($image, $text, $color, $width, $height);
        }
    }
    
    /**
     * 日本語TTFフォントでテキスト描画
     */
    private function draw_japanese_text_with_ttf($image, $text, $font_size, $color, $width, $height, $font_path) {
        // 拡大倍率をデフォルト値で設定
        $scale_factor = 3;
        
        // フォントサイズを調整（日本語用に大きめに）
        $adjusted_font_size = max($font_size, 36) * ($scale_factor / 3);
        
        error_log("Featured Image Generator: Drawing Japanese text: {$text}");
        error_log("Featured Image Generator: Font size: {$adjusted_font_size}, Font path: {$font_path}");
        
        // 長いテキストを複数行に分割（日本語用）
        $max_chars_per_line = intval(12 / ($scale_factor / 3)); // 日本語は文字数を少なめに
        $lines = $this->split_japanese_text_into_lines($text, $max_chars_per_line);
        
        error_log("Featured Image Generator: Split into " . count($lines) . " lines: " . implode(' | ', $lines));
        
        // 各行の高さを計算
        $line_heights = array();
        $total_height = 0;
        $line_spacing = 30; // 行間
        
        foreach ($lines as $line) {
            try {
                $bbox = imagettfbbox($adjusted_font_size, 0, $font_path, $line);
                if ($bbox === false) {
                    error_log("Featured Image Generator: Failed to get bbox for line: {$line}");
                    $line_height = $adjusted_font_size * 1.2; // フォールバック
                } else {
                    $line_height = abs($bbox[1] - $bbox[7]);
                }
            } catch (Exception $e) {
                error_log("Featured Image Generator: Exception getting bbox: " . $e->getMessage());
                $line_height = $adjusted_font_size * 1.2; // フォールバック
            }
            
            $line_heights[] = $line_height;
            $total_height += $line_height + $line_spacing;
        }
        $total_height -= $line_spacing; // 最後の行間を削除
        
        // 開始Y位置を計算（中央揃え）
        $start_y = ($height - $total_height) / 2;
        
        // 影色
        $shadow_color = imagecolorallocatealpha($image, 0, 0, 0, 70);
        
        foreach ($lines as $index => $line) {
            try {
                // テキストの境界ボックスを取得
                $bbox = imagettfbbox($adjusted_font_size, 0, $font_path, $line);
                if ($bbox === false) {
                    error_log("Featured Image Generator: Failed to get bbox for rendering line: {$line}");
                    continue;
                }
                
                $text_width = $bbox[4] - $bbox[0];
                $line_height = $line_heights[$index];
                
                // 中央配置の計算
                $x = ($width - $text_width) / 2;
                $y = $start_y + ($index * ($line_height + $line_spacing)) + $line_height;
                
                error_log("Featured Image Generator: Drawing line {$index}: '{$line}' at ({$x}, {$y})");
                
                // 影効果（複数の影で強調）
                for ($sx = 1; $sx <= 3; $sx++) {
                    for ($sy = 1; $sy <= 3; $sy++) {
                        imagettftext($image, $adjusted_font_size, 0, $x + $sx, $y + $sy, $shadow_color, $font_path, $line);
                    }
                }
                
                // メインテキスト（太く見せるために複数回描画）
                for ($dx = 0; $dx <= 1; $dx++) {
                    for ($dy = 0; $dy <= 1; $dy++) {
                        imagettftext($image, $adjusted_font_size, 0, $x + $dx, $y + $dy, $color, $font_path, $line);
                    }
                }
                
            } catch (Exception $e) {
                error_log("Featured Image Generator: Exception drawing line: " . $e->getMessage());
            }
        }
        
        error_log("Featured Image Generator: Japanese text drawing completed");
    }
    
    /**
     * TTFフォントでテキスト描画（旧版・互換性のため残す）
     */
    private function draw_text_with_ttf($image, $text, $font_size, $color, $width, $height, $font_path) {
        // テキストの境界ボックスを取得
        $bbox = imagettfbbox($font_size, 0, $font_path, $text);
        $text_width = $bbox[4] - $bbox[0];
        $text_height = $bbox[1] - $bbox[7];
        
        // 中央配置の計算
        $x = ($width - $text_width) / 2;
        $y = ($height - $text_height) / 2 + $text_height;
        
        // 影効果
        $shadow_color = imagecolorallocatealpha($image, 0, 0, 0, 50);
        imagettftext($image, $font_size, 0, $x + 2, $y + 2, $shadow_color, $font_path, $text);
        
        // メインテキスト
        imagettftext($image, $font_size, 0, $x, $y, $color, $font_path, $text);
    }
    
    /**
     * 内蔵フォントでテキスト描画（文字化け防止版）
     */
    private function draw_text_with_builtin($image, $text, $color, $width, $height) {
        $font_size = 5; // 内蔵フォントサイズ（1-5）
        
        // 設定から拡大倍率を取得（基本設定を優先）
        $scale_factor = 4;
        
        // テキストが日本語を含む場合はローマ字に変換
        if (preg_match('/[^\x00-\x7F]/', $text)) {
            $display_text = $this->convert_japanese_to_clean_romaji($text);
        } else {
            $display_text = $text;
        }
        
        // 長いテキストを複数行に分割（拡大を考慮して文字数を調整）
        $max_chars_per_line = intval(16 / ($scale_factor / 3)); // 拡大倍率に応じて調整
        $lines = $this->split_text_into_lines($display_text, $max_chars_per_line);
        
        $base_char_width = imagefontwidth($font_size);
        $base_char_height = imagefontheight($font_size);
        $scaled_char_width = $base_char_width * $scale_factor;
        $scaled_char_height = $base_char_height * $scale_factor;
        
        $line_height = $scaled_char_height + 30; // 行間を追加
        $total_height = count($lines) * $line_height;
        
        // 開始Y位置を計算（中央揃え）
        $start_y = ($height - $total_height) / 2;
        
        foreach ($lines as $index => $line) {
            // ASCII文字のみであることを確認
            $safe_line = preg_replace('/[^\x00-\x7F]/', '?', $line);
            
            $text_width = $scaled_char_width * strlen($safe_line);
        $x = ($width - $text_width) / 2;
            $y = $start_y + ($index * $line_height);
            
            // 拡大描画のために文字を1文字ずつ処理
            $this->draw_scaled_text($image, $safe_line, $font_size, $color, $x, $y, $scale_factor);
        }
    }
    
    /**
     * 文字を拡大して描画
     */
    private function draw_scaled_text($image, $text, $font_size, $color, $start_x, $start_y, $scale_factor) {
        $base_char_width = imagefontwidth($font_size);
        $base_char_height = imagefontheight($font_size);
        
        // 影色
        $shadow_color = imagecolorallocate($image, 0, 0, 0);
        
        for ($i = 0; $i < strlen($text); $i++) {
            $char = $text[$i];
            $char_x = $start_x + ($i * $base_char_width * $scale_factor);
            
            // 一時的な小さい画像を作成して文字を描画
            $temp_img = imagecreatetruecolor($base_char_width, $base_char_height);
            $temp_bg = imagecolorallocate($temp_img, 255, 255, 255);
            $temp_text_color = imagecolorallocate($temp_img, 0, 0, 0);
            imagefill($temp_img, 0, 0, $temp_bg);
            imagestring($temp_img, $font_size, 0, 0, $char, $temp_text_color);
            
            // 拡大してメイン画像にコピー（影）
            imagecopyresized(
                $image, $temp_img,
                $char_x + ($scale_factor * 2), $start_y + ($scale_factor * 2), // 影の位置
                0, 0,
                $base_char_width * $scale_factor, $base_char_height * $scale_factor,
                $base_char_width, $base_char_height
            );
            
            // 影を黒に変換
            $this->replace_color($image, 
                $char_x + ($scale_factor * 2), $start_y + ($scale_factor * 2),
                $base_char_width * $scale_factor, $base_char_height * $scale_factor,
                imagecolorallocate($image, 255, 255, 255), $shadow_color
            );
            
            // 拡大してメイン画像にコピー（メインテキスト）
            imagecopyresized(
                $image, $temp_img,
                $char_x, $start_y,
                0, 0,
                $base_char_width * $scale_factor, $base_char_height * $scale_factor,
                $base_char_width, $base_char_height
            );
            
            // メインテキストの色を変更
            $this->replace_color($image, 
                $char_x, $start_y,
                $base_char_width * $scale_factor, $base_char_height * $scale_factor,
                imagecolorallocate($image, 255, 255, 255), $color
            );
            
            imagedestroy($temp_img);
        }
    }
    
    /**
     * 日本語をローマ字に変換
     */
    private function convert_japanese_to_romaji($japanese_text) {
        // ひらがな・カタカナ・漢字をローマ字に変換するマッピング
        $conversion_map = array(
            // 基本的な単語
            'ニュース' => 'NEWS',
            'まとめ' => 'MATOME',
            '政治' => 'SEIJI',
            '経済' => 'KEIZAI',
            '社会' => 'SHAKAI',
            '国際' => 'KOKUSAI',
            '地域' => 'CHIIKI',
            'スポーツ' => 'SPORTS',
            '芸能' => 'GEINOU',
            'テック' => 'TECH',
            'ビジネス' => 'BUSINESS',
            '最新' => 'SAISHIN',
            
            // 政党名
            '自民党' => 'JIMINTO',
            '公明党' => 'KOMEITO',
            '参政党' => 'SANSEITO',
            '国民民主党' => 'KOKUMIN',
            
            // 月日
            '1月' => '1-gatsu', '2月' => '2-gatsu', '3月' => '3-gatsu',
            '4月' => '4-gatsu', '5月' => '5-gatsu', '6月' => '6-gatsu',
            '7月' => '7-gatsu', '8月' => '8-gatsu', '9月' => '9-gatsu',
            '10月' => '10-gatsu', '11月' => '11-gatsu', '12月' => '12-gatsu',
            
            // 日付
            '日' => '-nichi',
            
            // 記号
            '・' => ' ',
            '：' => ':',
            '、' => ', ',
            '。' => '.',
            
            // 数字（全角→半角）
            '０' => '0', '１' => '1', '２' => '2', '３' => '3', '４' => '4',
            '５' => '5', '６' => '6', '７' => '7', '８' => '8', '９' => '9'
        );
        
        $romaji_text = $japanese_text;
        
        // 変換マップを適用
        foreach ($conversion_map as $japanese => $romaji) {
            $romaji_text = str_replace($japanese, $romaji, $romaji_text);
        }
        
        // 残った日本語文字を削除し、英数字と記号のみ残す
        $romaji_text = preg_replace('/[^\x00-\x7F]/', ' ', $romaji_text);
        
        // 複数のスペースを1つにまとめる
        $romaji_text = preg_replace('/\s+/', ' ', $romaji_text);
        
        // 前後のスペースを削除
        $romaji_text = trim($romaji_text);
        
        // 空の場合はデフォルトテキスト
        if (empty($romaji_text)) {
            $romaji_text = 'NEWS SUMMARY';
        }
        
        return strtoupper($romaji_text);
    }
    
    /**
     * 日本語を確実に表示できるローマ字に変換（文字化け防止）
     */
    private function convert_japanese_to_clean_romaji($japanese_text) {
        // より包括的な変換マッピング
        $conversion_map = array(
            // 基本的な単語
            'ニュース' => 'NEWS',
            'まとめ' => 'MATOME',
            '政治' => 'SEIJI',
            '経済' => 'KEIZAI',
            '社会' => 'SHAKAI',
            '国際' => 'KOKUSAI',
            '地域' => 'CHIIKI',
            'スポーツ' => 'SPORTS',
            '芸能' => 'GEINOU',
            'エンタメ' => 'ENTAME',
            'テック' => 'TECH',
            'ビジネス' => 'BUSINESS',
            '最新' => 'SAISHIN',
            '健康' => 'KENKOU',
            '教育' => 'KYOUIKU',
            '環境' => 'KANKYOU',
            
            // 政党名・組織名
            '自民党' => 'JIMINTO',
            '公明党' => 'KOMEITO',
            '参政党' => 'SANSEITO',
            '国民民主党' => 'KOKUMIN',
            
            // よく使われる単語
            '新政策' => 'SHINSEISAKU',
            '市場' => 'SHIJOU',
            '予測' => 'YOSOKU',
            '技術' => 'GIJUTSU',
            'プロ野球' => 'PROYAKYU',
            '開幕' => 'KAIMAKU',
            '活性化' => 'KASSEIKA',
            '外交' => 'GAIKOU',
            '政策' => 'SEISAKU',
            '映画' => 'EIGA',
            
            // 月日
            '1月' => '1-GATSU', '2月' => '2-GATSU', '3月' => '3-GATSU',
            '4月' => '4-GATSU', '5月' => '5-GATSU', '6月' => '6-GATSU',
            '7月' => '7-GATSU', '8月' => '8-GATSU', '9月' => '9-GATSU',
            '10月' => '10-GATSU', '11月' => '11-GATSU', '12月' => '12-GATSU',
            
            // 日付
            '日' => '-NICHI',
            
            // 記号
            '・' => ' ',
            '：' => ':',
            '、' => ', ',
            '。' => '.',
            
            // 数字（全角→半角）
            '０' => '0', '１' => '1', '２' => '2', '３' => '3', '４' => '4',
            '５' => '5', '６' => '6', '７' => '7', '８' => '8', '９' => '9'
        );
        
        $romaji_text = $japanese_text;
        
        // 変換マップを適用
        foreach ($conversion_map as $japanese => $romaji) {
            $romaji_text = str_replace($japanese, $romaji, $romaji_text);
        }
        
        // 残った日本語文字を削除し、英数字と記号のみ残す
        $romaji_text = preg_replace('/[^\x00-\x7F]/', ' ', $romaji_text);
        
        // 複数のスペースを1つにまとめる
        $romaji_text = preg_replace('/\s+/', ' ', $romaji_text);
        
        // 前後のスペースを削除
        $romaji_text = trim($romaji_text);
        
        // 空の場合はデフォルトテキスト
        if (empty($romaji_text)) {
            $romaji_text = 'NEWS SUMMARY';
        }
        
        return $romaji_text;
    }
    
    /**
     * 日本語タイトルを英語に変換（旧版・互換性のため残す）
     */
    private function convert_to_english($japanese_text) {
        // 基本的な変換マップ
        $conversion_map = array(
            '政治' => 'Politics',
            '経済' => 'Economy', 
            'ニュース' => 'News',
            'まとめ' => 'Summary',
            '自民党' => 'LDP',
            '公明党' => 'Komeito',
            '参政党' => 'Sanseito',
            '国民民主党' => 'DPFP',
            'チームみらい' => 'Team Mirai',
            '年' => '',
            '月' => '/',
            '日' => '',
            '：' => ':',
            '、' => ', ',
            '。' => '.'
        );
        
        $english_text = $japanese_text;
        
        // 変換マップを適用
        foreach ($conversion_map as $japanese => $english) {
            $english_text = str_replace($japanese, $english, $english_text);
        }
        
        // 日付パターンを変換 (例: 2025年8月25日 -> 2025/8/25)
        $english_text = preg_replace('/(\d{4})年(\d{1,2})月(\d{1,2})日/', '$1/$2/$3', $english_text);
        
        // 残った日本語文字を削除し、英数字と記号のみ残す
        $english_text = preg_replace('/[^\x00-\x7F]/', ' ', $english_text);
        
        // 複数のスペースを1つにまとめる
        $english_text = preg_replace('/\s+/', ' ', $english_text);
        
        // 前後のスペースを削除
        $english_text = trim($english_text);
        
        // 空の場合はデフォルトテキスト
        if (empty($english_text)) {
            $english_text = 'News Summary';
        }
        
        return $english_text;
    }
    
    /**
     * テキストを複数行に分割（汎用版）
     */
    private function split_text_into_lines($text, $max_chars_per_line) {
        $words = explode(' ', $text);
        $lines = array();
        $current_line = '';
        
        foreach ($words as $word) {
            if (strlen($current_line . ' ' . $word) <= $max_chars_per_line) {
                $current_line .= ($current_line ? ' ' : '') . $word;
            } else {
                if (!empty($current_line)) {
                    $lines[] = $current_line;
                }
                $current_line = $word;
            }
        }
        
        if (!empty($current_line)) {
            $lines[] = $current_line;
        }
        
        return empty($lines) ? array($text) : $lines;
    }
    
    /**
     * 日本語テキストを複数行に分割
     */
    private function split_japanese_text_into_lines($text, $max_chars_per_line) {
        $lines = array();
        $current_line = '';
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        foreach ($chars as $char) {
            if (mb_strlen($current_line . $char) <= $max_chars_per_line) {
                $current_line .= $char;
            } else {
                if (!empty($current_line)) {
                    $lines[] = $current_line;
                }
                $current_line = $char;
            }
        }
        
        if (!empty($current_line)) {
            $lines[] = $current_line;
        }
        
        return empty($lines) ? array($text) : $lines;
    }
    
    /**
     * 日本語テキストのフォールバック描画（フォントがない場合）
     */
    private function draw_japanese_text_fallback($image, $text, $color, $width, $height) {
        // 拡大倍率をデフォルト値で設定
        $scale_factor = 4;
        
        // 日本語を読みやすい形式に変換
        $display_text = $this->format_japanese_for_display($text);
        
        // 長いテキストを複数行に分割
        $max_chars_per_line = intval(16 / ($scale_factor / 3));
        $lines = $this->split_japanese_text_into_lines($display_text, $max_chars_per_line);
        
        $font_size = 5; // 内蔵フォントサイズ（1-5）
        $base_char_width = imagefontwidth($font_size) * 2; // 日本語用に幅を調整
        $base_char_height = imagefontheight($font_size);
        $scaled_char_width = $base_char_width * $scale_factor;
        $scaled_char_height = $base_char_height * $scale_factor;
        
        $line_height = $scaled_char_height + 30; // 行間を追加
        $total_height = count($lines) * $line_height;
        
        // 開始Y位置を計算（中央揃え）
        $start_y = ($height - $total_height) / 2;
        
        foreach ($lines as $index => $line) {
            $text_width = $scaled_char_width * mb_strlen($line);
            $x = ($width - $text_width) / 2;
            $y = $start_y + ($index * $line_height);
            
            // 拡大描画のために文字を1文字ずつ処理
            $this->draw_scaled_japanese_text($image, $line, $font_size, $color, $x, $y, $scale_factor);
        }
    }
    
    /**
     * 日本語を表示用にフォーマット
     */
    private function format_japanese_for_display($text) {
        // 読みやすくするための調整
        $formatted = $text;
        
        // スペースを適切に配置
        $formatted = str_replace('ニュースまとめ', 'ニュース まとめ', $formatted);
        
        return $formatted;
    }
    
    /**
     * 拡大された日本語文字を描画
     */
    private function draw_scaled_japanese_text($image, $text, $font_size, $color, $start_x, $start_y, $scale_factor) {
        $base_char_width = imagefontwidth($font_size) * 2; // 日本語用に幅を調整
        $base_char_height = imagefontheight($font_size);
        
        // 影色
        $shadow_color = imagecolorallocate($image, 0, 0, 0);
        
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        for ($i = 0; $i < count($chars); $i++) {
            $char = $chars[$i];
            $char_x = $start_x + ($i * $base_char_width * $scale_factor);
            
            // 日本語文字を簡略化して表示
            $display_char = $this->simplify_japanese_char($char);
            
            // 一時的な小さい画像を作成して文字を描画
            $temp_img = imagecreatetruecolor($base_char_width, $base_char_height);
            $temp_bg = imagecolorallocate($temp_img, 255, 255, 255);
            $temp_text_color = imagecolorallocate($temp_img, 0, 0, 0);
            imagefill($temp_img, 0, 0, $temp_bg);
            imagestring($temp_img, $font_size, 0, 0, $display_char, $temp_text_color);
            
            // 拡大してメイン画像にコピー（影）
            imagecopyresized(
                $image, $temp_img,
                $char_x + ($scale_factor * 2), $start_y + ($scale_factor * 2), // 影の位置
                0, 0,
                $base_char_width * $scale_factor, $base_char_height * $scale_factor,
                $base_char_width, $base_char_height
            );
            
            // 影を黒に変換
            $this->replace_color($image, 
                $char_x + ($scale_factor * 2), $start_y + ($scale_factor * 2),
                $base_char_width * $scale_factor, $base_char_height * $scale_factor,
                imagecolorallocate($image, 255, 255, 255), $shadow_color
            );
            
            // 拡大してメイン画像にコピー（メインテキスト）
            imagecopyresized(
                $image, $temp_img,
                $char_x, $start_y,
                0, 0,
                $base_char_width * $scale_factor, $base_char_height * $scale_factor,
                $base_char_width, $base_char_height
            );
            
            // メインテキストの色を変更
            $this->replace_color($image, 
                $char_x, $start_y,
                $base_char_width * $scale_factor, $base_char_height * $scale_factor,
                imagecolorallocate($image, 255, 255, 255), $color
            );
            
            imagedestroy($temp_img);
        }
    }
    
    /**
     * 日本語文字を簡略化（フォールバック用）
     */
    private function simplify_japanese_char($char) {
        // 日本語文字を英数字に簡略化するマッピング
        $char_map = array(
            // ひらがな
            'あ' => 'a', 'い' => 'i', 'う' => 'u', 'え' => 'e', 'お' => 'o',
            'か' => 'ka', 'き' => 'ki', 'く' => 'ku', 'け' => 'ke', 'こ' => 'ko',
            'さ' => 'sa', 'し' => 'si', 'す' => 'su', 'せ' => 'se', 'そ' => 'so',
            'た' => 'ta', 'ち' => 'ti', 'つ' => 'tu', 'て' => 'te', 'と' => 'to',
            'な' => 'na', 'に' => 'ni', 'ぬ' => 'nu', 'ね' => 'ne', 'の' => 'no',
            'は' => 'ha', 'ひ' => 'hi', 'ふ' => 'hu', 'へ' => 'he', 'ほ' => 'ho',
            'ま' => 'ma', 'み' => 'mi', 'む' => 'mu', 'め' => 'me', 'も' => 'mo',
            'や' => 'ya', 'ゆ' => 'yu', 'よ' => 'yo',
            'ら' => 'ra', 'り' => 'ri', 'る' => 'ru', 'れ' => 're', 'ろ' => 'ro',
            'わ' => 'wa', 'ん' => 'n',
            
            // カタカナ
            'ア' => 'A', 'イ' => 'I', 'ウ' => 'U', 'エ' => 'E', 'オ' => 'O',
            'カ' => 'KA', 'キ' => 'KI', 'ク' => 'KU', 'ケ' => 'KE', 'コ' => 'KO',
            'サ' => 'SA', 'シ' => 'SI', 'ス' => 'SU', 'セ' => 'SE', 'ソ' => 'SO',
            'タ' => 'TA', 'チ' => 'TI', 'ツ' => 'TU', 'テ' => 'TE', 'ト' => 'TO',
            'ナ' => 'NA', 'ニ' => 'NI', 'ヌ' => 'NU', 'ネ' => 'NE', 'ノ' => 'NO',
            'ハ' => 'HA', 'ヒ' => 'HI', 'フ' => 'HU', 'ヘ' => 'HE', 'ホ' => 'HO',
            'マ' => 'MA', 'ミ' => 'MI', 'ム' => 'MU', 'メ' => 'ME', 'モ' => 'MO',
            'ヤ' => 'YA', 'ユ' => 'YU', 'ヨ' => 'YO',
            'ラ' => 'RA', 'リ' => 'RI', 'ル' => 'RU', 'レ' => 'RE', 'ロ' => 'RO',
            'ワ' => 'WA', 'ン' => 'N',
            
            // 漢字（よく使われるもの）
            '政' => 'SEI', '治' => 'JI', '経' => 'KEI', '済' => 'ZAI',
            '社' => 'SHA', '会' => 'KAI', '国' => 'KOKU', '際' => 'SAI',
            '地' => 'CHI', '域' => 'IKI', '最' => 'SAI', '新' => 'SHIN',
            'ニ' => 'NI', 'ュ' => 'YU', 'ー' => '-', 'ス' => 'SU',
            'ま' => 'MA', 'と' => 'TO', 'め' => 'ME',
            '月' => 'GATSU', '日' => 'NICHI',
            
            // 数字
            '１' => '1', '２' => '2', '３' => '3', '４' => '4', '５' => '5',
            '６' => '6', '７' => '7', '８' => '8', '９' => '9', '０' => '0',
            
            // 記号
            '・' => '*', '：' => ':', '、' => ',', '。' => '.'
        );
        
        return isset($char_map[$char]) ? $char_map[$char] : $char;
    }
    
    /**
     * 英語テキストを複数行に分割（旧版・互換性のため残す）
     */
    private function split_english_text_into_lines($text, $max_chars_per_line) {
        $words = explode(' ', $text);
        $lines = array();
        $current_line = '';
        
        foreach ($words as $word) {
            if (strlen($current_line . ' ' . $word) <= $max_chars_per_line) {
                $current_line .= ($current_line ? ' ' : '') . $word;
            } else {
                if (!empty($current_line)) {
                    $lines[] = $current_line;
                }
                $current_line = $word;
            }
        }
        
        if (!empty($current_line)) {
            $lines[] = $current_line;
        }
        
        return empty($lines) ? array($text) : $lines;
    }
    

    
    /**
     * キーワードタグを描画（日本語対応）
     */
    private function draw_keywords_on_image($image, $keywords, $width, $height, $text_color) {
        $rgb = $this->hex_to_rgb($text_color);
        $tag_color = imagecolorallocatealpha($image, $rgb['r'], $rgb['g'], $rgb['b'], 30);
        $text_color_obj = imagecolorallocate($image, $rgb['r'], $rgb['g'], $rgb['b']);
        
        $tag_y = $height - 80;
        $tag_x = 50;
        
        foreach (array_slice($keywords, 0, 3) as $keyword) {
            // キーワードをローマ字に変換
            $romaji_keyword = $this->convert_japanese_to_romaji($keyword);
            
            $tag_width = strlen($romaji_keyword) * 8 + 20; // ローマ字用に調整
            $tag_height = 30;
            
            // タグ背景（角丸風）
            imagefilledrectangle($image, $tag_x, $tag_y, $tag_x + $tag_width, $tag_y + $tag_height, $tag_color);
            
            // タグテキスト（太く表示）
            $font_size = 3;
            for ($dx = 0; $dx <= 1; $dx++) {
                for ($dy = 0; $dy <= 1; $dy++) {
                    imagestring($image, $font_size, $tag_x + 10 + $dx, $tag_y + 8 + $dy, $romaji_keyword, $text_color_obj);
                }
            }
            
            $tag_x += $tag_width + 15;
        }
    }
    
    /**
     * 日本語フォントファイルのパスを取得
     */
    private function get_japanese_font_path() {
        error_log('Featured Image Generator: Starting font path search...');
        
        // 優先順位1: macOSシステムフォント（信頼性が高い）
        $macos_fonts = array(
            '/System/Library/Fonts/PingFang.ttc',
            '/System/Library/Fonts/Hiragino Sans GB.ttc',
            '/System/Library/Fonts/ヒラギノ角ゴシック W3.ttc',
            '/System/Library/Fonts/Supplemental/Hiragino Sans GB.ttc',
            '/Library/Fonts/ヒラギノ角ゴ ProN W3.otf',
            '/System/Library/Fonts/STHeiti Medium.ttc',
            '/System/Library/Fonts/STHeiti Light.ttc',
            '/System/Library/Fonts/AquaKana.ttc',
            '/System/Library/Fonts/Osaka.ttf',
            '/Library/Fonts/Arial Unicode MS.ttf'
        );
        
        error_log('Featured Image Generator: Checking macOS system fonts...');
        foreach ($macos_fonts as $font) {
            error_log('Featured Image Generator: Checking font: ' . $font);
            if (file_exists($font)) {
                error_log('Featured Image Generator: Found macOS system font: ' . $font);
                if (is_readable($font)) {
                    error_log('Featured Image Generator: Font file is readable: ' . $font);
                    $file_size = filesize($font);
                    error_log('Featured Image Generator: Font file size: ' . $file_size . ' bytes');
                    
                    // フォントの動作確認（必須）
                    if (function_exists('imagettfbbox')) {
                        $test_bbox = imagettfbbox(20, 0, $font, 'テスト');
                        if ($test_bbox !== false) {
                            error_log('Featured Image Generator: Font test successful: ' . $font);
                            return $font;
                        } else {
                            error_log('Featured Image Generator: Font test failed (bbox): ' . $font);
                        }
                    } else {
                        error_log('Featured Image Generator: FreeType functions not available');
                        // FreeTypeが利用できない場合は、システムフォントのみ使用
                        if (strpos($font, '/System/Library/Fonts/') === 0 || strpos($font, '/Library/Fonts/') === 0) {
                            error_log('Featured Image Generator: Using system font without FreeType test: ' . $font);
                            return $font;
                        }
                    }
                } else {
                    error_log('Featured Image Generator: Font file not readable: ' . $font);
                }
            } else {
                error_log('Featured Image Generator: Font not found: ' . $font);
            }
        }
        
        error_log('Featured Image Generator: No working system fonts found, trying plugin fonts...');
        
        // 優先順位2: プラグイン内のフォントファイル（フォールバック）
        $plugin_fonts = array();
        
        // 現在のファイルの場所から相対パスで解決
        $current_file = __FILE__;
        $plugin_root = dirname(dirname($current_file));
        
        // 複数のパスパターンを試行
        $plugin_fonts[] = $plugin_root . '/assets/fonts/NotoSansJP-Regular.ttf';
        $plugin_fonts[] = $plugin_root . '/assets/fonts/NotoSansJP-Regular.otf';
        
        // WordPress関数を使用したパス解決
        if (function_exists('plugin_dir_path')) {
            $plugin_dir = plugin_dir_path(__FILE__);
            $plugin_fonts[] = $plugin_dir . '../assets/fonts/NotoSansJP-Regular.ttf';
            $plugin_fonts[] = $plugin_dir . '../assets/fonts/NotoSansJP-Regular.otf';
        }
        
        // 絶対パスでの解決（フォールバック）
        $fallback_path = dirname(dirname(__FILE__)) . '/assets/fonts/NotoSansJP-Regular.ttf';
        $plugin_fonts[] = $fallback_path;
        $plugin_fonts[] = dirname(dirname(__FILE__)) . '/assets/fonts/NotoSansJP-Regular.otf';
        
        // さらに確実なパス解決
        $absolute_paths = array(
            '/Users/kantanpro/Desktop/KantanPro/wordpress/wp-content/plugins/news-crawler/assets/fonts/NotoSansJP-Regular.ttf',
            dirname(dirname(__DIR__)) . '/assets/fonts/NotoSansJP-Regular.ttf',
            realpath(dirname(dirname(__FILE__)) . '/assets/fonts/NotoSansJP-Regular.ttf')
        );
        
        foreach ($absolute_paths as $path) {
            if ($path && file_exists($path)) {
                $plugin_fonts[] = $path;
            }
        }
        
        // 重複を除去
        $plugin_fonts = array_unique($plugin_fonts);
        
        error_log('Featured Image Generator: Checking ' . count($plugin_fonts) . ' plugin fonts...');
        foreach ($plugin_fonts as $plugin_font) {
            error_log('Featured Image Generator: Checking plugin font: ' . $plugin_font);
            if (file_exists($plugin_font)) {
                error_log('Featured Image Generator: Plugin font file exists: ' . $plugin_font);
                if (is_readable($plugin_font)) {
                    error_log('Featured Image Generator: Plugin font file is readable: ' . $plugin_font);
                    $file_size = filesize($plugin_font);
                    error_log('Featured Image Generator: Plugin font file size: ' . $file_size . ' bytes');
                    
                    // フォントの動作確認（必須）
                    if (function_exists('imagettfbbox')) {
                        $test_bbox = imagettfbbox(20, 0, $plugin_font, 'テスト');
                        if ($test_bbox !== false) {
                            error_log('Featured Image Generator: Plugin font test successful: ' . $plugin_font);
                            return $plugin_font;
                        } else {
                            error_log('Featured Image Generator: Plugin font test failed (bbox): ' . $plugin_font);
                        }
                    } else {
                        error_log('Featured Image Generator: FreeType functions not available');
                        // FreeTypeが利用できない場合は、プラグインフォントも使用しない
                        error_log('Featured Image Generator: Skipping plugin font due to FreeType unavailability');
                    }
                } else {
                    error_log('Featured Image Generator: Plugin font file not readable: ' . $plugin_font);
                }
            } else {
                error_log('Featured Image Generator: Plugin font file does not exist: ' . $plugin_font);
            }
        }
        
        // 優先順位3: Linuxシステムフォント
        $linux_fonts = array(
            '/usr/share/fonts/truetype/noto/NotoSansCJK-Regular.ttc',
            '/usr/share/fonts/opentype/noto/NotoSansCJK-Regular.otf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'
        );
        
        foreach ($linux_fonts as $font) {
            if (file_exists($font)) {
                error_log('Featured Image Generator: Found Linux system font: ' . $font);
                return $font;
            }
        }
        
        error_log('Featured Image Generator: No Japanese font found after checking all sources!');
        return false;
    }
    
    /**
     * フォントファイルのパスを取得（旧版・互換性のため残す）
     */
    private function get_font_path() {
        // プラグインディレクトリ内のフォントファイルを確認
        $plugin_fonts = array(
            plugin_dir_path(__FILE__) . '../assets/fonts/NotoSansJP-Regular.otf',
            plugin_dir_path(__FILE__) . '../assets/fonts/NotoSansJP-Regular.ttf'
        );
        
        foreach ($plugin_fonts as $plugin_font) {
            if (file_exists($plugin_font)) {
                return $plugin_font;
            }
        }
        
        // システムフォントを確認（macOS/Linux）
        $system_fonts = array(
            '/System/Library/Fonts/Arial.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/TTF/arial.ttf'
        );
        
        foreach ($system_fonts as $font) {
            if (file_exists($font)) {
                return $font;
            }
        }
        
        return false;
    }
    
    /**
     * 日本語タイトルを生成（ジャンル + キーワード + ニュースまとめ + 日付）
     */
    private function create_japanese_title($original_title, $keywords) {
        // ジャンルを抽出（元のタイトルまたはキーワードから）
        $genre = $this->extract_genre_from_title($original_title, $keywords);
        
        // キーワードから主要なものを選択
        $main_keyword = '';
        if (!empty($keywords)) {
            // 最初のキーワードを使用、または複数のキーワードを組み合わせ
            if (count($keywords) == 1) {
                $main_keyword = $keywords[0];
        } else {
                // 複数のキーワードがある場合は最初の2つを使用
                $main_keyword = implode('・', array_slice($keywords, 0, 2));
            }
        } else {
            // キーワードがない場合は元のタイトルから推測
            $main_keyword = $this->extract_keyword_from_title($original_title);
        }
        
        // 日付を取得（今日の日付）
        $date = date_i18n('n月j日');
        
        // タイトル形式: {ジャンル}{キーワード}ニュースまとめ {日付}
        $japanese_title = $genre . $main_keyword . 'ニュースまとめ ' . $date;
        
        return $japanese_title;
    }
    
    /**
     * ジャンルを抽出
     */
    private function extract_genre_from_title($title, $keywords) {
        // ジャンルマッピング
        $genre_map = array(
            '政治' => '政治',
            '経済' => '経済',
            'AI' => 'テック',
            'テクノロジー' => 'テック',
            'ビジネス' => 'ビジネス',
            'スポーツ' => 'スポーツ',
            '芸能' => 'エンタメ',
            '社会' => '社会',
            '国際' => '国際',
            '地域' => '地域',
            '健康' => '健康',
            '教育' => '教育',
            '環境' => '環境'
        );
        
        // キーワードからジャンルを検索
        if (!empty($keywords)) {
            foreach ($keywords as $keyword) {
                foreach ($genre_map as $search => $genre) {
                    if (strpos($keyword, $search) !== false) {
                        return $genre;
                    }
                }
            }
        }
        
        // タイトルからジャンルを検索
        foreach ($genre_map as $search => $genre) {
            if (strpos($title, $search) !== false) {
                return $genre;
            }
        }
        
        // デフォルトジャンル
        return '最新';
    }
    
    /**
     * タイトルからキーワードを抽出
     */
    private function extract_keyword_from_title($title) {
        // よく使われるキーワードのマッピング
        $keyword_map = array(
            '政治' => '政治',
            '経済' => '経済',
            '自民党' => '政治',
            '公明党' => '政治',
            '参政党' => '政治',
            '国民民主党' => '政治',
            'AI' => 'AI',
            'テクノロジー' => 'テック',
            'ビジネス' => 'ビジネス',
            'スポーツ' => 'スポーツ',
            '芸能' => '芸能',
            '社会' => '社会',
            '国際' => '国際',
            '地域' => '地域'
        );
        
        foreach ($keyword_map as $search => $keyword) {
            if (strpos($title, $search) !== false) {
                return $keyword;
            }
        }
        
        // マッチしない場合はデフォルト
        return '最新';
    }
    
    /**
     * タイトルを画像表示用にフォーマット
     */
    private function format_title_for_image($title, $max_length = 40) {
        if (mb_strlen($title) <= $max_length) {
            return $title;
        }
        
        return mb_substr($title, 0, $max_length) . '...';
    }
    
    /**
     * 画像をWordPressの添付ファイルとして保存
     */
    private function save_image_as_attachment($image, $post_id, $title) {
        // 一時ファイル作成
        $upload_dir = wp_upload_dir();
        $filename = 'featured-image-' . $post_id . '-' . time() . '.png';
        $filepath = $upload_dir['path'] . '/' . $filename;
        
        // PNG形式で保存
        if (!imagepng($image, $filepath)) {
            imagedestroy($image);
            return false;
        }
        
        imagedestroy($image);
        
        // WordPressの添付ファイルとして登録
        $attachment = array(
            'guid' => $upload_dir['url'] . '/' . $filename,
            'post_mime_type' => 'image/png',
            'post_title' => 'アイキャッチ: ' . $title,
            'post_content' => '',
            'post_status' => 'inherit'
        );
        
        $attachment_id = wp_insert_attachment($attachment, $filepath, $post_id);
        
        if (is_wp_error($attachment_id)) {
            return false;
        }
        
        // 添付ファイルのメタデータを生成
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $filepath);
        wp_update_attachment_metadata($attachment_id, $attachment_data);
        
        // 投稿のアイキャッチに設定
        set_post_thumbnail($post_id, $attachment_id);
        
        // OGPマネージャーに通知（存在する場合）
        if (class_exists('NewsCrawlerOGPManager')) {
            $ogp_manager = new NewsCrawlerOGPManager();
            $ogp_manager->update_featured_image_meta($post_id, $attachment_id);
        }
        
        return $attachment_id;
    }
    
    /**
     * 外部画像をダウンロードして添付ファイルとして保存 - 強化版通信エラーハンドリング
     */
    private function download_and_attach_image($image_url, $post_id, $title) {
        // URLの検証
        if (empty($image_url) || !filter_var($image_url, FILTER_VALIDATE_URL)) {
            error_log('NewsCrawlerFeaturedImageGenerator: 無効な画像URL: ' . $image_url);
            return array('error' => '無効な画像URLです');
        }

        // 画像ダウンロード（強化版指数バックオフ付き）
        $max_retries = 3;
        $base_delay = 2;
        $max_delay = 30;

        for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
            error_log('NewsCrawlerFeaturedImageGenerator: 画像ダウンロード試行回数 ' . $attempt . '/' . $max_retries . ' - URL: ' . $image_url);

            // リクエスト間の待機（2回目以降）
            if ($attempt > 1) {
                $delay = min($base_delay * pow(2, $attempt - 2), $max_delay);
                $jitter = mt_rand(0, 1000) / 1000;
                $total_delay = $delay + $jitter;

                error_log('NewsCrawlerFeaturedImageGenerator: 画像ダウンロード通信エラー対策で ' . round($total_delay, 2) . '秒待機します');
                usleep($total_delay * 1000000);
            }

            // タイムアウトを動的に設定
            $timeout = 30 + ($attempt * 15); // 30秒から開始、試行ごとに15秒延ばす
            $timeout = min($timeout, 90); // 最大90秒

            $response = wp_remote_get($image_url, array(
                'timeout' => $timeout,
                'redirection' => 5,
                'httpversion' => '1.1',
                'user-agent' => 'NewsCrawler/1.0',
                'headers' => array(
                    'Accept' => 'image/*',
                    'Referer' => get_site_url()
                )
            ));

            // ネットワークエラーの詳細な処理
            if (is_wp_error($response)) {
                $error_code = $response->get_error_code();
                $error_message = $response->get_error_message();

                error_log('NewsCrawlerFeaturedImageGenerator: 画像ダウンロード試行' . $attempt . ' - ネットワークエラー: ' . $error_code . ' - ' . $error_message);

                // エラーの種類に応じた処理
                if (strpos($error_message, 'timed out') !== false || strpos($error_message, 'timeout') !== false) {
                    $user_message = '画像のダウンロードがタイムアウトしました。インターネット接続を確認してください。';
                } elseif (strpos($error_message, 'could not resolve host') !== false) {
                    $user_message = '画像サーバーに接続できません。DNSまたはネットワーク設定を確認してください。';
                } elseif (strpos($error_message, 'SSL') !== false) {
                    $user_message = 'SSL接続エラーが発生しました。証明書の有効性を確認してください。';
                } else {
                    $user_message = '画像のダウンロードに失敗しました: ' . $error_message;
                }

                // ネットワークエラーの場合は再試行
                if ($attempt < $max_retries) {
                    continue;
                }
                return array('error' => $user_message);
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            error_log('NewsCrawlerFeaturedImageGenerator: 画像ダウンロード試行' . $attempt . ' - レスポンスコード: ' . $response_code);

            // HTTPステータスコードに応じた処理
            if ($response_code === 404) {
                error_log('NewsCrawlerFeaturedImageGenerator: 画像が見つかりません (404) - URL: ' . $image_url);
                return array('error' => '指定された画像が見つかりません。画像が削除された可能性があります。');
            } elseif ($response_code === 403) {
                error_log('NewsCrawlerFeaturedImageGenerator: 画像へのアクセスが拒否されました (403) - URL: ' . $image_url);
                return array('error' => '画像へのアクセスが拒否されました。アクセス権限を確認してください。');
            } elseif ($response_code >= 500 && $response_code < 600) {
                // サーバーエラー
                error_log('NewsCrawlerFeaturedImageGenerator: 画像サーバーエラー: ' . $response_code . ' - URL: ' . $image_url);

                if ($attempt < $max_retries) {
                    continue;
                }

                $user_message = '画像サーバーで一時的なエラーが発生しています。しばらく時間をおいてから再度お試しください。';
                return array('error' => $user_message);
            } elseif ($response_code >= 400 && $response_code < 500) {
                // クライアントエラー（404,403以外）
                error_log('NewsCrawlerFeaturedImageGenerator: 画像ダウンロードクライアントエラー: ' . $response_code . ' - URL: ' . $image_url);
                break; // 再試行せず終了
            }

            // 成功または4xxエラーの場合はループを抜ける
            if ($response_code === 200 || ($response_code >= 400 && $response_code < 500)) {
                break;
            }
        }

        // 最終的なレスポンスを評価
        if (is_wp_error($response)) {
            return array('error' => '画像ダウンロードエラー: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $image_data = wp_remote_retrieve_body($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');

        // 画像データの検証
        if (empty($image_data)) {
            error_log('NewsCrawlerFeaturedImageGenerator: ダウンロードした画像データが空です - URL: ' . $image_url);
            return array('error' => 'ダウンロードした画像データが空です。画像が破損している可能性があります。');
        }

        // 画像サイズの検証（最小サイズチェック）
        $image_size = strlen($image_data);
        if ($image_size < 1000) { // 1KB未満は不正とみなす
            error_log('NewsCrawlerFeaturedImageGenerator: ダウンロードした画像サイズが小さすぎます: ' . $image_size . ' bytes - URL: ' . $image_url);
            return array('error' => 'ダウンロードした画像のサイズが小さすぎます。有効な画像ではない可能性があります。');
        }

        // コンテンツタイプの検証
        if (empty($content_type) || !preg_match('/^image\//', $content_type)) {
            error_log('NewsCrawlerFeaturedImageGenerator: 無効なコンテンツタイプ: ' . $content_type . ' - URL: ' . $image_url);
            return array('error' => 'ダウンロードしたファイルは画像ではありません。');
        }

        // ファイル拡張子を決定
        $extension = 'jpg';
        if (strpos($content_type, 'png') !== false) {
            $extension = 'png';
        } elseif (strpos($content_type, 'gif') !== false) {
            $extension = 'gif';
        } elseif (strpos($content_type, 'webp') !== false) {
            $extension = 'webp';
        }

        // 一時ファイル作成
        $upload_dir = wp_upload_dir();
        if (!wp_mkdir_p($upload_dir['path'])) {
            error_log('NewsCrawlerFeaturedImageGenerator: アップロードディレクトリの作成に失敗しました: ' . $upload_dir['path']);
            return array('error' => 'アップロードディレクトリの作成に失敗しました。権限を確認してください。');
        }

        $filename = 'featured-image-' . $post_id . '-' . time() . '.' . $extension;
        $filepath = $upload_dir['path'] . '/' . $filename;

        // ファイル書き込みのエラーハンドリング
        if (!file_put_contents($filepath, $image_data)) {
            error_log('NewsCrawlerFeaturedImageGenerator: 画像ファイルの保存に失敗しました: ' . $filepath);
            return array('error' => '画像ファイルの保存に失敗しました。ディスク容量や権限を確認してください。');
        }

        // ファイルサイズの再確認
        $saved_size = filesize($filepath);
        if ($saved_size !== $image_size) {
            error_log('NewsCrawlerFeaturedImageGenerator: 保存されたファイルサイズが一致しません。期待: ' . $image_size . ' bytes, 実際: ' . $saved_size . ' bytes');
            @unlink($filepath); // ファイルを削除
            return array('error' => '画像ファイルの保存に失敗しました。ファイルが破損している可能性があります。');
        }

        error_log('NewsCrawlerFeaturedImageGenerator: 画像ダウンロード成功 - サイズ: ' . $saved_size . ' bytes, タイプ: ' . $content_type);

        // WordPressの添付ファイルとして登録
        $attachment = array(
            'guid' => $upload_dir['url'] . '/' . $filename,
            'post_mime_type' => $content_type,
            'post_title' => 'アイキャッチ: ' . $title,
            'post_content' => '',
            'post_status' => 'inherit'
        );

        $attachment_id = wp_insert_attachment($attachment, $filepath, $post_id);

        if (is_wp_error($attachment_id)) {
            error_log('NewsCrawlerFeaturedImageGenerator: 添付ファイルの登録に失敗しました: ' . $attachment_id->get_error_message());
            @unlink($filepath); // ファイルを削除
            return array('error' => '添付ファイルの登録に失敗しました: ' . $attachment_id->get_error_message());
        }

        // 添付ファイルのメタデータを生成
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $filepath);
        if (is_wp_error($attachment_data)) {
            error_log('NewsCrawlerFeaturedImageGenerator: 添付ファイルメタデータの生成に失敗しました: ' . $attachment_data->get_error_message());
            // メタデータ生成失敗でも続行
        } else {
            wp_update_attachment_metadata($attachment_id, $attachment_data);
        }

        // 投稿のアイキャッチに設定
        $thumbnail_result = set_post_thumbnail($post_id, $attachment_id);
        if (!$thumbnail_result) {
            error_log('NewsCrawlerFeaturedImageGenerator: アイキャッチ画像の設定に失敗しました - 投稿ID: ' . $post_id . ', 添付ファイルID: ' . $attachment_id);
            // 添付ファイルは残すが、エラーを返す
            return array('error' => 'アイキャッチ画像の設定に失敗しました。');
        }

        // OGPマネージャーに通知（存在する場合）
        if (class_exists('NewsCrawlerOGPManager')) {
            $ogp_manager = new NewsCrawlerOGPManager();
            $ogp_manager->update_featured_image_meta($post_id, $attachment_id);
        }

        error_log('NewsCrawlerFeaturedImageGenerator: 画像ダウンロードと設定が完了しました - 添付ファイルID: ' . $attachment_id);
        return $attachment_id;
    }
    
    /**
     * AI画像生成用のプロンプトを作成
     */
    private function create_ai_prompt($title, $keywords, $settings) {
        $style = isset($settings['ai_style']) ? $settings['ai_style'] : 'modern, clean, professional, engaging';
        $base_prompt = isset($settings['ai_base_prompt']) ? $settings['ai_base_prompt'] : 'Create an attractive and engaging featured image for a blog post about';
        
        $keyword_text = !empty($keywords) ? implode(', ', array_slice($keywords, 0, 3)) : '';
        
        $prompt = $base_prompt . ' "' . $title . '"';
        if (!empty($keyword_text)) {
            $prompt .= ' related to ' . $keyword_text;
        }
        $prompt .= '. Style: ' . $style . '. The image should be visually appealing and draw readers\' attention. No text overlay. High quality, professional appearance.';
        
        return $prompt;
    }
    
    /**
     * Unsplash検索用のクエリを作成
     */
    private function create_unsplash_query($title, $keywords) {
        if (!empty($keywords)) {
            return implode(' ', array_slice($keywords, 0, 2));
        }
        
        // タイトルから重要なキーワードを抽出
        $words = explode(' ', $title);
        $important_words = array_slice($words, 0, 2);
        
        return implode(' ', $important_words);
    }
    
    /**
     * 設定のサニタイズ
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        

        
        // AI設定

        $sanitized['ai_style'] = isset($input['ai_style']) ? sanitize_text_field($input['ai_style']) : 'modern, clean, professional, engaging';
        $sanitized['ai_base_prompt'] = isset($input['ai_base_prompt']) ? sanitize_textarea_field($input['ai_base_prompt']) : 'Create an attractive and engaging featured image for a blog post about';
        
        // Unsplash設定
        $sanitized['unsplash_access_key'] = isset($input['unsplash_access_key']) ? sanitize_text_field($input['unsplash_access_key']) : '';
        
        return $sanitized;
    }
    
    /**
     * 設定画面のHTML出力
     */
    public function render_settings_form() {
        $settings = get_option($this->option_name, array());
        ?>
        <div class="featured-image-settings">
            <h3>アイキャッチ自動生成設定</h3>
            
            <table class="form-table">
                <tr>
                    <th scope="row">生成方法</th>
                    <td>
                        <select name="featured_image_method" id="featured_image_method">
                            <option value="ai">AI画像生成 (OpenAI DALL-E)</option>
                            <option value="unsplash">Unsplash画像取得</option>
                        </select>
                    </td>
                </tr>
            </table>
            

            
            <!-- AI設定 -->
            <div id="ai-settings" class="method-settings" style="display: none;">
                <h4>AI画像生成設定</h4>
                <table class="form-table">
                    <tr>
                        <th scope="row">画像スタイル</th>
                        <td><input type="text" name="<?php echo $this->option_name; ?>[ai_style]" value="<?php echo esc_attr($settings['ai_style'] ?? 'modern, clean, professional, engaging'); ?>" size="50" />
                        <p class="description">画像のスタイルを指定してください（例：modern, clean, professional, engaging, vibrant, minimalist）</p></td>
                    </tr>
                    <tr>
                        <th scope="row">ベースプロンプト</th>
                        <td><textarea name="<?php echo $this->option_name; ?>[ai_base_prompt]" rows="3" cols="50"><?php echo esc_textarea($settings['ai_base_prompt'] ?? 'Create an attractive and engaging featured image for a blog post about'); ?></textarea>
                        <p class="description">AI画像生成の基本となるプロンプトを指定してください。タイトルは自動で追加されます。</p></td>
                    </tr>
                </table>
            </div>
            
            <!-- Unsplash設定 -->
            <div id="unsplash-settings" class="method-settings" style="display: none;">
                <h4>Unsplash設定</h4>
                <table class="form-table">
                    <tr>
                        <th scope="row">Unsplash Access Key</th>
                        <td><input type="text" name="<?php echo $this->option_name; ?>[unsplash_access_key]" value="<?php echo esc_attr($settings['unsplash_access_key'] ?? ''); ?>" size="50" /></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#featured_image_method').change(function() {
                $('.method-settings').hide();
                $('#' + $(this).val() + '-settings').show();
            });
        });
        </script>
        <?php
    }
    
    /**
     * アイキャッチ生成用のメタボックスを追加
     */
    public function add_featured_image_meta_box() {
        // 投稿タイプがpostの場合のみ追加
        add_meta_box(
            'news_crawler_featured_image',
            'News Crawler ' . $this->get_plugin_version() . ' - アイキャッチ生成',
            array($this, 'render_featured_image_meta_box'),
            'post',
            'side',
            'high'
        );
    }
    
    /**
     * アイキャッチ生成用のメタボックスの内容を表示
     */
    public function render_featured_image_meta_box($post) {
        // 基本設定からOpenAI APIキーを取得
        $basic_settings = get_option('news_crawler_basic_settings', array());
        $api_key = isset($basic_settings['openai_api_key']) ? $basic_settings['openai_api_key'] : '';
        
        // アイキャッチ画像設定を取得
        $featured_image_settings = get_option('news_crawler_featured_image_settings', array());
        $generation_method = isset($featured_image_settings['featured_image_method']) ? $featured_image_settings['featured_image_method'] : 'ai';
        
        // 既にアイキャッチ画像が設定されているかチェック
        $has_featured_image = has_post_thumbnail($post->ID);
        $featured_image_id = get_post_thumbnail_id($post->ID);
        
        if (empty($api_key) && $generation_method === 'ai') {
            echo '<div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px; margin-bottom: 15px;">';
            echo '<p style="margin: 0; color: #721c24;"><strong>⚠️ OpenAI APIキーが設定されていません</strong></p>';
            echo '<p style="margin: 0; font-size: 12px; color: #721c24;">基本設定でOpenAI APIキーを設定してください。</p>';
            echo '</div>';
            return;
        }
        
        echo '<div id="news-crawler-featured-image-controls">';
        
        if ($has_featured_image) {
            echo '<div style="background: #d1ecf1; border: 1px solid #bee5eb; padding: 10px; border-radius: 4px; margin-bottom: 15px;">';
            echo '<p style="margin: 0 0 10px 0;"><strong>✅ アイキャッチ画像が設定されています</strong></p>';
            echo '<div style="text-align: center; margin-bottom: 10px;">';
            echo get_the_post_thumbnail($post->ID, 'thumbnail');
            echo '</div>';
            echo '<p style="margin: 0; font-size: 12px; color: #666;">ID: ' . $featured_image_id . '</p>';
            echo '</div>';
            
            echo '<button type="button" id="regenerate-featured-image" class="button button-secondary" style="width: 100%; margin-bottom: 10px;">';
            echo 'アイキャッチを再生成';
            echo '</button>';
        } else {
            echo '<div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; border-radius: 4px; margin-bottom: 15px;">';
            echo '<p style="margin: 0;"><strong>📷 アイキャッチ画像が設定されていません</strong></p>';
            echo '</div>';
            
            echo '<button type="button" id="generate-featured-image" class="button button-primary" style="width: 100%; margin-bottom: 10px;">';
            echo 'アイキャッチを生成';
            echo '</button>';
        }
        
        // 生成方法の選択
        echo '<div style="margin-bottom: 15px;">';
        echo '<label for="featured-image-method" style="display: block; margin-bottom: 5px; font-weight: bold;">生成方法:</label>';
        echo '<select id="featured-image-method" style="width: 100%;">';
        echo '<option value="ai"' . ($generation_method === 'ai' ? ' selected' : '') . '>AI画像生成 (OpenAI DALL-E)</option>';
        echo '<option value="unsplash"' . ($generation_method === 'unsplash' ? ' selected' : '') . '>Unsplash画像取得</option>';
        echo '</select>';
        echo '</div>';
        
        // キーワード入力
        echo '<div style="margin-bottom: 15px;">';
        echo '<label for="featured-image-keywords" style="display: block; margin-bottom: 5px; font-weight: bold;">キーワード (オプション):</label>';
        echo '<input type="text" id="featured-image-keywords" placeholder="カンマ区切りで入力" style="width: 100%;" />';
        echo '<p style="margin: 5px 0 0 0; font-size: 11px; color: #666;">画像生成の参考に使用されます</p>';
        echo '</div>';
        
        // ステータス表示エリア
        echo '<div id="featured-image-status" style="display: none; margin-top: 10px; padding: 10px; border-radius: 4px;"></div>';
        
        echo '</div>';
        
        // JavaScript
        ?>
        <script>
        jQuery(document).ready(function($) {
            // ajaxurlが定義されていない場合は定義する
            if (typeof ajaxurl === 'undefined') {
                ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
            }

            // アイキャッチ生成
            $('#generate-featured-image').click(function() {
                var button = $(this);
                var statusDiv = $('#featured-image-status');
                var method = $('#featured-image-method').val();
                var keywords = $('#featured-image-keywords').val();

                button.prop('disabled', true).text('生成中...');
                statusDiv.html('<div style="color: #0073aa;">🔄 アイキャッチ画像を生成中です...</div>').show();

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    dataType: 'json',
                    timeout: 60000, // 60秒タイムアウト
                    data: {
                        action: 'generate_featured_image',
                        nonce: '<?php echo wp_create_nonce('generate_featured_image_nonce'); ?>',
                        post_id: <?php echo $post->ID; ?>,
                        method: method,
                        keywords: keywords
                    },
                    success: function(response) {
                        if (response.success) {
                            statusDiv.html('<div style="color: #46b450;">✅ アイキャッチ画像の生成と設定が完了しました！</div>');

                            // ページをリロードして更新された内容を表示
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            var errorMessage = response.data || '不明なエラーが発生しました';
                            statusDiv.html('<div style="color: #d63638;">❌ エラー: ' + errorMessage + '</div>');
                        }
                    },
                    error: function(xhr, status, error) {
                        // エラーが発生した場合でも、実際にアイキャッチ画像が生成されているかチェック
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            dataType: 'json',
                            data: {
                                action: 'check_featured_image_status',
                                post_id: <?php echo $post->ID; ?>,
                                nonce: '<?php echo wp_create_nonce('check_featured_image_nonce'); ?>'
                            },
                            success: function(checkResponse) {
                                if (checkResponse.success && checkResponse.data.has_thumbnail) {
                                    statusDiv.html('<div style="color: #46b450;">✅ アイキャッチ画像の生成と設定が完了しました！</div>');
                                    setTimeout(function() {
                                        location.reload();
                                    }, 2000);
                                } else {
                                    var errorMessage = '通信エラーが発生しました';
                                    if (status === 'timeout') {
                                        errorMessage = 'リクエストがタイムアウトしました。しばらく時間をおいてから再度お試しください。';
                                    } else if (status === 'error') {
                                        errorMessage = 'サーバーエラーが発生しました。ページをリロードして再度お試しください。';
                                    } else if (status === 'abort') {
                                        errorMessage = 'リクエストが中断されました。';
                                    }
                                    statusDiv.html('<div style="color: #d63638;">❌ ' + errorMessage + '</div>');
                                }
                            },
                            error: function() {
                                var errorMessage = '通信エラーが発生しました';
                                if (status === 'timeout') {
                                    errorMessage = 'リクエストがタイムアウトしました。しばらく時間をおいてから再度お試しください。';
                                } else if (status === 'error') {
                                    errorMessage = 'サーバーエラーが発生しました。ページをリロードして再度お試しください。';
                                } else if (status === 'abort') {
                                    errorMessage = 'リクエストが中断されました。';
                                }
                                statusDiv.html('<div style="color: #d63638;">❌ ' + errorMessage + '</div>');
                            }
                        });
                        
                        console.error('AJAX Error:', {
                            status: status,
                            error: error,
                            xhr: xhr
                        });
                    },
                    complete: function() {
                        button.prop('disabled', false).text('アイキャッチを生成');
                    }
                });
            });

            // アイキャッチ再生成
            $('#regenerate-featured-image').click(function() {
                var button = $(this);
                var statusDiv = $('#featured-image-status');
                var method = $('#featured-image-method').val();
                var keywords = $('#featured-image-keywords').val();

                button.prop('disabled', true).text('再生成中...');
                statusDiv.html('<div style="color: #0073aa;">🔄 アイキャッチ画像を再生成中です...</div>').show();

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    dataType: 'json',
                    timeout: 60000, // 60秒タイムアウト
                    data: {
                        action: 'regenerate_featured_image',
                        nonce: '<?php echo wp_create_nonce('regenerate_featured_image_nonce'); ?>',
                        post_id: <?php echo $post->ID; ?>,
                        method: method,
                        keywords: keywords
                    },
                    success: function(response) {
                        if (response.success) {
                            statusDiv.html('<div style="color: #46b450;">✅ アイキャッチ画像の再生成と設定が完了しました！</div>');

                            // ページをリロードして更新された内容を表示
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            var errorMessage = response.data || '不明なエラーが発生しました';
                            statusDiv.html('<div style="color: #d63638;">❌ エラー: ' + errorMessage + '</div>');
                        }
                    },
                    error: function(xhr, status, error) {
                        // エラーが発生した場合でも、実際にアイキャッチ画像が生成されているかチェック
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            dataType: 'json',
                            data: {
                                action: 'check_featured_image_status',
                                post_id: <?php echo $post->ID; ?>,
                                nonce: '<?php echo wp_create_nonce('check_featured_image_nonce'); ?>'
                            },
                            success: function(checkResponse) {
                                if (checkResponse.success && checkResponse.data.has_thumbnail) {
                                    statusDiv.html('<div style="color: #46b450;">✅ アイキャッチ画像の再生成と設定が完了しました！</div>');
                                    setTimeout(function() {
                                        location.reload();
                                    }, 2000);
                                } else {
                                    var errorMessage = '通信エラーが発生しました';
                                    if (status === 'timeout') {
                                        errorMessage = 'リクエストがタイムアウトしました。しばらく時間をおいてから再度お試しください。';
                                    } else if (status === 'error') {
                                        errorMessage = 'サーバーエラーが発生しました。ページをリロードして再度お試しください。';
                                    } else if (status === 'abort') {
                                        errorMessage = 'リクエストが中断されました。';
                                    }
                                    statusDiv.html('<div style="color: #d63638;">❌ ' + errorMessage + '</div>');
                                }
                            },
                            error: function() {
                                var errorMessage = '通信エラーが発生しました';
                                if (status === 'timeout') {
                                    errorMessage = 'リクエストがタイムアウトしました。しばらく時間をおいてから再度お試しください。';
                                } else if (status === 'error') {
                                    errorMessage = 'サーバーエラーが発生しました。ページをリロードして再度お試しください。';
                                } else if (status === 'abort') {
                                    errorMessage = 'リクエストが中断されました。';
                                }
                                statusDiv.html('<div style="color: #d63638;">❌ ' + errorMessage + '</div>');
                            }
                        });
                        
                        console.error('AJAX Error:', {
                            status: status,
                            error: error,
                            xhr: xhr
                        });
                    },
                    complete: function() {
                        button.prop('disabled', false).text('アイキャッチを再生成');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * アイキャッチ画像を生成するAJAXハンドラー
     */
    public function ajax_generate_featured_image() {
        check_ajax_referer('generate_featured_image_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('権限がありません');
        }
        
        $post_id = intval($_POST['post_id']);
        $method = sanitize_text_field($_POST['method']);
        $keywords = sanitize_text_field($_POST['keywords']);
        
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'post') {
            wp_send_json_error('投稿が見つかりません');
        }
        
        // 既にアイキャッチ画像が設定されている場合はスキップ
        if (has_post_thumbnail($post_id)) {
            wp_send_json_error('既にアイキャッチ画像が設定されています');
        }
        
        // キーワードを配列に変換
        $keywords_array = array();
        if (!empty($keywords)) {
            $keywords_array = array_map('trim', explode(',', $keywords));
        }
        
        // アイキャッチ画像を生成
        $result = $this->generate_and_set_featured_image($post_id, $post->post_title, $keywords_array, $method);
        
        if (is_array($result) && isset($result['error'])) {
            wp_send_json_error($result['error']);
        } elseif ($result) {
            // 生成された画像が確実にアイキャッチ画像として設定されているか確認
            if (has_post_thumbnail($post_id)) {
                $thumbnail_id = get_post_thumbnail_id($post_id);
                
                if ($thumbnail_id == $result) {
                    wp_send_json_success('アイキャッチ画像の生成と設定が完了しました');
                } else {
                    // 強制的にアイキャッチ画像として設定
                    set_post_thumbnail($post_id, $result);
                    wp_send_json_success('アイキャッチ画像の生成と設定が完了しました');
                }
            } else {
                // アイキャッチ画像が設定されていない場合は強制的に設定
                set_post_thumbnail($post_id, $result);
                wp_send_json_success('アイキャッチ画像の生成と設定が完了しました');
            }
        } else {
            wp_send_json_error('アイキャッチ画像の生成に失敗しました');
        }
    }
    
    /**
     * アイキャッチ画像を再生成するAJAXハンドラー
     */
    public function ajax_regenerate_featured_image() {
        check_ajax_referer('regenerate_featured_image_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('権限がありません');
        }
        
        $post_id = intval($_POST['post_id']);
        $method = sanitize_text_field($_POST['method']);
        $keywords = sanitize_text_field($_POST['keywords']);
        
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'post') {
            wp_send_json_error('投稿が見つかりません');
        }
        
        // 既存のアイキャッチ画像を削除
        $old_thumbnail_id = get_post_thumbnail_id($post_id);
        if ($old_thumbnail_id) {
            delete_post_thumbnail($post_id);
            // 古い添付ファイルも削除（オプション）
            wp_delete_attachment($old_thumbnail_id, true);
        }
        
        // キーワードを配列に変換
        $keywords_array = array();
        if (!empty($keywords)) {
            $keywords_array = array_map('trim', explode(',', $keywords));
        }
        
        // 新しいアイキャッチ画像を生成
        $result = $this->generate_and_set_featured_image($post_id, $post->post_title, $keywords_array, $method);
        
        if (is_array($result) && isset($result['error'])) {
            wp_send_json_error($result['error']);
        } elseif ($result) {
            // 生成された画像が確実にアイキャッチ画像として設定されているか確認
            if (has_post_thumbnail($post_id)) {
                $thumbnail_id = get_post_thumbnail_id($post_id);
                
                if ($thumbnail_id == $result) {
                    wp_send_json_success('アイキャッチ画像の再生成と設定が完了しました');
                } else {
                    // 強制的にアイキャッチ画像として設定
                    set_post_thumbnail($post_id, $result);
                    wp_send_json_success('アイキャッチ画像の再生成と設定が完了しました');
                }
            } else {
                // アイキャッチ画像が設定されていない場合は強制的に設定
                set_post_thumbnail($post_id, $result);
                wp_send_json_success('アイキャッチ画像の再生成と設定が完了しました');
            }
        } else {
            wp_send_json_error('アイキャッチ画像の再生成に失敗しました');
        }
    }
    
    /**
     * アイキャッチ画像の状態をチェックするAJAXハンドラー
     */
    public function ajax_check_featured_image_status() {
        check_ajax_referer('check_featured_image_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('権限がありません');
        }
        
        $post_id = intval($_POST['post_id']);
        $post = get_post($post_id);
        
        if (!$post || $post->post_type !== 'post') {
            wp_send_json_error('投稿が見つかりません');
        }
        
        $has_thumbnail = has_post_thumbnail($post_id);
        $thumbnail_id = $has_thumbnail ? get_post_thumbnail_id($post_id) : null;
        
        wp_send_json_success(array(
            'has_thumbnail' => $has_thumbnail,
            'thumbnail_id' => $thumbnail_id
        ));
    }
    
    /**
     * プラグインのバージョンを動的に取得
     */
    private function get_plugin_version() {
        // 定数から直接取得（より確実）
        return NEWS_CRAWLER_VERSION;
    }
}
