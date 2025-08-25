<?php
// WordPress環境を読み込み
require_once '/var/www/html/wp-config.php';

// プラグインのメインファイルを読み込み（クラスが自動読み込みされる）
require_once '/var/www/html/wp-content/plugins/news-crawler/news-crawler.php';

echo "=== AI画像ダウンロードテスト ===\n";

// テスト用の投稿を作成
$post_data = array(
    'post_title' => 'AI画像テスト投稿',
    'post_content' => 'これはAI画像生成のテスト投稿です。',
    'post_status' => 'publish',
    'post_type' => 'post'
);

$post_id = wp_insert_post($post_data);

if (is_wp_error($post_id)) {
    echo "投稿作成エラー: " . $post_id->get_error_message() . "\n";
    exit;
}

echo "テスト投稿ID: $post_id\n";

// Featured Image Generatorのインスタンス作成
$generator = new NewsCrawlerFeaturedImageGenerator();

// AI画像生成設定
$settings = array(
    'ai_style' => 'modern, clean, professional',
    'ai_base_prompt' => 'Create a featured image for a blog post about'
);

// AI画像生成を実行
echo "AI画像生成を開始...\n";
$result = $generator->generate_and_set_featured_image($post_id, 'AI画像テスト投稿', array('AI', 'テスト'), 'ai');

if ($result) {
    echo "✓ AI画像生成成功！添付ファイルID: $result\n";
    
    // 添付ファイル情報を確認
    $attachment_url = wp_get_attachment_url($result);
    echo "画像URL: $attachment_url\n";
    
    // アイキャッチが設定されているか確認
    $thumbnail_id = get_post_thumbnail_id($post_id);
    echo "アイキャッチID: $thumbnail_id\n";
    
    if ($thumbnail_id == $result) {
        echo "✓ アイキャッチ設定成功\n";
    } else {
        echo "✗ アイキャッチ設定失敗\n";
    }
} else {
    echo "✗ AI画像生成失敗\n";
}

// テスト投稿を削除
wp_delete_post($post_id, true);
echo "テスト投稿を削除しました\n";

echo "=== テスト完了 ===\n";
?>