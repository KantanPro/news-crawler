<?php
/**
 * フォントファイル読み込みテスト
 */

// エラーレポートを有効化
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>フォントファイル読み込みテスト</h1>\n";

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

// フォントファイルの確認
echo "<h2>フォントファイルの確認</h2>\n";
$font_dir = __DIR__ . '/../assets/fonts/';
echo "フォントディレクトリ: " . $font_dir . "<br>\n";

if (is_dir($font_dir)) {
    echo "✓ フォントディレクトリが存在します<br>\n";
    
    $fonts = scandir($font_dir);
    echo "フォントファイル一覧:<br>\n";
    foreach ($fonts as $font) {
        if ($font !== '.' && $font !== '..') {
            $font_path = $font_dir . $font;
            $file_size = filesize($font_path);
            $readable = is_readable($font_path) ? '読み取り可能' : '読み取り不可';
            echo "- {$font} ({$file_size} bytes, {$readable})<br>\n";
        }
    }
} else {
    echo "✗ フォントディレクトリが存在しません<br>\n";
}

// 日本語フォントのテスト
echo "<h2>日本語フォントのテスト</h2>\n";
$test_fonts = array(
    $font_dir . 'NotoSansJP-Regular.otf',
    $font_dir . 'NotoSansJP-Regular.ttf'
);

foreach ($test_fonts as $font_path) {
    if (file_exists($font_path)) {
        echo "テスト対象フォント: " . basename($font_path) . "<br>\n";
        
        try {
            // 簡単な日本語文字でテスト
            $test_bbox = imagettfbbox(20, 0, $font_path, 'あ');
            if (is_array($test_bbox)) {
                echo "✓ フォント読み込み成功: " . basename($font_path) . "<br>\n";
                echo "  BBox: " . implode(', ', $test_bbox) . "<br>\n";
            } else {
                echo "✗ フォント読み込み失敗: " . basename($font_path) . "<br>\n";
            }
        } catch (Exception $e) {
            echo "✗ フォント読み込みエラー: " . basename($font_path) . " - " . $e->getMessage() . "<br>\n";
        }
    } else {
        echo "フォントファイルが存在しません: " . basename($font_path) . "<br>\n";
    }
}

echo "<h2>テスト完了</h2>\n";
echo "フォントファイルの読み込みテストが完了しました。";
?>
