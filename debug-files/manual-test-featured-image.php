<?php
/**
 * 手動アイキャッチ生成テストスクリプト
 */

// WordPressを読み込み（プラグインディレクトリからWordPressルートへ）
$wp_root = dirname(dirname(dirname(__DIR__)));
require_once($wp_root . '/wp-config.php');
require_once($wp_root . '/wp-load.php');

echo "<h2>手動アイキャッチ生成テスト</h2>";

// 最新の投稿を取得
$posts = get_posts(array('numberposts' => 1, 'post_status' => 'any'));
if (empty($posts)) {
    echo "投稿が見つかりません。";
    exit;
}

$post = $posts[0];
$post_id = $post->ID;

echo "テスト対象投稿: ID {$post_id} - {$post->post_title}<br><br>";

// クラスの存在確認
if (!class_exists('NewsCrawlerFeaturedImageGenerator')) {
    echo "NewsCrawlerFeaturedImageGeneratorクラスが見つかりません。<br>";
    exit;
}

// GD拡張の確認
if (!extension_loaded('gd')) {
    echo "GD拡張が有効になっていません。<br>";
    exit;
}

echo "GD拡張: 有効<br>";
echo "NewsCrawlerFeaturedImageGeneratorクラス: 存在<br><br>";

// アイキャッチ生成クラスのインスタンス作成
$generator = new NewsCrawlerFeaturedImageGenerator();

// テスト用の設定を一時保存
$test_setting = array(
    'auto_featured_image' => 1,
    'featured_image_method' => 'template'
);
set_transient('news_crawler_current_genre_setting', $test_setting, 300);

echo "テスト設定を一時保存しました。<br><br>";

// テンプレート生成をテスト
echo "<h3>テンプレート生成テスト</h3>";
$keywords = array('政治', '経済', 'ニュース');
$result = $generator->generate_and_set_featured_image($post_id, $post->post_title, $keywords, 'template');

if ($result) {
    echo "✅ テンプレート生成成功！<br>";
    echo "添付ファイルID: " . $result . "<br>";
    $thumbnail_url = wp_get_attachment_url($result);
    echo "画像URL: " . $thumbnail_url . "<br>";
    echo "<img src='{$thumbnail_url}' style='max-width: 400px; border: 1px solid #ccc;'><br>";
} else {
    echo "❌ テンプレート生成失敗<br>";
}

// 基本設定の確認
echo "<h3>基本設定</h3>";
$basic_settings = get_option('news_crawler_basic_settings', array());
echo "OpenAI APIキー: " . (isset($basic_settings['openai_api_key']) && !empty($basic_settings['openai_api_key']) ? '設定済み' : '未設定') . "<br>";

// AI生成をテスト（APIキーが設定されている場合）
if (isset($basic_settings['openai_api_key']) && !empty($basic_settings['openai_api_key'])) {
    echo "<h3>AI生成テスト</h3>";
    echo "AI生成をテスト中...<br>";
    $ai_result = $generator->generate_and_set_featured_image($post_id, $post->post_title, $keywords, 'ai');
    
    if ($ai_result) {
        echo "✅ AI生成成功！<br>";
        echo "添付ファイルID: " . $ai_result . "<br>";
        $ai_thumbnail_url = wp_get_attachment_url($ai_result);
        echo "画像URL: " . $ai_thumbnail_url . "<br>";
        echo "<img src='{$ai_thumbnail_url}' style='max-width: 400px; border: 1px solid #ccc;'><br>";
    } else {
        echo "❌ AI生成失敗<br>";
    }
} else {
    echo "<h3>AI生成テスト</h3>";
    echo "OpenAI APIキーが設定されていないため、AI生成テストをスキップします。<br>";
}

// エラーログの確認
echo "<h3>最新のエラーログ</h3>";
$log_file = ini_get('error_log');
if ($log_file && file_exists($log_file)) {
    $log_content = file_get_contents($log_file);
    $lines = explode("\n", $log_content);
    $recent_lines = array_slice($lines, -30); // 最新30行
    echo "<pre style='height: 200px; overflow-y: scroll; background: #f0f0f0; padding: 10px; font-size: 12px;'>";
    foreach ($recent_lines as $line) {
        if (strpos($line, 'Featured Image Generator') !== false || 
            strpos($line, 'NewsCrawler') !== false || 
            strpos($line, 'Genre Settings') !== false) {
            echo htmlspecialchars($line) . "\n";
        }
    }
    echo "</pre>";
} else {
    echo "エラーログファイルが見つかりません。<br>";
}
?>