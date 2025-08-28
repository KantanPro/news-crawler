<?php
/**
 * Plugin Name: News Crawler
 * Plugin URI: https://github.com/KantanPro/news-crawler
 * Description: 指定されたニュースソースから自動的に記事を取得し、WordPressサイトに投稿として追加するプラグイン。YouTube動画のクロール機能も含む。XPosterに依存しない独立したプラグインとして動作します。
 * Version: 1.9.12
 * Author: KantanPro
 * Author URI: https://github.com/KantanPro
 * License: MIT
 * Text Domain: news-crawler
 */

// 直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

// 必要なクラスファイルをインクルード
require_once plugin_dir_path(__FILE__) . 'includes/class-genre-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-youtube-crawler.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-featured-image-generator.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-eyecatch-generator.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-eyecatch-admin.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-openai-summarizer.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-post-editor-summary.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ogp-manager.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ogp-settings.php';


// プラグイン初期化
function news_crawler_init() {
    // ジャンル設定管理クラスを初期化
    if (class_exists('NewsCrawlerGenreSettings')) {
        new NewsCrawlerGenreSettings();
    }
    
    // 既存のNewsCrawlerクラスも初期化（後方互換性のため）
    if (class_exists('NewsCrawler')) {
        // メニュー登録を無効化したNewsCrawlerクラスは手動で初期化しない
        // ジャンル設定から呼び出される際にインスタンス化される
    }
    
    // 既存のYouTubeCrawlerクラスも初期化（後方互換性のため）
    if (class_exists('YouTubeCrawler')) {
        // メニュー登録を無効化したYouTubeCrawlerクラスは手動で初期化しない
    }
    
    // 既存のYouTubeCrawlerクラス（新版）も初期化
    if (class_exists('NewsCrawlerYouTubeCrawler')) {
        // メニュー登録を無効化したクラスは手動で初期化しない
    }
    
    // アイキャッチ生成クラスを初期化
    if (class_exists('NewsCrawlerFeaturedImageGenerator')) {
        new NewsCrawlerFeaturedImageGenerator();
    }
    
    // アイキャッチ画像生成クラスを初期化
    if (class_exists('News_Crawler_Eyecatch_Generator')) {
        new News_Crawler_Eyecatch_Generator();
    }
    
    // アイキャッチ画像管理画面クラスを初期化
    if (class_exists('News_Crawler_Eyecatch_Admin')) {
        new News_Crawler_Eyecatch_Admin();
    }
    
    // AI要約生成クラスを初期化
    if (class_exists('NewsCrawlerOpenAISummarizer')) {
        new NewsCrawlerOpenAISummarizer();
    }
    
    // 投稿編集画面の要約生成クラスを初期化
    if (class_exists('NewsCrawlerPostEditorSummary')) {
        new NewsCrawlerPostEditorSummary();
    }
    
    // OGP管理クラスを初期化
    if (class_exists('NewsCrawlerOGPManager')) {
        new NewsCrawlerOGPManager();
    }
    
    // OGP設定クラスを初期化
    if (class_exists('NewsCrawlerOGPSettings')) {
        new NewsCrawlerOGPSettings();
    }
    

    

}
add_action('plugins_loaded', 'news_crawler_init');

// News Crawler用の処理のための投稿ステータス変更フック
add_action('news_crawler_update_post_status', 'news_crawler_do_update_post_status', 10, 2);

// News Crawler独自の投稿監視フックを追加
if (function_exists('wp_after_insert_post')) {
    // WordPress 5.6以降用
    add_action('wp_after_insert_post', 'news_crawler_save_post', 10, 2);
    add_action('wp_after_insert_post', 'news_crawler_do_post_update', 15, 4);
} else {
    // 従来のWordPress用
    add_action('save_post', 'news_crawler_save_post', 10, 2);
    add_action('save_post', 'news_crawler_do_post_update', 15);
}

// 未来の投稿が公開される際のフック
add_action('future_to_publish', 'news_crawler_future_to_publish', 16);

// News Crawler用メタデータを確実に設定するためのフック
add_action('news_crawler_ensure_meta', 'news_crawler_ensure_meta', 10, 1);

// 投稿作成直後のXPoster用メタデータ設定を強化
add_action('wp_insert_post', 'news_crawler_enhance_xposter_meta', 10, 3);

function news_crawler_do_update_post_status($post_id, $status) {
    if (!$post_id || !$status) {
        return;
    }
    
    // 投稿が存在するかチェック
    $post = get_post($post_id);
    if (!$post) {
        return;
    }
    
    // 現在のステータスと異なる場合のみ更新
    if ($post->post_status !== $status) {
        $update_data = array(
            'ID' => $post_id,
            'post_status' => $status
        );
        
        // 投稿ステータスを更新
        $result = wp_update_post($update_data);
        
        if ($result) {
            error_log('NewsCrawler: 投稿ステータスを ' . $status . ' に更新しました (ID: ' . $post_id . ')');
        } else {
            error_log('NewsCrawler: 投稿ステータスの更新に失敗しました (ID: ' . $post_id . ')');
        }
    }
}

/**
 * News Crawler用の投稿保存処理
 * 投稿作成後のメタデータ設定を管理
 */
function news_crawler_save_post($post_id, $post) {
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }
    
    // News Crawlerで作成された投稿かチェック
    $is_news_crawler_post = get_post_meta($post_id, '_news_crawler_created', true);
    if ($is_news_crawler_post) {
        // News Crawler用のメタデータを設定
        update_post_meta($post_id, '_news_crawler_ready', true);
        
        error_log('NewsCrawler: 投稿用メタデータを設定しました (ID: ' . $post_id . ')');
    }
}

/**
 * News Crawler用の投稿更新処理
 * 投稿公開時の処理を管理
 */
function news_crawler_do_post_update($post_id, $post = null, $updated = null, $post_before = null) {
    if ((empty($_POST) && !news_crawler_auto_post_allowed($post_id)) || 
        (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || 
        wp_is_post_revision($post_id) || 
        isset($_POST['_inline_edit']) || 
        (defined('DOING_AJAX') && DOING_AJAX && !news_crawler_auto_post_allowed($post_id)) || 
        !news_crawler_in_post_type($post_id)) {
        return $post_id;
    }
    
    $post = (null === $post) ? get_post($post_id) : $post;
    if ('publish' !== $post->post_status) {
        return $post_id;
    }
    
    // News Crawlerで作成された投稿の場合、メタデータを更新
    $is_news_crawler_post = get_post_meta($post_id, '_news_crawler_created', true);
    if ($is_news_crawler_post) {
        // News Crawler用のメタデータを更新
        update_post_meta($post_id, '_news_crawler_published', true);
        update_post_meta($post_id, '_news_crawler_publish_date', current_time('mysql'));
        
        error_log('NewsCrawler: 公開時にメタデータを更新しました (ID: ' . $post_id . ')');
    }
    
    return $post_id;
}

/**
 * 未来の投稿が公開される際の処理
 */
function news_crawler_future_to_publish($post) {
    $post_id = $post->ID;
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id) || !news_crawler_in_post_type($post_id)) {
        return;
    }
    
    // News Crawlerで作成された投稿の場合、メタデータを更新
    $is_news_crawler_post = get_post_meta($post_id, '_news_crawler_created', true);
    if ($is_news_crawler_post) {
        update_post_meta($post_id, '_news_crawler_published', true);
        update_post_meta($post_id, '_news_crawler_publish_date', current_time('mysql'));
        
        error_log('NewsCrawler: 未来投稿公開時にメタデータを更新しました (ID: ' . $post_id . ')');
    }
}

/**
 * 自動投稿が許可されているかチェック
 */
function news_crawler_auto_post_allowed($post_id) {
    $state = get_option('news_crawler_auto_post_allowed', '1');
    $return = ('0' !== $state) ? true : false;
    
    return apply_filters('news_crawler_auto_post_allowed', $return, $post_id);
}

/**
 * 投稿タイプが許可されているかチェック
 */
function news_crawler_in_post_type($post_id) {
    $post_types = news_crawler_allowed_post_types();
    $type = get_post_type($post_id);
    
    if (in_array($type, $post_types, true)) {
        return true;
    }
    
    return false;
}

/**
 * 許可されている投稿タイプの配列を取得
 */
function news_crawler_allowed_post_types($post_type = false) {
    // News Crawler独自の設定を使用
    $post_type_settings = get_option('news_crawler_post_types', array(
        'post' => array(
            'post-published-update' => '1',
            'post-edited-update' => '1'
        )
    ));
    
    $post_types = array_keys($post_type_settings);
    
    if ($post_type) {
        return in_array($post_type, $post_types, true) ? true : false;
    }
    
    $allowed_types = array();
    if (is_array($post_type_settings) && !empty($post_type_settings)) {
        foreach ($post_type_settings as $type => $settings) {
            if ('1' === (string) $settings['post-edited-update'] || '1' === (string) $settings['post-published-update']) {
                $allowed_types[] = $type;
            }
        }
    }
    
    return apply_filters('news_crawler_allowed_post_types', $allowed_types, $post_type_settings);
}

/**
 * 投稿作成直後のXPoster用メタデータ設定を強化
 */
function news_crawler_enhance_xposter_meta($post_id, $post, $update) {
    if ($update || wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }
    
    // News Crawlerで作成された投稿かチェック
    $is_news_crawler_post = get_post_meta($post_id, '_news_crawler_created', true);
    if ($is_news_crawler_post) {
        // XPoster用のメタデータを確実に設定
        update_post_meta($post_id, '_news_crawler_post_this', 'yes');
        update_post_meta($post_id, '_news_crawler_twitter', 'yes');
        update_post_meta($post_id, '_news_crawler_template_x', 'yes');
        update_post_meta($post_id, '_news_crawler_template_mastodon', 'yes');
        update_post_meta($post_id, '_news_crawler_template_bluesky', 'yes');
        update_post_meta($post_id, '_news_crawler_ready', true);
        
        error_log('NewsCrawler: 投稿作成直後にXPoster用メタデータを強化設定しました (ID: ' . $post_id . ')');
    }
}

/**
 * News Crawler用メタデータを確実に設定
 */
function news_crawler_ensure_meta($post_id) {
    if (!$post_id) {
        return;
    }
    
    // 投稿が存在するかチェック
    $post = get_post($post_id);
    if (!$post_id) {
        return;
    }
    
    // News Crawlerで作成された投稿かチェック
    $is_news_crawler_post = get_post_meta($post_id, '_news_crawler_created', true);
    if ($is_news_crawler_post) {
        // News Crawler用のメタデータを再設定
        update_post_meta($post_id, '_news_crawler_ready', true);
        update_post_meta($post_id, '_news_crawler_last_meta_update', current_time('mysql'));
        
        error_log('NewsCrawler: メタデータを確実に設定しました (ID: ' . $post_id . ')');
    }
}

// YouTube API クラス
class YouTubeCrawler {
    private $api_key;
    private $option_name = 'youtube_crawler_settings';
    
    public function __construct() {
        $this->api_key = get_option('youtube_api_key', '');
        // メニュー登録は新しいジャンル設定システムで管理されるため無効化
        // add_action('admin_menu', array($this, 'add_admin_menu'));
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
    }
    
    public function main_section_callback() {
        echo '<p>YouTubeチャンネルからキーワードにマッチした動画を取得し、動画の埋め込みと要約を含む投稿を作成します。</p>';
        echo '<p><strong>注意:</strong> YouTube Data API v3のAPIキーが必要です。<a href="https://developers.google.com/youtube/v3/getting-started" target="_blank">こちら</a>から取得できます。</p>';
    }
    
    public function api_key_callback() {
        $api_key = get_option('youtube_api_key', '');
        echo '<input type="text" id="youtube_api_key" name="youtube_api_key" value="' . esc_attr($api_key) . '" size="50" />';
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
            if (is_array($input['keywords'])) {
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
            if (is_array($input['channels'])) {
                $channels = array_map('trim', $input['channels']);
                $channels = array_filter($channels);
                $sanitized['channels'] = $channels;
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
                        dataType: 'json',
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
                                dataType: 'json',
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
                                error: function(xhr, status, error) {
                                    console.log('AJAX Error Details:', {
                                        status: xhr.status,
                                        statusText: xhr.statusText,
                                        responseText: xhr.responseText,
                                        responseJSON: xhr.responseJSON,
                                        error: error
                                    });
                                    
                                    var errorMessage = 'エラーが発生しました。';
                                    if (xhr.responseJSON && xhr.responseJSON.data) {
                                        errorMessage = xhr.responseJSON.data;
                                    } else if (xhr.status >= 400) {
                                        errorMessage = 'HTTPエラー: ' + xhr.status + ' ' + xhr.statusText;
                                    } else if (xhr.responseText) {
                                        // レスポンステキストを確認
                                        try {
                                            var response = JSON.parse(xhr.responseText);
                                            if (response.data) {
                                                errorMessage = response.data;
                                            } else {
                                                errorMessage = 'レスポンス解析エラー: ' + xhr.responseText.substring(0, 100);
                                            }
                                        } catch (e) {
                                            errorMessage = 'レスポンス形式エラー: ' + xhr.responseText.substring(0, 100);
                                        }
                                    } else if (error) {
                                        errorMessage = 'エラー: ' + error;
                                    }
                                    
                                    resultDiv.html(testResult + '<br><div class="notice notice-error"><p><strong>動画投稿作成エラー:</strong><br>' + errorMessage + '</p></div>');
                                },
                                complete: function() {
                                    button.prop('disabled', false).text('動画投稿を作成');
                                }
                            });
                        },
                        error: function(xhr, status, error) {
                            console.log('AJAX Error Details (Test):', {
                                status: xhr.status,
                                statusText: xhr.statusText,
                                responseText: xhr.responseText,
                                responseJSON: xhr.responseJSON,
                                error: error
                            });
                            
                            var errorMessage = 'エラーが発生しました。';
                            if (xhr.responseJSON && xhr.responseJSON.data) {
                                errorMessage = xhr.responseJSON.data;
                            } else if (xhr.status >= 400) {
                                errorMessage = 'HTTPエラー: ' + xhr.status + ' ' + xhr.statusText;
                            } else if (xhr.responseText) {
                                // レスポンステキストを確認
                                try {
                                    var response = JSON.parse(xhr.responseText);
                                    if (response.data) {
                                        errorMessage = response.data;
                                    } else {
                                        errorMessage = 'レスポンス解析エラー: ' + xhr.responseText.substring(0, 100);
                                    }
                                } catch (e) {
                                    errorMessage = 'レスポンス形式エラー: ' + xhr.responseText.substring(0, 100);
                                }
                            } else if (error) {
                                errorMessage = 'エラー: ' + error;
                            }
                            
                            resultDiv.html('<div class="notice notice-error"><p><strong>YouTubeチャンネル解析エラー:</strong><br>' + errorMessage + '</p></div>');
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
    
    public function crawl_youtube() {
        $options = get_option($this->option_name, array());
        $channels = isset($options['channels']) && !empty($options['channels']) ? $options['channels'] : array();
        $keywords = isset($options['keywords']) && !empty($options['keywords']) ? $options['keywords'] : array('AI', 'テクノロジー', 'ビジネス', 'ニュース');
        $max_videos = isset($options['max_videos']) && !empty($options['max_videos']) ? $options['max_videos'] : 5;
        $categories = isset($options['post_categories']) && !empty($options['post_categories']) ? $options['post_categories'] : array('blog');
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
        $post_id = null;
        if (!empty($valid_videos)) {
            $post_id = $this->create_video_summary_post($valid_videos, $categories, $status);
            if ($post_id && !is_wp_error($post_id)) {
                $posts_created = 1;
                $debug_info[] = "\n投稿作成成功: 投稿ID " . $post_id;
            } else {
                $error_message = is_wp_error($post_id) ? $post_id->get_error_message() : '不明なエラー';
                $debug_info[] = "\n投稿作成失敗: " . $error_message;
            }
        } else {
            $debug_info[] = "\n有効な動画がないため投稿を作成しませんでした";
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
        
        // デバッグ情報を追加
        $debug_info = array();
        $debug_info[] = 'YouTube動画キーワードマッチング詳細:';
        $debug_info[] = '  動画タイトル: ' . $video['title'];
        $debug_info[] = '  検索対象テキスト（最初の200文字）: ' . mb_substr($text_to_search, 0, 200) . '...';
        $debug_info[] = '  設定されたキーワード: ' . implode(', ', $keywords);
        
        $match_found = false;
        $matched_keywords = array();
        
        foreach ($keywords as $keyword) {
            $keyword_trimmed = trim($keyword);
            if (empty($keyword_trimmed)) {
                continue; // 空のキーワードはスキップ
            }
            
            $keyword_lower = strtolower($keyword_trimmed);
            
            // 完全一致チェック
            if (stripos($text_to_search, $keyword_lower) !== false) {
                $match_found = true;
                $matched_keywords[] = $keyword_trimmed;
                $debug_info[] = '  ✓ キーワード "' . $keyword_trimmed . '" でマッチ';
            } else {
                $debug_info[] = '  ✗ キーワード "' . $keyword_trimmed . '" でマッチなし';
            }
        }
        
        if ($match_found) {
            $debug_info[] = '  結果: マッチ成功 (' . implode(', ', $matched_keywords) . ')';
        } else {
            $debug_info[] = '  結果: マッチ失敗';
        }
        
        // デバッグ情報をグローバル変数に保存
        global $youtube_crawler_keyword_debug;
        if (!isset($youtube_crawler_keyword_debug)) {
            $youtube_crawler_keyword_debug = array();
        }
        $youtube_crawler_keyword_debug[] = implode("\n", $debug_info);
        
        return $match_found;
    }
    
    private function create_video_summary_post($videos, $categories, $status) {
        $cat_ids = array();
        foreach ($categories as $category) {
            $cat_ids[] = $this->get_or_create_category($category);
        }
        
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
            
            // 動画の埋め込み（ブロックエディタ対応）
            $youtube_url = 'https://www.youtube.com/watch?v=' . esc_attr($video['video_id']);
            
            if ($embed_type === 'responsive' || $embed_type === 'classic') {
                // WordPress標準のYouTube埋め込みブロック
                $post_content .= '<!-- wp:embed {"url":"' . esc_url($youtube_url) . '","type":"video","providerNameSlug":"youtube","responsive":true,"className":"wp-embed-aspect-16-9 wp-has-aspect-ratio"} -->';
                $post_content .= '<figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio">';
                $post_content .= '<div class="wp-block-embed__wrapper">';
                $post_content .= $youtube_url;
                $post_content .= '</div></figure>';
                $post_content .= '<!-- /wp:embed -->';
            } else {
                // ミニマル埋め込み（リンクのみ）
                $post_content .= '<!-- wp:paragraph -->';
                $post_content .= '<p><a href="' . esc_url($youtube_url) . '" target="_blank" rel="noopener noreferrer">📺 YouTubeで視聴する</a></p>';
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
        
        // News Crawler用の処理のため、最初に下書きとして投稿を作成
        $post_data = array(
            'post_title'    => $post_title,
            'post_content'  => $post_content,
            'post_status'   => 'draft', // 最初は下書きとして作成
            'post_author'   => get_current_user_id() ?: 1,
            'post_type'     => 'post',
            'post_category' => $cat_ids
        );
        
        $post_id = wp_insert_post($post_data, true);
        
        if (is_wp_error($post_id)) {
            return $post_id;
        }
        
        // メタデータの保存
        update_post_meta($post_id, '_youtube_summary', true);
        update_post_meta($post_id, '_youtube_videos_count', count($videos));
        update_post_meta($post_id, '_youtube_crawled_date', current_time('mysql'));
        
        // XPoster連携用のメタデータを追加
        update_post_meta($post_id, '_news_crawler_created', true);
        update_post_meta($post_id, '_news_crawler_creation_method', 'youtube');
        update_post_meta($post_id, '_news_crawler_intended_status', $status);
        update_post_meta($post_id, '_news_crawler_creation_timestamp', current_time('timestamp'));
        update_post_meta($post_id, '_news_crawler_ready', false);
        
        // News Crawler用のメタデータを設定
        update_post_meta($post_id, '_news_crawler_post_this', 'yes');
        update_post_meta($post_id, '_news_crawler_twitter', 'yes'); // カスタムツイート用
        update_post_meta($post_id, '_news_crawler_template_x', 'yes'); // X用テンプレート
        update_post_meta($post_id, '_news_crawler_template_mastodon', 'yes'); // Mastodon用テンプレート
        update_post_meta($post_id, '_news_crawler_template_bluesky', 'yes'); // Bluesky用テンプレート
        
        // ジャンルIDを保存（自動投稿用）
        $current_genre_setting = get_transient('news_crawler_current_genre_setting');
        if ($current_genre_setting && isset($current_genre_setting['id'])) {
            update_post_meta($post_id, '_youtube_crawler_genre_id', $current_genre_setting['id']);
        }
        
        foreach ($videos as $index => $video) {
            update_post_meta($post_id, '_youtube_video_' . $index . '_title', $video['title']);
            update_post_meta($post_id, '_youtube_video_' . $index . '_id', $video['video_id']);
            update_post_meta($post_id, '_youtube_video_' . $index . '_channel', $video['channel_title']);
        }
        
        // アイキャッチ生成（ジャンル設定から呼び出された場合）
        error_log('NewsCrawler: About to call maybe_generate_featured_image for YouTube post ' . $post_id);
        $featured_result = $this->maybe_generate_featured_image($post_id, $post_title, $keywords);
        error_log('NewsCrawler: YouTube maybe_generate_featured_image returned: ' . ($featured_result ? 'Success (ID: ' . $featured_result . ')' : 'Failed or skipped'));
        
        // AI要約生成（メタデータ設定後に呼び出し）
        error_log('NewsCrawler: About to call AI summarizer for YouTube post ' . $post_id);
        if (class_exists('NewsCrawlerOpenAISummarizer')) {
            error_log('NewsCrawler: NewsCrawlerOpenAISummarizer class found, creating instance');
            $summarizer = new NewsCrawlerOpenAISummarizer();
            error_log('NewsCrawler: Calling generate_summary for post ' . $post_id);
            $summarizer->generate_summary($post_id);
            error_log('NewsCrawler: generate_summary completed for post ' . $post_id);
        } else {
            error_log('NewsCrawler: NewsCrawlerOpenAISummarizer class NOT found');
        }
        
        // X（Twitter）自動シェア（投稿成功後）
        $this->maybe_share_to_twitter($post_id, $post_title);
        
        // News Crawler用の処理のため、投稿ステータス変更を遅延実行
        if ($status !== 'draft') {
            $this->schedule_post_status_update($post_id, $status);
        }
        
        return $post_id;
    }
    
    private function is_duplicate_video($video) {
        global $wpdb;
        $video_id = $video['video_id'];
        $title = $video['title'];
        
        // 基本設定から重複チェック設定を取得
        $basic_settings = get_option('news_crawler_basic_settings', array());
        $strictness = isset($basic_settings['duplicate_check_strictness']) ? $basic_settings['duplicate_check_strictness'] : 'medium';
        $period = isset($basic_settings['duplicate_check_period']) ? intval($basic_settings['duplicate_check_period']) : 30;
        
        // 厳しさに応じて類似度の閾値を設定
        $title_similarity_threshold = 0.85; // デフォルト
        
        switch ($strictness) {
            case 'low':
                $title_similarity_threshold = 0.75;
                break;
            case 'high':
                $title_similarity_threshold = 0.95;
                break;
            default: // medium
                $title_similarity_threshold = 0.85;
                break;
        }
        
        // 1. 動画IDの完全一致チェック（設定された期間）
        $existing_video = $wpdb->get_var($wpdb->prepare(
            "SELECT pm.post_id FROM {$wpdb->postmeta} pm 
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
             WHERE pm.meta_key LIKE %s AND pm.meta_value = %s 
             AND p.post_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
             AND p.post_status IN ('publish', 'draft', 'pending', 'private')",
            '_youtube_video_%_id',
            $video_id,
            $period
        ));
        
        if ($existing_video) {
            error_log('NewsCrawler: 動画ID重複で重複を検出: ' . $video_id);
            return true;
        }
        
        // 2. タイトルの完全一致チェック（設定された期間）
        $exact_title_match = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} 
             WHERE post_title = %s 
             AND post_type = 'post' 
             AND post_status IN ('publish', 'draft', 'pending', 'private') 
             AND post_date >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $title,
            $period
        ));
        
        if ($exact_title_match) {
            error_log('NewsCrawler: 動画タイトル完全一致で重複を検出: ' . $title);
            return true;
        }
        
        // 3. 高類似度タイトルチェック（設定された期間、設定された類似度以上）
        $similar_titles = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_title FROM {$wpdb->posts} 
             WHERE post_type = 'post' 
             AND post_status IN ('publish', 'draft', 'pending', 'private') 
             AND post_date >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $period
        ));
        
        foreach ($similar_titles as $existing_post) {
            $similarity = $this->calculate_title_similarity($title, $existing_post->post_title);
            if ($similarity >= $title_similarity_threshold) {
                error_log('NewsCrawler: 動画タイトル高類似度で重複を検出: ' . $title . ' vs ' . $existing_post->post_title . ' (類似度: ' . $similarity . ', 閾値: ' . $title_similarity_threshold . ')');
                return true;
            }
        }
        
        // 4. チャンネル名とタイトルの組み合わせチェック（設定された期間）
        if (!empty($video['channel_title'])) {
            $channel_title_match = $wpdb->get_var($wpdb->prepare(
                "SELECT pm.post_id FROM {$wpdb->postmeta} pm 
                 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
                 WHERE pm.meta_key LIKE %s 
                 AND pm.meta_value = %s 
                 AND p.post_title = %s
                 AND p.post_date >= DATE_SUB(NOW(), INTERVAL %d DAY)",
                '_youtube_video_%_channel',
                $video['channel_title'],
                $title,
                $period
            ));
            
            if ($channel_title_match) {
                error_log('NewsCrawler: チャンネル名とタイトルの組み合わせで重複を検出: ' . $video['channel_title'] . ' - ' . $title);
                return true;
            }
        }
        
        return false;
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
        
        // cURL設定を調整（ローカル環境用）
        $response = wp_remote_get($url, array(
            'timeout' => 60, // タイムアウトを60秒に延長
            'sslverify' => false, // SSL証明書検証を無効化
            'httpversion' => '1.1',
            'blocking' => true,
            'user-agent' => 'News Crawler Plugin/1.0'
        ));
        
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
        
        // cURL設定を調整（ローカル環境用）
        $response = wp_remote_get($url, array(
            'timeout' => 60, // タイムアウトを60秒に延長
            'sslverify' => false, // SSL証明書検証を無効化
            'httpversion' => '1.1',
            'blocking' => true,
            'user-agent' => 'News Crawler Plugin/1.0'
        ));
        
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
            error_log('YouTubeCrawler: Featured image generation skipped - not enabled');
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
        
        $result = $generator->generate_and_set_featured_image($post_id, $title, $keywords, $method);
        error_log('YouTubeCrawler: Featured image generation result: ' . ($result ? 'Success (ID: ' . $result . ')' : 'Failed'));
        
        return $result;
    }
}

// プラグインの基本クラス
class NewsCrawler {
    
    private $option_name = 'news_crawler_settings';
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        // メニュー登録は新しいジャンル設定システムで管理されるため無効化
        // add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('wp_ajax_news_crawler_manual_run', array($this, 'manual_run'));
        add_action('wp_ajax_news_crawler_test_fetch', array($this, 'test_fetch'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // 自動投稿機能は廃止、手動実行のみ
    }
    
    public function init() {
        // 初期化処理
        load_plugin_textdomain('news-crawler', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function add_admin_menu() {
        // 新しいジャンル設定システムに統合されたため、このメニューは無効化
        // メニューは NewsCrawlerGenreSettings クラスで管理されます
    }
    
    public function admin_init() {
        register_setting($this->option_name, $this->option_name, array($this, 'sanitize_settings'));
        
        add_settings_section(
            'news_crawler_main',
            '基本設定',
            array($this, 'main_section_callback'),
            'news-crawler'
        );
        
        add_settings_field(
            'max_articles',
            '最大記事数',
            array($this, 'max_articles_callback'),
            'news-crawler',
            'news_crawler_main',
            array('label_for' => 'max_articles')
        );
        
        add_settings_field(
            'keywords',
            'キーワード設定',
            array($this, 'keywords_callback'),
            'news-crawler',
            'news_crawler_main',
            array('label_for' => 'keywords')
        );
        
        add_settings_field(
            'news_sources',
            'ニュースソース（URL）',
            array($this, 'news_sources_callback'),
            'news-crawler',
            'news_crawler_main',
            array('label_for' => 'news_sources')
        );
        
        add_settings_field(
            'post_category',
            '投稿カテゴリー',
            array($this, 'post_category_callback'),
            'news-crawler',
            'news_crawler_main',
            array('label_for' => 'post_category')
        );
        
        add_settings_field(
            'post_status',
            '投稿ステータス',
            array($this, 'post_status_callback'),
            'news-crawler',
            'news_crawler_main',
            array('label_for' => 'post_status')
        );
    }
    
    public function main_section_callback() {
        echo '<p>ニュースソースからキーワードにマッチした記事を取得し、1つの投稿にまとめて作成します。</p>';
    }
    
    public function max_articles_callback() {
        $options = get_option($this->option_name, array());
        $max_articles = isset($options['max_articles']) && !empty($options['max_articles']) ? $options['max_articles'] : 10;
        echo '<input type="number" id="max_articles" name="' . $this->option_name . '[max_articles]" value="' . esc_attr($max_articles) . '" min="1" max="50" />';
        echo '<p class="description">キーワードにマッチした記事の最大取得数（1-50件）</p>';
    }
    
    public function keywords_callback() {
        $options = get_option($this->option_name, array());
        $keywords = isset($options['keywords']) && !empty($options['keywords']) ? $options['keywords'] : array('AI', 'テクノロジー', 'ビジネス', 'ニュース');
        $keywords_text = implode("\n", $keywords);
        echo '<textarea id="keywords" name="' . $this->option_name . '[keywords]" rows="5" cols="50" placeholder="1行に1キーワードを入力してください">' . esc_textarea($keywords_text) . '</textarea>';
        echo '<p class="description">1行に1キーワードを入力してください。キーワードにマッチした記事のみを取得します。例：AI, テクノロジー, ビジネス</p>';
    }
    
    public function news_sources_callback() {
        $options = get_option($this->option_name, array());
        $sources = isset($options['news_sources']) && !empty($options['news_sources']) ? $options['news_sources'] : array();
        $sources_text = implode("\n", $sources);
        echo '<textarea id="news_sources" name="' . $this->option_name . '[news_sources]" rows="10" cols="50" placeholder="https://example.com/news&#10;https://example2.com/rss">' . esc_textarea($sources_text) . '</textarea>';
        echo '<p class="description">1行に1URLを入力してください。RSSフィードまたはニュースサイトのURLを指定できます。</p>';
    }
    
    public function post_category_callback() {
        $options = get_option($this->option_name, array());
        $category = isset($options['post_category']) && !empty($options['post_category']) ? $options['post_category'] : 'blog';
        echo '<input type="text" id="post_category" name="' . $this->option_name . '[post_category]" value="' . esc_attr($category) . '" />';
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
        echo '<select id="post_status" name="' . $this->option_name . '[post_status]">';
        foreach ($statuses as $value => $label) {
            echo '<option value="' . $value . '" ' . selected($value, $status, false) . '>' . $label . '</option>';
        }
        echo '</select>';
    }
    
    public function sanitize_settings($input) {
        $sanitized = array();
        
        $existing_options = get_option($this->option_name, array());
        
        if (isset($input['max_articles'])) {
            if (is_numeric($input['max_articles']) || (is_string($input['max_articles']) && !empty(trim($input['max_articles'])))) {
                $max_articles = intval($input['max_articles']);
                $sanitized['max_articles'] = max(1, min(50, $max_articles));
            } else {
                $sanitized['max_articles'] = isset($existing_options['max_articles']) ? $existing_options['max_articles'] : 10;
            }
        } else {
            $sanitized['max_articles'] = isset($existing_options['max_articles']) ? $existing_options['max_articles'] : 10;
        }
        
        if (isset($input['keywords'])) {
            if (is_array($input['keywords'])) {
                // 配列の場合（ジャンル設定から渡される場合）
                $keywords = array_map('trim', $input['keywords']);
                $keywords = array_filter($keywords);
                $sanitized['keywords'] = $keywords;
            } elseif (is_string($input['keywords']) && !empty(trim($input['keywords']))) {
                // 文字列の場合（管理画面から直接入力される場合）
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
        
        if (isset($input['news_sources'])) {
            if (is_array($input['news_sources'])) {
                // 配列の場合（ジャンル設定から渡される場合）
                $sources = array_map('trim', $input['news_sources']);
                $sources = array_filter($sources);
                $sources = array_map('esc_url_raw', $sources);
                $sanitized['news_sources'] = $sources;
            } elseif (is_string($input['news_sources']) && !empty(trim($input['news_sources']))) {
                // 文字列の場合（管理画面から直接入力される場合）
                $sources = explode("\n", $input['news_sources']);
                $sources = array_map('trim', $sources);
                $sources = array_filter($sources);
                $sources = array_map('esc_url_raw', $sources);
                $sanitized['news_sources'] = $sources;
            } else {
                $sanitized['news_sources'] = isset($existing_options['news_sources']) ? $existing_options['news_sources'] : array();
            }
        } else {
            $sanitized['news_sources'] = isset($existing_options['news_sources']) ? $existing_options['news_sources'] : array();
        }
        
        if (isset($input['post_category'])) {
            if (is_string($input['post_category']) && !empty(trim($input['post_category']))) {
                $sanitized['post_category'] = sanitize_text_field($input['post_category']);
            } else {
                $sanitized['post_category'] = isset($existing_options['post_category']) ? $existing_options['post_category'] : 'blog';
            }
        } else {
            $sanitized['post_category'] = isset($existing_options['post_category']) ? $existing_options['post_category'] : 'blog';
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
        
        return $sanitized;
    }
    
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>News Crawler</h1>
            
            <?php if (isset($_GET['settings-updated'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p>設定を保存しました。</p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields($this->option_name);
                do_settings_sections('news-crawler');
                submit_button();
                ?>
            </form>
            
            <hr>
            
            <h2>投稿を作成</h2>
            <p>設定したニュースソースからキーワードにマッチした記事を取得して、1つの投稿にまとめて作成します。</p>
            <button type="button" id="manual-run" class="button button-primary">投稿を作成</button>
            
            <div id="manual-run-result" style="margin-top: 10px; white-space: pre-wrap; background: #f7f7f7; padding: 15px; border: 1px solid #ccc; border-radius: 4px; max-height: 400px; overflow-y: auto;"></div>
            
            <hr>
            
            <h2>統計情報</h2>
            <?php $stats = $this->get_crawler_statistics(); ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>項目</th>
                        <th>数値</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>総投稿数</td>
                        <td><?php echo $stats['total_posts']; ?>件</td>
                    </tr>
                    <tr>
                        <td>今月の投稿数</td>
                        <td><?php echo $stats['posts_this_month']; ?>件</td>
                    </tr>
                    <tr>
                        <td>重複スキップ数</td>
                        <td><?php echo $stats['duplicates_skipped']; ?>件</td>
                    </tr>
                    <tr>
                        <td>低品質スキップ数</td>
                        <td><?php echo $stats['low_quality_skipped']; ?>件</td>
                    </tr>
                    <tr>
                        <td>最後の実行日時</td>
                        <td><?php echo $stats['last_run']; ?></td>
                    </tr>
                </tbody>
            </table>
            
            <script>
            jQuery(document).ready(function($) {
                $('#manual-run').click(function() {
                    var button = $(this);
                    var resultDiv = $('#manual-run-result');
                    button.prop('disabled', true).text('実行中...');
                    resultDiv.html('ニュースソースの解析と投稿作成を開始します...');
                    
                    // まずニュースソースの解析を実行
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'news_crawler_test_fetch',
                            nonce: '<?php echo wp_create_nonce('news_crawler_nonce'); ?>'
                        },
                        success: function(testResponse) {
                            var testResult = '';
                            if (testResponse.success) {
                                testResult = '<div class="notice notice-info"><p><strong>ニュースソース解析結果:</strong><br>' + testResponse.data + '</p></div>';
                            } else {
                                testResult = '<div class="notice notice-error"><p><strong>ニュースソース解析エラー:</strong><br>' + testResponse.data + '</p></div>';
                            }
                            
                            // 次に投稿作成を実行
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                dataType: 'json',
                                data: {
                                    action: 'news_crawler_manual_run',
                                    nonce: '<?php echo wp_create_nonce('news_crawler_nonce'); ?>'
                                },
                                success: function(postResponse) {
                                    var postResult = '';
                                    if (postResponse.success) {
                                        postResult = '<div class="notice notice-success"><p><strong>投稿作成結果:</strong><br>' + postResponse.data.replace(/\n/g, '<br>') + '</p></div>';
                                    } else {
                                        postResult = '<div class="notice notice-error"><p><strong>投稿作成エラー:</strong><br>' + postResponse.data.replace(/\n/g, '<br>') + '</p></div>';
                                    }
                                    
                                    // 両方の結果を表示
                                    resultDiv.html(testResult + '<br>' + postResult);
                                },
                                error: function(xhr, status, error) {
                                    console.log('AJAX Error Details (Post):', {
                                        status: xhr.status,
                                        statusText: xhr.statusText,
                                        responseText: xhr.responseText,
                                        responseJSON: xhr.responseJSON,
                                        error: error
                                    });
                                    
                                    var errorMessage = 'エラーが発生しました。';
                                    if (xhr.responseJSON && xhr.responseJSON.data) {
                                        errorMessage = xhr.responseJSON.data;
                                    } else if (xhr.status >= 400) {
                                        errorMessage = 'HTTPエラー: ' + xhr.status + ' ' + xhr.statusText;
                                    } else if (xhr.responseText) {
                                        // レスポンステキストを確認
                                        try {
                                            var response = JSON.parse(xhr.responseText);
                                            if (response.data) {
                                                errorMessage = response.data;
                                            } else {
                                                errorMessage = 'レスポンス解析エラー: ' + xhr.responseText.substring(0, 100);
                                            }
                                        } catch (e) {
                                            errorMessage = 'レスポンス形式エラー: ' + xhr.responseText.substring(0, 100);
                                        }
                                    } else if (error) {
                                        errorMessage = 'エラー: ' + error;
                                    }
                                    
                                    resultDiv.html(testResult + '<br><div class="notice notice-error"><p><strong>投稿作成エラー:</strong><br>' + errorMessage + '</p></div>');
                                },
                                complete: function() {
                                    button.prop('disabled', false).text('投稿を作成');
                                }
                            });
                        },
                        error: function(xhr, status, error) {
                            console.log('AJAX Error Details (Test Fetch):', {
                                status: xhr.status,
                                statusText: xhr.statusText,
                                responseText: xhr.responseText,
                                responseJSON: xhr.responseJSON,
                                error: error
                            });
                            
                            var errorMessage = 'エラーが発生しました。';
                            if (xhr.responseJSON && xhr.responseJSON.data) {
                                errorMessage = xhr.responseJSON.data;
                            } else if (xhr.status >= 400) {
                                errorMessage = 'HTTPエラー: ' + xhr.status + ' ' + xhr.statusText;
                            } else if (xhr.responseText) {
                                // レスポンステキストを確認
                                try {
                                    var response = JSON.parse(xhr.responseText);
                                    if (response.data) {
                                        errorMessage = response.data;
                                    } else {
                                        errorMessage = 'レスポンス解析エラー: ' + xhr.responseText.substring(0, 100);
                                    }
                                } catch (e) {
                                    errorMessage = 'レスポンス形式エラー: ' + xhr.responseText.substring(0, 100);
                                }
                            } else if (error) {
                                errorMessage = 'エラー: ' + error;
                            }
                            
                            resultDiv.html('<div class="notice notice-error"><p><strong>ニュースソース解析エラー:</strong><br>' + errorMessage + '</p></div>');
                            button.prop('disabled', false).text('投稿を作成');
                        }
                    });
                });
            });
            </script>
        </div>
        <?php
    }
    
    public function manual_run() {
        check_ajax_referer('news_crawler_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('権限がありません');
        }
        
        $result = $this->crawl_news();
        wp_send_json_success($result);
    }
    
    public function test_fetch() {
        check_ajax_referer('news_crawler_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('権限がありません');
        }
        
        $options = get_option($this->option_name, array());
        $sources = isset($options['news_sources']) && !empty($options['news_sources']) ? $options['news_sources'] : array();
        
        if (empty($sources)) {
            wp_send_json_success('ニュースソースが設定されていません。');
        }
        
        $test_result = array();
        foreach ($sources as $source) {
            $content = $this->fetch_content($source);
            if ($content) {
                $test_result[] = $source . ': 取得成功 (' . (is_array($content) ? count($content) . '件の記事' : strlen($content) . ' 文字') . ')';
            } else {
                $test_result[] = $source . ': 取得失敗';
            }
        }
        
        wp_send_json_success(implode('<br>', $test_result));
    }
    
    public function activate() {
        $options = get_option($this->option_name);
        if (!$options) {
            $default_options = array(
                'max_articles' => 10,
                'keywords' => array('AI', 'テクノロジー', 'ビジネス', 'ニュース'),
                'news_sources' => array(),
                'post_category' => 'blog',
                'post_status' => 'draft'
            );
            add_option($this->option_name, $default_options);
        }
    }
    
    public function deactivate() {
        // プラグイン無効化時の処理
        // 自動投稿のcronジョブをクリーンアップ
        wp_clear_scheduled_hook('news_crawler_auto_posting_cron');
        
        // 一時的なデータをクリーンアップ
        delete_transient('news_crawler_current_genre_setting');
        
        // 自動投稿関連のオプションをクリーンアップ
        delete_option('news_crawler_auto_posting_logs');
        
        // 各ジャンルの実行時刻オプションをクリーンアップ
        $genre_settings = get_option('news_crawler_genre_settings', array());
        foreach ($genre_settings as $genre_id => $setting) {
            delete_option('news_crawler_last_execution_' . $genre_id);
        }
    }
    
    public function crawl_news() {
        $options = get_option($this->option_name, array());
        $sources = isset($options['news_sources']) && !empty($options['news_sources']) ? $options['news_sources'] : array();
        $keywords = isset($options['keywords']) && !empty($options['keywords']) ? $options['keywords'] : array('AI', 'テクノロジー', 'ビジネス', 'ニュース');
        $max_articles = isset($options['max_articles']) && !empty($options['max_articles']) ? $options['max_articles'] : 10;
        $category = isset($options['post_category']) && !empty($options['post_category']) ? $options['post_category'] : 'blog';
        $status = isset($options['post_status']) && !empty($options['post_status']) ? $options['post_status'] : 'draft';
        
        if (empty($sources)) {
            return 'ニュースソースが設定されていません。';
        }
        
        $matched_articles = array();
        $errors = array();
        $duplicates_skipped = 0;
        $low_quality_skipped = 0;
        $debug_info = array();
        
        foreach ($sources as $source) {
            try {
                $content = $this->fetch_content($source);
                if ($content) {
                    if (is_array($content)) {
                        $debug_info[] = $source . ': RSSフィードから' . count($content) . '件の記事を取得';
                        foreach ($content as $article) {
                            if ($this->is_keyword_match($article, $keywords)) {
                                $matched_articles[] = $article;
                                $debug_info[] = '  - キーワードマッチ: ' . $article['title'];
                            } else {
                                // キーワードマッチしない場合のデバッグ情報
                                global $news_crawler_search_text;
                                $debug_info[] = '  - キーワードマッチなし: ' . $article['title'];
                                $debug_info[] = '    検索対象テキスト: ' . mb_substr($news_crawler_search_text, 0, 100) . '...';
                            }
                        }
                    } else {
                        $articles = $this->parse_content($content, $source);
                        if ($articles && is_array($articles)) {
                            $debug_info[] = $source . ': HTMLページから' . count($articles) . '件の記事を解析';
                            foreach ($articles as $article) {
                                if ($this->is_keyword_match($article, $keywords)) {
                                    $matched_articles[] = $article;
                                    $debug_info[] = '  - キーワードマッチ: ' . $article['title'];
                                }
                            }
                        } elseif ($articles) {
                            // 単一記事の場合
                            $debug_info[] = $source . ': HTMLページから単一記事を解析';
                            if ($this->is_keyword_match($articles, $keywords)) {
                                $matched_articles[] = $articles;
                                $debug_info[] = '  - キーワードマッチ: ' . $articles['title'];
                            } else {
                                // キーワードマッチしない場合のデバッグ情報
                                global $news_crawler_search_text;
                                $debug_info[] = '  - キーワードマッチなし: ' . $articles['title'];
                                $debug_info[] = '    検索対象テキスト: ' . mb_substr($news_crawler_search_text, 0, 100) . '...';
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                $errors[] = $source . ': ' . $e->getMessage();
            }
        }
        
        $debug_info[] = "\nキーワードマッチした記事数: " . count($matched_articles);
        
        $valid_articles = array();
        foreach ($matched_articles as $article) {
            $debug_info[] = "  - 記事: " . $article['title'];
            
            if ($this->is_duplicate_article($article)) {
                $duplicates_skipped++;
                $debug_info[] = "    → 重複のためスキップ";
                continue;
            }
            
            $quality_score = $this->calculate_quality_score($article);
            $debug_info[] = "    → 品質スコア: " . number_format($quality_score, 2);
            
            // 品質スコアの詳細情報を追加
            global $news_crawler_debug_details;
            if (!empty($news_crawler_debug_details)) {
                foreach ($news_crawler_debug_details as $detail) {
                    $debug_info[] = "      " . $detail;
                }
            }
            
            if ($quality_score < 0.3) {
                $low_quality_skipped++;
                $debug_info[] = "    → 品質スコアが低いためスキップ";
                continue;
            }
            
            $debug_info[] = "    → 有効記事として追加";
            $valid_articles[] = $article;
        }
        
        $valid_articles = array_slice($valid_articles, 0, $max_articles);
        
        $posts_created = 0;
        if (!empty($valid_articles)) {
            $post_id = $this->create_summary_post($valid_articles, $category, $status);
            if ($post_id && !is_wp_error($post_id)) {
                $posts_created = 1;
            }
        }
        
        $result = $posts_created . '件の投稿を作成しました（' . count($valid_articles) . '件の記事を含む）。';
        $result .= "\n投稿ID: " . ($post_id ?? 'なし');
        if ($duplicates_skipped > 0) $result .= "\n重複スキップ: " . $duplicates_skipped . '件';
        if ($low_quality_skipped > 0) $result .= "\n低品質スキップ: " . $low_quality_skipped . '件';
        if (!empty($errors)) $result .= "\nエラー: " . implode(', ', $errors);
        
        $result .= "\n\n=== デバッグ情報 ===\n" . implode("\n", $debug_info);
        
        $this->update_crawler_statistics($posts_created, $duplicates_skipped, $low_quality_skipped);
        
        return $result;
    }
    
    public function crawl_news_with_options($options) {
        $sources = isset($options['news_sources']) && !empty($options['news_sources']) ? $options['news_sources'] : array();
        $keywords = isset($options['keywords']) && !empty($options['keywords']) ? $options['keywords'] : array('AI', 'テクノロジー', 'ビジネス', 'ニュース');
        $max_articles = isset($options['max_articles']) && !empty($options['max_articles']) ? $options['max_articles'] : 10;
        $categories = isset($options['post_categories']) && !empty($options['post_categories']) ? $options['post_categories'] : array('blog');
        $status = isset($options['post_status']) && !empty($options['post_status']) ? $options['post_status'] : 'draft';
        
        if (empty($sources)) {
            return 'ニュースソースが設定されていません。';
        }
        
        $matched_articles = array();
        $errors = array();
        $duplicates_skipped = 0;
        $low_quality_skipped = 0;
        $debug_info = array();
        
        foreach ($sources as $source) {
            try {
                $content = $this->fetch_content($source);
                if ($content) {
                    if (is_array($content)) {
                        $debug_info[] = $source . ': RSSフィードから' . count($content) . '件の記事を取得';
                        foreach ($content as $article) {
                            if ($this->is_keyword_match($article, $keywords)) {
                                $matched_articles[] = $article;
                                $debug_info[] = '  - キーワードマッチ: ' . $article['title'];
                            } else {
                                // キーワードマッチしない場合のデバッグ情報
                                global $news_crawler_search_text;
                                $debug_info[] = '  - キーワードマッチなし: ' . $article['title'];
                                $debug_info[] = '    検索対象テキスト: ' . mb_substr($news_crawler_search_text, 0, 100) . '...';
                            }
                        }
                    } else {
                        $articles = $this->parse_content($content, $source);
                        if ($articles && is_array($articles)) {
                            $debug_info[] = $source . ': HTMLページから' . count($articles) . '件の記事を解析';
                            foreach ($articles as $article) {
                                if ($this->is_keyword_match($article, $keywords)) {
                                    $matched_articles[] = $article;
                                    $debug_info[] = '  - キーワードマッチ: ' . $article['title'];
                                }
                            }
                        } elseif ($articles) {
                            // 単一記事の場合
                            $debug_info[] = $source . ': HTMLページから単一記事を解析';
                            if ($this->is_keyword_match($articles, $keywords)) {
                                $matched_articles[] = $articles;
                                $debug_info[] = '  - キーワードマッチ: ' . $articles['title'];
                            } else {
                                // キーワードマッチしない場合のデバッグ情報
                                global $news_crawler_search_text;
                                $debug_info[] = '  - キーワードマッチなし: ' . $articles['title'];
                                $debug_info[] = '    検索対象テキスト: ' . mb_substr($news_crawler_search_text, 0, 100) . '...';
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                $errors[] = $source . ': ' . $e->getMessage();
            }
        }
        
        $debug_info[] = "\nキーワードマッチした記事数: " . count($matched_articles);
        
        $valid_articles = array();
        foreach ($matched_articles as $article) {
            $debug_info[] = "  - 記事: " . $article['title'];
            
            if ($this->is_duplicate_article($article)) {
                $duplicates_skipped++;
                $debug_info[] = "    → 重複のためスキップ";
                continue;
            }
            
            $quality_score = $this->calculate_quality_score($article);
            $debug_info[] = "    → 品質スコア: " . number_format($quality_score, 2);
            
            // 品質スコアの詳細情報を追加
            global $news_crawler_debug_details;
            if (!empty($news_crawler_debug_details)) {
                foreach ($news_crawler_debug_details as $detail) {
                    $debug_info[] = "      " . $detail;
                }
            }
            
            if ($quality_score < 0.3) {
                $low_quality_skipped++;
                $debug_info[] = "    → 品質スコアが低いためスキップ";
                continue;
            }
            
            $debug_info[] = "    → 有効記事として追加";
            $valid_articles[] = $article;
        }
        
        $valid_articles = array_slice($valid_articles, 0, $max_articles);
        
        $posts_created = 0;
        $post_id = null;
        if (!empty($valid_articles)) {
            $post_id = $this->create_summary_post_with_categories($valid_articles, $categories, $status);
            if ($post_id && !is_wp_error($post_id)) {
                $posts_created = 1;
                $debug_info[] = "\n投稿作成成功: 投稿ID " . $post_id;
            } else {
                $error_message = is_wp_error($post_id) ? $post_id->get_error_message() : '不明なエラー';
                $debug_info[] = "\n投稿作成失敗: " . $error_message;
            }
        } else {
            $debug_info[] = "\n有効な記事がないため投稿を作成しませんでした";
        }
        
        $result = $posts_created . '件の投稿を作成しました（' . count($valid_articles) . '件の記事を含む）。';
        $result .= "\n投稿ID: " . ($post_id ?? 'なし');
        if ($duplicates_skipped > 0) $result .= "\n重複スキップ: " . $duplicates_skipped . '件';
        if ($low_quality_skipped > 0) $result .= "\n低品質スキップ: " . $low_quality_skipped . '件';
        if (!empty($errors)) $result .= "\nエラー: " . implode(', ', $errors);
        
        $result .= "\n\n=== デバッグ情報 ===\n" . implode("\n", $debug_info);
        
        $this->update_crawler_statistics($posts_created, $duplicates_skipped, $low_quality_skipped);
        
        return $result;
    }
    
    private function is_keyword_match($article, $keywords) {
        $text_to_search = strtolower($article['title'] . ' ' . ($article['excerpt'] ?? '') . ' ' . ($article['news_content'] ?? '') . ' ' . ($article['description'] ?? ''));
        
        // デバッグ用：検索対象のテキストを記録
        global $news_crawler_search_text;
        $news_crawler_search_text = $text_to_search;
        
        // デバッグ情報を追加
        $debug_info = array();
        $debug_info[] = 'キーワードマッチング詳細:';
        $debug_info[] = '  記事タイトル: ' . $article['title'];
        $debug_info[] = '  検索対象テキスト（最初の200文字）: ' . mb_substr($text_to_search, 0, 200) . '...';
        $debug_info[] = '  設定されたキーワード: ' . implode(', ', $keywords);
        
        $match_found = false;
        $matched_keywords = array();
        
        foreach ($keywords as $keyword) {
            $keyword_trimmed = trim($keyword);
            if (empty($keyword_trimmed)) {
                continue; // 空のキーワードはスキップ
            }
            
            $keyword_lower = strtolower($keyword_trimmed);
            
            // 完全一致チェック
            if (stripos($text_to_search, $keyword_lower) !== false) {
                $match_found = true;
                $matched_keywords[] = $keyword_trimmed;
                $debug_info[] = '  ✓ キーワード "' . $keyword_trimmed . '" でマッチ';
            } else {
                $debug_info[] = '  ✗ キーワード "' . $keyword_trimmed . '" でマッチなし';
            }
        }
        
        if ($match_found) {
            $debug_info[] = '  結果: マッチ成功 (' . implode(', ', $matched_keywords) . ')';
        } else {
            $debug_info[] = '  結果: マッチ失敗';
        }
        
        // デバッグ情報をグローバル変数に保存
        global $news_crawler_keyword_debug;
        if (!isset($news_crawler_keyword_debug)) {
            $news_crawler_keyword_debug = array();
        }
        $news_crawler_keyword_debug[] = implode("\n", $debug_info);
        
        return $match_found;
    }
    
    private function create_summary_post($articles, $category, $status) {
        $cat_id = $this->get_or_create_category($category);
        
        // キーワード情報を取得
        $options = get_option('news_crawler_settings', array());
        $keywords = isset($options['keywords']) ? $options['keywords'] : array('ニュース');
        
        // キーワードが設定されていない場合は、記事の内容から推測
        if (empty($keywords) || (count($keywords) === 1 && $keywords[0] === 'ニュース')) {
            $keyword_text = '最新';
        } else {
            // キーワードを組み合わせてタイトルを作成（最大3つまで）
            $keyword_text = implode('、', array_slice($keywords, 0, 3));
        }
        
        $post_title = $keyword_text . '：ニュースまとめ – ' . date_i18n('Y年n月j日');
        
        $post_content = '';
        
        $articles_by_source = array();
        foreach ($articles as $article) {
            $source_host = parse_url($article['source'], PHP_URL_HOST) ?: $article['source'];
            $articles_by_source[$source_host][] = $article;
        }
        
        foreach ($articles_by_source as $source_host => $source_articles) {
            $post_content .= '<!-- wp:quote -->';
            $post_content .= '<blockquote class="wp-block-quote">';
            
            $post_content .= '<!-- wp:heading {"level":2} -->';
            $post_content .= '<h2>' . esc_html($this->get_readable_source_name($source_host)) . '</h2>';
            $post_content .= '<!-- /wp:heading -->';
            
            foreach ($source_articles as $article) {
                if (!empty($article['link'])) {
                    $post_content .= '<!-- wp:heading {"level":3} -->';
                    $post_content .= '<h3><a href="' . esc_url($article['link']) . '" target="_blank" rel="noopener noreferrer">' . esc_html($article['title']) . '</a></h3>';
                    $post_content .= '<!-- /wp:heading -->';
                } else {
                    $post_content .= '<!-- wp:heading {"level":3} -->';
                    $post_content .= '<h3>' . esc_html($article['title']) . '</h3>';
                    $post_content .= '<!-- /wp:heading -->';
                }
                
                if (!empty($article['excerpt'])) {
                    $post_content .= '<!-- wp:paragraph -->';
                    $post_content .= '<p>' . esc_html($article['excerpt']) . '</p>';
                    $post_content .= '<!-- /wp:paragraph -->';
                }
                
                $meta_info = [];
                if (!empty($article['article_date'])) {
                    $meta_info[] = '<strong>公開日:</strong> ' . esc_html($article['article_date']);
                }
                if (!empty($article['source'])) {
                    $meta_info[] = '<strong>出典:</strong> <a href="' . esc_url($article['source']) . '" target="_blank" rel="noopener noreferrer">' . esc_html(parse_url($article['source'], PHP_URL_HOST) ?: $article['source']) . '</a>';
                }

                if (!empty($meta_info)) {
                    $post_content .= '<!-- wp:paragraph {"fontSize":"small"} -->';
                    $post_content .= '<p class="has-small-font-size">' . implode(' | ', $meta_info) . '</p>';
                    $post_content .= '<!-- /wp:paragraph -->';
                }

                $post_content .= '<!-- wp:spacer {"height":"20px"} -->';
                $post_content .= '<div style="height:20px" aria-hidden="true" class="wp-block-spacer"></div>';
                $post_content .= '<!-- /wp:spacer -->';
            }
            
            $post_content .= '</blockquote>';
            $post_content .= '<!-- /wp:quote -->';
        }
        
        // News Crawler用の処理のため、最初に下書きとして投稿を作成
        $post_data = array(
            'post_title'    => $post_title,
            'post_content'  => $post_content,
            'post_status'   => 'draft', // 最初は下書きとして作成
            'post_author'   => get_current_user_id() ?: 1,
            'post_type'     => 'post',
            'post_category' => array($cat_id)
        );
        
        // ksesフィルターを一時的に無効化して投稿を作成
        kses_remove_filters();
        $post_id = wp_insert_post($post_data, true);
        kses_init_filters();
        
        if (is_wp_error($post_id)) {
            return $post_id;
        }
        
        // メタデータの保存
        update_post_meta($post_id, '_news_summary', true);
        update_post_meta($post_id, '_news_articles_count', count($articles));
        update_post_meta($post_id, '_news_crawled_date', current_time('mysql'));
        
        // XPoster連携用のメタデータを追加
        update_post_meta($post_id, '_news_crawler_created', true);
        update_post_meta($post_id, '_news_crawler_creation_method', 'auto');
        update_post_meta($post_id, '_news_crawler_intended_status', $status);
        update_post_meta($post_id, '_news_crawler_creation_timestamp', current_time('timestamp'));
        update_post_meta($post_id, '_news_crawler_ready', false);
        
        // News Crawler用のメタデータを設定
        update_post_meta($post_id, '_news_crawler_post_this', 'yes');
        update_post_meta($post_id, '_news_crawler_twitter', 'yes'); // カスタムツイート用
        update_post_meta($post_id, '_news_crawler_template_x', 'yes'); // X用テンプレート
        update_post_meta($post_id, '_news_crawler_template_mastodon', 'yes'); // Mastodon用テンプレート
        update_post_meta($post_id, '_news_crawler_template_bluesky', 'yes'); // Bluesky用テンプレート
        
        // ジャンルIDを保存（自動投稿用）
        $current_genre_setting = get_transient('news_crawler_current_genre_setting');
        if ($current_genre_setting && isset($current_genre_setting['id'])) {
            update_post_meta($post_id, '_news_crawler_genre_id', $current_genre_setting['id']);
        }
        
        foreach ($articles as $index => $article) {
            update_post_meta($post_id, '_news_article_' . $index . '_title', $article['title']);
            update_post_meta($post_id, '_news_article_' . $index . '_source', $article['source']);
            if (!empty($article['link'])) {
                update_post_meta($post_id, '_news_article_' . $index . '_link', $article['link']);
            }
        }
        
        // アイキャッチ生成（ジャンル設定から呼び出された場合）
        error_log('NewsCrawler: About to call maybe_generate_featured_image for news post ' . $post_id);
        $featured_result = $this->maybe_generate_featured_image($post_id, $post_title, $keywords);
        error_log('NewsCrawler: News maybe_generate_featured_image returned: ' . ($featured_result ? 'Success (ID: ' . $featured_result . ')' : 'Failed or skipped'));
        
        // AI要約生成（メタデータ設定後に呼び出し）
        if (class_exists('NewsCrawlerOpenAISummarizer')) {
            $summarizer = new NewsCrawlerOpenAISummarizer();
            $summarizer->generate_summary($post_id);
        }
        
        // X（Twitter）自動シェア（投稿成功後）
        $this->maybe_share_to_twitter($post_id, $post_title);
        
        return $post_id;
    }
    
    private function create_summary_post_with_categories($articles, $categories, $status) {
        // 複数カテゴリーに対応
        $cat_ids = array();
        foreach ($categories as $category) {
            $cat_ids[] = $this->get_or_create_category($category);
        }
        
        // キーワード情報を取得
        $options = get_option('news_crawler_settings', array());
        $keywords = isset($options['keywords']) ? $options['keywords'] : array('ニュース');
        
        // キーワードが設定されていない場合は、記事の内容から推測
        if (empty($keywords) || (count($keywords) === 1 && $keywords[0] === 'ニュース')) {
            $keyword_text = '最新';
        } else {
            // キーワードを組み合わせてタイトルを作成（最大3つまで）
            $keyword_text = implode('、', array_slice($keywords, 0, 3));
        }
        
        $post_title = $keyword_text . '：ニュースまとめ – ' . date_i18n('Y年n月j日');
        
        $post_content = '';
        
        $articles_by_source = array();
        foreach ($articles as $article) {
            $source_host = parse_url($article['source'], PHP_URL_HOST) ?: $article['source'];
            $articles_by_source[$source_host][] = $article;
        }
        
        foreach ($articles_by_source as $source_host => $source_articles) {
            $post_content .= '<!-- wp:quote -->';
            $post_content .= '<blockquote class="wp-block-quote">';
            
            $post_content .= '<!-- wp:heading {"level":2} -->';
            $post_content .= '<h2>' . esc_html($this->get_readable_source_name($source_host)) . '</h2>';
            $post_content .= '<!-- /wp:heading -->';
            
            foreach ($source_articles as $article) {
                if (!empty($article['link'])) {
                    $post_content .= '<!-- wp:heading {"level":3} -->';
                    $post_content .= '<h3><a href="' . esc_url($article['link']) . '" target="_blank" rel="noopener noreferrer">' . esc_html($article['title']) . '</a></h3>';
                    $post_content .= '<!-- /wp:heading -->';
                } else {
                    $post_content .= '<!-- wp:heading {"level":3} -->';
                    $post_content .= '<h3>' . esc_html($article['title']) . '</h3>';
                    $post_content .= '<!-- /wp:heading -->';
                }
                
                if (!empty($article['excerpt'])) {
                    $post_content .= '<!-- wp:paragraph -->';
                    $post_content .= '<p>' . esc_html($article['excerpt']) . '</p>';
                    $post_content .= '<!-- /wp:paragraph -->';
                }
                
                $meta_info = [];
                if (!empty($article['article_date'])) {
                    $meta_info[] = '<strong>公開日:</strong> ' . esc_html($article['article_date']);
                }
                if (!empty($article['source'])) {
                    $meta_info[] = '<strong>出典:</strong> <a href="' . esc_url($article['source']) . '" target="_blank" rel="noopener noreferrer">' . esc_html(parse_url($article['source'], PHP_URL_HOST) ?: $article['source']) . '</a>';
                }

                if (!empty($meta_info)) {
                    $post_content .= '<!-- wp:paragraph {"fontSize":"small"} -->';
                    $post_content .= '<p class="has-small-font-size">' . implode(' | ', $meta_info) . '</p>';
                    $post_content .= '<!-- /wp:paragraph -->';
                }

                $post_content .= '<!-- wp:spacer {"height":"20px"} -->';
                $post_content .= '<div style="height:20px" aria-hidden="true" class="wp-block-spacer"></div>';
                $post_content .= '<!-- /wp:spacer -->';
            }
            
            $post_content .= '</blockquote>';
            $post_content .= '<!-- /wp:quote -->';
        }
        
        // News Crawler用の処理のため、最初に下書きとして投稿を作成
        $post_data = array(
            'post_title'    => $post_title,
            'post_content'  => $post_content,
            'post_status'   => 'draft', // 最初は下書きとして作成
            'post_author'   => get_current_user_id() ?: 1,
            'post_type'     => 'post',
            'post_category' => $cat_ids
        );
        
        // ksesフィルターを一時的に無効化して投稿を作成
        kses_remove_filters();
        $post_id = wp_insert_post($post_data, true);
        kses_init_filters();
        
        if (is_wp_error($post_id)) {
            return $post_id;
        }
        
        // メタデータの保存
        update_post_meta($post_id, '_news_summary', true);
        update_post_meta($post_id, '_news_articles_count', count($articles));
        update_post_meta($post_id, '_news_crawled_date', current_time('mysql'));
        
        // XPoster連携用のメタデータを追加
        update_post_meta($post_id, '_news_crawler_created', true);
        update_post_meta($post_id, '_news_crawler_creation_method', 'auto_categories');
        update_post_meta($post_id, '_news_crawler_intended_status', $status);
        update_post_meta($post_id, '_news_crawler_creation_timestamp', current_time('timestamp'));
        update_post_meta($post_id, '_news_crawler_ready', false);
        
        // News Crawler用のメタデータを設定
        update_post_meta($post_id, '_news_crawler_post_this', 'yes');
        update_post_meta($post_id, '_news_crawler_twitter', 'yes'); // カスタムツイート用
        update_post_meta($post_id, '_news_crawler_template_x', 'yes'); // X用テンプレート
        update_post_meta($post_id, '_news_crawler_template_mastodon', 'yes'); // Mastodon用テンプレート
        update_post_meta($post_id, '_news_crawler_template_bluesky', 'yes'); // Bluesky用テンプレート
        
        foreach ($articles as $index => $article) {
            update_post_meta($post_id, '_news_article_' . $index . '_title', $article['title']);
            update_post_meta($post_id, '_news_article_' . $index . '_source', $article['source']);
            if (!empty($article['link'])) {
                update_post_meta($post_id, '_news_article_' . $index . '_link', $article['link']);
            }
        }
        
        // アイキャッチ生成（ジャンル設定から呼び出された場合）
        error_log('NewsCrawler: About to call maybe_generate_featured_image for news post ' . $post_id);
        $featured_result = $this->maybe_generate_featured_image($post_id, $post_title, $keywords);
        error_log('NewsCrawler: News maybe_generate_featured_image returned: ' . ($featured_result ? 'Success (ID: ' . $featured_result . ')' : 'Failed or skipped'));
        
        // AI要約生成（メタデータ設定後に呼び出し）
        if (class_exists('NewsCrawlerOpenAISummarizer')) {
            $summarizer = new NewsCrawlerOpenAISummarizer();
            $summarizer->generate_summary($post_id);
        }
        
        // X（Twitter）自動シェア（投稿成功後）
        $this->maybe_share_to_twitter($post_id, $post_title);
        
        // News Crawler用の処理のため、投稿ステータス変更を遅延実行
        if ($status !== 'draft') {
            $this->schedule_post_status_update($post_id, $status);
        }
        
        return $post_id;
    }
    
    private function is_duplicate_article($article) {
        global $wpdb;
        $title = $article['title'];
        
        // 基本設定から重複チェック設定を取得
        $basic_settings = get_option('news_crawler_basic_settings', array());
        $strictness = isset($basic_settings['duplicate_check_strictness']) ? $basic_settings['duplicate_check_strictness'] : 'medium';
        $period = isset($basic_settings['duplicate_check_period']) ? intval($basic_settings['duplicate_check_period']) : 30;
        
        // 厳しさに応じて類似度の閾値を設定
        $title_similarity_threshold = 0.8; // デフォルト
        $content_similarity_threshold = 0.7; // デフォルト
        
        switch ($strictness) {
            case 'low':
                $title_similarity_threshold = 0.7;
                $content_similarity_threshold = 0.6;
                break;
            case 'high':
                $title_similarity_threshold = 0.9;
                $content_similarity_threshold = 0.8;
                break;
            default: // medium
                $title_similarity_threshold = 0.8;
                $content_similarity_threshold = 0.7;
                break;
        }
        
        // 1. 完全一致タイトルチェック（設定された期間）
        $exact_title_match = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} 
             WHERE post_title = %s 
             AND post_type = 'post' 
             AND post_status IN ('publish', 'draft', 'pending') 
             AND post_date >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $title,
            $period
        ));
        if ($exact_title_match) {
            error_log('NewsCrawler: 完全一致タイトルで重複を検出: ' . $title);
            return true;
        }
        
        // 2. 高類似度タイトルチェック（設定された期間、設定された類似度以上）
        $similar_titles = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_title FROM {$wpdb->posts} 
             WHERE post_type = 'post' 
             AND post_status IN ('publish', 'draft', 'pending') 
             AND post_date >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $period
        ));
        
        foreach ($similar_titles as $existing_post) {
            $similarity = $this->calculate_title_similarity($title, $existing_post->post_title);
            if ($similarity >= $title_similarity_threshold) {
                error_log('NewsCrawler: 高類似度タイトルで重複を検出: ' . $title . ' vs ' . $existing_post->post_title . ' (類似度: ' . $similarity . ', 閾値: ' . $title_similarity_threshold . ')');
                return true;
            }
        }
        
        // 3. URL重複チェック（設定された期間）
        if (!empty($article['link'])) {
            $existing_url = $wpdb->get_var($wpdb->prepare(
                "SELECT pm.post_id FROM {$wpdb->postmeta} pm 
                 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
                 WHERE pm.meta_key = '_news_source' 
                 AND pm.meta_value = %s 
                 AND p.post_date >= DATE_SUB(NOW(), INTERVAL %d DAY)",
                $article['link'],
                $period
            ));
            if ($existing_url) {
                error_log('NewsCrawler: URL重複で重複を検出: ' . $article['link']);
                return true;
            }
        }
        
        // 4. 内容の類似性チェック（設定された期間、設定された類似度以上）
        if (!empty($article['excerpt']) || !empty($article['news_content'])) {
            $content_text = '';
            if (!empty($article['excerpt'])) $content_text .= $article['excerpt'] . ' ';
            if (!empty($article['news_content'])) $content_text .= $article['news_content'] . ' ';
            if (!empty($article['description'])) $content_text .= $article['description'] . ' ';
            
            $recent_posts = $wpdb->get_results($wpdb->prepare(
                "SELECT ID, post_content FROM {$wpdb->posts} 
                 WHERE post_type = 'post' 
                 AND post_status IN ('publish', 'draft', 'pending') 
                 AND post_date >= DATE_SUB(NOW(), INTERVAL %d DAY)",
                $period
            ));
            
            foreach ($recent_posts as $existing_post) {
                $content_similarity = $this->calculate_content_similarity($content_text, $existing_post->post_content);
                if ($content_similarity >= $content_similarity_threshold) {
                    error_log('NewsCrawler: 内容類似性で重複を検出: ' . $title . ' (類似度: ' . $content_similarity . ', 閾値: ' . $content_similarity_threshold . ')');
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * タイトルの類似度を計算（レーベンシュタイン距離ベース）
     */
    private function calculate_title_similarity($title1, $title2) {
        $title1 = mb_strtolower(trim($title1));
        $title2 = mb_strtolower(trim($title2));
        
        // 完全一致
        if ($title1 === $title2) {
            return 1.0;
        }
        
        // 片方が空
        if (empty($title1) || empty($title2)) {
            return 0.0;
        }
        
        // レーベンシュタイン距離を計算
        $distance = levenshtein($title1, $title2);
        $max_length = max(mb_strlen($title1), mb_strlen($title2));
        
        // 類似度を計算（距離が小さいほど類似度が高い）
        $similarity = 1 - ($distance / $max_length);
        
        return max(0, $similarity);
    }
    
    /**
     * 内容の類似度を計算（キーワードベース）
     */
    private function calculate_content_similarity($content1, $content2) {
        $content1 = mb_strtolower(trim($content1));
        $content2 = mb_strtolower(trim($content2));
        
        // 片方が空
        if (empty($content1) || empty($content2)) {
            return 0.0;
        }
        
        // キーワードを抽出（2文字以上の単語）
        preg_match_all('/\b\w{2,}\b/', $content1, $matches1);
        preg_match_all('/\b\w{2,}\b/', $content2, $matches2);
        
        $keywords1 = array_unique($matches1[0]);
        $keywords2 = array_unique($matches2[0]);
        
        if (empty($keywords1) || empty($keywords2)) {
            return 0.0;
        }
        
        // 共通キーワード数を計算
        $common_keywords = array_intersect($keywords1, $keywords2);
        $total_keywords = array_unique(array_merge($keywords1, $keywords2));
        
        $similarity = count($common_keywords) / count($total_keywords);
        
        return $similarity;
    }
    
    private function calculate_quality_score($article) {
        $score = 0;
        $debug_details = [];
        
        $title_length = mb_strlen($article['title']);
        if ($title_length >= 5 && $title_length <= 150) {
            $score += 0.3;
            $debug_details[] = "タイトル長: " . $title_length . "文字 (+0.3)";
        } else {
            $debug_details[] = "タイトル長: " . $title_length . "文字 (不足)";
        }
        
        // excerptとnews_contentの両方をチェック（RSSとHTMLの両方に対応）
        $content_text = '';
        if (!empty($article['excerpt'])) $content_text .= $article['excerpt'] . ' ';
        if (!empty($article['news_content'])) $content_text .= $article['news_content'] . ' ';
        if (!empty($article['description'])) $content_text .= $article['description'] . ' ';
        
        $content_length = mb_strlen(trim($content_text));
        if ($content_length >= 50) {
            $score += 0.4;
            $debug_details[] = "本文長: " . $content_length . "文字 (+0.4)";
        } else {
            $debug_details[] = "本文長: " . $content_length . "文字 (不足)";
        }
        
        if (!empty($article['image_url'])) {
            $score += 0.1;
            $debug_details[] = "画像あり (+0.1)";
        } else {
            $debug_details[] = "画像なし";
        }
        
        if (!empty($article['article_date'])) {
            $score += 0.1;
            $debug_details[] = "日付あり (+0.1)";
        } else {
            $debug_details[] = "日付なし";
        }
        
        if (!empty($article['source'])) {
            $score += 0.1;
            $debug_details[] = "ソースあり (+0.1)";
        } else {
            $debug_details[] = "ソースなし";
        }
        
        $final_score = min($score, 1.0);
        $debug_details[] = "最終スコア: " . number_format($final_score, 2);
        
        // デバッグ情報をグローバル変数に保存
        global $news_crawler_debug_details;
        $news_crawler_debug_details = $debug_details;
        
        return $final_score;
    }
    
    private function get_crawler_statistics() {
        global $wpdb;
        $stats = array();
        $stats['total_posts'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_news_summary'");
        $stats['posts_this_month'] = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_news_crawled_date' AND meta_value >= %s", date('Y-m-01')));
        $stats['duplicates_skipped'] = get_option('news_crawler_duplicates_skipped', 0);
        $stats['low_quality_skipped'] = get_option('news_crawler_low_quality_skipped', 0);
        $stats['last_run'] = get_option('news_crawler_last_run', '未実行');
        return $stats;
    }
    
    private function update_crawler_statistics($posts_created, $duplicates_skipped, $low_quality_skipped) {
        if ($duplicates_skipped > 0) {
            $current_duplicates = get_option('news_crawler_duplicates_skipped', 0);
            update_option('news_crawler_duplicates_skipped', $current_duplicates + $duplicates_skipped);
        }
        if ($low_quality_skipped > 0) {
            $current_low_quality = get_option('news_crawler_low_quality_skipped', 0);
            update_option('news_crawler_low_quality_skipped', $current_low_quality + $low_quality_skipped);
        }
        update_option('news_crawler_last_run', current_time('mysql'));
    }
    
    private function fetch_content($url) {
        if ($this->is_rss_feed($url)) {
            return $this->fetch_rss_content($url);
        } else {
            return $this->fetch_html_content($url);
        }
    }
    
    private function is_rss_feed($url) {
        $url_lower = strtolower($url);
        return str_contains($url_lower, 'rss') || str_contains($url_lower, 'feed') || str_contains($url_lower, 'xml');
    }
    
    private function fetch_rss_content($url) {
        $response = wp_remote_get($url, array('timeout' => 30, 'user-agent' => 'NewsCrawler/1.1'));
        if (is_wp_error($response)) return false;
        $body = wp_remote_retrieve_body($response);
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        if ($xml === false) return false;
        return $this->parse_rss_xml($xml, $url);
    }
    
    private function fetch_html_content($url) {
        $response = wp_remote_get($url, array('timeout' => 30, 'user-agent' => 'NewsCrawler/1.1'));
        if (is_wp_error($response)) return false;
        return wp_remote_retrieve_body($response);
    }
    
    private function parse_rss_xml($xml, $source_url) {
        $articles = array();
        $items = $xml->channel->item ?? $xml->entry ?? [];
        
        foreach ($items as $item) {
            $namespaces = $item->getNamespaces(true);
            $dc = $item->children($namespaces['dc'] ?? '');

            $article = array(
                'title' => (string)($item->title ?? ''),
                'link' => (string)($item->link['href'] ?? $item->link ?? ''),
                'description' => (string)($item->description ?? $item->summary ?? ''),
                'article_date' => date('Y-m-d H:i:s', strtotime((string)($item->pubDate ?? $item->published ?? $dc->date ?? 'now'))),
                'source' => $source_url
            );
            $article['excerpt'] = wp_strip_all_tags($article['description']);
            $articles[] = $article;
        }
        return $articles;
    }
    
    private function parse_content($content, $source) {
        try {
            $doc = new DOMDocument();
            libxml_use_internal_errors(true);
            $doc->loadHTML('<?xml encoding="utf-8" ?>' . $content);
            libxml_clear_errors();
            $xpath = new DOMXPath($doc);
            
            if (!$xpath) {
                error_log('News Crawler: XPath初期化に失敗しました');
                return array();
            }

        $articles = array();
        
        // 複数の記事を抽出するためのセレクター
        $article_selectors = array(
            '//article',
            '//div[contains(@class, "post")]',
            '//div[contains(@class, "news")]',
            '//div[contains(@class, "item")]',
            '//li[contains(@class, "news")]',
            '//div[contains(@class, "article")]',
            '//div[contains(@class, "entry")]'
        );
        
        $found_articles = false;
        foreach ($article_selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes && $nodes->length > 0) {
                foreach ($nodes as $node) {
                    $title_query = $xpath->query('.//h1|.//h2|.//h3|.//h4|.//a', $node);
                    if (!$title_query || $title_query->length === 0) continue;
                    
                    $title_node = $title_query->item(0);
                    if (!$title_node) continue;
                    
                    $title = trim($title_node->nodeValue);
                    if (empty($title) || mb_strlen($title) < 5) continue;
                    
                    $link_query = $xpath->query('.//a', $node);
                    $link = '';
                    if ($link_query && $link_query->length > 0) {
                        $link_node = $link_query->item(0);
                        if ($link_node) {
                            $link = $link_node->getAttribute('href');
                            // 相対URLを絶対URLに変換
                            if (!empty($link) && !filter_var($link, FILTER_VALIDATE_URL)) {
                                $link = $this->build_absolute_url($source, $link);
                            }
                        }
                    }
                    
                    $paragraphs = array();
                    // より多くの要素から本文を抽出
                    $content_selectors = array(
                        './/p',
                        './/div[contains(@class, "content")]',
                        './/div[contains(@class, "text")]',
                        './/div[contains(@class, "body")]',
                        './/div[contains(@class, "article")]',
                        './/span[contains(@class, "content")]',
                        './/span[contains(@class, "text")]'
                    );
                    
                    foreach ($content_selectors as $content_selector) {
                        $content_query = $xpath->query($content_selector, $node);
                        if ($content_query && $content_query->length > 0) {
                            foreach ($content_query as $content_element) {
                                $text = trim($content_element->nodeValue);
                                if (mb_strlen($text) > 20) {
                                    $paragraphs[] = $text;
                                }
                            }
                        }
                    }
                    
                    // 段落が見つからない場合は、ノード全体からテキストを抽出
                    if (empty($paragraphs)) {
                        $node_text = trim(strip_tags($doc->saveHTML($node)));
                        if (mb_strlen($node_text) > 50) {
                            $paragraphs[] = $node_text;
                        }
                    }
                    
                    $excerpt = implode(' ', array_slice($paragraphs, 0, 2));
                    
                    $time_query = $xpath->query('.//time[@datetime]|.//span[@class*="date"]', $node);
                    $article_date = '';
                    if ($time_query && $time_query->length > 0) {
                        $time_node = $time_query->item(0);
                        if ($time_node) {
                            if ($time_node->hasAttribute('datetime')) {
                                $article_date = date('Y-m-d H:i:s', strtotime($time_node->getAttribute('datetime')));
                            } else {
                                $article_date = date('Y-m-d H:i:s', strtotime($time_node->nodeValue));
                            }
                        }
                    }
                    
                    $articles[] = array(
                        'title' => $title,
                        'link' => $link,
                        'excerpt' => $excerpt,
                        'news_content' => implode("\n\n", $paragraphs),
                        'article_date' => $article_date,
                        'source' => $source,
                    );
                    
                    // デバッグ用：抽出された記事の詳細を記録
                    error_log('News Crawler: 記事抽出 - タイトル: ' . $title . ', 本文長: ' . mb_strlen($excerpt) . '文字, リンク: ' . $link);
                }
                $found_articles = true;
                break;
            }
        }
        
        // 記事が見つからない場合は、単一ページとして解析
        if (!$found_articles) {
            $title = '';
            $h1_query = $xpath->query('//h1');
            if ($h1_query && $h1_query->length > 0) {
                $title = $h1_query->item(0)->nodeValue ?? '';
            }
            if (empty($title)) {
                $title_query = $xpath->query('//title');
                if ($title_query && $title_query->length > 0) {
                    $title = $title_query->item(0)->nodeValue ?? '';
                }
            }
            
            $paragraphs = array();
            $p_query = $xpath->query('//p');
            if ($p_query && $p_query->length > 0) {
                foreach ($p_query as $p) {
                    $text = trim($p->nodeValue);
                    if (mb_strlen($text) > 30) {
                        $paragraphs[] = $text;
                    }
                }
            }
            $excerpt = implode(' ', array_slice($paragraphs, 0, 2));
            
            $article_date = '';
            $time_query = $xpath->query('//time[@datetime]');
            if ($time_query && $time_query->length > 0) {
                $time_node = $time_query->item(0);
                if ($time_node) {
                    $article_date = $time_node->getAttribute('datetime');
                }
            }

            $articles[] = array(
                'title' => trim($title),
                'excerpt' => $excerpt,
                'news_content' => implode("\n\n", $paragraphs),
                'article_date' => $article_date ? date('Y-m-d H:i:s', strtotime($article_date)) : '',
                'source' => $source,
            );
        }
        
        return $articles;
        } catch (Exception $e) {
            error_log('News Crawler: HTML解析中にエラーが発生しました: ' . $e->getMessage());
            return array();
        }
    }
    
    private function get_readable_source_name($source_host) {
        // ドメイン名を読みやすい名前に変換
        $source_names = array(
            'www3.nhk.or.jp' => 'NHKニュース',
            'news.tv-asahi.co.jp' => 'テレビ朝日ニュース',
            'newsdig.tbs.co.jp' => 'TBSニュース',
            'www.fnn.jp' => 'フジテレビニュース',
            'news.ntv.co.jp' => '日本テレビニュース',
            'mainichi.jp' => '毎日新聞',
            'www.asahi.com' => '朝日新聞',
            'www.yomiuri.co.jp' => '読売新聞',
            'www.sankei.com' => '産経新聞',
            'www.nikkei.com' => '日本経済新聞',
            'www.tokyo-np.co.jp' => '東京新聞',
            'kyodonews.jp' => '共同通信',
            'www.jiji.com' => '時事通信',
            'www.itmedia.co.jp' => 'ITmedia',
            'www.techno-edge.net' => 'テクノエッジ',
            'sanseito.jp' => '参政党',
            'www.komei.or.jp' => '公明党',
            'reiwa-shinsengumi.com' => 'れいわ新選組'
        );
        
        // 登録されている名前があれば返す
        if (isset($source_names[$source_host])) {
            return $source_names[$source_host];
        }
        
        // 登録されていない場合は、ドメイン名をクリーンアップして返す
        $clean_name = str_replace(array('www.', 'news.', 'www3.'), '', $source_host);
        $clean_name = ucfirst($clean_name); // 最初の文字を大文字に
        
        return $clean_name;
    }
    
    private function build_absolute_url($base_url, $relative_url) {
        // 既に絶対URLの場合はそのまま返す
        if (filter_var($relative_url, FILTER_VALIDATE_URL)) {
            return $relative_url;
        }
        
        // 空の場合は空文字を返す
        if (empty($relative_url)) {
            return '';
        }
        
        // プロトコル相対URL（//example.com/path）の場合
        if (substr($relative_url, 0, 2) === '//') {
            $base_parts = parse_url($base_url);
            $scheme = $base_parts['scheme'] ?? 'https';
            return $scheme . ':' . $relative_url;
        }
        
        // 絶対パス（/path）の場合
        if (substr($relative_url, 0, 1) === '/') {
            $base_parts = parse_url($base_url);
            $scheme = $base_parts['scheme'] ?? 'https';
            $host = $base_parts['host'] ?? '';
            return $scheme . '://' . $host . $relative_url;
        }
        
        // 相対パス（path）の場合
        $base_parts = parse_url($base_url);
        $scheme = $base_parts['scheme'] ?? 'https';
        $host = $base_parts['host'] ?? '';
        $path = $base_parts['path'] ?? '/';
        
        // ベースパスからディレクトリ部分を取得
        $dir = dirname($path);
        if ($dir === '.') {
            $dir = '/';
        }
        
        return $scheme . '://' . $host . $dir . '/' . $relative_url;
    }
    
    private function get_or_create_category($category_name) {
        $category = get_term_by('name', $category_name, 'category');
        if (!$category) {
            $result = wp_insert_term($category_name, 'category');
            return is_wp_error($result) ? 1 : $result['term_id'];
        }
        return $category->term_id;
    }
    
    /**
     * アイキャッチ画像を生成（ジャンル設定から呼び出された場合のみ）
     */
    private function maybe_generate_featured_image($post_id, $title, $keywords) {
        error_log('NewsCrawler: maybe_generate_featured_image called for post ' . $post_id);
        error_log('NewsCrawler: Title: ' . $title);
        error_log('NewsCrawler: Keywords: ' . implode(', ', $keywords));
        
        // ジャンル設定からの実行かどうかを確認
        $genre_setting = get_transient('news_crawler_current_genre_setting');
        
        error_log('NewsCrawler: Genre setting exists: ' . ($genre_setting ? 'Yes' : 'No'));
        if ($genre_setting) {
            error_log('NewsCrawler: Genre setting content: ' . print_r($genre_setting, true));
            error_log('NewsCrawler: Auto featured image enabled: ' . (isset($genre_setting['auto_featured_image']) && $genre_setting['auto_featured_image'] ? 'Yes' : 'No'));
            if (isset($genre_setting['featured_image_method'])) {
                error_log('NewsCrawler: Featured image method: ' . $genre_setting['featured_image_method']);
            }
        } else {
            error_log('NewsCrawler: No genre setting found in transient storage');
            // 基本設定からアイキャッチ生成設定を確認
            $basic_settings = get_option('news_crawler_basic_settings', array());
            $featured_settings = get_option('news_crawler_featured_image_settings', array());
            
            error_log('NewsCrawler: Checking basic settings for featured image generation');
            error_log('NewsCrawler: Basic settings: ' . print_r($basic_settings, true));
            error_log('NewsCrawler: Featured settings: ' . print_r($featured_settings, true));
            
            // 基本設定でアイキャッチ生成が有効かチェック
            $auto_featured_enabled = isset($basic_settings['auto_featured_image']) && $basic_settings['auto_featured_image'];
            if (!$auto_featured_enabled) {
                error_log('NewsCrawler: Featured image generation skipped - not enabled in basic settings');
                return false;
            }
            
            // 基本設定から設定を作成
            $genre_setting = array(
                'auto_featured_image' => true,
                'featured_image_method' => isset($basic_settings['featured_image_method']) ? $basic_settings['featured_image_method'] : 'template'
            );
            error_log('NewsCrawler: Using basic settings for featured image generation');
        }
        
        if (!isset($genre_setting['auto_featured_image']) || !$genre_setting['auto_featured_image']) {
            error_log('NewsCrawler: Featured image generation skipped - not enabled');
            return false;
        }
        
        if (!class_exists('NewsCrawlerFeaturedImageGenerator')) {
            error_log('NewsCrawler: Featured image generator class not found');
            return false;
        }
        
        error_log('NewsCrawler: Creating featured image generator instance');
        $generator = new NewsCrawlerFeaturedImageGenerator();
        $method = isset($genre_setting['featured_image_method']) ? $genre_setting['featured_image_method'] : 'template';
        
        error_log('NewsCrawler: Generating featured image with method: ' . $method);
        
        $result = $generator->generate_and_set_featured_image($post_id, $title, $keywords, $method);
        error_log('NewsCrawler: Featured image generation result: ' . ($result ? 'Success (ID: ' . $result . ')' : 'Failed'));
        
        return $result;
    }
    
    /**
     * 投稿成功後にX（Twitter）にシェアするかチェック
     */
    private function maybe_share_to_twitter($post_id, $post_title) {
        // 基本設定からX（Twitter）設定を取得
        $basic_settings = get_option('news_crawler_basic_settings', array());
        
        // X（Twitter）シェアが有効でない場合はスキップ
        if (empty($basic_settings['twitter_enabled'])) {
            return;
        }
        
        // 必要な認証情報が不足している場合はスキップ
        if (empty($basic_settings['twitter_bearer_token']) || empty($basic_settings['twitter_api_key']) || 
            empty($basic_settings['twitter_api_secret']) || empty($basic_settings['twitter_access_token']) || 
            empty($basic_settings['twitter_access_token_secret'])) {
            error_log('NewsCrawler Twitter: 必要な認証情報が不足しています');
            return;
        }
        
        // 既にシェア済みの場合はスキップ
        if (get_post_meta($post_id, '_twitter_shared', true)) {
            return;
        }
        
        // X（Twitter）にシェア
        $this->share_to_twitter($post_id, $post_title, $basic_settings);
    }
    
    /**
     * X（Twitter）にシェア
     */
    private function share_to_twitter($post_id, $post_title, $settings) {
        // メッセージを作成
        $message = $this->create_twitter_message($post_id, $post_title, $settings);
        
        // 文字数制限チェック（280文字）
        if (mb_strlen($message) > 280) {
            $message = mb_substr($message, 0, 277) . '...';
        }
        
        try {
            // Twitter API v2で投稿
            $result = $this->post_tweet($message, $settings);
            
            if ($result && isset($result['data']['id'])) {
                // シェア成功
                update_post_meta($post_id, '_twitter_shared', true);
                update_post_meta($post_id, '_twitter_tweet_id', $result['data']['id']);
                update_post_meta($post_id, '_twitter_shared_date', current_time('mysql'));
                
                error_log('NewsCrawler Twitter: 投稿ID ' . $post_id . ' をX（Twitter）にシェアしました。Tweet ID: ' . $result['data']['id']);
            } else {
                error_log('NewsCrawler Twitter: 投稿ID ' . $post_id . ' のX（Twitter）シェアに失敗しました');
            }
        } catch (Exception $e) {
            error_log('NewsCrawler Twitter: 投稿ID ' . $post_id . ' のX（Twitter）シェアでエラーが発生: ' . $e->getMessage());
        }
    }
    
    /**
     * Twitter投稿用メッセージを作成
     */
    private function create_twitter_message($post_id, $post_title, $settings) {
        $template = isset($settings['twitter_message_template']) ? $settings['twitter_message_template'] : '{title}';
        
        // カテゴリー情報を取得
        $categories = get_the_category($post_id);
        $category_names = array();
        foreach ($categories as $category) {
            $category_names[] = $category->name;
        }
        $category_text = implode('、', $category_names);
        
        // OGP設定を取得
        $ogp_settings = get_option('news_crawler_ogp_settings', array());
        $include_description = isset($ogp_settings['twitter_include_description']) ? $ogp_settings['twitter_include_description'] : true;
        $description_length = isset($ogp_settings['twitter_description_length']) ? $ogp_settings['twitter_description_length'] : 100;
        
        // 抜粋を取得（HTMLタグを除去）
        $post = get_post($post_id);
        $excerpt = '';
        if ($include_description) {
            $excerpt = wp_strip_all_tags($post->post_excerpt);
            if (empty($excerpt)) {
                $excerpt = wp_strip_all_tags(wp_trim_words($post->post_content, $description_length / 10, ''));
            }
            // 指定された長さに制限
            if (mb_strlen($excerpt) > $description_length) {
                $excerpt = mb_substr($excerpt, 0, $description_length) . '...';
            }
        }
        
        // 変数を置換
        $message = str_replace(
            array('{title}', '{excerpt}', '{category}'),
            array($post_title, $excerpt, $category_text),
            $template
        );
        
        // リンクを含める場合
        if (!empty($settings['twitter_include_link'])) {
            $permalink = get_permalink($post_id);
            $message .= ' ' . $permalink;
            }
        
        // ハッシュタグを追加
        if (!empty($settings['twitter_hashtags'])) {
            $hashtags = explode(' ', $settings['twitter_hashtags']);
            foreach ($hashtags as $hashtag) {
                if (!empty($hashtag) && strpos($hashtag, '#') === 0) {
                    $message .= ' ' . $hashtag;
                } elseif (!empty($hashtag)) {
                    $message .= ' #' . ltrim($hashtag, '#');
                }
            }
        }
        
        return $message;
    }
    
    /**
     * Twitter API v2で投稿
     */
    private function post_tweet($message, $settings) {
        // OAuth 1.0a認証ヘッダーを作成
        $oauth = array(
            'oauth_consumer_key' => $settings['twitter_api_key'],
            'oauth_nonce' => wp_generate_password(32, false),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => time(),
            'oauth_token' => $settings['twitter_access_token'],
            'oauth_version' => '1.0'
        );
        
        $url = 'https://api.twitter.com/2/tweets';
        $method = 'POST';
        
        // パラメータをソート
        ksort($oauth);
        
        // 署名ベース文字列を作成
        $base_string = $method . '&' . rawurlencode($url) . '&';
        $base_string .= rawurlencode(http_build_query($oauth, '', '&', PHP_QUERY_RFC3986));
        
        // 署名キーを作成
        $signature_key = rawurlencode($settings['twitter_api_secret']) . '&' . rawurlencode($settings['twitter_access_token_secret']);
        
        // 署名を生成
        $oauth['oauth_signature'] = base64_encode(hash_hmac('sha1', $base_string, $signature_key, true));
        
        // Authorizationヘッダーを作成
        $auth_header = 'OAuth ';
        $auth_parts = array();
        foreach ($oauth as $key => $value) {
            $auth_parts[] = $key . '="' . rawurlencode($value) . '"';
        }
        $auth_header .= implode(', ', $auth_parts);
        
        // リクエストを送信
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => $auth_header,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'text' => $message
            )),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            throw new Exception('リクエストエラー: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (wp_remote_retrieve_response_code($response) !== 201) {
            $error_message = isset($data['errors'][0]['message']) ? $data['errors'][0]['message'] : '不明なエラー';
            throw new Exception('Twitter API エラー: ' . $error_message);
        }
        
        return $data;
    }
    
    /**
     * News Crawler用の処理のための投稿ステータス変更を遅延実行
     */
    private function schedule_post_status_update($post_id, $target_status) {
        // XPosterが新規投稿を認識するまで10秒待ってからステータスを変更（時間を延長）
        wp_schedule_single_event(time() + 10, 'news_crawler_update_post_status', array($post_id, $target_status));
        
        // 追加でNews Crawler用のメタデータを再設定
        wp_schedule_single_event(time() + 3, 'news_crawler_ensure_meta', array($post_id));
        
        error_log('NewsCrawler: 投稿ステータス変更を遅延実行でスケジュール (ID: ' . $post_id . ', 対象ステータス: ' . $target_status . ')');
    }
}

// YouTubeクラスを読み込み
require_once plugin_dir_path(__FILE__) . 'includes/class-youtube-crawler.php';

// プラグインのインスタンス化
new NewsCrawler();

// YouTube機能が利用可能な場合のみインスタンス化
if (class_exists('NewsCrawlerYouTubeCrawler')) {
    new NewsCrawlerYouTubeCrawler();
}
