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
        
        if (isset($ogp_settings['enable_ogp']) ? $ogp_settings['enable_ogp'] : true) {
            add_action('wp_head', array($this, 'output_ogp_meta_tags'));
        }
        
        if (isset($ogp_settings['enable_twitter_card']) ? $ogp_settings['enable_twitter_card'] : true) {
            add_action('wp_head', array($this, 'output_twitter_card_meta_tags'));
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
     * 投稿の説明文を取得
     */
    private function get_post_description($post) {
        // 抜粋がある場合は使用
        if (!empty($post->post_excerpt)) {
            return wp_strip_all_tags($post->post_excerpt);
        }
        
        // 抜粋がない場合は本文から生成
        $content = wp_strip_all_tags($post->post_content);
        $content = str_replace(array("\n", "\r", "\t"), ' ', $content);
        $content = preg_replace('/\s+/', ' ', $content);
        
        // 160文字程度で切り詰め
        if (mb_strlen($content) > 160) {
            $content = mb_substr($content, 0, 157) . '...';
        }
        
        return $content;
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
