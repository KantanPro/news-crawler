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
        // 設定に基づいてOGPとTwitter Cardの出力を制御
        $ogp_settings = get_option('news_crawler_ogp_settings', array());
        
        // ライセンスチェック - 高度なOGP機能が有効かどうかを確認
        $advanced_ogp_enabled = true;
        if (class_exists('NewsCrawler_License_Manager')) {
            $license_manager = NewsCrawler_License_Manager::get_instance();
            $advanced_ogp_enabled = $license_manager->is_advanced_features_enabled();
        }
        
        if (isset($ogp_settings['enable_ogp']) ? $ogp_settings['enable_ogp'] : true) {
            add_action('wp_head', array($this, 'output_ogp_meta_tags'));
        }
        
        if (isset($ogp_settings['enable_twitter_card']) ? $ogp_settings['enable_twitter_card'] : true) {
            add_action('wp_head', array($this, 'output_twitter_card_meta_tags'));
        }
        
        // 高度なOGP機能が無効な場合は、基本的なOGPのみ出力
        if (!$advanced_ogp_enabled) {
            error_log('NewsCrawlerOGPManager: ライセンスが無効なため、高度なOGP機能を制限します');
        }
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
        
        // 基本情報
        $title = get_the_title($post->ID);
        $description = $this->get_post_description($post);
        $url = get_permalink($post->ID);
        $site_name = get_bloginfo('name');
        
        // アイキャッチ画像
        $image_url = $this->get_featured_image_url($post->ID);
        $image_width = 1200;
        $image_height = 630;
        
        // OGPメタタグを出力
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
        }
        
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
     * Twitter Cardメタタグを出力
     */
    public function output_twitter_card_meta_tags() {
        // 投稿ページでのみ出力
        if (!is_single() && !is_page()) {
            return;
        }
        
        global $post;
        if (!$post) {
            return;
        }
        
        $title = get_the_title($post->ID);
        $description = $this->get_post_description($post);
        $image_url = $this->get_featured_image_url($post->ID);
        
        // Twitter Cardメタタグを出力
        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($title) . '" />' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr($description) . '" />' . "\n";
        
        if ($image_url) {
            echo '<meta name="twitter:image" content="' . esc_url($image_url) . '" />' . "\n";
        }
        
        // サイトのTwitterアカウント（設定可能）
        $ogp_settings = get_option('news_crawler_ogp_settings', array());
        $twitter_username = isset($ogp_settings['twitter_username']) ? $ogp_settings['twitter_username'] : '';
        if (!empty($twitter_username)) {
            echo '<meta name="twitter:site" content="' . esc_attr($twitter_username) . '" />' . "\n";
        }
    }
    
    /**
     * 投稿の説明文を取得（キーワード最適化対応）
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
        
        // キーワード最適化が有効な場合は、AIでメタディスクリプションを生成
        if (class_exists('NewsCrawlerSeoSettings')) {
            $seo_settings = get_option('news_crawler_seo_settings', array());
            $auto_meta_description = isset($seo_settings['auto_meta_description']) ? $seo_settings['auto_meta_description'] : false;
            $keyword_optimization_enabled = isset($seo_settings['keyword_optimization_enabled']) ? $seo_settings['keyword_optimization_enabled'] : false;
            $target_keywords = isset($seo_settings['target_keywords']) ? trim($seo_settings['target_keywords']) : '';
            
            if ($auto_meta_description && $keyword_optimization_enabled && !empty($target_keywords)) {
                $optimized_description = $this->generate_keyword_optimized_description($post, $description);
                if ($optimized_description) {
                    return $optimized_description;
                }
            }
        }
        
        return $description;
    }
    
    /**
     * キーワード最適化されたメタディスクリプションを生成
     */
    private function generate_keyword_optimized_description($post, $original_description) {
        // OpenAI APIキーを取得
        $basic_settings = get_option('news_crawler_basic_settings', array());
        $api_key = isset($basic_settings['openai_api_key']) ? $basic_settings['openai_api_key'] : '';
        
        if (empty($api_key)) {
            return $original_description;
        }
        
        // SEO設定を取得
        $seo_settings = get_option('news_crawler_seo_settings', array());
        $target_keywords = isset($seo_settings['target_keywords']) ? trim($seo_settings['target_keywords']) : '';
        $meta_description_length = isset($seo_settings['meta_description_length']) ? intval($seo_settings['meta_description_length']) : 160;
        
        if (empty($target_keywords)) {
            return $original_description;
        }
        
        // キーワードを配列に変換
        $keywords = array_map('trim', preg_split('/[,\n\r]+/', $target_keywords));
        $keywords = array_filter($keywords);
        
        if (empty($keywords)) {
            return $original_description;
        }
        
        $keyword_list = implode('、', $keywords);
        
        // プロンプトを作成
        $prompt = "以下の記事内容を基に、SEOに最適化されたメタディスクリプションを生成してください。

記事タイトル：{$post->post_title}

記事内容：
" . wp_strip_all_tags($post->post_content) . "

元の説明文：
{$original_description}

【重要】以下のキーワードを必ず含めてください：
ターゲットキーワード：{$keyword_list}

要求事項：
1. {$meta_description_length}文字以内の簡潔な説明文
2. 指定されたキーワードを自然に含める
3. 記事の内容を正確に表現
4. 読者の興味を引く魅力的な表現
5. 日本語で自然な文章

メタディスクリプションのみを返してください。説明や装飾は不要です。";
        
        // OpenAI APIを呼び出し
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
                        'content' => 'あなたはSEOに精通したWebライターです。記事の内容を基に、検索エンジン最適化された魅力的なメタディスクリプションを生成してください。'
                    ),
                    array(
                        'role' => 'user',
                        'content' => $prompt
                    )
                ),
                'max_tokens' => 200,
                'temperature' => 0.3
            )),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return $original_description;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || !isset($data['choices'][0]['message']['content'])) {
            return $original_description;
        }
        
        $generated_description = trim($data['choices'][0]['message']['content']);
        
        // 生成された説明文が適切な長さかチェック
        if (mb_strlen($generated_description) > $meta_description_length) {
            $generated_description = mb_substr($generated_description, 0, $meta_description_length - 3) . '...';
        }
        
        return $generated_description;
    }
    
    /**
     * アイキャッチ画像のURLを取得
     */
    private function get_featured_image_url($post_id) {
        // アイキャッチ画像が設定されている場合
        if (has_post_thumbnail($post_id)) {
            $image_id = get_post_thumbnail_id($post_id);
            $image_data = wp_get_attachment_image_src($image_id, 'full');
            
            if ($image_data) {
                return $image_data[0];
            }
        }
        
        // アイキャッチ画像がない場合は、News Crawlerで生成された画像を探す
        $generated_image_id = get_post_meta($post_id, '_news_crawler_generated_image_id', true);
        if ($generated_image_id) {
            $image_data = wp_get_attachment_image_src($generated_image_id, 'full');
            if ($image_data) {
                return $image_data[0];
            }
        }
        
        // デフォルト画像（設定可能）
        $ogp_settings = get_option('news_crawler_ogp_settings', array());
        $default_image = isset($ogp_settings['default_og_image']) ? $ogp_settings['default_og_image'] : '';
        if (!empty($default_image)) {
            return $default_image;
        }
        
        return '';
    }
    
    /**
     * アイキャッチ画像の生成完了時にメタデータを更新
     */
    public function update_featured_image_meta($post_id, $attachment_id) {
        update_post_meta($post_id, '_news_crawler_generated_image_id', $attachment_id);
        
        // 投稿の更新日時を更新（OGPの更新日時を最新にするため）
        wp_update_post(array(
            'ID' => $post_id,
            'post_modified' => current_time('mysql'),
            'post_modified_gmt' => current_time('mysql', 1)
        ));
    }
}
