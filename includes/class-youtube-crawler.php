<?php
/**
 * YouTube Crawler Class
 * 
 * YouTubeチャンネルから動画を取得し、動画の埋め込みと要約を含む投稿を作成する機能
 */

// 直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

class NewsCrawlerYouTubeCrawler {
    private $api_key;
    private $option_name = 'youtube_crawler_settings';
    
    public function __construct() {
        // APIキーは基本設定から取得
        $basic_settings = get_option('news_crawler_basic_settings', array());
        $this->api_key = isset($basic_settings['youtube_api_key']) ? $basic_settings['youtube_api_key'] : '';
        
        // APIキーの設定状況をログに記録
        if (empty($this->api_key)) {
            error_log('YouTubeCrawler: APIキーが設定されていません');
        } else {
            error_log('YouTubeCrawler: APIキーが設定されています（長さ: ' . strlen($this->api_key) . '文字）');
        }
        
        // メニュー登録は新しいジャンル設定システムで管理されるため無効化
        // add_action('admin_menu', array($this, 'manual_run'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('wp_ajax_youtube_crawler_manual_run', array($this, 'manual_run'));
        add_action('wp_ajax_youtube_crawler_test_fetch', array($this, 'test_fetch'));
    }
    
    public function add_admin_menu() {
        // 新しいジャンル設定システムに統合されたため、このメニューは無効化
        // メニューは NewsCrawlerGenreSettings クラスで管理されます
    }
    
    public function admin_init() {
        register_setting($this->option_name, $this->option_name, array($this, 'sanitize_settings'));
        
        add_settings_section(
            'youtube_crawler_main',
            'YouTube基本設定',
            array($this, 'main_section_callback'),
            'youtube-crawler'
        );
        
        add_settings_field(
            'youtube_api_key',
            'YouTube API キー',
            array($this, 'api_key_callback'),
            'youtube-crawler',
            'youtube_crawler_main',
            array('label_for' => 'youtube_api_key')
        );
        
        add_settings_field(
            'youtube_channels',
            'YouTubeチャンネルID',
            array($this, 'channels_callback'),
            'youtube-crawler',
            'youtube_crawler_main',
            array('label_for' => 'youtube_channels')
        );
        
        add_settings_field(
            'youtube_max_videos',
            '最大動画数',
            array($this, 'max_videos_callback'),
            'youtube-crawler',
            'youtube_crawler_main',
            array('label_for' => 'youtube_max_videos')
        );
        
        add_settings_field(
            'youtube_keywords',
            'キーワード設定',
            array($this, 'keywords_callback'),
            'youtube-crawler',
            'youtube_crawler_main',
            array('label_for' => 'youtube_keywords')
        );
        
        add_settings_field(
            'youtube_post_categories',
            '投稿カテゴリー',
            array($this, 'post_category_callback'),
            'youtube-crawler',
            'youtube_crawler_main',
            array('label_for' => 'youtube_post_categories')
        );
        
        add_settings_field(
            'youtube_post_status',
            '投稿ステータス',
            array($this, 'post_status_callback'),
            'youtube-crawler',
            'youtube_crawler_main',
            array('label_for' => 'youtube_post_status')
        );
        
        add_settings_field(
            'youtube_embed_type',
            '動画埋め込みタイプ',
            array($this, 'embed_type_callback'),
            'youtube-crawler',
            'youtube_crawler_main',
            array('label_for' => 'youtube_embed_type')
        );
        
        add_settings_field(
            'youtube_skip_duplicates',
            '重複チェック',
            array($this, 'skip_duplicates_callback'),
            'youtube-crawler',
            'youtube_crawler_main',
            array('label_for' => 'youtube_skip_duplicates')
        );
    }
    
    public function main_section_callback() {
        echo '<p>各YouTubeチャンネルから最新の動画を1件ずつ取得し、キーワードにマッチした動画の埋め込みと要約を含む投稿を作成します。</p>';
        echo '<p><strong>注意:</strong> YouTube Data API v3のAPIキーが必要です。<a href="https://developers.google.com/youtube/v3/getting-started" target="_blank">こちら</a>から取得できます。</p>';
    }
    
    public function api_key_callback() {
        $options = get_option($this->option_name, array());
        $api_key = isset($options['api_key']) && !empty($options['api_key']) ? $options['api_key'] : '';
        echo '<input type="text" id="youtube_api_key" name="' . $this->option_name . '[api_key]" value="' . esc_attr($api_key) . '" size="50" />';
        echo '<p class="description">YouTube Data API v3のAPIキーを入力してください。</p>';
    }
    
    public function channels_callback() {
        $options = get_option($this->option_name, array());
        $channels = isset($options['channels']) && !empty($options['channels']) ? $options['channels'] : array();
        $channels_text = implode("\n", $channels);
        echo '<textarea id="youtube_channels" name="' . $this->option_name . '[channels]" rows="5" cols="50" placeholder="UCxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">' . esc_textarea($channels_text) . '</textarea>';
        echo '<p class="description">1行に1チャンネルIDを入力してください。チャンネルIDは通常「UC」で始まります。</p>';
    }
    
    public function max_videos_callback() {
        $options = get_option($this->option_name, array());
        $max_videos = isset($options['max_videos']) && !empty($options['max_videos']) ? $options['max_videos'] : 5;
        echo '<input type="number" id="youtube_max_videos" name="' . $this->option_name . '[max_videos]" value="' . esc_attr($max_videos) . '" min="1" max="20" />';
        echo '<p class="description">キーワードにマッチした動画の最大取得数（1-20件）。各チャンネルから最新の動画を1件ずつ取得します。</p>';
    }
    
    public function keywords_callback() {
        $options = get_option($this->option_name, array());
        $keywords = isset($options['keywords']) && !empty($options['keywords']) ? $options['keywords'] : array('AI', 'テクノロジー', 'ビジネス', 'ニュース');
        $keywords_text = implode("\n", $keywords);
        echo '<textarea id="youtube_keywords" name="' . $this->option_name . '[keywords]" rows="5" cols="50" placeholder="1行に1キーワードを入力してください">' . esc_textarea($keywords_text) . '</textarea>';
        echo '<p class="description">1行に1キーワードを入力してください。キーワードにマッチした動画のみを取得します。</p>';
    }
    
    public function post_category_callback() {
        $options = get_option($this->option_name, array());
        $categories = isset($options['post_categories']) && !empty($options['post_categories']) ? $options['post_categories'] : array('blog');
        $categories_text = implode("\n", $categories);
        echo '<textarea id="youtube_post_categories" name="' . $this->option_name . '[post_categories]" rows="3" cols="50" placeholder="1行に1カテゴリー名を入力してください">' . esc_textarea($categories_text) . '</textarea>';
        echo '<p class="description">投稿するカテゴリー名を1行に1つずつ入力してください。存在しない場合は自動的に作成されます。</p>';
    }
    
    public function post_status_callback() {
        $options = get_option($this->option_name, array());
        $status = isset($options['post_status']) && !empty($options['post_status']) ? $options['post_status'] : 'draft';
        $statuses = array(
            'draft' => '下書き',
            'publish' => '公開',
            'private' => '非公開',
            'pending' => '承認待ち'
        );
        echo '<select id="youtube_post_status" name="' . $this->option_name . '[post_status]">';
        foreach ($statuses as $value => $label) {
            echo '<option value="' . $value . '" ' . selected($value, $status, false) . '>' . $label . '</option>';
        }
        echo '</select>';
    }
    
    public function embed_type_callback() {
        $options = get_option($this->option_name, array());
        $embed_type = isset($options['embed_type']) && !empty($options['embed_type']) ? $options['embed_type'] : 'responsive';
        $types = array(
            'responsive' => 'WordPress埋め込みブロック（推奨）',
            'classic' => 'WordPress埋め込みブロック',
            'minimal' => 'リンクのみ（軽量）'
        );
        echo '<select id="youtube_embed_type" name="' . $this->option_name . '[embed_type]">';
        foreach ($types as $value => $label) {
            echo '<option value="' . $value . '" ' . selected($value, $embed_type, false) . '>' . $label . '</option>';
        }
        echo '</select>';
        echo '<p class="description">WordPress埋め込みブロックを選択すると、ブロックエディターで動画プレビューが表示されます。</p>';
    }
    
    public function skip_duplicates_callback() {
        $options = get_option($this->option_name, array());
        $skip_duplicates = isset($options['skip_duplicates']) && !empty($options['skip_duplicates']) ? $options['skip_duplicates'] : 'enabled';
        $options_array = array(
            'enabled' => '重複チェックを有効にする（推奨）',
            'disabled' => '重複チェックを無効にする'
        );
        echo '<select id="youtube_skip_duplicates" name="' . $this->option_name . '[skip_duplicates]">';
        foreach ($options_array as $value => $label) {
            echo '<option value="' . $value . '" ' . selected($value, $skip_duplicates, false) . '>' . $label . '</option>';
        }
        echo '</select>';
        echo '<p class="description">重複チェックを無効にすると、同じ動画が含まれた投稿が複数作成される可能性があります。</p>';
    }   
 
    public function sanitize_settings($input) {
        $sanitized = array();
        
        $existing_options = get_option($this->option_name, array());
        
        if (isset($input['max_videos'])) {
            if (is_numeric($input['max_videos']) || (is_string($input['max_videos']) && !empty(trim($input['max_videos'])))) {
                $max_videos = intval($input['max_videos']);
                $sanitized['max_videos'] = max(1, min(20, $max_videos));
            } else {
                $sanitized['max_videos'] = isset($existing_options['max_videos']) ? $existing_options['max_videos'] : 5;
            }
        } else {
            $sanitized['max_videos'] = isset($existing_options['max_videos']) ? $existing_options['max_videos'] : 5;
        }
        
        if (isset($input['keywords'])) {
            if (is_array($input['keywords']) && !empty($input['keywords'])) {
                $keywords = array_map('trim', $input['keywords']);
                $keywords = array_filter($keywords);
                $sanitized['keywords'] = $keywords;
            } elseif (is_string($input['keywords']) && !empty(trim($input['keywords']))) {
                $keywords = explode("\n", $input['keywords']);
                $keywords = array_map('trim', $keywords);
                $keywords = array_filter($keywords);
                $sanitized['keywords'] = $keywords;
            } else {
                $sanitized['keywords'] = isset($existing_options['keywords']) ? $existing_options['keywords'] : array('AI', 'テクノロジー', 'ビジネス', 'ニュース');
            }
        } else {
            $sanitized['keywords'] = isset($existing_options['keywords']) ? $existing_options['keywords'] : array('AI', 'テクノロジー', 'ビジネス', 'ニュース');
        }
        
        if (isset($input['channels'])) {
            if (is_array($input['channels']) && !empty($input['channels'])) {
                $channels = array_map('trim', $input['channels']);
                $channels = array_filter($channels);
            } elseif (is_string($input['channels']) && !empty(trim($input['channels']))) {
                $channels = explode("\n", $input['channels']);
                $channels = array_map('trim', $channels);
                $channels = array_filter($channels);
                $sanitized['channels'] = $channels;
            } else {
                $sanitized['channels'] = isset($existing_options['channels']) ? $existing_options['channels'] : array();
            }
        } else {
            $sanitized['channels'] = isset($existing_options['channels']) ? $existing_options['channels'] : array();
        }
        
        if (isset($input['post_categories'])) {
            if (is_array($input['post_categories'])) {
                $categories = array_map('trim', $input['post_categories']);
                $categories = array_filter($categories);
                $sanitized['post_categories'] = !empty($categories) ? $categories : array('blog');
            } elseif (is_string($input['post_categories']) && !empty(trim($input['post_categories']))) {
                $categories = explode("\n", $input['post_categories']);
                $categories = array_map('trim', $categories);
                $categories = array_filter($categories);
                $sanitized['post_categories'] = !empty($categories) ? $categories : array('blog');
            } else {
                $sanitized['post_categories'] = isset($existing_options['post_categories']) ? $existing_options['post_categories'] : array('blog');
            }
        } else {
            $sanitized['post_categories'] = isset($existing_options['post_categories']) ? $existing_options['post_categories'] : array('blog');
        }
        
        if (isset($input['post_status'])) {
            if (is_string($input['post_status']) && !empty(trim($input['post_status']))) {
                $sanitized['post_status'] = sanitize_text_field($input['post_status']);
            } else {
                $sanitized['post_status'] = isset($existing_options['post_status']) ? $existing_options['post_status'] : 'draft';
            }
        } else {
            $sanitized['post_status'] = isset($existing_options['post_status']) ? $existing_options['post_status'] : 'draft';
        }
        
        if (isset($input['embed_type'])) {
            if (is_string($input['embed_type']) && !empty(trim($input['embed_type']))) {
                $sanitized['embed_type'] = sanitize_text_field($input['embed_type']);
            } else {
                $sanitized['embed_type'] = isset($existing_options['embed_type']) ? $existing_options['embed_type'] : 'responsive';
            }
        } else {
            $sanitized['embed_type'] = isset($existing_options['embed_type']) ? $existing_options['embed_type'] : 'responsive';
        }
        
        // API キーの処理
        if (isset($input['api_key'])) {
            if (is_string($input['api_key']) && !empty(trim($input['api_key']))) {
                $sanitized['api_key'] = sanitize_text_field($input['api_key']);
            } else {
                $sanitized['api_key'] = isset($existing_options['api_key']) ? $existing_options['api_key'] : '';
            }
        } else {
            $sanitized['api_key'] = isset($existing_options['api_key']) ? $existing_options['api_key'] : '';
        }
        
        // 重複チェック設定の処理
        if (isset($input['skip_duplicates'])) {
            if (is_string($input['skip_duplicates']) && !empty(trim($input['skip_duplicates']))) {
                $sanitized['skip_duplicates'] = sanitize_text_field($input['skip_duplicates']);
            } else {
                $sanitized['skip_duplicates'] = isset($existing_options['skip_duplicates']) ? $existing_options['skip_duplicates'] : 'enabled';
            }
        } else {
            $sanitized['skip_duplicates'] = isset($existing_options['skip_duplicates']) ? $existing_options['skip_duplicates'] : 'enabled';
        }
        
        return $sanitized;
    }
    
    public function manual_run() {
        check_ajax_referer('youtube_crawler_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('権限がありません');
        }
        
        $result = $this->crawl_youtube();
        wp_send_json_success($result);
    }
    
    public function test_fetch() {
        check_ajax_referer('youtube_crawler_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('権限がありません');
        }
        
        $options = get_option($this->option_name, array());
        $channels = isset($options['channels']) && !empty($options['channels']) ? $options['channels'] : array();
        
        if (empty($channels)) {
            wp_send_json_success('YouTubeチャンネルが設定されていません。');
        }
        
        if (empty($this->api_key)) {
            wp_send_json_error('YouTube APIキーが設定されていません。');
        }
        
        $test_result = array();
        foreach ($channels as $channel) {
            $videos = $this->fetch_channel_videos($channel, 1);
            if ($videos && is_array($videos)) {
                $test_result[] = $channel . ': 取得成功 (最新の動画1件)';
            } else {
                $test_result[] = $channel . ': 取得失敗';
            }
        }
        
        wp_send_json_success(implode('<br>', $test_result));
    }
    
    public function crawl_youtube() {
        $options = get_option($this->option_name, array());
        $channels = isset($options['channels']) && !empty($options['channels']) ? $options['channels'] : array();
        $keywords = isset($options['keywords']) && !empty($options['keywords']) ? $options['keywords'] : array('AI', 'テクノロジー', 'ビジネス', 'ニュース');
        $max_videos = isset($options['max_videos']) && !empty($options['max_videos']) ? $options['max_videos'] : 5;
        $categories = isset($options['post_categories']) && !empty($options['post_categories']) ? $options['post_categories'] : array('blog');
        $status = isset($options['post_status']) && !empty($options['post_status']) ? $options['post_status'] : 'draft';
        $skip_duplicates = isset($options['skip_duplicates']) && !empty($options['skip_duplicates']) ? $options['skip_duplicates'] : 'enabled';
        
        if (empty($channels)) {
            return 'YouTubeチャンネルが設定されていません。';
        }
        
        // APIキーを基本設定から取得
        $basic_settings = get_option('news_crawler_basic_settings', array());
        $api_key = isset($basic_settings['youtube_api_key']) ? $basic_settings['youtube_api_key'] : '';
        
        if (empty($api_key)) {
            return 'YouTube APIキーが設定されていません。基本設定で設定してください。';
        }
        
        // クォータ超過チェック
        $quota_exceeded_time = get_option('youtube_api_quota_exceeded', 0);
        if ($quota_exceeded_time > 0 && (time() - $quota_exceeded_time) < 86400) { // 24時間
            $remaining_hours = ceil((86400 - (time() - $quota_exceeded_time)) / 3600);
            return 'YouTube APIのクォータ制限により、' . $remaining_hours . '時間後に再試行してください。';
        }
        
        $this->api_key = $api_key;
        
        $matched_videos = array();
        $errors = array();
        $duplicates_skipped = 0;
        
        foreach ($channels as $channel) {
            try {
                // 各チャンネルから最新の動画を複数件取得（ヒット率向上）
                $per_channel_fetch = max(1, min(5, $max_videos));
                $videos = $this->fetch_channel_videos($channel, $per_channel_fetch);
                if ($videos && is_array($videos)) {
                    foreach ($videos as $video) {
                        if ($this->is_keyword_match($video, $keywords)) {
                            $matched_videos[] = $video;
                            if (count($matched_videos) >= $max_videos) {
                                break 2; // 目標数に達したら全体ループを早期終了
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                $errors[] = $channel . ': ' . $e->getMessage();
            }
        }
        
        $valid_videos = array();
        foreach ($matched_videos as $video) {
            if ($skip_duplicates === 'enabled') {
                $duplicate_info = $this->is_duplicate_video($video);
                if ($duplicate_info) {
                    $duplicates_skipped++;
                    continue;
                }
            }
            
            $valid_videos[] = $video;
        }
        
        $valid_videos = array_slice($valid_videos, 0, $max_videos);
        
        $posts_created = 0;
        $post_id = null;
        if (!empty($valid_videos)) {
            $post_id = $this->create_video_summary_post($valid_videos, $categories, $status);
            if ($post_id && !is_wp_error($post_id)) {
                $posts_created = 1;
            }
        }
        
        $result = $posts_created . '件の動画投稿を作成しました（' . count($valid_videos) . '件の動画を含む）。';
        $result .= "\n投稿ID: " . ($post_id ?? 'なし');
        if ($duplicates_skipped > 0) $result .= "\n重複スキップ: " . $duplicates_skipped . '件';
        if (!empty($errors)) $result .= "\nエラー: " . implode(', ', $errors);
        
        $this->update_youtube_statistics($posts_created, $duplicates_skipped);
        
        return $result;
    }
    
    public function crawl_youtube_with_options($options) {
        $channels = isset($options['channels']) && !empty($options['channels']) ? $options['channels'] : array();
        $keywords = isset($options['keywords']) && !empty($options['keywords']) ? $options['keywords'] : array('AI', 'テクノロジー', 'ビジネス', 'ニュース');
        $max_videos = isset($options['max_videos']) && !empty($options['max_videos']) ? $options['max_videos'] : 5;
        $categories = isset($options['post_categories']) && !empty($options['post_categories']) ? $options['post_categories'] : array('blog');
        $status = isset($options['post_status']) && !empty($options['post_status']) ? $options['post_status'] : 'draft';
        $skip_duplicates = isset($options['skip_duplicates']) && !empty($options['skip_duplicates']) ? $options['skip_duplicates'] : 'enabled';
        
        if (empty($channels)) {
            return 'YouTubeチャンネルが設定されていません。';
        }
        
        // APIキーを基本設定から取得
        $basic_settings = get_option('news_crawler_basic_settings', array());
        $api_key = isset($basic_settings['youtube_api_key']) ? $basic_settings['youtube_api_key'] : '';
        
        // オプションからもAPIキーを取得（優先）
        if (isset($options['api_key']) && !empty($options['api_key'])) {
            $api_key = $options['api_key'];
        }
        
        if (empty($api_key)) {
            return 'YouTube APIキーが設定されていません。基本設定で設定してください。';
        }
        
        $this->api_key = $api_key;
        
        $matched_videos = array();
        $errors = array();
        $duplicates_skipped = 0;
        
        foreach ($channels as $channel) {
            try {
                // 各チャンネルから最新の動画を複数件取得（ヒット率向上）
                $per_channel_fetch = max(1, min(5, $max_videos));
                $videos = $this->fetch_channel_videos($channel, $per_channel_fetch);
                if ($videos && is_array($videos)) {
                    foreach ($videos as $video) {
                        if ($this->is_keyword_match($video, $keywords)) {
                            $matched_videos[] = $video;
                            if (count($matched_videos) >= $max_videos) {
                                break 2; // 目標数に達したら全体ループを早期終了
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                $errors[] = $channel . ': ' . $e->getMessage();
            }
        }
        
        $valid_videos = array();
        foreach ($matched_videos as $video) {
            if ($skip_duplicates === 'enabled') {
                $duplicate_info = $this->is_duplicate_video($video);
                if ($duplicate_info) {
                    $duplicates_skipped++;
                    continue;
                }
            }
            
            $valid_videos[] = $video;
        }
        
        $valid_videos = array_slice($valid_videos, 0, $max_videos);
        
        $posts_created = 0;
        $post_id = null;
        if (!empty($valid_videos)) {
            $post_id = $this->create_video_summary_post($valid_videos, $categories, $status);
            if ($post_id && !is_wp_error($post_id)) {
                $posts_created = 1;
            }
        }
        
        $result = $posts_created . '件の動画投稿を作成しました（' . count($valid_videos) . '件の動画を含む）。';
        $result .= "\n投稿ID: " . ($post_id ?? 'なし');
        if ($duplicates_skipped > 0) $result .= "\n重複スキップ: " . $duplicates_skipped . '件';
        if (!empty($errors)) $result .= "\nエラー: " . implode(', ', $errors);
        
        $this->update_youtube_statistics($posts_created, $duplicates_skipped);
        
        return $result;
    }  
  
    private function is_keyword_match($video, $keywords) {
        $text_to_search = strtolower($video['title'] . ' ' . ($video['description'] ?? ''));
        
        foreach ($keywords as $keyword) {
            if (stripos($text_to_search, strtolower($keyword)) !== false) {
                return true;
            }
        }
        return false;
    }
    
    private function create_video_summary_post($videos, $categories, $status) {
        // デバッグ: 受け取ったステータスをログに記録
        error_log('YouTubeCrawler: create_video_summary_post called with status: ' . $status);
        
        $cat_ids = array();
        foreach ($categories as $category) {
            $cat_ids[] = $this->get_or_create_category($category);
        }
        
        // キーワード情報を取得
        $options = get_option($this->option_name, array());
        $keywords = isset($options['keywords']) ? $options['keywords'] : array('動画');
        $embed_type = isset($options['embed_type']) ? $options['embed_type'] : 'responsive';
        
        $keyword_text = implode('、', array_slice($keywords, 0, 3));
        
        // ジャンル名を取得してタイトルの先頭に追加
        $genre_name = '';
        $current_genre_setting = get_transient('news_crawler_current_genre_setting');
        if ($current_genre_setting && isset($current_genre_setting['genre_name'])) {
            $genre_name = $current_genre_setting['genre_name'];
        }
        
        $post_title = $keyword_text . '：YouTube動画まとめ – ' . date_i18n('Y年n月j日');
        
        // ジャンル名がある場合は先頭に追加
        if (!empty($genre_name)) {
            $post_title = '【' . $genre_name . '】' . $post_title;
        }
        
        $post_content = '';
        
        // 全体の概要セクションを追加
        $post_content .= '<!-- wp:paragraph -->' . "\n";
        $post_content .= '<p class="wp-block-paragraph">本日は' . count($videos) . '本の注目動画をお届けします。最新の' . $keyword_text . 'に関する動画を厳選してまとめました。各動画の詳細とともに、ぜひご覧ください。</p>' . "\n";
        $post_content .= '<!-- /wp:paragraph -->' . "\n\n";
        
        // 今日の動画ニュース（H2）セクション
        $post_content .= '<!-- wp:heading {"level":2} -->' . "\n";
        $post_content .= '<h2 class="wp-block-heading">今日の動画ニュース</h2>' . "\n";
        $post_content .= '<!-- /wp:heading -->' . "\n\n";
        
        foreach ($videos as $video) {
            // 動画タイトル（ブロックエディタ形式）
            $post_content .= '<!-- wp:heading {"level":3} -->' . "\n";
            $post_content .= '<h3 class="wp-block-heading">' . esc_html($video['title']) . '</h3>' . "\n";
            $post_content .= '<!-- /wp:heading -->' . "\n\n";
            
            // 動画の埋め込み（ブロックエディタ対応）
            $youtube_url = 'https://www.youtube.com/watch?v=' . esc_attr($video['video_id']);
            
            if ($embed_type === 'responsive' || $embed_type === 'classic') {
                // WordPress標準のYouTube埋め込みブロック
                $post_content .= '<!-- wp:embed {"url":"' . esc_url($youtube_url) . '","type":"video","providerNameSlug":"youtube","responsive":true,"className":"wp-embed-aspect-16-9 wp-has-aspect-ratio"} -->' . "\n";
                $post_content .= '<figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio">';
                $post_content .= '<div class="wp-block-embed__wrapper">' . "\n";
                $post_content .= $youtube_url . "\n";
                $post_content .= '</div></figure>' . "\n";
                $post_content .= '<!-- /wp:embed -->' . "\n\n";
            } else {
                // ミニマル埋め込み（リンクのみ）
                $post_content .= '<!-- wp:paragraph -->' . "\n";
                $post_content .= '<p class="wp-block-paragraph"><a href="' . esc_url($youtube_url) . '" target="_blank" rel="noopener noreferrer">📺 YouTubeで視聴する</a></p>' . "\n";
                $post_content .= '<!-- /wp:paragraph -->' . "\n\n";
            }
            
            // 動画の説明
            if (!empty($video['description'])) {
                $description = wp_trim_words($video['description'], 100, '...');
                $post_content .= '<!-- wp:paragraph -->' . "\n";
                $post_content .= '<p class="wp-block-paragraph">' . esc_html($description) . '</p>' . "\n";
                $post_content .= '<!-- /wp:paragraph -->' . "\n\n";
            }
            
            // メタ情報
            $meta_info = [];
            if (!empty($video['published_at'])) {
                $published_date = date('Y年n月j日', strtotime($video['published_at']));
                $meta_info[] = '<strong>公開日:</strong> ' . esc_html($published_date);
            }
            if (!empty($video['channel_title'])) {
                $meta_info[] = '<strong>チャンネル:</strong> ' . esc_html($video['channel_title']);
            }
            if (!empty($video['duration'])) {
                $meta_info[] = '<strong>動画時間:</strong> ' . esc_html($video['duration']);
            }
            if (!empty($video['view_count'])) {
                $meta_info[] = '<strong>視聴回数:</strong> ' . esc_html(number_format($video['view_count'])) . '回';
            }

            if (!empty($meta_info)) {
                $post_content .= '<!-- wp:paragraph {"fontSize":"small","textColor":"contrast-2"} -->' . "\n";
                $post_content .= '<p class="wp-block-paragraph has-contrast-2-color has-text-color has-small-font-size">' . implode(' | ', $meta_info) . '</p>' . "\n";
                $post_content .= '<!-- /wp:paragraph -->' . "\n\n";
            }

            // 区切り線（最後の動画以外）
            if ($video !== end($videos)) {
                $post_content .= '<!-- wp:separator {"className":"is-style-wide"} -->' . "\n";
                $post_content .= '<hr class="wp-block-separator has-alpha-channel-opacity is-style-wide"/>' . "\n";
                $post_content .= '<!-- /wp:separator -->' . "\n\n";
            }
        }
        
        // まとめセクションを追加
        $post_content .= '<!-- wp:heading {"level":2} -->' . "\n";
        $post_content .= '<h2 class="wp-block-heading">まとめ</h2>' . "\n";
        $post_content .= '<!-- /wp:heading -->' . "\n\n";
        
        $post_content .= '<!-- wp:paragraph -->' . "\n";
        $post_content .= '<p class="wp-block-paragraph">本日は' . count($videos) . '本の' . $keyword_text . 'に関する動画をご紹介しました。各動画から最新の情報やトレンドをキャッチアップして、ぜひお気に入りの動画を見つけてください。また、チャンネル登録や高評価もお忘れなく！</p>' . "\n";
        $post_content .= '<!-- /wp:paragraph -->' . "\n\n";
        
        // 指定されたステータスで直接投稿を作成（公開設定を確実に反映）
        $post_data = array(
            'post_title'    => $post_title,
            'post_content'  => $post_content,
            'post_status'   => $status, // 指定されたステータスで直接作成
            'post_author'   => get_current_user_id() ?: 1,
            'post_type'     => 'post',
            'post_category' => $cat_ids
        );
        
        // メタデータを事前に設定するためのフラグ
        set_transient('news_crawler_creating_youtube_post', true, 60);
        
        $post_id = wp_insert_post($post_data, true);
        
        if (is_wp_error($post_id)) {
            delete_transient('news_crawler_creating_youtube_post');
            return $post_id;
        }
        
        error_log('YouTubeCrawler: Post created with ID: ' . $post_id);
        
        // メタデータの保存（即座に実行）
        update_post_meta($post_id, '_youtube_summary', true);
        update_post_meta($post_id, '_youtube_videos_count', count($videos));
        update_post_meta($post_id, '_youtube_crawled_date', current_time('mysql'));
        
        // XPoster連携用のメタデータを追加
        update_post_meta($post_id, '_news_crawler_created', true);
        update_post_meta($post_id, '_news_crawler_creation_method', 'youtube_standalone');
        update_post_meta($post_id, '_news_crawler_intended_status', $status);
        update_post_meta($post_id, '_news_crawler_creation_timestamp', current_time('timestamp'));
        update_post_meta($post_id, '_news_crawler_ready', false);
        
        // News Crawler用のメタデータを設定
        update_post_meta($post_id, '_news_crawler_creation_timestamp', current_time('timestamp'));
        update_post_meta($post_id, '_news_crawler_ready', false);
        
        // ジャンルIDを保存（自動投稿用）
        $current_genre_setting = get_transient('news_crawler_current_genre_setting');
        if ($current_genre_setting && isset($current_genre_setting['id'])) {
            update_post_meta($post_id, '_news_crawler_genre_id', $current_genre_setting['id']);
        }
        
        foreach ($videos as $index => $video) {
            update_post_meta($post_id, '_youtube_video_' . $index . '_title', $video['title']);
            update_post_meta($post_id, '_youtube_video_' . $index . '_id', $video['video_id']);
            update_post_meta($post_id, '_youtube_video_' . $index . '_channel', $video['channel_title']);
        }
        
        // メタデータ設定完了をログに記録
        error_log('YouTubeCrawler: All metadata set for post ' . $post_id);
        error_log('YouTubeCrawler: _youtube_summary meta: ' . get_post_meta($post_id, '_youtube_summary', true));
        
        // フラグを削除
        delete_transient('news_crawler_creating_youtube_post');
        
        // アイキャッチ生成
        $this->maybe_generate_featured_image($post_id, $post_title, $keywords);
        
        // 投稿作成成功後、評価値を適切に更新
        $current_genre_setting = get_transient('news_crawler_current_genre_setting');
        if ($current_genre_setting && isset($current_genre_setting['id'])) {
            // 投稿作成前に全ジャンルの評価値をバックアップ
            $this->backup_all_evaluation_values();
            
            // 投稿作成ジャンルの評価値を更新
            $this->update_evaluation_after_post_creation($current_genre_setting['id'], $current_genre_setting);
            
            // 投稿作成後の評価値復元をスケジュール（5秒後）
            wp_schedule_single_event(time() + 5, 'news_crawler_restore_evaluation_values');
        }
        
        // AI要約生成（非同期スケジュール実行に変更）
        error_log('YouTubeCrawler: About to schedule AI summarizer for YouTube post ' . $post_id);
        // 基本設定で要約生成が有効かチェック（デフォルトで有効）
        $basic_settings = get_option('news_crawler_basic_settings', array());
        $auto_summary_enabled = isset($basic_settings['auto_summary_generation']) ? $basic_settings['auto_summary_generation'] : true;

        if ($auto_summary_enabled) {
            // 10秒後に非同期実行をスケジュール
            if (wp_schedule_single_event(time() + 10, 'news_crawler_generate_summary', array($post_id))) {
                error_log('YouTubeCrawler: Scheduled AI summary generation (post_id=' . $post_id . ')');
            } else {
                error_log('YouTubeCrawler: Failed to schedule AI summary generation (post_id=' . $post_id . ')');
            }
        } else {
            error_log('YouTubeCrawler: AI要約生成が無効のためスケジュールをスキップします (投稿ID: ' . $post_id . ')');
        }
        
        // X（Twitter）自動シェア機能は削除済み
        
        // 投稿作成完了をログに記録
        error_log('YouTubeCrawler: 投稿を ' . $status . ' ステータスで正常に作成しました (ID: ' . $post_id . ')');
        
        return $post_id;
    }
    
    /**
     * アイキャッチ画像を生成
     */
    private function maybe_generate_featured_image($post_id, $title, $keywords) {
        error_log('YouTubeCrawler: maybe_generate_featured_image called for post ' . $post_id);
        error_log('YouTubeCrawler: Title: ' . $title);
        error_log('YouTubeCrawler: Keywords: ' . implode(', ', $keywords));
        
        // ジャンル設定からの実行かどうかを確認
        $genre_setting = get_transient('news_crawler_current_genre_setting');
        
        error_log('YouTubeCrawler: Genre setting exists: ' . ($genre_setting ? 'Yes' : 'No'));
        if ($genre_setting) {
            error_log('YouTubeCrawler: Genre setting content: ' . print_r($genre_setting, true));
            error_log('YouTubeCrawler: Auto featured image enabled: ' . (isset($genre_setting['auto_featured_image']) && $genre_setting['auto_featured_image'] ? 'Yes' : 'No'));
            if (isset($genre_setting['featured_image_method'])) {
                error_log('YouTubeCrawler: Featured image method: ' . $genre_setting['featured_image_method']);
            }
        } else {
            error_log('YouTubeCrawler: No genre setting found in transient storage');
            // 基本設定からアイキャッチ生成設定を確認
            $basic_settings = get_option('news_crawler_basic_settings', array());
            
            error_log('YouTubeCrawler: Checking basic settings for featured image generation');
            error_log('YouTubeCrawler: Basic settings: ' . print_r($basic_settings, true));
            
            // 基本設定でアイキャッチ生成が有効かチェック
            $auto_featured_enabled = isset($basic_settings['auto_featured_image']) && $basic_settings['auto_featured_image'];
            if (!$auto_featured_enabled) {
                error_log('YouTubeCrawler: Featured image generation skipped - not enabled in basic settings');
                return false;
            }
            
            // 基本設定から設定を作成
            $genre_setting = array(
                'auto_featured_image' => true,
                'featured_image_method' => isset($basic_settings['featured_image_method']) ? $basic_settings['featured_image_method'] : 'template'
            );
            error_log('YouTubeCrawler: Using basic settings for featured image generation');
        }
        
        if (!isset($genre_setting['auto_featured_image']) || !$genre_setting['auto_featured_image']) {
            error_log('YouTubeCrawler: Featured image generation skipped - not enabled in genre setting');
            return false;
        }
        
        if (!class_exists('NewsCrawlerFeaturedImageGenerator')) {
            error_log('YouTubeCrawler: Featured image generator class not found');
            return false;
        }
        
        error_log('YouTubeCrawler: Creating featured image generator instance');
        $generator = new NewsCrawlerFeaturedImageGenerator();
        $method = isset($genre_setting['featured_image_method']) ? $genre_setting['featured_image_method'] : 'template';
        
        error_log('YouTubeCrawler: Generating featured image with method: ' . $method);
        
        try {
            // タイムアウト設定（30秒）
            set_time_limit(30);
            
            $result = $generator->generate_and_set_featured_image($post_id, $title, $keywords, $method);
            
            if ($result) {
                error_log('YouTubeCrawler: Featured image generation result: Success (ID: ' . $result . ')');
            } else {
                error_log('YouTubeCrawler: Featured image generation result: Failed - No result returned');
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log('YouTubeCrawler: Featured image generation error: ' . $e->getMessage());
            return false;
        } catch (Error $e) {
            error_log('YouTubeCrawler: Featured image generation fatal error: ' . $e->getMessage());
            return false;
        }
    }
    
    private function is_duplicate_video($video) {
        global $wpdb;
        $video_id = $video['video_id'];
        
        // 過去30日以内の投稿のみをチェック（重複チェックを緩和）
        $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));
        
        $existing_video = $wpdb->get_var($wpdb->prepare(
            "SELECT pm.post_id FROM {$wpdb->postmeta} pm 
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
             WHERE pm.meta_key LIKE %s AND pm.meta_value = %s 
             AND p.post_date >= %s 
             AND p.post_status IN ('publish', 'draft', 'pending', 'private')",
            '_youtube_video_%_id',
            $video_id,
            $thirty_days_ago
        ));
        
        return $existing_video ? $existing_video : false;
    }
    
    /**
     * 動画が期間制限内かどうかをチェック
     */
    private function is_video_within_age_limit($published_at) {
        // 基本設定から期間制限設定を取得
        $basic_settings = get_option('news_crawler_basic_settings', array());
        
        // デバッグ情報を記録
        error_log('YouTube Crawler: 期間制限チェック開始 - 動画公開日時: ' . $published_at);
        error_log('YouTube Crawler: 基本設定: ' . print_r($basic_settings, true));
        
        // 期間制限が無効の場合は常にtrue
        if (!isset($basic_settings['enable_content_age_limit']) || !$basic_settings['enable_content_age_limit']) {
            error_log('YouTube Crawler: 期間制限が無効 - 制限なしで動画を許可');
            return true;
        }
        
        // 制限月数を取得（デフォルト12ヶ月）
        $limit_months = isset($basic_settings['content_age_limit_months']) ? intval($basic_settings['content_age_limit_months']) : 12;
        error_log('YouTube Crawler: 制限月数: ' . $limit_months . 'ヶ月');
        
        // 制限日時を計算
        $limit_date = date('Y-m-d H:i:s', strtotime("-{$limit_months} months"));
        error_log('YouTube Crawler: 制限日時: ' . $limit_date);
        
        // 動画の公開日時を取得
        $video_date = date('Y-m-d H:i:s', strtotime($published_at));
        error_log('YouTube Crawler: 動画日時（変換後）: ' . $video_date);
        
        // 動画の公開日が制限日時より新しい場合はtrue
        $is_within_limit = $video_date >= $limit_date;
        error_log('YouTube Crawler: 期間制限チェック結果: ' . ($is_within_limit ? '制限内（許可）' : '制限外（スキップ）'));
        
        return $is_within_limit;
    }
    
    private function fetch_channel_videos($channel_id, $max_results = 20) {
        // APIキーの検証
        if (empty($this->api_key)) {
            throw new Exception('YouTube APIキーが設定されていません');
        }
        
        // クォータ効率化のため、検索APIと動画詳細APIを統合
        $api_url = 'https://www.googleapis.com/youtube/v3/search';
        $params = array(
            'key' => $this->api_key,
            'channelId' => $channel_id,
            'part' => 'snippet',
            'order' => 'date',
            'type' => 'video',
            'maxResults' => $max_results
        );
        
        $url = add_query_arg($params, $api_url);
        
        // 指数バックオフ付きリトライ
        $max_retries = 3;
        $base_delay = 2; // 秒
        $response = null;
        for ($attempt = 0; $attempt < $max_retries; $attempt++) {
            $response = wp_remote_get($url, array(
                'timeout' => 30,
                'sslverify' => false,
                'httpversion' => '1.1',
                'redirection' => 5,
                'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
            ));
            if (!is_wp_error($response)) {
                break;
            }
            // タイムアウト/接続失敗のみ再試行
            $msg = $response->get_error_message();
            if (stripos($msg, 'timed out') === false && stripos($msg, 'timeout') === false && stripos($msg, 'couldn\'t connect') === false && stripos($msg, 'could not resolve host') === false) {
                break;
            }
            sleep(pow($base_delay, $attempt));
        }
        
        if (is_wp_error($response)) {
            // APIに到達できない場合はRSSフィードにフォールバック
            $rss_videos = $this->fetch_channel_videos_via_rss($channel_id, $max_results);
            if (!empty($rss_videos)) {
                return $rss_videos;
            }
            // RSSでも取得できない場合はエラーにせず空配列を返す（環境依存のネットワーク遮断を考慮）
            error_log('YouTubeCrawler: APIエラー後のRSSフォールバックも失敗（channel: ' . $channel_id . '）。空配列を返します。理由: ' . $response->get_error_message());
            return array();
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            
            // クォータ超過エラーの特別処理
            if ($response_code === 403 && strpos($body, 'quotaExceeded') !== false) {
                // クォータ超過時刻を記録
                update_option('youtube_api_quota_exceeded', time());
                throw new Exception('YouTube APIのクォータ（利用制限）を超過しています。24時間後に再試行してください。');
            }
            
            // HTTPエラー時もRSSフィードにフォールバック
            $rss_videos = $this->fetch_channel_videos_via_rss($channel_id, $max_results);
            if (!empty($rss_videos)) {
                return $rss_videos;
            }
            // RSSでも取得できない場合は空配列を返す
            error_log('YouTubeCrawler: API HTTPエラー後のRSSフォールバックも失敗（channel: ' . $channel_id . '）。空配列を返します。HTTP: ' . $response_code);
            return array();
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // デバッグ情報をログに記録
        error_log('YouTube API Response for channel ' . $channel_id . ': ' . print_r($data, true));
        
        if (!$data) {
            throw new Exception('APIレスポンスのJSON解析に失敗しました。レスポンス: ' . substr($body, 0, 500));
        }
        
        if (!isset($data['items'])) {
            $error_message = isset($data['error']['message']) ? $data['error']['message'] : '不明なエラー';
            $error_code = isset($data['error']['code']) ? $data['error']['code'] : '不明';
            throw new Exception('APIレスポンスにitemsが含まれていません。エラー: ' . $error_message . ' (コード: ' . $error_code . ')');
        }
        
        if (empty($data['items'])) {
            throw new Exception('チャンネルに動画が存在しません。チャンネルID: ' . $channel_id);
        }
        
        $videos = array();
        foreach ($data['items'] as $item) {
            $snippet = $item['snippet'];
            $video_id = $item['id']['videoId'];
            
            // 期間制限をチェック
            if (!$this->is_video_within_age_limit($snippet['publishedAt'])) {
                continue; // 古い動画はスキップ
            }
            
            // クォータ節約のため、基本的な情報のみを使用
            // 動画の詳細情報は必要最小限に制限
            $videos[] = array(
                'video_id' => $video_id,
                'title' => $snippet['title'],
                'description' => $snippet['description'],
                'channel_title' => $snippet['channelTitle'],
                'channel_id' => $snippet['channelId'],
                'published_at' => date('Y-m-d H:i:s', strtotime($snippet['publishedAt'])),
                'thumbnail' => $snippet['thumbnails']['high']['url'] ?? '',
                'duration' => '', // クォータ節約のため一時的に無効化
                'view_count' => 0  // クォータ節約のため一時的に無効化
            );
        }
        
        return $videos;
    }

    /**
     * YouTubeチャンネルRSSフィードから最新動画を取得（API障害時フォールバック）
     */
    private function fetch_channel_videos_via_rss($channel_id, $max_results = 20) {
        $rss_url = 'https://www.youtube.com/feeds/videos.xml?channel_id=' . urlencode($channel_id);
        $response = wp_remote_get($rss_url, array(
            'timeout' => 20,
            'sslverify' => false,
            'httpversion' => '1.1',
            'redirection' => 5,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
        ));
        if (is_wp_error($response)) {
            return array();
        }
        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return array();
        }
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        if ($xml === false) {
            return array();
        }
        // 名前空間
        $yt = $xml->getNamespaces(true);
        $ytNs = isset($yt['yt']) ? $yt['yt'] : 'http://www.youtube.com/xml/schemas/2015';
        $mediaNs = isset($yt['media']) ? $yt['media'] : 'http://search.yahoo.com/mrss/';
        
        $videos = array();
        $count = 0;
        foreach ($xml->entry as $entry) {
            if ($count >= $max_results) break;
            // videoId
            $videoId = '';
            $childrenYt = $entry->children($ytNs);
            if (isset($childrenYt->videoId)) {
                $videoId = (string)$childrenYt->videoId;
            }
            if (empty($videoId) && isset($entry->id)) {
                // id から抽出
                $idStr = (string)$entry->id; // 例: yt:video:VIDEOID
                if (preg_match('#video:([A-Za-z0-9_-]{6,})#', $idStr, $m)) {
                    $videoId = $m[1];
                }
            }
            if (empty($videoId)) {
                continue;
            }
            $title = isset($entry->title) ? (string)$entry->title : '';
            $published = isset($entry->published) ? (string)$entry->published : '';
            $channelTitle = '';
            if (isset($entry->author) && isset($entry->author->name)) {
                $channelTitle = (string)$entry->author->name;
            } elseif (isset($xml->title)) {
                $channelTitle = (string)$xml->title;
            }
            // description
            $desc = '';
            $media = $entry->children($mediaNs);
            if (isset($media->group) && isset($media->group->description)) {
                $desc = (string)$media->group->description;
            }
            // サムネイル
            $thumb = '';
            if (isset($media->group) && isset($media->group->thumbnail)) {
                $attrs = $media->group->thumbnail->attributes();
                if (isset($attrs['url'])) {
                    $thumb = (string)$attrs['url'];
                }
            }
            $videos[] = array(
                'video_id' => $videoId,
                'title' => $title,
                'description' => $desc,
                'channel_title' => $channelTitle,
                'channel_id' => $channel_id,
                'published_at' => !empty($published) ? date('Y-m-d H:i:s', strtotime($published)) : '',
                'thumbnail' => $thumb,
                'duration' => '',
                'view_count' => 0
            );
            $count++;
        }
        return $videos;
    }
    
    private function fetch_video_details($video_id) {
        // APIキーの検証
        if (empty($this->api_key)) {
            error_log('YouTube Video Details: APIキーが設定されていません');
            return array();
        }
        
        $api_url = 'https://www.googleapis.com/youtube/v3/videos';
        $params = array(
            'key' => $this->api_key,
            'id' => $video_id,
            'part' => 'contentDetails,statistics'
        );
        
        $url = add_query_arg($params, $api_url);
        
        // 指数バックオフ付きリトライ
        $max_retries = 3;
        $base_delay = 2;
        $response = null;
        for ($attempt = 0; $attempt < $max_retries; $attempt++) {
            $response = wp_remote_get($url, array(
                'timeout' => 30,
                'sslverify' => false,
                'httpversion' => '1.1',
                'redirection' => 5,
                'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
            ));
            if (!is_wp_error($response)) {
                break;
            }
            $msg = $response->get_error_message();
            if (stripos($msg, 'timed out') === false && stripos($msg, 'timeout') === false && stripos($msg, 'couldn\'t connect') === false && stripos($msg, 'could not resolve host') === false) {
                break;
            }
            sleep(pow($base_delay, $attempt));
        }
        
        if (is_wp_error($response)) {
            error_log('YouTube Video Details: APIリクエストに失敗しました: ' . $response->get_error_message());
            return array();
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            error_log('YouTube Video Details: APIリクエストが失敗しました。HTTPステータス: ' . $response_code . '、レスポンス: ' . substr($body, 0, 500));
            return array();
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // デバッグ情報をログに記録
        error_log('YouTube Video Details API Response for video ' . $video_id . ': ' . print_r($data, true));
        
        if (!$data) {
            error_log('YouTube Video Details: JSON解析に失敗しました。レスポンス: ' . substr($body, 0, 500));
            return array();
        }
        
        if (!isset($data['items']) || empty($data['items'])) {
            $error_message = isset($data['error']['message']) ? $data['error']['message'] : '不明なエラー';
            error_log('YouTube Video Details: itemsが含まれていません。エラー: ' . $error_message);
            return array();
        }
        
        $item = $data['items'][0];
        $content_details = $item['contentDetails'] ?? array();
        $statistics = $item['statistics'] ?? array();
        
        return array(
            'duration' => $this->format_duration($content_details['duration'] ?? ''),
            'view_count' => intval($statistics['viewCount'] ?? 0)
        );
    }
    
    private function format_duration($duration) {
        // ISO 8601形式の期間を読みやすい形式に変換
        preg_match('/PT(\d+H)?(\d+M)?(\d+S)?/', $duration, $matches);
        
        $hours = isset($matches[1]) ? intval($matches[1]) : 0;
        $minutes = isset($matches[2]) ? intval($matches[2]) : 0;
        $seconds = isset($matches[3]) ? intval($matches[3]) : 0;
        
        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        } else {
            return sprintf('%d:%02d', $minutes, $seconds);
        }
    }
    
    private function get_youtube_statistics() {
        global $wpdb;
        $stats = array();
        $stats['total_posts'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_youtube_summary'");
        $stats['posts_this_month'] = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_youtube_crawled_date' AND meta_value >= %s", date('Y-m-01')));
        $stats['duplicates_skipped'] = get_option('youtube_crawler_duplicates_skipped', 0);
        $stats['last_run'] = get_option('youtube_crawler_last_run', '未実行');
        return $stats;
    }
    
    private function update_youtube_statistics($posts_created, $duplicates_skipped) {
        if ($duplicates_skipped > 0) {
            $current_duplicates = get_option('youtube_crawler_duplicates_skipped', 0);
            update_option('youtube_crawler_duplicates_skipped', $current_duplicates + $duplicates_skipped);
        }
        update_option('youtube_crawler_last_run', current_time('mysql'));
    }
    
    private function get_or_create_category($category_name) {
        $category = get_term_by('name', $category_name, 'category');
        if (!$category) {
            $result = wp_insert_term($category_name, 'category');
            return is_wp_error($result) ? 1 : $result['term_id'];
        }
        return $category->term_id;
    }
    
    // X（Twitter）シェア機能は削除済み
    
    // X（Twitter）シェア機能は削除済み
    
    /**
     * News Crawler用の処理のための投稿ステータス変更を遅延実行
     */
    private function schedule_post_status_update($post_id, $target_status) {
        // XPosterが新規投稿を認識するまで5秒待ってからステータスを変更（時間を延長）
        wp_schedule_single_event(time() + 10, 'news_crawler_update_post_status', array($post_id, $target_status));
        
        // 追加でNews Crawler用のメタデータを再設定
        wp_schedule_single_event(time() + 2, 'news_crawler_ensure_meta', array($post_id));
        
        error_log('YouTubeCrawler: 投稿ステータス変更を遅延実行でスケジュール (ID: ' . $post_id . ', 対象ステータス: ' . $target_status . ')');
    }
    
    /**
     * 投稿作成後の評価値を更新
     */
    private function update_evaluation_after_post_creation($genre_id, $setting) {
        // 投稿作成後、評価値を適切に更新
        // 現在の評価値を取得
        $cache_key = 'news_crawler_available_count_' . $genre_id;
        $current_available = get_transient($cache_key);
        
        if ($current_available !== false && $current_available > 0) {
            // 投稿作成により1件減らす
            $new_available = max(0, $current_available - 1);
            set_transient($cache_key, $new_available, 30 * MINUTE_IN_SECONDS);
            error_log('YouTubeCrawler: 投稿作成後の評価値更新 - ジャンルID: ' . $genre_id . ', 更新前: ' . $current_available . ', 更新後: ' . $new_available);
        } else {
            // 評価値が0またはキャッシュがない場合は再評価
            try {
                // GenreSettingsクラスのインスタンスを取得して評価値を再計算
                if (class_exists('NewsCrawlerGenreSettings')) {
                    $genre_settings = NewsCrawlerGenreSettings::get_instance();
                    $available = intval($genre_settings->test_news_source_availability($setting));
                    set_transient($cache_key, $available, 30 * MINUTE_IN_SECONDS);
                    error_log('YouTubeCrawler: 投稿作成後の評価値再評価 - ジャンルID: ' . $genre_id . ', 評価値: ' . $available);
                } else {
                    error_log('YouTubeCrawler: GenreSettingsクラスが見つかりません');
                }
            } catch (Exception $e) {
                error_log('YouTubeCrawler: 投稿作成後の評価値再評価エラー - ジャンルID: ' . $genre_id . ', エラー: ' . $e->getMessage());
            }
        }
        
        // 投稿作成後の評価値保護フラグを設定（他の処理によるリセットを防ぐ）
        set_transient('news_crawler_post_creation_protection_' . $genre_id, true, 5 * MINUTE_IN_SECONDS);
        error_log('YouTubeCrawler: 投稿作成後の評価値保護フラグを設定 - ジャンルID: ' . $genre_id);
    }
    
    /**
     * 全ジャンルの評価値をバックアップ
     */
    private function backup_all_evaluation_values() {
        if (!class_exists('NewsCrawlerGenreSettings')) {
            return;
        }
        
        $genre_settings = NewsCrawlerGenreSettings::get_instance();
        $all_settings = $genre_settings->get_genre_settings();
        
        $backup_data = array();
        foreach ($all_settings as $genre_id => $setting) {
            $cache_key = 'news_crawler_available_count_' . $genre_id;
            $current_value = get_transient($cache_key);
            if ($current_value !== false) {
                $backup_data[$genre_id] = $current_value;
            }
        }
        
        // バックアップデータを保存（10分間有効）
        set_transient('news_crawler_evaluation_backup', $backup_data, 10 * MINUTE_IN_SECONDS);
        error_log('YouTubeCrawler: 全ジャンルの評価値をバックアップ - ' . count($backup_data) . '件');
    }
}