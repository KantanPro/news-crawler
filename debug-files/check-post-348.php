<?php
/**
 * 投稿ID 348の詳細確認とアイキャッチ生成テスト
 */

// エラー表示を有効にする
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // WordPressを読み込み
    $wp_root = dirname(dirname(dirname(__DIR__)));
    require_once($wp_root . '/wp-config.php');
    require_once($wp_root . '/wp-load.php');
    
    echo "<h1>投稿ID 348の詳細確認</h1>";
    
    // 投稿の基本情報
    $post = get_post(348);
    if ($post) {
        echo "<h2>投稿情報</h2>";
        echo "タイトル: " . esc_html($post->post_title) . "<br>";
        echo "ステータス: " . $post->post_status . "<br>";
        echo "作成日: " . $post->post_date . "<br>";
        
        // アイキャッチの確認
        $thumbnail_id = get_post_thumbnail_id(348);
        echo "<h2>アイキャッチ情報</h2>";
        if ($thumbnail_id) {
            echo "✅ アイキャッチID: " . $thumbnail_id . "<br>";
            $thumbnail_url = wp_get_attachment_url($thumbnail_id);
            echo "アイキャッチURL: " . $thumbnail_url . "<br>";
            echo "<img src='{$thumbnail_url}' style='max-width: 400px; border: 1px solid #ccc;'><br>";
        } else {
            echo "❌ アイキャッチなし<br>";
        }
        
    } else {
        echo "❌ 投稿ID 348が見つかりません。<br>";
        exit;
    }
    
    // 現在のジャンル設定を確認
    echo "<h2>現在のジャンル設定（一時保存）</h2>";
    $current_genre = get_transient('news_crawler_current_genre_setting');
    if ($current_genre) {
        echo "✅ 一時保存された設定が存在します：<br>";
        echo "アイキャッチ自動生成: " . (isset($current_genre['auto_featured_image']) && $current_genre['auto_featured_image'] ? 'Yes' : 'No') . "<br>";
        if (isset($current_genre['featured_image_method'])) {
            echo "生成方法: " . $current_genre['featured_image_method'] . "<br>";
        }
        echo "<pre>";
        print_r($current_genre);
        echo "</pre>";
    } else {
        echo "❌ 一時保存されたジャンル設定がありません。<br>";
    }
    
    // 手動でアイキャッチ生成をテスト
    echo "<h2>手動アイキャッチ生成テスト</h2>";
    if (class_exists('NewsCrawlerFeaturedImageGenerator')) {
        echo "✅ NewsCrawlerFeaturedImageGeneratorクラス: 存在<br>";
        
        // テスト用の設定を一時保存
        $test_setting = array(
            'auto_featured_image' => 1,
            'featured_image_method' => 'template'
        );
        set_transient('news_crawler_current_genre_setting', $test_setting, 300);
        echo "✅ テスト用設定を一時保存しました<br>";
        
        echo "<strong>テンプレート生成をテスト中...</strong><br>";
        $generator = new NewsCrawlerFeaturedImageGenerator();
        $result = $generator->generate_and_set_featured_image(348, $post->post_title, array('政治', '経済', 'ニュース'), 'template');
        
        if ($result) {
            echo "✅ <strong>テンプレート生成成功！</strong><br>";
            echo "添付ファイルID: " . $result . "<br>";
            $thumbnail_url = wp_get_attachment_url($result);
            echo "画像URL: " . $thumbnail_url . "<br>";
            echo "<img src='{$thumbnail_url}' style='max-width: 400px; border: 1px solid #ccc;'><br>";
        } else {
            echo "❌ <strong>テンプレート生成失敗</strong><br>";
        }
    } else {
        echo "❌ NewsCrawlerFeaturedImageGeneratorクラスが見つかりません。<br>";
    }
    
    // maybe_generate_featured_imageメソッドを直接テスト
    echo "<h2>maybe_generate_featured_imageメソッドのテスト</h2>";
    if (class_exists('NewsCrawler')) {
        echo "✅ NewsCrawlerクラス: 存在<br>";
        
        // テスト用設定を再設定
        $test_setting = array(
            'auto_featured_image' => 1,
            'featured_image_method' => 'template'
        );
        set_transient('news_crawler_current_genre_setting', $test_setting, 300);
        
        // リフレクションを使ってprivateメソッドを呼び出し
        $crawler = new NewsCrawler();
        $reflection = new ReflectionClass($crawler);
        $method = $reflection->getMethod('maybe_generate_featured_image');
        $method->setAccessible(true);
        
        echo "<strong>maybe_generate_featured_imageを直接呼び出し中...</strong><br>";
        $result = $method->invoke($crawler, 348, $post->post_title, array('政治', '経済', 'ニュース'));
        
        if ($result) {
            echo "✅ <strong>maybe_generate_featured_image成功！</strong><br>";
            echo "添付ファイルID: " . $result . "<br>";
        } else {
            echo "❌ <strong>maybe_generate_featured_image失敗</strong><br>";
        }
    } else {
        echo "❌ NewsCrawlerクラスが見つかりません。<br>";
    }
    
    // エラーログの確認
    echo "<h2>最新のエラーログ</h2>";
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
            $recent_lines = array_slice($lines, -100);
            
            $relevant_lines = array();
            foreach ($recent_lines as $line) {
                if (strpos($line, 'Featured Image Generator') !== false || 
                    strpos($line, 'NewsCrawler') !== false || 
                    strpos($line, 'Genre Settings') !== false) {
                    $relevant_lines[] = $line;
                }
            }
            
            if (!empty($relevant_lines)) {
                echo "<h3>関連ログエントリ（最新" . count($relevant_lines) . "件）:</h3>";
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
        echo "❌ 関連するエラーログが見つかりません。<br>";
    }
    
} catch (Exception $e) {
    echo "<h1>エラーが発生しました</h1>";
    echo "エラーメッセージ: " . $e->getMessage() . "<br>";
    echo "ファイル: " . $e->getFile() . "<br>";
    echo "行: " . $e->getLine() . "<br>";
}
?>