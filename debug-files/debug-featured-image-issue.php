<?php
// WordPress環境を読み込み
require_once '/var/www/html/wp-config.php';
require_once '/var/www/html/wp-content/plugins/news-crawler/news-crawler.php';

echo "=== アイキャッチ生成問題の調査 ===\n";

// 最新の投稿を取得
$posts = get_posts(array(
    'numberposts' => 5,
    'post_status' => 'publish',
    'orderby' => 'date',
    'order' => 'DESC'
));

echo "最新の投稿一覧:\n";
foreach ($posts as $post) {
    $thumbnail_id = get_post_thumbnail_id($post->ID);
    echo "ID: {$post->ID} | タイトル: {$post->post_title} | アイキャッチ: " . ($thumbnail_id ? "あり (ID: $thumbnail_id)" : "なし") . "\n";
}

// アイキャッチがない投稿を選択
$post_without_thumbnail = null;
foreach ($posts as $post) {
    if (!get_post_thumbnail_id($post->ID)) {
        $post_without_thumbnail = $post;
        break;
    }
}

if (!$post_without_thumbnail) {
    echo "\nアイキャッチがない投稿が見つかりません。新しい投稿を作成します。\n";
    $post_data = array(
        'post_title' => 'アイキャッチテスト投稿 - ' . date('Y-m-d H:i:s'),
        'post_content' => 'これはアイキャッチ生成のテスト投稿です。',
        'post_status' => 'publish',
        'post_type' => 'post'
    );
    
    $post_id = wp_insert_post($post_data);
    if (is_wp_error($post_id)) {
        echo "投稿作成エラー: " . $post_id->get_error_message() . "\n";
        exit;
    }
    $post_without_thumbnail = get_post($post_id);
}

$test_post = $post_without_thumbnail;
echo "\nテスト対象投稿:\n";
echo "ID: {$test_post->ID}\n";
echo "タイトル: {$test_post->post_title}\n";

// 設定を確認
echo "\n=== 設定確認 ===\n";
$basic_settings = get_option('news_crawler_basic_settings', array());
$featured_settings = get_option('news_crawler_featured_image_settings', array());

echo "OpenAI APIキー: " . (!empty($basic_settings['openai_api_key']) ? "設定済み" : "未設定") . "\n";
echo "アイキャッチ生成設定: " . print_r($featured_settings, true) . "\n";

// アイキャッチ生成を実行
echo "\n=== アイキャッチ生成テスト ===\n";
$generator = new NewsCrawlerFeaturedImageGenerator();

// テンプレート生成をテスト
echo "1. テンプレート生成をテスト...\n";
$template_result = $generator->generate_and_set_featured_image(
    $test_post->ID, 
    $test_post->post_title, 
    array('ニュース', 'テスト'), 
    'template'
);

if ($template_result) {
    echo "✓ テンプレート生成成功: 添付ファイルID $template_result\n";
} else {
    echo "✗ テンプレート生成失敗\n";
}

// AI生成をテスト（APIキーがある場合）
if (!empty($basic_settings['openai_api_key'])) {
    echo "\n2. AI生成をテスト...\n";
    $ai_result = $generator->generate_and_set_featured_image(
        $test_post->ID, 
        $test_post->post_title, 
        array('ニュース', 'テスト'), 
        'ai'
    );
    
    if ($ai_result) {
        echo "✓ AI生成成功: 添付ファイルID $ai_result\n";
    } else {
        echo "✗ AI生成失敗\n";
    }
} else {
    echo "\n2. AI生成: OpenAI APIキーが未設定のためスキップ\n";
}

// 結果確認
$final_thumbnail_id = get_post_thumbnail_id($test_post->ID);
echo "\n=== 最終結果 ===\n";
echo "アイキャッチID: " . ($final_thumbnail_id ? $final_thumbnail_id : "なし") . "\n";

if ($final_thumbnail_id) {
    $attachment_url = wp_get_attachment_url($final_thumbnail_id);
    echo "画像URL: $attachment_url\n";
    echo "✓ アイキャッチ設定成功\n";
} else {
    echo "✗ アイキャッチが設定されていません\n";
}

echo "\n=== 調査完了 ===\n";
?>