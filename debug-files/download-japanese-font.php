<?php
/**
 * 日本語フォント自動ダウンロード
 */

// WordPressの読み込み
require_once('../../../wp-config.php');

echo "<h2>日本語フォント自動ダウンロード</h2>";

// フォントディレクトリの確認・作成
$font_dir = plugin_dir_path(__FILE__) . '../assets/fonts/';
if (!file_exists($font_dir)) {
    if (mkdir($font_dir, 0755, true)) {
        echo "<p>✓ フォントディレクトリを作成しました: {$font_dir}</p>";
    } else {
        echo "<p>❌ フォントディレクトリの作成に失敗しました</p>";
        exit;
    }
} else {
    echo "<p>✓ フォントディレクトリが存在します: {$font_dir}</p>";
}

// Noto Sans JPフォントのダウンロード（Google Fonts API経由）
$font_urls = array(
    'https://fonts.gstatic.com/s/notosansjp/v52/noto-sans-jp-v52-japanese-regular.ttf',
    'https://github.com/googlefonts/noto-cjk/releases/download/Sans2.004/04_NotoSansCJK-OTF.zip',
    'https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400&display=swap'
);
$font_file = $font_dir . 'NotoSansJP-Regular.ttf';

echo "<h3>Noto Sans JPフォントのダウンロード</h3>";

if (file_exists($font_file)) {
    echo "<p>✓ フォントファイルは既に存在します: {$font_file}</p>";
    $file_size = filesize($font_file);
    $file_size_mb = round($file_size / 1024 / 1024, 2);
    echo "<p>ファイルサイズ: {$file_size_mb} MB</p>";
} else {
    echo "<p>フォントファイルをダウンロード中...</p>";
    
    $download_success = false;
    
    // 複数のURLを試行
    foreach ($font_urls as $index => $font_url) {
        echo "<p>試行 " . ($index + 1) . ": " . htmlspecialchars($font_url) . "</p>";
        
        if (strpos($font_url, '.zip') !== false) {
            // ZIPファイルの場合は手動ダウンロードを案内
            echo "<p>⚠ ZIPファイルは手動ダウンロードが必要です</p>";
            continue;
        }
        
        if (strpos($font_url, 'css2') !== false) {
            // CSS APIの場合はスキップ
            echo "<p>⚠ CSS APIはスキップします</p>";
            continue;
        }
        
        // wp_remote_getを使用してダウンロード
        $response = wp_remote_get($font_url, array(
            'timeout' => 120,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ));
        
        if (is_wp_error($response)) {
            echo "<p>❌ ダウンロードエラー: " . $response->get_error_message() . "</p>";
            continue;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code === 200) {
            $font_data = wp_remote_retrieve_body($response);
            if (!empty($font_data) && strlen($font_data) > 1000) { // 最小サイズチェック
                if (file_put_contents($font_file, $font_data)) {
                    $file_size = filesize($font_file);
                    $file_size_mb = round($file_size / 1024 / 1024, 2);
                    echo "<p>✓ ダウンロード成功: {$file_size_mb} MB</p>";
                    $download_success = true;
                    break;
                } else {
                    echo "<p>❌ ファイル保存に失敗</p>";
                }
            } else {
                echo "<p>❌ 無効なフォントデータ</p>";
            }
        } else {
            echo "<p>❌ HTTP エラー: {$response_code}</p>";
        }
    }
    
    if (!$download_success) {
        echo "<h4>手動ダウンロード方法</h4>";
        echo "<p>自動ダウンロードに失敗しました。以下の手順で手動でダウンロードしてください：</p>";
        echo "<ol>";
        echo "<li><a href='https://fonts.google.com/noto/specimen/Noto+Sans+JP' target='_blank'>Google Fonts - Noto Sans JP</a> にアクセス</li>";
        echo "<li>右上の「Download family」ボタンをクリック</li>";
        echo "<li>ダウンロードしたZIPファイル「Noto_Sans_JP.zip」を解凍</li>";
        echo "<li>解凍したフォルダ内の「NotoSansJP-Regular.ttf」を以下のパスにコピー:</li>";
        echo "<li><code>{$font_file}</code></li>";
        echo "</ol>";
        
        echo "<h4>代替フォントダウンロード</h4>";
        echo "<p>または、以下の直接リンクからダウンロード:</p>";
        echo "<ul>";
        echo "<li><a href='https://github.com/googlefonts/noto-fonts/raw/main/hinted/ttf/NotoSansJP/NotoSansJP-Regular.ttf' target='_blank'>GitHub - NotoSansJP-Regular.ttf</a></li>";
        echo "</ul>";
    }
}

// フォントテスト
if (file_exists($font_file)) {
    echo "<h3>フォントテスト</h3>";
    
    if (function_exists('imagettftext')) {
        try {
            // 簡単なテスト画像を作成
            $test_image = imagecreatetruecolor(400, 200);
            $bg_color = imagecolorallocate($test_image, 255, 255, 255);
            $text_color = imagecolorallocate($test_image, 0, 0, 0);
            imagefill($test_image, 0, 0, $bg_color);
            
            // 日本語テキストを描画
            $test_text = 'テスト';
            $bbox = imagettfbbox(24, 0, $font_file, $test_text);
            
            if ($bbox !== false) {
                imagettftext($test_image, 24, 0, 50, 100, $text_color, $font_file, $test_text);
                
                // テスト画像を保存
                $test_image_path = $font_dir . 'font_test.png';
                if (imagepng($test_image, $test_image_path)) {
                    echo "<p>✓ フォントテスト成功</p>";
                    echo "<p>テスト画像: <a href='" . plugin_dir_url(__FILE__) . "../assets/fonts/font_test.png' target='_blank'>font_test.png</a></p>";
                } else {
                    echo "<p>❌ テスト画像の保存に失敗</p>";
                }
            } else {
                echo "<p>❌ フォントの境界ボックス取得に失敗</p>";
            }
            
            imagedestroy($test_image);
            
        } catch (Exception $e) {
            echo "<p>❌ フォントテストエラー: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p>❌ imagettftext関数が利用できません</p>";
    }
}

// 次のステップの案内
echo "<h3>次のステップ</h3>";
if (file_exists($font_file)) {
    echo "<p>✓ 日本語フォントの準備が完了しました</p>";
    echo "<p><a href='test-japanese-font-only.php'>日本語フォント専用テストを実行</a></p>";
} else {
    echo "<p>❌ 日本語フォントの準備が必要です</p>";
    echo "<p>上記の手動ダウンロード方法を参照してフォントをインストールしてください</p>";
}

echo "<p><strong>処理完了</strong></p>";
?>