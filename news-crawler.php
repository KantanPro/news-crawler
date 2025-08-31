<?php
/**
 * Plugin Name: News Crawler
 * Description: 指定されたニュースソースから記事を自動取得し、WordPressサイトに投稿として追加します。YouTube動画クロール機能も含まれています。
 * Version: 2.1.2
 * Author: KantanPro
 * Author URI: https://kantanpro.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: news-crawler
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.9.1
 * Requires PHP: 7.4
 * Update URI: https://github.com/KantanPro/news-crawler.git
 **/

// 直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

// プラグイン定数の定義
define('NEWS_CRAWLER_VERSION', '2.1.2');
define('NEWS_CRAWLER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NEWS_CRAWLER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('NEWS_CRAWLER_TEXT_DOMAIN', 'news-crawler');

// 必要なクラスファイルをインクルード
require_once NEWS_CRAWLER_PLUGIN_DIR . 'includes/class-i18n.php';
require_once NEWS_CRAWLER_PLUGIN_DIR . 'includes/class-security-manager.php';
require_once NEWS_CRAWLER_PLUGIN_DIR . 'includes/class-settings-manager.php';
require_once NEWS_CRAWLER_PLUGIN_DIR . 'includes/class-genre-settings.php';
require_once NEWS_CRAWLER_PLUGIN_DIR . 'includes/class-youtube-crawler.php';
require_once NEWS_CRAWLER_PLUGIN_DIR . 'includes/class-featured-image-generator.php';
require_once NEWS_CRAWLER_PLUGIN_DIR . 'includes/class-eyecatch-generator.php';
require_once NEWS_CRAWLER_PLUGIN_DIR . 'includes/class-eyecatch-admin.php';
require_once NEWS_CRAWLER_PLUGIN_DIR . 'includes/class-openai-summarizer.php';
require_once NEWS_CRAWLER_PLUGIN_DIR . 'includes/class-post-editor-summary.php';
require_once NEWS_CRAWLER_PLUGIN_DIR . 'includes/class-ogp-manager.php';
require_once NEWS_CRAWLER_PLUGIN_DIR . 'includes/class-ogp-settings.php';
require_once NEWS_CRAWLER_PLUGIN_DIR . 'includes/class-seo-title-generator.php';
require_once NEWS_CRAWLER_PLUGIN_DIR . 'includes/class-updater.php';


// プラグイン初期化
function news_crawler_init() {
    // 国際化の初期化
    NewsCrawlerI18n::init();
    
    // セキュリティマネージャーの初期化
    NewsCrawlerSecurityManager::get_instance();
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
    
    // SEOタイトル生成クラスを初期化
    if (class_exists('NewsCrawlerSEOTitleGenerator')) {
        new NewsCrawlerSEOTitleGenerator();
    }
    
    // OGP管理クラスを初期化
    if (class_exists('NewsCrawlerOGPManager')) {
        new NewsCrawlerOGPManager();
    }
    
    // OGP設定クラスを初期化
    if (class_exists('NewsCrawlerOGPSettings')) {
        new NewsCrawlerOGPSettings();
    }
    
    // 更新チェッククラスを初期化
    if (class_exists('NewsCrawlerUpdater')) {
        new NewsCrawlerUpdater();
    }
    

}
add_action('plugins_loaded', 'news_crawler_init');

// 自動投稿のcron設定を確実に実行するための追加フック
add_action('init', 'news_crawler_ensure_cron_setup');
add_action('wp_loaded', 'news_crawler_ensure_cron_setup');

function news_crawler_ensure_cron_setup() {
    // 自動投稿のcronが設定されているかチェック
    $next_cron = wp_next_scheduled('news_crawler_auto_posting_cron');
    
    // cronが設定されていない場合は設定を実行
    if (!$next_cron) {
        if (class_exists('NewsCrawlerGenreSettings')) {
            $genre_settings = new NewsCrawlerGenreSettings();
            if (method_exists($genre_settings, 'setup_auto_posting_cron')) {
                $genre_settings->setup_auto_posting_cron();
                error_log('NewsCrawler: 自動投稿のcron設定を実行しました');
            }
        }
    }
}

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
        // XPoster連携機能は削除済み
        
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

// 重複したYouTubeCrawlerクラスは削除されました
// 新しいNewsCrawlerYouTubeCrawlerクラスを使用してください

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
    }
    
    public function init() {
        // 初期化処理
        load_plugin_textdomain('news-crawler', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function admin_init() {
        register_setting($this->option_name, $this->option_name, array($this, 'sanitize_settings'));
        
        add_settings_section(
            'news_crawler_main',
            'ニュースクロール基本設定',
            array($this, 'main_section_callback'),
            'news-crawler'
        );
        
        add_settings_field(
            'max_articles',
            '一度に引用する記事数',
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
            'ニュースソース',
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
    }
    
    public function main_section_callback() {
        echo '<p>ニュースソースから記事を取得し、要約と共に投稿を作成します。</p>';
    }
    
    public function max_articles_callback() {
        $options = get_option($this->option_name, array());
        $max_articles = isset($options['max_articles']) && !empty($options['max_articles']) ? $options['max_articles'] : 1;
        echo '<input type="number" id="max_articles" name="' . $this->option_name . '[max_articles]" value="' . esc_attr($max_articles) . '" min="1" max="50" />';
        echo '<p class="description">一度に引用する記事の数（1-50件）</p>';
    }
    
    public function news_sources_callback() {
        $options = get_option($this->option_name, array());
        $sources = isset($options['news_sources']) && !empty($options['news_sources']) ? $options['news_sources'] : array();
        $sources_text = implode("\n", $sources);
        echo '<textarea id="news_sources" name="' . $this->option_name . '[news_sources]" rows="10" cols="50" placeholder="https://example.com/news&#10;https://example2.com/rss">' . esc_textarea($sources_text) . '</textarea>';
        echo '<p class="description">1行に1URLを入力してください。RSSフィードまたはニュースサイトのURLを指定できます。</p>';
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
        update_post_meta($post_id, '_news_crawler_creation_timestamp', current_time('timestamp'));
        update_post_meta($post_id, '_news_crawler_ready', false);
        
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
        
        // X（Twitter）自動シェア機能は削除済み
        
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

// 重複したNewsCrawlerクラスは削除されました

// YouTubeクラスを読み込み
require_once plugin_dir_path(__FILE__) . 'includes/class-youtube-crawler.php';

// プラグインのインスタンス化
new NewsCrawler();

// YouTube機能が利用可能な場合のみインスタンス化
if (class_exists('NewsCrawlerYouTubeCrawler')) {
    new NewsCrawlerYouTubeCrawler();
}

// プラグイン無効化時のクリーンアップ
register_deactivation_hook(__FILE__, 'news_crawler_deactivation');

function news_crawler_deactivation() {
    // 更新チェックのクリーンアップ
    if (class_exists('NewsCrawlerUpdater')) {
        NewsCrawlerUpdater::cleanup();
    }
    
    // その他のクリーンアップ処理
    wp_clear_scheduled_hook('news_crawler_auto_posting_cron');
}
