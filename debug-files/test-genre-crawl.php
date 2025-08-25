<?php
// WordPress環境を読み込み
require_once '/var/www/html/wp-config.php';
require_once '/var/www/html/wp-content/plugins/news-crawler/news-crawler.php';

echo "=== ジャンル設定からのクロールテスト ===\n";

// ジャンル設定を取得
$genre_settings = get_option('news_crawler_genre_settings', array());

if (empty($genre_settings)) {
    echo "ジャンル設定が見つかりません。テスト用設定を作成します。\n";
    
    $test_genre = array(
        'name' => 'テストニュース',
        'url' => 'https://news.yahoo.co.jp/rss/topics/top-picks.xml',
        'auto_featured_image' => true,
        'featured_image_method' => 'ai',
        'keywords' => array('ニュース', 'テスト'),
        'post_categories' => array('ニュース'),
        'post_status' => 'draft'
    );
    
    $genre_id = 'genre_test_' . time();
    $genre_settings[$genre_id] = $test_genre;
    update_option('news_crawler_genre_settings', $genre_settings);
    
    echo "テスト用ジャンル設定を作成しました: $genre_id\n";
} else {
    // 最初のジャンル設定を使用
    $genre_id = array_keys($genre_settings)[0];
    $test_genre = $genre_settings[$genre_id];
    echo "既存のジャンル設定を使用: $genre_id\n";
}

echo "使用するジャンル設定:\n";
echo print_r($test_genre, true) . "\n";

// ジャンル設定クラスのインスタンスを作成
$genre_settings_class = new NewsCrawlerGenreSettings();

// 一時保存データを設定（ジャンル設定画面からの実行をシミュレート）
echo "=== 一時保存データの設定 ===\n";
$transient_result = set_transient('news_crawler_current_genre_setting', $test_genre, 300);
echo "一時保存結果: " . ($transient_result ? '✅ 成功' : '❌ 失敗') . "\n";

// 確認
$saved_setting = get_transient('news_crawler_current_genre_setting');
if ($saved_setting) {
    echo "✓ 一時保存データ確認成功\n";
    echo "自動アイキャッチ: " . (isset($saved_setting['auto_featured_image']) && $saved_setting['auto_featured_image'] ? 'ON' : 'OFF') . "\n";
    echo "アイキャッチ方法: " . ($saved_setting['featured_image_method'] ?? '未設定') . "\n";
} else {
    echo "✗ 一時保存データが見つかりません\n";
    exit;
}

// ニュースクロールを実行
echo "\n=== ニュースクロール実行 ===\n";
$crawler = new NewsCrawler();

// リフレクションを使ってprivateメソッドにアクセス
$reflection = new ReflectionClass($crawler);
$crawl_method = $reflection->getMethod('crawl_news');
$crawl_method->setAccessible(true);

// テスト用のRSSフィードでクロール実行
$result = $crawl_method->invoke($crawler, $test_genre['url'], 1, $test_genre['keywords'], $test_genre['post_categories'], $test_genre['post_status']);

echo "クロール結果: $result\n";

// 最新の投稿を確認
$latest_posts = get_posts(array(
    'numberposts' => 3,
    'post_status' => array('publish', 'draft'),
    'orderby' => 'date',
    'order' => 'DESC'
));

echo "\n=== 最新投稿の確認 ===\n";
foreach ($latest_posts as $post) {
    $thumbnail_id = get_post_thumbnail_id($post->ID);
    echo "ID: {$post->ID} | タイトル: {$post->post_title} | ステータス: {$post->post_status} | アイキャッチ: " . ($thumbnail_id ? "あり (ID: $thumbnail_id)" : "なし") . "\n";
}

// 一時保存データをクリア
delete_transient('news_crawler_current_genre_setting');
echo "\n一時保存データをクリアしました\n";

echo "\n=== テスト完了 ===\n";
?>