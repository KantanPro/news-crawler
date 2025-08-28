<?php
/**
 * SNS Auto Publisher Class
 * 
 * 自動投稿が成功した後にX（旧ツイッター）に自動で投稿する機能
 * 
 * @package NewsCrawler
 * @since 1.8.0
 */

// 直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

class NewsCrawlerSNSAutoPublisher {
    
    private $option_name = 'news_crawler_sns_settings';
    private $api_base_url = 'https://api.twitter.com/2';
    
    public function __construct() {
        add_action('admin_init', array($this, 'admin_init'));
        add_action('wp_insert_post', array($this, 'maybe_publish_to_sns'), 10, 3);
        add_action('wp_ajax_test_sns_connection', array($this, 'test_sns_connection'));
        add_action('wp_ajax_preview_sns_message', array($this, 'preview_sns_message'));
    }
    
    /**
     * 管理画面の初期化
     */
    public function admin_init() {
        register_setting($this->option_name, $this->option_name, array($this, 'sanitize_settings'));
        
        add_settings_section(
            'sns_publisher_main',
            '設定項目（X）',
            array($this, 'settings_section_callback'),
            'news-crawler-sns'
        );
        
        add_settings_field(
            'sns_enabled',
            'SNS自動投稿を有効にする',
            array($this, 'enabled_field_callback'),
            'news-crawler-sns',
            'sns_publisher_main'
        );
        
        add_settings_field(
            'x_client_id',
            'Client ID：',
            array($this, 'x_client_id_field_callback'),
            'news-crawler-sns',
            'sns_publisher_main'
        );
        
        add_settings_field(
            'x_client_secret',
            'Client Secret：',
            array($this, 'x_client_secret_field_callback'),
            'news-crawler-sns',
            'sns_publisher_main'
        );
        
        add_settings_field(
            'x_username',
            'Twitterのユーザー名：',
            array($this, 'x_username_field_callback'),
            'news-crawler-sns',
            'sns_publisher_main'
        );
        
        add_settings_field(
            'x_message_template',
            '投稿用のメッセージ形式（セレクター選んで追加）',
            array($this, 'x_message_template_field_callback'),
            'news-crawler-sns',
            'sns_publisher_main'
        );
        
        add_settings_field(
            'x_card_type',
            'ツイッターカード選択：',
            array($this, 'x_card_type_field_callback'),
            'news-crawler-sns',
            'sns_publisher_main'
        );
        
        add_settings_field(
            'x_hashtags',
            'ハッシュタグ設定：',
            array($this, 'x_hashtags_field_callback'),
            'news-crawler-sns',
            'sns_publisher_main'
        );
        
        add_settings_field(
            'x_access_token',
            'Access Token：',
            array($this, 'x_access_token_field_callback'),
            'news-crawler-sns',
            'sns_publisher_main'
        );
        
        add_settings_field(
            'x_access_token_secret',
            'Access Token Secret：',
            array($this, 'x_access_token_secret_field_callback'),
            'news-crawler-sns',
            'sns_publisher_main'
        );
        
        add_settings_field(
            'x_bearer_token',
            'Bearer Token：',
            array($this, 'x_bearer_token_field_callback'),
            'news-crawler-sns',
            'sns_publisher_main'
        );
        
        add_settings_field(
            'x_api_key',
            'API Key（Consumer Key）：',
            array($this, 'x_api_key_field_callback'),
            'news-crawler-sns',
            'sns_publisher_main'
        );
        
        add_settings_field(
            'x_api_secret',
            'API Secret（Consumer Secret）：',
            array($this, 'x_api_secret_field_callback'),
            'news-crawler-sns',
            'sns_publisher_main'
        );
    }
    
    /**
     * 管理メニューの追加
     */
    public function add_admin_menu() {
        add_submenu_page(
            'news-crawler',
            'SNSシェア',
            'SNSシェア',
            'manage_options',
            'news-crawler-sns',
            array($this, 'admin_page_callback')
        );
    }
    
    /**
     * 設定セクションのコールバック
     */
    public function settings_section_callback() {
        echo '<p>自動投稿が成功した後にX（旧ツイッター）に自動で投稿する設定を行います。</p>';
        echo '<p><strong>注意：</strong>X（Twitter）APIの利用には開発者アカウントの申請が必要です。</p>';
    }
    
    /**
     * 有効化フィールドのコールバック
     */
    public function enabled_field_callback() {
        $options = get_option($this->option_name, array());
        $enabled = isset($options['sns_enabled']) ? $options['sns_enabled'] : false;
        ?>
        <input type="checkbox" id="sns_enabled" name="<?php echo $this->option_name; ?>[sns_enabled]" value="1" <?php checked(1, $enabled); ?> />
        <label for="sns_enabled">SNSへの自動投稿を有効にする</label>
        <?php
    }
    
    /**
     * X Client IDフィールドのコールバック
     */
    public function x_client_id_field_callback() {
        $options = get_option($this->option_name, array());
        $client_id = isset($options['x_client_id']) ? $options['x_client_id'] : '';
        ?>
        <input type="password" id="x_client_id" name="<?php echo $this->option_name; ?>[x_client_id]" value="<?php echo esc_attr($client_id); ?>" class="regular-text" />
        <p class="description">X（Twitter）APIのClient IDを入力してください</p>
        <?php
    }
    
    /**
     * X Client Secretフィールドのコールバック
     */
    public function x_client_secret_field_callback() {
        $options = get_option($this->option_name, array());
        $client_secret = isset($options['x_client_secret']) ? $options['x_client_secret'] : '';
        ?>
        <input type="password" id="x_client_secret" name="<?php echo $this->option_name; ?>[x_client_secret]" value="<?php echo esc_attr($client_secret); ?>" class="regular-text" />
        <p class="description">X（Twitter）APIのClient Secretを入力してください</p>
        <?php
    }
    
    /**
     * X ユーザー名フィールドのコールバック
     */
    public function x_username_field_callback() {
        $options = get_option($this->option_name, array());
        $username = isset($options['x_username']) ? $options['x_username'] : '';
        ?>
        <input type="text" id="x_username" name="<?php echo $this->option_name; ?>[x_username]" value="<?php echo esc_attr($username); ?>" class="regular-text" />
        <p class="description">X（Twitter）のユーザー名を入力してください（@は不要）</p>
        <?php
    }
    
    /**
     * X メッセージテンプレートフィールドのコールバック
     */
    public function x_message_template_field_callback() {
        $options = get_option($this->option_name, array());
        $template = isset($options['x_message_template']) ? $options['x_message_template'] : '{POST_TITLE}';
        
        $available_vars = array(
            '{POST_TITLE}' => '投稿タイトル',
            '{PERMALINK}' => '投稿のパーマリンク',
            '{POST_EXCERPT}' => '投稿の抜粋',
            '{POST_CONTENT}' => '投稿の内容（最初の100文字）',
            '{BLOG_TITLE}' => 'ブログのタイトル',
            '{USER_NICENAME}' => '投稿者のニックネーム',
            '{POST_ID}' => '投稿ID',
            '{POST_PUBLISH_DATE}' => '投稿公開日',
            '{USER_DISPLAY_NAME}' => '投稿者の表示名'
        );
        ?>
        <textarea id="x_message_template" name="<?php echo $this->option_name; ?>[x_message_template]" rows="4" cols="60" class="large-text"><?php echo esc_textarea($template); ?></textarea>
        <p class="description">投稿メッセージのテンプレートを設定してください。使用可能な変数：</p>
        <div style="background: #f9f9f9; padding: 10px; margin: 5px 0; border: 1px solid #ddd;">
            <?php foreach ($available_vars as $var => $desc): ?>
                <button type="button" class="button button-small add-variable" data-variable="<?php echo esc_attr($var); ?>" style="margin: 2px;"><?php echo esc_html($var); ?></button>
            <?php endforeach; ?>
        </div>
        <button type="button" id="preview-message" class="button button-secondary">プレビュー表示</button>
        <div id="message-preview" style="margin-top: 10px; padding: 10px; background: #f0f0f0; border: 1px solid #ccc; display: none;"></div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.add-variable').on('click', function() {
                var variable = $(this).data('variable');
                var textarea = $('#x_message_template');
                var currentValue = textarea.val();
                var cursorPos = textarea[0].selectionStart;
                var textBefore = currentValue.substring(0, cursorPos);
                var textAfter = currentValue.substring(cursorPos);
                
                textarea.val(textBefore + variable + textAfter);
                textarea.focus();
                
                // カーソル位置を更新
                var newCursorPos = cursorPos + variable.length;
                textarea[0].setSelectionRange(newCursorPos, newCursorPos);
            });
        });
        </script>
        <?php
    }
    
    /**
     * X カードタイプフィールドのコールバック
     */
    public function x_card_type_field_callback() {
        $options = get_option($this->option_name, array());
        $card_type = isset($options['x_card_type']) ? $options['x_card_type'] : 'small';
        ?>
        <select id="x_card_type" name="<?php echo $this->option_name; ?>[x_card_type]">
            <option value="small" <?php selected('small', $card_type); ?>>スモール</option>
            <option value="large" <?php selected('large', $card_type); ?>>ビック</option>
        </select>
        <p class="description">ツイッターカードの表示サイズを選択してください</p>
        <?php
    }
    
    /**
     * X ハッシュタグフィールドのコールバック
     */
    public function x_hashtags_field_callback() {
        $options = get_option($this->option_name, array());
        $hashtags = isset($options['x_hashtags']) ? $options['x_hashtags'] : '';
        ?>
        <input type="text" id="x_hashtags" name="<?php echo $this->option_name; ?>[x_hashtags]" value="<?php echo esc_attr($hashtags); ?>" class="regular-text" />
        <p class="description">ハッシュタグをスペース区切りで入力してください（例：#ニュース #自動投稿）</p>
        <?php
    }
    
    /**
     * X Access Tokenフィールドのコールバック
     */
    public function x_access_token_field_callback() {
        $options = get_option($this->option_name, array());
        $access_token = isset($options['x_access_token']) ? $options['x_access_token'] : '';
        ?>
        <input type="password" id="x_access_token" name="<?php echo $this->option_name; ?>[x_access_token]" value="<?php echo esc_attr($access_token); ?>" class="regular-text" />
        <p class="description">X（Twitter）APIのAccess Tokenを入力してください</p>
        <?php
    }
    
    /**
     * X Access Token Secretフィールドのコールバック
     */
    public function x_access_token_secret_field_callback() {
        $options = get_option($this->option_name, array());
        $access_token_secret = isset($options['x_access_token_secret']) ? $options['x_access_token_secret'] : '';
        ?>
        <input type="password" id="x_access_token_secret" name="<?php echo $this->option_name; ?>[x_access_token_secret]" value="<?php echo esc_attr($access_token_secret); ?>" class="regular-text" />
        <p class="description">X（Twitter）APIのAccess Token Secretを入力してください</p>
        <?php
    }
    
    /**
     * 設定のサニタイズ
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        $sanitized['sns_enabled'] = isset($input['sns_enabled']) ? 1 : 0;
        $sanitized['x_client_id'] = sanitize_text_field($input['x_client_id']);
        $sanitized['x_client_secret'] = sanitize_text_field($input['x_client_secret']);
        $sanitized['x_username'] = sanitize_text_field($input['x_username']);
        $sanitized['x_message_template'] = sanitize_textarea_field($input['x_message_template']);
        $sanitized['x_card_type'] = sanitize_text_field($input['x_card_type']);
        $sanitized['x_hashtags'] = sanitize_text_field($input['x_hashtags']);
        $sanitized['x_access_token'] = sanitize_text_field($input['x_access_token']);
        $sanitized['x_access_token_secret'] = sanitize_text_field($input['x_access_token_secret']);
        $sanitized['x_bearer_token'] = sanitize_text_field($input['x_bearer_token']);
        $sanitized['x_api_key'] = sanitize_text_field($input['x_api_key']);
        $sanitized['x_api_secret'] = sanitize_text_field($input['x_api_secret']);
        
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
                do_settings_sections('news-crawler-sns');
                submit_button('設定を保存');
                ?>
            </form>
            
            <hr>
            
            <h2>接続テスト</h2>
            <p>設定した認証情報でX（Twitter）APIへの接続をテストできます。</p>
            <button type="button" id="test-sns-connection" class="button button-secondary">接続をテスト</button>
            <div id="test-result"></div>
            
            <script>
            jQuery(document).ready(function($) {
                // 接続テスト
                $('#test-sns-connection').on('click', function() {
                    var button = $(this);
                    var resultDiv = $('#test-result');
                    
                    button.prop('disabled', true).text('テスト中...');
                    resultDiv.html('<p>接続をテスト中...</p>');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'test_sns_connection',
                            nonce: '<?php echo wp_create_nonce('test_sns_connection'); ?>'
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
                
                // メッセージプレビュー
                $('#preview-message').on('click', function() {
                    var template = $('#x_message_template').val();
                    var previewDiv = $('#message-preview');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'preview_sns_message',
                            template: template,
                            nonce: '<?php echo wp_create_nonce('preview_sns_message'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                previewDiv.html('<strong>プレビュー:</strong><br>' + response.data.message);
                                previewDiv.show();
                            } else {
                                previewDiv.html('<p style="color: red;">プレビューの生成に失敗しました</p>');
                                previewDiv.show();
                            }
                        },
                        error: function() {
                            previewDiv.html('<p style="color: red;">プレビューの生成でエラーが発生しました</p>');
                            previewDiv.show();
                        }
                    });
                });
            });
            </script>
        </div>
        <?php
    }
    
    /**
     * 投稿成功後にSNSに投稿するかチェック
     */
    public function maybe_publish_to_sns($post_id, $post, $update) {
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
        
        // SNS投稿が有効でない場合はスキップ
        $options = get_option($this->option_name, array());
        if (empty($options['sns_enabled'])) {
            return;
        }
        
        // 必要な認証情報が不足している場合はスキップ
        if (empty($options['x_client_id']) || empty($options['x_client_secret']) || 
            empty($options['x_access_token']) || empty($options['x_access_token_secret'])) {
            error_log('NewsCrawler SNS: 必要な認証情報が不足しています');
            return;
        }
        
        // 既に投稿済みの場合はスキップ
        if (get_post_meta($post_id, '_sns_published', true)) {
            return;
        }
        
        // X（Twitter）に投稿
        $this->publish_to_x($post_id, $post);
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
     * X（Twitter）に投稿
     */
    private function publish_to_x($post_id, $post) {
        $options = get_option($this->option_name, array());
        
        // メッセージを作成
        $message = $this->create_x_message($post, $options);
        
        // 文字数制限チェック（280文字）
        if (mb_strlen($message) > 280) {
            $message = mb_substr($message, 0, 277) . '...';
        }
        
        try {
            // Twitter API v2で投稿
            $result = $this->post_tweet($message);
            
            if ($result && isset($result['data']['id'])) {
                // 投稿成功
                update_post_meta($post_id, '_sns_published', true);
                update_post_meta($post_id, '_x_tweet_id', $result['data']['id']);
                update_post_meta($post_id, '_sns_published_date', current_time('mysql'));
                
                error_log('NewsCrawler SNS: 投稿ID ' . $post_id . ' をX（Twitter）に投稿しました。Tweet ID: ' . $result['data']['id']);
            } else {
                error_log('NewsCrawler SNS: 投稿ID ' . $post_id . ' のX（Twitter）投稿に失敗しました');
            }
        } catch (Exception $e) {
            error_log('NewsCrawler SNS: 投稿ID ' . $post_id . ' のX（Twitter）投稿でエラーが発生: ' . $e->getMessage());
        }
    }
    
    /**
     * X投稿用メッセージを作成
     */
    private function create_x_message($post, $options) {
        $template = isset($options['x_message_template']) ? $options['x_message_template'] : '{POST_TITLE}';
        
        // 投稿者情報を取得
        $user = get_userdata($post->post_author);
        $user_nicename = $user ? $user->user_nicename : '';
        $user_display_name = $user ? $user->display_name : '';
        
        // 抜粋を取得（HTMLタグを除去）
        $excerpt = wp_strip_all_tags($post->post_excerpt);
        if (empty($excerpt)) {
            $excerpt = wp_strip_all_tags(wp_trim_words($post->post_content, 50, ''));
        }
        
        // 投稿内容の最初の100文字を取得
        $content = wp_strip_all_tags($post->post_content);
        $content = mb_substr($content, 0, 100);
        
        // 変数を置換
        $message = str_replace(
            array(
                '{POST_TITLE}',
                '{PERMALINK}',
                '{POST_EXCERPT}',
                '{POST_CONTENT}',
                '{BLOG_TITLE}',
                '{USER_NICENAME}',
                '{POST_ID}',
                '{POST_PUBLISH_DATE}',
                '{USER_DISPLAY_NAME}'
            ),
            array(
                $post->post_title,
                get_permalink($post->ID),
                $excerpt,
                $content,
                get_bloginfo('name'),
                $user_nicename,
                $post->ID,
                get_the_date('Y-m-d H:i:s', $post->ID),
                $user_display_name
            ),
            $template
        );
        
        // ハッシュタグを追加
        if (!empty($options['x_hashtags'])) {
            $hashtags = explode(' ', $options['x_hashtags']);
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
            'oauth_consumer_key' => $options['x_client_id'],
            'oauth_nonce' => wp_generate_password(32, false),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => time(),
            'oauth_token' => $options['x_access_token'],
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
        $signature_key = rawurlencode($options['x_client_secret']) . '&' . rawurlencode($options['x_access_token_secret']);
        
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
    public function test_sns_connection() {
        check_ajax_referer('test_sns_connection', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('権限がありません');
        }
        
        $options = get_option($this->option_name, array());
        
        if (empty($options['sns_enabled'])) {
            wp_send_json_error(array('message' => 'SNS自動投稿が無効になっています'));
            return;
        }
        
        if (empty($options['x_client_id']) || empty($options['x_client_secret']) || 
            empty($options['x_access_token']) || empty($options['x_access_token_secret'])) {
            wp_send_json_error(array('message' => '必要な認証情報が不足しています'));
            return;
        }
        
        try {
            // ユーザー情報を取得して接続をテスト
            $oauth = array(
                'oauth_consumer_key' => $options['x_client_id'],
                'oauth_nonce' => wp_generate_password(32, false),
                'oauth_signature_method' => 'HMAC-SHA1',
                'oauth_timestamp' => time(),
                'oauth_token' => $options['x_access_token'],
                'oauth_version' => '1.0'
            );
            
            $url = $this->api_base_url . '/users/me';
            $method = 'GET';
            
            // パラメータをソート
            ksort($oauth);
            
            // 署名ベース文字列を作成
            $base_string = $method . '&' . rawurlencode($url) . '&';
            $base_string .= rawurlencode(http_build_query($oauth, '', '&', PHP_QUERY_RFC3986));
            
            // 署名キーを作成
            $signature_key = rawurlencode($options['x_client_secret']) . '&' . rawurlencode($options['x_access_token_secret']);
            
            // 署名を生成
            $oauth['oauth_signature'] = base64_encode(hash_hmac('sha1', $base_string, $signature_key, true));
            
            // Authorizationヘッダーを作成
            $auth_header = 'OAuth ';
            $auth_parts = array();
            foreach ($oauth as $key => $value) {
                $auth_parts[] = $key . '="' . rawurlencode($value) . '"';
            }
            $auth_header .= implode(', ', $auth_parts);
            
            // デバッグ情報をログに出力
            error_log('NewsCrawler SNS: 接続テスト - URL: ' . $url);
            error_log('NewsCrawler SNS: 接続テスト - OAuthパラメータ: ' . print_r($oauth, true));
            error_log('NewsCrawler SNS: 接続テスト - Authorizationヘッダー: ' . $auth_header);
            
            $response = wp_remote_get($url, array(
                'headers' => array(
                    'Authorization' => $auth_header
                ),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                throw new Exception('リクエストエラー: ' . $response->get_error_message());
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_headers = wp_remote_retrieve_headers($response);
            
            // デバッグ情報をログに出力
            error_log('NewsCrawler SNS: 接続テスト - レスポンスコード: ' . $response_code);
            error_log('NewsCrawler SNS: 接続テスト - レスポンスヘッダー: ' . print_r($response_headers, true));
            error_log('NewsCrawler SNS: 接続テスト - レスポンスボディ: ' . $body);
            
            if ($response_code === 200 && isset($data['data']['username'])) {
                wp_send_json_success(array('message' => '接続成功！ユーザー名: @' . $data['data']['username']));
            } else {
                $error_message = '不明なエラー';
                if (isset($data['errors']) && is_array($data['errors']) && !empty($data['errors'])) {
                    $error_message = $data['errors'][0]['message'];
                } elseif (isset($data['detail'])) {
                    $error_message = $data['detail'];
                } elseif (isset($data['message'])) {
                    $error_message = $data['message'];
                } elseif ($response_code !== 200) {
                    $error_message = 'HTTPエラー: ' . $response_code;
                }
                throw new Exception('API エラー: ' . $error_message);
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * メッセージプレビューのAJAXハンドラー
     */
    public function preview_sns_message() {
        check_ajax_referer('preview_sns_message', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('権限がありません');
        }
        
        $template = sanitize_textarea_field($_POST['template']);
        
        if (empty($template)) {
            wp_send_json_error(array('message' => 'テンプレートが空です'));
            return;
        }
        
        // サンプルデータでプレビューを生成
        $sample_post = (object) array(
            'post_title' => 'サンプル投稿タイトル',
            'post_excerpt' => 'これはサンプルの投稿抜粋です。実際の投稿では、ここに記事の要約が表示されます。',
            'post_content' => 'これはサンプルの投稿内容です。実際の投稿では、ここに記事の本文が表示されます。記事の内容は長くなる場合があります。',
            'ID' => 123
        );
        
        $sample_user = (object) array(
            'user_nicename' => 'sample_user',
            'display_name' => 'サンプルユーザー'
        );
        
        // 変数を置換
        $message = str_replace(
            array(
                '{POST_TITLE}',
                '{PERMALINK}',
                '{POST_EXCERPT}',
                '{POST_CONTENT}',
                '{BLOG_TITLE}',
                '{USER_NICENAME}',
                '{POST_ID}',
                '{POST_PUBLISH_DATE}',
                '{USER_DISPLAY_NAME}'
            ),
            array(
                $sample_post->post_title,
                get_permalink($sample_post->ID),
                $sample_post->post_excerpt,
                mb_substr($sample_post->post_content, 0, 100),
                get_bloginfo('name'),
                $sample_user->user_nicename,
                $sample_post->ID,
                current_time('Y-m-d H:i:s'),
                $sample_user->display_name
            ),
            $template
        );
        
        wp_send_json_success(array('message' => $message));
    }
    
    /**
     * X Bearer Tokenフィールドのコールバック
     */
    public function x_bearer_token_field_callback() {
        $options = get_option($this->option_name, array());
        $bearer_token = isset($options['x_bearer_token']) ? $options['x_bearer_token'] : '';
        ?>
        <input type="password" id="x_bearer_token" name="<?php echo $this->option_name; ?>[x_bearer_token]" value="<?php echo esc_attr($bearer_token); ?>" class="regular-text" />
        <p class="description">X（Twitter）APIのBearer Tokenを入力してください</p>
        <?php
    }
    
    /**
     * X API Keyフィールドのコールバック
     */
    public function x_api_key_field_callback() {
        $options = get_option($this->option_name, array());
        $api_key = isset($options['x_api_key']) ? $options['x_api_key'] : '';
        ?>
        <input type="password" id="x_api_key" name="<?php echo $this->option_name; ?>[x_api_key]" value="<?php echo esc_attr($api_key); ?>" class="regular-text" />
        <p class="description">X（Twitter）APIのAPI Key（Consumer Key）を入力してください</p>
        <?php
    }
    
    /**
     * X API Secretフィールドのコールバック
     */
    public function x_api_secret_field_callback() {
        $options = get_option($this->option_name, array());
        $api_secret = isset($options['x_api_secret']) ? $options['x_api_secret'] : '';
        ?>
        <input type="password" id="x_api_secret" name="<?php echo $this->option_name; ?>[x_api_secret]" value="<?php echo esc_attr($api_secret); ?>" class="regular-text" />
        <p class="description">X（Twitter）APIのAPI Secret（Consumer Secret）を入力してください</p>
        <?php
    }
}
