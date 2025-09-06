<?php
/**
 * OGP Settings Class
 * 
 * OGP設定を管理画面で設定するためのクラス
 */

// 直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

class NewsCrawlerOGPSettings {
    private $option_name = 'news_crawler_ogp_settings';
    
    public function __construct() {
        // メニュー登録はNews Crawlerメインメニューから行われるため、
        // ここではadmin_initのみ実行
        add_action('admin_init', array($this, 'admin_init'));
    }
    
    /**
     * 管理メニューに追加
     * News Crawlerメニューのサブメニューとして登録されるため、
     * このメソッドは呼び出されません
     */
    public function add_admin_menu() {
        // このメソッドは使用されません
        // OGP設定はNews Crawlerメニューのサブメニューとして登録されます
    }
    
    /**
     * 管理画面の初期化
     */
    public function admin_init() {
        register_setting($this->option_name, $this->option_name, array($this, 'sanitize_settings'));
        
        add_settings_section(
            'ogp_basic_settings',
            '基本設定',
            array($this, 'section_callback'),
            'news-crawler-ogp-settings'
        );
        
        add_settings_field(
            'default_og_image',
            'デフォルトOGP画像',
            array($this, 'default_og_image_callback'),
            'news-crawler-ogp-settings',
            'ogp_basic_settings'
        );
        
        add_settings_field(
            'twitter_username',
            'Twitterアカウント',
            array($this, 'twitter_username_callback'),
            'news-crawler-ogp-settings',
            'ogp_basic_settings'
        );
        
        add_settings_field(
            'enable_ogp',
            'OGP有効化',
            array($this, 'enable_ogp_callback'),
            'news-crawler-ogp-settings',
            'ogp_basic_settings'
        );
        
        add_settings_field(
            'enable_twitter_card',
            'Twitter Card有効化',
            array($this, 'enable_twitter_card_callback'),
            'news-crawler-ogp-settings',
            'ogp_basic_settings'
        );
        
        add_settings_field(
            'twitter_description_length',
            'X投稿時の説明文の長さ',
            array($this, 'twitter_description_length_callback'),
            'news-crawler-ogp-settings',
            'ogp_basic_settings'
        );
        
        add_settings_field(
            'twitter_include_description',
            'X投稿に説明文を含める',
            array($this, 'twitter_include_description_callback'),
            'news-crawler-ogp-settings',
            'ogp_basic_settings'
        );
    }
    
    /**
     * セクションコールバック
     */
    public function section_callback() {
        echo '<p>OGP（Open Graph Protocol）とTwitter Cardの設定を行います。</p>';
    }
    
    /**
     * デフォルトOGP画像フィールド
     */
    public function default_og_image_callback() {
        $settings = get_option($this->option_name, array());
        $default_image = isset($settings['default_og_image']) ? $settings['default_og_image'] : '';
        
        echo '<input type="text" id="default_og_image" name="' . $this->option_name . '[default_og_image]" value="' . esc_attr($default_image) . '" size="50" />';
        echo '<button type="button" id="upload_image_button" class="button">画像を選択</button>';
        echo '<p class="description">アイキャッチ画像がない場合に使用されるデフォルト画像のURLを入力してください。</p>';
        
        if (!empty($default_image)) {
            echo '<div style="margin-top: 10px;">';
            echo '<img src="' . esc_url($default_image) . '" style="max-width: 200px; height: auto;" />';
            echo '</div>';
        }
        
        // JavaScript for media uploader
        echo '<script>
        jQuery(document).ready(function($) {
            $("#upload_image_button").click(function(e) {
                e.preventDefault();
                var image = wp.media({
                    title: "デフォルトOGP画像を選択",
                    multiple: false
                }).open().on("select", function() {
                    var uploaded_image = image.state().get("selection").first();
                    var image_url = uploaded_image.toJSON().url;
                    $("#default_og_image").val(image_url);
                });
            });
        });
        </script>';
    }
    
    /**
     * Twitterアカウントフィールド
     */
    public function twitter_username_callback() {
        $settings = get_option($this->option_name, array());
        $twitter_username = isset($settings['twitter_username']) ? $settings['twitter_username'] : '';
        
        echo '<input type="text" name="' . $this->option_name . '[twitter_username]" value="' . esc_attr($twitter_username) . '" size="30" />';
        echo '<p class="description">サイトのTwitterアカウント名を入力してください（例：@username）。</p>';
    }
    
    /**
     * OGP有効化フィールド
     */
    public function enable_ogp_callback() {
        $settings = get_option($this->option_name, array());
        $enable_ogp = isset($settings['enable_ogp']) ? $settings['enable_ogp'] : true;
        
        echo '<label><input type="checkbox" name="' . $this->option_name . '[enable_ogp]" value="1" ' . checked(1, $enable_ogp, false) . ' /> OGPメタタグを出力する</label>';
        echo '<p class="description">Facebook、LINE、その他のSNSでシェアされた際の表示を最適化します。</p>';
    }
    
    /**
     * Twitter Card有効化フィールド
     */
    public function enable_twitter_card_callback() {
        $settings = get_option($this->option_name, array());
        $enable_twitter_card = isset($settings['enable_twitter_card']) ? $settings['enable_twitter_card'] : true;
        
        echo '<label><input type="checkbox" name="' . $this->option_name . '[enable_twitter_card]" value="1" ' . checked(1, $enable_twitter_card, false) . ' /> Twitter Cardメタタグを出力する</label>';
        echo '<p class="description">Twitterでシェアされた際の表示を最適化します。</p>';
    }
    
    /**
     * X投稿時の説明文の長さフィールド
     */
    public function twitter_description_length_callback() {
        $settings = get_option($this->option_name, array());
        $description_length = isset($settings['twitter_description_length']) ? $settings['twitter_description_length'] : 100;
        
        echo '<input type="number" name="' . $this->option_name . '[twitter_description_length]" value="' . esc_attr($description_length) . '" min="50" max="200" size="10" /> 文字';
        echo '<p class="description">X投稿に含める説明文の最大文字数を設定します（50-200文字）。</p>';
    }
    
    /**
     * X投稿に説明文を含めるフィールド
     */
    public function twitter_include_description_callback() {
        $settings = get_option($this->option_name, array());
        $include_description = isset($settings['twitter_include_description']) ? $settings['twitter_include_description'] : true;
        
        echo '<label><input type="checkbox" name="' . $this->option_name . '[twitter_include_description]" value="1" ' . checked(1, $include_description, false) . ' /> X投稿に記事の説明文を含める</label>';
        echo '<p class="description">X投稿時に記事の抜粋や説明文を含めるかどうかを設定します。</p>';
    }
    
    /**
     * 設定のサニタイズ
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        if (isset($input['default_og_image'])) {
            $sanitized['default_og_image'] = esc_url_raw($input['default_og_image']);
        }
        
        if (isset($input['twitter_username'])) {
            $sanitized['twitter_username'] = sanitize_text_field($input['twitter_username']);
        }
        
        $sanitized['enable_ogp'] = isset($input['enable_ogp']) ? true : false;
        $sanitized['enable_twitter_card'] = isset($input['enable_twitter_card']) ? true : false;
        
        if (isset($input['twitter_description_length'])) {
            $length = intval($input['twitter_description_length']);
            $sanitized['twitter_description_length'] = max(50, min(200, $length));
        }
        
        $sanitized['twitter_include_description'] = isset($input['twitter_include_description']) ? true : false;
        
        return $sanitized;
    }
    
    /**
     * 管理画面ページ
     */
    public function admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        ?>
        <div class="wrap">
            <h1>News Crawler <?php echo esc_html(defined('NEWS_CRAWLER_VERSION') ? NEWS_CRAWLER_VERSION : ''); ?> - OGP設定</h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields($this->option_name);
                do_settings_sections('news-crawler-ogp-settings');
                submit_button();
                ?>
            </form>
            
            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2>OGP設定について</h2>
                <p>OGP（Open Graph Protocol）は、Facebook、LINE、その他のSNSでシェアされた際の表示を最適化するためのメタタグです。</p>
                
                <h3>主な機能</h3>
                <ul>
                    <li><strong>自動アイキャッチ画像出力</strong>: News Crawlerで生成されたアイキャッチ画像をOGPに自動反映</li>
                    <li><strong>Twitter Card対応</strong>: Twitterでの表示を最適化</li>
                    <li><strong>カテゴリー・タグ情報</strong>: 投稿の分類情報をOGPに含める</li>
                    <li><strong>著者情報</strong>: 投稿者の情報をOGPに含める</li>
                    <li><strong>X投稿時の説明文制御</strong>: X投稿に含める説明文の長さと内容を制御</li>
                </ul>
                
                <h3>設定のポイント</h3>
                <ul>
                    <li>デフォルトOGP画像は、アイキャッチ画像がない場合に使用されます</li>
                    <li>Twitterアカウントを設定すると、Twitter Cardにサイト情報が表示されます</li>
                    <li>OGPとTwitter Cardは個別に有効/無効を設定できます</li>
                    <li>X投稿時の説明文の長さは50-200文字の範囲で設定できます</li>
                    <li>説明文を含めない設定にすると、タイトルのみの投稿になります</li>
                </ul>
            </div>
        </div>
        <?php
    }
}
