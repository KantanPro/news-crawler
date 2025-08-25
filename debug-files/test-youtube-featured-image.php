<?php
// WordPress環境を読み込み
require_once '/var/www/html/wp-config.php';
require_once '/var/www/html/wp-content/plugins/news-crawler/news-crawler.php';

echo "=== YouTube投稿のアイキャッチ生成テスト ===\n";

// 基本設定を確認
$basic_settings = get_option('news_crawler_basic_settings', array());
echo "アイキャッチ自動生成: " . (isset($basic_settings['auto_featured_image']) && $basic_settings['auto_featured_image'] ? 'ON' : 'OFF') . "\n";
echo "アイキャッチ生成方法: " . ($basic_settings['featured_image_method'] ?? '未設定') . "\n";

// 一時保存データをクリア（基本設定からの動作をテストするため）
delete_transient('news_crawler_current_genre_setting');
echo "一時保存データをクリアしました\n";

// テスト用のYouTube動画データを作成
$test_videos = array(
    array(
        'video_id' => 'test123',
        'title' => 'AI技術の最新動向について',
        'description' => 'AIの最新技術について詳しく解説します。',
        'channel_title' => 'テストチャンネル',
        'published_at' => '2025-08-25',
        'duration' => '10:30',
        'view_count' => 1000
    )
);

// YouTubeCrawlerのインスタンスを作成
$youtube_crawler = new YouTubeCrawler();

// リフレクションを使ってprivateメソッドにアクセス
$reflection = new ReflectionClass($youtube_crawler);
$create_post_method = $reflection->getMethod('create_video_summary_post');
$create_post_method->setAccessible(true);

echo "\n=== YouTube投稿作成 ===\n";
$post_id = $create_post_method->invoke(
    $youtube_crawler, 
    $test_videos, 
    array('テスト'), 
    'draft'
);

if ($post_id && !is_wp_error($post_id)) {
    echo "✓ YouTube投稿作成成功: ID $post_id\n";
    
    // 投稿情報を確認
    $post = get_post($post_id);
    echo "タイトル: {$post->post_title}\n";
    
    // アイキャッチを確認
    $thumbnail_id = get_post_thumbnail_id($post_id);
    if ($thumbnail_id) {
        echo "✓ アイキャッチ設定成功: ID $thumbnail_id\n";
        $attachment_url = wp_get_attachment_url($thumbnail_id);
        echo "画像URL: $attachment_url\n";
    } else {
        echo "✗ アイキャッチが設定されていません\n";
    }
} else {
    $error_message = is_wp_error($post_id) ? $post_id->get_error_message() : '不明なエラー';
    echo "✗ YouTube投稿作成失敗: $error_message\n";
}

echo "\n=== テスト完了 ===\n";
?>