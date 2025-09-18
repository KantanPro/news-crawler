<?php
/**
 * OGP Manager Class
 * 
 * 投稿のOGPメタタグを管理し、アイキャッチ画像を正しく出力する機能
 */

// 直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

class NewsCrawlerOGPManager {
    
    public function __construct() {
        // フロントエンドでOGPメタタグを出力（優先度を最高に設定）
        add_action('wp_head', array($this, 'output_ogp_meta_tags'), 1);
        
        // 既存のOGPメタタグを削除
        add_action('wp_head', array($this, 'remove_existing_ogp_tags'), 0);
        
        // 投稿保存時にNews Crawler生成画像をアイキャッチ画像として設定
        add_action('save_post', array($this, 'auto_set_featured_image'), 10, 2);
        
        // 投稿公開時にアイキャッチ画像を強制設定
        add_action('publish_post', array($this, 'force_set_featured_image'), 10, 1);
        
        // 管理画面にメニューを追加
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
        }
    }
    
    /**
     * 既存のOGPメタタグを削除
     */
    public function remove_existing_ogp_tags() {
        // 投稿ページでのみ実行
        if (!is_single() && !is_page()) {
            return;
        }
        
        // 他のプラグインのOGPメタタグ出力を無効化
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
        remove_action('wp_head', 'wp_oembed_add_host_js');
        
        // テーマのOGPメタタグ出力を無効化（一般的なフック）
        remove_action('wp_head', 'wp_head_meta_tags');
        
        // 人気のあるSEOプラグインのOGPタグ出力を無効化
        remove_action('wp_head', 'wpseo_opengraph');
        remove_action('wp_head', 'wpseo_twitter');
        remove_action('wp_head', 'rank_math_opengraph');
        remove_action('wp_head', 'rank_math_twitter');
    }
    
    /**
     * OGPメタタグを出力
     */
    public function output_ogp_meta_tags() {
        // 投稿ページでのみ出力
        if (!is_single() && !is_page()) {
            return;
        }
        
        global $post;
        if (!$post) {
            return;
        }
        
        // SEO設定でOGPタグ自動生成が有効かチェック
        $seo_settings = get_option('news_crawler_seo_settings', array());
        $auto_ogp_tags = isset($seo_settings['auto_ogp_tags']) ? $seo_settings['auto_ogp_tags'] : true;
        
        if (!$auto_ogp_tags) {
            return;
        }
        
        // 基本情報
        $title = get_the_title($post->ID);
        $description = $this->get_post_description($post);
        $url = get_permalink($post->ID);
        $site_name = get_bloginfo('name');
        
        // アイキャッチ画像
        $image_url = $this->get_featured_image_url($post->ID);
        $image_width = 1200;
        $image_height = 630;
        
        // デバッグログ
        error_log('NewsCrawler OGP: Title = ' . $title);
        error_log('NewsCrawler OGP: Description = ' . $description);
        error_log('NewsCrawler OGP: Image URL = ' . ($image_url ? $image_url : 'なし'));
        
        // OGPメタタグを出力
        echo "\n<!-- News Crawler OGP Meta Tags -->\n";
        echo '<meta property="og:type" content="article" />' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($title) . '" />' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($description) . '" />' . "\n";
        echo '<meta property="og:url" content="' . esc_url($url) . '" />' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr($site_name) . '" />' . "\n";
        
        if ($image_url) {
            echo '<meta property="og:image" content="' . esc_url($image_url) . '" />' . "\n";
            echo '<meta property="og:image:width" content="' . esc_attr($image_width) . '" />' . "\n";
            echo '<meta property="og:image:height" content="' . esc_attr($image_height) . '" />' . "\n";
            echo '<meta property="og:image:type" content="image/jpeg" />' . "\n";
        } else {
            echo '<!-- OGP Image not found -->' . "\n";
        }
        
        // Twitter Cardメタタグも出力
        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($title) . '" />' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr($description) . '" />' . "\n";
        
        if ($image_url) {
            echo '<meta name="twitter:image" content="' . esc_url($image_url) . '" />' . "\n";
        }
        echo "<!-- End News Crawler OGP Meta Tags -->\n\n";
        
        // 投稿の公開日時
        $published_time = get_the_date('c', $post->ID);
        $modified_time = get_the_modified_date('c', $post->ID);
        
        echo '<meta property="article:published_time" content="' . esc_attr($published_time) . '" />' . "\n";
        echo '<meta property="article:modified_time" content="' . esc_attr($modified_time) . '" />' . "\n";
        
        // カテゴリー情報
        $categories = get_the_category($post->ID);
        if (!empty($categories)) {
            foreach ($categories as $category) {
                echo '<meta property="article:section" content="' . esc_attr($category->name) . '" />' . "\n";
            }
        }
        
        // タグ情報
        $tags = get_the_tags($post->ID);
        if (!empty($tags)) {
            foreach ($tags as $tag) {
                echo '<meta property="article:tag" content="' . esc_attr($tag->name) . '" />' . "\n";
            }
        }
        
        // 著者情報
        $author_id = $post->post_author;
        if ($author_id) {
            $author_name = get_the_author_meta('display_name', $author_id);
            $author_url = get_author_posts_url($author_id);
            
            echo '<meta property="article:author" content="' . esc_url($author_url) . '" />' . "\n";
            echo '<meta property="og:author" content="' . esc_attr($author_name) . '" />' . "\n";
        }
    }
    
    /**
     * 投稿の説明文を取得
     */
    private function get_post_description($post) {
        // 抜粋がある場合は使用
        if (!empty($post->post_excerpt)) {
            $description = wp_strip_all_tags($post->post_excerpt);
        } else {
            // 抜粋がない場合は本文から生成
            $content = wp_strip_all_tags($post->post_content);
            $content = str_replace(array("\n", "\r", "\t"), ' ', $content);
            $content = preg_replace('/\s+/', ' ', $content);
            
            // 160文字程度で切り詰め
            if (mb_strlen($content) > 160) {
                $content = mb_substr($content, 0, 157) . '...';
            }
            
            $description = $content;
        }
        
        return $description;
    }
    
    /**
     * アイキャッチ画像のURLを取得
     */
    private function get_featured_image_url($post_id) {
        // 1. アイキャッチ画像が設定されている場合
        if (has_post_thumbnail($post_id)) {
            $image_id = get_post_thumbnail_id($post_id);
            $image_data = wp_get_attachment_image_src($image_id, 'full');
            
            if ($image_data) {
                error_log('NewsCrawler OGP: アイキャッチ画像を使用: ' . $image_data[0]);
                return $image_data[0];
            }
        }
        
        // 2. News Crawlerで生成された画像を探す
        $generated_image_id = get_post_meta($post_id, '_news_crawler_generated_image_id', true);
        if ($generated_image_id) {
            $image_data = wp_get_attachment_image_src($generated_image_id, 'full');
            if ($image_data) {
                error_log('NewsCrawler OGP: 生成された画像を使用: ' . $image_data[0]);
                return $image_data[0];
            }
        }
        
        // 3. 投稿に添付された画像を探す
        $attachments = get_attached_media('image', $post_id);
        if (!empty($attachments)) {
            $first_attachment = reset($attachments);
            $image_data = wp_get_attachment_image_src($first_attachment->ID, 'full');
            if ($image_data) {
                error_log('NewsCrawler OGP: 添付画像を使用: ' . $image_data[0]);
                return $image_data[0];
            }
        }
        
        // 4. 投稿本文から画像を抽出
        $content = get_post_field('post_content', $post_id);
        if ($content) {
            preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches);
            if (!empty($matches[1])) {
                $image_url = $matches[1][0];
                error_log('NewsCrawler OGP: 本文から画像を抽出: ' . $image_url);
                return $image_url;
            }
        }
        
        // 5. デフォルト画像（サイトのロゴなど）
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $image_data = wp_get_attachment_image_src($custom_logo_id, 'full');
            if ($image_data) {
                error_log('NewsCrawler OGP: サイトロゴを使用: ' . $image_data[0]);
                return $image_data[0];
            }
        }
        
        error_log('NewsCrawler OGP: 画像が見つかりませんでした');
        return '';
    }
    
    /**
     * アイキャッチ画像の生成完了時にメタデータを更新
     */
    public function update_featured_image_meta($post_id, $attachment_id) {
        update_post_meta($post_id, '_news_crawler_generated_image_id', $attachment_id);
        
        // アイキャッチ画像として設定
        $this->set_featured_image($post_id, $attachment_id);
        
        // 投稿の更新日時を更新（OGPの更新日時を最新にするため）
        wp_update_post(array(
            'ID' => $post_id,
            'post_modified' => current_time('mysql'),
            'post_modified_gmt' => current_time('mysql', 1)
        ));
    }
    
    /**
     * アイキャッチ画像を設定
     */
    public function set_featured_image($post_id, $attachment_id) {
        // アイキャッチ画像として設定
        $result = set_post_thumbnail($post_id, $attachment_id);
        
        if ($result) {
            error_log('NewsCrawler OGP: アイキャッチ画像を設定しました - Post ID: ' . $post_id . ', Attachment ID: ' . $attachment_id);
        } else {
            error_log('NewsCrawler OGP: アイキャッチ画像の設定に失敗しました - Post ID: ' . $post_id . ', Attachment ID: ' . $attachment_id);
        }
        
        return $result;
    }
    
    /**
     * 既存の投稿でNews Crawler生成画像をアイキャッチ画像として設定
     */
    public function set_generated_image_as_featured($post_id) {
        $generated_image_id = get_post_meta($post_id, '_news_crawler_generated_image_id', true);
        
        if ($generated_image_id && !has_post_thumbnail($post_id)) {
            $this->set_featured_image($post_id, $generated_image_id);
            return true;
        }
        
        return false;
    }
    
    /**
     * 投稿保存時に自動的にアイキャッチ画像を設定
     */
    public function auto_set_featured_image($post_id, $post) {
        // 自動保存やリビジョンの場合はスキップ
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        
        // 投稿タイプがpostでない場合はスキップ
        if ($post->post_type !== 'post') {
            return;
        }
        
        // 既にアイキャッチ画像が設定されている場合はスキップ
        if (has_post_thumbnail($post_id)) {
            return;
        }
        
        // News Crawler生成画像をアイキャッチ画像として設定
        $this->set_generated_image_as_featured($post_id);
    }
    
    /**
     * 投稿公開時にアイキャッチ画像を強制設定
     */
    public function force_set_featured_image($post_id) {
        // 投稿タイプがpostでない場合はスキップ
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'post') {
            return;
        }
        
        // News Crawler生成画像をアイキャッチ画像として強制設定
        $generated_image_id = get_post_meta($post_id, '_news_crawler_generated_image_id', true);
        
        if ($generated_image_id) {
            // 既存のアイキャッチ画像を削除してから設定
            delete_post_thumbnail($post_id);
            $this->set_featured_image($post_id, $generated_image_id);
            error_log('NewsCrawler OGP: 投稿公開時にアイキャッチ画像を強制設定 - Post ID: ' . $post_id . ', Image ID: ' . $generated_image_id);
        }
    }
    
    /**
     * 管理画面メニューを追加
     */
    public function add_admin_menu() {
        add_submenu_page(
            'news-crawler-main',
            'OGP画像設定',
            'OGP画像設定',
            'manage_options',
            'news-crawler-ogp-images',
            array($this, 'admin_page')
        );
    }
    
    /**
     * 管理画面ページ
     */
    public function admin_page() {
        if (isset($_POST['action']) && $_POST['action'] === 'set_all_featured_images') {
            $this->set_all_generated_images_as_featured();
            echo '<div class="notice notice-success"><p>すべてのNews Crawler生成画像をアイキャッチ画像として設定しました。</p></div>';
        }
        
        ?>
        <div class="wrap">
            <h1>News Crawler OGP画像設定</h1>
            
            <div class="card">
                <h2>アイキャッチ画像の自動設定</h2>
                <p>News Crawlerで生成された画像をアイキャッチ画像として設定します。</p>
                
                <form method="post">
                    <input type="hidden" name="action" value="set_all_featured_images">
                    <?php wp_nonce_field('news_crawler_ogp_images', 'ogp_images_nonce'); ?>
                    <p>
                        <input type="submit" class="button button-primary" value="すべての生成画像をアイキャッチ画像として設定" 
                               onclick="return confirm('すべてのNews Crawler生成画像をアイキャッチ画像として設定しますか？');">
                    </p>
                </form>
            </div>
            
            <div class="card">
                <h2>現在の状況</h2>
                <?php $this->display_image_status(); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * すべての生成画像をアイキャッチ画像として設定
     */
    private function set_all_generated_images_as_featured() {
        global $wpdb;
        
        // News Crawler生成画像のメタデータを持つ投稿を取得
        $posts = $wpdb->get_results("
            SELECT p.ID, pm.meta_value as generated_image_id
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'post'
            AND p.post_status = 'publish'
            AND pm.meta_key = '_news_crawler_generated_image_id'
            AND pm.meta_value != ''
        ");
        
        $count = 0;
        foreach ($posts as $post) {
            if (!$this->set_generated_image_as_featured($post->ID)) {
                $count++;
            }
        }
        
        error_log('NewsCrawler OGP: ' . $count . '件の投稿でアイキャッチ画像を設定しました');
    }
    
    /**
     * 画像の状況を表示
     */
    private function display_image_status() {
        global $wpdb;
        
        // 統計情報を取得
        $total_posts = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish'");
        $posts_with_featured = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'post' AND p.post_status = 'publish'
            AND pm.meta_key = '_thumbnail_id'
        ");
        $posts_with_generated = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'post' AND p.post_status = 'publish'
            AND pm.meta_key = '_news_crawler_generated_image_id'
            AND pm.meta_value != ''
        ");
        
        echo '<p><strong>総投稿数:</strong> ' . $total_posts . '</p>';
        echo '<p><strong>アイキャッチ画像設定済み:</strong> ' . $posts_with_featured . '</p>';
        echo '<p><strong>News Crawler生成画像あり:</strong> ' . $posts_with_generated . '</p>';
        
        // 生成画像があるがアイキャッチ画像が設定されていない投稿
        $posts_without_featured = $wpdb->get_results("
            SELECT p.ID, p.post_title, pm.meta_value as generated_image_id
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_thumbnail_id'
            WHERE p.post_type = 'post' AND p.post_status = 'publish'
            AND pm.meta_key = '_news_crawler_generated_image_id'
            AND pm.meta_value != ''
            AND pm2.meta_value IS NULL
            LIMIT 10
        ");
        
        if (!empty($posts_without_featured)) {
            echo '<h3>アイキャッチ画像が設定されていない投稿（生成画像あり）</h3>';
            echo '<ul>';
            foreach ($posts_without_featured as $post) {
                echo '<li><a href="' . get_edit_post_link($post->ID) . '">' . esc_html($post->post_title) . '</a></li>';
            }
            echo '</ul>';
        }
    }
}
