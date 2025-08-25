<?php
/**
 * 現在の生成方法を確認するスクリプト
 */

try {
    // WordPressを読み込み
    $wp_root = '/var/www/html';
    require_once($wp_root . '/wp-config.php');
    require_once($wp_root . '/wp-load.php');
    
    echo "<h1>アイキャッチ生成方法の確認</h1>";
    
    // ジャンル設定の確認
    echo "<h2>ジャンル設定</h2>";
    $genre_settings = get_option('news_crawler_genre_settings', array());
    
    foreach ($genre_settings as $id => $setting) {
        if ($setting['genre_name'] === '政治・経済') {
            echo "<strong>政治・経済ジャンル:</strong><br>";
            echo "- アイキャッチ自動生成: " . (isset($setting['auto_featured_image']) && $setting['auto_featured_image'] ? '✅ 有効' : '❌ 無効') . "<br>";
            
            if (isset($setting['featured_image_method'])) {
                $method = $setting['featured_image_method'];
                echo "- 生成方法: <strong>" . $method . "</strong><br>";
                
                if ($method === 'ai') {
                    echo "  → 🤖 <strong>AI生成（OpenAI DALL-E）を使用</strong><br>";
                    
                    // OpenAI APIキーの確認
                    $basic_settings = get_option('news_crawler_basic_settings', array());
                    $api_key = isset($basic_settings['openai_api_key']) ? $basic_settings['openai_api_key'] : '';
                    
                    if (!empty($api_key)) {
                        echo "  → ✅ OpenAI APIキー: 設定済み<br>";
                    } else {
                        echo "  → ❌ OpenAI APIキー: 未設定（AI生成は動作しません）<br>";
                    }
                    
                } elseif ($method === 'template') {
                    echo "  → 🎨 <strong>テンプレート生成を使用</strong><br>";
                    echo "  → プログラムで背景とテキストを描画<br>";
                    
                } elseif ($method === 'unsplash') {
                    echo "  → 📷 <strong>Unsplash画像取得を使用</strong><br>";
                    
                } else {
                    echo "  → ❓ 不明な生成方法<br>";
                }
            } else {
                echo "- 生成方法: 未設定<br>";
            }
            break;
        }
    }
    
    // 一時保存された設定の確認
    echo "<h2>一時保存された設定</h2>";
    $current_genre = get_transient('news_crawler_current_genre_setting');
    if ($current_genre) {
        echo "✅ 一時保存された設定が存在<br>";
        if (isset($current_genre['featured_image_method'])) {
            $temp_method = $current_genre['featured_image_method'];
            echo "- 一時保存の生成方法: <strong>" . $temp_method . "</strong><br>";
            
            if ($temp_method === 'ai') {
                echo "  → 🤖 AI生成が設定されています<br>";
            } elseif ($temp_method === 'template') {
                echo "  → 🎨 テンプレート生成が設定されています<br>";
            }
        }
    } else {
        echo "❌ 一時保存された設定がありません<br>";
    }
    
    echo "<h2>生成方法の説明</h2>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>方法</th><th>説明</th><th>必要な設定</th></tr>";
    echo "<tr>";
    echo "<td>🎨 template</td>";
    echo "<td>プログラムで背景色とテキストを描画してアイキャッチを作成</td>";
    echo "<td>なし（GD拡張のみ）</td>";
    echo "</tr>";
    echo "<tr>";
    echo "<td>🤖 ai</td>";
    echo "<td>OpenAI DALL-Eを使用してAI画像を生成</td>";
    echo "<td>OpenAI APIキーが必要</td>";
    echo "</tr>";
    echo "<tr>";
    echo "<td>📷 unsplash</td>";
    echo "<td>Unsplashから関連画像を取得</td>";
    echo "<td>Unsplash Access Keyが必要</td>";
    echo "</tr>";
    echo "</table>";
    
    echo "<h2>現在の状況</h2>";
    if (isset($method) && $method === 'ai') {
        if (empty($api_key)) {
            echo "<p>⚠️ <strong>AI生成が設定されていますが、OpenAI APIキーが未設定のため動作しません。</strong></p>";
            echo "<p>以下のいずれかを選択してください：</p>";
            echo "<ol>";
            echo "<li><strong>テンプレート生成に変更</strong>: WordPress管理画面でジャンル設定を編集し、生成方法を「テンプレート生成」に変更</li>";
            echo "<li><strong>OpenAI APIキーを設定</strong>: 基本設定でOpenAI APIキーを入力</li>";
            echo "</ol>";
        } else {
            echo "<p>✅ <strong>AI生成が正しく設定されています。</strong></p>";
        }
    } elseif (isset($method) && $method === 'template') {
        echo "<p>✅ <strong>テンプレート生成が設定されています。プログラムで画像を作成します。</strong></p>";
    }
    
} catch (Exception $e) {
    echo "<h1>エラー</h1>";
    echo "メッセージ: " . $e->getMessage() . "<br>";
}
?>