<?php
/**
 * スタンドアロンフォントテスト
 * WordPress環境なしでフォント検索とアイキャッチ生成をテスト
 */

echo "<h1>スタンドアロンフォントテスト</h1>\n";
echo "<pre>\n";

// エラーレポートを有効化
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 現在のディレクトリを確認
echo "現在のディレクトリ: " . __DIR__ . "\n";
$plugin_root = dirname(__DIR__);
echo "プラグインルート: " . $plugin_root . "\n";

// GDライブラリとFreeTypeの確認
echo "\n=== PHP拡張機能の確認 ===\n";
echo "GDライブラリ: " . (extension_loaded('gd') ? '利用可能' : '利用不可') . "\n";
echo "FreeTypeサポート: " . (function_exists('imagettftext') ? '利用可能' : '利用不可') . "\n";

if (extension_loaded('gd')) {
    $gd_info = gd_info();
    echo "GD情報:\n";
    foreach ($gd_info as $key => $value) {
        echo "  " . $key . ": " . (is_bool($value) ? ($value ? 'YES' : 'NO') : $value) . "\n";
    }
}

// フォント検索ロジックを再現
echo "\n=== フォント検索のテスト ===\n";

// 優先順位1: システムの日本語フォント（macOS、最も信頼性が高い）
$system_fonts = array(
    '/System/Library/Fonts/STHeiti Medium.ttc',  // 最も安定している
    '/System/Library/Fonts/STHeiti Light.ttc',   // 軽量版
    '/System/Library/Fonts/PingFang.ttc',        // モダンなフォント
    '/System/Library/Fonts/Hiragino Sans GB.ttc', // ヒラギノ
    '/System/Library/Fonts/ヒラギノ角ゴシック W3.ttc',
    '/System/Library/Fonts/ヒラギノ角ゴシック W6.ttc',
    '/System/Library/Fonts/ヒラギノ角ゴシック W8.ttc',
    '/System/Library/Fonts/AquaKana.ttc',
    '/System/Library/Fonts/Osaka.ttf',
    '/Library/Fonts/Arial Unicode MS.ttf',
    '/Library/Fonts/ヒラギノ角ゴ Pro W3.otf',
    '/Library/Fonts/ヒラギノ角ゴ Pro W6.otf'
);

echo "システムフォントの確認...\n";
$found_font = false;
foreach ($system_fonts as $font) {
    echo "  チェック中: " . $font . "\n";
    if (file_exists($font) && is_readable($font)) {
        echo "  ✓ 見つかりました: " . $font . "\n";
        $found_font = $font;
        break;
    } else {
        echo "  ✗ 見つかりません: " . $font . "\n";
    }
}

if (!$found_font) {
    // 優先順位2: プラグイン内のフォントファイル
    $plugin_fonts = array(
        $plugin_root . '/assets/fonts/NotoSansJP-Regular.ttf',
        $plugin_root . '/assets/fonts/NotoSansJP-Regular.otf'
    );
    
    echo "\nプラグイン内フォントの確認...\n";
    foreach ($plugin_fonts as $font) {
        echo "  チェック中: " . $font . "\n";
        if (file_exists($font) && is_readable($font)) {
            echo "  ✓ 見つかりました: " . $font . "\n";
            $found_font = $font;
            break;
        } else {
            echo "  ✗ 見つかりません: " . $font . "\n";
        }
    }
}

if ($found_font) {
    echo "\n=== フォントテスト ===\n";
    echo "使用フォント: " . $found_font . "\n";
    
    // フォントのテスト
    $test_image = imagecreatetruecolor(200, 100);
    $white = imagecolorallocate($test_image, 255, 255, 255);
    $black = imagecolorallocate($test_image, 0, 0, 0);
    
    // 背景を白で塗りつぶし
    imagefill($test_image, 0, 0, $white);
    
    // フォントでテキストを描画
    $text = 'テスト';
    
    try {
        $bbox = imagettfbbox(20, 0, $found_font, $text);
        
        if ($bbox !== false && is_array($bbox) && count($bbox) >= 8) {
            echo "✓ フォントテスト成功: 境界ボックス取得OK\n";
            echo "  境界ボックス: " . implode(', ', $bbox) . "\n";
            
            // テキストを描画
            $result = imagettftext($test_image, 20, 0, 10, 50, $black, $found_font, $text);
            if ($result !== false) {
                echo "✓ テキスト描画成功!\n";
                
                // 画像を保存
                $output_file = $plugin_root . '/debug-files/standalone-font-test-output.png';
                if (imagepng($test_image, $output_file)) {
                    echo "✓ テスト画像保存成功: " . $output_file . "\n";
                } else {
                    echo "✗ テスト画像保存失敗\n";
                }
            } else {
                echo "✗ テキスト描画失敗\n";
            }
        } else {
            echo "✗ フォントテスト失敗: 境界ボックス取得エラー\n";
        }
    } catch (Exception $e) {
        echo "✗ フォントテストエラー: " . $e->getMessage() . "\n";
    }
    
    imagedestroy($test_image);
    
    // アイキャッチ生成のテスト
    echo "\n=== アイキャッチ生成のテスト ===\n";
    try {
        $result = generate_test_eyecatch($found_font, 'テクノロジー', 'AI', '2024年8月25日');
        if ($result) {
            echo "✓ アイキャッチ生成成功!\n";
            echo "  ファイル: " . $result . "\n";
        } else {
            echo "✗ アイキャッチ生成失敗\n";
        }
    } catch (Exception $e) {
        echo "✗ アイキャッチ生成エラー: " . $e->getMessage() . "\n";
    }
    
} else {
    echo "\n✗ 日本語フォントが見つかりません\n";
}

echo "\n=== テスト完了 ===\n";
echo "</pre>\n";

/**
 * テスト用アイキャッチ生成
 */
function generate_test_eyecatch($font_path, $genre, $keyword, $date) {
    try {
        // 画像サイズ設定
        $width = 1200;
        $height = 630;
        
        // 画像を作成
        $image = imagecreatetruecolor($width, $height);
        
        if (!$image) {
            throw new Exception('画像の作成に失敗しました');
        }
        
        // 背景色（グラデーション）
        create_gradient_background($image, $width, $height);
        
        // テキストを描画
        draw_text($image, $genre, $font_path, 48, 0xFFFFFF, $width, 200);
        draw_text($image, $keyword, $font_path, 36, 0xFFFFFF, $width, 280);
        draw_text($image, 'ニュースまとめ', $font_path, 42, 0xFFFFFF, $width, 360);
        draw_text($image, $date, $font_path, 32, 0xFFFFFF, $width, 420);
        
        // 装飾要素を追加
        add_decorative_elements($image, $width, $height);
        
        // ファイルに保存
        $output_file = dirname(__DIR__) . '/debug-files/test-eyecatch-output.png';
        
        if (imagepng($image, $output_file)) {
            imagedestroy($image);
            return $output_file;
        } else {
            imagedestroy($image);
            return false;
        }
        
    } catch (Exception $e) {
        if (isset($image)) {
            imagedestroy($image);
        }
        throw $e;
    }
}

/**
 * グラデーション背景を作成
 */
function create_gradient_background($image, $width, $height) {
    $color1 = imagecolorallocate($image, 41, 128, 185);  // 青
    $color2 = imagecolorallocate($image, 52, 152, 219);  // 明るい青
    
    for ($y = 0; $y < $height; $y++) {
        $ratio = $y / $height;
        
        // 色の値を個別に計算し、0-255の範囲に制限
        $r1 = 41; $g1 = 128; $b1 = 185;
        $r2 = 52; $g2 = 152; $b2 = 219;
        
        $r = (int)max(0, min(255, $r1 + ($r2 - $r1) * $ratio));
        $g = (int)max(0, min(255, $g1 + ($g2 - $g1) * $ratio));
        $b = (int)max(0, min(255, $b1 + ($b2 - $b1) * $ratio));
        
        $color = imagecolorallocate($image, $r, $g, $b);
        imageline($image, 0, $y, $width, $y, $color);
    }
}

/**
 * テキストを描画（センタリング）
 */
function draw_text($image, $text, $font_path, $font_size, $color, $width, $y) {
    $bbox = imagettfbbox($font_size, 0, $font_path, $text);
    
    if ($bbox === false) {
        throw new Exception('フォントファイルの読み込みに失敗しました: ' . $font_path);
    }
    
    $text_width = $bbox[4] - $bbox[0];
    $text_height = $bbox[1] - $bbox[7];
    
    // センタリング位置を計算
    $x = ($width - $text_width) / 2;
    
    // Y座標を調整（テキストのベースラインに合わせる）
    $adjusted_y = $y + $text_height;
    
    $result = imagettftext($image, $font_size, 0, $x, $adjusted_y, $color, $font_path, $text);
    
    if ($result === false) {
        throw new Exception('テキストの描画に失敗しました: ' . $text);
    }
}

/**
 * 装飾要素を追加
 */
function add_decorative_elements($image, $width, $height) {
    $white = imagecolorallocate($image, 255, 255, 255);
    $alpha = imagecolorallocatealpha($image, 255, 255, 255, 80);
    
    // 左上の円
    imagefilledellipse($image, 100, 100, 60, 60, $alpha);
    imageellipse($image, 100, 100, 60, 60, $white);
    
    // 右下の円
    imagefilledellipse($image, $width - 100, $height - 100, 80, 80, $alpha);
    imageellipse($image, $width - 100, $height - 100, 80, 80, $white);
    
    // 中央上部に装飾線
    $line_color = imagecolorallocate($image, 255, 255, 255);
    imagefilledrectangle($image, $width/2 - 50, 150, $width/2 + 50, 155, $line_color);
}
?>
