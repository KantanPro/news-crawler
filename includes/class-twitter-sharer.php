<?php
/**
 * Twitter Auto Sharer Class
 * 
 * 自動投稿が成功した後にX（旧ツイッター）に自動でシェアする機能
 * 
 * @package NewsCrawler
 * @since 1.6.0
 */

// 直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

class NewsCrawlerTwitterSharer {
    
    private $option_name = 'news_crawler_twitter_settings';
    private $api_base_url = 'https://api.twitter.com/2';
    
    public function __construct() {
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_insert_post', array($this, 'maybe_share_to_twitter'), 10, 3);
        add_action('wp_ajax_test_twitter_connection', array($this, 'test_twitter_connection'));
    }
    
    /**
     * 管理画面の初期化
     */
    public function admin_init() {
        register_setting($this->option_name, $this->option_name, array($this, 'sanitize_settings'));
        
        add_settings_section(
            'twitter_sharer_main',
            'X（Twitter）自動シェア設定',
            array($this, 'settings_section_callback'),
            'news-crawler-twitter'
        );
        
        add_settings_field(
            'twitter_enabled',
            '自動シェアを有効にする',
            array($this, 'enabled_field_callback'),
            'news-crawler-twitter',
            'twitter_sharer_main'
        );
        
        add_settings_field(
            'twitter_bearer_token',
            'Bearer Token',
            array($this, 'bearer_token_field_callback'),
            'news-crawler-twitter',
            'twitter_sharer_main'
        );
        
        add_settings_field(
            'twitter_api_key',
            'API Key',
            array($this, 'api_key_field_callback'),
            'news-crawler-twitter',
            'twitter_sharer_main'
        );
        
        add_settings_field(
            'twitter_api_secret',
            'API Secret',
            array($this, 'api_secret_field_callback'),
            'news-crawler-twitter',
            'twitter_sharer_main'
        );
        
        add_settings_field(
            'twitter_access_token',
            'Access Token',
            array($this, 'access_token_field_callback'),
            'news-crawler-twitter',
            'twitter_sharer_main'
        );
        
        add_settings_field(
            'twitter_access_token_secret',
            'Access Token Secret',
            array($this, 'access_token_secret_field_callback'),
            'news-crawler-twitter',
            'twitter_sharer_main'
        );
        
        add_settings_field(
            'twitter_message_template',
            '投稿メッセージテンプレート',
            array($this, 'message_template_field_callback'),
            'news-crawler-twitter',
            'twitter_sharer_main'
        );
        
        add_settings_field(
            'twitter_include_link',
            '記事リンクを含める',
            array($this, 'include_link_field_callback'),
            'news-crawler-twitter',
            'twitter_sharer_main'
        );
        
        add_settings_field(
            'twitter_hashtags',
            'ハッシュタグ',
            array($this, 'hashtags_field_callback'),
            'news-crawler-twitter',
            'twitter_sharer_main'
        );
    }
    
    /**
     * 管理メニューの追加
     */
    public function add_admin_menu() {
        add_submenu_page(
            'news-crawler',
            'X（Twitter）設定',
            'X（Twitter）設定',
            'manage_options',
            'news-crawler-twitter',
            array($this, 'admin_page_callback')
        );
    }
    
    /**
     * 設定セクションのコールバック
     */
    public function settings_section_callback() {
        echo '<p>自動投稿が成功した後にX（旧ツイッター）に自動でシェアする設定を行います。</p>';
        echo '<p><strong>注意：</strong>X（Twitter）APIの利用には開発者アカウントの申請が必要です。</p>';
    }
    
    /**
     * 有効化フィールドのコールバック
     */
    public function enabled_field_callback() {
        $options = get_option($this->option_name, array());
        $enabled = isset($options['enabled']) ? $options['enabled'] : false;
        ?>
        <input type="checkbox" id="twitter_enabled" name="<?php echo $this->option_name; ?>[enabled]" value="1" <?php checked(1, $enabled); ?> />
        <label for="twitter_enabled">X（Twitter）への自動シェアを有効にする</label>
        <?php
    }
    
    /**
     * Bearer Tokenフィールドのコールバック
     */
    public function bearer_token_field_callback() {
        $options = get_option($this->option_name, array());
        $bearer_token = isset($options['bearer_token']) ? $options['bearer_token'] : '';
        ?>
        <input type="password" id="twitter_bearer_token" name="<?php echo $this->option_name; ?>[bearer_token]" value="<?php echo esc_attr($bearer_token); ?>" class="regular-text" />
        <p class="description">Twitter API v2のBearer Tokenを入力してください</p>
        <?php
    }
    
    /**
     * API Keyフィールドのコールバック
     */
    public function api_key_field_callback() {
        $options = get_option($this->option_name, array());
        $api_key = isset($options['api_key']) ? $options['api_key'] : '';
        ?>
        <input type="password" id="twitter_api_key" name="<?php echo $this->option_name; ?>[api_key]" value="<?php echo esc_attr($api_key); ?>" class="regular-text" />
        <p class="description">Twitter API Keyを入力してください</p>
        <?php
    }
    
    /**
     * API Secretフィールドのコールバック
     */
    public function api_secret_field_callback() {
        $options = get_option($this->option_name, array());
        $api_secret = isset($options['api_secret']) ? $options['api_secret'] : '';
        ?>
        <input type="password" id="twitter_api_secret" name="<?php echo $this->option_name; ?>[api_secret]" value="<?php echo esc_attr($api_secret); ?>" class="regular-text" />
        <p class="description">Twitter API Secretを入力してください</p>
        <?php
    }
    
    /**
     * Access Tokenフィールドのコールバック
     */
    public function access_token_field_callback() {
        $options = get_option($this->option_name, array());
        $access_token = isset($options['access_token']) ? $options['access_token'] : '';
        ?>
        <input type="password" id="twitter_api_key" name="<?php echo $this->option_name; ?>[access_token]" value="<?php echo esc_attr($access_token); ?>" class="regular-text" />
        <p class="description">Twitter Access Tokenを入力してください</p>
        <?php
    }
    
    /**
     * Access Token Secretフィールドのコールバック
     */
    public function access_token_secret_field_callback() {
        $options = get_option($this->option_name, array());
        $access_token_secret = isset($options['access_token_secret']) ? $options['access_token_secret'] : '';
        ?>
        <input type="password" id="twitter_access_token_secret" name="<?php echo $this->option_name; ?>[access_token_secret]" value="<?php echo esc_attr($access_token_secret); ?>" class="regular-text" />
        <p class="description">Twitter Access Token Secretを入力してください</p>
        <?php
    }
    
    /**
     * メッセージテンプレートフィールドのコールバック
     */
    public function message_template_field_callback() {
        $options = get_option($this->option_name, array());
        $template = isset($options['message_template']) ? $options['message_template'] : '{title}';
        ?>
        <textarea id="twitter_message_template" name="<?php echo $this->option_name; ?>[message_template]" rows="3" cols="50" class="large-text"><?php echo esc_textarea($template); ?></textarea>
        <p class="description">投稿メッセージのテンプレートを設定してください。使用可能な変数：{title}, {excerpt}, {category}</p>
        <?php
    }
    
    /**
     * リンクを含めるフィールドのコールバック
     */
    public function include_link_field_callback() {
        $options = get_option($this->option_name, array());
        $include_link = isset($options['include_link']) ? $options['include_link'] : true;
        ?>
        <input type="checkbox" id="twitter_include_link" name="<?php echo $this->option_name; ?>[include_link]" value="1" <?php checked(1, $include_link); ?> />
        <label for="twitter_include_link">投稿メッセージに記事のリンクを含める</label>
        <?php
    }
    
    /**
     * ハッシュタグフィールドのコールバック
     */
    public function hashtags_field_callback() {
        $options = get_option($this->option_name, array());
        $hashtags = isset($options['hashtags']) ? $options['hashtags'] : '';
        ?>
        <input type="text" id="twitter_hashtags" name="<?php echo $this->option_name; ?>[hashtags]" value="<?php echo esc_attr($hashtags); ?>" class="regular-text" />
        <p class="description">ハッシュタグをスペース区切りで入力してください（例：#ニュース #自動投稿）</p>
        <?php
    }
    
    /**
     * 設定のサニタイズ
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        $sanitized['enabled'] = isset($input['enabled']) ? 1 : 0;
        $sanitized['bearer_token'] = sanitize_text_field($input['bearer_token']);
        $sanitized['api_key'] = sanitize_text_field($input['api_key']);
        $sanitized['api_secret'] = sanitize_text_field($input['api_secret']);
        $sanitized['access_token'] = sanitize_text_field($input['access_token']);
        $sanitized['access_token_secret'] = sanitize_text_field($input['access_token_secret']);
        $sanitized['message_template'] = sanitize_textarea_field($input['message_template']);
        $sanitized['include_link'] = isset($input['include_link']) ? 1 : 0;
        $sanitized['hashtags'] = sanitize_text_field($input['hashtags']);
        
        return $sanitized;
    }
    
    /**
     * 管理画面のコールバック
     */
    public function admin_page_callback() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields($this->option_name);
                do_settings_sections('news-crawler-twitter');
                submit_button('設定を保存');
                ?>
            </form>
            
            <hr>
            
            <h2>接続テスト</h2>
            <p>設定した認証情報でX（Twitter）APIへの接続をテストできます。</p>
            <button type="button" id="test-twitter-connection" class="button button-secondary">接続をテスト</button>
            <div id="test-result"></div>
            
            <script>
            jQuery(document).ready(function($) {
                $('#test-twitter-connection').on('click', function() {
                    var button = $(this);
                    var resultDiv = $('#test-result');
                    
                    button.prop('disabled', true).text('テスト中...');
                    resultDiv.html('<p>接続をテスト中...</p>');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'test_twitter_connection',
                            nonce: '<?php echo wp_create_nonce('test_twitter_connection'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                resultDiv.html('<p style="color: green;">✓ 接続成功: ' + response.data.message + '</p>');
                            } else {
                                resultDiv.html('<p style="color: red;">✗ 接続失敗: ' + response.data.message + '</p>');
                            }
                        },
                        error: function() {
                            resultDiv.html('<p style="color: red;">✗ リクエストエラーが発生しました</p>');
                        },
                        complete: function() {
                            button.prop('disabled', false).text('接続をテスト');
                        }
                    });
                });
            });
            </script>
        </div>
        <?php
    }
    
    /**
     * 投稿成功後にX（Twitter）にシェアするかチェック
     */
    public function maybe_share_to_twitter($post_id, $post, $update) {
        // 新規投稿でない場合はスキップ
        if ($update) {
            return;
        }
        
        // 投稿が公開状態でない場合はスキップ
        if ($post->post_status !== 'publish') {
            return;
        }
        
        // 自動投稿でない場合はスキップ
        if (!$this->is_auto_post($post_id)) {
            return;
        }
        
        // X（Twitter）シェアが有効でない場合はスキップ
        $options = get_option($this->option_name, array());
        if (empty($options['enabled'])) {
            return;
        }
        
        // 必要な認証情報が不足している場合はスキップ
        if (empty($options['bearer_token']) || empty($options['api_key']) || 
            empty($options['api_secret']) || empty($options['access_token']) || 
            empty($options['access_token_secret'])) {
            error_log('NewsCrawler Twitter: 必要な認証情報が不足しています');
            return;
        }
        
        // 既にシェア済みの場合はスキップ
        if (get_post_meta($post_id, '_twitter_shared', true)) {
            return;
        }
        
        // X（Twitter）にシェア
        $this->share_to_twitter($post_id, $post);
    }
    
    /**
     * 自動投稿かどうかをチェック
     */
    private function is_auto_post($post_id) {
        // メタデータで自動投稿かどうかをチェック
        $youtube_summary = get_post_meta($post_id, '_youtube_summary', true);
        $news_summary = get_post_meta($post_id, '_news_summary', true);
        
        return $youtube_summary || $news_summary;
    }
    
    /**
     * X（Twitter）にシェア
     */
    private function share_to_twitter($post_id, $post) {
        $options = get_option($this->option_name, array());
        
        // メッセージを作成
        $message = $this->create_twitter_message($post, $options);
        
        // 文字数制限チェック（280文字）
        if (mb_strlen($message) > 280) {
            $message = mb_substr($message, 0, 277) . '...';
        }
        
        try {
            // Twitter API v2で投稿
            $result = $this->post_tweet($message);
            
            if ($result && isset($result['data']['id'])) {
                // シェア成功
                update_post_meta($post_id, '_twitter_shared', true);
                update_post_meta($post_id, '_twitter_tweet_id', $result['data']['id']);
                update_post_meta($post_id, '_twitter_shared_date', current_time('mysql'));
                
                error_log('NewsCrawler Twitter: 投稿ID ' . $post_id . ' をX（Twitter）にシェアしました。Tweet ID: ' . $result['data']['id']);
            } else {
                error_log('NewsCrawler Twitter: 投稿ID ' . $post_id . ' のX（Twitter）シェアに失敗しました');
            }
        } catch (Exception $e) {
            error_log('NewsCrawler Twitter: 投稿ID ' . $post_id . ' のX（Twitter）シェアでエラーが発生: ' . $e->getMessage());
        }
    }
    
    /**
     * Twitter投稿用メッセージを作成
     */
    private function create_twitter_message($post, $options) {
        $template = isset($options['message_template']) ? $options['message_template'] : '{title}';
        
        // カテゴリー情報を取得
        $categories = get_the_category($post->ID);
        $category_names = array();
        foreach ($categories as $category) {
            $category_names[] = $category->name;
        }
        $category_text = implode('、', $category_names);
        
        // 抜粋を取得（HTMLタグを除去）
        $excerpt = wp_strip_all_tags($post->post_excerpt);
        if (empty($excerpt)) {
            $excerpt = wp_strip_all_tags(wp_trim_words($post->post_content, 50, ''));
        }
        
        // 変数を置換
        $message = str_replace(
            array('{title}', '{excerpt}', '{category}'),
            array($post->post_title, $excerpt, $category_text),
            $template
        );
        
        // リンクを含める場合
        if (!empty($options['include_link'])) {
            $permalink = get_permalink($post->ID);
            $message .= ' ' . $permalink;
        }
        
        // ハッシュタグを追加
        if (!empty($options['hashtags'])) {
            $hashtags = explode(' ', $options['hashtags']);
            foreach ($hashtags as $hashtag) {
                if (!empty($hashtag) && strpos($hashtag, '#') === 0) {
                    $message .= ' ' . $hashtag;
                } elseif (!empty($hashtag)) {
                    $message .= ' #' . ltrim($hashtag, '#');
                }
            }
        }
        
        return $message;
    }
    
    /**
     * Twitter API v2で投稿
     */
    private function post_tweet($message) {
        $options = get_option($this->option_name, array());
        
        // OAuth 1.0a認証ヘッダーを作成
        $oauth = array(
            'oauth_consumer_key' => $options['api_key'],
            'oauth_nonce' => wp_generate_password(32, false),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => time(),
            'oauth_token' => $options['access_token'],
            'oauth_version' => '1.0'
        );
        
        $url = $this->api_base_url . '/tweets';
        $method = 'POST';
        
        // パラメータをソート
        ksort($oauth);
        
        // 署名ベース文字列を作成
        $base_string = $method . '&' . rawurlencode($url) . '&';
        $base_string .= rawurlencode(http_build_query($oauth, '', '&', PHP_QUERY_RFC3986));
        
        // 署名キーを作成
        $signature_key = rawurlencode($options['api_secret']) . '&' . rawurlencode($options['access_token_secret']);
        
        // 署名を生成
        $oauth['oauth_signature'] = base64_encode(hash_hmac('sha1', $base_string, $signature_key, true));
        
        // Authorizationヘッダーを作成
        $auth_header = 'OAuth ';
        $auth_parts = array();
        foreach ($oauth as $key => $value) {
            $auth_parts[] = $key . '="' . rawurlencode($value) . '"';
        }
        $auth_header .= implode(', ', $auth_parts);
        
        // リクエストを送信
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => $auth_header,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'text' => $message
            )),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            throw new Exception('リクエストエラー: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (wp_remote_retrieve_response_code($response) !== 201) {
            $error_message = isset($data['errors'][0]['message']) ? $data['errors'][0]['message'] : '不明なエラー';
            throw new Exception('Twitter API エラー: ' . $error_message);
        }
        
        return $data;
    }
    
    /**
     * 接続テストのAJAXハンドラー
     */
    public function test_twitter_connection() {
        check_ajax_referer('test_twitter_connection', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('権限がありません');
        }
        
        $options = get_option($this->option_name, array());
        
        if (empty($options['enabled'])) {
            wp_send_json_error(array('message' => '自動シェアが無効になっています'));
            return;
        }
        
        if (empty($options['bearer_token']) || empty($options['api_key']) || 
            empty($options['api_secret']) || empty($options['access_token']) || 
            empty($options['access_token_secret'])) {
            wp_send_json_error(array('message' => '必要な認証情報が不足しています'));
            return;
        }
        
        try {
            // ユーザー情報を取得して接続をテスト
            $response = wp_remote_get($this->api_base_url . '/users/me', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $options['bearer_token']
                ),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                throw new Exception('リクエストエラー: ' . $response->get_error_message());
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (wp_remote_retrieve_response_code($response) === 200 && isset($data['data']['username'])) {
                wp_send_json_success(array('message' => '接続成功！ユーザー名: @' . $data['data']['username']));
            } else {
                $error_message = isset($data['errors'][0]['message']) ? $data['errors'][0]['message'] : '不明なエラー';
                throw new Exception('API エラー: ' . $error_message);
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
}
