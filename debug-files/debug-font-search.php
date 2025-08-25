<?php
/**
 * フォント検索デバッグスクリプト
 * 
 * アイキャッチジェネレーターのフォント検索プロセスを詳細に確認
 */

// WordPress環境の読み込み
require_once('../../../wp-load.php');

echo "<h1>フォント検索デバッグ</h1>\n";
echo "<pre>\n";

// プラグインのパスを確認
$plugin_dir = plugin_dir_path(__FILE__) . '../';
echo "プラグインディレクトリ: " . $plugin_dir . "\n";

// フォントディレクトリのパスを確認
$font_dir = $plugin_dir . 'assets/fonts/';
echo "フォントディレクトリ: " . $font_dir . "\n";

// フォントファイルの存在確認
$font_files = array(
    $font_dir . 'NotoSansJP-Regular.ttf',
    $font_dir . 'NotoSansJP-Regular.otf'
);

echo "\n=== プラグイン内フォントファイルの確認 ===\n";
foreach ($font_files as $font_file) {
    echo "フォントファイル: " . $font_file . "\n";
    echo "  存在: " . (file_exists($font_file) ? 'YES' : 'NO') . "\n";
    echo "  読み取り可能: " . (is_readable($font_file) ? 'YES' : 'NO') . "\n";
    if (file_exists($font_file)) {
        echo "  サイズ: " . filesize($font_file) . " bytes\n";
        echo "  パーミッション: " . substr(sprintf('%o', fileperms($font_file)), -4) . "\n";
    }
    echo "\n";
}

// システムフォントの確認
echo "=== システムフォントの確認 ===\n";
$system_fonts = array(
    '/System/Library/Fonts/PingFang.ttc',
    '/System/Library/Fonts/Hiragino Sans GB.ttc',
    '/System/Library/Fonts/ヒラギノ角ゴシック W3.ttc',
    '/System/Library/Fonts/ヒラギノ角ゴシック W6.ttc',
    '/System/Library/Fonts/ヒラギノ角ゴシック W8.ttc',
    '/System/Library/Fonts/AquaKana.ttc',
    '/System/Library/Fonts/Osaka.ttf',
    '/Library/Fonts/Arial Unicode MS.ttf',
    '/Library/Fonts/ヒラギノ角ゴ Pro W3.otf',
    '/Library/Fonts/ヒラギノ角ゴ Pro W6.otf'
);

foreach ($system_fonts as $font) {
    echo "システムフォント: " . $font . "\n";
    echo "  存在: " . (file_exists($font) ? 'YES' : 'NO') . "\n";
    echo "  読み取り可能: " . (is_readable($font) ? 'YES' : 'NO') . "\n";
    if (file_exists($font)) {
        echo "  サイズ: " . filesize($font) . " bytes\n";
    }
    echo "\n";
}

// GDライブラリとFreeTypeの確認
echo "=== PHP拡張機能の確認 ===\n";
echo "GDライブラリ: " . (extension_loaded('gd') ? '利用可能' : '利用不可') . "\n";
echo "FreeTypeサポート: " . (function_exists('imagettftext') ? '利用可能' : '利用不可') . "\n";

if (extension_loaded('gd')) {
    $gd_info = gd_info();
    echo "GD情報:\n";
    foreach ($gd_info as $key => $value) {
        echo "  " . $key . ": " . (is_bool($value) ? ($value ? 'YES' : 'NO') : $value) . "\n";
    }
}

// 実際のフォント検索メソッドをテスト
echo "\n=== フォント検索メソッドのテスト ===\n";

// クラスファイルを読み込み
require_once($plugin_dir . 'includes/class-eyecatch-generator.php');

if (class_exists('News_Crawler_Eyecatch_Generator')) {
    $generator = new News_Crawler_Eyecatch_Generator();
    
    // リフレクションを使用してプライベートメソッドにアクセス
    $reflection = new ReflectionClass($generator);
    $method = $reflection->getMethod('get_japanese_font_path');
    $method->setAccessible(true);
    
    $font_path = $method->invoke($generator);
    
    echo "フォント検索結果: " . ($font_path ? $font_path : 'フォントが見つかりません') . "\n";
    
    if ($font_path) {
        echo "フォントファイルの詳細:\n";
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
            echo "フォントテスト成功: 境界ボックス取得OK\n";
            echo "  境界ボックス: " . implode(', ', $bbox) . "\n";
        } else {
            echo "フォントテスト失敗: 境界ボックス取得エラー\n";
        }
        
        imagedestroy($test_image);
    }
} else {
    echo "News_Crawler_Eyecatch_Generatorクラスが見つかりません\n";
}

echo "</pre>\n";
echo "<p><a href='javascript:location.reload()'>再読み込み</a></p>\n";
?>
