<?php
/**
 * アイキャッチ生成テスト
 */

// WordPressの読み込みをシミュレート
define('ABSPATH', dirname(__FILE__) . '/../../../../');
require_once ABSPATH . 'wp-config.php';

// エラーレポートを有効化
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>アイキャッチ生成テスト</h1>\n";

try {
    // アイキャッチ生成クラスを読み込み
    require_once __DIR__ . '/../includes/class-featured-image-generator.php';
    
    if (class_exists('NewsCrawlerFeaturedImageGenerator')) {
        echo "✓ NewsCrawlerFeaturedImageGeneratorクラスが読み込まれました<br>\n";
        
        // インスタンスを作成
        $generator = new NewsCrawlerFeaturedImageGenerator();
        echo "✓ アイキャッチ生成クラスのインスタンスが作成されました<br>\n";
        
        // テスト用のパラメータ
        $test_post_id = 999; // テスト用の投稿ID
        $test_title = 'テスト記事：AI技術の最新動向について';
        $test_keywords = array('AI', 'テクノロジー', 'ニュース');
        $test_method = 'template';
        
        echo "<h2>テストパラメータ</h2>\n";
        echo "投稿ID: {$test_post_id}<br>\n";
        echo "タイトル: {$test_title}<br>\n";
        echo "キーワード: " . implode(', ', $test_keywords) . "<br>\n";
        echo "生成方法: {$test_method}<br>\n";
        
        echo "<h2>アイキャッチ生成テスト</h2>\n";
        
        // アイキャッチ生成を実行
        $result = $generator->generate_and_set_featured_image(
            $test_post_id,
            $test_title,
            $test_keywords,
            $test_method
        );
        
        if ($result) {
            echo "✓ アイキャッチ生成成功！<br>\n";
            echo "結果: " . $result . "<br>\n";
        } else {
            echo "✗ アイキャッチ生成失敗<br>\n";
        }
        
    } else {
        echo "✗ NewsCrawlerFeaturedImageGeneratorクラスが読み込めません<br>\n";
    }
    
} catch (Exception $e) {
    echo "✗ エラーが発生しました: " . $e->getMessage() . "<br>\n";
    echo "スタックトレース: <pre>" . $e->getTraceAsString() . "</pre><br>\n";
}

echo "<h2>テスト完了</h2>\n";
echo "アイキャッチ生成のテストが完了しました。";
?>
