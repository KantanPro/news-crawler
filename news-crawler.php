<?php
/**
 * Plugin Name: News Crawler
 * Description: 指定されたニュースソースから記事を自動取得し、WordPressサイトに投稿として追加します。YouTube動画クロール機能も含まれています。
 * Version: 2.3.17
 * Author: KantanPro
 * Author URI: https://kantanpro.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: news-crawler
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.9.1
 * Requires PHP: 7.4
 **/

// 直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

// プラグイン定数の定義
define('NEWS_CRAWLER_VERSION', '2.3.17');
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
require_once NEWS_CRAWLER_PLUGIN_DIR . 'includes/class-cron-settings.php';
require_once NEWS_CRAWLER_PLUGIN_DIR . 'includes/class-seo-title-generator.php';
require_once NEWS_CRAWLER_PLUGIN_DIR . 'includes/class-updater.php';
require_once NEWS_CRAWLER_PLUGIN_DIR . 'includes/class-license-manager.php';
require_once NEWS_CRAWLER_PLUGIN_DIR . 'includes/class-license-settings.php';


// プラグイン初期化
function news_crawler_init() {
    // 国際化の初期化（翻訳読み込みを最初に実行）
    NewsCrawlerI18n::init();
    
    // 翻訳読み込み完了後に他の初期化処理を実行
    add_action('init', 'news_crawler_init_components', 15);
}

// AJAXハンドラーの直接登録（バックアップ用）
add_action('wp_ajax_news_crawler_toggle_dev_license', 'news_crawler_ajax_toggle_dev_license');
add_action('wp_ajax_nopriv_news_crawler_toggle_dev_license', 'news_crawler_ajax_toggle_dev_license');

// テスト用のAJAXハンドラー
add_action('wp_ajax_news_crawler_test_ajax', 'news_crawler_test_ajax');
add_action('wp_ajax_nopriv_news_crawler_test_ajax', 'news_crawler_test_ajax');

// 直接的なAJAX処理（WordPressのAJAX処理をバイパス）
add_action('wp_loaded', 'news_crawler_handle_direct_ajax');

function news_crawler_handle_direct_ajax() {
    if (isset($_POST['action']) && $_POST['action'] === 'news_crawler_direct_toggle') {
        news_crawler_direct_toggle_handler();
        exit;
    }
}

function news_crawler_direct_toggle_handler() {
    error_log('NewsCrawler: Direct toggle handler called');
    error_log('NewsCrawler: POST data = ' . print_r($_POST, true));
    
    // 基本的なセキュリティチェック
    if (!current_user_can('manage_options')) {
        error_log('NewsCrawler: User does not have manage_options capability');
        wp_die('権限がありません。');
    }
    
    // ライセンス管理クラスのインスタンスを取得
    if (class_exists('NewsCrawler_License_Manager')) {
        $license_manager = NewsCrawler_License_Manager::get_instance();
        
        // 開発環境チェック
        if (!$license_manager->is_development_environment()) {
            error_log('NewsCrawler: Not in development environment');
            wp_send_json_error(array('message' => '開発環境でのみ利用できます。'));
        }
        
        // 開発用ライセンスの状態を切り替え
        $enabled = get_option('news_crawler_dev_license_enabled', '1');
        $new_status = ($enabled === '1') ? '0' : '1';
        
        update_option('news_crawler_dev_license_enabled', $new_status);
        
        error_log('NewsCrawler: Dev license toggled from ' . $enabled . ' to ' . $new_status);
        
        // JSONレスポンスを返す
        header('Content-Type: application/json');
        echo json_encode(array(
            'success' => true,
            'data' => array(
                'new_status' => ($new_status === '1'),
                'message' => '開発用ライセンスの状態が変更されました。'
            )
        ));
    } else {
        error_log('NewsCrawler: NewsCrawler_License_Manager class not found');
        header('Content-Type: application/json');
        echo json_encode(array(
            'success' => false,
            'data' => array('message' => 'ライセンス管理機能が利用できません。')
        ));
    }
}

function news_crawler_simple_test() {
    error_log('NewsCrawler: Simple test AJAX handler called');
    error_log('NewsCrawler: POST data = ' . print_r($_POST, true));
    error_log('NewsCrawler: GET data = ' . print_r($_GET, true));
    
    // セキュリティチェックをバイパス（テスト用）
    if (!isset($_POST['action']) || $_POST['action'] !== 'news_crawler_simple_test') {
        error_log('NewsCrawler: Invalid action in simple test AJAX');
        wp_die('Invalid action');
    }
    
    echo json_encode(array('success' => true, 'message' => 'Simple test working'));
    wp_die();
}

function news_crawler_test_ajax() {
    error_log('NewsCrawler: Test AJAX handler called');
    error_log('NewsCrawler: POST data = ' . print_r($_POST, true));
    error_log('NewsCrawler: GET data = ' . print_r($_GET, true));
    error_log('NewsCrawler: REQUEST_METHOD = ' . $_SERVER['REQUEST_METHOD']);
    error_log('NewsCrawler: REQUEST_URI = ' . $_SERVER['REQUEST_URI']);
    
    // 基本的なセキュリティチェック
    if (!isset($_POST['action']) || $_POST['action'] !== 'news_crawler_test_ajax') {
        error_log('NewsCrawler: Invalid action in test AJAX');
        wp_die('Invalid action');
    }
    
    wp_send_json_success(array('message' => 'Test AJAX handler is working'));
}

function news_crawler_ajax_toggle_dev_license() {
    error_log('NewsCrawler: Direct AJAX handler called for news_crawler_toggle_dev_license');
    error_log('NewsCrawler: POST data = ' . print_r($_POST, true));
    error_log('NewsCrawler: GET data = ' . print_r($_GET, true));
    
    // 基本的なセキュリティチェック
    if (!isset($_POST['nonce'])) {
        error_log('NewsCrawler: No nonce found in POST data');
        wp_die('Security check failed. Please try again.');
    }
    
    $nonce = sanitize_text_field($_POST['nonce']);
    error_log('NewsCrawler: Received nonce: ' . $nonce);
    error_log('NewsCrawler: Expected nonce: ' . wp_create_nonce('news_crawler_license_nonce'));
    
    if (!wp_verify_nonce($nonce, 'news_crawler_license_nonce')) {
        error_log('NewsCrawler: Nonce verification failed');
        wp_die('Security check failed. Please try again.');
    }
    
    // ユーザー権限チェック
    if (!current_user_can('manage_options')) {
        error_log('NewsCrawler: User does not have manage_options capability');
        wp_die('権限がありません。');
    }
    
    // ライセンス管理クラスのインスタンスを取得
    if (class_exists('NewsCrawler_License_Manager')) {
        $license_manager = NewsCrawler_License_Manager::get_instance();
        $license_manager->ajax_toggle_dev_license();
    } else {
        error_log('NewsCrawler: NewsCrawler_License_Manager class not found');
        wp_send_json_error(array('message' => 'ライセンス管理機能が利用できません。'));
    }
}

// ライセンス制限通知
function news_crawler_license_restriction_notice() {
    ?>
    <div class="notice notice-error">
        <p>
            <strong><?php echo esc_html__('News Crawler', 'news-crawler'); ?>:</strong>
            <?php echo esc_html__('有効なライセンスキーが必要です。プラグインの機能が制限されています。', 'news-crawler'); ?>
            <a href="<?php echo admin_url('admin.php?page=news-crawler-license'); ?>" class="button button-small" style="margin-left: 10px;">
                <?php echo esc_html__('ライセンスを設定', 'news-crawler'); ?>
            </a>
        </p>
    </div>
    <?php
}

// プラグインコンポーネントの初期化
function news_crawler_init_components() {
    // ライセンス管理クラスの初期化（最初に実行）
    if (class_exists('NewsCrawler_License_Manager')) {
        $license_manager = NewsCrawler_License_Manager::get_instance();
        
        // ライセンスが無効な場合は通知を表示するが、メニューは表示する
        if (!$license_manager->is_license_valid()) {
            // ライセンス無効時の制限を適用
            add_action('admin_notices', 'news_crawler_license_restriction_notice');
            // returnを削除してメニュー登録を継続
        }
    }
    
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
    
    // 更新チェッククラスを初期化（WordPress標準のUpdate URIと併用）
    if (class_exists('NewsCrawlerUpdater')) {
        new NewsCrawlerUpdater();
    }
    
    // ライセンス管理クラスを初期化
    if (class_exists('NewsCrawler_License_Manager')) {
        NewsCrawler_License_Manager::get_instance();
    }
    
    // ライセンス設定クラスを初期化
    if (class_exists('NewsCrawler_License_Settings')) {
        NewsCrawler_License_Settings::get_instance();
    }
    
    // Cron設定クラスを初期化
    if (class_exists('NewsCrawlerCronSettings')) {
        new NewsCrawlerCronSettings();
    }
}
add_action('init', 'news_crawler_init', 5);

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
    if (!$post) {
        return;
    }
    
    // News Crawlerで作成された投稿かチェック
    $is_news_crawler_post = get_post_meta($post_id, '_news_crawler_created', true);
    if ($is_news_crawler_post) {
        // News Crawler用のメタデータを再設定
        update_post_meta($post_id, '_news_crawler_ready', true);
        update_post_meta($post_id, '_news_crawler_last_meta_update', current_time('mysql'));
        
        // 本来の投稿ステータスを取得して設定
        $intended_status = get_post_meta($post_id, '_news_crawler_intended_status', true);
        if ($intended_status && $intended_status !== 'draft' && $post->post_status === 'draft') {
            $update_data = array(
                'ID' => $post_id,
                'post_status' => $intended_status
            );
            
            $result = wp_update_post($update_data);
            if ($result) {
                error_log('NewsCrawler: メタデータ設定時に投稿ステータスを ' . $intended_status . ' に更新しました (ID: ' . $post_id . ')');
            } else {
                error_log('NewsCrawler: メタデータ設定時の投稿ステータス更新に失敗しました (ID: ' . $post_id . ')');
            }
        }
        
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
        add_action('wp_ajax_news_crawler_manual_run_news', array($this, 'manual_run_news'));
        add_action('wp_ajax_news_crawler_test_news_fetch', array($this, 'test_news_fetch'));
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
            'news_crawler_main',
            array('label_for' => 'max_articles')
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
            array('label_for' => 'max_articles')
        );
        
        add_settings_field(
            'news_sources',
            'ニュースソース',
            array($this, 'news_sources_callback'),
            'news-crawler',
            'news_crawler_main',
            array('label_for' => 'max_articles')
        );
        
        add_settings_field(
            'post_categories',
            '投稿カテゴリー',
            array($this, 'post_categories_callback'),
            'news-crawler',
            'news_crawler_main',
            array('label_for' => 'max_articles')
        );
        
        add_settings_field(
            'post_status',
            '投稿ステータス',
            array($this, 'post_status_callback'),
            'news-crawler',
            'news_crawler_main',
            array('label_for' => 'max_articles')
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
    
    public function post_categories_callback() {
        $options = get_option($this->option_name, array());
        $categories = isset($options['post_categories']) && !empty($options['post_categories']) ? $options['post_categories'] : array('blog');
        $categories_text = implode("\n", $categories);
        echo '<textarea id="news_post_categories" name="' . $this->option_name . '[post_categories]" rows="3" cols="50" placeholder="1行に1カテゴリー名を入力してください">' . esc_textarea($categories_text) . '</textarea>';
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
        echo '<select id="news_post_status" name="' . $this->option_name . '[post_status]">';
        foreach ($statuses as $value => $label) {
            echo '<option value="' . $value . '" ' . selected($value, $status, false) . '>' . $label . '</option>';
        }
        echo '</select>';
        echo '<p class="description">作成する投稿の初期ステータスを選択してください。</p>';
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
        
        // ニュース記事数の処理
        if (isset($input['max_articles'])) {
            if (is_numeric($input['max_articles']) || (is_string($input['max_articles']) && !empty(trim($input['max_articles'])))) {
                $max_articles = intval($input['max_articles']);
                $sanitized['max_articles'] = max(1, min(50, $max_articles));
            } else {
                $sanitized['max_articles'] = isset($existing_options['max_articles']) ? $existing_options['max_articles'] : 3;
            }
        } else {
            $sanitized['max_articles'] = isset($existing_options['max_articles']) ? $existing_options['max_articles'] : 3;
        }
        
        // ニュースソースの処理
        if (isset($input['news_sources'])) {
            if (is_array($input['news_sources'])) {
                $news_sources = array_map('trim', $input['news_sources']);
                $news_sources = array_filter($news_sources);
                $sanitized['news_sources'] = $news_sources;
            } elseif (is_string($input['news_sources']) && !empty(trim($input['news_sources']))) {
                $news_sources = explode("\n", $input['news_sources']);
                $news_sources = array_map('trim', $news_sources);
                $news_sources = array_filter($news_sources);
                $sanitized['news_sources'] = $news_sources;
            } else {
                $sanitized['news_sources'] = isset($existing_options['news_sources']) ? $existing_options['news_sources'] : array();
            }
        } else {
            $sanitized['news_sources'] = isset($existing_options['news_sources']) ? $existing_options['news_sources'] : array();
        }
        
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
        
        if (isset($input['post_status']) && is_string($input['post_status']) && !empty(trim($input['post_status']))) {
            $sanitized['post_status'] = sanitize_text_field($input['post_status']);
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
            
            <h2>ニュースクロール機能</h2>
            <p>設定したニュースソースからキーワードにマッチした記事を取得して、要約と共に投稿を作成します。</p>
            
            <h3>ニュースソースのテスト</h3>
            <button type="button" id="news-test-fetch" class="button button-secondary">ニュースソースをテスト</button>
            <div id="news-test-fetch-result" style="margin-top: 10px; white-space: pre-wrap; background: #f7f7f7; padding: 15px; border: 1px solid #ccc; border-radius: 4px; max-height: 300px; overflow-y: auto;"></div>
            
            <h3>ニュース投稿を作成</h3>
            <button type="button" id="news-manual-run" class="button button-primary">ニュース投稿を作成</button>
            <div id="news-manual-run-result" style="margin-top: 10px; white-space: pre-wrap; background: #f7f7f7; padding: 15px; border: 1px solid #ccc; border-radius: 4px; max-height: 400px; overflow-y: auto;"></div>
            
            <hr>
            
            <h2>YouTube動画クロール機能</h2>
            <p>設定したYouTubeチャンネルからキーワードにマッチした動画を取得して、動画の埋め込みと要約を含む投稿を作成します。</p>
            <button type="button" id="youtube-manual-run" class="button button-primary">動画投稿を作成</button>
            
            <div id="youtube-manual-run-result" style="margin-top: 10px; white-space: pre-wrap; background: #f7f7f7; padding: 15px; border: 1px solid #ccc; border-radius: 4px; max-height: 400px; overflow-y: auto;"></div>
            
            <hr>
            
            <h2>統計情報</h2>
            <h3>ニュース統計</h3>
            <?php $news_stats = $this->get_news_statistics(); ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>項目</th>
                        <th>数値</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>総ニュース投稿数</td>
                        <td><?php echo $news_stats['total_posts']; ?>件</td>
                    </tr>
                    <tr>
                        <td>今月のニュース投稿数</td>
                        <td><?php echo $news_stats['posts_this_month']; ?>件</td>
                    </tr>
                    <tr>
                        <td>重複スキップ数</td>
                        <td><?php echo $news_stats['duplicates_skipped']; ?>件</td>
                    </tr>
                    <tr>
                        <td>最後の実行日時</td>
                        <td><?php echo $news_stats['last_run']; ?></td>
                    </tr>
                </tbody>
            </table>
            
            <h3>YouTube統計</h3>
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
                // ニュースソースのテスト
                $('#news-test-fetch').click(function() {
                    var button = $(this);
                    var resultDiv = $('#news-test-fetch-result');
                    button.prop('disabled', true).text('テスト中...');
                    resultDiv.html('ニュースソースのテストを開始します...');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'news_crawler_test_news_fetch',
                            nonce: '<?php echo wp_create_nonce('news_crawler_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                resultDiv.html('<div class="notice notice-success"><p><strong>ニュースソーステスト結果:</strong><br>' + response.data.replace(/\n/g, '<br>') + '</p></div>');
                            } else {
                                resultDiv.html('<div class="notice notice-error"><p><strong>ニュースソーステストエラー:</strong><br>' + response.data + '</p></div>');
                            }
                        },
                        error: function(xhr, status, error) {
                            resultDiv.html('<div class="notice notice-error"><p><strong>エラーが発生しました:</strong><br>' + error + '</p></div>');
                        },
                        complete: function() {
                            button.prop('disabled', false).text('ニュースソースをテスト');
                        }
                    });
                });
                
                // ニュース投稿の作成
                $('#news-manual-run').click(function() {
                    var button = $(this);
                    var resultDiv = $('#news-manual-run-result');
                    button.prop('disabled', true).text('実行中...');
                    resultDiv.html('ニュースクロールと投稿作成を開始します...');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'news_crawler_manual_run_news',
                            nonce: '<?php echo wp_create_nonce('news_crawler_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                resultDiv.html('<div class="notice notice-success"><p><strong>ニュース投稿作成結果:</strong><br>' + response.data.replace(/\n/g, '<br>') + '</p></div>');
                            } else {
                                resultDiv.html('<div class="notice notice-error"><p><strong>ニュース投稿作成エラー:</strong><br>' + response.data + '</p></div>');
                            }
                        },
                        error: function(xhr, status, error) {
                            resultDiv.html('<div class="notice notice-error"><p><strong>エラーが発生しました:</strong><br>' + error + '</p></div>');
                        },
                        complete: function() {
                            button.prop('disabled', false).text('ニュース投稿を作成');
                        }
                    });
                });
                
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
    
    /**
     * ニュースクロールを実行
     */
    public function crawl_news() {
        $options = get_option($this->option_name, array());
        $news_sources = isset($options['news_sources']) && !empty($options['news_sources']) ? $options['news_sources'] : array();
        $keywords = isset($options['keywords']) && !empty($options['keywords']) ? $options['keywords'] : array('AI', 'テクノロジー', 'ビジネス', 'ニュース');
        $max_articles = isset($options['max_articles']) && !empty($options['max_articles']) ? $options['max_articles'] : 3;
        $categories = isset($options['post_categories']) && !empty($options['post_categories']) ? $options['post_categories'] : array('blog');
        $status = isset($options['post_status']) && !empty($options['post_status']) ? $options['post_status'] : 'draft';
        
        if (empty($news_sources)) {
            return 'ニュースソースが設定されていません。';
        }
        
        $matched_articles = array();
        $errors = array();
        $duplicates_skipped = 0;
        $debug_info = array();
        
        foreach ($news_sources as $source) {
            try {
                $articles = $this->fetch_news_articles($source, 20);
                if ($articles && is_array($articles)) {
                    $debug_info[] = $source . ': ' . count($articles) . '件の記事を取得';
                    foreach ($articles as $article) {
                        if ($this->is_news_keyword_match($article, $keywords)) {
                            $matched_articles[] = $article;
                            $debug_info[] = '  - キーワードマッチ: ' . $article['title'];
                        } else {
                            $debug_info[] = '  - キーワードマッチなし: ' . $article['title'];
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
            
            if ($this->is_duplicate_news($article)) {
                $duplicates_skipped++;
                $debug_info[] = "    → 重複のためスキップ";
                continue;
            }
            
            $debug_info[] = "    → 有効記事として追加";
            $valid_articles[] = $article;
        }
        
        $valid_articles = array_slice($valid_articles, 0, $max_articles);
        
        $posts_created = 0;
        $post_id = null;
        if (!empty($valid_articles)) {
            $post_id = $this->create_news_summary_post($valid_articles, $categories, $status);
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
        
        $result = $posts_created . '件のニュース投稿を作成しました（' . count($valid_articles) . '件の記事を含む）。';
        $result .= "\n投稿ID: " . ($post_id ?? 'なし');
        if ($duplicates_skipped > 0) $result .= "\n重複スキップ: " . $duplicates_skipped . '件';
        if (!empty($errors)) $result .= "\nエラー: " . implode(', ', $errors);
        
        $result .= "\n\n=== デバッグ情報 ===\n" . implode("\n", $debug_info);
        
        $this->update_news_statistics($posts_created, $duplicates_skipped);
        
        return $result;
    }
    
    /**
     * オプション指定でニュースクロールを実行
     */
    public function crawl_news_with_options($options) {
        $news_sources = isset($options['news_sources']) && !empty($options['news_sources']) ? $options['news_sources'] : array();
        $keywords = isset($options['keywords']) && !empty($options['keywords']) ? $options['keywords'] : array('AI', 'テクノロジー', 'ビジネス', 'ニュース');
        $max_articles = isset($options['max_articles']) && !empty($options['max_articles']) ? $options['max_articles'] : 3;
        $categories = isset($options['post_categories']) && !empty($options['post_categories']) ? $options['post_categories'] : array('blog');
        $status = isset($options['post_status']) && !empty($options['post_status']) ? $options['post_status'] : 'draft';
        
        if (empty($news_sources)) {
            return 'ニュースソースが設定されていません。';
        }
        
        $matched_articles = array();
        $errors = array();
        $duplicates_skipped = 0;
        $debug_info = array();
        
        foreach ($news_sources as $source) {
            try {
                $articles = $this->fetch_news_articles($source, 20);
                if ($articles && is_array($articles)) {
                    $debug_info[] = $source . ': ' . count($articles) . '件の記事を取得';
                    foreach ($articles as $article) {
                        if ($this->is_news_keyword_match($article, $keywords)) {
                            $matched_articles[] = $article;
                            $debug_info[] = '  - キーワードマッチ: ' . $article['title'];
                        } else {
                            $debug_info[] = '  - キーワードマッチなし: ' . $article['title'];
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
            
            if ($this->is_duplicate_news($article)) {
                $duplicates_skipped++;
                $debug_info[] = "    → 重複のためスキップ";
                continue;
            }
            
            $debug_info[] = "    → 有効記事として追加";
            $valid_articles[] = $article;
        }
        
        $valid_articles = array_slice($valid_articles, 0, $max_articles);
        
        $posts_created = 0;
        $post_id = null;
        if (!empty($valid_articles)) {
            $post_id = $this->create_news_summary_post($valid_articles, $categories, $status);
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
        
        $result = $posts_created . '件のニュース投稿を作成しました（' . count($valid_articles) . '件の記事を含む）。';
        $result .= "\n投稿ID: " . ($post_id ?? 'なし');
        if ($duplicates_skipped > 0) $result .= "\n重複スキップ: " . $duplicates_skipped . '件';
        if (!empty($errors)) $result .= "\nエラー: " . implode(', ', $errors);
        
        $result .= "\n\n=== デバッグ情報 ===\n" . implode("\n", $debug_info);
        
        $this->update_news_statistics($posts_created, $duplicates_skipped);
        
        return $result;
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
    
    /**
     * ニュースソースのテスト実行
     */
    public function test_news_fetch() {
        check_ajax_referer('news_crawler_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('権限がありません');
        }
        
        $options = get_option($this->option_name, array());
        $news_sources = isset($options['news_sources']) && !empty($options['news_sources']) ? $options['news_sources'] : array();
        
        if (empty($news_sources)) {
            wp_send_json_success('ニュースソースが設定されていません。');
        }
        
        $test_result = array();
        foreach ($news_sources as $source) {
            try {
                $articles = $this->fetch_news_articles($source, 3);
                if ($articles && is_array($articles)) {
                    $test_result[] = $source . ': 取得成功 (' . count($articles) . '件の記事)';
                    foreach (array_slice($articles, 0, 2) as $article) {
                        $test_result[] = '  - ' . $article['title'];
                    }
                } else {
                    $test_result[] = $source . ': 取得失敗';
                }
            } catch (Exception $e) {
                $test_result[] = $source . ': エラー - ' . $e->getMessage();
            }
        }
        
        wp_send_json_success(implode('<br>', $test_result));
    }
    
    /**
     * ニュースクロールの手動実行
     */
    public function manual_run_news() {
        check_ajax_referer('news_crawler_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('権限がありません');
        }
        
        // 新しいジャンル設定システムを使用
        $genre_settings = new NewsCrawlerGenreSettings();
        $result = $genre_settings->manual_run_news();
        wp_send_json_success($result);
    }
    
    public function crawl_youtube() {
        $options = get_option($this->option_name, array());
        $channels = isset($options['channels']) && !empty($options['channels']) ? $options['channels'] : array();
        $keywords = isset($options['keywords']) && !empty($options['keywords']) ? $options['keywords'] : array('AI', 'テクノロジー', 'ビジネス', 'ニュース');
        $max_videos = isset($options['max_videos']) && !empty($options['max_videos']) ? $options['max_videos'] : 5;
        $categories = isset($options['post_categories']) && !empty($options['post_categories']) ? $options['post_categories'] : array('blog');
        $status = isset($options['post_status']) && !empty($options['post_status']) ? $options['post_status'] : 'draft';

        // 設定情報のデバッグ出力
        error_log('NewsCrawler: YouTubeクロール開始');
        error_log('NewsCrawler: 設定されたチャンネル: ' . implode(', ', $channels));
        error_log('NewsCrawler: 設定されたキーワード: ' . implode(', ', $keywords));
        error_log('NewsCrawler: 最大動画数: ' . $max_videos);

        if (empty($channels)) {
            error_log('NewsCrawler: YouTubeチャンネルが設定されていません');
            return 'YouTubeチャンネルが設定されていません。設定画面でチャンネルIDを追加してください。';
        }

        if (empty($this->api_key)) {
            error_log('NewsCrawler: YouTube APIキーが設定されていません');
            return 'YouTube APIキーが設定されていません。Google Cloud ConsoleでAPIキーを取得して設定してください。';
        }

        $matched_videos = array();
        $errors = array();
        $duplicates_skipped = 0;
        $debug_info = array();

        foreach ($channels as $channel) {
            try {
                error_log('NewsCrawler: チャンネル ' . $channel . ' から動画を取得開始');
                $videos = $this->fetch_channel_videos($channel, 20);

                if ($videos && is_array($videos)) {
                    $debug_info[] = $channel . ': ' . count($videos) . '件の動画を取得';
                    error_log('NewsCrawler: ' . $channel . ' から ' . count($videos) . '件の動画を取得');

                    foreach ($videos as $video) {
                        error_log('NewsCrawler: 動画チェック: ' . $video['title']);
                        if ($this->is_keyword_match($video, $keywords)) {
                            $matched_videos[] = $video;
                            $debug_info[] = '  - キーワードマッチ: ' . $video['title'];
                            error_log('NewsCrawler: キーワードマッチ成功: ' . $video['title']);
                        } else {
                            $debug_info[] = '  - キーワードマッチなし: ' . $video['title'];
                            error_log('NewsCrawler: キーワードマッチ失敗: ' . $video['title']);
                        }
                    }
                } else {
                    $debug_info[] = $channel . ': 動画を取得できませんでした';
                    error_log('NewsCrawler: ' . $channel . ' から動画を取得できませんでした');
                }
            } catch (Exception $e) {
                $error_msg = $channel . ': ' . $e->getMessage();
                $errors[] = $error_msg;
                $debug_info[] = $error_msg;
                error_log('NewsCrawler: チャンネル ' . $channel . ' の処理でエラー: ' . $e->getMessage());
            }
        }

        $debug_info[] = "\nキーワードマッチした動画数: " . count($matched_videos);
        error_log('NewsCrawler: キーワードマッチした動画数: ' . count($matched_videos));

        $valid_videos = array();
        foreach ($matched_videos as $video) {
            $debug_info[] = "  - 動画: " . $video['title'];

            if ($this->is_duplicate_video($video)) {
                $duplicates_skipped++;
                $debug_info[] = "    → 重複のためスキップ";
                error_log('NewsCrawler: 重複動画をスキップ: ' . $video['title']);
                continue;
            }

            $debug_info[] = "    → 有効動画として追加";
            $valid_videos[] = $video;
            error_log('NewsCrawler: 有効動画として追加: ' . $video['title']);
        }

        $valid_videos = array_slice($valid_videos, 0, $max_videos);
        error_log('NewsCrawler: 最終的に処理する動画数: ' . count($valid_videos));

        $posts_created = 0;
        $post_id = null;
        if (!empty($valid_videos)) {
            error_log('NewsCrawler: 動画投稿作成開始');
            $post_id = $this->create_video_summary_post($valid_videos, $categories, $status);
            if ($post_id && !is_wp_error($post_id)) {
                $posts_created = 1;
                $debug_info[] = "\n投稿作成成功: 投稿ID " . $post_id;
                error_log('NewsCrawler: 動画投稿作成成功: 投稿ID ' . $post_id);
            } else {
                $error_message = is_wp_error($post_id) ? $post_id->get_error_message() : '不明なエラー';
                $debug_info[] = "\n投稿作成失敗: " . $error_message;
                error_log('NewsCrawler: 動画投稿作成失敗: ' . $error_message);
            }
        } else {
            $debug_info[] = "\n有効な動画がないため投稿を作成しませんでした";
            error_log('NewsCrawler: 有効な動画がないため投稿を作成しませんでした');
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

        // タイトルは一時的に【ジャンル名】以降はＡＩで生成（SEOタイトル生成で上書き）
        $genre_name_for_title = 'ニュース';
        $current_genre_setting = get_transient('news_crawler_current_genre_setting');
        if ($current_genre_setting && isset($current_genre_setting['genre_name']) && !empty($current_genre_setting['genre_name'])) {
            $genre_name_for_title = $current_genre_setting['genre_name'];
        }
        $post_title = '【' . $genre_name_for_title . '】以降はＡＩで生成';

        $post_content = '';

        foreach ($videos as $video) {
            // 動画区切り
            $post_content .= '<!-- wp:separator -->';
            $post_content .= '<hr class="wp-block-separator has-alpha-channel-opacity"/>';
            $post_content .= '<!-- /wp:separator -->';

            // 動画タイトル
            $post_content .= '<!-- wp:heading -->';
            $post_content .= '<h2>' . esc_html($video['title']) . '</h2>';
            $post_content .= '<!-- /wp:heading -->';

            // 動画の埋め込み
            $youtube_url = 'https://www.youtube.com/watch?v=' . esc_attr($video['video_id']);

            if ($embed_type === 'responsive' || $embed_type === 'classic') {
                // WordPress標準のYouTube埋め込みブロック（シンプル版）
                $post_content .= '<!-- wp:embed {"url":"' . esc_url($youtube_url) . '","type":"video","providerNameSlug":"youtube","responsive":true} -->';
                $post_content .= '<figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube">';
                $post_content .= '<div class="wp-block-embed__wrapper">';
                $post_content .= esc_url($youtube_url);
                $post_content .= '</div></figure>';
                $post_content .= '<!-- /wp:embed -->';
            } else {
                // ミニマル埋め込み（リンクのみ）
                $post_content .= '<!-- wp:paragraph -->';
                $post_content .= '<p><a href="' . esc_url($youtube_url) . '" target="_blank" rel="noopener noreferrer"><strong>📺 YouTubeで視聴する</strong></a></p>';
                $post_content .= '<!-- /wp:paragraph -->';
            }

            // 動画説明
            if (!empty($video['description'])) {
                $post_content .= '<!-- wp:paragraph -->';
                $post_content .= '<p>' . esc_html(wp_trim_words($video['description'], 100, '...')) . '</p>';
                $post_content .= '<!-- /wp:paragraph -->';
            }

            // メタ情報
            $meta_info = array();

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
                $post_content .= '<!-- wp:paragraph -->';
                $post_content .= '<p><small>' . implode(' | ', $meta_info) . '</small></p>';
                $post_content .= '<!-- /wp:paragraph -->';
            }
        }

        // 仕上げの全体サニタイズ（念のため）
        $post_content = $this->sanitize_post_content($post_content);

        // 投稿作成者を決定（基本設定のデフォルト投稿者を優先）
        $basic_settings = get_option('news_crawler_basic_settings', array());
        $author_id = 0;
        if (isset($basic_settings['default_post_author'])) {
            $candidate = intval($basic_settings['default_post_author']);
            if ($candidate > 0 && get_user_by('id', $candidate)) {
                $author_id = $candidate;
            }
        }
        if (!$author_id) {
            $author_id = get_current_user_id() ?: 1;
        }

        // 設定されたステータスで直接投稿を作成
        $post_data = array(
            'post_title'    => $post_title,
            'post_content'  => $post_content,
            'post_status'   => $status, // 設定されたステータスで直接作成
            'post_author'   => $author_id,
            'post_type'     => 'post',
            'post_category' => $cat_ids
        );

        // 予約投稿の場合、日時を設定
        if ($status === 'future') {
            $basic_settings = get_option('news_crawler_basic_settings', array());
            $scheduled_time = isset($basic_settings['scheduled_publish_time']) ? $basic_settings['scheduled_publish_time'] : '09:00';

            // 今日の日付に予約時間を設定
            $publish_date = date('Y-m-d') . ' ' . $scheduled_time . ':00';
            $post_data['post_date'] = $publish_date;
            $post_data['post_date_gmt'] = get_gmt_from_date($publish_date);
        }

        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // メタデータの保存
        update_post_meta($post_id, '_youtube_summary', true);
        update_post_meta($post_id, '_youtube_videos_count', count($videos));
        update_post_meta($post_id, '_youtube_crawled_date', current_time('mysql'));
        update_post_meta($post_id, '_news_crawler_created', true);
        update_post_meta($post_id, '_news_crawler_creation_method', 'youtube');
        update_post_meta($post_id, '_news_crawler_intended_status', $status);
        update_post_meta($post_id, '_news_crawler_creation_timestamp', current_time('timestamp'));
        update_post_meta($post_id, '_news_crawler_ready', true); // ステータス変更不要のためtrueに設定

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

        // アイキャッチ生成
        $featured_result = $this->maybe_generate_featured_image($post_id, $post_title, $keywords);

        // AI要約生成
        if (class_exists('NewsCrawlerOpenAISummarizer')) {
            $basic_settings = get_option('news_crawler_basic_settings', array());
            $auto_summary_enabled = isset($basic_settings['auto_summary_generation']) ? $basic_settings['auto_summary_generation'] : true;

            if ($auto_summary_enabled) {
                error_log('NewsCrawler: AI要約生成を実行します (YouTube投稿ID: ' . $post_id . ')');
                $summarizer = new NewsCrawlerOpenAISummarizer();
                $summarizer->generate_summary($post_id);
            } else {
                error_log('NewsCrawler: AI要約生成が無効のためスキップします (YouTube投稿ID: ' . $post_id . ')');
            }
        }

        error_log('NewsCrawler: YouTube投稿を ' . $status . ' ステータスで正常に作成しました (ID: ' . $post_id . ')');

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

        // YouTube APIエラーのチェック
        if (isset($data['error'])) {
            $error_code = $data['error']['code'] ?? 0;
            $error_message = $data['error']['message'] ?? '不明なエラー';

            // クォータ超過エラーの場合
            if ($error_code === 403) {
                if (strpos($error_message, 'quota') !== false || strpos($error_message, 'limit') !== false) {
                    throw new Exception('YouTube APIのクォータ（利用制限）を超過しています。24時間後に再試行してください。');
                } elseif (strpos($error_message, 'disabled') !== false) {
                    throw new Exception('YouTube APIが無効化されています。Google Cloud ConsoleでAPIを有効化してください。');
                } else {
                    throw new Exception('YouTube APIアクセスが拒否されました: ' . $error_message);
                }
            }
            // APIキーが無効の場合
            elseif ($error_code === 400) {
                throw new Exception('YouTube APIキーが無効です。正しいAPIキーを設定してください。');
            }
            // その他のエラー
            else {
                throw new Exception('YouTube APIエラー (' . $error_code . '): ' . $error_message);
            }
        }

        if (!$data || !isset($data['items'])) {
            throw new Exception('APIレスポンスの解析に失敗しました。チャンネルIDが正しいか確認してください。');
        }

        $videos = array();
        foreach ($data['items'] as $item) {
            $snippet = $item['snippet'];
            $video_id = $item['id']['videoId'];

            // 動画の詳細情報を取得（エラーハンドリング付き）
            $video_details = array();
            try {
                $video_details = $this->fetch_video_details($video_id);
            } catch (Exception $e) {
                error_log('NewsCrawler: 動画詳細取得エラー (' . $video_id . '): ' . $e->getMessage());
                // 詳細取得に失敗しても動画情報は保持
            }

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
    
    private function get_news_statistics() {
        global $wpdb;
        $stats = array();
        $stats['total_posts'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_news_summary'");
        $stats['posts_this_month'] = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_news_crawled_date' AND meta_value >= %s", date('Y-m-01')));
        $stats['duplicates_skipped'] = get_option('news_crawler_duplicates_skipped', 0);
        $stats['last_run'] = get_option('news_crawler_last_run', '未実行');
        return $stats;
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
            error_log('NewsCrawler: Featured image generation skipped - not enabled in genre setting');
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
    
    /**
     * ニュース記事を取得
     */
    private function fetch_news_articles($source, $max_results = 20) {
        // RSSフィードかどうかを判定
        if ($this->is_rss_feed($source)) {
            return $this->fetch_rss_articles($source, $max_results);
        } else {
            // ニュース記事タイプかどうかを判定
            if ($this->is_news_article_type($source)) {
                return $this->fetch_news_articles_deep($source, $max_results);
            } else {
                return $this->fetch_html_articles($source, $max_results);
            }
        }
    }
    
    /**
     * RSSフィードかどうかを判定
     */
    private function is_rss_feed($url) {
        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        return strpos($body, '<rss') !== false || strpos($body, '<feed') !== false;
    }
    
    /**
     * ニュース記事タイプかどうかを判定
     */
    private function is_news_article_type($url) {
        // URLパターンでニュース記事タイプを判定
        $news_patterns = array(
            '/\/news\//',
            '/\/article\//',
            '/\/post\//',
            '/\/blog\//',
            '/\/press\//',
            '/\/release\//',
            '/\/info\//',
            '/\/information\//',
            '/\/announcement\//',
            '/\/update\//',
            '/\/report\//'
        );
        
        foreach ($news_patterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return true;
            }
        }
        
        // HTMLコンテンツでニュース記事タイプを判定
        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'sslverify' => false,
            'user-agent' => 'News Crawler Plugin/1.0'
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        
        // ニュース記事の特徴的な要素をチェック
        $news_indicators = array(
            'article',
            'news-item',
            'post-content',
            'entry-content',
            'news-content',
            'article-content',
            'news-list',
            'article-list',
            'post-list',
            'entry-list',
            'news-archive',
            'article-archive'
        );
        
        foreach ($news_indicators as $indicator) {
            if (strpos($body, $indicator) !== false) {
                return true;
            }
        }
        
        // より詳細な判定：記事リンクの存在をチェック
        $article_link_patterns = array(
            '/<a[^>]+href=["\'][^"\']*\/news\/[^"\']*["\'][^>]*>/i',
            '/<a[^>]+href=["\'][^"\']*\/article\/[^"\']*["\'][^>]*>/i',
            '/<a[^>]+href=["\'][^"\']*\/post\/[^"\']*["\'][^>]*>/i',
            '/<a[^>]+href=["\'][^"\']*\/blog\/[^"\']*["\'][^>]*>/i'
        );
        
        foreach ($article_link_patterns as $pattern) {
            if (preg_match($pattern, $body)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * ニュース記事の深層クロール
     */
    private function fetch_news_articles_deep($source_url, $max_results = 20) {
        $articles = array();
        
        try {
            // ニュースソースページから最新記事のリンクを取得
            $article_links = $this->extract_article_links($source_url, $max_results);
            
            if (empty($article_links)) {
                error_log('NewsCrawler: 記事リンクが見つかりませんでした: ' . $source_url);
                return $articles;
            }
            
            error_log('NewsCrawler: ' . count($article_links) . '件の記事リンクを取得しました');
            
            // 各記事ページから詳細コンテンツを取得（並列処理を避けて順次処理）
            $processed_count = 0;
            foreach ($article_links as $index => $link) {
                // 処理時間をチェック（4分を超えたら中断）
                if (time() - $_SERVER['REQUEST_TIME'] > 240) {
                    error_log('NewsCrawler: 処理時間制限に達したため中断: ' . $processed_count . '件処理済み');
                    break;
                }
                
                error_log('NewsCrawler: 記事処理中 (' . ($index + 1) . '/' . count($article_links) . '): ' . $link);
                
                $article_data = $this->fetch_individual_article($link);
                if ($article_data) {
                    $articles[] = $article_data;
                    $processed_count++;
                    error_log('NewsCrawler: 記事取得成功: ' . $article_data['title']);
                } else {
                    error_log('NewsCrawler: 記事取得失敗: ' . $link);
                }
                
                // 最大記事数に達したら終了
                if (count($articles) >= $max_results) {
                    break;
                }
                
                // 処理間隔を空ける（サーバー負荷軽減）
                usleep(500000); // 0.5秒待機
            }
            
            error_log('NewsCrawler: 深層クロール完了: ' . count($articles) . '件の記事を取得');
            
        } catch (Exception $e) {
            error_log('NewsCrawler: 深層クロールエラー: ' . $e->getMessage());
        }
        
        return $articles;
    }
    
    /**
     * ニュースソースから記事リンクを抽出
     */
    private function extract_article_links($source_url, $max_links = 20) {
        $response = wp_remote_get($source_url, array(
            'timeout' => 20, // タイムアウトを短縮
            'sslverify' => false,
            'user-agent' => 'News Crawler Plugin/1.0',
            'redirection' => 3, // リダイレクト回数を制限
            'httpversion' => '1.1'
        ));
        
        if (is_wp_error($response)) {
            error_log('NewsCrawler: ソースページ取得エラー: ' . $response->get_error_message() . ' - ' . $source_url);
            throw new Exception('ニュースソースページの取得に失敗しました: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $links = array();
        
        // より包括的な記事リンクのパターンを検索
        $patterns = array(
            // 一般的なニュース記事パターン
            '/<a[^>]+href=["\']([^"\']*\/news\/[^"\']*)["\'][^>]*>/i',
            '/<a[^>]+href=["\']([^"\']*\/article\/[^"\']*)["\'][^>]*>/i',
            '/<a[^>]+href=["\']([^"\']*\/post\/[^"\']*)["\'][^>]*>/i',
            '/<a[^>]+href=["\']([^"\']*\/blog\/[^"\']*)["\'][^>]*>/i',
            '/<a[^>]+href=["\']([^"\']*\/info\/[^"\']*)["\'][^>]*>/i',
            '/<a[^>]+href=["\']([^"\']*\/information\/[^"\']*)["\'][^>]*>/i',
            
            // 数字付き記事パターン（n4909のような）
            '/<a[^>]+href=["\']([^"\']*\/news\/n\d+[^"\']*)["\'][^>]*>/i',
            '/<a[^>]+href=["\']([^"\']*\/article\/\d+[^"\']*)["\'][^>]*>/i',
            '/<a[^>]+href=["\']([^"\']*\/post\/\d+[^"\']*)["\'][^>]*>/i',
            '/<a[^>]+href=["\']([^"\']*\/blog\/\d+[^"\']*)["\'][^>]*>/i',
            '/<a[^>]+href=["\']([^"\']*\/info\/\d+[^"\']*)["\'][^>]*>/i',
            
            // 日付付き記事パターン
            '/<a[^>]+href=["\']([^"\']*\/news\/\d{4}\/\d{2}\/\d{2}[^"\']*)["\'][^>]*>/i',
            '/<a[^>]+href=["\']([^"\']*\/\d{4}\/\d{2}\/\d{2}[^"\']*)["\'][^>]*>/i',
            '/<a[^>]+href=["\']([^"\']*\/\d{4}\/\d{2}[^"\']*)["\'][^>]*>/i',
            
            // カテゴリ付き記事パターン
            '/<a[^>]+href=["\']([^"\']*\/news\/cat\/[^"\']*)["\'][^>]*>/i',
            '/<a[^>]+href=["\']([^"\']*\/article\/cat\/[^"\']*)["\'][^>]*>/i',
            
            // より汎用的なパターン（ニュース関連のリンク）
            '/<a[^>]+href=["\']([^"\']*\/news\/[^"\']*\/[^"\']*)["\'][^>]*>/i',
            '/<a[^>]+href=["\']([^"\']*\/article\/[^"\']*\/[^"\']*)["\'][^>]*>/i',
            '/<a[^>]+href=["\']([^"\']*\/post\/[^"\']*\/[^"\']*)["\'][^>]*>/i',
            
            // より柔軟なパターン（任意の文字列を含む記事リンク）
            '/<a[^>]+href=["\']([^"\']*\/news\/[^"\']*[^\/]+\/[^"\']*)["\'][^>]*>/i',
            '/<a[^>]+href=["\']([^"\']*\/article\/[^"\']*[^\/]+\/[^"\']*)["\'][^>]*>/i',
            '/<a[^>]+href=["\']([^"\']*\/post\/[^"\']*[^\/]+\/[^"\']*)["\'][^>]*>/i',
            
            // 数字のみの記事IDパターン
            '/<a[^>]+href=["\']([^"\']*\/news\/\d+[^"\']*)["\'][^>]*>/i',
            '/<a[^>]+href=["\']([^"\']*\/article\/\d+[^"\']*)["\'][^>]*>/i',
            '/<a[^>]+href=["\']([^"\']*\/post\/\d+[^"\']*)["\'][^>]*>/i'
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $body, $matches)) {
                foreach ($matches[1] as $link) {
                    // 相対URLを絶対URLに変換
                    $absolute_link = $this->make_absolute_url($link, $source_url);
                    if ($absolute_link && !in_array($absolute_link, $links)) {
                        // カテゴリページや一覧ページを除外
                        if (!$this->is_category_or_list_page($absolute_link)) {
                            $links[] = $absolute_link;
                        }
                    }
                }
            }
        }
        
        // リンクを優先度順にソート（数字付き記事を優先）
        usort($links, array($this, 'sort_article_links'));
        
        // 最大リンク数に制限
        return array_slice($links, 0, $max_links);
    }
    
    /**
     * カテゴリページや一覧ページかどうかを判定
     */
    private function is_category_or_list_page($url) {
        $exclude_patterns = array(
            '/\/cat\//',
            '/\/category\//',
            '/\/archive\//',
            '/\/list\//',
            '/\/index\//',
            '/\/page\//',
            '/\/tag\//',
            '/\/author\//',
            '/\/search\//',
            '/\/feed\//',
            '/\/rss\//',
            '/\/sitemap\//'
        );
        
        foreach ($exclude_patterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 記事リンクを優先度順にソート
     */
    private function sort_article_links($a, $b) {
        // 数字付き記事（n4909など）を優先
        $a_has_number = preg_match('/\/n\d+|\/\d+[^\/]*$/', $a);
        $b_has_number = preg_match('/\/n\d+|\/\d+[^\/]*$/', $b);
        
        if ($a_has_number && !$b_has_number) return -1;
        if (!$a_has_number && $b_has_number) return 1;
        
        // 日付付き記事を次に優先
        $a_has_date = preg_match('/\/\d{4}\/\d{2}\/\d{2}/', $a);
        $b_has_date = preg_match('/\/\d{4}\/\d{2}\/\d{2}/', $b);
        
        if ($a_has_date && !$b_has_date) return -1;
        if (!$a_has_date && $b_has_date) return 1;
        
        return 0;
    }
    
    /**
     * 相対URLを絶対URLに変換
     */
    private function make_absolute_url($url, $base_url) {
        // 既に絶対URLの場合はそのまま返す
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }
        
        // ベースURLからドメインとパスを取得
        $parsed_base = parse_url($base_url);
        $base_domain = $parsed_base['scheme'] . '://' . $parsed_base['host'];
        
        // 相対URLを絶対URLに変換
        if (strpos($url, '/') === 0) {
            return $base_domain . $url;
        } else {
            return $base_domain . '/' . $url;
        }
    }
    
    /**
     * 個別記事ページから詳細コンテンツを取得
     */
    private function fetch_individual_article($article_url) {
        $response = wp_remote_get($article_url, array(
            'timeout' => 15, // タイムアウトを短縮
            'sslverify' => false,
            'user-agent' => 'News Crawler Plugin/1.0',
            'redirection' => 3, // リダイレクト回数を制限
            'httpversion' => '1.1'
        ));
        
        if (is_wp_error($response)) {
            error_log('NewsCrawler: 記事取得エラー: ' . $response->get_error_message() . ' - ' . $article_url);
            return null;
        }
        
        $body = wp_remote_retrieve_body($response);
        
        // タイトルを取得（複数のパターンを試行）
        $title = $this->extract_title($body);
        
        // 本文を取得（より包括的なパターンで試行）
        $content = $this->extract_article_content($body);
        
        // メタディスクリプションを取得
        $description = $this->extract_description($body, $content);
        
        // 公開日を取得
        $published_at = $this->extract_published_date($body);
        
        // 著者情報を取得
        $author = $this->extract_author($body);
        
        // カテゴリ情報を取得
        $categories = $this->extract_categories($body);
        
        return array(
            'title' => $title,
            'content' => $content,
            'description' => $description,
            'url' => $article_url,
            'published_at' => $published_at,
            'author' => $author,
            'source' => $article_url,
            'categories' => $categories,
            'guid' => md5($article_url),
            'excerpt' => wp_trim_words(strip_tags($content), 100, '...')
        );
    }
    
    /**
     * タイトルを抽出
     */
    private function extract_title($body) {
        $title_patterns = array(
            '/<title[^>]*>(.*?)<\/title>/i',
            '/<h1[^>]*>(.*?)<\/h1>/i',
            '/<meta[^>]*property=["\']og:title["\'][^>]*content=["\']([^"\']*)["\']/i',
            '/<meta[^>]*name=["\']title["\'][^>]*content=["\']([^"\']*)["\']/i'
        );
        
        foreach ($title_patterns as $pattern) {
            if (preg_match($pattern, $body, $matches)) {
                $title = trim(strip_tags($matches[1]));
                if (!empty($title)) {
                    return $title;
                }
            }
        }
        
        return 'タイトルなし';
    }
    
    /**
     * 記事本文を抽出（強化版）
     */
    private function extract_article_content($body) {
        $content_patterns = array(
            // セマンティックな要素を優先
            '/<article[^>]*>(.*?)<\/article>/is',
            '/<main[^>]*>(.*?)<\/main>/is',
            
            // クラス名ベースのパターン（より詳細）
            '/<div[^>]*class=["\'][^"\']*content[^"\']*["\'][^>]*>(.*?)<\/div>/is',
            '/<div[^>]*class=["\'][^"\']*post-content[^"\']*["\'][^>]*>(.*?)<\/div>/is',
            '/<div[^>]*class=["\'][^"\']*entry-content[^"\']*["\'][^>]*>(.*?)<\/div>/is',
            '/<div[^>]*class=["\'][^"\']*news-content[^"\']*["\'][^>]*>(.*?)<\/div>/is',
            '/<div[^>]*class=["\'][^"\']*article-content[^"\']*["\'][^>]*>(.*?)<\/div>/is',
            '/<div[^>]*class=["\'][^"\']*post-body[^"\']*["\'][^>]*>(.*?)<\/div>/is',
            '/<div[^>]*class=["\'][^"\']*entry-body[^"\']*["\'][^>]*>(.*?)<\/div>/is',
            '/<div[^>]*class=["\'][^"\']*text[^"\']*["\'][^>]*>(.*?)<\/div>/is',
            
            // IDベースのパターン
            '/<div[^>]*id=["\'][^"\']*content[^"\']*["\'][^>]*>(.*?)<\/div>/is',
            '/<div[^>]*id=["\'][^"\']*post-content[^"\']*["\'][^>]*>(.*?)<\/div>/is',
            '/<div[^>]*id=["\'][^"\']*entry-content[^"\']*["\'][^>]*>(.*?)<\/div>/is',
            
            // より汎用的なパターン
            '/<div[^>]*class=["\'][^"\']*[^"\']*content[^"\']*["\'][^>]*>(.*?)<\/div>/is',
            '/<div[^>]*class=["\'][^"\']*[^"\']*post[^"\']*["\'][^>]*>(.*?)<\/div>/is',
            '/<div[^>]*class=["\'][^"\']*[^"\']*article[^"\']*["\'][^>]*>(.*?)<\/div>/is'
        );
        
        $best_content = '';
        $max_length = 0;
        
        foreach ($content_patterns as $pattern) {
            if (preg_match_all($pattern, $body, $matches)) {
                foreach ($matches[1] as $match) {
                    $cleaned_content = $this->clean_article_content($match);
                    $content_length = strlen(strip_tags($cleaned_content));
                    
                    // より長いコンテンツを優先
                    if ($content_length > $max_length && $content_length > 100) {
                        $best_content = $cleaned_content;
                        $max_length = $content_length;
                    }
                }
            }
        }
        
        // パターンマッチで見つからない場合は、段落タグから抽出
        if (empty($best_content)) {
            $best_content = $this->extract_content_from_paragraphs($body);
        }
        
        return $best_content;
    }
    
    /**
     * 段落タグからコンテンツを抽出
     */
    private function extract_content_from_paragraphs($body) {
        $content = '';
        
        // 複数の段落を結合
        if (preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $body, $matches)) {
            $paragraphs = array();
            foreach ($matches[1] as $paragraph) {
                $cleaned = $this->clean_article_content($paragraph);
                if (strlen(strip_tags($cleaned)) > 50) { // 短すぎる段落は除外
                    $paragraphs[] = $cleaned;
                }
            }
            $content = implode("\n\n", $paragraphs);
        }
        
        return $content;
    }
    
    /**
     * 説明文を抽出
     */
    private function extract_description($body, $content) {
        $description_patterns = array(
            '/<meta[^>]*name=["\']description["\'][^>]*content=["\']([^"\']*)["\']/i',
            '/<meta[^>]*property=["\']og:description["\'][^>]*content=["\']([^"\']*)["\']/i',
            '/<meta[^>]*name=["\']twitter:description["\'][^>]*content=["\']([^"\']*)["\']/i'
        );
        
        foreach ($description_patterns as $pattern) {
            if (preg_match($pattern, $body, $matches)) {
                $description = trim($matches[1]);
                if (!empty($description)) {
                    return $description;
                }
            }
        }
        
        // メタディスクリプションが見つからない場合は、コンテンツから生成
        return wp_trim_words(strip_tags($content), 100, '...');
    }
    
    /**
     * 公開日を抽出
     */
    private function extract_published_date($body) {
        $date_patterns = array(
            '/<time[^>]*datetime=["\']([^"\']*)["\'][^>]*>/i',
            '/<meta[^>]*property=["\']article:published_time["\'][^>]*content=["\']([^"\']*)["\']/i',
            '/<meta[^>]*name=["\']date["\'][^>]*content=["\']([^"\']*)["\']/i',
            '/<meta[^>]*name=["\']pubdate["\'][^>]*content=["\']([^"\']*)["\']/i'
        );
        
        foreach ($date_patterns as $pattern) {
            if (preg_match($pattern, $body, $matches)) {
                $date = strtotime($matches[1]);
                if ($date !== false) {
                    return date('Y-m-d H:i:s', $date);
                }
            }
        }
        
        return current_time('Y-m-d H:i:s');
    }
    
    /**
     * 著者情報を抽出
     */
    private function extract_author($body) {
        $author_patterns = array(
            '/<meta[^>]*name=["\']author["\'][^>]*content=["\']([^"\']*)["\']/i',
            '/<meta[^>]*property=["\']article:author["\'][^>]*content=["\']([^"\']*)["\']/i',
            '/<span[^>]*class=["\'][^"\']*author[^"\']*["\'][^>]*>(.*?)<\/span>/i',
            '/<div[^>]*class=["\'][^"\']*author[^"\']*["\'][^>]*>(.*?)<\/div>/i'
        );
        
        foreach ($author_patterns as $pattern) {
            if (preg_match($pattern, $body, $matches)) {
                $author = trim(strip_tags($matches[1]));
                if (!empty($author)) {
                    return $author;
                }
            }
        }
        
        return '';
    }
    
    /**
     * カテゴリ情報を抽出
     */
    private function extract_categories($body) {
        $categories = array();
        
        $category_patterns = array(
            '/<meta[^>]*property=["\']article:section["\'][^>]*content=["\']([^"\']*)["\']/i',
            '/<a[^>]*class=["\'][^"\']*category[^"\']*["\'][^>]*>(.*?)<\/a>/i',
            '/<span[^>]*class=["\'][^"\']*category[^"\']*["\'][^>]*>(.*?)<\/span>/i'
        );
        
        foreach ($category_patterns as $pattern) {
            if (preg_match_all($pattern, $body, $matches)) {
                foreach ($matches[1] as $match) {
                    $category = trim(strip_tags($match));
                    if (!empty($category) && !in_array($category, $categories)) {
                        $categories[] = $category;
                    }
                }
            }
        }
        
        return $categories;
    }
    
    /**
     * RSSフィードから記事を取得（詳細版）
     */
    private function fetch_rss_articles($url, $max_results = 20) {
        if (!class_exists('SimplePie')) {
            require_once(ABSPATH . WPINC . '/class-simplepie.php');
        }

        $feed = new SimplePie();
        $feed->set_feed_url($url);
        $feed->set_cache_location(WP_CONTENT_DIR . '/cache');
        $feed->set_cache_duration(300); // 5分
        $feed->enable_order_by_date(true);
        $feed->enable_cache(true);
        $feed->init();

        if ($feed->error()) {
            throw new Exception('RSSフィードの読み込みに失敗しました: ' . $feed->error());
        }

        $items = $feed->get_items();
        $articles = array();

        foreach (array_slice($items, 0, $max_results) as $item) {
            // 詳細なコンテンツを取得
            $content = $item->get_content();
            $description = $item->get_description();

            // コンテンツが空の場合は説明文を使用
            if (empty($content) && !empty($description)) {
                $content = $description;
            }

            // HTMLタグを除去してクリーンなテキストを取得
            $clean_content = $this->clean_article_content($content);

            // カテゴリ情報を取得
            $categories = array();
            if ($item->get_categories()) {
                foreach ($item->get_categories() as $category) {
                    $categories[] = $category->get_label();
                }
            }

            // より詳細な記事情報を取得
            $article_data = array(
                'title' => $item->get_title(),
                'content' => $clean_content,
                'description' => $description,
                'url' => $item->get_permalink(),
                'published_at' => $item->get_date('Y-m-d H:i:s'),
                'author' => $item->get_author() ? $item->get_author()->get_name() : '',
                'source' => $url,
                'categories' => $categories,
                'guid' => $item->get_id(),
                'excerpt' => $item->get_description() ? wp_trim_words(strip_tags($item->get_description()), 50, '...') : ''
            );

            // 記事の詳細ページから追加コンテンツを取得（可能であれば）
            if (!empty($article_data['url'])) {
                $additional_content = $this->fetch_additional_content($article_data['url']);
                if (!empty($additional_content)) {
                    $article_data['content'] = $additional_content;
                }
            }

            $articles[] = $article_data;
        }

        return $articles;
    }
    
    /**
     * HTMLページから記事を取得
     */
    private function fetch_html_articles($url, $max_results = 20) {
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'sslverify' => false,
            'user-agent' => 'News Crawler Plugin/1.0'
        ));
        
        if (is_wp_error($response)) {
            throw new Exception('HTMLページの取得に失敗しました: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        
        // HTMLパース（簡易版）
        $articles = array();
        
        // タイトルを取得
        if (preg_match('/<title[^>]*>(.*?)<\/title>/i', $body, $matches)) {
            $title = trim(strip_tags($matches[1]));
        } else {
            $title = 'タイトルなし';
        }
        
        // 本文を取得（最初の段落から）
        $content = '';
        if (preg_match('/<p[^>]*>(.*?)<\/p>/i', $body, $matches)) {
            $content = trim(strip_tags($matches[1]));
        }
        
        // メタディスクリプションを取得
        if (preg_match('/<meta[^>]*name=["\']description["\'][^>]*content=["\']([^"\']*)["\']/i', $body, $matches)) {
            $description = trim($matches[1]);
        } else {
            $description = $content;
        }
        
        $articles[] = array(
            'title' => $title,
            'content' => $content,
            'description' => $description,
            'url' => $url,
            'published_at' => current_time('Y-m-d H:i:s'),
            'author' => '',
            'source' => $url
        );
        
        return $articles;
    }
    
    /**
     * ニュース記事のキーワードマッチング
     */
    private function is_news_keyword_match($article, $keywords) {
        $text_to_search = strtolower($article['title'] . ' ' . ($article['content'] ?? '') . ' ' . ($article['description'] ?? ''));
        
        foreach ($keywords as $keyword) {
            $keyword_trimmed = trim($keyword);
            if (empty($keyword_trimmed)) {
                continue;
            }
            
            if (stripos($text_to_search, strtolower($keyword_trimmed)) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * ニュース記事の重複チェック
     */
    private function is_duplicate_news($article) {
        global $wpdb;
        $title = $article['title'];
        $url = $article['url'];
        
        // 基本設定から重複チェック設定を取得
        $basic_settings = get_option('news_crawler_basic_settings', array());
        $period = isset($basic_settings['duplicate_check_period']) ? intval($basic_settings['duplicate_check_period']) : 30;
        
        // URLの完全一致チェック
        $existing_url = $wpdb->get_var($wpdb->prepare(
            "SELECT pm.post_id FROM {$wpdb->postmeta} pm 
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
             WHERE pm.meta_key = '_news_crawler_source_url' AND pm.meta_value = %s 
             AND p.post_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
             AND p.post_status IN ('publish', 'draft', 'pending', 'private')",
            $url,
            $period
        ));
        
        if ($existing_url) {
            return true;
        }
        
        // タイトルの完全一致チェック
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
            return true;
        }
        
        return false;
    }
    
    /**
     * 各記事の詳細な要約を生成
     */
    private function generate_article_summary($article) {
        // OpenAI APIキーを取得
        $basic_settings = get_option('news_crawler_basic_settings', array());
        $api_key = isset($basic_settings['openai_api_key']) ? $basic_settings['openai_api_key'] : '';

        if (empty($api_key) || empty($article['content'])) {
            // APIキーがない場合や本文が空の場合でも、フォールバック要約にクリーンアップを適用
            $raw = $article['content'] ?: $article['description'] ?: '';
            $clean_fallback = $this->clean_content_for_summary($raw);
            if (empty($clean_fallback)) {
                return '';
            }
            return wp_trim_words($clean_fallback, 50, '...');
        }

        // クリーンアップ後のコンテンツが短すぎる場合はスキップ（投稿自体を作成しない判定につながる）
        $clean_short = $this->clean_content_for_summary($article['content']);
        // 最低必要文字数を緩和し、フォールバックを試みる
        $min_length = 20;
        if (mb_strlen($clean_short) < $min_length) {
            // description や 詳細ページからの追加コンテンツでフォールバックを試す
            $fallback_raw = $article['description'] ?? '';
            if (!empty($article['url']) && mb_strlen($fallback_raw) < $min_length) {
                $additional = $this->fetch_additional_content($article['url']);
                if (!empty($additional)) {
                    $fallback_raw = $additional;
                }
            }
            $fallback_clean = $this->clean_content_for_summary($fallback_raw);
            if (mb_strlen($fallback_clean) >= 20) {
                // フォールバックが十分なら簡易要約を返却
                return wp_trim_words($fallback_clean, 70, '...');
            }
            error_log('NewsCrawler: 記事要約生成スキップ - 元コンテンツが短すぎます: ' . ($article['title'] ?? ''));
            return '';
        }

        // 要約生成前にコンテンツをさらにクリーンアップ
        $clean_content = $this->clean_content_for_summary($article['content']);

        // クリーンアップ後のコンテンツが短すぎる場合はスキップ
        if (mb_strlen($clean_content) < 20) {
            error_log('NewsCrawler: 記事要約生成スキップ - クリーンアップ後のコンテンツが短すぎます: ' . $article['title']);
            return '';
        }

        try {
            // OpenAI APIで詳細な要約を生成
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
                            'content' => 'あなたはニュース記事の要約を専門とするアシスタントです。記事の内容を読みやすく、わかりやすい日本語で要約してください。重要なポイントを押さえつつ、簡潔にまとめてください。ナビゲーションやメニュー、広告などの不要なテキストは完全に無視してください。'
                        ),
                        array(
                            'role' => 'user',
                            'content' => '以下のニュース記事を3-4行程度で要約してください。ナビゲーションやメニュー、広告などの不要なテキストは無視してください：' . "\n\n" . $clean_content
                        )
                    ),
                    'max_tokens' => 200,
                    'temperature' => 0.3
                )),
                'timeout' => 30,
                'redirection' => 5,
                'httpversion' => '1.1',
                'user-agent' => 'News Crawler Plugin/1.0'
            ));

            if (!is_wp_error($response)) {
                $response_code = wp_remote_retrieve_response_code($response);
                if ($response_code === 200) {
                    $body = wp_remote_retrieve_body($response);
                    $data = json_decode($body, true);

                    if (isset($data['choices'][0]['message']['content'])) {
                        $summary = trim($data['choices'][0]['message']['content']);

                        // 要約がナビゲーションやメニュー関連のテキストを含んでいる場合は除去
                        $summary = $this->clean_summary_content($summary);

                        // 要約が短すぎる場合はスキップ
                        if (mb_strlen($summary) < 20) {
                            error_log('NewsCrawler: 記事要約生成スキップ - 要約が短すぎます: ' . $article['title']);
                            return '';
                        }

                        // 改行をスペースに変換して整形
                        $summary = str_replace(array("\r\n", "\r", "\n"), ' ', $summary);
                        return $summary;
                    }
                }
            }
        } catch (Exception $e) {
            error_log('NewsCrawler: 記事要約生成エラー: ' . $e->getMessage());
        }

        // API呼び出しに失敗した場合は簡易要約を使用
        return wp_trim_words($clean_content, 50, '...');
    }

    /**
     * 要約生成用のコンテンツをさらにクリーンアップ
     */
    private function clean_content_for_summary($content) {
        if (empty($content)) {
            return '';
        }

        // 基本的なクリーンアップを実行
        $clean_content = $this->clean_article_content($content);

        // 要約生成に適さないパターンをさらに除去
        $summary_patterns = array(
            '/本文エリアへ/iu',           // 本文エリアへのリンク
            '/メインコンテンツ/iu',       // メインコンテンツへのリンク
            '/スキップ/iu',               // スキップリンク
            '/メニュー/iu',               // メニュー関連
            '/ナビゲーション/iu',         // ナビゲーション関連
            '/広告/iu',                   // 広告関連
            '/スポンサー/iu',             // スポンサー関連
            '/シェア/iu',                 // シェア関連
            '/コメント/iu',               // コメント関連
            '/フッター/iu',               // フッター関連
            '/著作権/iu',                 // 著作権関連
            '/朝日新聞：朝日新聞社のニュースサイト/iu',
            '/朝日新聞デジタル：朝日新聞社のニュースサイト/iu',
        );

        foreach ($summary_patterns as $pattern) {
            $clean_content = preg_replace($pattern, '', $clean_content);
        }

        // 連続する空白を除去
        $clean_content = preg_replace('/\s+/', ' ', $clean_content);
        $clean_content = trim($clean_content);

        return $clean_content;
    }

    /**
     * 生成された要約をクリーンアップ
     */
    private function clean_summary_content($summary) {
        if (empty($summary)) {
            return '';
        }

        // 要約に含まれる不要なテキストを除去
        $clean_patterns = array(
            '/メインメニューをとばして/iu',
            '/メインコンテンツへ/iu',
            '/本文へ/iu',
            '/スキップ/iu',
            '/Skip to/iu',
            '/メニュー/iu',
            '/Menu/iu',
            '/Navigation/iu',
            '/広告/iu',
            '/スポンサー/iu',
            '/シェア/iu',
            '/Share/iu',
            '/コメント/iu',
            '/Comments/iu',
            '/朝日新聞：朝日新聞社のニュースサイト/iu',
            '/朝日新聞デジタル：朝日新聞社のニュースサイト/iu',
        );

        foreach ($clean_patterns as $pattern) {
            $summary = preg_replace($pattern, '', $summary);
        }

        // 連続する空白を除去
        $summary = preg_replace('/\s+/', ' ', $summary);
        $summary = trim($summary);

        return $summary;
    }

    /**
     * 投稿全体のHTMLテキストから不要な定型文を最終的に除去
     * （どこかの経路で混入してもここで一掃）
     */
    private function sanitize_post_content($html) {
        if (empty($html)) {
            return '';
        }

        $patterns = array(
            // アクセシビリティのスキップリンク類
            '/メインメニューをとばして[、,\\s]*このページの本文エリアへ/iu',
            '/メインメニューをとばして/iu',
            '/メインコンテンツへ/iu',
            '/本文エリアへ/iu',
            '/本文へ/iu',
            '/スキップ/iu',
            '/Skip to(?:\\s*main\\s*content)?/iu',
            '/Jump to\\s*main\\s*content/iu',

            // 朝日新聞 固有表現
            '/朝日新聞(?:デジタル)?[：:\\s]*朝日新聞社のニュースサイト/iu',
        );

        foreach ($patterns as $pattern) {
            $html = preg_replace($pattern, '', $html);
        }

        // 余分な空白を整理
        $html = preg_replace('/[ \\t\\x{3000}]{2,}/u', ' ', $html);
        return $html;
    }

    /**
     * ニュース記事の投稿を作成
     */
    private function create_news_summary_post($articles, $categories, $status) {
        // デバッグ: 受け取ったステータスをログに記録
        error_log('NewsCrawler: create_news_summary_post called with status: ' . $status);

        $cat_ids = array();
        foreach ($categories as $category) {
            $cat_ids[] = $this->get_or_create_category($category);
        }

        // キーワード情報を取得
        $options = get_option($this->option_name, array());
        $keywords = isset($options['keywords']) ? $options['keywords'] : array('ニュース');

        // タイトルは一時的に【ジャンル名】以降はＡＩで生成（SEOタイトル生成で上書き）
        $genre_name_for_title = 'ニュース';
        $current_genre_setting = get_transient('news_crawler_current_genre_setting');
        if ($current_genre_setting && isset($current_genre_setting['genre_name']) && !empty($current_genre_setting['genre_name'])) {
            $genre_name_for_title = $current_genre_setting['genre_name'];
        }
        $post_title = '【' . $genre_name_for_title . '】以降はＡＩで生成';

        $post_content = '';
        $valid_articles = array(); // 要約が生成できた記事のみを格納

        foreach ($articles as $article) {
            // 記事要約を生成
            $article_summary = $this->generate_article_summary($article);

            // 要約が生成できなかった場合はスキップ
            if (empty($article_summary)) {
                error_log('NewsCrawler: 要約生成に失敗したため記事をスキップ: ' . $article['title']);
                continue;
            }

            // 要約が生成できた記事を有効記事として追加
            $valid_articles[] = $article;

            // 記事区切り
            $post_content .= '<!-- wp:separator -->';
            $post_content .= '<hr class="wp-block-separator has-alpha-channel-opacity"/>';
            $post_content .= '<!-- /wp:separator -->';

            // サイトタイトル（H2）
            $site_title = $this->extract_site_title($article['source']);
            if (!empty($site_title)) {
                $post_content .= '<!-- wp:heading {"level":2} -->';
                $post_content .= '<h2>' . esc_html($site_title) . '</h2>';
                $post_content .= '<!-- /wp:heading -->';
            }

            // 記事タイトル（H3）
            $post_content .= '<!-- wp:heading {"level":3} -->';
            $post_content .= '<h3>' . esc_html($article['title']) . '</h3>';
            $post_content .= '<!-- /wp:heading -->';

            // 記事要約（詳細な要約を表示）
            $post_content .= '<!-- wp:paragraph -->';
            $post_content .= '<p>' . esc_html($article_summary) . '</p>';
            $post_content .= '<!-- /wp:paragraph -->';

            // 記事リンク
            if (!empty($article['url'])) {
                $post_content .= '<!-- wp:paragraph -->';
                $post_content .= '<p><a href="' . esc_url($article['url']) . '" target="_blank" rel="noopener noreferrer"><strong>📰 元記事を読む →</strong></a></p>';
                $post_content .= '<!-- /wp:paragraph -->';
            }

            // メタ情報（シンプルな段落形式）
            $meta_info = array();

            if (!empty($article['published_at'])) {
                $meta_info[] = '<strong>公開日:</strong> ' . esc_html($article['published_at']);
            }

            if (!empty($article['author'])) {
                $meta_info[] = '<strong>著者:</strong> ' . esc_html($article['author']);
            }

            if (!empty($meta_info)) {
                $post_content .= '<!-- wp:paragraph -->';
                $post_content .= '<p><small>' . implode(' | ', $meta_info) . '</small></p>';
                $post_content .= '<!-- /wp:paragraph -->';
            }
        }

        // 有効な記事がない場合は投稿を作成しない（上位に明確なエラーを返す）
        if (empty($valid_articles)) {
            $original_count = is_array($articles) ? count($articles) : 0;
            $message = '要約生成できた記事がありませんでした（元記事数: ' . $original_count . '）。設定や要約閾値を見直してください。';
            error_log('NewsCrawler: ' . $message);
            return new WP_Error('no_valid_articles', $message);
        }
        
        // 仕上げの全体サニタイズ（念のため）
        $post_content = $this->sanitize_post_content($post_content);

        // 投稿作成者を決定（基本設定のデフォルト投稿者を優先）
        $basic_settings = get_option('news_crawler_basic_settings', array());
        $author_id = 0;
        if (isset($basic_settings['default_post_author'])) {
            $candidate = intval($basic_settings['default_post_author']);
            if ($candidate > 0 && get_user_by('id', $candidate)) {
                $author_id = $candidate;
            }
        }
        if (!$author_id) {
            $author_id = get_current_user_id() ?: 1;
        }

        // 設定されたステータスで直接投稿を作成
        $post_data = array(
            'post_title'    => $post_title,
            'post_content'  => $post_content,
            'post_status'   => $status, // 設定されたステータスで直接作成
            'post_author'   => $author_id,
            'post_type'     => 'post',
            'post_category' => $cat_ids
        );

        // 予約投稿の場合、日時を設定
        if ($status === 'future') {
            $basic_settings = get_option('news_crawler_basic_settings', array());
            $scheduled_time = isset($basic_settings['scheduled_publish_time']) ? $basic_settings['scheduled_publish_time'] : '09:00';

            // 今日の日付に予約時間を設定
            $publish_date = date('Y-m-d') . ' ' . $scheduled_time . ':00';
            $post_data['post_date'] = $publish_date;
            $post_data['post_date_gmt'] = get_gmt_from_date($publish_date);
        }

        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // メタデータの保存
        update_post_meta($post_id, '_news_summary', true);
        update_post_meta($post_id, '_news_articles_count', count($articles));
        update_post_meta($post_id, '_news_crawled_date', current_time('mysql'));
        update_post_meta($post_id, '_news_crawler_created', true);
        update_post_meta($post_id, '_news_crawler_creation_method', 'news');
        update_post_meta($post_id, '_news_crawler_intended_status', $status);
        update_post_meta($post_id, '_news_crawler_creation_timestamp', current_time('timestamp'));
        update_post_meta($post_id, '_news_crawler_ready', true); // ステータス変更不要のためtrueに設定

        // ジャンルIDを保存（自動投稿用）
        $current_genre_setting = get_transient('news_crawler_current_genre_setting');
        if ($current_genre_setting && isset($current_genre_setting['id'])) {
            update_post_meta($post_id, '_news_crawler_genre_id', $current_genre_setting['id']);
        }

        // ソースURLを保存（数値キーの有無にかかわらず最初のURLを安全に取得）
        $first_article_url = '';
        foreach ($articles as $art) {
            if (is_array($art) && !empty($art['url'])) {
                $first_article_url = $art['url'];
                break;
            }
        }
        if (!empty($first_article_url)) {
            update_post_meta($post_id, '_news_crawler_source_url', $first_article_url);
        }

        // アイキャッチ生成
        $featured_result = $this->maybe_generate_featured_image($post_id, $post_title, $keywords);

        // AI要約生成
        if (class_exists('NewsCrawlerOpenAISummarizer')) {
            // 基本設定で要約生成が有効かチェック（デフォルトで有効）
            $basic_settings = get_option('news_crawler_basic_settings', array());
            $auto_summary_enabled = isset($basic_settings['auto_summary_generation']) ? $basic_settings['auto_summary_generation'] : true;

            if ($auto_summary_enabled) {
                error_log('NewsCrawler: AI要約生成を実行します (投稿ID: ' . $post_id . ')');
                $summarizer = new NewsCrawlerOpenAISummarizer();
                $summarizer->generate_summary($post_id);
            } else {
                error_log('NewsCrawler: AI要約生成が無効のためスキップします (投稿ID: ' . $post_id . ')');
            }
        }

        error_log('NewsCrawler: 投稿を ' . $status . ' ステータスで正常に作成しました (ID: ' . $post_id . ')');
        
        return $post_id;
    }
    
    /**
     * ニュース統計情報を更新
     */
    private function update_news_statistics($posts_created, $duplicates_skipped) {
        if ($duplicates_skipped > 0) {
            $current_duplicates = get_option('news_crawler_duplicates_skipped', 0);
            update_option('news_crawler_duplicates_skipped', $current_duplicates + $duplicates_skipped);
        }
        update_option('news_crawler_last_run', current_time('mysql'));
    }
    
    /**
     * 投稿ステータス変更をスケジュール
     */
    private function schedule_post_status_update($post_id, $status) {
        // XPosterが新規投稿を認識するまで10秒待ってからステータスを変更
        wp_schedule_single_event(time() + 10, 'news_crawler_update_post_status', array($post_id, $status));
        
        // 追加でNews Crawler用のメタデータを再設定
        wp_schedule_single_event(time() + 2, 'news_crawler_ensure_meta', array($post_id));
        
        error_log('NewsCrawler: 投稿ステータス変更を遅延実行でスケジュール (ID: ' . $post_id . ', 対象ステータス: ' . $status . ')');
    }
    
    /**
     * 記事コンテンツをクリーンアップ
     */
    private function clean_article_content($content) {
        if (empty($content)) {
            return '';
        }

        // デバッグ: 処理前のコンテンツをログに記録
        error_log('NewsCrawler: クリーンアップ処理開始 - コンテンツ長: ' . mb_strlen($content));

        // まずHTMLから本文部分を抽出
        $content = $this->extract_main_content($content);

        // HTMLタグを除去
        $clean_content = wp_strip_all_tags($content);

        // 特殊文字をデコード
        $clean_content = html_entity_decode($clean_content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // デバッグ: HTML除去後のコンテンツを確認
        if (strpos($clean_content, 'メインメニューをとばして') !== false) {
            error_log('NewsCrawler: HTML除去後もナビゲーションテキストが残っています');
            error_log('NewsCrawler: 該当部分: ' . substr($clean_content, strpos($clean_content, 'メインメニューをとばして'), 100));
        }

        // 不要な文字列を除去（ナビゲーション、メニュー、広告など）
        $patterns_to_remove = array(
            // ナビゲーションテキスト（より詳細なパターン）
            '/メインメニューをとばして、このページの本文エリアへ/iu',
            '/メインメニューをとばして/iu',
            '/メインコンテンツへ/iu',
            '/本文エリアへ/iu',
            '/本文へ/iu',
            '/スキップ/iu',
            '/Skip to/iu',
            '/Skip to main content/iu',
            '/Jump to main content/iu',
            '/朝日新聞：朝日新聞社のニュースサイト/iu',
            '/朝日新聞デジタル：朝日新聞社のニュースサイト/iu',

            // メニュー関連
            '/メニュー.*?$/i',
            '/Menu.*?$/i',
            '/Navigation.*?$/i',
            '/ナビゲーション.*?$/i',

            // 広告関連
            '/\[.*?\]/',  // 角括弧内のテキスト
            '/\(.*?\)/',  // 丸括弧内のテキスト（一部）
            '/続きを読む.*?$/i',  // 「続きを読む」以降
            '/関連記事.*?$/i',    // 「関連記事」以降
            '/広告.*?$/i',       // 「広告」以降
            '/スポンサーリンク.*?$/i', // 「スポンサーリンク」以降
            '/PR.*?$/i',         // PR表記
            '/Sponsored.*?$/i',  // スポンサー表記
            '/Affiliate.*?$/i',  // アフィリエイト表記

            // ソーシャルメディア関連
            '/シェア.*?$/i',
            '/Share.*?$/i',
            '/ツイート.*?$/i',
            '/Tweet.*?$/i',
            '/いいね.*?$/i',
            '/Like.*?$/i',
            '/Facebook.*?$/i',
            '/Twitter.*?$/i',
            '/Instagram.*?$/i',

            // コメント関連
            '/コメント.*?$/i',
            '/Comments.*?$/i',
            '/Leave a comment.*?$/i',

            // フッター関連
            '/フッター.*?$/i',
            '/Footer.*?$/i',
            '/©.*?$/i',          // 著作権表記
            '/Copyright.*?$/i',
            '/All rights reserved.*?$/i',

            // その他の不要なパターン
            '/\d{1,2}\/\d{1,2}\/\d{4}.*?$/', // 日付パターン
            '/\d{4}年\d{1,2}月\d{1,2}日.*?$/', // 日本語日付パターン
            '/\d{1,2}:\d{2}.*?$/', // 時間パターン
            '/\d{4}-\d{1,2}-\d{1,2}.*?$/', // 日付パターン（ハイフン）

            // 一般的なナビゲーションテキスト
            '/ホーム.*?$/i',
            '/Home.*?$/i',
            '/トップ.*?$/i',
            '/Top.*?$/i',
            '/サイトマップ.*?$/i',
            '/Sitemap.*?$/i',
            '/お問い合わせ.*?$/i',
            '/Contact.*?$/i',
            '/プライバシーポリシー.*?$/i',
            '/Privacy Policy.*?$/i',
            '/利用規約.*?$/i',
            '/Terms of Service.*?$/i',
        );

        foreach ($patterns_to_remove as $pattern) {
            $clean_content = preg_replace($pattern, '', $clean_content);
        }

        // 余分な空白を除去
        $clean_content = preg_replace('/\s+/', ' ', $clean_content);
        $clean_content = trim($clean_content);

        // デバッグ: 最終結果を確認
        if (strpos($clean_content, 'メインメニューをとばして') !== false) {
            error_log('NewsCrawler: クリーンアップ後もナビゲーションテキストが残っています');
            error_log('NewsCrawler: 最終コンテンツ長: ' . mb_strlen($clean_content));
        } else {
            error_log('NewsCrawler: ナビゲーションテキストは正常に除去されました');
        }

        // コンテンツが短すぎる場合は空文字を返す（閾値緩和）
        if (mb_strlen($clean_content) < 20) {
            error_log('NewsCrawler: コンテンツが短すぎるため空文字を返します');
            return '';
        }

        return $clean_content;
    }

    /**
     * HTMLから本文部分を抽出
     */
    private function extract_main_content($html) {
        if (empty($html)) {
            return '';
        }

        // まず、明らかに不要な要素を除去
        $html = $this->remove_unwanted_elements($html);

        // 本文を抽出するためのパターンを優先順位で試す
        $content_patterns = array(
            // 記事本文クラス（一般的なもの）
            '/<div[^>]*class="[^"]*article-body[^"]*"[^>]*>(.*?)<\/div>/si',
            '/<div[^>]*class="[^"]*entry-content[^"]*"[^>]*>(.*?)<\/div>/si',
            '/<div[^>]*class="[^"]*post-content[^"]*"[^>]*>(.*?)<\/div>/si',
            '/<div[^>]*class="[^"]*content[^"]*"[^>]*>(.*?)<\/div>/si',
            '/<article[^>]*>(.*?)<\/article>/si',

            // より具体的なクラス名
            '/<div[^>]*class="[^"]*article__body[^"]*"[^>]*>(.*?)<\/div>/si',
            '/<div[^>]*class="[^"]*article-content[^"]*"[^>]*>(.*?)<\/div>/si',
            '/<div[^>]*class="[^"]*news-content[^"]*"[^>]*>(.*?)<\/div>/si',

            // 朝日新聞などのニュースサイト固有のパターン
            '/<div[^>]*class="[^"]*ArticleBody[^"]*"[^>]*>(.*?)<\/div>/si',
            '/<div[^>]*class="[^"]*articleText[^"]*"[^>]*>(.*?)<\/div>/si',
            '/<div[^>]*class="[^"]*news-body[^"]*"[^>]*>(.*?)<\/div>/si',
            '/<div[^>]*class="[^"]*main-content[^"]*"[^>]*>(.*?)<\/div>/si',

            // IDによる抽出
            '/<div[^>]*id="[^"]*content[^"]*"[^>]*>(.*?)<\/div>/si',
            '/<div[^>]*id="[^"]*article[^"]*"[^>]*>(.*?)<\/div>/si',
            '/<div[^>]*id="[^"]*main[^"]*"[^>]*>(.*?)<\/div>/si',
            '/<div[^>]*id="[^"]*news[^"]*"[^>]*>(.*?)<\/div>/si',

            // 段落の集まり（フォールバック）
            '/<p[^>]*>.*?<\/p>(?:\s*<p[^>]*>.*?<\/p>)*/si',
        );

        foreach ($content_patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $extracted = $matches[1];
                if (!empty($extracted) && mb_strlen(strip_tags($extracted)) > 100) {
                    return $extracted;
                }
            }
        }

        // パターンで抽出できなかった場合は、ナビゲーション関連の要素を除去して全体を返す
        $clean_html = $html;

        // ナビゲーション要素を除去
        $nav_patterns = array(
            '/<nav[^>]*>.*?<\/nav>/si',
            '/<header[^>]*>.*?<\/header>/si',
            '/<aside[^>]*>.*?<\/aside>/si',
            '/<footer[^>]*>.*?<\/footer>/si',
            '/<div[^>]*class="[^"]*nav[^"]*"[^>]*>.*?<\/div>/si',
            '/<div[^>]*class="[^"]*menu[^"]*"[^>]*>.*?<\/div>/si',
            '/<div[^>]*class="[^"]*sidebar[^"]*"[^>]*>.*?<\/div>/si',
            '/<div[^>]*class="[^"]*widget[^"]*"[^>]*>.*?<\/div>/si',
            '/<ul[^>]*class="[^"]*nav[^"]*"[^>]*>.*?<\/ul>/si',
            '/<ul[^>]*class="[^"]*menu[^"]*"[^>]*>.*?<\/ul>/si',
            '/<div[^>]*class="[^"]*header[^"]*"[^>]*>.*?<\/div>/si',
            '/<div[^>]*class="[^"]*footer[^"]*"[^>]*>.*?<\/div>/si',
            '/<div[^>]*class="[^"]*breadcrumb[^"]*"[^>]*>.*?<\/div>/si',
            '/<div[^>]*class="[^"]*social[^"]*"[^>]*>.*?<\/div>/si',
            '/<div[^>]*class="[^"]*share[^"]*"[^>]*>.*?<\/div>/si',
        );

        foreach ($nav_patterns as $pattern) {
            $clean_html = preg_replace($pattern, '', $clean_html);
        }

        return $clean_html;
    }

    /**
     * 明らかに不要な要素を除去
     */
    private function remove_unwanted_elements($html) {
        if (empty($html)) {
            return '';
        }

        // スクリプトとスタイルを除去
        $html = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $html);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $html);
        $html = preg_replace('/<link[^>]*>.*?<\/link>/si', '', $html);

        // コメントを除去
        $html = preg_replace('/<!--.*?-->/si', '', $html);

        // ナビゲーション関連のテキストを直接除去
        $nav_text_patterns = array(
            '/メインメニューをとばして、このページの本文エリアへ/iu',
            '/メインメニューをとばして/iu',
            '/メインコンテンツへ/iu',
            '/本文エリアへ/iu',
            '/本文へ/iu',
            '/スキップ/iu',
            '/Skip to/iu',
            '/Skip to main content/iu',
            '/Jump to main content/iu',
            '/朝日新聞：朝日新聞社のニュースサイト/iu',
            '/朝日新聞デジタル：朝日新聞社のニュースサイト/iu',
        );

        foreach ($nav_text_patterns as $pattern) {
            $html = preg_replace($pattern, '', $html);
        }

        return $html;
    }

    /**
     * 記事の詳細ページから追加コンテンツを取得
     */
    private function fetch_additional_content($url) {
        try {
            $response = wp_remote_get($url, array(
                'timeout' => 15,
                'sslverify' => false,
                'user_agent' => 'News Crawler Plugin/1.0',
                'headers' => array(
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'ja,en-US;q=0.7,en;q=0.3',
                    'Accept-Encoding' => 'gzip, deflate',
                    'DNT' => '1',
                    'Connection' => 'keep-alive',
                    'Upgrade-Insecure-Requests' => '1',
                )
            ));

            if (is_wp_error($response)) {
                error_log('NewsCrawler: 追加コンテンツ取得エラー: ' . $response->get_error_message());
                return '';
            }

            $body = wp_remote_retrieve_body($response);

            if (empty($body)) {
                return '';
            }

            // 記事本文を抽出するためのパターン
            $content_patterns = array(
                // JSON-LD構造化データから本文を取得
                '/"articleBody"\s*:\s*"([^"]*(?:\\\\.[^"]*)*)"/s',
                // 一般的な記事本文クラス
                '/<div[^>]*class="[^"]*article-body[^"]*"[^>]*>(.*?)<\/div>/si',
                '/<div[^>]*class="[^"]*entry-content[^"]*"[^>]*>(.*?)<\/div>/si',
                '/<div[^>]*class="[^"]*post-content[^"]*"[^>]*>(.*?)<\/div>/si',
                '/<article[^>]*>(.*?)<\/article>/si',
                // 一般的な段落の集まり
                '/<p[^>]*>.*?<\/p>(?:\s*<p[^>]*>.*?<\/p>)*/si',
            );

            $additional_content = '';

            foreach ($content_patterns as $pattern) {
                if (preg_match($pattern, $body, $matches)) {
                    $extracted_content = $matches[1];

                    // JSON-LDの場合はデコード
                    if (strpos($pattern, 'articleBody') !== false) {
                        $extracted_content = json_decode('"' . $extracted_content . '"');
                    }

                    if (!empty($extracted_content)) {
                        $additional_content = $this->clean_article_content($extracted_content);
                        break;
                    }
                }
            }

            // コンテンツが十分な長さがある場合のみ使用
            if (mb_strlen($additional_content) > 100) {
                error_log('NewsCrawler: 追加コンテンツ取得成功 - 長さ: ' . mb_strlen($additional_content) . '文字');
                return $additional_content;
            }

        } catch (Exception $e) {
            error_log('NewsCrawler: 追加コンテンツ取得例外: ' . $e->getMessage());
        }

        return '';
    }

    /**
     * サイトタイトルを抽出
     */
    private function extract_site_title($url) {
        if (empty($url)) {
            return '';
        }

        // URLからドメインを抽出
        $parsed_url = parse_url($url);
        if (!isset($parsed_url['host'])) {
            return '';
        }

        $domain = $parsed_url['host'];

        // www. を除去
        $domain = preg_replace('/^www\./', '', $domain);

        // 一般的なニュースサイトのタイトルを定義
        $site_titles = array(
            'nhk.or.jp' => 'NHKニュース',
            'bbc.com' => 'BBC News',
            'cnn.com' => 'CNN',
            'reuters.com' => 'Reuters',
            'apnews.com' => 'AP News',
            'bloomberg.com' => 'Bloomberg',
            'wsj.com' => 'The Wall Street Journal',
            'nytimes.com' => 'The New York Times',
            'washingtonpost.com' => 'The Washington Post',
            'guardian.com' => 'The Guardian',
            'ft.com' => 'Financial Times',
            'japantimes.co.jp' => 'The Japan Times',
            'asahi.com' => '朝日新聞',
            'yomiuri.co.jp' => '読売新聞',
            'mainichi.jp' => '毎日新聞',
            'nikkei.com' => '日本経済新聞',
            'sankei.com' => '産経新聞',
            'yahoo.co.jp' => 'Yahoo!ニュース',
            'news.yahoo.co.jp' => 'Yahoo!ニュース',
            'google.com' => 'Google News',
            'news.google.com' => 'Google News',
        );

        // ドメインに基づいてタイトルを検索
        foreach ($site_titles as $site_domain => $title) {
            if (strpos($domain, $site_domain) !== false) {
                return $title;
            }
        }

        // マッチしない場合はドメインの最初の部分を使用
        $parts = explode('.', $domain);
        if (count($parts) > 1) {
            return ucfirst($parts[0]);
        }

        return ucfirst($domain);
    }

    /**
     * タイトルの類似度を計算
     */
    private function calculate_title_similarity($title1, $title2) {
        $title1 = strtolower(trim($title1));
        $title2 = strtolower(trim($title2));

        if ($title1 === $title2) {
            return 1.0;
        }

        $words1 = explode(' ', $title1);
        $words2 = explode(' ', $title2);

        $common_words = array_intersect($words1, $words2);
        $total_words = array_unique(array_merge($words1, $words2));

        if (empty($total_words)) {
            return 0.0;
        }

        return count($common_words) / count($total_words);
    }
}

// 重複したNewsCrawlerクラスは削除されました

// YouTubeクラスを読み込み
require_once plugin_dir_path(__FILE__) . 'includes/class-youtube-crawler.php';

// プラグインのインスタンス化
new NewsCrawler();

// 設定管理クラスの初期化
if (class_exists('NewsCrawlerSettingsManager')) {
    new NewsCrawlerSettingsManager();
}

// YouTube機能が利用可能な場合のみインスタンス化
if (class_exists('NewsCrawlerYouTubeCrawler')) {
    new NewsCrawlerYouTubeCrawler();
}

// プラグイン有効化時の初期化
register_activation_hook(__FILE__, 'news_crawler_activation');

// プラグイン無効化時のクリーンアップ
register_deactivation_hook(__FILE__, 'news_crawler_deactivation');

function news_crawler_activation() {
    // Cronスクリプトの自動作成
    if (class_exists('NewsCrawlerCronSettings')) {
        $cron_settings = new NewsCrawlerCronSettings();
        // 有効化時は強制的にスクリプトを作成
        $cron_settings->force_create_cron_script();
    }
}

function news_crawler_deactivation() {
    // 更新チェックのクリーンアップ
    if (class_exists('NewsCrawlerUpdater')) {
        NewsCrawlerUpdater::cleanup();
    }
    
    // その他のクリーンアップ処理
    wp_clear_scheduled_hook('news_crawler_auto_posting_cron');
}

