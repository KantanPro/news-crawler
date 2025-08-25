<?php
/**
 * 基本機能テスト
 * News Crawlerプラグインの基本機能をテストします
 */

// WordPress環境を読み込み
require_once '/var/www/html/wp-config.php';
require_once '/var/www/html/wp-content/plugins/news-crawler/news-crawler.php';

echo "=== News Crawler 基本機能テスト ===\n";

// 1. 基本設定の確認
echo "\n1. 基本設定の確認\n";
$basic_settings = get_option('news_crawler_basic_settings', array());
echo "OpenAI APIキー: " . (isset($basic_settings['openai_api_key']) && !empty($basic_settings['openai_api_key']) ? '設定済み' : '未設定') . "\n";
echo "アイキャッチ自動生成: " . (isset($basic_settings['auto_featured_image']) && $basic_settings['auto_featured_image'] ? 'ON' : 'OFF') . "\n";
echo "アイキャッチ生成方法: " . ($basic_settings['featured_image_method'] ?? '未設定') . "\n";

// 2. ジャンル設定の確認
echo "\n2. ジャンル設定の確認\n";
$genre_settings = get_option('news_crawler_genre_settings', array());
echo "登録済みジャンル数: " . count($genre_settings) . "\n";

foreach ($genre_settings as $genre_id => $setting) {
    echo "- " . ($setting['genre_name'] ?? $genre_id) . " (" . ($setting['content_type'] ?? 'unknown') . ")\n";
}

// 3. クラスの読み込み確認
echo "\n3. クラスの読み込み確認\n";
$classes = array(
    'NewsCrawler' => class_exists('NewsCrawler'),
    'YouTubeCrawler' => class_exists('YouTubeCrawler'),
    'NewsCrawlerGenreSettings' => class_exists('NewsCrawlerGenreSettings'),
    'FeaturedImageGenerator' => class_exists('FeaturedImageGenerator')
);

foreach ($classes as $class_name => $exists) {
    echo "- $class_name: " . ($exists ? '✓' : '✗') . "\n";
}

// 4. 最新投稿の確認
echo "\n4. 最新投稿の確認\n";
$latest_posts = get_posts(array(
    'numberposts' => 5,
    'post_status' => array('publish', 'draft'),
    'orderby' => 'date',
    'order' => 'DESC'
));

foreach ($latest_posts as $post) {
    $thumbnail_id = get_post_thumbnail_id($post->ID);
    echo "- ID:{$post->ID} | {$post->post_title} | " . ($thumbnail_id ? 'アイキャッチあり' : 'アイキャッチなし') . "\n";
}

echo "\n=== テスト完了 ===\n";
?>