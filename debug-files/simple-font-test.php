<?php
/**
 * シンプルなフォントテスト
 */

echo "<h1>フォントテスト</h1>\n";
echo "<pre>\n";

// 現在のディレクトリを確認
echo "現在のディレクトリ: " . __DIR__ . "\n";

// プラグインのルートディレクトリを確認
$plugin_root = dirname(__DIR__);
echo "プラグインルート: " . $plugin_root . "\n";

// フォントディレクトリを確認
$font_dir = $plugin_root . '/assets/fonts';
echo "フォントディレクトリ: " . $font_dir . "\n";

// フォントファイルの存在確認
$font_file = $font_dir . '/NotoSansJP-Regular.ttf';
echo "フォントファイル: " . $font_file . "\n";
echo "存在: " . (file_exists($font_file) ? 'YES' : 'NO') . "\n";
echo "読み取り可能: " . (is_readable($font_file) ? 'YES' : 'NO') . "\n";

if (file_exists($font_file)) {
    echo "サイズ: " . filesize($font_file) . " bytes\n";
    
    // GDライブラリの確認
    if (extension_loaded('gd')) {
        echo "GDライブラリ: 利用可能\n";
        
        if (function_exists('imagettftext')) {
            echo "FreeType: 利用可能\n";
            
            // フォントテスト
            $test_image = imagecreatetruecolor(200, 100);
            $white = imagecolorallocate($test_image, 255, 255, 255);
            $black = imagecolorallocate($test_image, 0, 0, 0);
            
            // 背景を白で塗りつぶし
            imagefill($test_image, 0, 0, $white);
            
            // フォントでテキストを描画
            $text = 'テスト';
            $bbox = imagettfbbox(20, 0, $font_file, $text);
            
            if ($bbox !== false) {
                echo "フォントテスト成功!\n";
                echo "境界ボックス: " . implode(', ', $bbox) . "\n";
                
                // テキストを描画
                $result = imagettftext($test_image, 20, 0, 10, 50, $black, $font_file, $text);
                if ($result !== false) {
                    echo "テキスト描画成功!\n";
                    
                    // 画像を保存
                    $output_file = $plugin_root . '/debug-files/font-test-output.png';
                    if (imagepng($test_image, $output_file)) {
                        echo "テスト画像保存成功: " . $output_file . "\n";
                    } else {
                        echo "テスト画像保存失敗\n";
                    }
                } else {
                    echo "テキスト描画失敗\n";
                }
            } else {
                echo "フォントテスト失敗: 境界ボックス取得エラー\n";
            }
            
            imagedestroy($test_image);
        } else {
            echo "FreeType: 利用不可\n";
        }
    } else {
        echo "GDライブラリ: 利用不可\n";
    }
} else {
    echo "フォントファイルが見つかりません\n";
}

echo "</pre>\n";
?>
