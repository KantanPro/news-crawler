<?php
/**
 * SEO最適化タイトル生成クラス
 * 
 * @package NewsCrawler
 * @since 1.6.0
 */

// 直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

class NewsCrawlerSEOTitleGenerator {
    
    private $api_key;
    private $model = 'gpt-3.5-turbo';
    
    public function __construct() {
        // デバッグ情報をログに出力
        error_log('NewsCrawlerSEOTitleGenerator: コンストラクタが呼び出されました');
        
        // 基本設定からOpenAI APIキーを取得
        $basic_settings = get_option('news_crawler_basic_settings', array());
        $this->api_key = isset($basic_settings['openai_api_key']) ? $basic_settings['openai_api_key'] : '';
        
        // 投稿作成後にSEOタイトルを生成するフックを追加
        add_action('wp_insert_post', array($this, 'maybe_generate_seo_title'), 10, 3);
        
        // 投稿編集画面にSEOタイトル生成ボタンを追加
        add_action('add_meta_boxes', array($this, 'add_seo_title_meta_box'));
        add_action('wp_ajax_generate_seo_title', array($this, 'ajax_generate_seo_title'));
        
        // 強制的にメタボックスを表示するテスト用フック
        add_action('admin_notices', array($this, 'debug_admin_notice'));
        
        error_log('NewsCrawlerSEOTitleGenerator: フックが追加されました');
    }
    
    /**
     * 投稿作成後にSEOタイトルを生成するかどうかを判定
     */
    public function maybe_generate_seo_title($post_id, $post, $update) {
        // 新規投稿のみ処理
        if ($update) {
            return;
        }
        
        // 投稿タイプがpostでない場合はスキップ
        if ($post->post_type !== 'post') {
            return;
        }
        
        // 既にSEOタイトルが生成されている場合はスキップ
        if (get_post_meta($post_id, '_seo_title_generated', true)) {
            return;
        }
        
        // ニュースまたはYouTube投稿かどうかを確認
        $is_news_summary = get_post_meta($post_id, '_news_summary', true);
        $is_youtube_summary = get_post_meta($post_id, '_youtube_summary', true);
        
        if (!$is_news_summary && !$is_youtube_summary) {
            return;
        }
        
        // 基本設定でSEOタイトル生成が無効になっている場合はスキップ
        $basic_settings = get_option('news_crawler_basic_settings', array());
        $auto_seo_title_enabled = isset($basic_settings['auto_seo_title_generation']) ? $basic_settings['auto_seo_title_generation'] : false;
        if (!$auto_seo_title_enabled) {
            return;
        }
        
        // SEOタイトル生成を実行
        $this->generate_seo_title($post_id);
    }
    
    /**
     * SEO最適化されたタイトルを生成
     */
    public function generate_seo_title($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return false;
        }
        
        // 投稿の本文が空かチェック
        if (empty(trim(wp_strip_all_tags($post->post_content)))) {
            return array('error' => '本文を入力してから実行してください');
        }
        
        // 投稿にカテゴリーが設定されているかチェック
        $current_categories = wp_get_post_categories($post_id);
        if (empty($current_categories)) {
            return array('error' => 'カテゴリーを設定してください');
        }
        
        // 現在のカテゴリーを保存
        $saved_categories = $current_categories;
        
        // News Crawlerで設定されているジャンル名を取得
        $genre_name = $this->get_news_crawler_genre_name($post_id);
        
        // 投稿内容からSEOタイトルを生成
        $seo_title = $this->generate_seo_title_with_ai($post, $genre_name);
        
        if ($seo_title) {
            // 投稿タイトルを更新
            wp_update_post(array(
                'ID' => $post_id,
                'post_title' => $seo_title
            ));
            
            // カテゴリーを復元
            wp_set_post_categories($post_id, $saved_categories);
            
            // メタデータを保存
            update_post_meta($post_id, '_seo_title_generated', true);
            update_post_meta($post_id, '_seo_title_date', current_time('mysql'));
            update_post_meta($post_id, '_original_title', $post->post_title);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * News Crawlerで設定されているジャンル名を取得
     */
    private function get_news_crawler_genre_name($post_id) {
        // ニュース投稿のジャンルIDを取得
        $news_genre_id = get_post_meta($post_id, '_news_crawler_genre_id', true);
        
        // YouTube投稿のジャンルIDを取得
        $youtube_genre_id = get_post_meta($post_id, '_youtube_crawler_genre_id', true);
        
        $genre_id = null;
        
        if (!empty($news_genre_id)) {
            $genre_id = $news_genre_id;
        } elseif (!empty($youtube_genre_id)) {
            $genre_id = $youtube_genre_id;
        }
        
        if ($genre_id) {
            // ジャンル設定からジャンル名を取得
            $genre_settings = get_option('news_crawler_genre_settings', array());
            if (isset($genre_settings[$genre_id]) && isset($genre_settings[$genre_id]['genre_name'])) {
                return $genre_settings[$genre_id]['genre_name'];
            }
        }
        
        // ジャンル名が取得できない場合は、カテゴリーから推測
        $categories = get_the_category($post_id);
        if (!empty($categories)) {
            return $categories[0]->name;
        }
        
        // デフォルトジャンル
        return 'ニュース';
    }
    
    /**
     * AIを使用してSEO最適化されたタイトルを生成
     */
    private function generate_seo_title_with_ai($post, $genre_name) {
        if (empty($this->api_key)) {
            return false;
        }
        
        // 投稿内容を取得
        $content = $post->post_content;
        $excerpt = $post->post_excerpt;
        
        // プロンプトを作成
        $prompt = $this->create_seo_title_prompt($content, $excerpt, $genre_name);
        
        // OpenAI APIを呼び出し
        $response = $this->call_openai_api($prompt);
        
        if ($response && !empty($response)) {
            // 【ジャンル名】プレフィックスを追加
            return '【' . $genre_name . '】' . $response;
        }
        
        return false;
    }
    
    /**
     * SEOタイトル生成用のプロンプトを作成
     */
    private function create_seo_title_prompt($content, $excerpt, $genre_name) {
        return "以下の記事内容を基に、SEOに最適化された魅力的なタイトルを生成してください。

記事のジャンル: {$genre_name}

記事の内容:
{$content}

記事の要約:
{$excerpt}

要求事項:
1. 30文字以内の簡潔で分かりやすいタイトル
2. 検索エンジンで検索されそうなキーワードを含める
3. 読者の興味を引く魅力的な表現
4. 記事の内容を正確に表現
5. 日本語で自然な表現

タイトルのみを返してください。説明や装飾は不要です。";
    }
    
    /**
     * OpenAI APIを呼び出し
     */
    private function call_openai_api($prompt) {
        $url = 'https://api.openai.com/v1/chat/completions';
        
        $data = array(
            'model' => $this->model,
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'あなたはSEOに精通したWebライターです。記事の内容を基に、検索エンジン最適化された魅力的なタイトルを生成してください。'
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => 100,
            'temperature' => 0.7
        );
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array('error' => 'OpenAI APIへの通信に失敗しました: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if (isset($result['choices'][0]['message']['content'])) {
            return trim($result['choices'][0]['message']['content']);
        }
        
        // APIレスポンスの解析に失敗した場合
        if (isset($result['error'])) {
            return array('error' => 'OpenAI APIエラー: ' . $result['error']['message']);
        }
        
        return array('error' => 'OpenAI APIからの応答が不正です。しばらく時間をおいてから再試行してください。');
    }
    
    /**
     * 投稿編集画面にSEOタイトル生成用のメタボックスを追加
     */
    public function add_seo_title_meta_box() {
        // デバッグ情報をログに出力
        error_log('NewsCrawlerSEOTitleGenerator: add_seo_title_meta_box が呼び出されました');
        
        // 投稿タイプがpostの場合のみ追加
        add_meta_box(
            'news_crawler_seo_title',
            'News Crawler ' . NEWS_CRAWLER_VERSION . ' - SEOタイトル生成',
            array($this, 'render_seo_title_meta_box'),
            'post',
            'side',
            'high'
        );
        
        error_log('NewsCrawlerSEOTitleGenerator: メタボックスが追加されました');
    }
    
    /**
     * SEOタイトル生成用のメタボックスの内容を表示
     */
    public function render_seo_title_meta_box($post) {
        $basic_settings = get_option('news_crawler_basic_settings', array());
        $api_key = isset($basic_settings['openai_api_key']) ? $basic_settings['openai_api_key'] : '';
        
        // 現在のジャンル名を取得
        $current_genre_name = $this->get_news_crawler_genre_name($post->ID);
        
        if (empty($api_key)) {
            echo '<div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px; margin-bottom: 15px;">';
            echo '<p style="margin: 0; color: #721c24;"><strong>⚠️ OpenAI APIキーが設定されていません</strong></p>';
            echo '<p style="margin: 0; font-size: 12px; color: #721c24;">基本設定でOpenAI APIキーを設定してください。</p>';
            echo '</div>';
            echo '<p style="margin: 0; font-size: 12px;">現在のジャンル: ' . esc_html($current_genre_name) . '</p>';
            return;
        }
        
        $seo_title_generated = get_post_meta($post->ID, '_seo_title_generated', true);
        $original_title = get_post_meta($post->ID, '_original_title', true);
        
        echo '<div id="news-crawler-seo-title-controls">';
        
        if ($seo_title_generated) {
            echo '<div style="background: #d1ecf1; border: 1px solid #bee5eb; padding: 10px; border-radius: 4px; margin-bottom: 15px;">';
            echo '<p style="margin: 0 0 10px 0;"><strong>✅ SEOタイトルが生成されています</strong></p>';
            if ($original_title) {
                echo '<p style="margin: 0; font-size: 12px;">元のタイトル: ' . esc_html($original_title) . '</p>';
            }
            echo '<p style="margin: 0; font-size: 12px;">現在のジャンル: ' . esc_html($current_genre_name) . '</p>';
            echo '</div>';
            
            echo '<p><button type="button" id="regenerate-seo-title" class="button button-secondary" style="width: 100%;">SEOタイトルを再生成</button></p>';
        } else {
            echo '<div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; border-radius: 4px; margin-bottom: 15px;">';
            echo '<p style="margin: 0;"><strong>📝 SEOタイトルが未生成です</strong></p>';
            echo '<p style="margin: 0; font-size: 12px;">現在のジャンル: ' . esc_html($current_genre_name) . '</p>';
            echo '</div>';
            
            echo '<p><button type="button" id="generate-seo-title" class="button button-primary" style="width: 100%;">SEOタイトルを生成</button></p>';
        }
        
        echo '<div id="seo-title-status" style="margin-top: 10px; display: none;"></div>';
        echo '</div>';
        
        // JavaScript
        ?>
        <script>
        jQuery(document).ready(function($) {
            // SEOタイトル生成
            $('#generate-seo-title, #regenerate-seo-title').click(function() {
                var button = $(this);
                var statusDiv = $('#seo-title-status');
                
                button.prop('disabled', true).text('生成中...');
                statusDiv.html('<div style="color: #0073aa;">🔄 SEOタイトルを生成中です...</div>').show();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'generate_seo_title',
                        post_id: <?php echo $post->ID; ?>,
                        nonce: '<?php echo wp_create_nonce('generate_seo_title_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            statusDiv.html('<div style="color: #28a745;">✅ SEOタイトルが生成されました！</div>');
                            // ページをリロードして変更を反映
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            statusDiv.html('<div style="color: #dc3545;">❌ エラー: ' + (response.data || '不明なエラー') + '</div>');
                        }
                    },
                    error: function() {
                        statusDiv.html('<div style="color: #dc3545;">❌ 通信エラーが発生しました</div>');
                    },
                    complete: function() {
                        button.prop('disabled', false);
                        if ($(this).attr('id') === 'generate-seo-title') {
                            button.text('SEOタイトルを生成');
                        } else {
                            button.text('SEOタイトルを再生成');
                        }
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAXハンドラー: SEOタイトル生成
     */
    public function ajax_generate_seo_title() {
        // セキュリティチェック
        if (!wp_verify_nonce($_POST['nonce'], 'generate_seo_title_nonce')) {
            wp_die('セキュリティチェックに失敗しました');
        }
        
        $post_id = intval($_POST['post_id']);
        
        if (!$post_id) {
            wp_send_json_error('投稿IDが無効です');
        }
        
        // 権限チェック
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error('権限がありません');
        }
        
        $result = $this->generate_seo_title($post_id);
        
        if (is_array($result) && isset($result['error'])) {
            wp_send_json_error($result['error']);
        } elseif ($result === true) {
            wp_send_json_success('SEOタイトルが正常に生成されました');
        } else {
            wp_send_json_error('SEOタイトルの生成に失敗しました');
        }
    }
    
    /**
     * デバッグ用のadmin_notice
     */
    public function debug_admin_notice() {
        $screen = get_current_screen();
        if ($screen && $screen->base === 'post' && $screen->post_type === 'post') {
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p><strong>News Crawler SEO Title Generator Debug:</strong> クラスが正常に読み込まれています。投稿編集画面でメタボックスが表示されるはずです。</p>';
            echo '<p>現在の画面: ' . $screen->base . ' / ' . $screen->post_type . '</p>';
            echo '</div>';
        }
    }
}

// クラスのインスタンス化
new NewsCrawlerSEOTitleGenerator();
