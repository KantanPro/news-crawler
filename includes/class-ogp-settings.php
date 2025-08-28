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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
    }
    
    /**
     * 管理メニューに追加
     */
    public function add_admin_menu() {
        // News Crawlerの設定メニューが存在する場合はサブメニューとして追加
        if (menu_page_url('news-crawler-settings', false)) {
            add_submenu_page(
                'news-crawler-settings',
                'OGP設定',
                'OGP設定',
                'manage_options',
                'news-crawler-ogp-settings',
                array($this, 'admin_page')
            );
        } else {
            // 親メニューが存在しない場合は独立したメニューとして追加
            add_menu_page(
                'OGP設定',
                'OGP設定',
                'manage_options',
                'news-crawler-ogp-settings',
                array($this, 'admin_page'),
                'dashicons-share',
                30
            );
        }
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
            <h1>OGP設定</h1>
            
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
                </ul>
                
                <h3>設定のポイント</h3>
                <ul>
                    <li>デフォルトOGP画像は、アイキャッチ画像がない場合に使用されます</li>
                    <li>Twitterアカウントを設定すると、Twitter Cardにサイト情報が表示されます</li>
                    <li>OGPとTwitter Cardは個別に有効/無効を設定できます</li>
                </ul>
            </div>
        </div>
        <?php
    }
}
