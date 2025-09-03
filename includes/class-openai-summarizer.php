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
        // フック実行タイミングを後ろにずらして、他の処理（投稿メタの設定など）が先に走るようにする
        add_action('wp_insert_post', array($this, 'maybe_generate_summary'), 20, 3);

        // デバッグ用: プラグインの初期化時に設定をログ出力
        $this->log_initial_settings();
    }
    
    /**
     * 初期設定をログに出力
     */
    private function log_initial_settings() {
        $basic_settings = get_option('news_crawler_basic_settings', array());
        $masked_settings = $basic_settings;
        if (isset($masked_settings['openai_api_key'])) {
            $masked_settings['openai_api_key'] = '***masked***';
        }
        error_log('NewsCrawlerOpenAISummarizer: 初期設定 - ' . print_r($masked_settings, true));
    }

    /**
     * OpenAI APIキーが設定されているか確認
     */
    private function validate_api_key() {
        if (empty($this->api_key)) {
            error_log('NewsCrawlerOpenAISummarizer: OpenAI APIキーが設定されていません。管理画面で設定してください。');
            return false;
        }
        return true;
    }

    /**
     * APIキーの形式を検証
     */
    private function validate_api_key_format($api_key) {
        // APIキーが文字列であり、一定の長さを持つか確認
        if (!is_string($api_key) || strlen($api_key) < 20) {
            error_log('NewsCrawlerOpenAISummarizer: APIキーの形式が無効です。');
            return false;
        }
        return true;
    }

    /**
     * 投稿作成後に要約とまとめを生成するかどうかを判定（修正版）
     */
    public function maybe_generate_summary($post_id, $post, $update) {
        error_log('NewsCrawlerOpenAISummarizer: maybe_generate_summaryが投稿ID ' . $post_id . ' で呼び出されました。更新: ' . ($update ? 'true' : 'false'));

        if (!$this->validate_api_key()) {
            return;
        }

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

        // 投稿の公開ステータスを確認
        if ($post->post_status !== 'publish') {
            error_log('NewsCrawlerOpenAISummarizer: 投稿が公開状態ではないためスキップします: ' . $post->post_status);
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

        // YouTube投稿作成中の場合は少し待機してから再確認
        if (!$is_news_summary && !$is_youtube_summary) {
            $is_creating_youtube = get_transient('news_crawler_creating_youtube_post');
            if ($is_creating_youtube) {
                error_log('NewsCrawlerOpenAISummarizer: YouTube投稿作成中です。少し待機してから再確認します。');
                // 少し待機してから再確認
                sleep(1);
                $is_youtube_summary = get_post_meta($post_id, '_youtube_summary', true);
                error_log('NewsCrawlerOpenAISummarizer: 再確認後の_youtube_summary: ' . ($is_youtube_summary ? 'true' : 'false'));
            }

            // デバッグ用: News Crawler関連のメタデータがない場合もAI生成を実行
            if (!$is_news_summary && !$is_youtube_summary) {
                error_log('NewsCrawlerOpenAISummarizer: News Crawlerメタデータが見つかりませんでしたが、デバッグ用にAI生成を続行します');
                // ここでreturnせず、AI生成を続行
            }
        }

        // ライセンスチェック - 一時的に無効化
        /*
        $license_status = 'no_license_manager';
        if (class_exists('NewsCrawler_License_Manager')) {
            try {
                $license_manager = NewsCrawler_License_Manager::get_instance();
                $license_status = $license_manager->is_ai_summary_enabled() ? 'enabled' : 'disabled';
            } catch (Exception $e) {
                $license_status = 'error:' . $e->getMessage();
            }
        }
        error_log('NewsCrawlerOpenAISummarizer: ライセンス状態: ' . $license_status);
        if (class_exists('NewsCrawler_License_Manager') && $license_status !== 'enabled') {
            error_log('NewsCrawlerOpenAISummarizer: ライセンスが無効なため、AI要約機能をスキップします');
            return;
        }
        */
        error_log('NewsCrawlerOpenAISummarizer: ライセンスチェックを一時的に無効化しました。');

        // OpenAI APIキーが設定されていない場合はスキップ
        if (empty($this->api_key)) {
            error_log('NewsCrawlerOpenAISummarizer: OpenAI APIキーが設定されていません。要約生成をスキップします。');
            return;
        }

        // 基本設定で要約生成が無効になっている場合はスキップ
        $basic_settings = get_option('news_crawler_basic_settings', array());
        $auto_summary_enabled = isset($basic_settings['auto_summary_generation']) ? $basic_settings['auto_summary_generation'] : true; // デフォルトで有効
        error_log('NewsCrawlerOpenAISummarizer: 設定 - auto_summary_enabled=' . ($auto_summary_enabled ? 'true' : 'false'));
        if (!$auto_summary_enabled) {
            error_log('NewsCrawlerOpenAISummarizer: 要約自動生成が無効になっています');
            return;
        }

        error_log('NewsCrawlerOpenAISummarizer: すべてのチェックが完了いたしました。投稿ID ' . $post_id . ' の要約生成を非同期で実行いたします');

        // 非同期処理のみ実行（レート制限回避のため同期処理を削除）
        if (!$this->schedule_event_with_retry($post_id)) {
            error_log('NewsCrawlerOpenAISummarizer: 非同期処理のスケジュールに失敗しました。投稿ID: ' . $post_id);
        }
    }
    
    /**
     * 課金制限エラー時の通知
     */
    private function notify_billing_limit_error() {
        error_log('NewsCrawlerOpenAISummarizer: OpenAIの課金制限に達しました。管理者に通知してください。');
        // 必要に応じて、管理者にメールを送信するコードを追加
        // wp_mail(admin_email(), 'OpenAI Billing Limit Reached', 'OpenAIの課金制限に達しました。アカウントを確認してください。');
    }

    /**
     * 課金制限エラー時の通知を管理者に表示
     */
    private function notify_admin_billing_limit_error() {
        error_log('NewsCrawlerOpenAISummarizer: OpenAIの課金制限に達しました。管理者に通知します。');

        // WordPress管理画面に通知を追加
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error is-dismissible">
                    <p>OpenAIの課金制限に達しました。アカウントの請求情報を確認してください。</p>
                  </div>';
        });
    }

    /**
     * 要約生成のログを強化
     */
    public function generate_summary($post_id) {
        error_log('NewsCrawlerOpenAISummarizer: generate_summary メソッド開始 - 投稿ID: ' . $post_id);
        
        // 投稿データの取得
        $post = get_post($post_id);
        if (!$post) {
            error_log('NewsCrawlerOpenAISummarizer: 投稿が見つかりません - 投稿ID: ' . $post_id);
            return false;
        }
        
        error_log('NewsCrawlerOpenAISummarizer: 投稿データ取得成功 - タイトル: ' . $post->post_title . ', 本文長さ: ' . strlen($post->post_content));
        
        // OpenAI API呼び出し前のチェック
        if (empty($this->api_key)) {
            error_log('NewsCrawlerOpenAISummarizer: APIキーが設定されていません。処理を中断します。');
            return false;
        }
        
        error_log('NewsCrawlerOpenAISummarizer: APIキー確認済み。要約生成を開始します。');

        // 既に要約が生成されている場合はスキップ
        $summary_generated = get_post_meta($post_id, '_openai_summary_generated', true);
        if ($summary_generated) {
            error_log('NewsCrawlerOpenAISummarizer: 投稿ID ' . $post_id . ' の要約は既に生成されております');
            return false;
        }

        // 処理中のフラグをチェック（重複実行防止）
        $processing_flag = get_transient('news_crawler_ai_processing_' . $post_id);
        if ($processing_flag) {
            error_log('NewsCrawlerOpenAISummarizer: 投稿ID ' . $post_id . ' は現在処理中です。スキップします。');
            // デバッグ用に一時的に処理中フラグをクリア
            delete_transient('news_crawler_ai_processing_' . $post_id);
            error_log('NewsCrawlerOpenAISummarizer: デバッグ用に処理中フラグをクリアしました。投稿ID: ' . $post_id);
        }

        // 処理中フラグを設定（10分間有効）
        set_transient('news_crawler_ai_processing_' . $post_id, true, 600);
        error_log('NewsCrawlerOpenAISummarizer: 投稿ID ' . $post_id . ' の処理中フラグを設定しました');

        try {
            error_log('NewsCrawlerOpenAISummarizer: 投稿ID ' . $post_id . ' のOpenAI要約生成を開始いたします');

            // 投稿の本文が空かチェック
            if (empty(trim(wp_strip_all_tags($post->post_content)))) {
                error_log('NewsCrawlerOpenAISummarizer: 投稿の本文が空です。要約生成をスキップします。');
                return array('error' => '本文を入力してから実行してください');
            }

            // 投稿にカテゴリーが設定されているかチェック
            $current_categories = wp_get_post_categories($post_id);
            if (empty($current_categories)) {
                error_log('NewsCrawlerOpenAISummarizer: 投稿にカテゴリーが設定されていません。');
                return array('error' => 'カテゴリーを設定してください');
            }

            // カテゴリーを保存
            update_post_meta($post_id, '_news_summary_categories', $current_categories);

            // 投稿内容から要約とまとめを生成
            $summary_result = $this->generate_summary_with_openai($post->post_content, $post->post_title);

            error_log('NewsCrawlerOpenAISummarizer: OpenAI結果: ' . print_r($summary_result, true));

            // 課金制限エラーの処理
            if (is_array($summary_result) && isset($summary_result['error']) && strpos($summary_result['error'], '課金制限') !== false) {
                $this->notify_billing_limit_error();
                return false;
            }

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

                    // 処理中フラグをクリア
                    delete_transient('news_crawler_ai_processing_' . $post_id);
                    error_log('NewsCrawlerOpenAISummarizer: 投稿ID ' . $post_id . ' の処理中フラグをクリアしました');

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
            error_log('NewsCrawlerOpenAISummarizer: Exception trace: ' . $e->getTraceAsString());

            // エラー発生時も処理中フラグをクリア
            delete_transient('news_crawler_ai_processing_' . $post_id);
            error_log('NewsCrawlerOpenAISummarizer: エラー発生により投稿ID ' . $post_id . ' の処理中フラグをクリアしました');
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

        // 内容が短すぎる場合はスキップ（デバッグ用に一時的に無効化）
        if (mb_strlen($text_content) < 80) {
            error_log('NewsCrawlerOpenAISummarizer: 内容が短すぎるためスキップいたします（閾値:80文字、現在: ' . mb_strlen($text_content) . '文字）');
            error_log('NewsCrawlerOpenAISummarizer: デバッグ用に短いコンテンツでもAI生成を続行します');
            // デバッグ用に短いコンテンツでも続行
        }

        // プロンプトを作成
        $prompt = $this->create_summary_prompt($text_content, $title);
        error_log('NewsCrawlerOpenAISummarizer: プロンプトが作成されました。長さ: ' . mb_strlen($prompt) . '文字');

        // OpenAI APIを呼び出し（指数バックオフ付き）
        $max_retries = 3;
        $base_delay = 1; // 基本待機時間（秒）

        for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
            error_log('NewsCrawlerOpenAISummarizer: 試行回数 ' . $attempt . '/' . $max_retries);

            // リクエスト間の待機（2回目以降）
            if ($attempt > 1) {
                $delay = $base_delay * pow(2, $attempt - 2); // 指数バックオフ
                $jitter = mt_rand(0, 1000) / 1000; // ジッターを追加（0-1秒）
                $total_delay = $delay + $jitter;

                error_log('NewsCrawlerOpenAISummarizer: レート制限対策で ' . round($total_delay, 2) . '秒待機します');
                usleep($total_delay * 1000000); // マイクロ秒に変換
            }

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
                $error_message = 'OpenAI APIへの通信に失敗しました: ' . $response->get_error_message();
                error_log('NewsCrawlerOpenAISummarizer: 試行' . $attempt . ' - ' . $error_message);

                // ネットワークエラーの場合は再試行
                if ($attempt < $max_retries) {
                    continue;
                }
                return array('error' => $error_message);
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            error_log('NewsCrawlerOpenAISummarizer: 試行' . $attempt . ' - APIレスポンスコード: ' . $response_code);

            // 429エラー（レート制限）の場合
            if ($response_code === 429) {
                error_log('NewsCrawlerOpenAISummarizer: レート制限エラーが発生しました。試行' . $attempt . '/' . $max_retries);

                if ($attempt < $max_retries) {
                    // より長い待機時間を設定
                    $rate_limit_delay = $base_delay * pow(2, $attempt);
                    error_log('NewsCrawlerOpenAISummarizer: レート制限対策で ' . $rate_limit_delay . '秒待機します');
                    sleep($rate_limit_delay);
                    continue;
                } else {
                    // 最大再試行回数に達した場合
                    $user_friendly_message = 'OpenAI APIのレート制限に達しました。しばらく時間をおいてから再度お試しください。';
                    error_log('NewsCrawlerOpenAISummarizer: レート制限エラー - 最大再試行回数に達しました');
                    return array('error' => $user_friendly_message);
                }
            }

            // 5xxエラー（サーバーエラー）の場合も再試行
            if ($response_code >= 500 && $response_code < 600) {
                error_log('NewsCrawlerOpenAISummarizer: サーバーエラーが発生しました。試行' . $attempt . '/' . $max_retries);

                if ($attempt < $max_retries) {
                    continue;
                }
            }

            // 成功または4xxエラーの場合はループを抜ける
            break;
        }

        // 最終的なレスポンスを評価
        if (is_wp_error($response)) {
            return array('error' => 'OpenAI API呼び出しエラー: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        error_log('NewsCrawlerOpenAISummarizer: OpenAI HTTP response code: ' . $response_code);
        error_log('NewsCrawlerOpenAISummarizer: APIレスポンス本文の長さ: ' . strlen($body) . '文字');
        error_log('NewsCrawlerOpenAISummarizer: APIレスポンス生データ（抜粋）: ' . substr($body, 0, 2000));

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $json_error = json_last_error_msg();
            error_log('NewsCrawlerOpenAISummarizer: JSONデコードエラー: ' . $json_error);
            error_log('NewsCrawlerOpenAISummarizer: レスポンス本文: ' . $body);
            return array('error' => 'JSONデコードエラー: ' . $json_error);
        }

        error_log('NewsCrawlerOpenAISummarizer: APIレスポンスデータ: ' . print_r($data, true));

        if (!$data || !isset($data['choices'][0]['message']['content'])) {
            error_log('NewsCrawlerOpenAISummarizer: OpenAI APIレスポンスの解析に失敗しました - HTTP code: ' . $response_code);
            $error_message = isset($data['error']['message']) ? $data['error']['message'] : '不明なエラー';
            error_log('NewsCrawlerOpenAISummarizer: OpenAI error message: ' . $error_message);
            return array('error' => 'OpenAI APIからの応答が不正です。' . $error_message);
        }

        $response_content = $data['choices'][0]['message']['content'];
        error_log('NewsCrawlerOpenAISummarizer: OpenAIレスポンス内容: ' . substr($response_content, 0, 2000));

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

    /**
     * OpenAI API呼び出しのエラーハンドリングを強化
     */
    private function handle_openai_error($response, $attempt) {
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($response_code == 429 || ($response_code >= 500 && $response_code < 600)) {
            error_log('NewsCrawlerOpenAISummarizer: OpenAI APIが一時的なエラーを返しました。HTTPコード: ' . $response_code . ' 試行回数: ' . $attempt);
            error_log('NewsCrawlerOpenAISummarizer: レスポンス抜粋: ' . substr($body, 0, 1000));
            return true; // 再試行可能
        }

        error_log('NewsCrawlerOpenAISummarizer: OpenAI APIレスポンスの解析に失敗しました - HTTPコード: ' . $response_code);
        error_log('NewsCrawlerOpenAISummarizer: レスポンス本文: ' . $body);
        return false; // 再試行不要
    }

    /**
     * 非同期処理のスケジュールをリトライ
     */
    private function schedule_event_with_retry($post_id, $max_retries = 3) {
        $attempt = 0;
        while ($attempt < $max_retries) {
            $attempt++;
            if (wp_schedule_single_event(time() + 10, 'news_crawler_generate_summary', array($post_id))) {
                return true;
            }
            error_log('NewsCrawlerOpenAISummarizer: 非同期処理のスケジュールに失敗しました。リトライ回数: ' . $attempt);
            sleep(1); // リトライ間隔
        }
        error_log('NewsCrawlerOpenAISummarizer: 非同期処理のスケジュールに失敗しました。最大リトライ回数に到達しました。');
        return false;
    }

    /**
     * Enhanced retry logic for OpenAI API calls
     */
    private function enhanced_retry_logic($response, $attempt, $max_retries) {
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($response_code == 429 || ($response_code >= 500 && $response_code < 600)) {
            error_log('NewsCrawlerOpenAISummarizer: OpenAI APIが一時的なエラーを返しました。HTTPコード: ' . $response_code . ' 試行回数: ' . $attempt);
            error_log('NewsCrawlerOpenAISummarizer: レスポンス抜粋: ' . substr($body, 0, 1000));

            if ($attempt >= $max_retries) {
                error_log('NewsCrawlerOpenAISummarizer: 最大再試行回数に達しました。処理を中断します。');
                return false;
            }

            // Exponential backoff with jitter
            $jitter = rand(0, 1000) / 1000; // 0〜1秒のジッター
            $sleep = pow(2, $attempt) + $jitter;
            error_log('NewsCrawlerOpenAISummarizer: 再試行前に待機します: ' . $sleep . '秒');
            usleep((int)($sleep * 1000000));

            return true; // Retry allowed
        }

        if ($response_code == 429 && strpos($body, 'insufficient_quota') !== false) {
            error_log('NewsCrawlerOpenAISummarizer: 課金制限エラーを検出しました。管理者に通知します。');
            $this->notify_admin_billing_limit_error();
            return false; // Stop retrying
        }

        return false; // No retry
    }
}

// クラスの初期化
global $news_crawler_openai_summarizer;
$news_crawler_openai_summarizer = new NewsCrawlerOpenAISummarizer();

// 非同期処理のフックを追加（スケジュール実行時にインスタンスメソッドを呼び出す）
add_action('news_crawler_generate_summary', function($post_id) {
    global $news_crawler_openai_summarizer;
    if ($news_crawler_openai_summarizer instanceof NewsCrawlerOpenAISummarizer) {
        $news_crawler_openai_summarizer->generate_summary($post_id);
    }
});
