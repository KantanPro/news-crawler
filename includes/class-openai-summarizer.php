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
        error_log('NewsCrawlerOpenAISummarizer: maybe_generate_summaryが投稿ID ' . $post_id . ' で呼び出されました。更新: ' . ($update ? 'true' : 'false'));
        
        // 新規投稿のみ処理（更新時はスキップ）
        if ($update) {
            error_log('NewsCrawlerOpenAISummarizer: 更新投稿のためスキップいたします');
            return;
        }
        
        // 投稿タイプがpostでない場合はスキップ
        if ($post->post_type !== 'post') {
            error_log('NewsCrawlerOpenAISummarizer: 投稿タイプではないためスキップいたします: ' . $post->post_type);
            return;
        }
        
        // 既に要約が生成されている場合はスキップ
        if (get_post_meta($post_id, '_openai_summary_generated', true)) {
            error_log('NewsCrawlerOpenAISummarizer: 投稿ID ' . $post_id . ' の要約は既に生成されております');
            return;
        }
        
        // ニュースまたはYouTube投稿かどうかを確認
        $is_news_summary = get_post_meta($post_id, '_news_summary', true);
        $is_youtube_summary = get_post_meta($post_id, '_youtube_summary', true);
        
        error_log('NewsCrawlerOpenAISummarizer: 投稿メタデータの確認 - _news_summary: ' . ($is_news_summary ? 'true' : 'false') . ', _youtube_summary: ' . ($is_youtube_summary ? 'true' : 'false'));
        
        if (!$is_news_summary && !$is_youtube_summary) {
            error_log('NewsCrawlerOpenAISummarizer: ニュースまたはYouTube投稿ではないためスキップいたします');
            return;
        }
        
        // OpenAI APIキーが設定されていない場合はスキップ
        if (empty($this->api_key)) {
            error_log('NewsCrawlerOpenAISummarizer: OpenAI APIキーが設定されておりません');
            return;
        }
        
        // 基本設定で要約生成が無効になっている場合はスキップ
        $basic_settings = get_option('news_crawler_basic_settings', array());
        $auto_summary_enabled = isset($basic_settings['auto_summary_generation']) ? $basic_settings['auto_summary_generation'] : false;
        if (!$auto_summary_enabled) {
            error_log('NewsCrawlerOpenAISummarizer: 要約自動生成が無効になっています');
            return;
        }
        
        error_log('NewsCrawlerOpenAISummarizer: すべてのチェックが完了いたしました。投稿ID ' . $post_id . ' の要約生成を即座に実行いたします');
        
        // 即座に要約とまとめを生成
        $result = $this->generate_summary($post_id);
        error_log('NewsCrawlerOpenAISummarizer: 即座の要約生成結果: ' . ($result ? '成功' : '失敗'));
        
        // 非同期でも実行（バックアップとして）
        wp_schedule_single_event(time() + 10, 'news_crawler_generate_summary', array($post_id));
    }
    
    /**
     * 要約とまとめを生成するメイン処理
     */
    public function generate_summary($post_id) {
        error_log('NewsCrawlerOpenAISummarizer: 投稿ID ' . $post_id . ' の要約生成が呼び出されました');
        
        $post = get_post($post_id);
        if (!$post) {
            error_log('NewsCrawlerOpenAISummarizer: 投稿ID ' . $post_id . ' の投稿が見つかりませんでした');
            return false;
        }
        
        // 既に要約が生成されている場合はスキップ
        if (get_post_meta($post_id, '_openai_summary_generated', true)) {
            error_log('NewsCrawlerOpenAISummarizer: 投稿ID ' . $post_id . ' の要約は既に生成されております');
            return false;
        }
        
        try {
            error_log('NewsCrawlerOpenAISummarizer: 投稿ID ' . $post_id . ' のOpenAI要約生成を開始いたします');
            
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
            update_post_meta($post_id, '_news_summary_categories', $current_categories);
            
            // 投稿内容から要約とまとめを生成
            $summary_result = $this->generate_summary_with_openai($post->post_content, $post->post_title);
            
            error_log('NewsCrawlerOpenAISummarizer: OpenAI結果: ' . print_r($summary_result, true));
            
            if ($summary_result && isset($summary_result['summary']) && isset($summary_result['conclusion'])) {
                error_log('NewsCrawlerOpenAISummarizer: 要約生成が成功いたしました。投稿内容を更新いたします');
                
                // 投稿内容に要約とまとめを追加
                $updated_content = $this->append_summary_to_post($post->post_content, $summary_result['summary'], $summary_result['conclusion']);
                
                // 基本設定で要約をexcerptに設定するかどうかを確認
                $basic_settings = get_option('news_crawler_basic_settings', array());
                $summary_to_excerpt = isset($basic_settings['summary_to_excerpt']) ? $basic_settings['summary_to_excerpt'] : true;
                
                $post_update_data = array(
                    'ID' => $post_id,
                    'post_content' => $updated_content
                );
                
                // 設定が有効な場合のみexcerptを設定
                if ($summary_to_excerpt) {
                    $excerpt = wp_trim_words($summary_result['summary'], 25, '...');
                    $post_update_data['post_excerpt'] = $excerpt;
                    error_log('NewsCrawlerOpenAISummarizer: 要約をexcerptに設定しました: ' . $excerpt);
                }
                
                // 投稿を更新
                $update_result = wp_update_post($post_update_data);
                
                error_log('NewsCrawlerOpenAISummarizer: 投稿更新結果: ' . print_r($update_result, true));
                
                if ($update_result && !is_wp_error($update_result)) {
                    // カテゴリーを復元
                    $saved_categories = get_post_meta($post_id, '_news_summary_categories', true);
                    if (!empty($saved_categories)) {
                        wp_set_post_categories($post_id, $saved_categories);
                        error_log('NewsCrawlerOpenAISummarizer: カテゴリーを復元しました。投稿ID: ' . $post_id);
                    }
                    
                    // 要約生成完了のメタデータを保存
                    update_post_meta($post_id, '_openai_summary_generated', true);
                    update_post_meta($post_id, '_openai_summary_date', current_time('mysql'));
                    update_post_meta($post_id, '_openai_summary_text', $summary_result['summary']);
                    update_post_meta($post_id, '_openai_conclusion_text', $summary_result['conclusion']);
                    
                    error_log('NewsCrawlerOpenAISummarizer: 要約とまとめの生成が正常に完了いたしました。投稿ID: ' . $post_id);
                    return true;
                } else {
                    error_log('NewsCrawlerOpenAISummarizer: 投稿更新に失敗いたしました: ' . print_r($update_result, true));
                }
            } else {
                error_log('NewsCrawlerOpenAISummarizer: 要約生成に失敗いたしました。または不完全な結果です');
            }
        } catch (Exception $e) {
            error_log('NewsCrawlerOpenAISummarizer: 要約生成中にエラーが発生いたしました: ' . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * OpenAI APIを使用して要約とまとめを生成
     */
    private function generate_summary_with_openai($content, $title) {
        error_log('NewsCrawlerOpenAISummarizer: タイトル「' . $title . '」でOpenAI要約生成が呼び出されました');
        
        if (empty($this->api_key)) {
            error_log('NewsCrawlerOpenAISummarizer: APIキーが空です');
            return false;
        }
        
        // 投稿内容をテキストとして抽出（HTMLタグを除去）
        $text_content = wp_strip_all_tags($content);
        error_log('NewsCrawlerOpenAISummarizer: テキスト内容の長さ: ' . mb_strlen($text_content) . '文字');
        
        // 内容が短すぎる場合はスキップ
        if (mb_strlen($text_content) < 100) {
            error_log('NewsCrawlerOpenAISummarizer: 内容が短すぎるためスキップいたします');
            return array('error' => '本文の文字数が不足しています。最低100文字以上入力してください。（現在: ' . mb_strlen($text_content) . '文字）');
        }
        
        // プロンプトを作成
        $prompt = $this->create_summary_prompt($text_content, $title);
        error_log('NewsCrawlerOpenAISummarizer: プロンプトが作成されました。長さ: ' . mb_strlen($prompt) . '文字');
        
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
                        'content' => 'あなたは親しみやすく、分かりやすい文章を書くのが得意なニュース編集者です。難しい専門用語は避けて、誰でも理解できるような表現を使い、読者が「なるほど！」と思えるような要約とまとめを作成いたします。絶対に「ですます調」で書いてください。文末は必ず「です」「ます」「ございます」で終わらせてください。禁止：「〜している」「〜である」「〜だろう」「〜れる」で終わる文章は絶対に書かないでください。回答を書く前に、すべての文末が「です」「ます」「ございます」で終わっているか必ず確認してください。'
                    ),
                    array(
                        'role' => 'user',
                        'content' => $prompt
                    )
                ),
                'max_tokens' => $this->max_tokens,
                'temperature' => 0.3
            )),
            'timeout' => 60
        ));
        
        if (is_wp_error($response)) {
            error_log('NewsCrawlerOpenAISummarizer: OpenAI API呼び出しエラーが発生いたしました: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        error_log('NewsCrawlerOpenAISummarizer: APIレスポンス本文の長さ: ' . strlen($body) . '文字');
        
        $data = json_decode($body, true);
        error_log('NewsCrawlerOpenAISummarizer: APIレスポンスデータ: ' . print_r($data, true));
        
        if (!$data || !isset($data['choices'][0]['message']['content'])) {
            error_log('NewsCrawlerOpenAISummarizer: OpenAI APIレスポンスの解析に失敗いたしました');
            return array('error' => 'OpenAI APIからの応答が不正です。しばらく時間をおいてから再試行してください。');
        }
        
        $response_content = $data['choices'][0]['message']['content'];
        error_log('NewsCrawlerOpenAISummarizer: OpenAIレスポンス内容: ' . $response_content);
        
        // レスポンスから要約とまとめを抽出
        $result = $this->parse_openai_response($response_content);
        error_log('NewsCrawlerOpenAISummarizer: 解析結果: ' . print_r($result, true));
        return $result;
    }
    
    /**
     * OpenAI API用のプロンプトを作成
     */
    private function create_summary_prompt($content, $title) {
        return "【最重要】必ず「ですます調」で書いてください！文末は「です」「ます」「ございます」で終わらせてください！

以下の記事の内容を分析して、以下の形式で回答いたします：

記事タイトル：{$title}

記事内容：
{$content}

回答形式：
## この記事の要約
（記事の要点を3-4行で、誰でも理解できるように分かりやすくまとめてください。専門用語がある場合は、簡単な言葉で説明いたします）

## まとめ
（記事の内容を踏まえて、読者の生活や仕事に役立つような洞察や今後の展望を2-3行で述べてください。具体的で実用的なアドバイスを含めてください）

【重要】文体のルール（絶対に守ってください）：
- 必ず「ですます調」で書いてください
- 文末は必ず「です」「ます」「ございます」で終わってください
- 「〜している」→「〜しております」
- 「〜している」→「〜しております」
- 「〜となる」→「〜となります」
- 「〜になる」→「〜になります」
- 「〜がある」→「〜がございます」
- 「〜が必要」→「〜が必要です」
- 「〜である」→「〜でございます」
- 「〜だろう」→「〜になります」
- 「〜される」→「〜されます」
- 「〜される」→「〜されます」

【絶対禁止事項】：
- 「〜している」で終わる文章は絶対に書かないでください
- 「〜である」で終わる文章は絶対に書かないでください
- 「〜だろう」で終わる文章は絶対に書かないでください
- 「〜れる」で終わる文章は絶対に書かないでください

【最終確認】：
回答を書く前に、すべての文末が「です」「ます」「ございます」で終わっているか必ず確認してください。

注意：
- 要約は事実に基づいて客観的に作成いたします
- まとめは読者の理解を深め、実際に活用できるような内容にしてください
- 日本語で自然で親しみやすい文章にしてください
- 必ず「〜です」「〜ます」の丁寧語を使い、読みやすい文体にしてください
- 難しい概念は身近な例えを使って説明いたします
- 文末は必ず「です」「ます」「ございます」で終わるようにしてください";
    }
    
    /**
     * OpenAI APIのレスポンスから要約とまとめを抽出
     */
    private function parse_openai_response($response) {
        error_log('NewsCrawlerOpenAISummarizer: レスポンス「' . $response . '」でOpenAIレスポンス解析が呼び出されました');
        
        $summary = '';
        $conclusion = '';
        
        // 要約部分を抽出
        if (preg_match('/## この記事の要約\s*(.+?)(?=\s*##|\s*$)/s', $response, $matches)) {
            $summary = trim($matches[1]);
            error_log('NewsCrawlerOpenAISummarizer: 要約が抽出されました: ' . $summary);
        } else {
            error_log('NewsCrawlerOpenAISummarizer: 要約の抽出に失敗いたしました');
        }
        
        // まとめ部分を抽出
        if (preg_match('/## まとめ\s*(.+?)(?=\s*##|\s*$)/s', $response, $matches)) {
            $conclusion = trim($matches[1]);
            error_log('NewsCrawlerOpenAISummarizer: まとめが抽出されました: ' . $conclusion);
        } else {
            error_log('NewsCrawlerOpenAISummarizer: まとめの抽出に失敗いたしました');
        }
        
        // 抽出に失敗した場合は、エラーメッセージを返す
        if (empty($summary) && empty($conclusion)) {
            error_log('NewsCrawlerOpenAISummarizer: 要約とまとめの両方の抽出に失敗いたしました');
            return array('error' => 'AIからの応答形式が正しくありません。要約の生成に失敗しました。しばらく時間をおいてから再試行してください。');
        }
        
        $result = array(
            'summary' => $summary,
            'conclusion' => $conclusion
        );
        
        error_log('NewsCrawlerOpenAISummarizer: 最終解析結果: ' . print_r($result, true));
        return $result;
    }
    
    /**
     * 投稿内容に要約とまとめを追加
     */
    private function append_summary_to_post($content, $summary, $conclusion) {
        error_log('NewsCrawlerOpenAISummarizer: 要約「' . $summary . '」とまとめ「' . $conclusion . '」で投稿への要約追加が呼び出されました');
        
        // 既存の要約とまとめを削除
        $content = $this->remove_existing_summary_and_conclusion($content);
        
        // 要約の段落のみを最初のH2タグの上に挿入
        $summary_paragraph = '<!-- wp:paragraph -->';
        $summary_paragraph .= '<p>' . esc_html($summary) . '</p>';
        $summary_paragraph .= '<!-- /wp:paragraph -->';
        
        // 最初のH2タグを探して、その前に要約を挿入
        $first_h2_pos = strpos($content, '<!-- wp:heading {"level":2} -->');
        
        if ($first_h2_pos !== false) {
            // 最初のH2タグの前に要約を挿入
            $before_h2 = substr($content, 0, $first_h2_pos);
            $after_h2 = substr($content, $first_h2_pos);
            
            $content = $before_h2 . $summary_paragraph . "\n\n" . $after_h2;
            error_log('NewsCrawlerOpenAISummarizer: 要約を最初のH2タグの上に挿入しました');
        } else {
            // H2タグが見つからない場合は、従来通り末尾に追加
            error_log('NewsCrawlerOpenAISummarizer: H2タグが見つからないため、要約を末尾に追加します');
            $content .= "\n\n" . $summary_paragraph;
        }
        
        // まとめセクションのみを末尾に追加
        $conclusion_html = "\n\n";
        $conclusion_html .= '<!-- wp:group {"style":{"spacing":{"margin":{"top":"40px","bottom":"40px"}}}} -->';
        $conclusion_html .= '<div class="wp-block-group" style="margin-top:40px;margin-bottom:40px">';
        
        $conclusion_html .= '<!-- wp:heading {"level":2} -->';
        $conclusion_html .= '<h2>まとめ</h2>';
        $conclusion_html .= '<!-- /wp:heading -->';
        
        $conclusion_html .= '<!-- wp:paragraph -->';
        $conclusion_html .= '<p>' . esc_html($conclusion) . '</p>';
        $conclusion_html .= '<!-- /wp:paragraph -->';
        
        $conclusion_html .= '</div>';
        $conclusion_html .= '<!-- /wp:group -->';
        
        error_log('NewsCrawlerOpenAISummarizer: まとめHTMLが生成されました: ' . $conclusion_html);
        
        // まとめを投稿内容の末尾に追加
        $result = $content . $conclusion_html;
        error_log('NewsCrawlerOpenAISummarizer: 最終コンテンツの長さ: ' . strlen($result) . '文字');
        
        return $result;
    }
    
    /**
     * 既存の要約とまとめを削除
     */
    private function remove_existing_summary_and_conclusion($content) {
        // 既存の要約を削除（最初のH2タグの前にある要約段落）
        $first_h2_pos = strpos($content, '<!-- wp:heading {"level":2} -->');
        if ($first_h2_pos !== false) {
            // 最初のH2タグの前の内容を取得
            $before_h2 = substr($content, 0, $first_h2_pos);
            
            // 要約段落のパターンを検索して削除
            $summary_pattern = '/<!-- wp:paragraph -->\s*<p>.*?<\/p>\s*<!-- \/wp:paragraph -->/s';
            $before_h2 = preg_replace($summary_pattern, '', $before_h2);
            
            // 最初のH2タグ以降の内容を取得
            $after_h2 = substr($content, $first_h2_pos);
            
            // まとめセクションを削除
            $conclusion_pattern = '/<!-- wp:group.*?まとめ.*?<!-- \/wp:group -->/s';
            $after_h2 = preg_replace($conclusion_pattern, '', $after_h2);
            
            // 結合して返す
            $content = $before_h2 . $after_h2;
            error_log('NewsCrawlerOpenAISummarizer: 既存の要約とまとめを削除しました');
        }
        
        return $content;
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
