<?php
/**
 * 簡単なアイキャッチ生成テスト（WordPress不要）
 */

echo "=== アイキャッチ生成機能テスト ===\n\n";

// GD拡張の確認
echo "1. GD拡張の確認\n";
if (extension_loaded('gd')) {
    echo "✅ GD拡張: 有効\n";
    $gd_info = gd_info();
    echo "   - GDバージョン: " . $gd_info['GD Version'] . "\n";
    echo "   - PNG対応: " . ($gd_info['PNG Support'] ? 'Yes' : 'No') . "\n";
    echo "   - JPEG対応: " . ($gd_info['JPEG Support'] ? 'Yes' : 'No') . "\n";
    echo "   - TrueType対応: " . ($gd_info['FreeType Support'] ? 'Yes' : 'No') . "\n";
} else {
    echo "❌ GD拡張: 無効\n";
    echo "   アイキャッチ生成にはGD拡張が必要です。\n";
    exit(1);
}

echo "\n2. 基本的な画像生成テスト\n";

// 画像作成
$width = 1200;
$height = 630;
$image = imagecreatetruecolor($width, $height);

if (!$image) {
    echo "❌ 画像作成失敗\n";
    exit(1);
}

echo "✅ 画像作成成功 ({$width}x{$height})\n";

// 背景色設定
$bg_color = imagecolorallocate($image, 79, 70, 229); // #4F46E5
imagefill($image, 0, 0, $bg_color);
echo "✅ 背景色設定完了\n";

// テキスト色設定
$text_color = imagecolorallocate($image, 255, 255, 255); // 白色

// フォントファイルの確認
echo "\n3. フォントファイルの確認\n";
$font_paths = array(
    '/System/Library/Fonts/Arial.ttf',
    '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
    '/usr/share/fonts/TTF/arial.ttf',
    __DIR__ . '/assets/fonts/NotoSansJP-Regular.ttf'
);

$font_path = false;
foreach ($font_paths as $path) {
    if (file_exists($path)) {
        $font_path = $path;
        echo "✅ フォントファイル発見: {$path}\n";
        break;
    }
}

if (!$font_path) {
    echo "⚠️  TTFフォントファイルが見つかりません。内蔵フォントを使用します。\n";
}

// テキスト描画
$test_text = "テスト用アイキャッチ画像";
echo "\n4. テキスト描画テスト\n";

if ($font_path && function_exists('imagettftext')) {
    // TTFフォントを使用
    $font_size = 48;
    $bbox = imagettfbbox($font_size, 0, $font_path, $test_text);
    $text_width = $bbox[4] - $bbox[0];
    $text_height = $bbox[1] - $bbox[7];
    
    $x = ($width - $text_width) / 2;
    $y = ($height - $text_height) / 2 + $text_height;
    
    imagettftext($image, $font_size, 0, $x, $y, $text_color, $font_path, $test_text);
    echo "✅ TTFフォントでテキスト描画完了\n";
} else {
    // 内蔵フォントを使用
    $font_size = 5;
    $text_width = imagefontwidth($font_size) * strlen($test_text);
    $text_height = imagefontheight($font_size);
    
    $x = ($width - $text_width) / 2;
    $y = ($height - $text_height) / 2;
    
    imagestring($image, $font_size, $x, $y, $test_text, $text_color);
    echo "✅ 内蔵フォントでテキスト描画完了\n";
}

// 画像保存テスト
echo "\n5. 画像保存テスト\n";
$filename = 'test-featured-image-' . time() . '.png';
$filepath = __DIR__ . '/' . $filename;

if (imagepng($image, $filepath)) {
    echo "✅ PNG画像保存成功: {$filename}\n";
    echo "   ファイルサイズ: " . number_format(filesize($filepath)) . " bytes\n";
    
    // ファイルを削除（テスト後のクリーンアップ）
    unlink($filepath);
    echo "✅ テストファイル削除完了\n";
} else {
    echo "❌ PNG画像保存失敗\n";
}

// メモリ解放
imagedestroy($image);

echo "\n6. クラスファイルの確認\n";
$class_file = __DIR__ . '/includes/class-featured-image-generator.php';
if (file_exists($class_file)) {
    echo "✅ アイキャッチ生成クラスファイル: 存在\n";
    
    // ファイルサイズ確認
    $file_size = filesize($class_file);
    echo "   ファイルサイズ: " . number_format($file_size) . " bytes\n";
    
    // 基本的な構文チェック
    $content = file_get_contents($class_file);
    if (strpos($content, 'class NewsCrawlerFeaturedImageGenerator') !== false) {
        echo "✅ クラス定義: 確認済み\n";
    } else {
        echo "❌ クラス定義: 見つかりません\n";
    }
    
    if (strpos($content, 'generate_and_set_featured_image') !== false) {
        echo "✅ メインメソッド: 確認済み\n";
    } else {
        echo "❌ メインメソッド: 見つかりません\n";
    }
} else {
    echo "❌ アイキャッチ生成クラスファイル: 見つかりません\n";
}

echo "\n=== テスト完了 ===\n";
echo "すべてのテストが正常に完了した場合、アイキャッチ生成機能は正常に動作するはずです。\n";
echo "WordPressが動作している環境で実際のテストを行ってください。\n";
?>