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
        add_action('wp_ajax_test_openai_summary', array($this, 'test_openai_summary'));
        
        // 自動投稿のスケジュール処理
        add_action('news_crawler_auto_posting_cron', array($this, 'execute_auto_posting'));
        add_action('wp_loaded', array($this, 'setup_auto_posting_cron'));
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
        
        // アイキャッチ生成設定セクション
        add_settings_section(
            'featured_image_settings',
            'アイキャッチ自動生成設定',
            array($this, 'featured_image_section_callback'),
            'news-crawler-basic'
        );
        
        add_settings_field(
            'openai_api_key',
            'OpenAI APIキー',
            array($this, 'openai_api_key_callback'),
            'news-crawler-basic',
            'featured_image_settings'
        );
        
        add_settings_field(
            'unsplash_access_key',
            'Unsplash Access Key',
            array($this, 'unsplash_access_key_callback'),
            'news-crawler-basic',
            'featured_image_settings'
        );
        
        add_settings_field(
            'auto_featured_image',
            'アイキャッチ自動生成',
            array($this, 'auto_featured_image_callback'),
            'news-crawler-basic',
            'featured_image_settings'
        );
        
        add_settings_field(
            'featured_image_method',
            'アイキャッチ生成方法',
            array($this, 'featured_image_method_callback'),
            'news-crawler-basic',
            'featured_image_settings'
        );
        
        // テンプレート設定フィールド
        add_settings_field(
            'template_settings',
            'テンプレート設定',
            array($this, 'template_settings_callback'),
            'news-crawler-basic',
            'featured_image_settings'
        );
        
        // 要約生成設定セクション
        add_settings_section(
            'summary_generation_settings',
            'AI要約自動生成設定',
            array($this, 'summary_generation_section_callback'),
            'news-crawler-basic'
        );
        
        add_settings_field(
            'auto_summary_generation',
            '要約自動生成',
            array($this, 'auto_summary_generation_callback'),
            'news-crawler-basic',
            'summary_generation_settings'
        );
        
        add_settings_field(
            'summary_generation_model',
            '使用モデル',
            array($this, 'summary_generation_model_callback'),
            'news-crawler-basic',
            'summary_generation_settings'
        );
    }
    
    public function basic_section_callback() {
        echo '<p>すべてのジャンル設定で共通して使用される基本設定です。</p>';
    }
    
    public function featured_image_section_callback() {
        echo '<p>投稿作成時のアイキャッチ自動生成に関する設定です。</p>';
    }
    
    public function summary_generation_section_callback() {
        echo '<p>投稿作成時のAI要約自動生成に関する設定です。OpenAI APIキーが設定されている必要があります。</p>';
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
    
    public function openai_api_key_callback() {
        $options = get_option('news_crawler_basic_settings', array());
        $api_key = isset($options['openai_api_key']) ? $options['openai_api_key'] : '';
        echo '<input type="text" name="news_crawler_basic_settings[openai_api_key]" value="' . esc_attr($api_key) . '" size="50" />';
        echo '<p class="description">AI画像生成とAI要約生成に使用するOpenAI APIキーを入力してください。</p>';
    }
    
    public function unsplash_access_key_callback() {
        $options = get_option('news_crawler_basic_settings', array());
        $access_key = isset($options['unsplash_access_key']) ? $options['unsplash_access_key'] : '';
        echo '<input type="text" name="news_crawler_basic_settings[unsplash_access_key]" value="' . esc_attr($access_key) . '" size="50" />';
        echo '<p class="description">Unsplash画像取得に使用するAccess Keyを入力してください。</p>';
    }
    
    public function auto_featured_image_callback() {
        $options = get_option('news_crawler_basic_settings', array());
        $enabled = isset($options['auto_featured_image']) ? $options['auto_featured_image'] : true; // デフォルトをtrueに変更
        echo '<input type="checkbox" name="news_crawler_basic_settings[auto_featured_image]" value="1" ' . checked(1, $enabled, false) . ' />';
        echo '<label for="news_crawler_basic_settings[auto_featured_image]">投稿作成時に自動でアイキャッチを生成する</label>';
        echo '<p class="description">ジャンル設定で個別に設定されていない場合に適用されます。</p>';
    }
    
    public function auto_summary_generation_callback() {
        $options = get_option('news_crawler_basic_settings', array());
        $enabled = isset($options['auto_summary_generation']) ? $options['auto_summary_generation'] : false;
        echo '<input type="checkbox" name="news_crawler_basic_settings[auto_summary_generation]" value="1" ' . checked(1, $enabled, false) . ' />';
        echo '<label for="news_crawler_basic_settings[auto_summary_generation]">投稿作成時に自動でAI要約とまとめを生成する</label>';
        echo '<p class="description">OpenAI APIキーが設定されている必要があります。</p>';
    }
    
    public function summary_generation_model_callback() {
        $options = get_option('news_crawler_basic_settings', array());
        $model = isset($options['summary_generation_model']) ? $options['summary_generation_model'] : 'gpt-3.5-turbo';
        $models = array(
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo (推奨)',
            'gpt-4' => 'GPT-4 (高品質)',
            'gpt-4-turbo' => 'GPT-4 Turbo (最新)'
        );
        echo '<select name="news_crawler_basic_settings[summary_generation_model]">';
        foreach ($models as $value => $label) {
            echo '<option value="' . $value . '" ' . selected($value, $model, false) . '>' . $label . '</option>';
        }
        echo '</select>';
        echo '<p class="description">要約生成に使用するOpenAIモデルを選択してください。</p>';
    }
    
    public function featured_image_method_callback() {
        $options = get_option('news_crawler_basic_settings', array());
        $method = isset($options['featured_image_method']) ? $options['featured_image_method'] : 'ai'; // デフォルトを'ai'に変更
        
        $methods = array(
            'template' => 'テンプレート生成（軽量・高速）',
            'ai' => 'AI生成（OpenAI DALL-E）',
            'unsplash' => 'Unsplash画像取得'
        );
        
        echo '<select name="news_crawler_basic_settings[featured_image_method]">';
        foreach ($methods as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($value, $method, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">デフォルトのアイキャッチ生成方法を選択してください。</p>';
    }
    
    public function sanitize_basic_settings($input) {
        $sanitized = array();
        
        if (isset($input['youtube_api_key'])) {
            $sanitized['youtube_api_key'] = sanitize_text_field($input['youtube_api_key']);
        }
        
        if (isset($input['default_post_author'])) {
            $sanitized['default_post_author'] = intval($input['default_post_author']);
        }
        
        if (isset($input['openai_api_key'])) {
            $sanitized['openai_api_key'] = sanitize_text_field($input['openai_api_key']);
        }
        
        if (isset($input['unsplash_access_key'])) {
            $sanitized['unsplash_access_key'] = sanitize_text_field($input['unsplash_access_key']);
        }
        
        if (isset($input['auto_featured_image'])) {
            $sanitized['auto_featured_image'] = (bool) $input['auto_featured_image'];
        }
        
        if (isset($input['featured_image_method'])) {
            $allowed_methods = array('template', 'ai', 'unsplash');
            $method = sanitize_text_field($input['featured_image_method']);
            $sanitized['featured_image_method'] = in_array($method, $allowed_methods) ? $method : 'template';
        }
        
        if (isset($input['auto_summary_generation'])) {
            $sanitized['auto_summary_generation'] = (bool) $input['auto_summary_generation'];
        }
        
        if (isset($input['summary_generation_model'])) {
            $allowed_models = array('gpt-3.5-turbo', 'gpt-4', 'gpt-4-turbo');
            $model = sanitize_text_field($input['summary_generation_model']);
            $sanitized['summary_generation_model'] = in_array($model, $allowed_models) ? $model : 'gpt-3.5-turbo';
        }
        
        // テンプレート設定の処理
        if (isset($input['template_width'])) {
            $sanitized['template_width'] = intval($input['template_width']);
        }
        
        if (isset($input['template_height'])) {
            $sanitized['template_height'] = intval($input['template_height']);
        }
        
        if (isset($input['bg_color1'])) {
            $sanitized['bg_color1'] = sanitize_hex_color($input['bg_color1']);
        }
        
        if (isset($input['bg_color2'])) {
            $sanitized['bg_color2'] = sanitize_hex_color($input['bg_color2']);
        }
        
        if (isset($input['text_color'])) {
            $sanitized['text_color'] = sanitize_hex_color($input['text_color']);
        }
        
        if (isset($input['font_size'])) {
            $sanitized['font_size'] = intval($input['font_size']);
        }
        
        if (isset($input['text_scale'])) {
            $sanitized['text_scale'] = intval($input['text_scale']);
        }
        
        return $sanitized;
    }
    
    public function template_settings_callback() {
        $options = get_option('news_crawler_basic_settings', array());
        ?>
        <div class="template-settings">
            <table class="form-table">
                <tr>
                    <th scope="row">画像サイズ</th>
                    <td>
                        <input type="number" name="news_crawler_basic_settings[template_width]" value="<?php echo esc_attr($options['template_width'] ?? 1200); ?>" min="400" max="2000" style="width: 80px;" /> × 
                        <input type="number" name="news_crawler_basic_settings[template_height]" value="<?php echo esc_attr($options['template_height'] ?? 630); ?>" min="200" max="1200" style="width: 80px;" /> px
                        <p class="description">アイキャッチ画像のサイズを指定してください。</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">背景色1</th>
                    <td>
                        <input type="color" name="news_crawler_basic_settings[bg_color1]" value="<?php echo esc_attr($options['bg_color1'] ?? '#4F46E5'); ?>" />
                        <p class="description">グラデーション背景の開始色</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">背景色2</th>
                    <td>
                        <input type="color" name="news_crawler_basic_settings[bg_color2]" value="<?php echo esc_attr($options['bg_color2'] ?? '#7C3AED'); ?>" />
                        <p class="description">グラデーション背景の終了色</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">テキスト色</th>
                    <td>
                        <input type="color" name="news_crawler_basic_settings[text_color]" value="<?php echo esc_attr($options['text_color'] ?? '#FFFFFF'); ?>" />
                        <p class="description">タイトルテキストの色</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">フォントサイズ</th>
                    <td>
                        <input type="number" name="news_crawler_basic_settings[font_size]" value="<?php echo esc_attr($options['font_size'] ?? 48); ?>" min="24" max="120" style="width: 80px;" /> px
                        <p class="description">TTFフォント使用時のサイズ。内蔵フォント使用時は自動調整されます。</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">文字拡大倍率</th>
                    <td>
                        <select name="news_crawler_basic_settings[text_scale]">
                            <option value="2" <?php selected($options['text_scale'] ?? 3, 2); ?>>2倍</option>
                            <option value="3" <?php selected($options['text_scale'] ?? 3, 3); ?>>3倍（推奨）</option>
                            <option value="4" <?php selected($options['text_scale'] ?? 3, 4); ?>>4倍</option>
                            <option value="5" <?php selected($options['text_scale'] ?? 3, 5); ?>>5倍</option>
                        </select>
                        <p class="description">内蔵フォント使用時の文字拡大倍率。文字が小さい場合は4倍または5倍を選択してください。</p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
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
            
            <hr>
            
            <h2>AI要約生成のテスト</h2>
            <p>OpenAI APIを使用した要約生成機能をテストできます。テスト用のサンプルテキストで要約とまとめを生成します。</p>
            
            <div class="card">
                <h3>テスト用サンプルテキスト</h3>
                <textarea id="test-text" rows="8" cols="80" style="width: 100%;">人工知能（AI）技術の進歩により、私たちの日常生活は大きく変化しています。スマートフォンの音声アシスタントから、自動運転車、医療診断システムまで、AIは様々な分野で活用されています。

特に注目されているのは、ChatGPTなどの大規模言語モデル（LLM）の登場です。これらの技術により、自然な会話のような対話が可能になり、文章作成、翻訳、プログラミング支援など、多様なタスクを支援できるようになりました。

しかし、AI技術の発展とともに、プライバシーの保護、雇用への影響、AIの倫理的な使用など、様々な課題も浮上しています。これらの課題に対して、適切な規制やガイドラインの策定が求められています。</textarea>
                
                <p>
                    <button type="button" id="test-summary-generation" class="button button-primary">要約生成をテスト</button>
                    <span id="test-status" style="margin-left: 10px;"></span>
                </p>
                
                <div id="test-result" style="display: none; margin-top: 20px; padding: 15px; background: #f7f7f7; border: 1px solid #ccc; border-radius: 4px;">
                    <h4>生成結果：</h4>
                    <div id="test-summary-content"></div>
                </div>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                $('#test-summary-generation').click(function() {
                    var button = $(this);
                    var statusSpan = $('#test-status');
                    var resultDiv = $('#test-result');
                    var resultContent = $('#test-summary-content');
                    
                    button.prop('disabled', true);
                    statusSpan.html('要約生成中...');
                    resultDiv.hide();
                    
                    var testText = $('#test-text').val();
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'test_openai_summary',
                            nonce: '<?php echo wp_create_nonce('openai_summary_test_nonce'); ?>',
                            text: testText
                        },
                        success: function(response) {
                            if (response.success) {
                                var result = response.data;
                                var html = '';
                                
                                if (result.summary) {
                                    html += '<h5>この記事の要約</h5>';
                                    html += '<p>' + result.summary + '</p>';
                                }
                                
                                if (result.conclusion) {
                                    html += '<h5>まとめ</h5>';
                                    html += '<p>' + result.conclusion + '</p>';
                                }
                                
                                resultContent.html(html);
                                resultDiv.show();
                                statusSpan.html('✅ 完了');
                            } else {
                                statusSpan.html('❌ エラー: ' + response.data);
                            }
                        },
                        error: function() {
                            statusSpan.html('❌ 通信エラー');
                        },
                        complete: function() {
                            button.prop('disabled', false);
                        }
                    });
                });
            });
            </script>
        </div>
        <?php
    }
    
    public function main_admin_page() {
        $genre_settings = $this->get_genre_settings();
        ?>
        <div class="wrap">
            <h1>News Crawler - ジャンル設定</h1>
            
            <!-- デバッグ情報表示エリア -->
            <div id="debug-info" style="margin-bottom: 20px; display: none;">
                <div class="card">
                    <h3>デバッグ情報</h3>
                    <div id="debug-content" style="white-space: pre-wrap; background: #f7f7f7; padding: 15px; border: 1px solid #ccc; border-radius: 4px; max-height: 300px; overflow-y: auto;"></div>
                    <p><button type="button" id="clear-debug" class="button">デバッグ情報をクリア</button></p>
                </div>
            </div>
            
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
                            <tr>
                                <th scope="row">アイキャッチ自動生成</th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="auto-featured-image" name="auto_featured_image" value="1" checked>
                                        投稿作成時にアイキャッチを自動生成する
                                    </label>
                                    <div id="featured-image-settings" style="margin-top: 10px; display: none;">
                                        <select id="featured-image-method" name="featured_image_method">
                                            <option value="template">テンプレート生成</option>
                                            <option value="ai" selected>AI画像生成 (OpenAI DALL-E)</option>
                                            <option value="unsplash">Unsplash画像取得</option>
                                        </select>
                                        <p class="description">アイキャッチの生成方法を選択してください。</p>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">自動投稿</th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="auto-posting" name="auto_posting" value="1">
                                        自動投稿を有効にする
                                    </label>
                                    <div id="auto-posting-settings" style="margin-top: 10px; display: none;">
                                        <table class="form-table" style="margin: 0;">
                                            <tr>
                                                <th scope="row" style="padding: 5px 0;">投稿頻度</th>
                                                <td style="padding: 5px 0;">
                                                    <select id="posting-frequency" name="posting_frequency">
                                                        <option value="daily">毎日</option>
                                                        <option value="weekly">1週間</option>
                                                        <option value="monthly">毎月</option>
                                                        <option value="custom">カスタム</option>
                                                    </select>
                                                    <div id="custom-frequency-settings" style="margin-top: 5px; display: none;">
                                                        <input type="number" id="custom-frequency-days" name="custom_frequency_days" value="7" min="1" max="365" style="width: 80px;" /> 日ごと
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row" style="padding: 5px 0;">投稿記事数上限</th>
                                                <td style="padding: 5px 0;">
                                                    <input type="number" id="max-posts-per-execution" name="max_posts_per_execution" value="3" min="1" max="20" style="width: 80px;" /> 件
                                                    <p class="description" style="margin: 5px 0 0 0;">1回の実行で作成する投稿の最大数</p>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row" style="padding: 5px 0;">次回実行予定</th>
                                                <td style="padding: 5px 0;">
                                                    <span id="next-execution-time">未設定</span>
                                                    <p class="description" style="margin: 5px 0 0 0;">自動投稿の次回実行予定時刻</p>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
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
            
            <!-- トラブルシューティングヘルプ -->

        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // デバッグ情報の表示
            function showDebugInfo() {
                var debugInfo = [];
                
                // キーワードマッチングのデバッグ情報を収集
                if (typeof window.news_crawler_keyword_debug !== 'undefined') {
                    debugInfo.push('=== ニュースキーワードマッチングデバッグ ===');
                    debugInfo.push(window.news_crawler_keyword_debug.join('\n\n'));
                }
                
                if (typeof window.youtube_crawler_keyword_debug !== 'undefined') {
                    debugInfo.push('\n=== YouTubeキーワードマッチングデバッグ ===');
                    debugInfo.push(window.youtube_crawler_keyword_debug.join('\n\n'));
                }
                
                if (debugInfo.length > 0) {
                    $('#debug-content').html(debugInfo.join('\n\n'));
                    $('#debug-info').show();
                }
            }
            
            // 定期的にデバッグ情報をチェック
            setInterval(showDebugInfo, 2000);
            
            // デバッグ情報クリア
            $('#clear-debug').click(function() {
                $('#debug-content').html('');
                $('#debug-info').hide();
                // グローバル変数もクリア
                if (typeof window.news_crawler_keyword_debug !== 'undefined') {
                    window.news_crawler_keyword_debug = [];
                }
                if (typeof window.youtube_crawler_keyword_debug !== 'undefined') {
                    window.youtube_crawler_keyword_debug = [];
                }
            });
            

            
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
            
            // アイキャッチ自動生成チェックボックス変更時の設定表示切り替え
            $('#auto-featured-image').change(function() {
                if ($(this).is(':checked')) {
                    $('#featured-image-settings').show();
                } else {
                    $('#featured-image-settings').hide();
                }
            });
            
            // 自動投稿チェックボックス変更時の設定表示切り替え
            $('#auto-posting').change(function() {
                if ($(this).is(':checked')) {
                    $('#auto-posting-settings').show();
                } else {
                    $('#auto-posting-settings').hide();
                }
            });
            
            // 投稿頻度変更時のカスタム設定表示切り替え
            $('#posting-frequency').change(function() {
                var frequency = $(this).val();
                if (frequency === 'custom') {
                    $('#custom-frequency-settings').show();
                } else {
                    $('#custom-frequency-settings').hide();
                }
                updateNextExecutionTime();
            });
            
            // カスタム頻度日数変更時
            $('#custom-frequency-days').change(function() {
                updateNextExecutionTime();
            });
            
            // 次回実行予定時刻を更新
            function updateNextExecutionTime() {
                var frequency = $('#posting-frequency').val();
                var customDays = $('#custom-frequency-days').val();
                var now = new Date();
                var nextExecution = new Date();
                
                switch (frequency) {
                    case 'daily':
                        nextExecution.setDate(now.getDate() + 1);
                        break;
                    case 'weekly':
                        nextExecution.setDate(now.getDate() + 7);
                        break;
                    case 'monthly':
                        nextExecution.setMonth(now.getMonth() + 1);
                        break;
                    case 'custom':
                        nextExecution.setDate(now.getDate() + parseInt(customDays));
                        break;
                }
                
                var timeString = nextExecution.getFullYear() + '年' + 
                               (nextExecution.getMonth() + 1) + '月' + 
                               nextExecution.getDate() + '日 ' +
                               nextExecution.getHours().toString().padStart(2, '0') + ':' +
                               nextExecution.getMinutes().toString().padStart(2, '0');
                
                $('#next-execution-time').text(timeString);
            }
            
            // 初期表示時に次回実行予定時刻を更新
            updateNextExecutionTime();
            
            // 初期表示時にアイキャッチ設定を表示
            $('#featured-image-settings').show();
            
            // フォーム送信
            $('#genre-settings-form').submit(function(e) {
                e.preventDefault();
                
                // チェックボックスの値を明示的に処理
                var autoFeaturedImage = $('#auto-featured-image').is(':checked') ? 1 : 0;
                var autoPosting = $('#auto-posting').is(':checked') ? 1 : 0;
                
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
                    post_status: $('#post-status').val(),
                    auto_featured_image: autoFeaturedImage,
                    featured_image_method: $('#featured-image-method').val(),
                    auto_posting: autoPosting,
                    posting_frequency: $('#posting-frequency').val(),
                    custom_frequency_days: $('#custom-frequency-days').val(),
                    max_posts_per_execution: $('#max-posts-per-execution').val()
                };
                
                // デバッグ情報をコンソールに出力
                console.log('Form submission - auto_posting checkbox checked:', $('#auto-posting').is(':checked'));
                console.log('Form submission - auto_posting processed value:', autoPosting);
                console.log('Form submission - auto_posting in formData:', formData.auto_posting);
                console.log('Form submission - full formData:', formData);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('エラー: ' + response.data);
                        }
                    },
                    error: function(xhr, status, error) {
                        var errorMessage = '保存中にエラーが発生しました。';
                        if (xhr.responseJSON && xhr.responseJSON.data) {
                            errorMessage = xhr.responseJSON.data;
                        } else if (xhr.statusText) {
                            errorMessage = '通信エラー: ' + xhr.statusText;
                        } else if (error) {
                            errorMessage = 'エラー: ' + error;
                        }
                        alert(errorMessage);
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
                        jQuery('#auto-featured-image').prop('checked', setting.auto_featured_image == 1).trigger('change');
                        jQuery('#featured-image-method').val(setting.featured_image_method || 'template');
                        jQuery('#auto-posting').prop('checked', setting.auto_posting == 1).trigger('change');
                        jQuery('#posting-frequency').val(setting.posting_frequency || 'daily').trigger('change');
                        jQuery('#custom-frequency-days').val(setting.custom_frequency_days || 7);
                        jQuery('#max-posts-per-execution').val(setting.max_posts_per_execution || 3);
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
                            location.reload();
                        } else {
                            alert('複製に失敗しました: ' + response.data);
                        }
                    },
                    error: function(xhr, status, error) {
                        var errorMessage = '複製中にエラーが発生しました。';
                        if (xhr.responseJSON && xhr.responseJSON.data) {
                            errorMessage = xhr.responseJSON.data;
                        } else if (xhr.statusText) {
                            errorMessage = '通信エラー: ' + xhr.statusText;
                        } else if (error) {
                            errorMessage = 'エラー: ' + error;
                        }
                        alert(errorMessage);
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
                            location.reload();
                        } else {
                            alert('削除に失敗しました: ' + response.data);
                        }
                    },
                    error: function(xhr, status, error) {
                        var errorMessage = '削除中にエラーが発生しました。';
                        if (xhr.responseJSON && xhr.responseJSON.data) {
                            errorMessage = xhr.responseJSON.data;
                        } else if (xhr.statusText) {
                            errorMessage = '通信エラー: ' + xhr.statusText;
                        } else if (error) {
                            errorMessage = 'エラー: ' + error;
                        }
                        alert(errorMessage);
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
                error: function(xhr, status, error) {
                    var errorMessage = '実行中にエラーが発生しました。';
                    if (xhr.responseJSON && xhr.responseJSON.data) {
                        errorMessage = xhr.responseJSON.data;
                    } else if (xhr.statusText) {
                        errorMessage = '通信エラー: ' + xhr.statusText;
                    } else if (error) {
                        errorMessage = 'エラー: ' + error;
                    }
                    jQuery('#execution-result-content').html('エラー: ' + errorMessage);
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
        echo '<th>アイキャッチ</th>';
        echo '<th>自動投稿</th>';
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
            // アイキャッチ設定の表示
            $featured_image_status = '';
            if (isset($setting['auto_featured_image']) && $setting['auto_featured_image']) {
                $method = isset($setting['featured_image_method']) ? $setting['featured_image_method'] : 'template';
                $method_labels = array(
                    'template' => 'テンプレート',
                    'ai' => 'AI生成',
                    'unsplash' => 'Unsplash'
                );
                $featured_image_status = '有効 (' . $method_labels[$method] . ')';
            } else {
                $featured_image_status = '無効';
            }
            
            echo '<td><strong>' . esc_html($setting['genre_name']) . '</strong></td>';
            echo '<td>' . esc_html($content_type_label) . '</td>';
            echo '<td><span class="keywords-display" title="' . esc_attr(implode(', ', $setting['keywords'])) . '">' . esc_html($keywords_display) . '</span></td>';
            echo '<td><span class="categories-display" title="' . esc_attr(implode(', ', $categories)) . '">' . esc_html($categories_display) . '</span></td>';
            echo '<td>' . esc_html($featured_image_status) . '</td>';
            
            // 自動投稿設定の表示
            $auto_posting_status = '';
            if (isset($setting['auto_posting']) && $setting['auto_posting']) {
                $frequency = isset($setting['posting_frequency']) ? $setting['posting_frequency'] : 'daily';
                $frequency_labels = array(
                    'daily' => '毎日',
                    'weekly' => '1週間',
                    'monthly' => '毎月',
                    'custom' => 'カスタム'
                );
                $max_posts = isset($setting['max_posts_per_execution']) ? $setting['max_posts_per_execution'] : 3;
                $auto_posting_status = '有効 (' . $frequency_labels[$frequency] . ', ' . $max_posts . '件)';
            } else {
                $auto_posting_status = '無効';
            }
            
            echo '<td>' . esc_html($auto_posting_status) . '</td>';
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
        
        // デバッグ情報を記録
        error_log('Genre Settings Save - POST data: ' . print_r($_POST, true));
        
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
        
        // 自動投稿の値を明示的に処理
        $auto_posting = 0;
        if (isset($_POST['auto_posting'])) {
            if ($_POST['auto_posting'] === '1' || $_POST['auto_posting'] === 1) {
                $auto_posting = 1;
            }
        }
        error_log('Genre Settings Save - Raw auto_posting from POST: ' . (isset($_POST['auto_posting']) ? $_POST['auto_posting'] : 'not set'));
        error_log('Genre Settings Save - Processed auto_posting value: ' . $auto_posting);
        
        $setting = array(
            'genre_name' => $genre_name,
            'content_type' => $content_type,
            'keywords' => $keywords,
            'post_categories' => $post_categories,
            'post_status' => sanitize_text_field($_POST['post_status']),
            'auto_featured_image' => isset($_POST['auto_featured_image']) ? 1 : 0,
            'featured_image_method' => sanitize_text_field($_POST['featured_image_method'] ?? 'template'),
            'auto_posting' => $auto_posting,
            'posting_frequency' => sanitize_text_field($_POST['posting_frequency'] ?? 'daily'),
            'custom_frequency_days' => intval($_POST['custom_frequency_days'] ?? 7),
            'max_posts_per_execution' => intval($_POST['max_posts_per_execution'] ?? 3),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        // デバッグ情報を記録
        error_log('Genre Settings Save - Processed auto_posting value: ' . $auto_posting);
        error_log('Genre Settings Save - Final setting array: ' . print_r($setting, true));
        
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
            error_log('Genre Settings Save - Creating new genre setting');
        } else {
            // 更新
            if (!isset($genre_settings[$genre_id])) {
                wp_send_json_error('指定された設定が見つかりません');
            }
            $setting['created_at'] = $genre_settings[$genre_id]['created_at'];
            
            // 既存の設定と比較
            $existing_setting = $genre_settings[$genre_id];
            error_log('Genre Settings Save - Updating existing genre setting');
            error_log('Genre Settings Save - Previous auto_posting value: ' . ($existing_setting['auto_posting'] ?? 'not set'));
            error_log('Genre Settings Save - New auto_posting value: ' . $setting['auto_posting']);
        }
        
        $setting['id'] = $genre_id;
        $genre_settings[$genre_id] = $setting;
        
        update_option($this->option_name, $genre_settings);
        
        // 保存後の確認
        $saved_settings = get_option($this->option_name, array());
        if (isset($saved_settings[$genre_id])) {
            error_log('Genre Settings Save - Verification: saved auto_posting value: ' . $saved_settings[$genre_id]['auto_posting']);
        } else {
            error_log('Genre Settings Save - Verification: setting not found after save');
        }
        
        // 自動投稿の設定に応じて次回実行時刻を管理
        if (isset($setting['auto_posting']) && $setting['auto_posting'] == 1) {
            // 自動投稿が有効な場合、次回実行時刻を設定
            error_log('Genre Settings Save - Auto posting enabled, setting next execution time');
            $this->update_next_execution_time($genre_id, $setting);
        } else {
            // 自動投稿が無効な場合、次回実行時刻とログをクリア
            error_log('Genre Settings Save - Auto posting disabled, clearing execution time and logs');
            delete_option('news_crawler_last_execution_' . $genre_id);
            
            // 自動投稿関連のログから該当ジャンルのエントリを削除
            $this->cleanup_auto_posting_logs($genre_id);
        }
        
        error_log('Genre Settings Save - Final auto_posting value in setting: ' . $setting['auto_posting']);
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
        
        // 自動投稿関連のデータをクリーンアップ
        delete_option('news_crawler_last_execution_' . $genre_id);
        $this->cleanup_auto_posting_logs($genre_id);
        
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
    
    /**
     * 投稿にアイキャッチを生成・設定
     */
    private function generate_featured_image_for_post($post_id, $title, $keywords, $setting) {
        if (!isset($setting['auto_featured_image']) || !$setting['auto_featured_image']) {
            return false;
        }
        
        if (!class_exists('NewsCrawlerFeaturedImageGenerator')) {
            return false;
        }
        
        $generator = new NewsCrawlerFeaturedImageGenerator();
        $method = isset($setting['featured_image_method']) ? $setting['featured_image_method'] : 'template';
        
        return $generator->generate_and_set_featured_image($post_id, $title, $keywords, $method);
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
            $debug_info[] = '  - 投稿カテゴリー: ' . implode(', ', $temp_options['post_categories']);
            $debug_info[] = '  - 投稿ステータス: ' . $temp_options['post_status'];
            
            // キーワードの詳細チェック
            $debug_info[] = '';
            $debug_info[] = 'キーワード詳細チェック:';
            foreach ($temp_options['keywords'] as $index => $keyword) {
                $debug_info[] = '  - キーワード[' . $index . ']: "' . $keyword . '" (長さ: ' . strlen($keyword) . '文字)';
                if (empty(trim($keyword))) {
                    $debug_info[] = '    → 警告: 空のキーワードが含まれています';
                }
            }
            
            // ニュースソースの詳細チェック
            $debug_info[] = '';
            $debug_info[] = 'ニュースソース詳細チェック:';
            foreach ($temp_options['news_sources'] as $index => $source) {
                $debug_info[] = '  - ソース[' . $index . ']: "' . $source . '"';
                if (empty(trim($source))) {
                    $debug_info[] = '    → 警告: 空のソースが含まれています';
                } elseif (!filter_var($source, FILTER_VALIDATE_URL)) {
                    $debug_info[] = '    → 警告: 有効なURLではありません';
                }
            }
            
            // 一時的にオプションを更新
            $original_options = get_option('news_crawler_settings', array());
            update_option('news_crawler_settings', array_merge($original_options, $temp_options));
            
            // アイキャッチ生成のために現在の設定を一時保存
            error_log('Genre Settings - News: Saving current setting for featured image generation');
            error_log('Genre Settings - News: Auto featured image: ' . (isset($setting['auto_featured_image']) && $setting['auto_featured_image'] ? 'Yes' : 'No'));
            if (isset($setting['featured_image_method'])) {
                error_log('Genre Settings - News: Featured image method: ' . $setting['featured_image_method']);
            }
            error_log('Genre Settings - News: Setting to save: ' . print_r($setting, true));
            
            $transient_result = set_transient('news_crawler_current_genre_setting', $setting, 300); // 5分間有効
            error_log('Genre Settings - News: Transient save result: ' . ($transient_result ? 'Success' : 'Failed'));
            
            // 保存直後に確認
            $saved_setting = get_transient('news_crawler_current_genre_setting');
            error_log('Genre Settings - News: Verification - saved setting exists: ' . ($saved_setting ? 'Yes' : 'No'));
            if ($saved_setting) {
                error_log('Genre Settings - News: Verification - saved setting content: ' . print_r($saved_setting, true));
            }
            
            try {
                $news_crawler = new NewsCrawler();
                
                if (!method_exists($news_crawler, 'crawl_news')) {
                    return 'NewsCrawlerクラスにcrawl_newsメソッドが見つかりません。';
                }
                
                $debug_info[] = "\nニュースクロール実行開始...";
                
                // 新しいメソッドがあるかチェック
                if (method_exists($news_crawler, 'crawl_news_with_options')) {
                    $result = $news_crawler->crawl_news_with_options($temp_options);
                } else {
                    $result = $news_crawler->crawl_news();
                }
                
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
            
            // デバッグ情報
            $debug_info = array();
            $debug_info[] = '設定内容:';
            $debug_info[] = '  - 最大動画数: ' . $temp_options['max_videos'];
            $debug_info[] = '  - キーワード: ' . implode(', ', $temp_options['keywords']);
            $debug_info[] = '  - YouTubeチャンネル: ' . implode(', ', $temp_options['channels']);
            $debug_info[] = '  - 投稿カテゴリー: ' . implode(', ', $temp_options['post_categories']);
            $debug_info[] = '  - 投稿ステータス: ' . $temp_options['post_status'];
            $debug_info[] = '  - 埋め込みタイプ: ' . $temp_options['embed_type'];
            
            // キーワードの詳細チェック
            $debug_info[] = '';
            $debug_info[] = 'キーワード詳細チェック:';
            foreach ($temp_options['keywords'] as $index => $keyword) {
                $debug_info[] = '  - キーワード[' . $index . ']: "' . $keyword . '" (長さ: ' . strlen($keyword) . '文字)';
                if (empty(trim($keyword))) {
                    $debug_info[] = '    → 警告: 空のキーワードが含まれています';
                }
            }
            
            // YouTubeチャンネルの詳細チェック
            $debug_info[] = '';
            $debug_info[] = 'YouTubeチャンネル詳細チェック:';
            foreach ($temp_options['channels'] as $index => $channel) {
                $debug_info[] = '  - チャンネル[' . $index . ']: "' . $channel . '"';
                if (empty(trim($channel))) {
                    $debug_info[] = '    → 警告: 空のチャンネルIDが含まれています';
                } elseif (!preg_match('/^UC[a-zA-Z0-9_-]{22}$/', trim($channel))) {
                    $debug_info[] = '    → 警告: 有効なYouTubeチャンネルIDではありません（UCで始まる24文字の文字列である必要があります）';
                }
            }
            
            // 一時的にオプションを更新
            $original_options = get_option('youtube_crawler_settings', array());
            $merged_options = array_merge($original_options, $temp_options);
            update_option('youtube_crawler_settings', $merged_options);
            
            // アイキャッチ生成のために現在の設定を一時保存
            error_log('Genre Settings - YouTube: Saving current setting for featured image generation');
            error_log('Genre Settings - YouTube: Auto featured image: ' . (isset($setting['auto_featured_image']) && $setting['auto_featured_image'] ? 'Yes' : 'No'));
            if (isset($setting['featured_image_method'])) {
                error_log('Genre Settings - YouTube: Featured image method: ' . $setting['featured_image_method']);
            }
            error_log('Genre Settings - YouTube: Setting to save: ' . print_r($setting, true));
            
            $transient_result = set_transient('news_crawler_current_genre_setting', $setting, 300); // 5分間有効
            error_log('Genre Settings - YouTube: Transient save result: ' . ($transient_result ? 'Success' : 'Failed'));
            
            // 保存直後に確認
            $saved_setting = get_transient('news_crawler_current_genre_setting');
            error_log('Genre Settings - YouTube: Verification - saved setting exists: ' . ($saved_setting ? 'Yes' : 'No'));
            if ($saved_setting) {
                error_log('Genre Settings - YouTube: Verification - saved setting content: ' . print_r($saved_setting, true));
            }
            
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
                
                return implode("\n", $debug_info) . "\n\n" . $result;
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
    
    /**
     * 自動投稿のスケジュール設定
     */
    public function setup_auto_posting_cron() {
        if (!wp_next_scheduled('news_crawler_auto_posting_cron')) {
            wp_schedule_event(time(), 'hourly', 'news_crawler_auto_posting_cron');
        }
    }
    
    /**
     * 自動投稿の実行処理
     */
    public function execute_auto_posting() {
        $genre_settings = $this->get_genre_settings();
        $current_time = current_time('timestamp');
        
        foreach ($genre_settings as $genre_id => $setting) {
            // 自動投稿が無効または設定されていない場合はスキップ
            if (!isset($setting['auto_posting']) || !$setting['auto_posting']) {
                continue;
            }
            
            // 次回実行時刻をチェック
            $next_execution = $this->get_next_execution_time($setting);
            if ($next_execution > $current_time) {
                continue;
            }
            
            // 自動投稿を実行
            $this->execute_auto_posting_for_genre($setting);
            
            // 次回実行時刻を更新
            $this->update_next_execution_time($genre_id, $setting);
        }
    }
    
    /**
     * 指定されたジャンルの自動投稿を実行
     */
    private function execute_auto_posting_for_genre($setting) {
        try {
            // 投稿記事数上限を適用
            $max_posts = isset($setting['max_posts_per_execution']) ? intval($setting['max_posts_per_execution']) : 3;
            
            if ($setting['content_type'] === 'news') {
                $this->execute_news_crawling_with_limit($setting, $max_posts);
            } elseif ($setting['content_type'] === 'youtube') {
                $this->execute_youtube_crawling_with_limit($setting, $max_posts);
            }
            
            // 実行ログを記録
            $this->log_auto_posting_execution($setting['id'], 'success');
            
        } catch (Exception $e) {
            // エラーログを記録
            $this->log_auto_posting_execution($setting['id'], 'error', $e->getMessage());
        }
    }
    
    /**
     * ニュースクロールを投稿数制限付きで実行
     */
    private function execute_news_crawling_with_limit($setting, $max_posts) {
        // 既存の投稿数をチェック
        $existing_posts = $this->count_recent_posts_by_genre($setting['id']);
        if ($existing_posts >= $max_posts) {
            return;
        }
        
        // 投稿数制限を適用してクロール実行
        $setting['max_articles'] = min($setting['max_articles'] ?? 10, $max_posts - $existing_posts);
        $this->execute_news_crawling($setting);
    }
    
    /**
     * YouTubeクロールを投稿数制限付きで実行
     */
    private function execute_youtube_crawling_with_limit($setting, $max_posts) {
        // 既存の投稿数をチェック
        $existing_posts = $this->count_recent_posts_by_genre($setting['id']);
        if ($existing_posts >= $max_posts) {
            return;
        }
        
        // 投稿数制限を適用してクロール実行
        $setting['max_videos'] = min($setting['max_videos'] ?? 5, $max_posts - $existing_posts);
        $this->execute_youtube_crawling($setting);
    }
    
    /**
     * ジャンル別の最近の投稿数をカウント
     */
    private function count_recent_posts_by_genre($genre_id) {
        $args = array(
            'post_type' => 'post',
            'post_status' => array('publish', 'draft', 'pending'),
            'meta_query' => array(
                array(
                    'key' => '_news_crawler_genre_id',
                    'value' => $genre_id,
                    'compare' => '='
                )
            ),
            'date_query' => array(
                array(
                    'after' => '1 day ago'
                )
            ),
            'posts_per_page' => -1,
            'fields' => 'ids'
        );
        
        $query = new WP_Query($args);
        return $query->found_posts;
    }
    
    /**
     * 次回実行時刻を取得
     */
    private function get_next_execution_time($setting) {
        $last_execution = get_option('news_crawler_last_execution_' . $setting['id'], 0);
        $frequency = $setting['posting_frequency'] ?? 'daily';
        
        switch ($frequency) {
            case 'daily':
                return $last_execution + (24 * 60 * 60); // 24時間後
            case 'weekly':
                return $last_execution + (7 * 24 * 60 * 60); // 7日後
            case 'monthly':
                return $last_execution + (30 * 24 * 60 * 60); // 30日後
            case 'custom':
                $days = $setting['custom_frequency_days'] ?? 7;
                return $last_execution + ($days * 24 * 60 * 60);
            default:
                return $last_execution + (24 * 60 * 60);
        }
    }
    
    /**
     * 次回実行時刻を更新
     */
    private function update_next_execution_time($genre_id, $setting) {
        update_option('news_crawler_last_execution_' . $genre_id, current_time('timestamp'));
    }
    
    /**
     * 自動投稿の実行ログを記録
     */
    private function log_auto_posting_execution($genre_id, $status, $message = '') {
        $logs = get_option('news_crawler_auto_posting_logs', array());
        
        $logs[] = array(
            'genre_id' => $genre_id,
            'status' => $status,
            'message' => $message,
            'timestamp' => current_time('mysql')
        );
        
        // ログは最新100件まで保持
        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }
        
        update_option('news_crawler_auto_posting_logs', $logs);
    }
    
    /**
     * 指定されたジャンルの自動投稿ログをクリーンアップ
     */
    private function cleanup_auto_posting_logs($genre_id) {
        $logs = get_option('news_crawler_auto_posting_logs', array());
        
        if (!empty($logs)) {
            // 指定されたジャンルのログエントリを削除
            $logs = array_filter($logs, function($log) use ($genre_id) {
                return $log['genre_id'] !== $genre_id;
            });
            
            update_option('news_crawler_auto_posting_logs', $logs);
        }
    }
    
    /**
     * OpenAI要約生成のテスト用AJAXハンドラー
     */
    public function test_openai_summary() {
        check_ajax_referer('openai_summary_test_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('権限がありません');
        }
        
        $test_text = sanitize_textarea_field($_POST['text']);
        
        if (empty($test_text)) {
            wp_send_json_error('テスト用テキストが入力されていません');
        }
        
        // 基本設定からOpenAI APIキーを取得
        $basic_settings = get_option('news_crawler_basic_settings', array());
        $api_key = isset($basic_settings['openai_api_key']) ? $basic_settings['openai_api_key'] : '';
        
        if (empty($api_key)) {
            wp_send_json_error('OpenAI APIキーが設定されていません');
        }
        
        try {
            // OpenAI APIを使用して要約を生成
            $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ),
                'body' => json_encode(array(
                    'model' => 'gpt-3.5-turbo',
                    'messages' => array(
                        array(
                            'role' => 'system',
                            'content' => 'あなたは優秀なニュース編集者です。与えられた記事の内容を分析し、簡潔で分かりやすい要約とまとめを作成してください。'
                        ),
                        array(
                            'role' => 'user',
                            'content' => "以下の記事の内容を分析して、以下の形式で回答してください：\n\n記事内容：\n{$test_text}\n\n回答形式：\n## この記事の要約\n（記事の要点を3-4行で簡潔にまとめてください）\n\n## まとめ\n（記事の内容を踏まえた考察や今後の展望を2-3行で述べてください）\n\n注意：\n- 要約は事実に基づいて客観的に作成してください\n- まとめは読者の理解を深めるような洞察を含めてください\n- 日本語で自然な文章にしてください"
                        )
                    ),
                    'max_tokens' => 1000,
                    'temperature' => 0.7
                )),
                'timeout' => 60
            ));
            
            if (is_wp_error($response)) {
                wp_send_json_error('OpenAI API呼び出しエラー: ' . $response->get_error_message());
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (!$data || !isset($data['choices'][0]['message']['content'])) {
                wp_send_json_error('OpenAI APIレスポンスの解析に失敗しました');
            }
            
            $response_content = $data['choices'][0]['message']['content'];
            
            // レスポンスから要約とまとめを抽出
            $summary = '';
            $conclusion = '';
            
            if (preg_match('/## この記事の要約\s*(.+?)(?=\s*##|\s*$)/s', $response_content, $matches)) {
                $summary = trim($matches[1]);
            }
            
            if (preg_match('/## まとめ\s*(.+?)(?=\s*##|\s*$)/s', $response_content, $matches)) {
                $conclusion = trim($matches[1]);
            }
            
            if (empty($summary) && empty($conclusion)) {
                $summary = 'AIによる要約生成中にエラーが発生しました。';
                $conclusion = '要約の生成に失敗しました。';
            }
            
            wp_send_json_success(array(
                'summary' => $summary,
                'conclusion' => $conclusion
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('要約生成中にエラーが発生しました: ' . $e->getMessage());
        }
    }
    

}