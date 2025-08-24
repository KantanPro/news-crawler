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
        $options = get_option($this->option_name, array());
        $this->api_key = isset($options['api_key']) ? $options['api_key'] : '';
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('wp_ajax_youtube_crawler_manual_run', array($this, 'manual_run'));
        add_action('wp_ajax_youtube_crawler_test_fetch', array($this, 'test_fetch'));
    }
    
    public function add_admin_menu() {
        // YouTubeサブメニュー
        add_submenu_page(
            'news-crawler',
            'YouTube',
            'YouTube',
            'manage_options',
            'youtube-crawler',
            array($this, 'admin_page')
        );
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
            'youtube_post_category',
            '投稿カテゴリー',
            array($this, 'post_category_callback'),
            'youtube-crawler',
            'youtube_crawler_main',
            array('label_for' => 'youtube_post_category')
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
    }
    
    public function main_section_callback() {
        echo '<p>YouTubeチャンネルからキーワードにマッチした動画を取得し、動画の埋め込みと要約を含む投稿を作成します。</p>';
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
        echo '<p class="description">キーワードにマッチした動画の最大取得数（1-20件）</p>';
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
        $category = isset($options['post_category']) && !empty($options['post_category']) ? $options['post_category'] : 'youtube';
        echo '<input type="text" id="youtube_post_category" name="' . $this->option_name . '[post_category]" value="' . esc_attr($category) . '" />';
        echo '<p class="description">投稿するカテゴリー名を入力してください。存在しない場合は自動的に作成されます。</p>';
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
            'responsive' => 'レスポンシブ埋め込み（推奨）',
            'classic' => 'クラシック埋め込み',
            'minimal' => 'ミニマル埋め込み（リンクのみ）'
        );
        echo '<select id="youtube_embed_type" name="' . $this->option_name . '[embed_type]">';
        foreach ($types as $value => $label) {
            echo '<option value="' . $value . '" ' . selected($value, $embed_type, false) . '>' . $label . '</option>';
        }
        echo '</select>';
    }
    
    public function sanitize_settings($input) {
        $sanitized = array();
        
        $existing_options = get_option($this->option_name, array());
        
        if (isset($input['max_videos']) && !empty($input['max_videos'])) {
            $max_videos = intval($input['max_videos']);
            $sanitized['max_videos'] = max(1, min(20, $max_videos));
        } else {
            $sanitized['max_videos'] = isset($existing_options['max_videos']) ? $existing_options['max_videos'] : 5;
        }
        
        if (isset($input['keywords']) && !empty($input['keywords'])) {
            // 配列の場合はそのまま使用、文字列の場合は改行で分割
            if (is_array($input['keywords'])) {
                $keywords = $input['keywords'];
            } else {
                $keywords = explode("\n", $input['keywords']);
            }
            $keywords = array_map('trim', $keywords);
            $keywords = array_filter($keywords);
            $sanitized['keywords'] = $keywords;
        } else {
            $sanitized['keywords'] = isset($existing_options['keywords']) ? $existing_options['keywords'] : array('AI', 'テクノロジー', 'ビジネス', 'ニュース');
        }
        
        if (isset($input['channels']) && !empty($input['channels'])) {
            // 配列の場合はそのまま使用、文字列の場合は改行で分割
            if (is_array($input['channels'])) {
                $channels = $input['channels'];
            } else {
                $channels = explode("\n", $input['channels']);
            }
            $channels = array_map('trim', $channels);
            $channels = array_filter($channels);
            $sanitized['channels'] = $channels;
        } else {
            $sanitized['channels'] = isset($existing_options['channels']) ? $existing_options['channels'] : array();
        }
        
        if (isset($input['post_category']) && !empty($input['post_category'])) {
            $sanitized['post_category'] = sanitize_text_field($input['post_category']);
        } else {
            $sanitized['post_category'] = isset($existing_options['post_category']) ? $existing_options['post_category'] : 'youtube';
        }
        
        if (isset($input['post_status']) && !empty($input['post_status'])) {
            $sanitized['post_status'] = sanitize_text_field($input['post_status']);
        } else {
            $sanitized['post_status'] = isset($existing_options['post_status']) ? $existing_options['post_status'] : 'draft';
        }
        
        if (isset($input['embed_type']) && !empty($input['embed_type'])) {
            $sanitized['embed_type'] = sanitize_text_field($input['embed_type']);
        } else {
            $sanitized['embed_type'] = isset($existing_options['embed_type']) ? $existing_options['embed_type'] : 'responsive';
        }
        
        // API キーの処理
        if (isset($input['api_key']) && !empty($input['api_key'])) {
            $sanitized['api_key'] = sanitize_text_field($input['api_key']);
        } else {
            $sanitized['api_key'] = isset($existing_options['api_key']) ? $existing_options['api_key'] : '';
        }
        
        return $sanitized;
    }
    
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>YouTube Crawler</h1>
            
            <?php if (isset($_GET['settings-updated'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p>設定を保存しました。</p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields($this->option_name);
                do_settings_sections('youtube-crawler');
                submit_button();
                ?>
            </form>
            
            <hr>
            
            <h2>動画投稿を作成</h2>
            <p>設定したYouTubeチャンネルからキーワードにマッチした動画を取得して、動画の埋め込みと要約を含む投稿を作成します。</p>
            <button type="button" id="youtube-manual-run" class="button button-primary">動画投稿を作成</button>
            
            <div id="youtube-manual-run-result" style="margin-top: 10px; white-space: pre-wrap; background: #f7f7f7; padding: 15px; border: 1px solid #ccc; border-radius: 4px; max-height: 400px; overflow-y: auto;"></div>
            
            <hr>
            
            <h2>統計情報</h2>
            <?php $stats = $this->get_youtube_statistics(); ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>項目</th>
                        <th>数値</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>総動画投稿数</td>
                        <td><?php echo $stats['total_posts']; ?>件</td>
                    </tr>
                    <tr>
                        <td>今月の動画投稿数</td>
                        <td><?php echo $stats['posts_this_month']; ?>件</td>
                    </tr>
                    <tr>
                        <td>重複スキップ数</td>
                        <td><?php echo $stats['duplicates_skipped']; ?>件</td>
                    </tr>
                    <tr>
                        <td>最後の実行日時</td>
                        <td><?php echo $stats['last_run']; ?></td>
                    </tr>
                </tbody>
            </table>
            
            <script>
            jQuery(document).ready(function($) {
                $('#youtube-manual-run').click(function() {
                    var button = $(this);
                    var resultDiv = $('#youtube-manual-run-result');
                    button.prop('disabled', true).text('実行中...');
                    resultDiv.html('YouTubeチャンネルの解析と動画投稿作成を開始します...');
                    
                    // まずチャンネルの解析を実行
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'youtube_crawler_test_fetch',
                            nonce: '<?php echo wp_create_nonce('youtube_crawler_nonce'); ?>'
                        },
                        success: function(testResponse) {
                            var testResult = '';
                            if (testResponse.success) {
                                testResult = '<div class="notice notice-info"><p><strong>YouTubeチャンネル解析結果:</strong><br>' + testResponse.data + '</p></div>';
                            } else {
                                testResult = '<div class="notice notice-error"><p><strong>YouTubeチャンネル解析エラー:</strong><br>' + testResponse.data + '</p></div>';
                            }
                            
                            // 次に動画投稿作成を実行
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'youtube_crawler_manual_run',
                                    nonce: '<?php echo wp_create_nonce('youtube_crawler_nonce'); ?>'
                                },
                                success: function(postResponse) {
                                    var postResult = '';
                                    if (postResponse.success) {
                                        postResult = '<div class="notice notice-success"><p><strong>動画投稿作成結果:</strong><br>' + postResponse.data.replace(/\n/g, '<br>') + '</p></div>';
                                    } else {
                                        postResult = '<div class="notice notice-error"><p><strong>動画投稿作成エラー:</strong><br>' + postResponse.data.replace(/\n/g, '<br>') + '</p></div>';
                                    }
                                    
                                    // 両方の結果を表示
                                    resultDiv.html(testResult + '<br>' + postResult);
                                },
                                error: function() {
                                    resultDiv.html(testResult + '<br><div class="notice notice-error"><p><strong>動画投稿作成エラー:</strong><br>エラーが発生しました。</p></div>');
                                },
                                complete: function() {
                                    button.prop('disabled', false).text('動画投稿を作成');
                                }
                            });
                        },
                        error: function() {
                            resultDiv.html('<div class="notice notice-error"><p><strong>YouTubeチャンネル解析エラー:</strong><br>エラーが発生しました。</p></div>');
                            button.prop('disabled', false).text('動画投稿を作成');
                        }
                    });
                });
            });
            </script>
        </div>
        <?php
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
            $videos = $this->fetch_channel_videos($channel, 3);
            if ($videos && is_array($videos)) {
                $test_result[] = $channel . ': 取得成功 (' . count($videos) . '件の動画)';
            } else {
                $test_result[] = $channel . ': 取得失敗';
            }
        }
        
        wp_send_json_success(implode('<br>', $test_result));
    }
    
    private function crawl_youtube() {
        $options = get_option($this->option_name, array());
        $channels = isset($options['channels']) && !empty($options['channels']) ? $options['channels'] : array();
        $keywords = isset($options['keywords']) && !empty($options['keywords']) ? $options['keywords'] : array('AI', 'テクノロジー', 'ビジネス', 'ニュース');
        $max_videos = isset($options['max_videos']) && !empty($options['max_videos']) ? $options['max_videos'] : 5;
        $category = isset($options['post_category']) && !empty($options['post_category']) ? $options['post_category'] : 'youtube';
        $status = isset($options['post_status']) && !empty($options['post_status']) ? $options['post_status'] : 'draft';
        
        if (empty($channels)) {
            return 'YouTubeチャンネルが設定されていません。';
        }
        
        if (empty($this->api_key)) {
            return 'YouTube APIキーが設定されていません。';
        }
        
        $matched_videos = array();
        $errors = array();
        $duplicates_skipped = 0;
        $debug_info = array();
        
        foreach ($channels as $channel) {
            try {
                $videos = $this->fetch_channel_videos($channel, 20);
                if ($videos && is_array($videos)) {
                    $debug_info[] = $channel . ': ' . count($videos) . '件の動画を取得';
                    foreach ($videos as $video) {
                        if ($this->is_keyword_match($video, $keywords)) {
                            $matched_videos[] = $video;
                            $debug_info[] = '  - キーワードマッチ: ' . $video['title'];
                        } else {
                            $debug_info[] = '  - キーワードマッチなし: ' . $video['title'];
                        }
                    }
                }
            } catch (Exception $e) {
                $errors[] = $channel . ': ' . $e->getMessage();
            }
        }
        
        $debug_info[] = "\nキーワードマッチした動画数: " . count($matched_videos);
        
        $valid_videos = array();
        foreach ($matched_videos as $video) {
            $debug_info[] = "  - 動画: " . $video['title'];
            
            if ($this->is_duplicate_video($video)) {
                $duplicates_skipped++;
                $debug_info[] = "    → 重複のためスキップ";
                continue;
            }
            
            $debug_info[] = "    → 有効動画として追加";
            $valid_videos[] = $video;
        }
        
        $valid_videos = array_slice($valid_videos, 0, $max_videos);
        
        $posts_created = 0;
        if (!empty($valid_videos)) {
            $post_id = $this->create_video_summary_post($valid_videos, $category, $status);
            if ($post_id && !is_wp_error($post_id)) {
                $posts_created = 1;
            }
        }
        
        $result = $posts_created . '件の動画投稿を作成しました（' . count($valid_videos) . '件の動画を含む）。';
        $result .= "\n投稿ID: " . ($post_id ?? 'なし');
        if ($duplicates_skipped > 0) $result .= "\n重複スキップ: " . $duplicates_skipped . '件';
        if (!empty($errors)) $result .= "\nエラー: " . implode(', ', $errors);
        
        $result .= "\n\n=== デバッグ情報 ===\n" . implode("\n", $debug_info);
        
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
    
    private function create_video_summary_post($videos, $category, $status) {
        $cat_id = $this->get_or_create_category($category);
        
        // キーワード情報を取得
        $options = get_option($this->option_name, array());
        $keywords = isset($options['keywords']) ? $options['keywords'] : array('動画');
        $embed_type = isset($options['embed_type']) ? $options['embed_type'] : 'responsive';
        
        $keyword_text = implode('、', array_slice($keywords, 0, 3));
        $post_title = $keyword_text . '：YouTube動画まとめ – ' . date_i18n('Y年n月j日');
        
        $post_content = '';
        
        foreach ($videos as $video) {
            $post_content .= '<!-- wp:group {"style":{"spacing":{"margin":{"top":"20px","bottom":"20px"}}}} -->';
            $post_content .= '<div class="wp-block-group" style="margin-top:20px;margin-bottom:20px">';
            
            $post_content .= '<!-- wp:heading {"level":3} -->';
            $post_content .= '<h3>' . esc_html($video['title']) . '</h3>';
            $post_content .= '<!-- /wp:heading -->';
            
            // 動画の埋め込み
            if ($embed_type === 'responsive') {
                $post_content .= '<!-- wp:html -->';
                $post_content .= '<div class="youtube-embed-responsive" style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%;">';
                $post_content .= '<iframe style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;" src="https://www.youtube.com/embed/' . esc_attr($video['video_id']) . '" frameborder="0" allowfullscreen></iframe>';
                $post_content .= '</div>';
                $post_content .= '<!-- /wp:html -->';
            } elseif ($embed_type === 'classic') {
                $post_content .= '<!-- wp:html -->';
                $post_content .= '<iframe width="560" height="315" src="https://www.youtube.com/embed/' . esc_attr($video['video_id']) . '" frameborder="0" allowfullscreen></iframe>';
                $post_content .= '<!-- /wp:html -->';
            } else {
                // ミニマル埋め込み（リンクのみ）
                $post_content .= '<!-- wp:paragraph -->';
                $post_content .= '<p><a href="https://www.youtube.com/watch?v=' . esc_attr($video['video_id']) . '" target="_blank" rel="noopener noreferrer">YouTubeで視聴</a></p>';
                $post_content .= '<!-- /wp:paragraph -->';
            }
            
            if (!empty($video['description'])) {
                $post_content .= '<!-- wp:paragraph -->';
                $post_content .= '<p>' . esc_html(wp_trim_words($video['description'], 100, '...')) . '</p>';
                $post_content .= '<!-- /wp:paragraph -->';
            }
            
            $meta_info = [];
            if (!empty($video['published_at'])) {
                $meta_info[] = '<strong>公開日:</strong> ' . esc_html($video['published_at']);
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
                $post_content .= '<!-- wp:paragraph {"fontSize":"small"} -->';
                $post_content .= '<p class="has-small-font-size">' . implode(' | ', $meta_info) . '</p>';
                $post_content .= '<!-- /wp:paragraph -->';
            }

            $post_content .= '</div>';
            $post_content .= '<!-- /wp:group -->';
        }
        
        $post_data = array(
            'post_title'    => $post_title,
            'post_content'  => $post_content,
            'post_status'   => $status,
            'post_author'   => get_current_user_id() ?: 1,
            'post_type'     => 'post',
            'post_category' => array($cat_id)
        );
        
        $post_id = wp_insert_post($post_data, true);
        
        if (is_wp_error($post_id)) {
            return $post_id;
        }
        
        // メタデータの保存
        update_post_meta($post_id, '_youtube_summary', true);
        update_post_meta($post_id, '_youtube_videos_count', count($videos));
        update_post_meta($post_id, '_youtube_crawled_date', current_time('mysql'));
        
        foreach ($videos as $index => $video) {
            update_post_meta($post_id, '_youtube_video_' . $index . '_title', $video['title']);
            update_post_meta($post_id, '_youtube_video_' . $index . '_id', $video['video_id']);
            update_post_meta($post_id, '_youtube_video_' . $index . '_channel', $video['channel_title']);
        }
        
        return $post_id;
    }
    
    private function is_duplicate_video($video) {
        global $wpdb;
        $video_id = $video['video_id'];
        $existing_video = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key LIKE %s AND meta_value = %s",
            '_youtube_video_%_id',
            $video_id
        ));
        return $existing_video ? true : false;
    }
    
    private function fetch_channel_videos($channel_id, $max_results = 20) {
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
        $response = wp_remote_get($url, array('timeout' => 30));
        
        if (is_wp_error($response)) {
            throw new Exception('APIリクエストに失敗しました: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || !isset($data['items'])) {
            throw new Exception('APIレスポンスの解析に失敗しました');
        }
        
        $videos = array();
        foreach ($data['items'] as $item) {
            $snippet = $item['snippet'];
            $video_id = $item['id']['videoId'];
            
            // 動画の詳細情報を取得
            $video_details = $this->fetch_video_details($video_id);
            
            $videos[] = array(
                'video_id' => $video_id,
                'title' => $snippet['title'],
                'description' => $snippet['description'],
                'channel_title' => $snippet['channelTitle'],
                'channel_id' => $snippet['channelId'],
                'published_at' => date('Y-m-d H:i:s', strtotime($snippet['publishedAt'])),
                'thumbnail' => $snippet['thumbnails']['high']['url'] ?? '',
                'duration' => $video_details['duration'] ?? '',
                'view_count' => $video_details['view_count'] ?? 0
            );
        }
        
        return $videos;
    }
    
    private function fetch_video_details($video_id) {
        $api_url = 'https://www.googleapis.com/youtube/v3/videos';
        $params = array(
            'key' => $this->api_key,
            'id' => $video_id,
            'part' => 'contentDetails,statistics'
        );
        
        $url = add_query_arg($params, $api_url);
        $response = wp_remote_get($url, array('timeout' => 30));
        
        if (is_wp_error($response)) {
            return array();
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || !isset($data['items'][0])) {
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
}
