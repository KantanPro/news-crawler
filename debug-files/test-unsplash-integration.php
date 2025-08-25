<?php
/**
 * Unsplash画像取得の統合テスト
 */

try {
    // WordPressを読み込み
    $wp_root = '/var/www/html';
    require_once($wp_root . '/wp-config.php');
    require_once($wp_root . '/wp-load.php');
    
    echo "<h1>Unsplash画像取得の統合テスト</h1>";
    
    // フィーチャー画像生成クラスをインスタンス化
    if (class_exists('NewsCrawlerFeaturedImageGenerator')) {
        echo "✅ NewsCrawlerFeaturedImageGeneratorクラスが見つかりました<br>";
        
        $generator = new NewsCrawlerFeaturedImageGenerator();
        echo "✅ フィーチャー画像生成クラスのインスタンスを作成しました<br>";
        
        // テスト用の投稿ID（存在しない投稿IDでも問題ありません）
        $test_post_id = 999;
        $test_title = 'テスト投稿 - 政治・経済ニュース';
        $test_keywords = array('政治', '経済', 'ニュース');
        
        echo "<h2>テストパラメータ</h2>";
        echo "- 投稿ID: " . $test_post_id . "<br>";
        echo "- タイトル: " . $test_title . "<br>";
        echo "- キーワード: " . implode(', ', $test_keywords) . "<br>";
        echo "- 生成方法: unsplash<br>";
        
        echo "<h2>Unsplash画像取得テスト開始</h2>";
        
        // Unsplash画像取得をテスト
        $result = $generator->generate_and_set_featured_image($test_post_id, $test_title, $test_keywords, 'unsplash');
        
        echo "<h2>テスト結果</h2>";
        if ($result) {
            echo "✅ <strong>成功！</strong><br>";
            echo "- 結果: " . $result . "<br>";
            echo "- 画像が正常に取得・設定されました<br>";
        } else {
            echo "❌ <strong>失敗</strong><br>";
            echo "- 画像の取得・設定に失敗しました<br>";
            echo "- エラーログを確認してください<br>";
        }
        
    } else {
        echo "❌ NewsCrawlerFeaturedImageGeneratorクラスが見つかりません<br>";
    }
    
    echo "<h2>次のステップ</h2>";
    echo "1. エラーログを確認する（wp-content/debug.log）<br>";
    echo "2. 設定画面でUnsplash Access Keyが正しく設定されているか確認する<br>";
    echo "3. 実際の投稿作成時にアイキャッチが生成されるかテストする<br>";
    
} catch (Exception $e) {
    echo "エラーが発生しました: " . $e->getMessage();
    echo "<br>スタックトレース: <pre>" . $e->getTraceAsString() . "</pre>";
}
?>
