<?php
/**
 * アイキャッチ画像生成管理画面クラス
 * 
 * @package NewsCrawler
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class News_Crawler_Eyecatch_Admin {
    
    public function __construct() {
        // メニュー登録は無効化（class-genre-settings.phpで統合管理）
        // add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_generate_eyecatch', array($this, 'ajax_generate_eyecatch'));
    }
    
    /**
     * 管理メニューを追加
     */
    public function add_admin_menu() {
        // アイキャッチ画像生成メニューは無効化
        // 元々存在しなかったメニューのため、登録しない
        return;
    }
    
    /**
     * スクリプトとスタイルを読み込み
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'news-crawler_page_news-crawler-eyecatch') {
            return;
        }
        
        wp_enqueue_script(
            'news-crawler-eyecatch',
            plugin_dir_url(__FILE__) . '../assets/js/eyecatch-admin.js',
            array('jquery'),
            '1.3.0',
            true
        );
        
        wp_localize_script('news-crawler-eyecatch', 'newsCrawlerEyecatch', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('eyecatch_nonce'),
            'strings' => array(
                'generating' => '画像を生成中...',
                'success' => '画像の生成が完了しました！',
                'error' => 'エラーが発生しました。'
            )
        ));
        
        wp_enqueue_style(
            'news-crawler-eyecatch',
            plugin_dir_url(__FILE__) . '../assets/css/eyecatch-admin.css',
            array(),
            '1.3.0'
        );
    }
    
    /**
     * 管理画面ページ
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>アイキャッチ画像生成</h1>
            <p>ニュース記事用のアイキャッチ画像を生成できます。</p>
            
            <div class="eyecatch-generator-form">
                <h2>画像生成設定</h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="genre">ジャンル</label>
                        </th>
                        <td>
                            <input type="text" id="genre" name="genre" class="regular-text" 
                                   placeholder="例: テクノロジー、エンターテイメント" />
                            <p class="description">記事のジャンルを入力してください</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="keyword">キーワード</label>
                        </th>
                        <td>
                            <input type="text" id="keyword" name="keyword" class="regular-text" 
                                   placeholder="例: AI、人工知能" />
                            <p class="description">記事の主要キーワードを入力してください</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="date">日付</label>
                        </th>
                        <td>
                            <input type="date" id="date" name="date" class="regular-text" 
                                   value="<?php echo date('Y-m-d'); ?>" />
                            <p class="description">記事の日付を選択してください</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="button" id="generate-eyecatch" class="button button-primary">
                        アイキャッチ画像を生成
                    </button>
                </p>
            </div>
            
            <div id="eyecatch-preview" style="display: none;">
                <h2>生成された画像</h2>
                <div id="eyecatch-image-container"></div>
                <div id="eyecatch-actions">
                    <button type="button" id="download-eyecatch" class="button">ダウンロード</button>
                    <button type="button" id="use-as-featured" class="button button-primary">アイキャッチ画像として使用</button>
                </div>
            </div>
            
            <div id="eyecatch-loading" style="display: none;">
                <div class="spinner is-active"></div>
                <p>画像を生成中です...</p>
            </div>
            
            <div id="eyecatch-error" style="display: none;">
                <div class="notice notice-error">
                    <p id="error-message"></p>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX処理：アイキャッチ画像生成
     */
    public function ajax_generate_eyecatch() {
        // セキュリティチェック
        if (!wp_verify_nonce($_POST['nonce'], 'eyecatch_nonce')) {
            wp_die('セキュリティチェックに失敗しました');
        }
        
        // 権限チェック
        if (!current_user_can('manage_options')) {
            wp_die('権限がありません');
        }
        
        $genre = sanitize_text_field($_POST['genre']);
        $keyword = sanitize_text_field($_POST['keyword']);
        $date = sanitize_text_field($_POST['date']);
        
        if (empty($genre) || empty($keyword) || empty($date)) {
            wp_send_json_error('必須項目が入力されていません');
        }
        
        // アイキャッチ画像生成
        $generator = new News_Crawler_Eyecatch_Generator();
        $result = $generator->generate_eyecatch($genre, $keyword, $date);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'image_url' => $result,
                'message' => '画像の生成が完了しました'
            ));
        }
    }
}
