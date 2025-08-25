<?php
/**
 * WordPress環境でのフォントデバッグ
 * 実際のプラグイン環境での問題を特定
 */

// エラーレポートを有効化
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>WordPress環境でのフォントデバッグ</h1>\n";
echo "<pre>\n";

// WordPress環境の読み込みを試行
$wp_loaded = false;
try {
    if (file_exists('../../../wp-load.php')) {
        require_once('../../../wp-load.php');
        $wp_loaded = true;
        echo "✓ WordPress環境が読み込まれました\n";
    } else {
        echo "✗ wp-load.phpが見つかりません\n";
    }
} catch (Exception $e) {
    echo "✗ WordPress環境の読み込みエラー: " . $e->getMessage() . "\n";
}

if ($wp_loaded) {
    echo "\n=== WordPress環境の確認 ===\n";
    echo "ABSPATH: " . (defined('ABSPATH') ? ABSPATH : '未定義') . "\n";
    echo "WP_CONTENT_DIR: " . (defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : '未定義') . "\n";
    echo "WP_PLUGIN_DIR: " . (defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : '未定義') . "\n";
    
    // プラグインのパスを確認
    $plugin_dir = plugin_dir_path(dirname(__FILE__));
    echo "plugin_dir_path: " . $plugin_dir . "\n";
    
    // 現在のファイルのパスを確認
    $current_file = __FILE__;
    echo "現在のファイル: " . $current_file . "\n";
    
    // 相対パスでの解決を試行
    $relative_path = dirname(dirname(__FILE__));
    echo "相対パス解決: " . $relative_path . "\n";
    
    // 絶対パスでの解決を試行
    $absolute_path = realpath(dirname(dirname(__FILE__)));
    echo "絶対パス解決: " . $absolute_path . "\n";
    
    echo "\n=== フォントファイルの確認 ===\n";
    
    // 複数のパス解決方法を試行
    $font_paths = array(
        $plugin_dir . 'assets/fonts/NotoSansJP-Regular.ttf',
        dirname(dirname(__FILE__)) . '/assets/fonts/NotoSansJP-Regular.ttf',
        realpath(dirname(dirname(__FILE__))) . '/assets/fonts/NotoSansJP-Regular.ttf',
        '/Users/kantanpro/Desktop/KantanPro/wordpress/wp-content/plugins/news-crawler/assets/fonts/NotoSansJP-Regular.ttf'
    );
    
    foreach ($font_paths as $font_path) {
        echo "フォントパス: " . $font_path . "\n";
        echo "  存在: " . (file_exists($font_path) ? 'YES' : 'NO') . "\n";
        echo "  読み取り可能: " . (is_readable($font_path) ? 'YES' : 'NO') . "\n";
        if (file_exists($font_path)) {
            echo "  サイズ: " . filesize($font_path) . " bytes\n";
            echo "  パーミッション: " . substr(sprintf('%o', fileperms($font_path)), -4) . "\n";
        }
        echo "\n";
    }
    
    // システムフォントの確認
    echo "=== システムフォントの確認 ===\n";
    $system_fonts = array(
        '/System/Library/Fonts/STHeiti Medium.ttc',
        '/System/Library/Fonts/STHeiti Light.ttc',
        '/System/Library/Fonts/PingFang.ttc',
        '/System/Library/Fonts/Hiragino Sans GB.ttc'
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
    
    // アイキャッチジェネレータークラスのテスト
    echo "=== アイキャッチジェネレータークラスのテスト ===\n";
    
    try {
        // クラスファイルを読み込み
        $class_file = dirname(__DIR__) . '/includes/class-eyecatch-generator.php';
        echo "クラスファイル: " . $class_file . "\n";
        echo "  存在: " . (file_exists($class_file) ? 'YES' : 'NO') . "\n";
        
        if (file_exists($class_file)) {
            require_once($class_file);
            
            if (class_exists('News_Crawler_Eyecatch_Generator')) {
                echo "✓ News_Crawler_Eyecatch_Generatorクラスが読み込まれました\n";
                
                $generator = new News_Crawler_Eyecatch_Generator();
                
                // リフレクションを使用してプライベートメソッドにアクセス
                $reflection = new ReflectionClass($generator);
                $method = $reflection->getMethod('get_japanese_font_path');
                $method->setAccessible(true);
                
                echo "\nフォント検索メソッドの実行...\n";
                $font_path = $method->invoke($generator);
                
                if ($font_path) {
                    echo "✓ フォントが見つかりました: " . $font_path . "\n";
                    echo "  存在: " . (file_exists($font_path) ? 'YES' : 'NO') . "\n";
                    echo "  読み取り可能: " . (is_readable($font_path) ? 'YES' : 'NO') . "\n";
                    
                    // フォントのテスト
                    if (function_exists('imagettftext')) {
                        $test_bbox = imagettfbbox(20, 0, $font_path, 'テスト');
                        if ($test_bbox !== false) {
                            echo "✓ フォントテスト成功: 境界ボックス取得OK\n";
                        } else {
                            echo "✗ フォントテスト失敗: 境界ボックス取得エラー\n";
                        }
                    } else {
                        echo "✗ FreeType関数が利用できません\n";
                    }
                } else {
                    echo "✗ フォントが見つかりません\n";
                }
                
            } else {
                echo "✗ News_Crawler_Eyecatch_Generatorクラスが見つかりません\n";
            }
        } else {
            echo "✗ クラスファイルが見つかりません\n";
        }
        
    } catch (Exception $e) {
        echo "✗ クラステストエラー: " . $e->getMessage() . "\n";
    }
    
} else {
    echo "\n=== スタンドアロンモードでのテスト ===\n";
    
    // システムフォントの直接テスト
    $system_font = '/System/Library/Fonts/STHeiti Medium.ttc';
    if (file_exists($system_font) && is_readable($system_font)) {
        echo "✓ システムフォントが見つかりました: " . $system_font . "\n";
        
        if (function_exists('imagettftext')) {
            $test_bbox = imagettfbbox(20, 0, $system_font, 'テスト');
            if ($test_bbox !== false) {
                echo "✓ フォントテスト成功: 境界ボックス取得OK\n";
            } else {
                echo "✗ フォントテスト失敗: 境界ボックス取得エラー\n";
            }
        } else {
            echo "✗ FreeType関数が利用できません\n";
        }
    } else {
        echo "✗ システムフォントが見つかりません\n";
    }
}

echo "\n=== 環境情報 ===\n";
echo "PHP Version: " . phpversion() . "\n";
echo "OS: " . php_uname() . "\n";
echo "Current Working Directory: " . getcwd() . "\n";

echo "\n=== デバッグ完了 ===\n";
echo "</pre>\n";
echo "<p><a href='javascript:location.reload()'>再読み込み</a></p>\n";
?>
