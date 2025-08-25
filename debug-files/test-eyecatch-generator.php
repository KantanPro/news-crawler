<?php
/**
 * アイキャッチジェネレーターのテスト
 */

// WordPress環境の読み込み
require_once('../../../wp-load.php');

echo "<h1>アイキャッチジェネレーターのテスト</h1>\n";
echo "<pre>\n";

// クラスファイルを読み込み
require_once(dirname(__DIR__) . '/includes/class-eyecatch-generator.php');

if (class_exists('News_Crawler_Eyecatch_Generator')) {
    echo "✓ News_Crawler_Eyecatch_Generatorクラスが読み込まれました\n";
    
    $generator = new News_Crawler_Eyecatch_Generator();
    
    // リフレクションを使用してプライベートメソッドにアクセス
    $reflection = new ReflectionClass($generator);
    $method = $reflection->getMethod('get_japanese_font_path');
    $method->setAccessible(true);
    
    echo "\n=== フォント検索のテスト ===\n";
    $font_path = $method->invoke($generator);
    
    if ($font_path) {
        echo "✓ フォントが見つかりました: " . $font_path . "\n";
        echo "  存在: " . (file_exists($font_path) ? 'YES' : 'NO') . "\n";
        echo "  読み取り可能: " . (is_readable($font_path) ? 'YES' : 'NO') . "\n";
        echo "  サイズ: " . filesize($font_path) . " bytes\n";
        
        // フォントのテスト
        echo "\n=== フォントテスト ===\n";
        $test_image = imagecreatetruecolor(100, 100);
        $white = imagecolorallocate($test_image, 255, 255, 255);
        $black = imagecolorallocate($test_image, 0, 0, 0);
        
        $bbox = imagettfbbox(20, 0, $font_path, 'テスト');
        if ($bbox !== false) {
            echo "✓ フォントテスト成功: 境界ボックス取得OK\n";
            echo "  境界ボックス: " . implode(', ', $bbox) . "\n";
        } else {
            echo "✗ フォントテスト失敗: 境界ボックス取得エラー\n";
        }
        
        imagedestroy($test_image);
        
        // 実際のアイキャッチ生成をテスト
        echo "\n=== アイキャッチ生成のテスト ===\n";
        try {
            $result = $generator->generate_eyecatch('テクノロジー', 'AI', '2024年8月25日');
            
            if (is_wp_error($result)) {
                echo "✗ アイキャッチ生成エラー: " . $result->get_error_message() . "\n";
                echo "  エラーコード: " . $result->get_error_code() . "\n";
            } else {
                echo "✓ アイキャッチ生成成功!\n";
                echo "  URL: " . $result . "\n";
            }
        } catch (Exception $e) {
            echo "✗ 例外が発生: " . $e->getMessage() . "\n";
        }
        
    } else {
        echo "✗ フォントが見つかりません\n";
    }
    
} else {
    echo "✗ News_Crawler_Eyecatch_Generatorクラスが見つかりません\n";
}

echo "\n=== テスト完了 ===\n";
echo "</pre>\n";
?>
