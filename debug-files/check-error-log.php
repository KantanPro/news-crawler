<?php
/**
 * エラーログ確認スクリプト
 */

echo "<h2>最新のエラーログ（アイキャッチ生成関連）</h2>";

// エラーログファイルのパスを取得
$log_file = ini_get('error_log');
if (!$log_file) {
    // 一般的なログファイルの場所を試す
    $possible_logs = array(
        '/var/log/apache2/error.log',
        '/var/log/httpd/error_log',
        '/Applications/MAMP/logs/php_error.log',
        '/usr/local/var/log/php-fpm.log',
        dirname(__FILE__) . '/error_log'
    );
    
    foreach ($possible_logs as $log) {
        if (file_exists($log)) {
            $log_file = $log;
            break;
        }
    }
}

echo "ログファイル: " . ($log_file ? $log_file : '見つかりません') . "<br><br>";

if ($log_file && file_exists($log_file)) {
    $log_content = file_get_contents($log_file);
    $lines = explode("\n", $log_content);
    
    // 最新100行から関連するログを抽出
    $recent_lines = array_slice($lines, -100);
    $relevant_lines = array();
    
    foreach ($recent_lines as $line) {
        if (strpos($line, 'Featured Image Generator') !== false || 
            strpos($line, 'NewsCrawler') !== false || 
            strpos($line, 'Genre Settings') !== false ||
            strpos($line, 'maybe_generate_featured_image') !== false) {
            $relevant_lines[] = $line;
        }
    }
    
    if (!empty($relevant_lines)) {
        echo "<h3>関連するログエントリ（最新" . count($relevant_lines) . "件）</h3>";
        echo "<pre style='height: 400px; overflow-y: scroll; background: #f0f0f0; padding: 10px; font-size: 12px; border: 1px solid #ccc;'>";
        foreach ($relevant_lines as $line) {
            echo htmlspecialchars($line) . "\n";
        }
        echo "</pre>";
    } else {
        echo "<p>アイキャッチ生成関連のログエントリが見つかりません。</p>";
        
        // 最新10行を表示
        echo "<h3>最新のログエントリ（参考）</h3>";
        echo "<pre style='height: 200px; overflow-y: scroll; background: #f0f0f0; padding: 10px; font-size: 12px; border: 1px solid #ccc;'>";
        $latest_lines = array_slice($lines, -10);
        foreach ($latest_lines as $line) {
            if (!empty(trim($line))) {
                echo htmlspecialchars($line) . "\n";
            }
        }
        echo "</pre>";
    }
} else {
    echo "<p>エラーログファイルが見つかりません。</p>";
    echo "<p>PHPの設定を確認してください：</p>";
    echo "<ul>";
    echo "<li>log_errors: " . (ini_get('log_errors') ? 'On' : 'Off') . "</li>";
    echo "<li>error_log: " . (ini_get('error_log') ? ini_get('error_log') : '未設定') . "</li>";
    echo "<li>error_reporting: " . error_reporting() . "</li>";
    echo "</ul>";
}

// 投稿338の詳細も表示
$wp_root = dirname(dirname(dirname(__DIR__)));
require_once($wp_root . '/wp-config.php');
require_once($wp_root . '/wp-load.php');

echo "<hr><h2>投稿ID 338の詳細</h2>";

$post = get_post(338);
if ($post) {
    echo "<h3>投稿情報</h3>";
    echo "タイトル: " . $post->post_title . "<br>";
    echo "ステータス: " . $post->post_status . "<br>";
    echo "作成日: " . $post->post_date . "<br>";
    
    // アイキャッチの確認
    $thumbnail_id = get_post_thumbnail_id(338);
    echo "<h3>アイキャッチ情報</h3>";
    if ($thumbnail_id) {
        echo "✅ アイキャッチID: " . $thumbnail_id . "<br>";
        $thumbnail_url = wp_get_attachment_url($thumbnail_id);
        echo "アイキャッチURL: " . $thumbnail_url . "<br>";
        echo "<img src='{$thumbnail_url}' style='max-width: 400px; border: 1px solid #ccc;'><br>";
    } else {
        echo "❌ アイキャッチなし<br>";
    }
} else {
    echo "投稿ID 338が見つかりません。";
}

// 現在のジャンル設定（一時保存）を確認
echo "<h3>現在のジャンル設定（一時保存）</h3>";
$current_genre = get_transient('news_crawler_current_genre_setting');
if ($current_genre) {
    echo "✅ 一時保存された設定が存在します：<br>";
    echo "<pre>";
    print_r($current_genre);
    echo "</pre>";
} else {
    echo "❌ 一時保存されたジャンル設定がありません。<br>";
}
?>