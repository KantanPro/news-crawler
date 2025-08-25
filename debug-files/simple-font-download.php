<?php
/**
 * シンプルな日本語フォントダウンロード
 */

// WordPressの読み込み
require_once('../../../wp-config.php');

echo "<h2>シンプルな日本語フォントダウンロード</h2>";

// フォントディレクトリの確認・作成
$font_dir = plugin_dir_path(__FILE__) . '../assets/fonts/';
if (!file_exists($font_dir)) {
    mkdir($font_dir, 0755, true);
    echo "<p>✓ フォントディレクトリを作成: {$font_dir}</p>";
}

$font_file = $font_dir . 'NotoSansJP-Regular.ttf';

// 直接的なフォントURL（GitHub経由）
$direct_font_url = 'https://github.com/googlefonts/noto-fonts/raw/main/hinted/ttf/NotoSansJP/NotoSansJP-Regular.ttf';

echo "<h3>フォントダウンロード実行</h3>";

if (file_exists($font_file)) {
    $file_size = filesize($font_file);
    $file_size_mb = round($file_size / 1024 / 1024, 2);
    echo "<p>✓ フォントファイルは既に存在します ({$file_size_mb} MB)</p>";
} else {
    echo "<p>ダウンロード中: " . htmlspecialchars($direct_font_url) . "</p>";
    
    // cURLを使用してダウンロード
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $direct_font_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; WordPress Font Downloader)');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $font_data = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            echo "<p>❌ cURLエラー: {$error}</p>";
        } elseif ($http_code === 200 && !empty($font_data) && strlen($font_data) > 100000) {
            if (file_put_contents($font_file, $font_data)) {
                $file_size = filesize($font_file);
                $file_size_mb = round($file_size / 1024 / 1024, 2);
                echo "<p>✓ ダウンロード成功: {$file_size_mb} MB</p>";
            } else {
                echo "<p>❌ ファイル保存に失敗</p>";
            }
        } else {
            echo "<p>❌ ダウンロード失敗 (HTTP: {$http_code}, Size: " . strlen($font_data) . " bytes)</p>";
        }
    } else {
        // wp_remote_getを使用
        $response = wp_remote_get($direct_font_url, array(
            'timeout' => 120,
            'user-agent' => 'Mozilla/5.0 (compatible; WordPress Font Downloader)'
        ));
        
        if (is_wp_error($response)) {
            echo "<p>❌ wp_remote_get エラー: " . $response->get_error_message() . "</p>";
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code === 200) {
                $font_data = wp_remote_retrieve_body($response);
                if (!empty($font_data) && strlen($font_data) > 100000) {
                    if (file_put_contents($font_file, $font_data)) {
                        $file_size = filesize($font_file);
                        $file_size_mb = round($file_size / 1024 / 1024, 2);
                        echo "<p>✓ ダウンロード成功: {$file_size_mb} MB</p>";
                    } else {
                        echo "<p>❌ ファイル保存に失敗</p>";
                    }
                } else {
                    echo "<p>❌ 無効なフォントデータ (Size: " . strlen($font_data) . " bytes)</p>";
                }
            } else {
                echo "<p>❌ HTTP エラー: {$response_code}</p>";
            }
        }
    }
}

// ダウンロード結果の確認
if (file_exists($font_file)) {
    echo "<h3>フォントファイル確認</h3>";
    $file_size = filesize($font_file);
    $file_size_mb = round($file_size / 1024 / 1024, 2);
    echo "<p>✓ ファイルパス: {$font_file}</p>";
    echo "<p>✓ ファイルサイズ: {$file_size_mb} MB</p>";
    
    // ファイルの先頭をチェック（TTFファイルかどうか）
    $file_header = file_get_contents($font_file, false, null, 0, 4);
    $is_ttf = (substr($file_header, 0, 4) === "\x00\x01\x00\x00" || substr($file_header, 0, 4) === "OTTO");
    
    if ($is_ttf) {
        echo "<p>✓ 有効なTTFフォントファイルです</p>";
    } else {
        echo "<p>❌ 無効なフォントファイルです</p>";
        echo "<p>ファイルヘッダー: " . bin2hex($file_header) . "</p>";
    }
    
    // 簡単なフォントテスト
    if (function_exists('imagettftext') && $is_ttf) {
        try {
            $test_image = imagecreatetruecolor(300, 100);
            $bg_color = imagecolorallocate($test_image, 255, 255, 255);
            $text_color = imagecolorallocate($test_image, 0, 0, 0);
            imagefill($test_image, 0, 0, $bg_color);
            
            $test_text = 'こんにちは';
            $result = imagettftext($test_image, 20, 0, 50, 50, $text_color, $font_file, $test_text);
            
            if ($result !== false) {
                echo "<p>✓ フォント描画テスト成功</p>";
                
                // テスト画像を保存
                $test_image_path = $font_dir . 'font_test_simple.png';
                if (imagepng($test_image, $test_image_path)) {
                    $test_image_url = plugin_dir_url(__FILE__) . '../assets/fonts/font_test_simple.png';
                    echo "<p>テスト画像: <a href='{$test_image_url}' target='_blank'>font_test_simple.png</a></p>";
                    echo "<img src='{$test_image_url}' style='border: 1px solid #ccc;' />";
                }
            } else {
                echo "<p>❌ フォント描画テスト失敗</p>";
            }
            
            imagedestroy($test_image);
        } catch (Exception $e) {
            echo "<p>❌ フォントテストエラー: " . $e->getMessage() . "</p>";
        }
    }
} else {
    echo "<h3>手動ダウンロード手順</h3>";
    echo "<p>自動ダウンロードに失敗しました。以下の手順で手動ダウンロードしてください：</p>";
    echo "<ol>";
    echo "<li><a href='https://fonts.google.com/noto/specimen/Noto+Sans+JP' target='_blank'>Google Fonts - Noto Sans JP</a> を開く</li>";
    echo "<li>「Download family」ボタンをクリック</li>";
    echo "<li>ダウンロードしたZIPファイルを解凍</li>";
    echo "<li>「NotoSansJP-Regular.ttf」を以下にコピー:</li>";
    echo "<li><code>{$font_file}</code></li>";
    echo "</ol>";
    
    echo "<h4>または直接ダウンロード</h4>";
    echo "<p><a href='{$direct_font_url}' download='NotoSansJP-Regular.ttf'>直接ダウンロード</a> (右クリック → 名前を付けて保存)</p>";
}

// 次のステップ
echo "<h3>次のステップ</h3>";
if (file_exists($font_file)) {
    echo "<p>✓ フォントの準備が完了しました</p>";
    echo "<p><a href='test-japanese-font-only.php' class='button'>日本語フォントテストを実行</a></p>";
} else {
    echo "<p>❌ フォントファイルが必要です</p>";
}

echo "<p><strong>処理完了</strong></p>";
?>