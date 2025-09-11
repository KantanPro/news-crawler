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

        // WP-Cron が無効な環境では即時同期実行にフォールバック
        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            error_log('NewsCrawlerOpenAISummarizer: WP-Cronが無効のため同期実行にフォールバックします（post_id=' . $post_id . '）');
            $this->generate_summary($post_id);
            return;
        }

        // 非同期スケジュール。失敗時は同期実行にフォールバック
        if (!$this->schedule_event_with_retry($post_id)) {
            error_log('NewsCrawlerOpenAISummarizer: 非同期スケジュールに失敗。同期実行にフォールバックします（post_id=' . $post_id . '）');
            $this->generate_summary($post_id);
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

            // 投稿にカテゴリーが設定されているかチェック（固定ページの場合はスキップ）
            $current_categories = wp_get_post_categories($post_id);
            if (empty($current_categories) && $post->post_type === 'post') {
                error_log('NewsCrawlerOpenAISummarizer: 投稿にカテゴリーが設定されていません。');
                return array('error' => 'カテゴリーを設定してください');
            }

            // カテゴリーを保存（固定ページの場合は空配列）
            update_post_meta($post_id, '_news_summary_categories', $current_categories);

            // 投稿内容から要約とまとめを生成（キーワード最適化対応）
            $summary_result = $this->generate_summary_with_openai($post->post_content, $post->post_title, $post_id);

            error_log('NewsCrawlerOpenAISummarizer: OpenAI結果: ' . print_r($summary_result, true));

            // 課金制限エラーの処理
            if (is_array($summary_result) && isset($summary_result['error']) && strpos($summary_result['error'], '課金制限') !== false) {
                $this->notify_billing_limit_error();
                return false;
            }

            // 要約またはまとめのどちらか一方でも取得できていれば更新を実施
            if ($summary_result && ( !empty($summary_result['summary']) || !empty($summary_result['conclusion']) )) {
                error_log('NewsCrawlerOpenAISummarizer: 要約生成が成功いたしました。投稿内容を更新いたします');

                // 投稿内容に要約とまとめを追加
                $updated_content = $this->append_summary_to_post($post->post_content, $summary_result['summary'] ?? '', $summary_result['conclusion'] ?? '');

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
                    if (!empty($summary_result['summary'])) {
                        update_post_meta($post_id, '_openai_summary_text', $summary_result['summary']);
                    }
                    if (!empty($summary_result['conclusion'])) {
                        update_post_meta($post_id, '_openai_conclusion_text', $summary_result['conclusion']);
                    }

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
     * OpenAI APIを使用して要約とまとめを生成（強化版通信エラーハンドリング）
     */
    private function generate_summary_with_openai($content, $title, $post_id = null) {
        error_log('NewsCrawlerOpenAISummarizer: タイトル「' . $title . '」でOpenAI要約生成が呼び出されました');

        if (empty($this->api_key)) {
            error_log('NewsCrawlerOpenAISummarizer: APIキーが空です');
            return array('error' => 'OpenAI APIキーが設定されていません。管理画面で設定してください。');
        }

        // APIキーの形式検証
        if (!$this->validate_api_key_format($this->api_key)) {
            return array('error' => 'OpenAI APIキーの形式が無効です。正しいAPIキーを設定してください。');
        }

        // 投稿内容をテキストとして抽出（HTMLタグを除去）
        $text_content = wp_strip_all_tags($content);

        // YouTubeまとめ投稿の場合は、メタに保存した説明テキストを要約ソースに追加
        $is_youtube_post = $post_id ? get_post_meta($post_id, '_youtube_summary', true) : false;
        if ($is_youtube_post) {
            $youtube_source = $post_id ? get_post_meta($post_id, '_youtube_summary_source', true) : '';
            if (!empty($youtube_source)) {
                // 本文テキストと結合してプロンプトの材料を充実させる
                $text_content = trim($text_content . "\n\n" . $youtube_source);
            }
        }
        error_log('NewsCrawlerOpenAISummarizer: テキスト内容の長さ: ' . mb_strlen($text_content) . '文字');

        // 内容が短すぎる場合はスキップ
        if (mb_strlen($text_content) < 80) {
            error_log('NewsCrawlerOpenAISummarizer: 内容が短すぎるためスキップいたします（閾値:80文字、現在: ' . mb_strlen($text_content) . '文字）');
            return array('error' => '記事の内容が短すぎるため、要約を生成できません。記事本文を充実させてください。');
        }

        // YouTube投稿かどうかを再判定（上で取得済みだが安全のため）
        $is_youtube_post = $post_id ? get_post_meta($post_id, '_youtube_summary', true) : false;
        
        // プロンプトを作成（キーワード最適化対応）
        if ($is_youtube_post) {
            $prompt = $this->create_youtube_summary_prompt($text_content, $title, $post_id);
            error_log('NewsCrawlerOpenAISummarizer: YouTube投稿用プロンプトが作成されました。長さ: ' . mb_strlen($prompt) . '文字');
        } else {
            $prompt = $this->create_summary_prompt($text_content, $title, $post_id);
            error_log('NewsCrawlerOpenAISummarizer: 通常投稿用プロンプトが作成されました。長さ: ' . mb_strlen($prompt) . '文字');
        }

        // OpenAI APIを呼び出し（強化版指数バックオフ付き）
        $max_retries = 5; // 再試行回数を増やす
        $base_delay = 2; // 基本待機時間を延ばす
        $max_delay = 60; // 最大待機時間（1分）

        for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
            error_log('NewsCrawlerOpenAISummarizer: 試行回数 ' . $attempt . '/' . $max_retries);

            // リクエスト間の待機（2回目以降）
            if ($attempt > 1) {
                $delay = min($base_delay * pow(2, $attempt - 2), $max_delay); // 指数バックオフ（最大60秒）
                $jitter = mt_rand(0, 1000) / 1000; // ジッターを追加（0-1秒）
                $total_delay = $delay + $jitter;

                error_log('NewsCrawlerOpenAISummarizer: 通信エラー対策で ' . round($total_delay, 2) . '秒待機します');
                usleep($total_delay * 1000000); // マイクロ秒に変換
            }

            // タイムアウトを動的に設定（試行回数に応じて延ばす）
            $timeout = 30 + ($attempt * 10); // 30秒から開始、試行ごとに10秒延ばす
            $timeout = min($timeout, 120); // 最大120秒

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
                            'content' => 'あなたは親しみやすく、分かりやすい文章を書くのが得意なニュース編集者です。難しい専門用語は避けて、誰でも理解できるような要約とまとめを作成いたします。絶対に「ですます調」で書いてください。文末は必ず「です」「ます」「ございます」で終わらせてください。禁止：「〜している」「〜である」「〜だろう」「〜れる」で終わる文章は絶対に書かないでください。回答を書く前に、すべての文末が「です」「ます」「ございます」で終わっているか必ず確認してください。'
                        ),
                        array(
                            'role' => 'user',
                            'content' => $prompt
                        )
                    ),
                    'max_tokens' => $this->max_tokens,
                    'temperature' => 0.3
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

                error_log('NewsCrawlerOpenAISummarizer: 試行' . $attempt . ' - ネットワークエラー: ' . $error_code . ' - ' . $error_message);

                // エラーの種類に応じた処理
                if (strpos($error_message, 'timed out') !== false || strpos($error_message, 'timeout') !== false) {
                    $user_message = 'OpenAI APIとの通信がタイムアウトしました。インターネット接続を確認してください。';
                } elseif (strpos($error_message, 'could not resolve host') !== false) {
                    $user_message = 'OpenAI APIサーバーに接続できません。DNSまたはネットワーク設定を確認してください。';
                } elseif (strpos($error_message, 'SSL') !== false) {
                    $user_message = 'SSL接続エラーが発生しました。証明書の有効性を確認してください。';
                } else {
                    $user_message = 'OpenAI APIへの通信に失敗しました: ' . $error_message;
                }

                // ネットワークエラーの場合は再試行
                if ($attempt < $max_retries) {
                    continue;
                }
                return array('error' => $user_message);
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            error_log('NewsCrawlerOpenAISummarizer: 試行' . $attempt . ' - APIレスポンスコード: ' . $response_code);

            // HTTPステータスコードに応じた処理
            if ($response_code === 429) {
                // レート制限エラー
                error_log('NewsCrawlerOpenAISummarizer: レート制限エラーが発生しました。試行' . $attempt . '/' . $max_retries);

                // レスポンスヘッダーからリトライ時間を取得
                $retry_after = wp_remote_retrieve_header($response, 'retry-after');
                if ($retry_after) {
                    $wait_time = min(intval($retry_after), $max_delay);
                    error_log('NewsCrawlerOpenAISummarizer: Retry-Afterヘッダーに従い ' . $wait_time . '秒待機します');
                    sleep($wait_time);
                } elseif ($attempt < $max_retries) {
                    // 指数バックオフ
                    $rate_limit_delay = min($base_delay * pow(2, $attempt), $max_delay);
                    error_log('NewsCrawlerOpenAISummarizer: レート制限対策で ' . $rate_limit_delay . '秒待機します');
                    sleep($rate_limit_delay);
                    continue;
                }

                if ($attempt >= $max_retries) {
                    $user_friendly_message = 'OpenAI APIのレート制限に達しました。しばらく時間をおいてから再度お試しください。';
                    error_log('NewsCrawlerOpenAISummarizer: レート制限エラー - 最大再試行回数に達しました');

                    // 管理者に通知
                    $this->notify_admin_rate_limit_error();
                    return array('error' => $user_friendly_message);
                }
            } elseif ($response_code === 401) {
                // 認証エラー
                error_log('NewsCrawlerOpenAISummarizer: APIキー認証エラー');
                return array('error' => 'OpenAI APIキーが無効です。正しいAPIキーを設定してください。');
            } elseif ($response_code === 403) {
                // アクセス拒否
                error_log('NewsCrawlerOpenAISummarizer: APIアクセス拒否エラー');
                return array('error' => 'OpenAI APIへのアクセスが拒否されました。アカウントの状態を確認してください。');
            } elseif ($response_code >= 500 && $response_code < 600) {
                // サーバーエラー
                error_log('NewsCrawlerOpenAISummarizer: OpenAIサーバーエラー: ' . $response_code);

                if ($attempt < $max_retries) {
                    continue;
                }

                $user_message = 'OpenAIサーバーで一時的なエラーが発生しています。しばらく時間をおいてから再度お試しください。';
                return array('error' => $user_message);
            } elseif ($response_code >= 400 && $response_code < 500) {
                // クライアントエラー（429以外）
                error_log('NewsCrawlerOpenAISummarizer: クライアントエラー: ' . $response_code);
                break; // 再試行せず終了
            }

            // 成功または4xxエラーの場合はループを抜ける
            if ($response_code === 200 || ($response_code >= 400 && $response_code < 500)) {
                break;
            }
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
     * OpenAI API用のプロンプトを作成（キーワード最適化対応）
     */
    private function create_summary_prompt($content, $title, $post_id = null) {
        // キーワード最適化の設定を取得
        $keyword_instructions = '';
        if ($post_id && class_exists('NewsCrawlerSeoSettings')) {
            $seo_settings = get_option('news_crawler_seo_settings', array());
            $keyword_optimization_enabled = isset($seo_settings['keyword_optimization_enabled']) ? $seo_settings['keyword_optimization_enabled'] : false;
            $target_keywords = isset($seo_settings['target_keywords']) ? trim($seo_settings['target_keywords']) : '';
            
            if ($keyword_optimization_enabled && !empty($target_keywords)) {
                // キーワードを配列に変換
                $keywords = array_map('trim', preg_split('/[,\n\r]+/', $target_keywords));
                $keywords = array_filter($keywords); // 空の要素を除去
                
                if (!empty($keywords)) {
                    $keyword_list = implode('、', $keywords);
                    $keyword_instructions = "

【SEO最適化指示】
以下のキーワードを自然に含めて要約とまとめを作成してください：
ターゲットキーワード：{$keyword_list}

注意事項：
- キーワードは自然な文章の流れの中で使用してください
- 無理にキーワードを詰め込まず、読みやすさを優先してください
- キーワードの密度は適切に保ち、過度な繰り返しは避けてください
- 要約とまとめの両方で、関連するキーワードを効果的に使用してください";
                }
            }
        }
        
        return "【最重要】必ず「ですます調」で書いてください！文末は「です」「ます」「ございます」で終わらせてください！{$keyword_instructions}

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
     * YouTube投稿用の要約プロンプトを作成
     */
    private function create_youtube_summary_prompt($content, $title, $post_id = null) {
        // キーワード最適化のためのキーワード取得
        $keyword_instructions = '';
        if ($post_id) {
            $seo_settings = get_option('news_crawler_seo_settings', array());
            if (isset($seo_settings['keywords']) && !empty($seo_settings['keywords'])) {
                $keywords = $seo_settings['keywords'];
                if (!empty($keywords)) {
                    $keyword_list = implode('、', $keywords);
                    $keyword_instructions = "

【SEO最適化指示】
以下のキーワードを自然に含めて要約とまとめを作成してください：
ターゲットキーワード：{$keyword_list}

注意事項：
- キーワードは自然な文章の流れの中で使用してください
- 無理にキーワードを詰め込まず、読みやすさを優先してください
- キーワードの密度は適切に保ち、過度な繰り返しは避けてください
- 要約とまとめの両方で、関連するキーワードを効果的に使用してください";
                }
            }
        }
        
        return "【最重要】必ず「ですます調」で書いてください！文末は「です」「ます」「ございます」で終わらせてください！{$keyword_instructions}

以下のYouTube動画まとめ記事の内容を分析して、以下の形式で回答いたします：

記事タイトル：{$title}

記事内容：
{$content}

回答形式：
## この記事の要約
（動画の内容を3-4行で、誰でも理解できるように分かりやすくまとめてください。各動画の要点を簡潔に説明いたします）

## まとめ
（紹介された動画の内容を踏まえて、読者が実際に活用できるような洞察や今後の展望を2-3行で述べてください。動画視聴のメリットや関連情報の探し方など、具体的で実用的なアドバイスを含めてください）

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
- 要約は動画の内容に基づいて客観的に作成いたします
- まとめは読者の理解を深め、実際に動画を活用できるような内容にしてください
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
        
        // 正規表現を寛容化：見出しの全角/半角スペース、バリエーション、「まとめです」などにも対応
        // 要約部分を抽出
        if (preg_match('/##\s*この記事の要約\s*(.+?)(?=\s*##|\s*$)/su', $response, $matches)) {
            $summary = trim($matches[1]);
            error_log('NewsCrawlerOpenAISummarizer: 要約が抽出されました: ' . $summary);
        } else {
            error_log('NewsCrawlerOpenAISummarizer: 要約の抽出に失敗いたしました');
        }
        
        // まとめ部分を抽出（「結論」「まとめです」「総括」等も許容）
        if (preg_match('/##\s*(まとめ|結論|総括)\s*(.+?)(?=\s*##|\s*$)/su', $response, $matches)) {
            // グループ2が本文
            $conclusion = trim(isset($matches[2]) ? $matches[2] : $matches[1]);
            error_log('NewsCrawlerOpenAISummarizer: まとめが抽出されました: ' . $conclusion);
        } else {
            error_log('NewsCrawlerOpenAISummarizer: まとめの抽出に失敗いたしました');
        }
        
        // フォールバック：本文の最後の見出し以降を「まとめ」とみなす（何も取れなかった場合）
        if (empty($conclusion)) {
            if (preg_match('/##\s*[^#\n]+\n+(.+)$/su', $response, $m)) {
                $fallback = trim($m[1]);
                if (mb_strlen($fallback) >= 20) {
                    $conclusion = $fallback;
                    error_log('NewsCrawlerOpenAISummarizer: まとめ抽出フォールバックを適用しました');
                }
            }
        }

        // どちらも空ならエラー
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
            
            // まとめセクションを削除（複数回実行してすべて削除）
            $conclusion_pattern = '/<!-- wp:group[^>]*>.*?<h2>まとめ<\/h2>.*?<!-- \/wp:group -->/s';
            $previous_content = '';
            while ($previous_content !== $after_h2) {
                $previous_content = $after_h2;
                $after_h2 = preg_replace($conclusion_pattern, '', $after_h2);
            }
            
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
