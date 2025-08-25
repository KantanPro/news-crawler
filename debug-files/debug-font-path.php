<?php
/**
 * フォントパス解決のデバッグスクリプト
 */

echo "<h1>フォントパス解決デバッグ</h1>\n";

// 現在のファイルの場所
echo "<h2>現在のファイル情報</h2>\n";
echo "現在のファイル: " . __FILE__ . "<br>\n";
echo "現在のディレクトリ: " . __DIR__ . "<br>\n";
echo "プラグインルート: " . dirname(__DIR__) . "<br>\n";

// プラグイン内のフォントファイル
echo "<h2>プラグイン内フォントファイル</h2>\n";
$plugin_fonts = array(
    dirname(__DIR__) . '/assets/fonts/NotoSansJP-Regular.ttf',
    dirname(__DIR__) . '/assets/fonts/NotoSansJP-Regular.otf'
);

foreach ($plugin_fonts as $font) {
    echo "フォントパス: " . $font . "<br>\n";
    if (file_exists($font)) {
        echo "  - 存在: はい<br>\n";
        if (is_readable($font)) {
            echo "  - 読み取り可能: はい<br>\n";
            echo "  - サイズ: " . filesize($font) . " bytes<br>\n";
        } else {
            echo "  - 読み取り可能: いいえ<br>\n";
        }
    } else {
        echo "  - 存在: いいえ<br>\n";
    }
    echo "<br>\n";
}

// システムフォント
echo "<h2>システムフォント</h2>\n";
$system_fonts = array(
    '/System/Library/Fonts/STHeiti Medium.ttc',
    '/System/Library/Fonts/PingFang.ttc',
    '/System/Library/Fonts/ヒラギノ角ゴシック W3.ttc'
);

foreach ($system_fonts as $font) {
    echo "フォントパス: " . $font . "<br>\n";
    if (file_exists($font)) {
        echo "  - 存在: はい<br>\n";
        if (is_readable($font)) {
            echo "  - 読み取り可能: はい<br>\n";
            echo "  - サイズ: " . filesize($font) . " bytes<br>\n";
            
            // FreeTypeテスト
            if (function_exists('imagettfbbox')) {
                $bbox = imagettfbbox(20, 0, $font, 'テスト');
                if ($bbox !== false) {
                    echo "  - FreeTypeテスト: 成功<br>\n";
                } else {
                    echo "  - FreeTypeテスト: 失敗<br>\n";
                }
            } else {
                echo "  - FreeType関数: 利用不可<br>\n";
            }
        } else {
            echo "  - 読み取り可能: いいえ<br>\n";
        }
    } else {
        echo "  - 存在: いいえ<br>\n";
    }
    echo "<br>\n";
}

// 絶対パスでのテスト
echo "<h2>絶対パステスト</h2>\n";
$absolute_paths = array(
    '/Users/kantanpro/Desktop/KantanPro/wordpress/wp-content/plugins/news-crawler/assets/fonts/NotoSansJP-Regular.ttf',
    '/Users/kantanpro/Desktop/KantanPro/wordpress/wp-content/plugins/news-crawler/assets/fonts/NotoSansJP-Regular.otf'
);

foreach ($absolute_paths as $font) {
    echo "フォントパス: " . $font . "<br>\n";
    if (file_exists($font)) {
        echo "  - 存在: はい<br>\n";
        if (is_readable($font)) {
            echo "  - 読み取り可能: はい<br>\n";
            echo "  - サイズ: " . filesize($font) . " bytes<br>\n";
            
            // FreeTypeテスト
            if (function_exists('imagettfbbox')) {
                $bbox = imagettfbbox(20, 0, $font, 'テスト');
                if ($bbox !== false) {
                    echo "  - FreeTypeテスト: 成功<br>\n";
                } else {
                    echo "  - FreeTypeテスト: 失敗<br>\n";
                }
            } else {
                echo "  - FreeTypeテスト: 失敗<br>\n";
            }
        } else {
            echo "  - 読み取り可能: いいえ<br>\n";
        }
    } else {
        echo "  - 存在: いいえ<br>\n";
    }
    echo "<br>\n";
}

// 環境情報
echo "<h2>環境情報</h2>\n";
echo "PHP バージョン: " . phpversion() . "<br>\n";
echo "GD拡張: " . (extension_loaded('gd') ? '有効' : '無効') . "<br>\n";
echo "FreeType関数: " . (function_exists('imagettfbbox') ? '利用可能' : '利用不可') . "<br>\n";
echo "現在の作業ディレクトリ: " . getcwd() . "<br>\n";

// 追加のパステスト
echo "<h2>追加のパステスト</h2>\n";
$additional_paths = array(
    dirname(__DIR__) . '/assets/fonts/',
    dirname(__DIR__) . '/assets/',
    dirname(__DIR__) . '/',
    __DIR__ . '/../',
    __DIR__ . '/../../'
);

foreach ($additional_paths as $path) {
    echo "パス: " . $path . "<br>\n";
    if (is_dir($path)) {
        echo "  - ディレクトリ: はい<br>\n";
        if (is_readable($path)) {
            echo "  - 読み取り可能: はい<br>\n";
            $files = scandir($path);
            echo "  - ファイル数: " . count($files) . "<br>\n";
            if (in_array('fonts', $files)) {
                echo "  - fontsサブディレクトリ: 存在<br>\n";
            }
        } else {
            echo "  - 読み取り可能: いいえ<br>\n";
        }
    } else {
        echo "  - ディレクトリ: いいえ<br>\n";
    }
    echo "<br>\n";
}
