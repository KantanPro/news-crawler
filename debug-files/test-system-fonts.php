<?php
/**
 * システムフォントテスト
 */

echo "<h1>システムフォントテスト</h1>\n";
echo "<pre>\n";

// システムフォントのリスト
$system_fonts = array(
    '/System/Library/Fonts/STHeiti Medium.ttc',
    '/System/Library/Fonts/Hiragino Sans GB.ttc',
    '/System/Library/Fonts/PingFang.ttc',
    '/System/Library/Fonts/STHeiti Light.ttc'
);

echo "=== システムフォントのテスト ===\n";

foreach ($system_fonts as $font) {
    echo "\nフォント: " . $font . "\n";
    echo "存在: " . (file_exists($font) ? 'YES' : 'NO') . "\n";
    echo "読み取り可能: " . (is_readable($font) ? 'YES' : 'NO') . "\n";
    
    if (file_exists($font) && is_readable($font)) {
        echo "サイズ: " . filesize($font) . " bytes\n";
        
        // GDライブラリとFreeTypeの確認
        if (extension_loaded('gd') && function_exists('imagettftext')) {
            echo "フォントテスト開始...\n";
            
            // テスト画像を作成
            $test_image = imagecreatetruecolor(200, 100);
            $white = imagecolorallocate($test_image, 255, 255, 255);
            $black = imagecolorallocate($test_image, 0, 0, 0);
            
            // 背景を白で塗りつぶし
            imagefill($test_image, 0, 0, $white);
            
            // フォントでテキストを描画
            $text = 'テスト';
            
            try {
                $bbox = imagettfbbox(20, 0, $font, $text);
                
                if ($bbox !== false && is_array($bbox) && count($bbox) >= 8) {
                    echo "フォントテスト成功!\n";
                    echo "境界ボックス: " . implode(', ', $bbox) . "\n";
                    
                    // テキストを描画
                    $result = imagettftext($test_image, 20, 0, 10, 50, $black, $font, $text);
                    if ($result !== false) {
                        echo "テキスト描画成功!\n";
                        
                        // 画像を保存
                        $output_file = dirname(__DIR__) . '/debug-files/system-font-test-' . basename($font) . '.png';
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
            } catch (Exception $e) {
                echo "フォントテストエラー: " . $e->getMessage() . "\n";
            }
            
            imagedestroy($test_image);
        } else {
            echo "GDライブラリまたはFreeTypeが利用できません\n";
        }
    }
}

echo "\n=== テスト完了 ===\n";
echo "</pre>\n";
?>
