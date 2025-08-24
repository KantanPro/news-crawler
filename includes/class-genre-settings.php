<?php
/**
 * Genre Settings Management Class
 * 
 * ジャンル別設定の保存・管理・実行機能を提供
 */

// 直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

class NewsCrawlerGenreSettings {
    private $option_name = 'news_crawler_genre_settings';
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('wp_ajax_genre_settings_save', array($this, 'save_genre_setting'));
        add_action('wp_ajax_genre_settings_delete', array($this, 'delete_genre_setting'));
        add_action('wp_ajax_genre_settings_load', array($this, 'load_genre_setting'));
        add_action('wp_ajax_genre_settings_execute', array($this, 'execute_genre_setting'));
        add_action('wp_ajax_genre_settings_duplicate', array($this, 'duplicate_genre_setting'));
    }
    
    public function add_admin_menu() {
        // メインメニュー
        add_menu_page(
            'News Crawler',
            'News Crawler',
            'manage_options',
            'news-crawler-main',
            array($this, 'main_admin_page'),
            'dashicons-rss',
            30
        );
        
        // ジャンル設定サブメニュー
        add_submenu_page(
            'news-crawler-main',
            'ジャンル設定',
            'ジャンル設定',
            'manage_options',
            'news-crawler-main',
            array($this, 'main_admin_page')
        );
        
        // 基本設定サブメニュー
        add_submenu_page(
            'news-crawler-main',
            '基本設定',
            '基本設定',
            'manage_options',
            'news-crawler-basic',
            array($this, 'basic_settings_page')
        );
    }
    
    public function admin_init() {
        register_setting('news_crawler_basic_settings', 'news_crawler_basic_settings', array($this, 'sanitize_basic_settings'));
        
        add_settings_section(
            'basic_settings_main',
            '基本設定',
            array($this, 'basic_section_callback'),
            'news-crawler-basic'
        );
        
        add_settings_field(
            'youtube_api_key',
            'YouTube API キー',
            array($this, 'youtube_api_key_callback'),
            'news-crawler-basic',
            'basic_settings_main'
        );
        
        add_settings_field(
            'default_post_author',
            'デフォルト投稿者',
            array($this, 'default_post_author_callback'),
            'news-crawler-basic',
            'basic_settings_main'
        );
    }
    
    public function basic_section_callback() {
        echo '<p>すべてのジャンル設定で共通して使用される基本設定です。</p>';
    }
    
    public function youtube_api_key_callback() {
        $options = get_option('news_crawler_basic_settings', array());
        $api_key = isset($options['youtube_api_key']) ? $options['youtube_api_key'] : '';
        echo '<input type="text" name="news_crawler_basic_settings[youtube_api_key]" value="' . esc_attr($api_key) . '" size="50" />';
        echo '<p class="description">YouTube Data API v3のAPIキーを入力してください。</p>';
    }
    
    public function default_post_author_callback() {
        $options = get_option('news_crawler_basic_settings', array());
        $author_id = isset($options['default_post_author']) ? $options['default_post_author'] : get_current_user_id();
        $users = get_users(array('capability' => 'edit_posts'));
        echo '<select name="news_crawler_basic_settings[default_post_author]">';
        foreach ($users as $user) {
            echo '<option value="' . $user->ID . '" ' . selected($user->ID, $author_id, false) . '>' . esc_html($user->display_name) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">投稿のデフォルト作成者を選択してください。</p>';
    }
    
    public function sanitize_basic_settings($input) {
        $sanitized = array();
        
        if (isset($input['youtube_api_key'])) {
            $sanitized['youtube_api_key'] = sanitize_text_field($input['youtube_api_key']);
        }
        
        if (isset($input['default_post_author'])) {
            $sanitized['default_post_author'] = intval($input['default_post_author']);
        }
        
        return $sanitized;
    }
    
    public function basic_settings_page() {
        ?>
        <div class="wrap">
            <h1>News Crawler - 基本設定</h1>
            
            <?php if (isset($_GET['settings-updated'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p>基本設定を保存しました。</p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('news_crawler_basic_settings');
                do_settings_sections('news-crawler-basic');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    public function main_admin_page() {
        $genre_settings = $this->get_genre_settings();
        ?>
        <div class="wrap">
            <h1>News Crawler - ジャンル設定</h1>
            
            <div id="genre-settings-container">
                <!-- ジャンル設定フォーム -->
                <div class="card" style="max-width: none;">
                    <h2>ジャンル設定の追加・編集</h2>
                    <form id="genre-settings-form">
                        <input type="hidden" id="genre-id" name="genre_id" value="">
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">ジャンル名</th>
                                <td>
                                    <input type="text" id="genre-name" name="genre_name" class="regular-text" required>
                                    <p class="description">設定を識別するためのジャンル名を入力してください。</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">コンテンツタイプ</th>
                                <td>
                                    <select id="content-type" name="content_type" required>
                                        <option value="">選択してください</option>
                                        <option value="news">ニュース記事</option>
                                        <option value="youtube">YouTube動画</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">キーワード</th>
                                <td>
                                    <textarea id="keywords" name="keywords" rows="5" cols="50" class="large-text" required placeholder="1行に1キーワードを入力してください"></textarea>
                                    <p class="description">1行に1キーワードを入力してください。</p>
                                </td>
                            </tr>
                        </table>
                        
                        <!-- ニュース設定 -->
                        <div id="news-settings" style="display: none;">
                            <h3>ニュース設定</h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">ニュースソース</th>
                                    <td>
                                        <textarea id="news-sources" name="news_sources" rows="5" cols="50" class="large-text" placeholder="1行に1URLを入力してください"></textarea>
                                        <p class="description">RSSフィードまたはニュースサイトのURLを1行に1つずつ入力してください。</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">最大記事数</th>
                                    <td>
                                        <input type="number" id="max-articles" name="max_articles" value="10" min="1" max="50">
                                        <p class="description">取得する記事の最大数（1-50件）</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- YouTube設定 -->
                        <div id="youtube-settings" style="display: none;">
                            <h3>YouTube設定</h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">YouTubeチャンネルID</th>
                                    <td>
                                        <textarea id="youtube-channels" name="youtube_channels" rows="5" cols="50" class="large-text" placeholder="UCxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"></textarea>
                                        <p class="description">1行に1チャンネルIDを入力してください。</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">最大動画数</th>
                                    <td>
                                        <input type="number" id="max-videos" name="max_videos" value="5" min="1" max="20">
                                        <p class="description">取得する動画の最大数（1-20件）</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">動画埋め込みタイプ</th>
                                    <td>
                                        <select id="embed-type" name="embed_type">
                                            <option value="responsive">WordPress埋め込みブロック（推奨）</option>
                                            <option value="classic">WordPress埋め込みブロック</option>
                                            <option value="minimal">リンクのみ（軽量）</option>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- 共通設定 -->
                        <h3>共通設定</h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row">投稿カテゴリー</th>
                                <td>
                                    <textarea id="post-categories" name="post_categories" rows="3" cols="50" class="large-text" placeholder="1行に1カテゴリー名を入力してください">blog</textarea>
                                    <p class="description">投稿するカテゴリー名を1行に1つずつ入力してください。存在しない場合は自動的に作成されます。</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">投稿ステータス</th>
                                <td>
                                    <select id="post-status" name="post_status">
                                        <option value="draft">下書き</option>
                                        <option value="publish">公開</option>
                                        <option value="private">非公開</option>
                                        <option value="pending">承認待ち</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" class="button button-primary">設定を保存</button>
                            <button type="button" id="cancel-edit" class="button" style="display: none;">キャンセル</button>
                        </p>
                    </form>
                </div>
                
                <!-- ジャンル設定リスト -->
                <div class="card" style="max-width: none; margin-top: 20px;">
                    <h2>保存済みジャンル設定</h2>
                    <div id="genre-settings-list">
                        <?php $this->render_genre_settings_list($genre_settings); ?>
                    </div>
                </div>
            </div>
            
            <!-- 実行結果表示エリア -->
            <div id="execution-result" style="margin-top: 20px; display: none;">
                <div class="card">
                    <h3>実行結果</h3>
                    <div id="execution-result-content" style="white-space: pre-wrap; background: #f7f7f7; padding: 15px; border: 1px solid #ccc; border-radius: 4px; max-height: 400px; overflow-y: auto;"></div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // コンテンツタイプ変更時の設定表示切り替え
            $('#content-type').change(function() {
                var contentType = $(this).val();
                $('#news-settings, #youtube-settings').hide();
                if (contentType === 'news') {
                    $('#news-settings').show();
                } else if (contentType === 'youtube') {
                    $('#youtube-settings').show();
                }
            });
            
            // フォーム送信
            $('#genre-settings-form').submit(function(e) {
                e.preventDefault();
                
                var formData = {
                    action: 'genre_settings_save',
                    nonce: '<?php echo wp_create_nonce('genre_settings_nonce'); ?>',
                    genre_id: $('#genre-id').val(),
                    genre_name: $('#genre-name').val(),
                    content_type: $('#content-type').val(),
                    keywords: $('#keywords').val(),
                    news_sources: $('#news-sources').val(),
                    max_articles: $('#max-articles').val(),
                    youtube_channels: $('#youtube-channels').val(),
                    max_videos: $('#max-videos').val(),
                    embed_type: $('#embed-type').val(),
                    post_categories: $('#post-categories').val(),
                    post_status: $('#post-status').val()
                };
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            alert('設定を保存しました。');
                            location.reload();
                        } else {
                            alert('エラー: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('保存中にエラーが発生しました。');
                    }
                });
            });
            
            // キャンセルボタン
            $('#cancel-edit').click(function() {
                $('#genre-settings-form')[0].reset();
                $('#genre-id').val('');
                $('#cancel-edit').hide();
                $('#news-settings, #youtube-settings').hide();
            });
        });
        
        // 編集ボタンクリック
        function editGenreSetting(genreId) {
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'genre_settings_load',
                    nonce: '<?php echo wp_create_nonce('genre_settings_nonce'); ?>',
                    genre_id: genreId
                },
                success: function(response) {
                    if (response.success) {
                        var setting = response.data;
                        jQuery('#genre-id').val(setting.id);
                        jQuery('#genre-name').val(setting.genre_name);
                        jQuery('#content-type').val(setting.content_type).trigger('change');
                        jQuery('#keywords').val(setting.keywords.join('\n'));
                        jQuery('#news-sources').val(setting.news_sources ? setting.news_sources.join('\n') : '');
                        jQuery('#max-articles').val(setting.max_articles || 10);
                        jQuery('#youtube-channels').val(setting.youtube_channels ? setting.youtube_channels.join('\n') : '');
                        jQuery('#max-videos').val(setting.max_videos || 5);
                        jQuery('#embed-type').val(setting.embed_type || 'responsive');
                        jQuery('#post-categories').val(setting.post_categories ? setting.post_categories.join('\n') : 'blog');
                        jQuery('#post-status').val(setting.post_status || 'draft');
                        jQuery('#cancel-edit').show();
                        
                        // フォームまでスクロール
                        jQuery('html, body').animate({
                            scrollTop: jQuery('#genre-settings-form').offset().top - 50
                        }, 500);
                    } else {
                        alert('設定の読み込みに失敗しました: ' + response.data);
                    }
                }
            });
        }
        
        // 複製ボタンクリック
        function duplicateGenreSetting(genreId, genreName) {
            if (confirm('ジャンル設定「' + genreName + '」を複製しますか？')) {
                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'genre_settings_duplicate',
                        nonce: '<?php echo wp_create_nonce('genre_settings_nonce'); ?>',
                        genre_id: genreId
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('設定を複製しました。');
                            location.reload();
                        } else {
                            alert('複製に失敗しました: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('複製中にエラーが発生しました。');
                    }
                });
            }
        }
        
        // 削除ボタンクリック
        function deleteGenreSetting(genreId, genreName) {
            if (confirm('ジャンル設定「' + genreName + '」を削除しますか？')) {
                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'genre_settings_delete',
                        nonce: '<?php echo wp_create_nonce('genre_settings_nonce'); ?>',
                        genre_id: genreId
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('設定を削除しました。');
                            location.reload();
                        } else {
                            alert('削除に失敗しました: ' + response.data);
                        }
                    }
                });
            }
        }
        
        // 投稿作成ボタンクリック
        function executeGenreSetting(genreId, genreName) {
            var button = jQuery('#execute-btn-' + genreId);
            var originalText = button.text();
            
            button.prop('disabled', true).text('実行中...');
            jQuery('#execution-result').show();
            jQuery('#execution-result-content').html('「' + genreName + '」の投稿作成を開始しています...');
            
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'genre_settings_execute',
                    nonce: '<?php echo wp_create_nonce('genre_settings_nonce'); ?>',
                    genre_id: genreId
                },
                success: function(response) {
                    if (response.success) {
                        jQuery('#execution-result-content').html(response.data);
                    } else {
                        jQuery('#execution-result-content').html('エラー: ' + response.data);
                    }
                },
                error: function() {
                    jQuery('#execution-result-content').html('実行中にエラーが発生しました。');
                },
                complete: function() {
                    button.prop('disabled', false).text(originalText);
                    
                    // 結果エリアまでスクロール
                    jQuery('html, body').animate({
                        scrollTop: jQuery('#execution-result').offset().top - 50
                    }, 500);
                }
            });
        }
        </script>
        
        <style>
        .genre-settings-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .genre-settings-table th,
        .genre-settings-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .genre-settings-table th {
            background-color: #f9f9f9;
            font-weight: bold;
        }
        .genre-settings-table tr:hover {
            background-color: #f5f5f5;
        }
        .keywords-display,
        .categories-display {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .action-buttons {
            white-space: nowrap;
        }
        .action-buttons .button {
            margin-right: 5px;
        }
        </style>
        <?php
    }    
  
  private function render_genre_settings_list($genre_settings) {
        if (empty($genre_settings)) {
            echo '<p>保存されたジャンル設定がありません。</p>';
            return;
        }
        
        echo '<table class="genre-settings-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>ジャンル名</th>';
        echo '<th>タイプ</th>';
        echo '<th>キーワード</th>';
        echo '<th>カテゴリー</th>';
        echo '<th>操作</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($genre_settings as $id => $setting) {
            $keywords_display = implode(', ', array_slice($setting['keywords'], 0, 3));
            if (count($setting['keywords']) > 3) {
                $keywords_display .= '...';
            }
            
            // カテゴリー表示の準備
            $categories = array();
            if (isset($setting['post_categories']) && is_array($setting['post_categories'])) {
                $categories = $setting['post_categories'];
            } elseif (isset($setting['post_category']) && !empty($setting['post_category'])) {
                // 後方互換性のため、古い単一カテゴリー設定もサポート
                $categories = array($setting['post_category']);
            } else {
                $categories = array('blog');
            }
            
            $categories_display = implode(', ', array_slice($categories, 0, 3));
            if (count($categories) > 3) {
                $categories_display .= '...';
            }
            
            $content_type_label = $setting['content_type'] === 'news' ? 'ニュース' : 'YouTube';
            
            echo '<tr>';
            echo '<td><strong>' . esc_html($setting['genre_name']) . '</strong></td>';
            echo '<td>' . esc_html($content_type_label) . '</td>';
            echo '<td><span class="keywords-display" title="' . esc_attr(implode(', ', $setting['keywords'])) . '">' . esc_html($keywords_display) . '</span></td>';
            echo '<td><span class="categories-display" title="' . esc_attr(implode(', ', $categories)) . '">' . esc_html($categories_display) . '</span></td>';
            echo '<td class="action-buttons">';
            echo '<button type="button" class="button" onclick="editGenreSetting(\'' . esc_js($id) . '\')">編集</button>';
            echo '<button type="button" class="button" onclick="duplicateGenreSetting(\'' . esc_js($id) . '\', \'' . esc_js($setting['genre_name']) . '\')">複製</button>';
            echo '<button type="button" id="execute-btn-' . esc_attr($id) . '" class="button button-primary" onclick="executeGenreSetting(\'' . esc_js($id) . '\', \'' . esc_js($setting['genre_name']) . '\')">投稿を作成</button>';
            echo '<button type="button" class="button button-link-delete" onclick="deleteGenreSetting(\'' . esc_js($id) . '\', \'' . esc_js($setting['genre_name']) . '\')">削除</button>';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
    }
    
    public function save_genre_setting() {
        check_ajax_referer('genre_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('権限がありません');
        }
        
        $genre_id = sanitize_text_field($_POST['genre_id']);
        $genre_name = sanitize_text_field($_POST['genre_name']);
        $content_type = sanitize_text_field($_POST['content_type']);
        $keywords = array_filter(array_map('trim', explode("\n", sanitize_textarea_field($_POST['keywords']))));
        
        if (empty($genre_name) || empty($content_type) || empty($keywords)) {
            wp_send_json_error('必須項目が入力されていません');
        }
        
        $post_categories = array_filter(array_map('trim', explode("\n", sanitize_textarea_field($_POST['post_categories']))));
        if (empty($post_categories)) {
            $post_categories = array('blog');
        }
        
        $setting = array(
            'genre_name' => $genre_name,
            'content_type' => $content_type,
            'keywords' => $keywords,
            'post_categories' => $post_categories,
            'post_status' => sanitize_text_field($_POST['post_status']),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        if ($content_type === 'news') {
            $setting['news_sources'] = array_filter(array_map('trim', explode("\n", sanitize_textarea_field($_POST['news_sources']))));
            $setting['max_articles'] = intval($_POST['max_articles']);
        } elseif ($content_type === 'youtube') {
            $setting['youtube_channels'] = array_filter(array_map('trim', explode("\n", sanitize_textarea_field($_POST['youtube_channels']))));
            $setting['max_videos'] = intval($_POST['max_videos']);
            $setting['embed_type'] = sanitize_text_field($_POST['embed_type']);
        }
        
        $genre_settings = $this->get_genre_settings();
        
        if (empty($genre_id)) {
            // 新規作成
            $genre_id = uniqid('genre_');
            $setting['created_at'] = current_time('mysql');
        } else {
            // 更新
            if (!isset($genre_settings[$genre_id])) {
                wp_send_json_error('指定された設定が見つかりません');
            }
            $setting['created_at'] = $genre_settings[$genre_id]['created_at'];
        }
        
        $setting['id'] = $genre_id;
        $genre_settings[$genre_id] = $setting;
        
        update_option($this->option_name, $genre_settings);
        
        wp_send_json_success('設定を保存しました');
    }
    
    public function delete_genre_setting() {
        check_ajax_referer('genre_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('権限がありません');
        }
        
        $genre_id = sanitize_text_field($_POST['genre_id']);
        $genre_settings = $this->get_genre_settings();
        
        if (!isset($genre_settings[$genre_id])) {
            wp_send_json_error('指定された設定が見つかりません');
        }
        
        unset($genre_settings[$genre_id]);
        update_option($this->option_name, $genre_settings);
        
        wp_send_json_success('設定を削除しました');
    }
    
    public function load_genre_setting() {
        check_ajax_referer('genre_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('権限がありません');
        }
        
        $genre_id = sanitize_text_field($_POST['genre_id']);
        $genre_settings = $this->get_genre_settings();
        
        if (!isset($genre_settings[$genre_id])) {
            wp_send_json_error('指定された設定が見つかりません');
        }
        
        wp_send_json_success($genre_settings[$genre_id]);
    }
    
    public function execute_genre_setting() {
        check_ajax_referer('genre_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('権限がありません');
        }
        
        try {
            $genre_id = sanitize_text_field($_POST['genre_id']);
            $genre_settings = $this->get_genre_settings();
            
            if (!isset($genre_settings[$genre_id])) {
                wp_send_json_error('指定された設定が見つかりません');
            }
            
            $setting = $genre_settings[$genre_id];
            
            // デバッグ情報を追加
            $debug_info = array();
            $debug_info[] = 'ジャンル設定実行開始: ' . $setting['genre_name'];
            $debug_info[] = 'コンテンツタイプ: ' . $setting['content_type'];
            $debug_info[] = 'キーワード数: ' . count($setting['keywords']);
            
            if ($setting['content_type'] === 'news') {
                $debug_info[] = 'ニュースソース数: ' . count($setting['news_sources'] ?? array());
                $result = $this->execute_news_crawling($setting);
            } elseif ($setting['content_type'] === 'youtube') {
                $debug_info[] = 'YouTubeチャンネル数: ' . count($setting['youtube_channels'] ?? array());
                $result = $this->execute_youtube_crawling($setting);
            } else {
                wp_send_json_error('不正なコンテンツタイプです: ' . $setting['content_type']);
            }
            
            // デバッグ情報を結果に追加
            $final_result = implode("\n", $debug_info) . "\n\n" . $result;
            
            wp_send_json_success($final_result);
        } catch (Exception $e) {
            wp_send_json_error('実行中にエラーが発生しました: ' . $e->getMessage() . "\n\nスタックトレース:\n" . $e->getTraceAsString());
        }
    }
    
    private function execute_news_crawling($setting) {
        // NewsCrawlerクラスのインスタンスを作成して実行
        if (!class_exists('NewsCrawler')) {
            return 'NewsCrawlerクラスが見つかりません。プラグインが正しく読み込まれていない可能性があります。';
        }
        
        try {
            // 設定を一時的に適用
            $temp_options = array(
                'max_articles' => isset($setting['max_articles']) ? intval($setting['max_articles']) : 10,
                'keywords' => isset($setting['keywords']) && is_array($setting['keywords']) ? $setting['keywords'] : array(),
                'news_sources' => isset($setting['news_sources']) && is_array($setting['news_sources']) ? $setting['news_sources'] : array(),
                'post_categories' => isset($setting['post_categories']) && is_array($setting['post_categories']) ? $setting['post_categories'] : array('blog'),
                'post_status' => isset($setting['post_status']) ? sanitize_text_field($setting['post_status']) : 'draft'
            );
            
            // 必須項目のチェック
            if (empty($temp_options['keywords'])) {
                return 'キーワードが設定されていません。ジャンル設定でキーワードを入力してください。';
            }
            
            if (empty($temp_options['news_sources'])) {
                return 'ニュースソースが設定されていません。ジャンル設定でニュースソースのURLを入力してください。';
            }
            
            // デバッグ情報
            $debug_info = array();
            $debug_info[] = '設定内容:';
            $debug_info[] = '  - 最大記事数: ' . $temp_options['max_articles'];
            $debug_info[] = '  - キーワード: ' . implode(', ', $temp_options['keywords']);
            $debug_info[] = '  - ニュースソース: ' . implode(', ', $temp_options['news_sources']);
            $debug_info[] = '  - 投稿カテゴリー: ' . $temp_options['post_category'];
            $debug_info[] = '  - 投稿ステータス: ' . $temp_options['post_status'];
            
            // 一時的にオプションを更新
            $original_options = get_option('news_crawler_settings', array());
            update_option('news_crawler_settings', array_merge($original_options, $temp_options));
            
            try {
                $news_crawler = new NewsCrawler();
                
                if (!method_exists($news_crawler, 'crawl_news')) {
                    return 'NewsCrawlerクラスにcrawl_newsメソッドが見つかりません。';
                }
                
                $debug_info[] = "\nニュースクロール実行開始...";
                $result = $news_crawler->crawl_news();
                
                // 統計情報を更新
                $this->update_genre_statistics($setting['id'], 'news');
                
                return implode("\n", $debug_info) . "\n\n" . $result;
            } finally {
                // 元の設定を復元
                update_option('news_crawler_settings', $original_options);
            }
        } catch (Exception $e) {
            return 'ニュースクロール実行中にエラーが発生しました: ' . $e->getMessage() . "\n\nファイル: " . $e->getFile() . "\n行: " . $e->getLine();
        } catch (Error $e) {
            return 'ニュースクロール実行中に致命的エラーが発生しました: ' . $e->getMessage() . "\n\nファイル: " . $e->getFile() . "\n行: " . $e->getLine();
        }
    }
    
    private function execute_youtube_crawling($setting) {
        // YouTubeCrawlerクラスのインスタンスを作成して実行
        if (!class_exists('NewsCrawlerYouTubeCrawler')) {
            return 'YouTubeCrawlerクラスが見つかりません。プラグインが正しく読み込まれていない可能性があります。';
        }
        
        try {
            // 基本設定からAPIキーを取得
            $basic_settings = get_option('news_crawler_basic_settings', array());
            if (empty($basic_settings['youtube_api_key'])) {
                return 'YouTube APIキーが設定されていません。基本設定でYouTube APIキーを入力してください。';
            }
            
            // 設定を一時的に適用
            $youtube_channels = isset($setting['youtube_channels']) && is_array($setting['youtube_channels']) ? $setting['youtube_channels'] : array();
            
            // チャンネルIDの配列を確実に作成
            if (empty($youtube_channels) && isset($setting['youtube_channels']) && is_string($setting['youtube_channels'])) {
                $youtube_channels = array_filter(array_map('trim', explode("\n", $setting['youtube_channels'])));
            }
            
            $temp_options = array(
                'api_key' => sanitize_text_field($basic_settings['youtube_api_key']),
                'max_videos' => isset($setting['max_videos']) ? intval($setting['max_videos']) : 5,
                'keywords' => isset($setting['keywords']) && is_array($setting['keywords']) ? $setting['keywords'] : array(),
                'channels' => $youtube_channels,
                'post_categories' => isset($setting['post_categories']) && is_array($setting['post_categories']) ? $setting['post_categories'] : array('blog'),
                'post_status' => isset($setting['post_status']) ? sanitize_text_field($setting['post_status']) : 'draft',
                'embed_type' => isset($setting['embed_type']) ? sanitize_text_field($setting['embed_type']) : 'responsive'
            );
            
            // 必須項目のチェック
            if (empty($temp_options['keywords'])) {
                return 'キーワードが設定されていません。ジャンル設定でキーワードを入力してください。';
            }
            
            if (empty($temp_options['channels'])) {
                return 'YouTubeチャンネルIDが設定されていません。ジャンル設定でYouTubeチャンネルIDを入力してください。';
            }
            
            // 一時的にオプションを更新
            $original_options = get_option('youtube_crawler_settings', array());
            $merged_options = array_merge($original_options, $temp_options);
            update_option('youtube_crawler_settings', $merged_options);
            
            try {
                $youtube_crawler = new NewsCrawlerYouTubeCrawler();
                
                if (!method_exists($youtube_crawler, 'crawl_youtube_with_options')) {
                    // 既存のメソッドを使用する場合の処理
                    if (!method_exists($youtube_crawler, 'crawl_youtube')) {
                        return 'NewsCrawlerYouTubeCrawlerクラスにcrawl_youtubeメソッドが見つかりません。';
                    }
                    
                    $result = $youtube_crawler->crawl_youtube();
                } else {
                    // 新しいメソッドを使用してオプションを直接渡す
                    $result = $youtube_crawler->crawl_youtube_with_options($merged_options);
                }
                
                // 統計情報を更新
                $this->update_genre_statistics($setting['id'], 'youtube');
                
                return $result;
            } finally {
                // 元の設定を復元
                update_option('youtube_crawler_settings', $original_options);
            }
        } catch (Exception $e) {
            return 'YouTubeクロール実行中にエラーが発生しました: ' . $e->getMessage() . "\n\nファイル: " . $e->getFile() . "\n行: " . $e->getLine();
        } catch (Error $e) {
            return 'YouTubeクロール実行中に致命的エラーが発生しました: ' . $e->getMessage() . "\n\nファイル: " . $e->getFile() . "\n行: " . $e->getLine();
        }
    }
    
    private function get_genre_settings() {
        return get_option($this->option_name, array());
    }
    
    public function duplicate_genre_setting() {
        check_ajax_referer('genre_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('権限がありません');
        }
        
        $genre_id = sanitize_text_field($_POST['genre_id']);
        $genre_settings = $this->get_genre_settings();
        
        if (!isset($genre_settings[$genre_id])) {
            wp_send_json_error('指定された設定が見つかりません');
        }
        
        // 元の設定をコピー
        $original_setting = $genre_settings[$genre_id];
        
        // 新しいIDを生成
        $new_genre_id = uniqid('genre_');
        
        // 複製用の設定を作成
        $duplicated_setting = $original_setting;
        $duplicated_setting['id'] = $new_genre_id;
        $duplicated_setting['genre_name'] = $original_setting['genre_name'];
        $duplicated_setting['created_at'] = current_time('mysql');
        $duplicated_setting['updated_at'] = current_time('mysql');
        
        // 設定を保存
        $genre_settings[$new_genre_id] = $duplicated_setting;
        update_option($this->option_name, $genre_settings);
        
        wp_send_json_success('設定を複製しました');
    }
    
    private function update_genre_statistics($genre_id, $content_type) {
        $stats_option = 'news_crawler_genre_stats';
        $stats = get_option($stats_option, array());
        
        if (!isset($stats[$genre_id])) {
            $stats[$genre_id] = array(
                'total_executions' => 0,
                'last_execution' => '',
                'content_type' => $content_type
            );
        }
        
        $stats[$genre_id]['total_executions']++;
        $stats[$genre_id]['last_execution'] = current_time('mysql');
        
        update_option($stats_option, $stats);
    }
}