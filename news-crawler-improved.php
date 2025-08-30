<?php
/**
 * Plugin Name: News Crawler (Improved)
 * Plugin URI: https://github.com/KantanPro/news-crawler
 * Description: 指定されたニュースソースから自動的に記事を取得し、WordPressサイトに投稿として追加するプラグイン。YouTube動画のクロール機能も含む。
 * Version: 2.0.0
 * Author: KantanPro
 * Author URI: https://github.com/KantanPro
 * License: MIT
 * Text Domain: news-crawler
 */

// 直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

// プラグイン定数の定義
define('NEWS_CRAWLER_VERSION', '2.0.3');
define('NEWS_CRAWLER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NEWS_CRAWLER_PLUGIN_URL', plugin_dir_url(__FILE__));

// 必要なクラスファイルをインクルード
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

/**
 * メインプラグインクラス
 */
class NewsCrawlerMain {
    
    private static $instance = null;
    
    /**
     * シングルトンインスタンスを取得
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * コンストラクタ
     */
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        add_action('init', array($this, 'setup_cron'));
        add_action('wp_loaded', array($this, 'ensure_cron_setup'));
        
        // 投稿関連のフック
        $this->setup_post_hooks();
        
        // プラグインの有効化・無効化フック
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * プラグイン初期化
     */
    public function init() {
        // 言語ファイルの読み込み
        load_plugin_textdomain('news-crawler', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // 設定管理クラスを初期化
        if (class_exists('NewsCrawlerSettingsManager')) {
            new NewsCrawlerSettingsManager();
        }
        
        // ジャンル設定管理クラスを初期化
        if (class_exists('NewsCrawlerGenreSettings')) {
            new NewsCrawlerGenreSettings();
        }
        
        // YouTubeクローラークラスを初期化
        if (class_exists('NewsCrawlerYouTubeCrawler')) {
            new NewsCrawlerYouTubeCrawler();
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
    }
    
    /**
     * 投稿関連のフックを設定
     */
    private function setup_post_hooks() {
        // 投稿ステータス変更フック
        add_action('news_crawler_update_post_status', array($this, 'update_post_status'), 10, 2);
        
        // 投稿監視フック
        if (function_exists('wp_after_insert_post')) {
            // WordPress 5.6以降用
            add_action('wp_after_insert_post', array($this, 'save_post'), 10, 2);
            add_action('wp_after_insert_post', array($this, 'post_update'), 15, 4);
        } else {
            // 従来のWordPress用
            add_action('save_post', array($this, 'save_post'), 10, 2);
            add_action('save_post', array($this, 'post_update'), 15);
        }
        
        // 未来の投稿が公開される際のフック
        add_action('future_to_publish', array($this, 'future_to_publish'), 16);
        
        // メタデータ確実設定フック
        add_action('news_crawler_ensure_meta', array($this, 'ensure_meta'), 10, 1);
        
        // 投稿作成直後のメタデータ設定強化
        add_action('wp_insert_post', array($this, 'enhance_post_meta'), 10, 3);
    }
    
    /**
     * Cronスケジュールの設定
     */
    public function setup_cron() {
        // 自動投稿のcronアクション
        add_action('news_crawler_auto_posting_cron', array($this, 'execute_auto_posting'));
    }
    
    /**
     * Cronスケジュールの確実な設定
     */
    public function ensure_cron_setup() {
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
    
    /**
     * 投稿ステータス更新処理
     */
    public function update_post_status($post_id, $status) {
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
     * 投稿保存処理
     */
    public function save_post($post_id, $post) {
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
     * 投稿更新処理
     */
    public function post_update($post_id, $post = null, $updated = null, $post_before = null) {
        if ((empty($_POST) && !$this->auto_post_allowed($post_id)) || 
            (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || 
            wp_is_post_revision($post_id) || 
            isset($_POST['_inline_edit']) || 
            (defined('DOING_AJAX') && DOING_AJAX && !$this->auto_post_allowed($post_id)) || 
            !$this->in_post_type($post_id)) {
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
    public function future_to_publish($post) {
        $post_id = $post->ID;
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id) || !$this->in_post_type($post_id)) {
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
     * メタデータ確実設定
     */
    public function ensure_meta($post_id) {
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
            
            error_log('NewsCrawler: メタデータを確実に設定しました (ID: ' . $post_id . ')');
        }
    }
    
    /**
     * 投稿作成直後のメタデータ設定強化
     */
    public function enhance_post_meta($post_id, $post, $update) {
        if ($update || wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        
        // News Crawlerで作成された投稿かチェック
        $is_news_crawler_post = get_post_meta($post_id, '_news_crawler_created', true);
        if ($is_news_crawler_post) {
            error_log('NewsCrawler: 投稿作成直後にメタデータを強化設定しました (ID: ' . $post_id . ')');
        }
    }
    
    /**
     * 自動投稿実行
     */
    public function execute_auto_posting() {
        if (class_exists('NewsCrawlerGenreSettings')) {
            $genre_settings = new NewsCrawlerGenreSettings();
            if (method_exists($genre_settings, 'execute_auto_posting')) {
                $genre_settings->execute_auto_posting();
            }
        }
    }
    
    /**
     * 自動投稿が許可されているかチェック
     */
    private function auto_post_allowed($post_id) {
        $state = get_option('news_crawler_auto_post_allowed', '1');
        $return = ('0' !== $state) ? true : false;
        
        return apply_filters('news_crawler_auto_post_allowed', $return, $post_id);
    }
    
    /**
     * 投稿タイプが許可されているかチェック
     */
    private function in_post_type($post_id) {
        $post_types = $this->allowed_post_types();
        $type = get_post_type($post_id);
        
        if (in_array($type, $post_types, true)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * 許可されている投稿タイプの配列を取得
     */
    private function allowed_post_types($post_type = false) {
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
     * プラグイン有効化時の処理
     */
    public function activate() {
        // デフォルト設定を作成
        $default_settings = array(
            'youtube_api_key' => '',
            'openai_api_key' => '',
            'auto_featured_image' => true,
            'featured_image_method' => 'ai_generated',
            'auto_summary_generation' => true,
            'summary_generation_model' => 'gpt-3.5-turbo',
            'duplicate_check_strictness' => 'medium',
            'duplicate_check_period' => 30,
            'age_limit_enabled' => true,
            'age_limit_days' => 7
        );
        
        // 既存の設定がない場合のみデフォルト設定を保存
        if (!get_option('news_crawler_settings')) {
            update_option('news_crawler_settings', $default_settings);
        }
        
        // Cronスケジュールを設定
        if (!wp_next_scheduled('news_crawler_auto_posting_cron')) {
            wp_schedule_event(time(), 'hourly', 'news_crawler_auto_posting_cron');
        }
        
        error_log('NewsCrawler: プラグインが有効化されました');
    }
    
    /**
     * プラグイン無効化時の処理
     */
    public function deactivate() {
        // Cronスケジュールをクリア
        wp_clear_scheduled_hook('news_crawler_auto_posting_cron');
        
        error_log('NewsCrawler: プラグインが無効化されました');
    }
}

// プラグインを初期化
NewsCrawlerMain::get_instance();