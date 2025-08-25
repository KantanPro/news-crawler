<?php
/**
 * OpenAI APIを使用して投稿の要約とまとめを生成するクラス
 * 
 * @package NewsCrawler
 * @since 1.5.2
 */

// 直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

class NewsCrawlerOpenAISummarizer {
    
    private $api_key;
    private $model = 'gpt-3.5-turbo';
    private $max_tokens = 1000;
    
    public function __construct() {
        // 基本設定からOpenAI APIキーを取得
        $basic_settings = get_option('news_crawler_basic_settings', array());
        $this->api_key = isset($basic_settings['openai_api_key']) ? $basic_settings['openai_api_key'] : '';
        
        // 基本設定からモデル設定を取得
        $this->model = isset($basic_settings['summary_generation_model']) ? $basic_settings['summary_generation_model'] : 'gpt-3.5-turbo';
        
        // 投稿作成後に要約とまとめを生成するフックを追加
        add_action('wp_insert_post', array($this, 'maybe_generate_summary'), 10, 3);
    }
    
    /**
     * 投稿作成後に要約とまとめを生成するかどうかを判定
     */
    public function maybe_generate_summary($post_id, $post, $update) {
        error_log('NewsCrawlerOpenAISummarizer: maybe_generate_summary called for post ' . $post_id . ', update: ' . ($update ? 'true' : 'false'));
        
        // 新規投稿のみ処理（更新時はスキップ）
        if ($update) {
            error_log('NewsCrawlerOpenAISummarizer: Skipping update post');
            return;
        }
        
        // 投稿タイプがpostでない場合はスキップ
        if ($post->post_type !== 'post') {
            error_log('NewsCrawlerOpenAISummarizer: Skipping non-post type: ' . $post->post_type);
            return;
        }
        
        // 既に要約が生成されている場合はスキップ
        if (get_post_meta($post_id, '_openai_summary_generated', true)) {
            error_log('NewsCrawlerOpenAISummarizer: Summary already generated for post ' . $post_id);
            return;
        }
        
        // ニュースまたはYouTube投稿かどうかを確認
        $is_news_summary = get_post_meta($post_id, '_news_summary', true);
        $is_youtube_summary = get_post_meta($post_id, '_youtube_summary', true);
        
        error_log('NewsCrawlerOpenAISummarizer: Post meta check - _news_summary: ' . ($is_news_summary ? 'true' : 'false') . ', _youtube_summary: ' . ($is_youtube_summary ? 'true' : 'false'));
        
        if (!$is_news_summary && !$is_youtube_summary) {
            error_log('NewsCrawlerOpenAISummarizer: Skipping non-news/youtube post');
            return;
        }
        
        // OpenAI APIキーが設定されていない場合はスキップ
        if (empty($this->api_key)) {
            error_log('NewsCrawlerOpenAISummarizer: OpenAI APIキーが設定されていません');
            return;
        }
        
        // 基本設定で要約生成が無効になっている場合はスキップ
        $basic_settings = get_option('news_crawler_basic_settings', array());
        $auto_summary_enabled = isset($basic_settings['auto_summary_generation']) ? $basic_settings['auto_summary_generation'] : false;
        if (!$auto_summary_enabled) {
            error_log('NewsCrawlerOpenAISummarizer: 要約自動生成が無効になっています');
            return;
        }
        
        error_log('NewsCrawlerOpenAISummarizer: All checks passed, executing summary generation immediately for post ' . $post_id);
        
        // 即座に要約とまとめを生成
        $result = $this->generate_summary($post_id);
        error_log('NewsCrawlerOpenAISummarizer: Immediate summary generation result: ' . ($result ? 'Success' : 'Failed'));
        
        // 非同期でも実行（バックアップとして）
        wp_schedule_single_event(time() + 10, 'news_crawler_generate_summary', array($post_id));
    }
    
    /**
     * 要約とまとめを生成するメイン処理
     */
    public function generate_summary($post_id) {
        error_log('NewsCrawlerOpenAISummarizer: generate_summary called for post ' . $post_id);
        
        $post = get_post($post_id);
        if (!$post) {
            error_log('NewsCrawlerOpenAISummarizer: Post not found for ID ' . $post_id);
            return false;
        }
        
        // 既に要約が生成されている場合はスキップ
        if (get_post_meta($post_id, '_openai_summary_generated', true)) {
            error_log('NewsCrawlerOpenAISummarizer: Summary already generated for post ' . $post_id);
            return false;
        }
        
        try {
            error_log('NewsCrawlerOpenAISummarizer: Starting OpenAI summary generation for post ' . $post_id);
            
            // 投稿内容から要約とまとめを生成
            $summary_result = $this->generate_summary_with_openai($post->post_content, $post->post_title);
            
            error_log('NewsCrawlerOpenAISummarizer: OpenAI result: ' . print_r($summary_result, true));
            
            if ($summary_result && isset($summary_result['summary']) && isset($summary_result['conclusion'])) {
                error_log('NewsCrawlerOpenAISummarizer: Summary generation successful, updating post content');
                
                // 投稿内容に要約とまとめを追加
                $updated_content = $this->append_summary_to_post($post->post_content, $summary_result['summary'], $summary_result['conclusion']);
                
                // 投稿を更新
                $update_result = wp_update_post(array(
                    'ID' => $post_id,
                    'post_content' => $updated_content
                ));
                
                error_log('NewsCrawlerOpenAISummarizer: Post update result: ' . print_r($update_result, true));
                
                if ($update_result && !is_wp_error($update_result)) {
                    // 要約生成完了のメタデータを保存
                    update_post_meta($post_id, '_openai_summary_generated', true);
                    update_post_meta($post_id, '_openai_summary_date', current_time('mysql'));
                    update_post_meta($post_id, '_openai_summary_text', $summary_result['summary']);
                    update_post_meta($post_id, '_openai_conclusion_text', $summary_result['conclusion']);
                    
                    error_log('NewsCrawlerOpenAISummarizer: 要約とまとめの生成が完了しました。投稿ID: ' . $post_id);
                    return true;
                } else {
                    error_log('NewsCrawlerOpenAISummarizer: Post update failed: ' . print_r($update_result, true));
                }
            } else {
                error_log('NewsCrawlerOpenAISummarizer: Summary generation failed or incomplete result');
            }
        } catch (Exception $e) {
            error_log('NewsCrawlerOpenAISummarizer: 要約生成中にエラーが発生しました: ' . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * OpenAI APIを使用して要約とまとめを生成
     */
    private function generate_summary_with_openai($content, $title) {
        error_log('NewsCrawlerOpenAISummarizer: generate_summary_with_openai called with title: ' . $title);
        
        if (empty($this->api_key)) {
            error_log('NewsCrawlerOpenAISummarizer: API key is empty');
            return false;
        }
        
        // 投稿内容をテキストとして抽出（HTMLタグを除去）
        $text_content = wp_strip_all_tags($content);
        error_log('NewsCrawlerOpenAISummarizer: Text content length: ' . mb_strlen($text_content));
        
        // 内容が短すぎる場合はスキップ
        if (mb_strlen($text_content) < 100) {
            error_log('NewsCrawlerOpenAISummarizer: Content too short, skipping');
            return false;
        }
        
        // プロンプトを作成
        $prompt = $this->create_summary_prompt($text_content, $title);
        error_log('NewsCrawlerOpenAISummarizer: Prompt created, length: ' . mb_strlen($prompt));
        
        // OpenAI APIを呼び出し
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'model' => $this->model,
                'messages' => array(
                    array(
                        'role' => 'system',
                        'content' => 'あなたは優秀なニュース編集者です。与えられた記事の内容を分析し、簡潔で分かりやすい要約とまとめを作成してください。'
                    ),
                    array(
                        'role' => 'user',
                        'content' => $prompt
                    )
                ),
                'max_tokens' => $this->max_tokens,
                'temperature' => 0.7
            )),
            'timeout' => 60
        ));
        
        if (is_wp_error($response)) {
            error_log('NewsCrawlerOpenAISummarizer: OpenAI API呼び出しエラー: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        error_log('NewsCrawlerOpenAISummarizer: API response body length: ' . strlen($body));
        
        $data = json_decode($body, true);
        error_log('NewsCrawlerOpenAISummarizer: API response data: ' . print_r($data, true));
        
        if (!$data || !isset($data['choices'][0]['message']['content'])) {
            error_log('NewsCrawlerOpenAISummarizer: OpenAI APIレスポンスの解析に失敗しました');
            return false;
        }
        
        $response_content = $data['choices'][0]['message']['content'];
        error_log('NewsCrawlerOpenAISummarizer: OpenAI response content: ' . $response_content);
        
        // レスポンスから要約とまとめを抽出
        $result = $this->parse_openai_response($response_content);
        error_log('NewsCrawlerOpenAISummarizer: Parsed result: ' . print_r($result, true));
        return $result;
    }
    
    /**
     * OpenAI API用のプロンプトを作成
     */
    private function create_summary_prompt($content, $title) {
        return "以下の記事の内容を分析して、以下の形式で回答してください：

記事タイトル：{$title}

記事内容：
{$content}

回答形式：
## この記事の要約
（記事の要点を3-4行で簡潔にまとめてください）

## まとめ
（記事の内容を踏まえた考察や今後の展望を2-3行で述べてください）

注意：
- 要約は事実に基づいて客観的に作成してください
- まとめは読者の理解を深めるような洞察を含めてください
- 日本語で自然な文章にしてください";
    }
    
    /**
     * OpenAI APIのレスポンスから要約とまとめを抽出
     */
    private function parse_openai_response($response) {
        error_log('NewsCrawlerOpenAISummarizer: parse_openai_response called with response: ' . $response);
        
        $summary = '';
        $conclusion = '';
        
        // 要約部分を抽出
        if (preg_match('/## この記事の要約\s*(.+?)(?=\s*##|\s*$)/s', $response, $matches)) {
            $summary = trim($matches[1]);
            error_log('NewsCrawlerOpenAISummarizer: Summary extracted: ' . $summary);
        } else {
            error_log('NewsCrawlerOpenAISummarizer: Failed to extract summary');
        }
        
        // まとめ部分を抽出
        if (preg_match('/## まとめ\s*(.+?)(?=\s*##|\s*$)/s', $response, $matches)) {
            $conclusion = trim($matches[1]);
            error_log('NewsCrawlerOpenAISummarizer: Conclusion extracted: ' . $conclusion);
        } else {
            error_log('NewsCrawlerOpenAISummarizer: Failed to extract conclusion');
        }
        
        // 抽出に失敗した場合は、レスポンス全体を要約として使用
        if (empty($summary) && empty($conclusion)) {
            error_log('NewsCrawlerOpenAISummarizer: Both summary and conclusion extraction failed, using fallback');
            $summary = 'AIによる要約生成中にエラーが発生しました。';
            $conclusion = '要約の生成に失敗しました。';
        }
        
        $result = array(
            'summary' => $summary,
            'conclusion' => $conclusion
        );
        
        error_log('NewsCrawlerOpenAISummarizer: Final parsed result: ' . print_r($result, true));
        return $result;
    }
    
    /**
     * 投稿内容に要約とまとめを追加
     */
    private function append_summary_to_post($content, $summary, $conclusion) {
        error_log('NewsCrawlerOpenAISummarizer: append_summary_to_post called with summary: ' . $summary . ', conclusion: ' . $conclusion);
        
        // 要約とまとめのHTMLブロックを作成
        $summary_html = "\n\n";
        $summary_html .= '<!-- wp:group {"style":{"spacing":{"margin":{"top":"40px","bottom":"40px"}}}} -->';
        $summary_html .= '<div class="wp-block-group" style="margin-top:40px;margin-bottom:40px">';
        
        // 要約セクション
        $summary_html .= '<!-- wp:heading {"level":2} -->';
        $summary_html .= '<h2>この記事の要約</h2>';
        $summary_html .= '<!-- /wp:heading -->';
        
        $summary_html .= '<!-- wp:paragraph -->';
        $summary_html .= '<p>' . esc_html($summary) . '</p>';
        $summary_html .= '<!-- /wp:paragraph -->';
        
        // まとめセクション
        $summary_html .= '<!-- wp:heading {"level":2} -->';
        $summary_html .= '<h2>まとめ</h2>';
        $summary_html .= '<!-- /wp:heading -->';
        
        $summary_html .= '<!-- wp:paragraph -->';
        $summary_html .= '<p>' . esc_html($conclusion) . '</p>';
        $summary_html .= '<!-- /wp:paragraph -->';
        
        $summary_html .= '</div>';
        $summary_html .= '<!-- /wp:group -->';
        
        error_log('NewsCrawlerOpenAISummarizer: Generated summary HTML: ' . $summary_html);
        
        // 投稿内容の末尾に追加
        $result = $content . $summary_html;
        error_log('NewsCrawlerOpenAISummarizer: Final content length: ' . strlen($result));
        
        return $result;
    }
    
    /**
     * 手動で要約とまとめを再生成
     */
    public function regenerate_summary($post_id) {
        // 既存の要約メタデータを削除
        delete_post_meta($post_id, '_openai_summary_generated');
        delete_post_meta($post_id, '_openai_summary_date');
        delete_post_meta($post_id, '_openai_summary_text');
        delete_post_meta($post_id, '_openai_conclusion_text');
        
        // 要約とまとめを再生成
        return $this->generate_summary($post_id);
    }
    
    /**
     * 要約生成の設定を取得
     */
    public function get_settings() {
        return array(
            'api_key_configured' => !empty($this->api_key),
            'model' => $this->model,
            'max_tokens' => $this->max_tokens
        );
    }
}

// クラスの初期化
new NewsCrawlerOpenAISummarizer();

// 非同期処理のフックを追加
add_action('news_crawler_generate_summary', array('NewsCrawlerOpenAISummarizer', 'generate_summary'));
