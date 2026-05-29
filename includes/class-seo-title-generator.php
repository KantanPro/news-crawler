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
        
        // 投稿タイプがpostまたはpageでない場合はスキップ
        if (!in_array($post->post_type, array('post', 'page'))) {
            return;
        }
        
        // 既にSEOタイトルが生成されている場合はスキップ
        if (get_post_meta($post_id, '_seo_title_generated', true)) {
            return;
        }
        
        // ニュースまたはYouTube投稿かどうかを確認
        $is_news_summary = get_post_meta($post_id, '_news_summary', true);
        $is_youtube_summary = get_post_meta($post_id, '_youtube_summary', true);

        // デバッグ用: News Crawlerメタデータがない場合もSEOタイトル生成を実行
        if (!$is_news_summary && !$is_youtube_summary) {
            error_log('NewsCrawlerSEOTitleGenerator: News Crawlerメタデータが見つかりませんでしたが、デバッグ用にSEOタイトル生成を続行します');
            // ここでreturnせず、SEOタイトル生成を続行
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
        
        // 投稿にカテゴリーが設定されているかチェック（固定ページの場合はスキップ）
        $current_categories = wp_get_post_categories($post_id);
        if (empty($current_categories) && $post->post_type === 'post') {
            return array('error' => 'カテゴリーを設定してください');
        }
        
        // 現在のカテゴリーを保存（固定ページの場合は空配列）
        $saved_categories = $current_categories;
        
        // News Crawlerで設定されているジャンル名を取得
        $genre_name = $this->get_news_crawler_genre_name($post_id);
        
        // 投稿内容からSEOタイトルを生成（キーワード最適化対応）
        $seo_title = $this->generate_seo_title_with_ai($post, $genre_name, $post_id);
        
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
     * News Crawlerのジャンル設定からジャンル名を取得
     */
    private function get_news_crawler_genre_name($post_id) {
        error_log('NewsCrawlerSEOTitleGenerator: get_news_crawler_genre_name called for post_id: ' . $post_id);

        // 投稿に保存されているNews CrawlerジャンルIDを取得
        $genre_id = get_post_meta($post_id, '_news_crawler_genre_id', true);
        error_log('NewsCrawlerSEOTitleGenerator: Found genre_id in post meta: ' . $genre_id);

        if (!empty($genre_id)) {
            // ジャンル設定からジャンル名を取得
            $genre_settings = get_option('news_crawler_genre_settings', array());
            error_log('NewsCrawlerSEOTitleGenerator: Genre settings loaded: ' . print_r($genre_settings, true));

            if (isset($genre_settings[$genre_id]) && isset($genre_settings[$genre_id]['genre_name'])) {
                $genre_name = $genre_settings[$genre_id]['genre_name'];
                error_log('NewsCrawlerSEOTitleGenerator: Found genre_name from settings: ' . $genre_name);
                return $genre_name;
            } else {
                error_log('NewsCrawlerSEOTitleGenerator: Genre name not found in settings for genre_id: ' . $genre_id);
            }
        } else {
            error_log('NewsCrawlerSEOTitleGenerator: No genre_id found in post meta');
        }

        // News CrawlerのジャンルIDが見つからない場合は、WordPressカテゴリーから取得（後方互換性）
        $categories = wp_get_post_categories($post_id, array('orderby' => 'term_order'));
        error_log('NewsCrawlerSEOTitleGenerator: Fallback to WordPress categories: ' . print_r($categories, true));

        if (!empty($categories) && is_array($categories)) {
            // 最初の（一番上）のカテゴリーを取得
            $first_category_id = $categories[0];
            $first_category = get_category($first_category_id);
            if ($first_category) {
                $category_name = $first_category->name;
                error_log('NewsCrawlerSEOTitleGenerator: Using WordPress category name: ' . $category_name);

                // カテゴリー名が「blog」の場合は、より適切な名前を使用
                if (strtolower($category_name) === 'blog') {
                    error_log('NewsCrawlerSEOTitleGenerator: Category name is "blog", trying to find better genre name');

                    // 他のカテゴリーがある場合はそれを使用
                    if (count($categories) > 1) {
                        $second_category = get_category($categories[1]);
                        if ($second_category) {
                            $category_name = $second_category->name;
                            error_log('NewsCrawlerSEOTitleGenerator: Using second category: ' . $category_name);
                        }
                    }

                    // それでも「blog」の場合は、投稿内容からジャンルを推測
                    if (strtolower($category_name) === 'blog') {
                        $post = get_post($post_id);
                        if ($post) {
                            $content = strtolower($post->post_content . ' ' . $post->post_title);

                            // キーワードに基づいてジャンルを推測
                            if (strpos($content, 'テクノロジー') !== false || strpos($content, 'technology') !== false ||
                                strpos($content, 'ai') !== false || strpos($content, 'ロボット') !== false ||
                                strpos($content, 'robot') !== false) {
                                $category_name = 'テクノロジー';
                            } elseif (strpos($content, 'ニュース') !== false || strpos($content, 'news') !== false) {
                                $category_name = 'ニュース';
                            } elseif (strpos($content, 'ビジネス') !== false || strpos($content, 'business') !== false) {
                                $category_name = 'ビジネス';
                            } elseif (strpos($content, 'エンタメ') !== false || strpos($content, 'entertainment') !== false) {
                                $category_name = 'エンタメ';
                            } elseif (strpos($content, 'スポーツ') !== false || strpos($content, 'sports') !== false) {
                                $category_name = 'スポーツ';
                            } elseif (strpos($content, '健康') !== false || strpos($content, 'health') !== false) {
                                $category_name = '健康';
                            } elseif (strpos($content, '教育') !== false || strpos($content, 'education') !== false) {
                                $category_name = '教育';
                            } else {
                                $category_name = 'ニュース'; // デフォルト
                            }

                            error_log('NewsCrawlerSEOTitleGenerator: Inferred genre from content: ' . $category_name);
                        }
                    }
                }

                return $category_name;
            }
        }

        // カテゴリーが設定されていない場合はデフォルト
        error_log('NewsCrawlerSEOTitleGenerator: Using default genre name: ニュース');
        return 'ニュース';
    }
    
    /**
     * AIを使用してSEO最適化されたタイトルを生成（キーワード最適化対応）
     */
    private function generate_seo_title_with_ai($post, $genre_name, $post_id = null) {
        if (empty($this->api_key)) {
            return false;
        }
        
        // 投稿内容を取得（改善版）
        $content = $this->extract_clean_content($post, $post_id);
        $excerpt = $post->post_excerpt;
        
        // プロンプトを作成（キーワード最適化対応）
        $prompt = $this->create_seo_title_prompt($content, $excerpt, $genre_name, $post_id);
        
        // OpenAI APIを呼び出し
        $response = $this->call_openai_api($prompt);
        
        if ($response && !empty($response)) {
            // 【ジャンル名】プレフィックスを追加
            return '【' . $genre_name . '】' . $response;
        }
        
        return false;
    }
    
    /**
     * 投稿内容からクリーンなテキストを抽出
     */
    private function extract_clean_content($post, $post_id = null) {
        $content = $post->post_content;
        
        // ニュース投稿かYouTube投稿かを確認
        $is_news_summary = $post_id ? get_post_meta($post_id, '_news_summary', true) : false;
        $is_youtube_summary = $post_id ? get_post_meta($post_id, '_youtube_summary', true) : false;
        
        // YouTube投稿の場合、要約メタデータを最優先で使用
        if ($is_youtube_summary && $post_id) {
            $youtube_summary_source = get_post_meta($post_id, '_youtube_summary_source', true);
            if (!empty($youtube_summary_source)) {
                $content = $youtube_summary_source;
                error_log('NewsCrawlerSEOTitleGenerator: YouTube要約ソースを使用してSEOタイトル生成');
            } else {
                // YouTube要約ソースがない場合は、AI要約を探す
                $ai_summary = get_post_meta($post_id, '_ai_summary', true);
                if (!empty($ai_summary)) {
                    $content = $ai_summary;
                    error_log('NewsCrawlerSEOTitleGenerator: AI要約を使用してSEOタイトル生成');
                }
            }
        }
        // ニュース投稿の場合、要約メタデータを最優先で使用
        elseif ($is_news_summary && $post_id) {
            $news_summary_source = get_post_meta($post_id, '_news_summary_source', true);
            if (!empty($news_summary_source)) {
                $content = $news_summary_source;
                error_log('NewsCrawlerSEOTitleGenerator: ニュース要約ソースを使用してSEOタイトル生成');
            } else {
                // ニュース要約ソースがない場合は、AI要約を探す
                $ai_summary = get_post_meta($post_id, '_ai_summary', true);
                if (!empty($ai_summary)) {
                    $content = $ai_summary;
                    error_log('NewsCrawlerSEOTitleGenerator: AI要約を使用してSEOタイトル生成');
                }
            }
        }
        
        // HTMLタグとブロックコメントを除去
        $content = $this->clean_html_content($content);
        
        // 内容が短い場合は元の本文からも抽出を試行
        if (mb_strlen($content) < 100) {
            $original_content = $this->clean_html_content($post->post_content);
            if (mb_strlen($original_content) > mb_strlen($content)) {
                $content = $original_content;
            }
        }
        
        return $content;
    }
    
    /**
     * HTMLコンテンツをクリーンアップ
     */
    private function clean_html_content($content) {
        // WordPressブロックコメントを除去
        $content = preg_replace('/<!-- wp:[^>]*-->/', '', $content);
        $content = preg_replace('/<!-- \/wp:[^>]*-->/', '', $content);
        
        // HTMLタグを除去
        $content = wp_strip_all_tags($content);
        
        // 余分な空白と改行を整理
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);
        
        return $content;
    }
    
    /**
     * SEO設定のターゲットキーワード向け指示（内容と関連する場合のみ・任意）
     *
     * @return string
     */
    private function build_optional_target_keyword_instructions() {
        if (!class_exists('NewsCrawlerSeoSettings')) {
            return '';
        }

        $seo_settings = get_option('news_crawler_seo_settings', array());
        $keyword_optimization_enabled = !empty($seo_settings['keyword_optimization_enabled']);
        $target_keywords = isset($seo_settings['target_keywords']) ? trim($seo_settings['target_keywords']) : '';

        if (!$keyword_optimization_enabled || $target_keywords === '') {
            return '';
        }

        $keywords = array_filter(array_map('trim', preg_split('/[,\n\r]+/', $target_keywords)));
        if (empty($keywords)) {
            return '';
        }

        $keyword_list = implode('、', $keywords);

        return "

【参考キーワード（任意・内容と関連する場合のみ）】
ターゲットキーワード：{$keyword_list}

- 共有した記事・動画の内容と明確に関連する場合のみ、自然に含めてください
- 内容と無関係なキーワード（記事にその話題がないのに「見積書作成」など）は絶対に含めないでください
- キーワードの無理な挿入より、共有コンテンツの話題を最優先してください";
    }

    /**
     * 要約ソースから共有記事・動画のタイトルを抽出
     *
     * @param string   $content 要約ソース本文
     * @param int|null $post_id 投稿 ID
     * @return array
     */
    private function extract_shared_source_titles($content, $post_id = null) {
        $titles = array();
        $segments = preg_split('/\n\n---\n\n/', (string) $content);

        foreach ($segments as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }

            $lines = preg_split('/\r\n|\r|\n/', $segment);
            $first_line = isset($lines[0]) ? trim($lines[0]) : '';

            if ($first_line === '') {
                continue;
            }

            if (preg_match('/^タイトル:\s*(.+)$/u', $first_line, $matches)) {
                $first_line = trim($matches[1]);
            }

            if (preg_match('/^要約:\s*/u', $first_line)) {
                continue;
            }

            if (mb_strlen($first_line) > 120) {
                $first_line = mb_substr($first_line, 0, 120);
            }

            if ($first_line !== '') {
                $titles[] = $first_line;
            }
        }

        $titles = array_values(array_unique($titles));

        return array_slice($titles, 0, 5);
    }

    /**
     * 共有ソースタイトルセクションをプロンプト用に整形
     *
     * @param string   $content 要約ソース
     * @param int|null $post_id 投稿 ID
     * @return string
     */
    private function build_shared_source_titles_section($content, $post_id = null) {
        $titles = $this->extract_shared_source_titles($content, $post_id);
        if (empty($titles)) {
            return '';
        }

        $section = "\n\n【共有した記事・動画のタイトル（タイトル生成の最優先材料）】\n";
        foreach ($titles as $title) {
            $section .= '- ' . $title . "\n";
        }

        return $section;
    }

    /**
     * コンテンツ重視の共通生成ルール
     *
     * @return string
     */
    private function get_content_first_title_rules() {
        return "
【最重要ルール】
1. 共有した記事・動画のタイトルと要約内容に沿った見出しにすること
2. 記事・動画に登場しない話題やサービス名（見積書作成・自社サービス名など）を無理に入れないこと
3. 30文字以内の簡潔で分かりやすい日本語（【】やジャンル名プレフィックスは付けない）
4. 読者が中身を想像できる具体的な話題を反映すること
5. 「最新最新」などの重複表現や不自然な SEO 詰め込みは避けること";
    }

    /**
     * SEOタイトル生成用のプロンプトを作成（キーワード最適化対応）
     */
    private function create_seo_title_prompt($content, $excerpt, $genre_name, $post_id = null) {
        $keyword_instructions = $this->build_optional_target_keyword_instructions();
        $source_titles_section = $this->build_shared_source_titles_section($content, $post_id);
        $content_first_rules = $this->get_content_first_title_rules();

        $additional_context = '';
        if ($post_id) {
            $is_news_summary = get_post_meta($post_id, '_news_summary', true);
            $is_youtube_summary = get_post_meta($post_id, '_youtube_summary', true);

            if ($is_news_summary) {
                $additional_context = "\n\n【記事の特徴】\n- 複数のニュース記事をまとめた投稿です\n- 各記事のタイトルと要約が共有コンテンツの根拠です";
            } elseif ($is_youtube_summary) {
                $video_count = get_post_meta($post_id, '_youtube_videos_count', true);
                $additional_context = "\n\n【記事の特徴】\n- " . ($video_count ? $video_count . '本' : '複数本') . "のYouTube動画をまとめた投稿です\n- 各動画のタイトルと説明が共有コンテンツの根拠です";
            }
        }

        if ($post_id) {
            $is_news_summary = get_post_meta($post_id, '_news_summary', true);
            $is_youtube_summary = get_post_meta($post_id, '_youtube_summary', true);

            if ($is_news_summary) {
                return "以下のニュースまとめ記事について、共有した記事の内容に沿った魅力的なタイトルを1つ生成してください。{$keyword_instructions}{$source_titles_section}

記事のジャンル（参考・タイトル本文には含めない）: {$genre_name}

ニュースの要約・ソース内容:
{$content}

記事の要約:
{$excerpt}{$additional_context}
{$content_first_rules}

【ニュースまとめ向けの補足】
- まとめているニュースの主題・出来事・トピックを見出しに反映してください
- 「ニュース」「まとめ」は自然な範囲で使ってよいが、内容より優先しないでください

タイトルのみを返してください。説明や装飾は不要です。";
            }

            if ($is_youtube_summary) {
                return "以下のYouTube動画まとめ記事について、共有した動画の内容に沿った魅力的なタイトルを1つ生成してください。{$keyword_instructions}{$source_titles_section}

記事のジャンル（参考・タイトル本文には含めない）: {$genre_name}

動画の要約・ソース内容:
{$content}

記事の要約:
{$excerpt}{$additional_context}
{$content_first_rules}

【YouTubeまとめ向けの補足】
- 紹介している動画の主題・内容を見出しに反映してください
- 「動画まとめ」「YouTube」は自然な範囲で使ってよいが、内容より優先しないでください

タイトルのみを返してください。説明や装飾は不要です。";
            }
        }

        return "以下の記事について、共有コンテンツの内容に沿った魅力的なタイトルを1つ生成してください。{$keyword_instructions}{$source_titles_section}

記事のジャンル（参考・タイトル本文には含めない）: {$genre_name}

記事の内容:
{$content}

記事の要約:
{$excerpt}{$additional_context}
{$content_first_rules}

タイトルのみを返してください。説明や装飾は不要です。";
    }
    
    /**
     * OpenAI APIを呼び出し（指数バックオフ付き）
     */
    private function call_openai_api($prompt) {
        error_log('NewsCrawlerSEOTitleGenerator: OpenAI API呼び出し開始');

        $url = 'https://api.openai.com/v1/chat/completions';
        $max_retries = 3;
        $base_delay = 1; // 基本待機時間（秒）

        $data = array(
            'model' => $this->model,
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'あなたは日本語のWeb編集者です。共有した記事や動画の内容に忠実なタイトルを作成します。内容と無関係なキーワード（見積書作成など）を無理に入れず、要約ソースと元タイトルに沿った見出しだけを返してください。'
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => 100,
            'temperature' => 0.7
        );

        for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
            error_log('NewsCrawlerSEOTitleGenerator: 試行回数 ' . $attempt . '/' . $max_retries);

            // リクエスト間の待機（2回目以降）
            if ($attempt > 1) {
                $delay = $base_delay * pow(2, $attempt - 2); // 指数バックオフ
                $jitter = mt_rand(0, 1000) / 1000; // ジッターを追加（0-1秒）
                $total_delay = $delay + $jitter;

                error_log('NewsCrawlerSEOTitleGenerator: レート制限対策で ' . round($total_delay, 2) . '秒待機します');
                usleep($total_delay * 1000000); // マイクロ秒に変換
            }

            $response = wp_remote_post($url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($data),
                'timeout' => 30
            ));

            if (is_wp_error($response)) {
                $error_message = 'OpenAI APIへの通信に失敗しました: ' . $response->get_error_message();
                error_log('NewsCrawlerSEOTitleGenerator: 試行' . $attempt . ' - ' . $error_message);

                // ネットワークエラーの場合は再試行
                if ($attempt < $max_retries) {
                    continue;
                }
                return array('error' => $error_message);
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            error_log('NewsCrawlerSEOTitleGenerator: 試行' . $attempt . ' - APIレスポンスコード: ' . $response_code);

            // 429エラー（レート制限）の場合
            if ($response_code === 429) {
                error_log('NewsCrawlerSEOTitleGenerator: レート制限エラーが発生しました。試行' . $attempt . '/' . $max_retries);

                if ($attempt < $max_retries) {
                    // より長い待機時間を設定
                    $rate_limit_delay = $base_delay * pow(2, $attempt);
                    error_log('NewsCrawlerSEOTitleGenerator: レート制限対策で ' . $rate_limit_delay . '秒待機します');
                    sleep($rate_limit_delay);
                    continue;
                } else {
                    // 最大再試行回数に達した場合
                    $user_friendly_message = 'OpenAI APIのレート制限に達しました。しばらく時間をおいてから再度お試しください。';
                    error_log('NewsCrawlerSEOTitleGenerator: レート制限エラー - 最大再試行回数に達しました');
                    return array('error' => $user_friendly_message);
                }
            }

            // 5xxエラー（サーバーエラー）の場合も再試行
            if ($response_code >= 500 && $response_code < 600) {
                error_log('NewsCrawlerSEOTitleGenerator: サーバーエラーが発生しました。試行' . $attempt . '/' . $max_retries);

                if ($attempt < $max_retries) {
                    continue;
                }
            }

            // 成功または4xxエラーの場合はループを抜ける
            break;
        }

        error_log('NewsCrawlerSEOTitleGenerator: APIレスポンス本文（先頭200文字）: ' . substr($body, 0, 200));

        $result = json_decode($body, true);

        if (isset($result['choices'][0]['message']['content'])) {
            $title = trim($result['choices'][0]['message']['content']);
            error_log('NewsCrawlerSEOTitleGenerator: 生成されたタイトル: ' . $title);
            return $title;
        }

        // APIレスポンスの解析に失敗した場合
        if (isset($result['error'])) {
            $error_message = isset($result['error']['message']) ? $result['error']['message'] : '不明なAPIエラー';

            // 課金制限エラーの場合、よりわかりやすいメッセージを表示
            if (strpos($error_message, 'exceeded your current quota') !== false ||
                strpos($error_message, 'insufficient_quota') !== false) {
                $user_friendly_message = 'OpenAIの課金制限に達しました。アカウントのクレジットを追加してください。' .
                                       'OpenAIプラットフォーム（https://platform.openai.com/account/billing）で確認・追加できます。';
                error_log('NewsCrawlerSEOTitleGenerator: 課金制限エラー: ' . $error_message);
                return array('error' => $user_friendly_message);
            }

            $full_error_message = 'OpenAI APIエラー: ' . $error_message;
            error_log('NewsCrawlerSEOTitleGenerator: ' . $full_error_message);
            return array('error' => $full_error_message);
        }

        $error_message = 'OpenAI APIからの応答が不正です。レスポンスコード: ' . $response_code . ', 本文: ' . substr($body, 0, 100);
        error_log('NewsCrawlerSEOTitleGenerator: ' . $error_message);
        return array('error' => $error_message);
    }
    
    /**
     * 投稿編集画面にSEOタイトル生成用のメタボックスを追加
     */
    public function add_seo_title_meta_box() {
        // デバッグ情報をログに出力
        error_log('NewsCrawlerSEOTitleGenerator: add_seo_title_meta_box が呼び出されました');
        
        // 投稿と固定ページの両方に追加
        add_meta_box(
            'news_crawler_seo_title',
            'News Crawler ' . news_crawler_get_version() . ' - SEOタイトル生成',
            array($this, 'render_seo_title_meta_box'),
            array('post', 'page'),
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
        error_log('NewsCrawlerSEOTitleGenerator: AJAXハンドラー開始 - POSTデータ: ' . print_r($_POST, true));

        // セキュリティチェック
        if (!wp_verify_nonce($_POST['nonce'], 'generate_seo_title_nonce')) {
            error_log('NewsCrawlerSEOTitleGenerator: セキュリティチェック失敗');
            wp_die('セキュリティチェックに失敗しました');
        }

        $post_id = intval($_POST['post_id']);
        error_log('NewsCrawlerSEOTitleGenerator: 処理対象投稿ID: ' . $post_id);

        if (!$post_id) {
            error_log('NewsCrawlerSEOTitleGenerator: 投稿IDが無効');
            wp_send_json_error('投稿IDが無効です');
        }

        // 権限チェック
        if (!current_user_can('edit_post', $post_id)) {
            error_log('NewsCrawlerSEOTitleGenerator: 権限チェック失敗');
            wp_send_json_error('権限がありません');
        }

        error_log('NewsCrawlerSEOTitleGenerator: SEOタイトル生成開始');
        $result = $this->generate_seo_title($post_id);
        error_log('NewsCrawlerSEOTitleGenerator: SEOタイトル生成結果: ' . print_r($result, true));

        if (is_array($result) && isset($result['error'])) {
            error_log('NewsCrawlerSEOTitleGenerator: エラー結果を返却: ' . $result['error']);
            wp_send_json_error($result['error']);
        } elseif ($result === true) {
            error_log('NewsCrawlerSEOTitleGenerator: 成功結果を返却');
            wp_send_json_success('SEOタイトルが正常に生成されました');
        } else {
            error_log('NewsCrawlerSEOTitleGenerator: 不明なエラー結果を返却');
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
