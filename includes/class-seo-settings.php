<?php
/**
 * SEO設定管理クラス
 * 
 * 投稿のSEO最適化に関する設定を管理
 */

// 直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

class NewsCrawlerSeoSettings {
    
    private $option_name = 'news_crawler_seo_settings';
    
    public function __construct() {
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * 管理画面スクリプトを読み込み
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'news-crawler') !== false) {
            wp_enqueue_script(
                'news-crawler-seo-admin',
                NEWS_CRAWLER_PLUGIN_URL . 'assets/js/seo-admin.js',
                array('jquery'),
                NEWS_CRAWLER_VERSION,
                true
            );
        }
    }
    
    /**
     * 設定を初期化
     */
    public function admin_init() {
        register_setting(
            $this->option_name,
            $this->option_name,
            array(
                'sanitize_callback' => array($this, 'sanitize_settings'),
                'type' => 'array',
                'default' => array(),
                'show_in_rest' => false,
                'capability' => 'manage_options'
            )
        );
        
        // SEO設定セクション
        add_settings_section(
            'seo_settings',
            'SEO設定',
            array($this, 'seo_section_callback'),
            'news-crawler-settings-seo'
        );
        
        // メタタグ設定
        add_settings_field(
            'auto_meta_description',
            'メタディスクリプション自動生成',
            array($this, 'auto_meta_description_callback'),
            'news-crawler-settings-seo',
            'seo_settings'
        );
        
        add_settings_field(
            'meta_description_length',
            'メタディスクリプション文字数',
            array($this, 'meta_description_length_callback'),
            'news-crawler-settings-seo',
            'seo_settings'
        );
        
        add_settings_field(
            'auto_meta_keywords',
            'メタキーワード自動生成',
            array($this, 'auto_meta_keywords_callback'),
            'news-crawler-settings-seo',
            'seo_settings'
        );
        
        add_settings_field(
            'meta_keywords_count',
            'メタキーワード数',
            array($this, 'meta_keywords_count_callback'),
            'news-crawler-settings-seo',
            'seo_settings'
        );
        
        // OGP設定
        add_settings_field(
            'auto_ogp_tags',
            'OGPタグ自動生成',
            array($this, 'auto_ogp_tags_callback'),
            'news-crawler-settings-seo',
            'seo_settings'
        );
        
        // 構造化データ設定
        add_settings_field(
            'auto_structured_data',
            '構造化データ自動生成',
            array($this, 'auto_structured_data_callback'),
            'news-crawler-settings-seo',
            'seo_settings'
        );
        
        add_settings_field(
            'structured_data_type',
            '構造化データタイプ',
            array($this, 'structured_data_type_callback'),
            'news-crawler-settings-seo',
            'seo_settings'
        );
        
        // キーワード最適化設定（最初に配置）
        add_settings_field(
            'keyword_optimization_enabled',
            'キーワード最適化機能',
            array($this, 'keyword_optimization_enabled_callback'),
            'news-crawler-settings-seo',
            'seo_settings'
        );
        
        add_settings_field(
            'target_keywords',
            'ターゲットキーワード',
            array($this, 'target_keywords_callback'),
            'news-crawler-settings-seo',
            'seo_settings'
        );
        
        // タイトル最適化設定
        add_settings_field(
            'auto_title_optimization',
            'タイトル最適化',
            array($this, 'auto_title_optimization_callback'),
            'news-crawler-settings-seo',
            'seo_settings'
        );
        
        add_settings_field(
            'title_max_length',
            'タイトル最大文字数',
            array($this, 'title_max_length_callback'),
            'news-crawler-settings-seo',
            'seo_settings'
        );
        
        add_settings_field(
            'title_include_site_name',
            'タイトルにサイト名を含める',
            array($this, 'title_include_site_name_callback'),
            'news-crawler-settings-seo',
            'seo_settings'
        );
    }
    
    /**
     * SEO設定セクションのコールバック
     */
    public function seo_section_callback() {
        echo '<p>投稿のSEO最適化に関する設定を行います。</p>';
    }
    
    /**
     * メタディスクリプション自動生成のコールバック
     */
    public function auto_meta_description_callback() {
        $settings = get_option($this->option_name, array());
        $value = isset($settings['auto_meta_description']) ? $settings['auto_meta_description'] : true;
        echo '<input type="hidden" name="' . $this->option_name . '[auto_meta_description]" value="0" />';
        echo '<input type="checkbox" name="' . $this->option_name . '[auto_meta_description]" value="1" ' . checked(1, $value, false) . ' />';
        echo '<p class="description">投稿作成時に自動でメタディスクリプションを生成します。</p>';
    }
    
    /**
     * メタディスクリプション文字数のコールバック
     */
    public function meta_description_length_callback() {
        $settings = get_option($this->option_name, array());
        $value = isset($settings['meta_description_length']) ? $settings['meta_description_length'] : 160;
        echo '<input type="number" name="' . $this->option_name . '[meta_description_length]" value="' . esc_attr($value) . '" min="100" max="300" />';
        echo '<span class="description">文字</span>';
        echo '<p class="description">メタディスクリプションの文字数を設定してください（推奨：120-160文字）。</p>';
    }
    
    /**
     * メタキーワード自動生成のコールバック
     */
    public function auto_meta_keywords_callback() {
        $settings = get_option($this->option_name, array());
        $value = isset($settings['auto_meta_keywords']) ? $settings['auto_meta_keywords'] : false;
        echo '<input type="hidden" name="' . $this->option_name . '[auto_meta_keywords]" value="0" />';
        echo '<input type="checkbox" name="' . $this->option_name . '[auto_meta_keywords]" value="1" ' . checked(1, $value, false) . ' />';
        echo '<p class="description">投稿作成時に自動でメタキーワードを生成します。</p>';
    }
    
    /**
     * メタキーワード数のコールバック
     */
    public function meta_keywords_count_callback() {
        $settings = get_option($this->option_name, array());
        $value = isset($settings['meta_keywords_count']) ? $settings['meta_keywords_count'] : 5;
        echo '<input type="number" name="' . $this->option_name . '[meta_keywords_count]" value="' . esc_attr($value) . '" min="1" max="20" />';
        echo '<span class="description">個</span>';
        echo '<p class="description">生成するメタキーワードの数を設定してください。</p>';
    }
    
    /**
     * OGPタグ自動生成のコールバック
     */
    public function auto_ogp_tags_callback() {
        $settings = get_option($this->option_name, array());
        $value = isset($settings['auto_ogp_tags']) ? $settings['auto_ogp_tags'] : true;
        echo '<input type="hidden" name="' . $this->option_name . '[auto_ogp_tags]" value="0" />';
        echo '<input type="checkbox" name="' . $this->option_name . '[auto_ogp_tags]" value="1" ' . checked(1, $value, false) . ' />';
        echo '<p class="description">投稿作成時に自動でOGPタグを生成し、アイキャッチ画像をOGP画像に設定します。</p>';
    }
    
    /**
     * 構造化データ自動生成のコールバック
     */
    public function auto_structured_data_callback() {
        $settings = get_option($this->option_name, array());
        $value = isset($settings['auto_structured_data']) ? $settings['auto_structured_data'] : true;
        echo '<input type="hidden" name="' . $this->option_name . '[auto_structured_data]" value="0" />';
        echo '<input type="checkbox" name="' . $this->option_name . '[auto_structured_data]" value="1" ' . checked(1, $value, false) . ' />';
        echo '<p class="description">投稿作成時に自動で構造化データを生成します。</p>';
    }
    
    /**
     * 構造化データタイプのコールバック
     */
    public function structured_data_type_callback() {
        $settings = get_option($this->option_name, array());
        $value = isset($settings['structured_data_type']) ? $settings['structured_data_type'] : 'article';
        $options = array(
            'article' => 'Article（記事）',
            'news_article' => 'NewsArticle（ニュース記事）',
            'blog_posting' => 'BlogPosting（ブログ投稿）'
        );
        echo '<select name="' . $this->option_name . '[structured_data_type]">';
        foreach ($options as $key => $label) {
            echo '<option value="' . $key . '" ' . selected($key, $value, false) . '>' . $label . '</option>';
        }
        echo '</select>';
        echo '<p class="description">生成する構造化データのタイプを選択してください。</p>';
    }
    
    /**
     * ターゲットキーワードのコールバック
     */
    public function target_keywords_callback() {
        $settings = get_option($this->option_name, array());
        $value = isset($settings['target_keywords']) ? $settings['target_keywords'] : '';
        echo '<textarea name="' . $this->option_name . '[target_keywords]" rows="3" cols="50" style="width: 100%; max-width: 500px;">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">投稿作成時に最適化するキーワードを入力してください。複数のキーワードは改行またはカンマで区切ってください。</p>';
    }
    
    /**
     * キーワード最適化機能のコールバック
     */
    public function keyword_optimization_enabled_callback() {
        $settings = get_option($this->option_name, array());
        $value = isset($settings['keyword_optimization_enabled']) ? $settings['keyword_optimization_enabled'] : true;
        echo '<input type="hidden" name="' . $this->option_name . '[keyword_optimization_enabled]" value="0" />';
        echo '<input type="checkbox" name="' . $this->option_name . '[keyword_optimization_enabled]" value="1" ' . checked(1, $value, false) . ' />';
        echo '<p class="description">AI要約生成時にターゲットキーワードを考慮した最適化を行います。</p>';
    }
    
    /**
     * タイトル最適化のコールバック
     */
    public function auto_title_optimization_callback() {
        $settings = get_option($this->option_name, array());
        $value = isset($settings['auto_title_optimization']) ? $settings['auto_title_optimization'] : true;
        echo '<input type="hidden" name="' . $this->option_name . '[auto_title_optimization]" value="0" />';
        echo '<input type="checkbox" name="' . $this->option_name . '[auto_title_optimization]" value="1" ' . checked(1, $value, false) . ' />';
        echo '<p class="description">投稿タイトルをSEO最適化します。</p>';
    }
    
    /**
     * タイトル最大文字数のコールバック
     */
    public function title_max_length_callback() {
        $settings = get_option($this->option_name, array());
        $value = isset($settings['title_max_length']) ? $settings['title_max_length'] : 60;
        echo '<input type="number" name="' . $this->option_name . '[title_max_length]" value="' . esc_attr($value) . '" min="30" max="100" />';
        echo '<span class="description">文字</span>';
        echo '<p class="description">タイトルの最大文字数を設定してください（推奨：30-60文字）。</p>';
    }
    
    /**
     * タイトルにサイト名を含めるのコールバック
     */
    public function title_include_site_name_callback() {
        $settings = get_option($this->option_name, array());
        $value = isset($settings['title_include_site_name']) ? $settings['title_include_site_name'] : false;
        echo '<input type="hidden" name="' . $this->option_name . '[title_include_site_name]" value="0" />';
        echo '<input type="checkbox" name="' . $this->option_name . '[title_include_site_name]" value="1" ' . checked(1, $value, false) . ' />';
        echo '<p class="description">タイトルにサイト名を含めます（例：記事タイトル | サイト名）。</p>';
    }
    
    /**
     * 設定をサニタイズ
     */
    public function sanitize_settings($input) {
        $existing_options = get_option($this->option_name, array());
        $sanitized = is_array($existing_options) ? $existing_options : array();
        $input = is_array($input) ? $input : array();

        // チェックボックス
        $checkboxes = array(
            'auto_meta_description', 
            'auto_meta_keywords', 
            'auto_ogp_tags',
            'auto_structured_data',
            'auto_title_optimization',
            'title_include_site_name',
            'keyword_optimization_enabled'
        );
        foreach ($checkboxes as $checkbox) {
            if (array_key_exists($checkbox, $input)) {
                $sanitized[$checkbox] = $input[$checkbox] ? true : false;
            }
        }

        // 数値
        $numbers = array(
            'meta_description_length' => array('min' => 100, 'max' => 300),
            'meta_keywords_count' => array('min' => 1, 'max' => 20),
            'title_max_length' => array('min' => 30, 'max' => 100)
        );
        foreach ($numbers as $number => $limits) {
            if (array_key_exists($number, $input)) {
                $sanitized[$number] = max($limits['min'], min($limits['max'], intval($input[$number])));
            }
        }

        // セレクトボックス
        $selects = array('structured_data_type');
        foreach ($selects as $select) {
            if (array_key_exists($select, $input)) {
                $sanitized[$select] = sanitize_text_field($input[$select]);
            }
        }

        // テキストエリア
        if (array_key_exists('target_keywords', $input)) {
            $sanitized['target_keywords'] = sanitize_textarea_field($input['target_keywords']);
        }

        return $sanitized;
    }
    
    /**
     * 設定値を取得
     */
    public static function get_setting($key, $default = null) {
        $settings = get_option('news_crawler_seo_settings', array());
        return isset($settings[$key]) ? $settings[$key] : $default;
    }
    
    /**
     * 設定値を更新
     */
    public static function update_setting($key, $value) {
        $settings = get_option('news_crawler_seo_settings', array());
        $settings[$key] = $value;
        return update_option('news_crawler_seo_settings', $settings);
    }
}
