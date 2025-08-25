<?php
/**
 * デバッグ情報表示スクリプト
 */

// WordPressを読み込み
require_once('wp-config.php');
require_once('wp-load.php');

echo "<h2>News Crawler デバッグ情報</h2>";

// 基本設定の確認
$basic_settings = get_option('news_crawler_basic_settings', array());
echo "<h3>基本設定</h3>";
echo "<pre>";
print_r($basic_settings);
echo "</pre>";

// ジャンル設定の確認
$genre_settings = get_option('news_crawler_genre_settings', array());
echo "<h3>ジャンル設定</h3>";
echo "<pre>";
print_r($genre_settings);
echo "</pre>";

// 現在のジャンル設定（一時保存）の確認
$current_genre = get_transient('news_crawler_current_genre_setting');
echo "<h3>現在のジャンル設定（一時保存）</h3>";
echo "<pre>";
print_r($current_genre);
echo "</pre>";

// クラスの存在確認
echo "<h3>クラス存在確認</h3>";
echo "NewsCrawlerFeaturedImageGenerator: " . (class_exists('NewsCrawlerFeaturedImageGenerator') ? '存在' : '存在しない') . "<br>";
echo "NewsCrawler: " . (class_exists('NewsCrawler') ? '存在' : '存在しない') . "<br>";

// GD拡張の確認
echo "<h3>GD拡張</h3>";
echo "GD拡張: " . (extension_loaded('gd') ? '有効' : '無効') . "<br>";
if (extension_loaded('gd')) {
    $gd_info = gd_info();
    echo "PNG サポート: " . ($gd_info['PNG Support'] ? 'あり' : 'なし') . "<br>";
    echo "JPEG サポート: " . ($gd_info['JPEG Support'] ? 'あり' : 'なし') . "<br>";
    echo "FreeType サポート: " . ($gd_info['FreeType Support'] ? 'あり' : 'なし') . "<br>";
}

// アップロードディレクトリの確認
echo "<h3>アップロードディレクトリ</h3>";
$upload_dir = wp_upload_dir();
echo "<pre>";
print_r($upload_dir);
echo "</pre>";

// 最新の投稿を確認
echo "<h3>最新の投稿（5件）</h3>";
$posts = get_posts(array('numberposts' => 5, 'post_status' => 'any'));
foreach ($posts as $post) {
    $thumbnail_id = get_post_thumbnail_id($post->ID);
    echo "ID: {$post->ID}, タイトル: {$post->post_title}, アイキャッチ: " . ($thumbnail_id ? "あり (ID: $thumbnail_id)" : "なし") . "<br>";
}

// エラーログの最新部分を表示
echo "<h3>最新のエラーログ</h3>";
$log_file = ini_get('error_log');
if ($log_file && file_exists($log_file)) {
    $log_content = file_get_contents($log_file);
    $lines = explode("\n", $log_content);
    $recent_lines = array_slice($lines, -50); // 最新50行
    echo "<pre style='height: 300px; overflow-y: scroll; background: #f0f0f0; padding: 10px;'>";
    foreach ($recent_lines as $line) {
        if (strpos($line, 'Featured Image Generator') !== false || strpos($line, 'NewsCrawler') !== false) {
            echo htmlspecialchars($line) . "\n";
        }
    }
    echo "</pre>";
} else {
    echo "エラーログファイルが見つかりません。<br>";
    echo "wp-config.phpに以下を追加してデバッグを有効にしてください：<br>";
    echo "<code>define('WP_DEBUG', true);<br>";
    echo "define('WP_DEBUG_LOG', true);</code>";
}
?>