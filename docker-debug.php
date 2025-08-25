<?php
/**
 * Docker環境でのデバッグスクリプト
 */

// WordPressを読み込み（プラグインディレクトリからWordPressルートへ）
$wp_root = dirname(dirname(dirname(__DIR__)));
require_once($wp_root . '/wp-config.php');
require_once($wp_root . '/wp-load.php');

echo "<h1>Docker環境でのアイキャッチ生成デバッグ</h1>";

// 1. 最新投稿の確認
echo "<h2>1. 最新投稿の確認</h2>";
$post = get_post(344);
if ($post) {
    echo "投稿ID 344: {$post->post_title}<br>";
    echo "ステータス: {$post->post_status}<br>";
    echo "作成日: {$post->post_date}<br>";
    
    $thumbnail_id = get_post_thumbnail_id(344);
    if ($thumbnail_id) {
        echo "✅ アイキャッチ: 有り (ID: {$thumbnail_id})<br>";
        $thumbnail_url = wp_get_attachment_url($thumbnail_id);
        echo "URL: {$thumbnail_url}<br>";
        echo "<img src='{$thumbnail_url}' style='max-width: 300px;'><br>";
    } else {
        echo "❌ アイキャッチ: 無し<br>";
    }
} else {
    echo "❌ 投稿ID 344が見つかりません<br>";
}

// 2. 一時保存された設定の確認
echo "<h2>2. 一時保存された設定</h2>";
$current_genre = get_transient('news_crawler_current_genre_setting');
if ($current_genre) {
    echo "✅ 一時保存された設定が存在します:<br>";
    echo "<pre>";
    print_r($current_genre);
    echo "</pre>";
} else {
    echo "❌ 一時保存された設定がありません<br>";
    
    // 全ての一時保存データを確認
    global $wpdb;
    $transients = $wpdb->get_results("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE '_transient_news_crawler%'");
    if (!empty($transients)) {
        echo "利用可能な一時保存データ:<br>";
        foreach ($transients as $transient) {
            echo "- {$transient->option_name}<br>";
        }
    } else {
        echo "一時保存データが全く見つかりません<br>";
    }
}

// 3. ジャンル設定の確認
echo "<h2>3. ジャンル設定</h2>";
$genre_settings = get_option('news_crawler_genre_settings', array());
if (!empty($genre_settings)) {
    foreach ($genre_settings as $id => $setting) {
        if ($setting['genre_name'] === '政治・経済') {
            echo "<strong>政治・経済ジャンル設定:</strong><br>";
            echo "- アイキャッチ自動生成: " . (isset($setting['auto_featured_image']) && $setting['auto_featured_image'] ? '✅ 有効' : '❌ 無効') . "<br>";
            if (isset($setting['featured_image_method'])) {
                echo "- 生成方法: {$setting['featured_image_method']}<br>";
            }
            echo "<pre>";
            print_r($setting);
            echo "</pre>";
            break;
        }
    }
} else {
    echo "❌ ジャンル設定が見つかりません<br>";
}

// 4. 手動でアイキャッチ生成をテスト
echo "<h2>4. 手動アイキャッチ生成テスト</h2>";
if (class_exists('NewsCrawlerFeaturedImageGenerator')) {
    echo "✅ NewsCrawlerFeaturedImageGeneratorクラス: 存在<br>";
    
    // テスト用設定を一時保存
    $test_setting = array(
        'auto_featured_image' => 1,
        'featured_image_method' => 'template'
    );
    set_transient('news_crawler_current_genre_setting', $test_setting, 300);
    echo "✅ テスト用設定を一時保存<br>";
    
    $generator = new NewsCrawlerFeaturedImageGenerator();
    echo "✅ ジェネレーターインスタンス作成<br>";
    
    echo "<strong>テンプレート生成を実行中...</strong><br>";
    $result = $generator->generate_and_set_featured_image(
        344, 
        $post->post_title, 
        array('政治', '経済', 'ニュース'), 
        'template'
    );
    
    if ($result) {
        echo "✅ <strong>手動テスト成功！</strong><br>";
        echo "添付ファイルID: {$result}<br>";
        $thumbnail_url = wp_get_attachment_url($result);
        echo "画像URL: {$thumbnail_url}<br>";
        echo "<img src='{$thumbnail_url}' style='max-width: 400px; border: 1px solid #ccc;'><br>";
    } else {
        echo "❌ <strong>手動テスト失敗</strong><br>";
    }
} else {
    echo "❌ NewsCrawlerFeaturedImageGeneratorクラス: 見つかりません<br>";
}

// 5. Docker環境の確認
echo "<h2>5. Docker環境の確認</h2>";
echo "PHP GD拡張: " . (extension_loaded('gd') ? '✅ 有効' : '❌ 無効') . "<br>";
$upload_dir = wp_upload_dir();
echo "アップロードディレクトリ: {$upload_dir['path']}<br>";
echo "書き込み権限: " . (is_writable($upload_dir['path']) ? '✅ OK' : '❌ NG') . "<br>";

// 6. エラーログの確認（Docker環境）
echo "<h2>6. エラーログの確認</h2>";
$possible_logs = array(
    '/var/log/apache2/error.log',
    '/var/log/php_errors.log',
    '/tmp/error.log',
    ini_get('error_log')
);

$log_found = false;
foreach ($possible_logs as $log_file) {
    if ($log_file && file_exists($log_file)) {
        echo "ログファイル発見: {$log_file}<br>";
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
            echo "<h3>関連ログエントリ:</h3>";
            echo "<pre style='height: 300px; overflow-y: scroll; background: #f0f0f0; padding: 10px; font-size: 12px;'>";
            foreach ($relevant_lines as $line) {
                echo htmlspecialchars($line) . "\n";
            }
            echo "</pre>";
            $log_found = true;
            break;
        }
    }
}

if (!$log_found) {
    echo "❌ 関連するエラーログが見つかりません<br>";
    echo "PHPエラーログ設定:<br>";
    echo "- log_errors: " . (ini_get('log_errors') ? 'On' : 'Off') . "<br>";
    echo "- error_log: " . (ini_get('error_log') ? ini_get('error_log') : '未設定') . "<br>";
}

// 7. 問題の診断
echo "<h2>7. 問題の診断</h2>";
echo "<ol>";
if (!$current_genre) {
    echo "<li><strong>主要問題:</strong> 一時保存された設定が見つかりません</li>";
    echo "<li><strong>原因:</strong> ジャンル設定でアイキャッチ生成が無効、または設定保存に失敗</li>";
    echo "<li><strong>対策:</strong> WordPress管理画面でジャンル設定を確認・修正</li>";
} else {
    echo "<li><strong>設定は正常:</strong> 一時保存された設定が存在します</li>";
    echo "<li><strong>確認事項:</strong> maybe_generate_featured_imageが呼び出されているか</li>";
}
echo "</ol>";

echo "<h2>次のステップ</h2>";
echo "<p>1. 上記の手動テストが成功した場合、設定の問題です</p>";
echo "<p>2. WordPress管理画面でジャンル設定のアイキャッチ生成を有効にしてください</p>";
echo "<p>3. 再度投稿を作成してテストしてください</p>";
?>