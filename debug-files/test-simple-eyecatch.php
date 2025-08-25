<?php
/**
 * シンプルなアイキャッチ生成テスト
 */

// エラーレポートを有効化
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>シンプルなアイキャッチ生成テスト</h1>\n";

// GD拡張の確認
echo "<h2>GD拡張の確認</h2>\n";
if (extension_loaded('gd')) {
    echo "✓ GD拡張が読み込まれています<br>\n";
    echo "GD情報: " . gd_info()['GD Version'] . "<br>\n";
} else {
    echo "✗ GD拡張が読み込まれていません<br>\n";
    exit;
}

// imagettftext関数の確認
echo "<h2>imagettftext関数の確認</h2>\n";
if (function_exists('imagettftext')) {
    echo "✓ imagettftext関数が利用可能です<br>\n";
} else {
    echo "✗ imagettftext関数が利用できません<br>\n";
    exit;
}

// システムフォントの確認
echo "<h2>システムフォントの確認</h2>\n";
$system_fonts = array(
    '/System/Library/Fonts/PingFang.ttc',
    '/System/Library/Fonts/Hiragino Sans GB.ttc',
    '/System/Library/Fonts/ヒラギノ角ゴシック W3.ttc'
);

foreach ($system_fonts as $font_path) {
    if (file_exists($font_path)) {
        echo "✓ フォントファイルが存在します: " . basename($font_path) . "<br>\n";
        echo "  パス: " . $font_path . "<br>\n";
        echo "  サイズ: " . number_format(filesize($font_path)) . " bytes<br>\n";
        echo "  読み取り可能: " . (is_readable($font_path) ? 'はい' : 'いいえ') . "<br>\n";
        
        if (is_readable($font_path)) {
            try {
                // 簡単な日本語文字でテスト
                $test_bbox = imagettfbbox(20, 0, $font_path, 'あ');
                if (is_array($test_bbox)) {
                    echo "  ✓ フォント読み込み成功<br>\n";
                    echo "    BBox: " . implode(', ', $test_bbox) . "<br>\n";
                } else {
                    echo "  ✗ フォント読み込み失敗<br>\n";
                }
            } catch (Exception $e) {
                echo "  ✗ フォント読み込みエラー: " . $e->getMessage() . "<br>\n";
            }
        }
        echo "<br>\n";
    } else {
        echo "✗ フォントファイルが存在しません: " . basename($font_path) . "<br>\n";
    }
}

// 簡単な画像生成テスト
echo "<h2>簡単な画像生成テスト</h2>\n";
try {
    // テスト画像を作成
    $width = 400;
    $height = 200;
    $image = imagecreatetruecolor($width, $height);
    
    if ($image) {
        echo "✓ 画像リソースが作成されました<br>\n";
        
        // 背景色を設定
        $bg_color = imagecolorallocate($image, 70, 70, 142);
        imagefill($image, 0, 0, $bg_color);
        
        // テキスト色を設定
        $text_color = imagecolorallocate($image, 255, 255, 255);
        
        // システムフォントを使用して日本語テキストを描画
        $font_path = '/System/Library/Fonts/PingFang.ttc';
        
        if (file_exists($font_path) && is_readable($font_path)) {
            echo "✓ フォントファイルを使用してテキストを描画します<br>\n";
            
            // 日本語テキストを描画
            $text = 'テスト日本語';
            $font_size = 24;
            
            $bbox = imagettfbbox($font_size, 0, $font_path, $text);
            if (is_array($bbox)) {
                $text_width = $bbox[4] - $bbox[0];
                $text_height = $bbox[1] - $bbox[7];
                
                $x = ($width - $text_width) / 2;
                $y = ($height + $text_height) / 2;
                
                imagettftext($image, $font_size, 0, $x, $y, $text_color, $font_path, $text);
                echo "✓ 日本語テキストが描画されました: {$text}<br>\n";
            } else {
                echo "✗ テキストの境界ボックスが取得できませんでした<br>\n";
            }
        } else {
            echo "✗ フォントファイルが利用できません<br>\n";
        }
        
        // 画像を保存
        $test_image_path = __DIR__ . '/test-eyecatch-output.png';
        if (imagepng($image, $test_image_path)) {
            echo "✓ テスト画像が保存されました: " . basename($test_image_path) . "<br>\n";
            echo "  サイズ: " . number_format(filesize($test_image_path)) . " bytes<br>\n";
        } else {
            echo "✗ 画像の保存に失敗しました<br>\n";
        }
        
        // 画像リソースを解放
        imagedestroy($image);
        echo "✓ 画像リソースが解放されました<br>\n";
        
    } else {
        echo "✗ 画像リソースの作成に失敗しました<br>\n";
    }
    
} catch (Exception $e) {
    echo "✗ 画像生成中にエラーが発生しました: " . $e->getMessage() . "<br>\n";
}

echo "<h2>テスト完了</h2>\n";
echo "シンプルなアイキャッチ生成テストが完了しました。";
?>
