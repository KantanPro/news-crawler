<?php
/**
 * アイキャッチ生成問題の診断スクリプト
 */

// WordPressを読み込み（プラグインディレクトリからWordPressルートへ）
$wp_root = dirname(dirname(dirname(__DIR__)));
require_once($wp_root . '/wp-config.php');
require_once($wp_root . '/wp-load.php');

echo "<h1>アイキャッチ生成問題の診断</h1>";

// 1. 最新投稿の確認
echo "<h2>1. 最新投稿の確認</h2>";
$latest_posts = get_posts(array('numberposts' => 3, 'post_status' => 'any'));
foreach ($latest_posts as $post) {
    $thumbnail_id = get_post_thumbnail_id($post->ID);
    echo "投稿ID {$post->ID}: {$post->post_title} - アイキャッチ: " . ($thumbnail_id ? "有り (ID: {$thumbnail_id})" : "無し") . "<br>";
}

// 2. ジャンル設定の確認
echo "<h2>2. ジャンル設定の確認</h2>";
$genre_settings = get_option('news_crawler_genre_settings', array());
if (empty($genre_settings)) {
    echo "❌ ジャンル設定が見つかりません<br>";
} else {
    foreach ($genre_settings as $id => $setting) {
        echo "<strong>ジャンル: {$setting['genre_name']}</strong><br>";
        echo "- アイキャッチ自動生成: " . (isset($setting['auto_featured_image']) && $setting['auto_featured_image'] ? '✅ 有効' : '❌ 無効') . "<br>";
        if (isset($setting['featured_image_method'])) {
            echo "- 生成方法: {$setting['featured_image_method']}<br>";
        }
        echo "<br>";
    }
}

// 3. 一時保存された設定の確認
echo "<h2>3. 一時保存された設定の確認</h2>";
$current_genre = get_transient('news_crawler_current_genre_setting');
if ($current_genre) {
    echo "✅ 一時保存された設定が存在します:<br>";
    echo "<pre>";
    print_r($current_genre);
    echo "</pre>";
} else {
    echo "❌ 一時保存された設定がありません<br>";
    echo "これが主な原因の可能性があります。投稿作成時に設定が保存されていない可能性があります。<br>";
}

// 4. クラスの存在確認
echo "<h2>4. 必要なクラスの確認</h2>";
if (class_exists('NewsCrawlerFeaturedImageGenerator')) {
    echo "✅ NewsCrawlerFeaturedImageGeneratorクラス: 存在<br>";
} else {
    echo "❌ NewsCrawlerFeaturedImageGeneratorクラス: 見つかりません<br>";
}

// 5. 手動でアイキャッチ生成をテスト
echo "<h2>5. 手動アイキャッチ生成テスト</h2>";
if (!empty($latest_posts)) {
    $test_post = $latest_posts[0];
    echo "テスト対象: 投稿ID {$test_post->ID}<br>";
    
    if (class_exists('NewsCrawlerFeaturedImageGenerator')) {
        // テスト用設定を一時保存
        $test_setting = array(
            'auto_featured_image' => 1,
            'featured_image_method' => 'template'
        );
        set_transient('news_crawler_current_genre_setting', $test_setting, 300);
        echo "✅ テスト用設定を一時保存しました<br>";
        
        $generator = new NewsCrawlerFeaturedImageGenerator();
        echo "✅ アイキャッチ生成クラスのインスタンス作成完了<br>";
        
        echo "<strong>テンプレート生成を実行中...</strong><br>";
        $result = $generator->generate_and_set_featured_image(
            $test_post->ID, 
            $test_post->post_title, 
            array('テスト', 'アイキャッチ', '生成'), 
            'template'
        );
        
        if ($result) {
            echo "✅ <strong>アイキャッチ生成成功！</strong><br>";
            echo "添付ファイルID: {$result}<br>";
            $thumbnail_url = wp_get_attachment_url($result);
            echo "画像URL: <a href='{$thumbnail_url}' target='_blank'>{$thumbnail_url}</a><br>";
            echo "<img src='{$thumbnail_url}' style='max-width: 400px; border: 1px solid #ccc; margin: 10px 0;'><br>";
        } else {
            echo "❌ <strong>アイキャッチ生成失敗</strong><br>";
        }
    }
}

// 6. WordPress設定の確認
echo "<h2>6. WordPress設定の確認</h2>";
$upload_dir = wp_upload_dir();
echo "アップロードディレクトリ: {$upload_dir['path']}<br>";
echo "アップロードURL: {$upload_dir['url']}<br>";
echo "書き込み権限: " . (is_writable($upload_dir['path']) ? '✅ OK' : '❌ NG') . "<br>";

// 7. PHP設定の確認
echo "<h2>7. PHP設定の確認</h2>";
echo "GD拡張: " . (extension_loaded('gd') ? '✅ 有効' : '❌ 無効') . "<br>";
echo "メモリ制限: " . ini_get('memory_limit') . "<br>";
echo "最大実行時間: " . ini_get('max_execution_time') . "秒<br>";

// 8. エラーログの確認
echo "<h2>8. 最新のエラーログ</h2>";
$log_file = ini_get('error_log');
if ($log_file && file_exists($log_file)) {
    $log_content = file_get_contents($log_file);
    $lines = explode("\n", $log_content);
    $recent_lines = array_slice($lines, -50);
    
    $relevant_lines = array();
    foreach ($recent_lines as $line) {
        if (strpos($line, 'Featured Image Generator') !== false || 
            strpos($line, 'NewsCrawler') !== false || 
            strpos($line, 'Genre Settings') !== false) {
            $relevant_lines[] = $line;
        }
    }
    
    if (!empty($relevant_lines)) {
        echo "<pre style='height: 300px; overflow-y: scroll; background: #f0f0f0; padding: 10px; font-size: 12px;'>";
        foreach ($relevant_lines as $line) {
            echo htmlspecialchars($line) . "\n";
        }
        echo "</pre>";
    } else {
        echo "関連するエラーログが見つかりません。<br>";
    }
} else {
    echo "エラーログファイルが見つかりません。<br>";
}

// 9. 推奨される対策
echo "<h2>9. 推奨される対策</h2>";
echo "<ol>";
echo "<li><strong>ジャンル設定を確認</strong>: WordPress管理画面でアイキャッチ生成が有効になっているか確認</li>";
echo "<li><strong>手動テストの結果を確認</strong>: 上記の手動テストが成功した場合、設定の問題です</li>";
echo "<li><strong>一時保存の問題</strong>: 投稿作成時に設定が正しく保存されていない可能性があります</li>";
echo "<li><strong>タイミングの問題</strong>: 一時保存の有効期限（5分）が切れている可能性があります</li>";
echo "</ol>";

echo "<h2>次のステップ</h2>";
echo "<p>1. 上記の手動テストが成功した場合は、ジャンル設定画面でアイキャッチ生成を有効にして再度投稿を作成してください。</p>";
echo "<p>2. 手動テストも失敗した場合は、エラーログを確認して具体的なエラーメッセージを教えてください。</p>";
?>