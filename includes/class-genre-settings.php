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


        add_action('wp_ajax_force_auto_posting_execution', array($this, 'force_auto_posting_execution'));
        add_action('wp_ajax_test_twitter_connection', array($this, 'test_twitter_connection'));
        add_action('wp_ajax_test_age_limit_function', array($this, 'test_age_limit_function'));
        // サーバーcron対応のため、以下のハンドラーは削除
        // add_action('wp_ajax_check_auto_posting_schedule', array($this, 'check_auto_posting_schedule'));
        // add_action('wp_ajax_reset_cron_schedule', array($this, 'reset_cron_schedule'));
        // add_action('wp_ajax_debug_cron_schedule', array($this, 'debug_cron_schedule'));
        
        // 自動投稿のスケジュール処理（サーバーcron使用のため無効化）
        // add_action('news_crawler_auto_posting_cron', array($this, 'execute_auto_posting'));
        // add_action('wp_loaded', array($this, 'setup_auto_posting_cron'));
        
        // 個別ジャンルの自動投稿フックを動的に登録
        add_action('init', array($this, 'register_genre_hooks'));
        
        // ライセンス認証の処理を追加
        add_action('admin_init', array($this, 'handle_license_activation'));
        
        // ライセンス管理用スクリプトの読み込み
        add_action('admin_enqueue_scripts', array($this, 'enqueue_license_scripts'));
        
        // Cron設定クラスの初期化
        if (class_exists('NewsCrawlerCronSettings')) {
            new NewsCrawlerCronSettings();
        }
    }
    
    public function add_admin_menu() {
        // メインメニュー
        add_menu_page(
            'News Crawler ' . NEWS_CRAWLER_VERSION,
            'News Crawler',
            'manage_options',
            'news-crawler-main',
            array($this, 'main_admin_page'),
            'dashicons-rss',
            30
        );
        
        // 投稿設定サブメニュー
        add_submenu_page(
            'news-crawler-main',
            'News Crawler ' . NEWS_CRAWLER_VERSION . ' - 投稿設定',
            '投稿設定',
            'manage_options',
            'news-crawler-main',
            array($this, 'main_admin_page')
        );
        
        // 基本設定サブメニュー
        add_submenu_page(
            'news-crawler-main',
            'News Crawler ' . NEWS_CRAWLER_VERSION . ' - 基本設定',
            '基本設定',
            'manage_options',
            'news-crawler-basic',
            array($this, 'basic_settings_page')
        );
        
        // Cron設定サブメニュー
        add_submenu_page(
            'news-crawler-main',
            'News Crawler ' . NEWS_CRAWLER_VERSION . ' - Cron設定',
            'Cron設定',
            'manage_options',
            'news-crawler-cron-settings',
            array($this, 'cron_settings_page')
        );
        
        // ライセンス設定サブメニュー
        add_submenu_page(
            'news-crawler-main',
            'News Crawler ' . NEWS_CRAWLER_VERSION . ' - ライセンス設定',
            'ライセンス設定',
            'manage_options',
            'news-crawler-license',
            array($this, 'license_settings_page')
        );
        
        // OGP設定サブメニュー
        add_submenu_page(
            'news-crawler-main',
            'News Crawler ' . NEWS_CRAWLER_VERSION . ' - OGP設定',
            'OGP設定',
            'manage_options',
            'news-crawler-ogp-settings',
            array($this, 'ogp_settings_page')
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
        
        add_settings_field(
            'summary_to_excerpt',
            '要約をexcerptに設定',
            array($this, 'summary_to_excerpt_callback'),
            'news-crawler-basic',
            'summary_generation_settings'
        );
        
        add_settings_field(
            'auto_seo_title_generation',
            'SEOタイトル自動生成',
            array($this, 'auto_seo_title_generation_callback'),
            'news-crawler-basic',
            'summary_generation_settings'
        );
        
        // X（Twitter）自動シェア設定セクションは廃止
        
        // 重複チェック設定セクション
        add_settings_section(
            'duplicate_check_settings',
            '重複チェック設定',
            array($this, 'duplicate_check_section_callback'),
            'news-crawler-basic'
        );
        
        add_settings_field(
            'duplicate_check_strictness',
            '重複チェックの厳しさ',
            array($this, 'duplicate_check_strictness_callback'),
            'news-crawler-basic',
            'duplicate_check_settings'
        );
        
        add_settings_field(
            'duplicate_check_period',
            '重複チェック期間',
            array($this, 'duplicate_check_period_callback'),
            'news-crawler-basic',
            'duplicate_check_settings'
        );
        
        // コンテンツ取得期間制限設定セクション
        add_settings_section(
            'content_age_limit_settings',
            'コンテンツ取得期間制限',
            array($this, 'content_age_limit_section_callback'),
            'news-crawler-basic'
        );
        
        add_settings_field(
            'enable_content_age_limit',
            '期間制限を有効にする',
            array($this, 'enable_content_age_limit_callback'),
            'news-crawler-basic',
            'content_age_limit_settings'
        );
        
        add_settings_field(
            'content_age_limit_months',
            '過去何ヶ月まで取得するか',
            array($this, 'content_age_limit_months_callback'),
            'news-crawler-basic',
            'content_age_limit_settings'
        );
        
        add_settings_field(
            'twitter_hashtags',
            'ハッシュタグ',
            array($this, 'twitter_hashtags_callback'),
            'news-crawler-basic',
            'twitter_sharer_settings'
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
    
    public function auto_seo_title_generation_callback() {
        $options = get_option('news_crawler_basic_settings', array());
        $enabled = isset($options['auto_seo_title_generation']) ? $options['auto_seo_title_generation'] : false;
        echo '<input type="checkbox" name="news_crawler_basic_settings[auto_seo_title_generation]" value="1" ' . checked(1, $enabled, false) . ' />';
        echo '<label for="news_crawler_basic_settings[auto_seo_title_generation]">投稿作成時に自動でSEO最適化タイトルを生成する</label>';
        echo '<p class="description">OpenAI APIキーが設定されている必要があります。News Crawlerで設定されたジャンル名が【】で囲まれてタイトルの先頭に追加されます。</p>';
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
    
    public function summary_to_excerpt_callback() {
        $options = get_option('news_crawler_basic_settings', array());
        $enabled = isset($options['summary_to_excerpt']) ? $options['summary_to_excerpt'] : true;
        echo '<input type="checkbox" name="news_crawler_basic_settings[summary_to_excerpt]" value="1" ' . checked(1, $enabled, false) . ' />';
        echo '<label for="news_crawler_basic_settings[summary_to_excerpt]">生成された要約をshort excerptに設定する</label>';
        echo '<p class="description">AI要約生成時に、生成された要約を投稿のexcerptフィールドに自動設定します。</p>';
    }
    
    // X（Twitter）自動シェア設定セクション
    public function twitter_section_callback() {
        echo '<p>X（旧Twitter）への自動投稿に関する設定です。投稿作成後に自動的にXにシェアされます。</p>';
    }
    
    public function twitter_enabled_callback() {
        $options = get_option('news_crawler_basic_settings', array());
        $enabled = isset($options['twitter_enabled']) ? $options['twitter_enabled'] : false;
        echo '<input type="checkbox" name="news_crawler_basic_settings[twitter_enabled]" value="1" ' . checked(1, $enabled, false) . ' />';
        echo '<label for="news_crawler_basic_settings[twitter_enabled]">X（Twitter）への自動シェアを有効にする</label>';
        echo '<p class="description">投稿作成後に自動的にXにシェアされます。</p>';
    }
    
    public function twitter_bearer_token_callback() {
        $options = get_option('news_crawler_basic_settings', array());
        $bearer_token = isset($options['twitter_bearer_token']) ? $options['twitter_bearer_token'] : '';
        echo '<input type="text" name="news_crawler_basic_settings[twitter_bearer_token]" value="' . esc_attr($bearer_token) . '" size="50" />';
        echo '<p class="description">X Developer Portalで取得したBearer Tokenを入力してください。</p>';
    }
    
    public function twitter_api_key_callback() {
        $options = get_option('news_crawler_basic_settings', array());
        $api_key = isset($options['twitter_api_key']) ? $options['twitter_api_key'] : '';
        echo '<input type="text" name="news_crawler_basic_settings[twitter_api_key]" value="' . esc_attr($api_key) . '" size="50" />';
        echo '<p class="description">X Developer Portalで取得したAPI Key（Consumer Key）を入力してください。</p>';
    }
    
    public function twitter_api_secret_callback() {
        $options = get_option('news_crawler_basic_settings', array());
        $api_secret = isset($options['twitter_api_secret']) ? $options['twitter_api_secret'] : '';
        echo '<input type="password" name="news_crawler_basic_settings[twitter_api_secret]" value="' . esc_attr($api_secret) . '" size="50" />';
        echo '<p class="description">X Developer Portalで取得したAPI Secret（Consumer Secret）を入力してください。</p>';
    }
    
    public function twitter_access_token_callback() {
        $options = get_option('news_crawler_basic_settings', array());
        $access_token = isset($options['twitter_access_token']) ? $options['twitter_access_token'] : '';
        echo '<input type="text" name="news_crawler_basic_settings[twitter_access_token]" value="' . esc_attr($access_token) . '" size="50" />';
        echo '<p class="description">X Developer Portalで取得したAccess Tokenを入力してください。</p>';
    }
    
    public function twitter_access_token_secret_callback() {
        $options = get_option('news_crawler_basic_settings', array());
        $access_token_secret = isset($options['twitter_api_secret']) ? $options['twitter_api_secret'] : '';
        echo '<input type="password" name="news_crawler_basic_settings[twitter_access_token_secret]" value="' . esc_attr($access_token_secret) . '" size="50" />';
        echo '<p class="description">X Developer Portalで取得したAccess Token Secretを入力してください。</p>';
    }
    
    public function twitter_message_template_callback() {
        $options = get_option('news_crawler_basic_settings', array());
        $template = isset($options['twitter_message_template']) ? $options['twitter_message_template'] : '{title}';
        echo '<input type="text" name="news_crawler_basic_settings[twitter_message_template]" value="' . esc_attr($template) . '" size="50" />';
        echo '<p class="description">X投稿用のメッセージテンプレートを入力してください。{title}で投稿タイトルを挿入できます。</p>';
    }
    
    public function twitter_include_link_callback() {
        $options = get_option('news_crawler_basic_settings', array());
        $include_link = isset($options['twitter_include_link']) ? $options['twitter_include_link'] : true;
        echo '<input type="checkbox" name="news_crawler_basic_settings[twitter_include_link]" value="1" ' . checked(1, $include_link, false) . ' />';
        echo '<label for="news_crawler_basic_settings[twitter_include_link]">投稿へのリンクを含める</label>';
        echo '<p class="description">X投稿に投稿へのリンクを含めます。</p>';
    }
    
    public function twitter_hashtags_callback() {
        $options = get_option('news_crawler_basic_settings', array());
        $hashtags = isset($options['twitter_hashtags']) ? $options['twitter_hashtags'] : '';
        echo '<input type="text" name="news_crawler_basic_settings[twitter_hashtags]" value="' . esc_attr($hashtags) . '" size="50" />';
        echo '<p class="description">X投稿に含めるハッシュタグをスペース区切りで入力してください（例：ニュース テクノロジー）。</p>';
    }
    
    public function duplicate_check_section_callback() {
        echo '<p>重複チェックの厳しさと期間を設定できます。より厳しい設定にすると重複を防げますが、誤ってスキップされる可能性も高くなります。</p>';
    }
    
    public function duplicate_check_strictness_callback() {
        $options = get_option('news_crawler_basic_settings', array());
        $strictness = isset($options['duplicate_check_strictness']) ? $options['duplicate_check_strictness'] : 'medium';
        
        $strictness_levels = array(
            'low' => '緩い（類似度70%以上で重複判定）',
            'medium' => '標準（類似度80%以上で重複判定）',
            'high' => '厳しい（類似度90%以上で重複判定）'
        );
        
        echo '<select name="news_crawler_basic_settings[duplicate_check_strictness]">';
        foreach ($strictness_levels as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($value, $strictness, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">重複チェックの厳しさを選択してください。標準設定を推奨します。</p>';
    }
    
    public function duplicate_check_period_callback() {
        $options = get_option('news_crawler_basic_settings', array());
        $period = isset($options['duplicate_check_period']) ? $options['duplicate_check_period'] : '30';
        
        $periods = array(
            '7' => '7日間',
            '14' => '14日間',
            '30' => '30日間（推奨）',
            '60' => '60日間',
            '90' => '90日間'
        );
        
        echo '<select name="news_crawler_basic_settings[duplicate_check_period]">';
        foreach ($periods as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($value, $period, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">重複チェックを行う期間を選択してください。期間が長いほど重複を防げますが、処理時間が長くなります。</p>';
    }
    
    public function content_age_limit_section_callback() {
        echo '<p>古いコンテンツ（記事や動画）の取得を制限する設定です。何年も前の古いコンテンツが投稿されることを防げます。</p>';
        echo '<p><strong>例：</strong>「過去12ヶ月まで」に設定すると、1年以上前の記事や動画は自動的に除外されます。</p>';
    }
    
    public function enable_content_age_limit_callback() {
        $options = get_option('news_crawler_basic_settings', array());
        $enabled = isset($options['enable_content_age_limit']) ? $options['enable_content_age_limit'] : false;
        echo '<input type="checkbox" name="news_crawler_basic_settings[enable_content_age_limit]" value="1" ' . checked(1, $enabled, false) . ' />';
        echo '<label for="news_crawler_basic_settings[enable_content_age_limit]">古いコンテンツの取得を制限する</label>';
        echo '<p class="description">チェックを入れると、指定した期間より古いコンテンツは投稿されません。</p>';
    }
    
    public function content_age_limit_months_callback() {
        $options = get_option('news_crawler_basic_settings', array());
        $months = isset($options['content_age_limit_months']) ? $options['content_age_limit_months'] : '12';
        
        $month_options = array(
            '3' => '3ヶ月',
            '6' => '6ヶ月',
            '12' => '12ヶ月（1年）',
            '18' => '18ヶ月',
            '24' => '24ヶ月（2年）',
            '36' => '36ヶ月（3年）',
            '60' => '60ヶ月（5年）'
        );
        
        echo '<select name="news_crawler_basic_settings[content_age_limit_months]">';
        foreach ($month_options as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($value, $months, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">この期間より古いコンテンツは取得・投稿されません。適切な期間を選択してください。</p>';
    }
    
    public function featured_image_method_callback() {
        $options = get_option('news_crawler_basic_settings', array());
        $method = isset($options['featured_image_method']) ? $options['featured_image_method'] : 'ai'; // デフォルトを'ai'に変更
        
        $methods = array(
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
            $allowed_methods = array('ai', 'unsplash');
            $method = sanitize_text_field($input['featured_image_method']);
            $sanitized['featured_image_method'] = in_array($method, $allowed_methods) ? $method : 'ai';
        }
        
        if (isset($input['auto_summary_generation'])) {
            $sanitized['auto_summary_generation'] = (bool) $input['auto_summary_generation'];
        }
        
        if (isset($input['summary_generation_model'])) {
            $allowed_models = array('gpt-3.5-turbo', 'gpt-4', 'gpt-4-turbo');
            $model = sanitize_text_field($input['summary_generation_model']);
            $sanitized['summary_generation_model'] = in_array($model, $allowed_models) ? $model : 'gpt-3.5-turbo';
        }
        
        if (isset($input['summary_to_excerpt'])) {
            $sanitized['summary_to_excerpt'] = (bool) $input['summary_to_excerpt'];
        }
        
        if (isset($input['auto_seo_title_generation'])) {
            $sanitized['auto_seo_title_generation'] = (bool) $input['auto_seo_title_generation'];
        }
        

        
        // X（Twitter）自動シェア設定の処理
        if (isset($input['twitter_enabled'])) {
            $sanitized['twitter_enabled'] = (bool) $input['twitter_enabled'];
        }
        
        if (isset($input['twitter_bearer_token'])) {
            $sanitized['twitter_bearer_token'] = sanitize_text_field($input['twitter_bearer_token']);
        }
        
        if (isset($input['twitter_api_key'])) {
            $sanitized['twitter_api_key'] = sanitize_text_field($input['twitter_api_key']);
        }
        
        if (isset($input['twitter_api_secret'])) {
            $sanitized['twitter_api_secret'] = sanitize_text_field($input['twitter_api_secret']);
        }
        
        if (isset($input['twitter_access_token'])) {
            $sanitized['twitter_access_token'] = sanitize_text_field($input['twitter_access_token']);
        }
        
        if (isset($input['twitter_access_token_secret'])) {
            $sanitized['twitter_access_token_secret'] = sanitize_text_field($input['twitter_access_token_secret']);
        }
        
        if (isset($input['twitter_message_template'])) {
            $sanitized['twitter_message_template'] = sanitize_text_field($input['twitter_message_template']);
        }
        
        if (isset($input['twitter_include_link'])) {
            $sanitized['twitter_include_link'] = (bool) $input['twitter_include_link'];
        }
        
        if (isset($input['twitter_hashtags'])) {
            $sanitized['twitter_hashtags'] = sanitize_text_field($input['twitter_hashtags']);
        }
        
        // 重複チェック設定の処理
        if (isset($input['duplicate_check_strictness'])) {
            $allowed_strictness = array('low', 'medium', 'high');
            $strictness = sanitize_text_field($input['duplicate_check_strictness']);
            $sanitized['duplicate_check_strictness'] = in_array($strictness, $allowed_strictness) ? $strictness : 'medium';
        }
        
        if (isset($input['duplicate_check_period'])) {
            $allowed_periods = array('7', '14', '30', '60', '90');
            $period = sanitize_text_field($input['duplicate_check_period']);
            $sanitized['duplicate_check_period'] = in_array($period, $allowed_periods) ? $period : '30';
        }
        
        // コンテンツ取得期間制限設定の処理
        if (isset($input['enable_content_age_limit'])) {
            $sanitized['enable_content_age_limit'] = (bool) $input['enable_content_age_limit'];
        }
        
        if (isset($input['content_age_limit_months'])) {
            $allowed_months = array('3', '6', '12', '18', '24', '36', '60');
            $months = sanitize_text_field($input['content_age_limit_months']);
            $sanitized['content_age_limit_months'] = in_array($months, $allowed_months) ? $months : '12';
        }
        
        return $sanitized;
    }
    

    

    
    public function basic_settings_page() {
        ?>
        <div class="wrap">
            <h1>News Crawler <?php echo esc_html(NEWS_CRAWLER_VERSION); ?> - 基本設定</h1>
            
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
    
    public function ogp_settings_page() {
        // OGP設定クラスのインスタンスを作成してページを表示
        if (class_exists('NewsCrawlerOGPSettings')) {
            $ogp_settings = new NewsCrawlerOGPSettings();
            $ogp_settings->admin_page();
        } else {
            echo '<div class="wrap"><h1>News Crawler ' . esc_html(NEWS_CRAWLER_VERSION) . ' - OGP設定</h1><p>OGP設定クラスが見つかりません。</p></div>';
        }
    }
    
    /**
     * Cron設定ページの表示
     */
    public function cron_settings_page() {
        // Cron設定クラスのインスタンスを作成してページを表示
        if (class_exists('NewsCrawlerCronSettings')) {
            $cron_settings = new NewsCrawlerCronSettings();
            $cron_settings->admin_page();
        } else {
            echo '<div class="wrap"><h1>News Crawler ' . esc_html(NEWS_CRAWLER_VERSION) . ' - Cron設定</h1><p>Cron設定クラスが見つかりません。</p></div>';
        }
    }
    
    public function main_admin_page() {
        $genre_settings = $this->get_genre_settings();
        ?>
        <div class="wrap">
            <h1>News Crawler <?php echo esc_html(NEWS_CRAWLER_VERSION); ?> - 投稿設定</h1>
            
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
                    <h2>投稿設定の追加・編集</h2>
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
                        <div id="news-settings">
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
                                    <th scope="row">一度に引用する記事数</th>
                                    <td>
                                        <input type="number" id="max-articles" name="max_articles" value="1" min="1" max="50">
                                        <p class="description">一度に引用する記事の数（1-50件）</p>
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
                                    <div style="background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin-bottom: 15px;">
                                        <h4 style="margin-top: 0; color: #856404;">⚠️ 自動投稿設定について</h4>
                                        <p style="margin-bottom: 10px;">自動投稿は<strong>サーバーのcronジョブ</strong>を使用して実行されます。</p>
                                        <p style="margin-bottom: 0;">
                                            <strong>設定手順：</strong><br>
                                            1. <a href="<?php echo admin_url('admin.php?page=news-crawler-cron-settings'); ?>" target="_blank">News Crawler > Cron設定</a> でcronジョブを設定<br>
                                            2. サーバーのcrontabに設定を追加<br>
                                            3. この設定で自動投稿を有効化
                                        </p>
                                    </div>
                                    
                                    <label>
                                        <input type="checkbox" id="auto-posting" name="auto_posting" value="1">
                                        自動投稿を有効にする（サーバーcronジョブが設定されている場合）
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
                                                    <p class="description" style="margin: 5px 0 0 0; color: #d63638;">※ 実際の実行頻度はサーバーのcronジョブ設定に依存します</p>
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
                                                <th scope="row" style="padding: 5px 0;">開始実行日時</th>
                                                <td style="padding: 5px 0;">
                                                    <input type="datetime-local" id="start-execution-time" name="start_execution_time" style="width: 200px;">
                                                    <p class="description" style="margin: 5px 0 0 0;">自動投稿の開始実行日時を選択してください</p>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row" style="padding: 5px 0;">次回実行予定</th>
                                                <td style="padding: 5px 0;">
                                                    <span id="next-execution-time" style="color: #0073aa; font-weight: bold;">サーバーcronジョブで管理</span>
                                                    <p class="description" style="margin: 5px 0 0 0;">実際の実行スケジュールはサーバーのcronジョブ設定で管理されます</p>
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
                    <h2>保存済み投稿設定</h2>
                    <div id="genre-settings-list">
                        <?php $this->render_genre_settings_list($genre_settings); ?>
                    </div>
                </div>
                
                <!-- 強制実行ボタン -->
                <div class="card" style="max-width: none; margin-top: 20px;">
                    <h2>自動投稿実行</h2>
                    
                    <div style="margin-bottom: 20px; padding: 15px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">
                        <h3 style="margin-top: 0; color: #856404;">⚠️ 自動投稿実行</h3>
                        <p style="color: #856404;">自動投稿は<strong>サーバーのcronジョブ</strong>で実行されます。以下のボタンで強制実行できます。</p>
                        
                        <div style="margin: 15px 0;">
                            <button type="button" id="force-execution" class="button button-primary">強制実行（今すぐ）</button>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 10px; border-radius: 4px; margin-top: 10px;">
                            <h4 style="margin-top: 0; color: #495057;">📋 サーバーcron設定について</h4>
                            <p style="margin-bottom: 10px;">自動投稿のスケジュールは<strong>サーバーのcronジョブ</strong>で管理されます。</p>
                            <p style="margin-bottom: 0;">
                                <strong>設定確認：</strong> <a href="<?php echo admin_url('admin.php?page=news-crawler-cron-settings'); ?>" target="_blank">News Crawler > Cron設定</a> でcronジョブの設定を確認してください。
                            </p>
                        </div>
                        
                        <div id="test-result" style="margin-top: 15px; display: none;">
                            <div id="test-result-content" style="white-space: pre-wrap; background: #f7f7f7; padding: 15px; border: 1px solid #ccc; border-radius: 4px; max-height: 300px; overflow-y: auto;"></div>
                        </div>
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
                $('#youtube-settings').hide();
                if (contentType === 'youtube') {
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
            
            // 開始実行日時変更時
            $('#start-execution-time').change(function() {
                updateNextExecutionTime();
            });
            
            // 次回実行予定時刻を更新（投稿頻度を考慮）
            function updateNextExecutionTime() {
                var frequency = $('#posting-frequency').val();
                var customDays = $('#custom-frequency-days').val();
                var startTime = $('#start-execution-time').val();
                
                if (!startTime) {
                    $('#next-execution-time').text('未設定');
                    return;
                }
                
                var startDate = new Date(startTime);
                var now = new Date();
                var nextExecution = new Date(startDate);
                
                // 開始日時が未来の場合は、その日時が次回実行予定
                if (startDate > now) {
                    nextExecution = new Date(startDate);
                } else {
                    // 開始日時が過去の場合は、投稿頻度に基づいて次回実行予定を計算
                    var intervalMs = 0;
                    switch (frequency) {
                        case 'daily':
                            intervalMs = 24 * 60 * 60 * 1000; // 24時間
                            break;
                        case 'weekly':
                            intervalMs = 7 * 24 * 60 * 60 * 1000; // 7日
                            break;
                        case 'monthly':
                            intervalMs = 30 * 24 * 60 * 60 * 1000; // 30日
                            break;
                        case 'custom':
                            intervalMs = parseInt(customDays || 1) * 24 * 60 * 60 * 1000;
                            break;
                        default:
                            intervalMs = 24 * 60 * 60 * 1000;
                    }
                    
                    // 開始時刻から現在時刻までの経過時間を計算
                    var elapsed = now.getTime() - startDate.getTime();
                    
                    // 次回実行までの回数を計算
                    var cycles = Math.ceil(elapsed / intervalMs);
                    
                    // 次回実行時刻を計算
                    nextExecution = new Date(startDate.getTime() + (cycles * intervalMs));
                }
                
                var timeString = nextExecution.getFullYear() + '年' + 
                               (nextExecution.getMonth() + 1) + '月' + 
                               nextExecution.getDate() + '日 ' +
                               nextExecution.getHours().toString().padStart(2, '0') + ':' +
                               nextExecution.getMinutes().toString().padStart(2, '0');
                
                $('#next-execution-time').text(timeString);
            }
            
            // 初期表示時に開始実行日時を現在時刻に設定
            var now = new Date();
            var nowString = now.getFullYear() + '-' + 
                           (now.getMonth() + 1).toString().padStart(2, '0') + '-' + 
                           now.getDate().toString().padStart(2, '0') + 'T' +
                           now.getHours().toString().padStart(2, '0') + ':' +
                           now.getMinutes().toString().padStart(2, '0');
            $('#start-execution-time').val(nowString);
            
            // 初期表示時に次回実行予定時刻を更新
            updateNextExecutionTime();
            
            // 初期表示時にアイキャッチ設定を表示
            $('#featured-image-settings').show();
            
            // 初期表示時にニュース設定を表示
            $('#news-settings').show();
            
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
                    max_posts_per_execution: $('#max-posts-per-execution').val(),
                    start_execution_time: $('#start-execution-time').val(),
                    next_execution_display: $('#next-execution-time').text().trim().replace(/\n/g, ' ')
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
                $('#youtube-settings').hide();
                
                // 開始実行日時を現在時刻にリセット
                var now = new Date();
                var nowString = now.getFullYear() + '-' + 
                               (now.getMonth() + 1).toString().padStart(2, '0') + '-' + 
                               now.getDate().toString().padStart(2, '0') + 'T' +
                               now.getHours().toString().padStart(2, '0') + ':' +
                               now.getMinutes().toString().padStart(2, '0');
                $('#start-execution-time').val(nowString);
                
                // 次回実行予定時刻を更新
                updateNextExecutionTime();
            });
            

            
            // 不要なボタンのイベントハンドラーは削除（サーバーcron対応のため）
            
            // 強制実行
            $('#force-execution').click(function() {
                var button = $(this);
                var resultDiv = $('#test-result');
                var resultContent = $('#test-result-content');
                
                button.prop('disabled', true).text('実行中...');
                resultDiv.show();
                resultContent.html('自動投稿を強制実行中...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'force_auto_posting_execution',
                        nonce: '<?php echo wp_create_nonce('auto_posting_force_nonce'); ?>'
                    },
                                success: function(response) {
                if (response.success) {
                    resultContent.html('✅ 強制実行完了\n\n' + response.data + '\n\n詳細なログはWordPressのデバッグログで確認できます。');
                    // レポートを更新
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    resultContent.html('❌ 強制実行失敗\n\n' + response.data + '\n\n詳細なログはWordPressのデバッグログで確認できます。');
                }
            },
                    error: function() {
                        resultContent.html('❌ 通信エラーが発生しました');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('強制実行（今すぐ）');
                    }
                });
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
                        jQuery('#max-articles').val(setting.max_articles || 1);
                        jQuery('#youtube-channels').val(setting.youtube_channels ? setting.youtube_channels.join('\n') : '');
                        jQuery('#max-videos').val(setting.max_videos || 5);
                        jQuery('#embed-type').val(setting.embed_type || 'responsive');
                        jQuery('#post-categories').val(setting.post_categories ? setting.post_categories.join('\n') : 'blog');
                        jQuery('#post-status').val(setting.post_status || 'draft');
                        jQuery('#auto-featured-image').prop('checked', setting.auto_featured_image == 1).trigger('change');
                        jQuery('#featured-image-method').val(setting.featured_image_method || 'ai');
                        jQuery('#auto-posting').prop('checked', setting.auto_posting == 1).trigger('change');
                        jQuery('#posting-frequency').val(setting.posting_frequency || 'daily').trigger('change');
                        jQuery('#custom-frequency-days').val(setting.custom_frequency_days || 7);
                        jQuery('#max-posts-per-execution').val(setting.max_posts_per_execution || 3);
                        jQuery('#start-execution-time').val(setting.start_execution_time || '');
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
        .genre-settings-table th:nth-child(8),
        .genre-settings-table td:nth-child(8) {
            width: 120px;
            text-align: left;
            font-size: 12px;
        }
        .genre-report {
            background: #f9f9f9;
            border-left: 4px solid #0073aa;
        }
        .genre-report h3 {
            margin-top: 0;
            color: #0073aa;
        }
        .genre-report table {
            margin-top: 10px;
        }
        .genre-report details {
            margin-top: 15px;
        }
        .genre-report summary {
            cursor: pointer;
            font-weight: bold;
            color: #0073aa;
        }
        .genre-report summary:hover {
            color: #005a87;
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
        echo '<th>ID</th>';
        echo '<th>ジャンル名</th>';
        echo '<th>タイプ</th>';
        echo '<th>キーワード</th>';
        echo '<th>カテゴリー</th>';
        echo '<th>アイキャッチ</th>';
        echo '<th>自動投稿</th>';
        echo '<th>次回実行予定</th>';
        echo '<th>公開設定</th>';
        echo '<th>操作</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        $display_id = 1; // 表示用の連番
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
            // IDカラムを追加（連番を表示）
            echo '<td><strong>' . esc_html($display_id) . '</strong></td>';
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
            
            // 自動投稿設定の表示（サーバーcron対応）
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
                
                $auto_posting_status = '<span style="color: #00a32a; font-weight: bold;">✓ 有効</span><br>';
                $auto_posting_status .= '<small>頻度: ' . $frequency_labels[$frequency] . '</small><br>';
                $auto_posting_status .= '<small>最大: ' . $max_posts . '件/回</small><br>';
                $auto_posting_status .= '<small style="color: #0073aa;">サーバーcronで実行</small>';
            } else {
                $auto_posting_status = '<span style="color: #d63638;">❌ 無効</span>';
            }
            
            echo '<td>' . $auto_posting_status . '</td>';
            
            // 次回実行予定の表示（サーバーcron対応）
            $next_execution_display = '';
            if (isset($setting['auto_posting']) && $setting['auto_posting']) {
                $next_execution_display = '<span style="color: #0073aa; font-weight: bold;">サーバーcronで管理</span><br>';
                
                // 開始実行日時がある場合は表示
                if (!empty($setting['start_execution_time'])) {
                    $start_time = strtotime($setting['start_execution_time']);
                    $next_execution_display .= '<small>開始: ' . date('m/d H:i', $start_time) . '</small><br>';
                }
                
                $next_execution_display .= '<small style="color: #666;">Cron設定で確認</small>';
            } else {
                $next_execution_display = '<span style="color: #666;">-</span>';
            }
            
            echo '<td>' . $next_execution_display . '</td>';
            
            // 公開設定の表示
            $post_status = isset($setting['post_status']) ? $setting['post_status'] : 'draft';
            $status_labels = array(
                'draft' => '下書き',
                'publish' => '公開',
                'private' => '非公開',
                'pending' => '承認待ち'
            );
            $post_status_display = isset($status_labels[$post_status]) ? $status_labels[$post_status] : '下書き';
            echo '<td>' . esc_html($post_status_display) . '</td>';
            
            echo '<td class="action-buttons">';
            echo '<button type="button" class="button" onclick="editGenreSetting(\'' . esc_js($id) . '\')">編集</button>';
            echo '<button type="button" class="button" onclick="duplicateGenreSetting(\'' . esc_js($id) . '\', \'' . esc_js($setting['genre_name']) . '\')">複製</button>';
            echo '<button type="button" id="execute-btn-' . esc_attr($id) . '" class="button button-primary" onclick="executeGenreSetting(\'' . esc_js($id) . '\', \'' . esc_js($setting['genre_name']) . '\')">投稿を作成</button>';
            echo '<button type="button" class="button button-link-delete" onclick="deleteGenreSetting(\'' . esc_js($id) . '\', \'' . esc_js($setting['genre_name']) . '\')">削除</button>';
            echo '</td>';
            echo '</tr>';
            
            $display_id++; // 連番をインクリメント
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
        
        // next_execution_displayの値をクリーンアップ
        $raw_next_execution = $_POST['next_execution_display'] ?? '';
        $cleaned_next_execution = sanitize_text_field(trim(str_replace(["\n", "\r"], ' ', $raw_next_execution)));
        
        // デバッグ情報を記録
        error_log('Genre Settings Save - Raw next_execution_display: "' . $raw_next_execution . '"');
        error_log('Genre Settings Save - Cleaned next_execution_display: "' . $cleaned_next_execution . '"');
        
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
            'start_execution_time' => sanitize_text_field($_POST['start_execution_time'] ?? ''),
            'next_execution_display' => $cleaned_next_execution,
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
            $genre_id = $this->generate_sequential_genre_id();
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
            
            // 個別スケジュールを設定
            if (!empty($setting['start_execution_time'])) {
                $this->schedule_genre_auto_posting($genre_id, $setting);
            }
        } else {
            // 自動投稿が無効な場合、次回実行時刻とログをクリア
            error_log('Genre Settings Save - Auto posting disabled, clearing execution time and logs');
            delete_option('news_crawler_last_execution_' . $genre_id);
            
            // 個別スケジュールをクリア
            $hook_name = 'news_crawler_genre_auto_posting_' . $genre_id;
            wp_clear_scheduled_hook($hook_name);
            
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
        
        // 個別スケジュールをクリア
        $hook_name = 'news_crawler_genre_auto_posting_' . $genre_id;
        wp_clear_scheduled_hook($hook_name);
        
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
                'max_articles' => isset($setting['max_articles']) ? intval($setting['max_articles']) : 1,
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
            $debug_info[] = '  - 一度に引用する記事数: ' . $temp_options['max_articles'];
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
    
    /**
     * 連番のジャンルIDを生成
     */
    private function generate_sequential_genre_id() {
        $genre_settings = $this->get_genre_settings();
        $max_number = 0;
        
        // 既存のジャンルIDから最大の番号を取得
        foreach ($genre_settings as $genre_id => $setting) {
            if (preg_match('/^genre_(\d+)$/', $genre_id, $matches)) {
                $number = intval($matches[1]);
                if ($number > $max_number) {
                    $max_number = $number;
                }
            }
        }
        
        return 'genre_' . ($max_number + 1);
    }
    
    /**
     * ジャンルIDを連番表示用に変換
     */
    private function get_display_genre_id($genre_id) {
        // 既に連番形式の場合はそのまま返す
        if (preg_match('/^genre_(\d+)$/', $genre_id, $matches)) {
            return $matches[1];
        }
        
        // ランダム文字列の場合は、ジャンル設定の順序に基づいて連番を割り当て
        $genre_settings = $this->get_genre_settings();
        $counter = 1;
        
        foreach ($genre_settings as $id => $setting) {
            if ($id === $genre_id) {
                return $counter;
            }
            $counter++;
        }
        
        return $genre_id; // 見つからない場合は元のIDを返す
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
        $new_genre_id = $this->generate_sequential_genre_id();
        
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
        // 既存のスケジュールをクリア
        wp_clear_scheduled_hook('news_crawler_auto_posting_cron');
        
        // ジャンル設定を取得
        $genre_settings = $this->get_genre_settings();
        
        foreach ($genre_settings as $genre_id => $setting) {
            // 自動投稿が有効で、開始実行日時が設定されている場合のみスケジュール
            if (isset($setting['auto_posting']) && $setting['auto_posting'] && !empty($setting['start_execution_time'])) {
                $this->schedule_genre_auto_posting($genre_id, $setting);
            }
        }
        
        // 全体的なチェック用のcronも設定（1時間ごと）
        $current_time = current_time('timestamp');
        $start_time = $current_time + (60 * 60); // 現在時刻から1時間後
        
        // デバッグ情報を記録
        error_log('Auto Posting Cron - Current time: ' . date('Y-m-d H:i:s', $current_time));
        error_log('Auto Posting Cron - Scheduled start time: ' . date('Y-m-d H:i:s', $start_time));
        
        // スケジュールを設定
        $scheduled = wp_schedule_event($start_time, 'hourly', 'news_crawler_auto_posting_cron');
        
        if ($scheduled) {
            error_log('Auto Posting Cron - Successfully scheduled at: ' . date('Y-m-d H:i:s', $start_time));
        } else {
            error_log('Auto Posting Cron - Failed to schedule');
        }
    }
    
    /**
     * ジャンル別フックを動的に登録
     */
    public function register_genre_hooks() {
        $genre_settings = $this->get_genre_settings();
        
        foreach ($genre_settings as $genre_id => $setting) {
            $hook_name = 'news_crawler_genre_auto_posting_' . $genre_id;
            add_action($hook_name, array($this, 'execute_genre_auto_posting'), 10, 1);
        }
    }
    
    /**
     * 個別ジャンルの自動投稿スケジュール設定
     */
    private function schedule_genre_auto_posting($genre_id, $setting) {
        $hook_name = 'news_crawler_genre_auto_posting_' . $genre_id;
        
        // 既存のスケジュールをクリア
        wp_clear_scheduled_hook($hook_name);
        
        // 開始実行日時を取得
        $datetime = $setting['start_execution_time'];
        
        // WordPressのローカルタイムゾーンでのタイムスタンプを取得
        $local_timestamp = strtotime($datetime);
        
        // 現在時刻と比較（両方ともWordPressローカルタイム）
        $current_time = current_time('timestamp');
        
        if ($local_timestamp > $current_time) {
            // 未来の時刻の場合はそのまま使用
            $timestamp = $local_timestamp;
        } else {
            // 過去の時刻の場合は次回実行時刻を計算
            $timestamp = $this->calculate_next_execution_from_start_time($setting, $local_timestamp);
        }
        
        // UTCタイムスタンプに変換してcronに登録
        $utc_timestamp = get_gmt_from_date(date('Y-m-d H:i:s', $timestamp), 'U');
        
        // 単発イベントとしてスケジュール
        $scheduled = wp_schedule_single_event($utc_timestamp, $hook_name, array($genre_id));
        
        if ($scheduled) {
            error_log('Genre Auto Posting - Successfully scheduled for genre ' . $setting['genre_name'] . ' at: ' . date('Y-m-d H:i:s', $timestamp) . ' (Local) / ' . date('Y-m-d H:i:s', $utc_timestamp) . ' (UTC)');
        } else {
            error_log('Genre Auto Posting - Failed to schedule for genre ' . $setting['genre_name']);
        }
    }
    
    /**
     * 自動投稿の実行処理（全体チェック用）
     */
    public function execute_auto_posting() {
        error_log('Auto Posting Execution - Starting...');
        
        $genre_settings = $this->get_genre_settings();
        $current_time = current_time('timestamp');
        
        error_log('Auto Posting Execution - Found ' . count($genre_settings) . ' genre settings');
        error_log('Auto Posting Execution - Current time: ' . date('Y-m-d H:i:s', $current_time));
        
        $executed_count = 0;
        $skipped_count = 0;
        
        foreach ($genre_settings as $genre_id => $setting) {
            $display_id = $this->get_display_genre_id($genre_id);
            error_log('Auto Posting Execution - Processing genre: ' . $setting['genre_name'] . ' (ID: ' . $display_id . ')');
            
            // 自動投稿が無効または設定されていない場合はスキップ
            if (!isset($setting['auto_posting']) || !$setting['auto_posting']) {
                error_log('Auto Posting Execution - Genre ' . $setting['genre_name'] . ' has auto_posting disabled');
                $skipped_count++;
                continue;
            }
            
            error_log('Auto Posting Execution - Genre ' . $setting['genre_name'] . ' has auto_posting enabled');
            
            // 次回実行時刻をチェック
            $next_execution = $this->get_next_execution_time($setting);
            error_log('Auto Posting Execution - Genre ' . $setting['genre_name'] . ' next execution: ' . date('Y-m-d H:i:s', $next_execution));
            
            if ($next_execution > $current_time) {
                error_log('Auto Posting Execution - Genre ' . $setting['genre_name'] . ' not ready for execution yet');
                $skipped_count++;
                continue;
            }
            
            error_log('Auto Posting Execution - Executing genre: ' . $setting['genre_name']);
            
            // 自動投稿を実行
            $this->execute_auto_posting_for_genre($setting);
            $executed_count++;
            
            // 次回実行時刻を更新
            $this->update_next_execution_time($genre_id, $setting);
        }
        
        error_log('Auto Posting Execution - Completed. Executed: ' . $executed_count . ', Skipped: ' . $skipped_count);
    }
    
    /**
     * 個別ジャンルの自動投稿実行処理
     */
    public function execute_genre_auto_posting($genre_id) {
        error_log('Genre Auto Posting - Starting for genre ID: ' . $genre_id);
        
        $genre_settings = $this->get_genre_settings();
        
        if (!isset($genre_settings[$genre_id])) {
            error_log('Genre Auto Posting - Genre not found: ' . $genre_id);
            return;
        }
        
        $setting = $genre_settings[$genre_id];
        
        // 自動投稿が有効かチェック
        if (!isset($setting['auto_posting']) || !$setting['auto_posting']) {
            error_log('Genre Auto Posting - Auto posting disabled for genre: ' . $setting['genre_name']);
            return;
        }
        
        error_log('Genre Auto Posting - Executing for genre: ' . $setting['genre_name']);
        
        // 自動投稿を実行
        $this->execute_auto_posting_for_genre($setting);
        
        // 次回実行時刻を更新して次のスケジュールを設定
        $this->update_next_execution_time($genre_id, $setting);
        $this->schedule_genre_auto_posting($genre_id, $setting);
        
        error_log('Genre Auto Posting - Completed for genre: ' . $setting['genre_name']);
    }
    
    /**
     * 指定されたジャンルの自動投稿を実行
     */
    private function execute_auto_posting_for_genre($setting, $is_forced = false) {
        $genre_id = $setting['id'];
        $max_posts = isset($setting['max_posts_per_execution']) ? intval($setting['max_posts_per_execution']) : 3;
        
        $display_id = $this->get_display_genre_id($genre_id);
        error_log('Execute Auto Posting for Genre - Starting for genre: ' . $setting['genre_name'] . ' (ID: ' . $display_id . ')');
        
        try {
            // 実行前のチェック
            error_log('Execute Auto Posting for Genre - Performing pre-execution check...');
            $check_result = $this->pre_execution_check($setting);
            error_log('Execute Auto Posting for Genre - Pre-execution check result: ' . print_r($check_result, true));
            
            if (!$check_result['can_execute']) {
                error_log('Execute Auto Posting for Genre - Cannot execute: ' . $check_result['reason']);
                $this->log_auto_posting_execution($genre_id, 'skipped', $check_result['reason']);
                return;
            }
            
            error_log('Execute Auto Posting for Genre - Pre-execution check passed');
            
            // 投稿記事数上限をチェック
            error_log('Execute Auto Posting for Genre - Checking post limit...');
            $existing_posts = $this->count_recent_posts_by_genre($genre_id);
            error_log('Execute Auto Posting for Genre - Existing posts: ' . $existing_posts . ', Max posts: ' . $max_posts);
            
            if ($existing_posts >= $max_posts) {
                error_log('Execute Auto Posting for Genre - Post limit reached for genre: ' . $setting['genre_name']);
                error_log('Execute Auto Posting for Genre - Existing posts: ' . $existing_posts . ', Max posts: ' . $max_posts);
                $this->log_auto_posting_execution($genre_id, 'skipped', "投稿数上限に達しています（既存: {$existing_posts}件、上限: {$max_posts}件）");
                return;
            }
            
            // 実行可能な投稿数を計算
            $available_posts = $max_posts - $existing_posts;
            error_log('Execute Auto Posting for Genre - Available posts: ' . $available_posts . ' for genre: ' . $setting['genre_name']);
            
            // クロール実行
            error_log('Execute Auto Posting for Genre - Starting crawl execution...');
            $result = '';
            $post_id = null;
            
            if ($setting['content_type'] === 'news') {
                error_log('Execute Auto Posting for Genre - Executing news crawling...');
                $result = $this->execute_news_crawling_with_limit($setting, $available_posts);
                
                // 投稿IDを抽出（結果から投稿IDを取得）
                if (preg_match('/投稿ID:\s*(\d+)/', $result, $matches)) {
                    $post_id = intval($matches[1]);
                    error_log('Execute Auto Posting for Genre - Extracted post ID: ' . $post_id);
                }
            } elseif ($setting['content_type'] === 'youtube') {
                error_log('Execute Auto Posting for Genre - Executing YouTube crawling...');
                $result = $this->execute_youtube_crawling_with_limit($setting, $available_posts);
                
                // 投稿IDを抽出（結果から投稿IDを取得）
                if (preg_match('/投稿ID:\s*(\d+)/', $result, $matches)) {
                    $post_id = intval($matches[1]);
                    error_log('Execute Auto Posting for Genre - Extracted post ID: ' . $post_id);
                }
            }
            
            error_log('Execute Auto Posting for Genre - Crawl execution result: ' . $result);
            error_log('Execute Auto Posting for Genre - Extracted post ID: ' . ($post_id ?: 'Not found'));
            
            // 実行結果をログに記録（投稿IDを含める）
            error_log('Execute Auto Posting for Genre - Logging success result...');
            $this->log_auto_posting_execution($genre_id, 'success', "投稿作成完了: {$result}", $post_id);
            error_log('Execute Auto Posting for Genre - Success logged');
            
            // 次回実行スケジュールを更新
            $this->reschedule_next_execution($genre_id, $setting);
            
        } catch (Exception $e) {
            error_log('Execute Auto Posting for Genre - Exception occurred: ' . $e->getMessage());
            // エラーログを記録
            $this->log_auto_posting_execution($genre_id, 'error', "実行エラー: " . $e->getMessage());
        }
        
        error_log('Execute Auto Posting for Genre - Completed for genre: ' . $setting['genre_name']);
    }
    
    /**
     * 実行前のチェック
     */
    private function pre_execution_check($setting) {
        $result = array('can_execute' => true, 'reason' => '');
        
        error_log('Pre Execution Check - Starting check for genre: ' . $setting['genre_name']);
        
        // 基本設定のチェック
        if ($setting['content_type'] === 'youtube') {
            $basic_settings = get_option('news_crawler_basic_settings', array());
            if (empty($basic_settings['youtube_api_key'])) {
                $result['can_execute'] = false;
                $result['reason'] = 'YouTube APIキーが設定されていません';
                error_log('Pre Execution Check - YouTube API key not set');
                return $result;
            }
            error_log('Pre Execution Check - YouTube API key check passed');
        }
        
        // ニュースソースのチェック
        if ($setting['content_type'] === 'news') {
            if (empty($setting['news_sources'])) {
                $result['can_execute'] = false;
                $result['reason'] = 'ニュースソースが設定されていません';
                error_log('Pre Execution Check - News sources not set for news content type');
                return $result;
            }
            error_log('Pre Execution Check - News sources check passed: ' . implode(', ', $setting['news_sources']));
        }
        
        // YouTubeチャンネルのチェック
        if ($setting['content_type'] === 'youtube') {
            if (empty($setting['youtube_channels'])) {
                $result['can_execute'] = false;
                $result['reason'] = 'YouTubeチャンネルが設定されていません';
                error_log('Pre Execution Check - YouTube channels not set for YouTube content type');
                return $result;
            }
            error_log('Pre Execution Check - YouTube channels check passed: ' . implode(', ', $setting['youtube_channels']));
        }
        
        // キーワードのチェック
        if (empty($setting['keywords'])) {
            $result['can_execute'] = false;
            $result['reason'] = 'キーワードが設定されていません';
            error_log('Pre Execution Check - Keywords not set');
            return $result;
        }
        error_log('Pre Execution Check - Keywords check passed: ' . implode(', ', $setting['keywords']));
        
        error_log('Pre Execution Check - All checks passed for genre: ' . $setting['genre_name']);
        return $result;
    }
    
    /**
     * ニュースクロールを投稿数制限付きで実行
     */
    private function execute_news_crawling_with_limit($setting, $max_posts) {
        // 投稿数制限を適用してクロール実行
        $setting['max_articles'] = min($setting['max_articles'] ?? 10, $max_posts);
        return $this->execute_news_crawling($setting);
    }
    
    /**
     * YouTubeクロールを投稿数制限付きで実行
     */
    private function execute_youtube_crawling_with_limit($setting, $max_posts) {
        // 投稿数制限を適用してクロール実行
        $setting['max_videos'] = min($setting['max_videos'] ?? 5, $max_posts);
        return $this->execute_youtube_crawling($setting);
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
     * 開始時刻から次回実行時刻を計算
     */
    private function calculate_next_execution_from_start_time($setting, $start_time) {
        $current_time = current_time('timestamp');
        $frequency = $setting['posting_frequency'] ?? 'daily';
        
        // 頻度に応じた間隔を取得
        $interval = $this->get_frequency_interval($frequency, $setting);
        
        // 開始時刻から現在時刻までの経過時間を計算
        $elapsed = $current_time - $start_time;
        
        // 既に経過したサイクル数を計算（floor使用で正確な経過サイクル数を取得）
        $completed_cycles = floor($elapsed / $interval);
        
        // 次回実行時刻を計算（次のサイクル）
        $next_execution = $start_time + (($completed_cycles + 1) * $interval);
        
        // デバッグ情報をログに記録
        error_log('Next Execution Calculation - Start: ' . date('Y-m-d H:i:s', $start_time) . 
                  ', Current: ' . date('Y-m-d H:i:s', $current_time) . 
                  ', Interval: ' . $interval . 's (' . ($interval / 3600) . 'h)' .
                  ', Completed cycles: ' . $completed_cycles . 
                  ', Next: ' . date('Y-m-d H:i:s', $next_execution));
        
        return $next_execution;
    }
    
    /**
     * 現在時刻から次回実行時刻を計算
     */
    private function calculate_next_execution_from_now($setting, $now) {
        $frequency = $setting['posting_frequency'] ?? 'daily';
        $interval = $this->get_frequency_interval($frequency, $setting);
        
        return $now + $interval;
    }
    
    /**
     * 頻度に応じた間隔（秒）を取得
     */
    private function get_frequency_interval($frequency, $setting) {
        switch ($frequency) {
            case 'daily':
            case '毎日':
                return 24 * 60 * 60; // 24時間
            case 'weekly':
            case '1週間ごと':
                return 7 * 24 * 60 * 60; // 7日
            case 'monthly':
            case '1ヶ月ごと':
                return 30 * 24 * 60 * 60; // 30日
            case 'custom':
                $days = $setting['custom_frequency_days'] ?? 7;
                return $days * 24 * 60 * 60;
            default:
                return 24 * 60 * 60; // デフォルトは24時間
        }
    }
    
    /**
     * 実際の次回実行時刻を取得（cronスケジュールを優先）
     */
    private function get_actual_next_execution_time($genre_id, $setting) {
        // 1. 個別ジャンルのcronスケジュールをチェック
        $hook_name = 'news_crawler_genre_auto_posting_' . $genre_id;
        $next_cron = wp_next_scheduled($hook_name);
        
        if ($next_cron) {
            // WordPressのUTCタイムスタンプをローカルタイムに変換
            $local_timestamp = get_date_from_gmt(date('Y-m-d H:i:s', $next_cron), 'U');
            return array(
                'timestamp' => $local_timestamp,
                'source' => ' (cronスケジュール)'
            );
        }
        
        // 2. 全体のcronスケジュールをチェック
        $global_cron = wp_next_scheduled('news_crawler_auto_posting_cron');
        if ($global_cron) {
            $local_timestamp = get_date_from_gmt(date('Y-m-d H:i:s', $global_cron), 'U');
            return array(
                'timestamp' => $local_timestamp,
                'source' => ' (全体cronスケジュール)'
            );
        }
        
        // 3. cronが設定されていない場合は計算値を使用
        $calculated_time = $this->calculate_next_execution_time_for_display($setting);
        return array(
            'timestamp' => $calculated_time,
            'source' => ' (計算値 - cronが未設定)'
        );
    }

    /**
     * 表示用の次回実行時刻を計算（ジャンル別設定のスケジュールを正しく反映）
     */
    private function calculate_next_execution_time_for_display($setting) {
        $now = current_time('timestamp');
        
        // 開始実行日時が設定されている場合
        if (!empty($setting['start_execution_time'])) {
            $start_time = strtotime($setting['start_execution_time']);
            
            // 開始日時が現在時刻より後の場合は、その日時を次回実行時刻とする
            if ($start_time > $now) {
                return $start_time;
            }
            
            // 開始日時が過去の場合は、開始日時から投稿頻度に基づいて計算
            return $this->calculate_next_execution_from_start_time($setting, $start_time);
        }
        
        // 開始実行日時が設定されていない場合は、現在時刻から投稿頻度に基づいて計算
        return $this->calculate_next_execution_from_now($setting, $now);
    }
    
    /**
     * 投稿頻度のテキストを取得
     */
    private function get_frequency_text($frequency, $custom_days = 7) {
        switch ($frequency) {
            case 'daily':
            case '毎日':
                return '毎日';
            case 'weekly':
            case '1週間ごと':
                return '1週間ごと';
            case 'monthly':
            case '毎月':
            case '1ヶ月ごと':
                return '毎月';
            case 'custom':
                return $custom_days . '日ごと';
            default:
                return '毎日';
        }
    }
    
    /**
     * 次回実行スケジュールを更新（実行後に呼び出し）
     */
    private function reschedule_next_execution($genre_id, $setting) {
        error_log('Reschedule Next Execution - Starting for genre: ' . $setting['genre_name']);
        
        // 現在のスケジュールをクリア
        $hook_name = 'news_crawler_genre_auto_posting_' . $genre_id;
        wp_clear_scheduled_hook($hook_name);
        
        // 次回実行時刻を計算
        $next_execution = $this->calculate_next_execution_time_for_display($setting);
        
        // UTCタイムスタンプに変換してcronに登録
        $utc_timestamp = get_gmt_from_date(date('Y-m-d H:i:s', $next_execution), 'U');
        
        // 単発イベントとしてスケジュール
        $scheduled = wp_schedule_single_event($utc_timestamp, $hook_name, array($genre_id));
        
        if ($scheduled) {
            error_log('Reschedule Next Execution - Successfully rescheduled for genre ' . $setting['genre_name'] . ' at: ' . date('Y-m-d H:i:s', $next_execution));
        } else {
            error_log('Reschedule Next Execution - Failed to reschedule for genre ' . $setting['genre_name']);
        }
    }

    /**
     * 次回実行時刻を更新
     */
    private function update_next_execution_time($genre_id, $setting) {
        $now = current_time('timestamp');
        
        // 開始実行日時が設定されている場合
        if (!empty($setting['start_execution_time'])) {
            $start_time = strtotime($setting['start_execution_time']);
            
            // 開始日時が現在時刻より後の場合は、その日時を次回実行時刻とする
            if ($start_time > $now) {
                $next_execution_time = $start_time;
            } else {
                // 開始日時が過去の場合は、開始日時から投稿頻度に基づいて計算
                $next_execution_time = $this->calculate_next_execution_from_start_time($setting, $start_time);
            }
        } else {
            // 開始実行日時が設定されていない場合は、現在時刻から投稿頻度分後
            $next_execution_time = $this->calculate_next_execution_from_now($setting, $now);
        }
        
        // デバッグログ
        error_log('Update Next Execution Time - Genre ID: ' . $genre_id . ', Next execution: ' . date('Y-m-d H:i:s', $next_execution_time));
        
        // 最後の実行時刻を更新
        update_option('news_crawler_last_execution_' . $genre_id, $next_execution_time);
        
        // 次回実行時刻も保存
        update_option('news_crawler_next_execution_' . $genre_id, $next_execution_time);
    }
    
    /**
     * 自動投稿の実行ログを記録
     */
    private function log_auto_posting_execution($genre_id, $status, $message = '', $post_id = null) {
        error_log('Log Auto Posting Execution - Starting to log for genre ID: ' . $genre_id . ', status: ' . $status . ', message: ' . $message . ', post_id: ' . ($post_id ?: 'null'));
        
        $logs = get_option('news_crawler_auto_posting_logs', array());
        error_log('Log Auto Posting Execution - Current logs count: ' . count($logs));
        
        $new_log_entry = array(
            'genre_id' => $genre_id,
            'status' => $status,
            'message' => $message,
            'timestamp' => current_time('mysql'),
            'execution_time' => current_time('mysql') // execution_timeフィールドを追加
        );
        
        // 投稿IDが提供されている場合は追加
        if ($post_id) {
            $new_log_entry['post_id'] = $post_id;
            error_log('Log Auto Posting Execution - Added post_id: ' . $post_id);
        }
        
        error_log('Log Auto Posting Execution - New log entry: ' . print_r($new_log_entry, true));
        
        $logs[] = $new_log_entry;
        
        // ログは最新100件まで保持
        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }
        
        error_log('Log Auto Posting Execution - Logs after adding new entry: ' . count($logs));
        
        $update_result = update_option('news_crawler_auto_posting_logs', $logs);
        error_log('Log Auto Posting Execution - Update result: ' . ($update_result ? 'Success' : 'Failed'));
        
        // 更新後の確認
        $updated_logs = get_option('news_crawler_auto_posting_logs', array());
        error_log('Log Auto Posting Execution - Verification: updated logs count: ' . count($updated_logs));
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
     * 実行詳細情報を取得
     */
    private function get_execution_details($genre_id, $log) {
        $details = array();
        
        // 次回実行予定時刻
        $next_execution = get_option('news_crawler_next_execution_' . $genre_id);
        if ($next_execution) {
            $next_time = date('Y-m-d H:i:s', $next_execution);
            $details[] = array('label' => '次回実行予定', 'value' => $next_time);
        }
        
        // 最後の実行時刻
        $last_execution = get_option('news_crawler_last_execution_' . $genre_id);
        if ($last_execution) {
            $last_time = date('Y-m-d H:i:s', $last_execution);
            $details[] = array('label' => '最後の実行', 'value' => $last_time);
        }
        
        // スキップ理由の詳細分析
        if ($log['status'] === 'skipped') {
            $skip_reasons = $this->analyze_skip_reasons($genre_id);
            if (!empty($skip_reasons)) {
                $details[] = array('label' => 'スキップ理由', 'value' => implode(', ', $skip_reasons));
            }
        }
        
        return $details;
    }
    
    /**
     * スキップ理由を分析
     */
    private function analyze_skip_reasons($genre_id) {
        $reasons = array();
        $genre_settings = $this->get_genre_settings();
        
        if (!isset($genre_settings[$genre_id])) {
            return array('ジャンル設定が見つかりません');
        }
        
        $setting = $genre_settings[$genre_id];
        
        // 基本設定のチェック
        if ($setting['content_type'] === 'youtube') {
            $basic_settings = get_option('news_crawler_basic_settings', array());
            if (empty($basic_settings['youtube_api_key'])) {
                $reasons[] = 'YouTube APIキーが設定されていません';
            }
        }
        
        // ニュースソースのチェック
        if ($setting['content_type'] === 'news' && empty($setting['news_sources'])) {
            $reasons[] = 'ニュースソースが設定されていません';
        }
        
        // YouTubeチャンネルのチェック
        if ($setting['content_type'] === 'youtube' && empty($setting['youtube_channels'])) {
            $reasons[] = 'YouTubeチャンネルが設定されていません';
        }
        
        // キーワードのチェック
        if (empty($setting['keywords'])) {
            $reasons[] = 'キーワードが設定されていません';
        }
        
        return $reasons;
    }
    

    
    /**
     * X（Twitter）接続テスト用AJAXハンドラー
     */
    public function test_twitter_connection() {
        check_ajax_referer('twitter_connection_test_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('権限がありません');
        }
        
        // 基本設定からX（Twitter）設定を取得
        $basic_settings = get_option('news_crawler_basic_settings', array());
        
        if (empty($basic_settings['twitter_enabled'])) {
            wp_send_json_error(array('message' => 'X（Twitter）自動シェアが無効になっています'));
        }
        
        if (empty($basic_settings['twitter_bearer_token']) || empty($basic_settings['twitter_api_key']) || 
            empty($basic_settings['twitter_api_secret']) || empty($basic_settings['twitter_access_token']) || 
            empty($basic_settings['twitter_access_token_secret'])) {
            wp_send_json_error(array('message' => '必要な認証情報が不足しています'));
        }
        
        try {
            // デバッグ情報をログに記録
            error_log('Twitter connection test: Starting connection test');
            error_log('Twitter connection test: Bearer token length: ' . strlen($basic_settings['twitter_bearer_token']));
            
            // より基本的なエンドポイントで接続をテスト（権限問題を回避）
            $response = wp_remote_get('https://api.twitter.com/2/tweets/counts/recent?query=test', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $basic_settings['twitter_bearer_token']
                ),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                error_log('Twitter connection test: WP_Error: ' . $response->get_error_message());
                throw new Exception('リクエストエラー: ' . $response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            error_log('Twitter connection test: Response code: ' . $response_code);
            error_log('Twitter connection test: Response body: ' . $body);
            
            if ($response_code === 200) {
                error_log('Twitter connection test: Success - API connection working');
                wp_send_json_success(array('message' => '接続成功！Twitter API v2への接続が確認できました'));
            } else {
                $error_message = '不明なエラー';
                if (isset($data['errors'][0]['message'])) {
                    $error_message = $data['errors'][0]['message'];
                } elseif (isset($data['error'])) {
                    $error_message = $data['error'];
                } elseif ($response_code !== 200) {
                    $error_message = 'HTTPエラー: ' . $response_code;
                }
                error_log('Twitter connection test: API Error: ' . $error_message);
                throw new Exception('API エラー: ' . $error_message);
            }
        } catch (Exception $e) {
            error_log('Twitter connection test: Exception: ' . $e->getMessage());
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    

    
    // check_auto_posting_schedule メソッドは削除（サーバーcron対応のため）
    
    // reset_cron_schedule メソッドは削除（サーバーcron対応のため）
    
    /**
     * 自動投稿の強制実行用AJAXハンドラー
     */
    public function force_auto_posting_execution() {
        check_ajax_referer('auto_posting_force_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('権限がありません');
        }
        
        try {
            // デバッグ情報を記録
            error_log('Force Auto Posting Execution - Starting...');
            
            // 強制実行用の自動投稿処理を実行
            $this->execute_auto_posting_forced();
            
            // 実行後のログ確認
            $logs = get_option('news_crawler_auto_posting_logs', array());
            error_log('Force Auto Posting Execution - Logs after execution: ' . print_r($logs, true));
            
            $result = "自動投稿の強制実行が完了しました。\n\n";
            $result .= "実行結果は自動投稿実行レポートで確認できます。\n";
            $result .= "記録されたログ数: " . count($logs) . "件";
            
            wp_send_json_success($result);
            
        } catch (Exception $e) {
            error_log('Force Auto Posting Execution - Error: ' . $e->getMessage());
            wp_send_json_error('強制実行中にエラーが発生しました: ' . $e->getMessage());
        }
    }
    
    /**
     * 強制実行用の自動投稿処理（開始実行日時の制限を無視、既存の自動投稿設定のスケジュールを復元・維持）
     */
    private function execute_auto_posting_forced() {
        error_log('Force Auto Posting Execution - Starting forced execution...');
        
        $genre_settings = $this->get_genre_settings();
        $current_time = current_time('timestamp');
        
        error_log('Force Auto Posting Execution - Found ' . count($genre_settings) . ' genre settings');
        error_log('Force Auto Posting Execution - Current time: ' . date('Y-m-d H:i:s', $current_time));
        
        $executed_count = 0;
        $skipped_count = 0;
        
        foreach ($genre_settings as $genre_id => $setting) {
            $display_id = $this->get_display_genre_id($genre_id);
            error_log('Force Auto Posting Execution - Processing genre: ' . $setting['genre_name'] . ' (ID: ' . $display_id . ')');
            
            // 自動投稿が無効または設定されていない場合はスキップ
            if (!isset($setting['auto_posting']) || !$setting['auto_posting']) {
                error_log('Force Auto Posting Execution - Genre ' . $setting['genre_name'] . ' has auto_posting disabled');
                $skipped_count++;
                continue;
            }
            
            error_log('Force Auto Posting Execution - Genre ' . $setting['genre_name'] . ' has auto_posting enabled - FORCING EXECUTION');
            
            // 設定の詳細をログに記録
            error_log('Force Auto Posting Execution - Genre settings: ' . print_r($setting, true));
            
            // 強制実行時は開始実行日時の制限を無視して即座に実行
            // 次回実行時刻は既存の自動投稿設定のスケジュールを復元・維持
            $this->execute_auto_posting_for_genre($setting, true);
            $executed_count++;
            
            // 強制実行時は既存の自動投稿設定に基づいて正しいスケジュールを復元・維持
            $this->update_next_execution_time_forced($genre_id, $setting);
        }
        
        error_log('Force Auto Posting Execution - Completed. Executed: ' . $executed_count . ', Skipped: ' . $skipped_count);
    }
    
    /**
     * 強制実行用の次回実行時刻更新（既存の自動投稿設定のスケジュールを復元・維持）
     */
    private function update_next_execution_time_forced($genre_id, $setting) {
        // 強制実行時は既存の自動投稿設定に基づいて正しいスケジュールを復元・維持
        error_log('Force Auto Posting Execution - Restoring schedule based on existing auto posting settings for genre ' . $genre_id);
        
        $now = current_time('timestamp');
        $next_execution_time = $now;
        
        // 開始実行日時が設定されている場合は、その設定を優先
        if (!empty($setting['start_execution_time'])) {
            $start_time = strtotime($setting['start_execution_time']);
            
            // 開始日時が現在時刻より後の場合は、その日時を次回実行時刻とする
            if ($start_time > $now) {
                $next_execution_time = $start_time;
                error_log('Force Auto Posting Execution - Using start_execution_time for genre ' . $genre_id . ': ' . date('Y-m-d H:i:s', $next_execution_time));
            } else {
                // 開始日時が過去の場合は、開始日時から投稿頻度に基づいて計算
                $next_execution_time = $this->calculate_next_execution_from_start_time($setting, $start_time);
                error_log('Force Auto Posting Execution - Calculated from start_time for genre ' . $genre_id . ': ' . date('Y-m-d H:i:s', $next_execution_time));
            }
        } else {
            // 開始実行日時が設定されていない場合は、現在時刻から投稿頻度に基づいて計算
            $next_execution_time = $this->calculate_next_execution_from_now($setting, $now);
            error_log('Force Auto Posting Execution - Calculated from now for genre ' . $genre_id . ': ' . date('Y-m-d H:i:s', $next_execution_time));
        }
        
        // 正しいスケジュールを設定
        update_option('news_crawler_next_execution_' . $genre_id, $next_execution_time);
        
        error_log('Force Auto Posting Execution - Restored correct schedule for genre ' . $genre_id . ': ' . date('Y-m-d H:i:s', $next_execution_time));
    }
    

    

    
    /**
     * ニュースソースの可用性をテストして、実際に取得可能な記事数を返す
     */
    private function test_news_source_availability($setting) {
        $content_type = $setting['content_type'] ?? 'news';
        $available_articles = 0;
        
        try {
            if ($content_type === 'youtube') {
                // YouTubeチャンネルのテスト
                $available_articles = $this->test_youtube_source_availability($setting);
            } else {
                // ニュースソースのテスト
                $available_articles = $this->test_news_source_availability_news($setting);
            }
        } catch (Exception $e) {
            error_log('News source availability test error: ' . $e->getMessage());
            $available_articles = 0;
        }
        
        return $available_articles;
    }
    
    /**
     * YouTubeソースの可用性をテスト
     */
    private function test_youtube_source_availability($setting) {
        $youtube_channels = $setting['youtube_channels'] ?? array();
        $keywords = $setting['keywords'] ?? array();
        
        if (empty($youtube_channels) || empty($keywords)) {
            return 0;
        }
        
        // YouTube APIキーを取得
        $basic_settings = get_option('news_crawler_basic_settings', array());
        $youtube_api_key = $basic_settings['youtube_api_key'] ?? '';
        
        if (empty($youtube_api_key)) {
            return 0;
        }
        
        // 最初のチャンネルとキーワードでテスト実行
        $channel_id = $youtube_channels[0];
        $keyword = $keywords[0];
        
        $api_url = 'https://www.googleapis.com/youtube/v3/search';
        $params = array(
            'key' => $youtube_api_key,
            'channelId' => $channel_id,
            'q' => $keyword,
            'part' => 'snippet',
            'order' => 'date',
            'maxResults' => 5,
            'type' => 'video',
            'publishedAfter' => date('c', strtotime('-7 days')) // 過去7日間
        );
        
        $url = add_query_arg($params, $api_url);
        
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'sslverify' => false,
            'httpversion' => '1.1',
            'user-agent' => 'News Crawler Plugin/1.0'
        ));
        
        if (is_wp_error($response)) {
            return 0;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || !isset($data['items'])) {
            return 0;
        }
        
        return count($data['items']);
    }
    
    /**
     * ニュースソースの可用性をテスト
     */
    private function test_news_source_availability_news($setting) {
        $news_sources = $setting['news_sources'] ?? array();
        $keywords = $setting['keywords'] ?? array();
        
        if (empty($news_sources) || empty($keywords)) {
            return 0;
        }
        
        // 最初のニュースソースでテスト実行
        $news_source = $news_sources[0];
        $keyword = $keywords[0];
        
        // RSSフィードの場合はSimplePieを使用
        if (filter_var($news_source, FILTER_VALIDATE_URL) && $this->is_rss_feed($news_source)) {
            return $this->test_rss_feed_availability($news_source, $keyword);
        }
        
        // 通常のWebサイトの場合はHTMLパース
        return $this->test_webpage_availability($news_source, $keyword);
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
     * RSSフィードの可用性をテスト
     */
    private function test_rss_feed_availability($url, $keyword) {
        if (!class_exists('SimplePie')) {
            require_once(ABSPATH . WPINC . '/class-simplepie.php');
        }
        
        $feed = new SimplePie();
        $feed->set_feed_url($url);
        $feed->set_cache_location(WP_CONTENT_DIR . '/cache');
        $feed->set_cache_duration(300); // 5分
        $feed->init();
        
        if ($feed->error()) {
            return 0;
        }
        
        $items = $feed->get_items();
        $matching_items = 0;
        
        foreach ($items as $item) {
            $title = $item->get_title();
            $content = $item->get_content();
            
            if (stripos($title, $keyword) !== false || stripos($content, $keyword) !== false) {
                $matching_items++;
            }
        }
        
        return $matching_items;
    }
    
    /**
     * Webページの可用性をテスト
     */
    private function test_webpage_availability($url, $keyword) {
        $response = wp_remote_get($url, array(
            'timeout' => 15,
            'sslverify' => false,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ));
        
        if (is_wp_error($response)) {
            return 0;
        }
        
        $body = wp_remote_retrieve_body($response);
        
        // キーワードマッチングのテスト（簡易版）
        $matching_count = 0;
        if (stripos($body, $keyword) !== false) {
            $matching_count = 1; // 最低1件は存在することを示す
        }
        
        return $matching_count;
    }
    
    // debug_cron_schedule メソッドは削除（サーバーcron対応のため）
    
    /**
     * 期間制限機能のテスト
     */
    public function test_age_limit_function() {
        if (!current_user_can('manage_options')) {
            wp_die('権限がありません');
        }
        
        $basic_settings = get_option('news_crawler_basic_settings', array());
        $enabled = $basic_settings['enable_content_age_limit'] ?? false;
        $months = $basic_settings['content_age_limit_months'] ?? 12;
        
        $test_results = array();
        $test_results[] = '期間制限機能テスト結果:';
        $test_results[] = '有効/無効: ' . ($enabled ? '有効' : '無効');
        $test_results[] = '制限期間: ' . $months . 'ヶ月';
        $test_results[] = '';
        
        if ($enabled) {
            $cutoff_date = strtotime('-' . $months . ' months');
            $test_results[] = 'カットオフ日時: ' . date('Y-m-d H:i:s', $cutoff_date);
            $test_results[] = '';
            
            // テスト用の日付をいくつか確認
            $test_dates = array(
                '2024-01-01 10:00:00',
                '2024-06-01 10:00:00',
                '2024-12-01 10:00:00',
                date('Y-m-d H:i:s', strtotime('-1 month')),
                date('Y-m-d H:i:s', strtotime('-6 months')),
                date('Y-m-d H:i:s', strtotime('-1 year'))
            );
            
            $test_results[] = 'テスト日付の判定結果:';
            foreach ($test_dates as $test_date) {
                $test_timestamp = strtotime($test_date);
                $is_valid = $test_timestamp >= $cutoff_date;
                $test_results[] = '  ' . $test_date . ': ' . ($is_valid ? '取得対象' : '除外対象');
            }
        } else {
            $test_results[] = '期間制限が無効のため、すべてのコンテンツが取得対象です。';
        }
        
        wp_send_json_success(implode("\n", $test_results));
    }
    
    /**
     * ライセンス設定ページの表示
     */
    public function license_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'この設定ページにアクセスする権限がありません。', 'news-crawler' ) );
        }

        // ライセンス状態再確認の処理
        if ( isset( $_POST['news_crawler_license_recheck'] ) && wp_verify_nonce( $_POST['news_crawler_license_recheck_nonce'], 'news_crawler_license_recheck' ) ) {
            $this->handle_license_recheck();
        }

        // ライセンスマネージャーのインスタンスを取得
        if (class_exists('NewsCrawler_License_Manager')) {
            $license_manager = NewsCrawler_License_Manager::get_instance();
            $license_status = $license_manager->get_license_status();
        } else {
            $license_status = array(
                'status' => 'not_set',
                'message' => 'ライセンス管理機能が利用できません。',
                'icon' => 'dashicons-warning',
                'color' => '#f56e28'
            );
        }
        
        ?>
        <div class="wrap ktp-admin-wrap">
            <h1><span class="dashicons dashicons-lock" style="margin-right: 10px; font-size: 24px; width: 24px; height: 24px;"></span><?php echo esc_html__( 'ライセンス設定', 'news-crawler' ); ?></h1>
            
            <?php
            // 通知表示
            settings_errors( 'news_crawler_license' );
            ?>
            
            <div class="ktp-settings-container">
                <div class="ktp-settings-section">
                    <!-- ライセンスステータス表示 -->
                    <div class="ktp-license-status-display" style="margin-bottom: 30px; padding: 20px; background: #fff; border: 1px solid #ddd; border-radius: 5px;">
                        <h3 style="margin-top: 0;">
                            <span class="dashicons <?php echo esc_attr( $license_status['icon'] ); ?>" style="color: <?php echo esc_attr( $license_status['color'] ); ?>;"></span>
                            <?php echo esc_html__( 'ライセンスステータス', 'news-crawler' ); ?>
                        </h3>
                        <p style="font-size: 16px; margin: 10px 0;">
                            <strong><?php echo esc_html( $license_status['message'] ); ?></strong>
                        </p>
                        <?php if ( isset($license_status['is_dev_mode']) && ! empty( $license_status['is_dev_mode'] ) ) : ?>
                            <div class="ktp-dev-mode-toggle" style="margin-top: 15px; padding: 10px; background-color: #fff8e1; border: 1px solid #ffecb3; border-radius: 4px;">
                                <p style="margin: 0; display: flex; align-items: center; justify-content: space-between;">
                                    <span><span class="dashicons dashicons-info-outline"></span> 開発環境モードで動作中です。</span>
                                    <button id="toggle-dev-license" class="button button-secondary">
                                        <?php echo isset($license_manager) && $license_manager->is_dev_license_enabled() ? '開発用ライセンスを無効化' : '開発用ライセンスを有効化'; ?>
                                    </button>
                                    <span class="spinner" style="float: none; margin-left: 5px;"></span>
                                </p>
                            </div>
                        <?php endif; ?>
                        <?php if ( isset( $license_status['info'] ) && ! empty( $license_status['info'] ) ) : ?>
                            <div class="ktp-license-info-details" style="margin-top: 15px; padding: 15px; background: #f9f9f9; border-radius: 3px;">
                                <h4 style="margin-top: 0;"><?php echo esc_html__( 'ライセンス詳細', 'news-crawler' ); ?></h4>
                                <table class="form-table" style="margin: 0;">
                                    <?php
                                    // 表示する項目を制限
                                    $display_fields = array(
                                        'user_email' => 'User email',
                                        'start_date' => '開始',
                                        'end_date' => '終了',
                                        'remaining_days' => '残り日数'
                                    );
                                    
                                    foreach ( $display_fields as $key => $label ) :
                                        if ( isset( $license_status['info'][$key] ) ) :
                                    ?>
                                        <tr>
                                            <th style="padding: 5px 0; font-weight: normal;"><?php echo esc_html( $label ); ?></th>
                                            <td style="padding: 5px 0;"><?php echo esc_html( $license_status['info'][$key] ); ?></td>
                                        </tr>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- ライセンス認証フォーム -->
                    <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                        <form method="post" action="" id="news-crawler-license-form" style="display: flex; align-items: center; gap: 10px; margin: 0;">
                            <?php wp_nonce_field( 'news_crawler_license_activation', 'news_crawler_license_nonce' ); ?>
                            <input type="hidden" name="news_crawler_license_activation" value="1">

                            <label for="news_crawler_license_key" style="margin-bottom: 0;"><?php echo esc_html__( 'ライセンスキー', 'news-crawler' ); ?></label>

                            <input type="password"
                                   id="news_crawler_license_key"
                                   name="news_crawler_license_key"
                                   value="<?php echo esc_attr( get_option( 'news_crawler_license_key' ) ); ?>"
                                   style="width: 400px;"
                                   placeholder="KTPA-XXXXXX-XXXXXX-XXXX"
                                   autocomplete="off">

                            <?php submit_button( __( 'ライセンスを認証', 'news-crawler' ), 'primary', 'submit', false, ['style' => 'margin: 0;'] ); ?>
                            
                            <?php if ( isset($license_manager) && $license_manager->is_development_environment() ) : ?>
                                <button type="button" id="use-dev-license" class="button button-secondary" style="margin-left: 10px;">
                                    <?php echo esc_html__( 'テスト用キーを使用', 'news-crawler' ); ?>
                                </button>
                            <?php endif; ?>
                        </form>

                        <!-- ライセンス状態再確認フォーム -->
                        <?php if ( ! empty( get_option( 'news_crawler_license_key' ) ) ) : ?>
                            <form method="post" action="" style="margin: 0;">
                                <?php wp_nonce_field( 'news_crawler_license_recheck', 'news_crawler_license_recheck_nonce' ); ?>
                                <input type="hidden" name="news_crawler_license_recheck" value="1">
                                <?php submit_button( __( 'ライセンス状態を再確認', 'news-crawler' ), 'secondary', 'recheck_license', false, ['style' => 'margin: 0;'] ); ?>
                            </form>
                        <?php endif; ?>
                    </div>

                    <p class="description" style="padding-left: 8px; margin-top: 5px;">
                        <?php echo esc_html__( 'KantanPro License Managerから取得したライセンスキーを入力してください。', 'news-crawler' ); ?>
                    </p>
                    
                    <?php if ( isset($license_manager) && $license_manager->is_development_environment() ) : ?>
                        <!-- 開発環境用テストライセンスキー -->
                        <div style="margin-top: 15px; padding: 15px; background: #e7f3ff; border: 1px solid #b3d9ff; border-radius: 5px;">
                            <h4 style="margin-top: 0; color: #0066cc;">
                                <span class="dashicons dashicons-admin-tools" style="margin-right: 5px;"></span>
                                <?php echo esc_html__( '開発環境用テストライセンス', 'news-crawler' ); ?>
                            </h4>
                            <p style="margin: 10px 0;">
                                <?php echo esc_html__( '開発環境では、以下のテスト用ライセンスキーを使用できます：', 'news-crawler' ); ?>
                            </p>
                            <div style="background: #fff; padding: 10px; border-radius: 3px; font-family: monospace; font-size: 14px;">
                                <?php echo esc_html( $license_manager->get_display_dev_license_key() ); ?>
                            </div>
                            <p style="margin: 10px 0 0 0; font-size: 12px; color: #666;">
                                <?php echo esc_html__( 'このキーは開発環境でのみ有効で、本番環境では使用できません。', 'news-crawler' ); ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <!-- ライセンス情報 -->
                    <div class="ktp-license-info" style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-radius: 5px;">
                        <h3><?php echo esc_html__( 'ライセンスについて', 'news-crawler' ); ?></h3>
                        <p><?php echo esc_html__( 'News Crawlerプラグインの一部の機能を利用するには有効なライセンスキーが必要です。', 'news-crawler' ); ?></p>

                        <!-- 利用可能なライセンスプラン -->
                        <div style="margin: 20px 0; padding: 15px; background: #fff; border-radius: 5px; border-left: 4px solid #0073aa;">
                            <h4 style="margin-top: 0; color: #0073aa;"><?php echo esc_html__( '利用可能なライセンスプラン', 'news-crawler' ); ?></h4>
                            <ul style="margin-left: 20px; line-height: 1.8;">
                                <li><strong><?php echo esc_html__( '月額プラン', 'news-crawler' ); ?></strong>: 980円/月</li>
                                <li><strong><?php echo esc_html__( '年額プラン', 'news-crawler' ); ?></strong>: 9,980円/年</li>
                                <li><strong><?php echo esc_html__( '買い切りプラン', 'news-crawler' ); ?></strong>: 49,900円</li>
                            </ul>
                        </div>

                        <ul style="margin-left: 20px;">
                            <li><?php echo esc_html__( 'ライセンスキーはKantanPro公式サイトから購入できます。', 'news-crawler' ); ?></li>
                            <li><?php echo esc_html__( 'ライセンス認証により、AI要約生成などの高度な機能が有効になります。', 'news-crawler' ); ?></li>
                            <li><?php echo esc_html__( 'ライセンスキーに関する問題がございましたら、サポートまでお問い合わせください。', 'news-crawler' ); ?></li>
                        </ul>
                        <p>
                            <a href="https://www.kantanpro.com/" target="_blank" class="button button-primary">
                                <?php echo esc_html__( 'ライセンスを購入', 'news-crawler' ); ?>
                            </a>
                            <a href="mailto:support@kantanpro.com" class="button button-secondary">
                                <?php echo esc_html__( 'サポートに問い合わせる', 'news-crawler' ); ?>
                            </a>
                        </p>
                    </div>

                    <!-- 機能制限情報 -->
                    <div class="ktp-feature-limitations" style="margin-top: 30px; padding: 20px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 5px;">
                        <h3 style="margin-top: 0; color: #856404;">
                            <span class="dashicons dashicons-info" style="margin-right: 5px;"></span>
                            <?php echo esc_html__( '機能制限について', 'news-crawler' ); ?>
                        </h3>
                        <p><?php echo esc_html__( 'ライセンスキーがない場合、以下の機能が制限されます：', 'news-crawler' ); ?></p>
                        <ul style="margin-left: 20px; line-height: 1.8;">
                            <li><strong><?php echo esc_html__( 'AI要約生成', 'news-crawler' ); ?></strong>: OpenAI APIを使用した記事の自動要約</li>
                            <li><strong><?php echo esc_html__( '高度なアイキャッチ生成', 'news-crawler' ); ?></strong>: AIを使用した画像生成</li>
                            <li><strong><?php echo esc_html__( 'SEOタイトル最適化', 'news-crawler' ); ?></strong>: AIによるタイトルの最適化提案</li>
                            <li><strong><?php echo esc_html__( '高度なOGP管理', 'news-crawler' ); ?></strong>: 自動OGPタグ生成</li>
                        </ul>
                        <p style="margin-bottom: 0;">
                            <em><?php echo esc_html__( '基本的なニュースクロール機能は無料でご利用いただけます。', 'news-crawler' ); ?></em>
                        </p>
                    </div>

                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * ライセンス状態再確認の処理
     */
    private function handle_license_recheck() {
        $license_key = get_option( 'news_crawler_license_key' );
        
        if ( empty( $license_key ) ) {
            add_settings_error( 'news_crawler_license', 'empty_key', __( 'ライセンスキーが設定されていません。', 'news-crawler' ), 'error' );
            return;
        }

        // ライセンスマネージャーのインスタンスを取得
        if (class_exists('NewsCrawler_License_Manager')) {
            $license_manager = NewsCrawler_License_Manager::get_instance();
            
            // 強制的にライセンスを再検証
            $result = $license_manager->verify_license( $license_key );
            
            if ( $result['success'] ) {
                // ライセンスが有効な場合、情報を更新
                update_option( 'news_crawler_license_status', 'active' );
                update_option( 'news_crawler_license_info', $result['data'] );
                update_option( 'news_crawler_license_verified_at', current_time( 'timestamp' ) );
                
                add_settings_error( 'news_crawler_license', 'recheck_success', __( 'ライセンス状態の再確認が完了しました。ライセンスは有効です。', 'news-crawler' ), 'success' );
            } else {
                // ライセンスが無効な場合、ステータスを更新
                update_option( 'news_crawler_license_status', 'invalid' );
                error_log( 'NewsCrawler License: License recheck failed: ' . $result['message'] );
                
                add_settings_error( 'news_crawler_license', 'recheck_failed', __( 'ライセンス状態の再確認が完了しました。ライセンスは無効です。', 'news-crawler' ) . ' (' . $result['message'] . ')', 'error' );
            }
        } else {
            add_settings_error( 'news_crawler_license', 'license_manager_not_found', __( 'ライセンス管理機能が利用できません。', 'news-crawler' ), 'error' );
        }
    }
    
    /**
     * ライセンス認証の処理
     */
    public function handle_license_activation() {
        if ( ! isset( $_POST['news_crawler_license_activation'] ) || ! wp_verify_nonce( $_POST['news_crawler_license_nonce'], 'news_crawler_license_activation' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'この操作を実行する権限がありません。', 'news-crawler' ) );
        }

        $license_key = sanitize_text_field( $_POST['news_crawler_license_key'] ?? '' );
        
        if ( empty( $license_key ) ) {
            add_settings_error( 'news_crawler_license', 'empty_key', __( 'ライセンスキーを入力してください。', 'news-crawler' ), 'error' );
            return;
        }

        if (class_exists('NewsCrawler_License_Manager')) {
            $license_manager = NewsCrawler_License_Manager::get_instance();
            $result = $license_manager->verify_license( $license_key );
            
            if ( $result['success'] ) {
                // Save license key
                update_option( 'news_crawler_license_key', $license_key );
                update_option( 'news_crawler_license_status', 'active' );
                update_option( 'news_crawler_license_info', $result['data'] );
                update_option( 'news_crawler_license_verified_at', current_time( 'timestamp' ) );
                
                add_settings_error( 'news_crawler_license', 'activation_success', __( 'ライセンスが正常に認証されました。', 'news-crawler' ), 'success' );
            } else {
                add_settings_error( 'news_crawler_license', 'activation_failed', $result['message'], 'error' );
            }
        } else {
            add_settings_error( 'news_crawler_license', 'license_manager_not_found', __( 'ライセンス管理機能が利用できません。', 'news-crawler' ), 'error' );
        }
    }
    
    /**
     * ライセンス管理用スクリプトの読み込み
     */
    public function enqueue_license_scripts($hook) {
        // ライセンス設定ページでのみ読み込み
        if ($hook === 'news-crawler_page_news-crawler-license') {
            wp_enqueue_script(
                'news-crawler-license-manager',
                NEWS_CRAWLER_PLUGIN_URL . 'assets/js/license-manager.js',
                array('jquery'),
                NEWS_CRAWLER_VERSION,
                true
            );
            
            // AJAX用のデータをローカライズ
            wp_localize_script('news-crawler-license-manager', 'news_crawler_license_ajax', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('news_crawler_license_nonce'),
                'dev_license_key' => isset($license_manager) ? $license_manager->get_development_license_key() : '',
                'is_development' => isset($license_manager) ? $license_manager->is_development_environment() : false,
                'strings' => array(
                    'verifying' => __( '認証中...', 'news-crawler' ),
                    'success'   => __( 'ライセンスが正常に認証されました。', 'news-crawler' ),
                    'error'     => __( 'ライセンスの認証に失敗しました。', 'news-crawler' ),
                    'network_error' => __( '通信エラーが発生しました。', 'news-crawler' )
                )
            ));
        }
    }
}