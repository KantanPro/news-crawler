<?php
/**
 * Unsplash設定を確認するスクリプト
 */

try {
    // WordPressを読み込み
    $wp_root = '/var/www/html';
    require_once($wp_root . '/wp-config.php');
    require_once($wp_root . '/wp-load.php');
    
    echo "<h1>Unsplash設定の確認</h1>";
    
    // 基本設定の確認
    echo "<h2>基本設定</h2>";
    $basic_settings = get_option('news_crawler_basic_settings', array());
    
    if (isset($basic_settings['unsplash_access_key'])) {
        $access_key = $basic_settings['unsplash_access_key'];
        if (!empty($access_key)) {
            echo "✅ Unsplash Access Key: 設定済み<br>";
            echo "- キーの長さ: " . strlen($access_key) . "文字<br>";
            echo "- キーの先頭: " . substr($access_key, 0, 8) . "...<br>";
        } else {
            echo "❌ Unsplash Access Key: 空の値<br>";
        }
    } else {
        echo "❌ Unsplash Access Key: 設定されていません<br>";
    }
    
    // フィーチャー画像設定の確認
    echo "<h2>フィーチャー画像設定</h2>";
    $featured_settings = get_option('news_crawler_featured_image_settings', array());
    
    if (isset($featured_settings['unsplash_access_key'])) {
        $featured_access_key = $featured_settings['unsplash_access_key'];
        if (!empty($featured_access_key)) {
            echo "✅ フィーチャー画像設定のUnsplash Access Key: 設定済み<br>";
            echo "- キーの長さ: " . strlen($featured_access_key) . "文字<br>";
            echo "- キーの先頭: " . substr($featured_access_key, 0, 8) . "...<br>";
        } else {
            echo "❌ フィーチャー画像設定のUnsplash Access Key: 空の値<br>";
        }
    } else {
        echo "❌ フィーチャー画像設定のUnsplash Access Key: 設定されていません<br>";
    }
    
    // ジャンル設定の確認
    echo "<h2>ジャンル設定</h2>";
    $genre_settings = get_option('news_crawler_genre_settings', array());
    
    foreach ($genre_settings as $id => $setting) {
        if ($setting['genre_name'] === '政治・経済') {
            echo "<strong>政治・経済ジャンル:</strong><br>";
            echo "- アイキャッチ自動生成: " . (isset($setting['auto_featured_image']) && $setting['auto_featured_image'] ? '✅ 有効' : '❌ 無効') . "<br>";
            echo "- 生成方法: " . (isset($setting['featured_image_method']) ? $setting['featured_image_method'] : '未設定') . "<br>";
            
            if (isset($setting['unsplash_access_key'])) {
                $genre_access_key = $setting['unsplash_access_key'];
                if (!empty($genre_access_key)) {
                    echo "- Unsplash Access Key: 設定済み<br>";
                } else {
                    echo "- Unsplash Access Key: 空の値<br>";
                }
            } else {
                echo "- Unsplash Access Key: 設定されていません<br>";
            }
            break;
        }
    }
    
    // 設定の優先順位
    echo "<h2>設定の優先順位</h2>";
    echo "1. ジャンル固有の設定（最優先）<br>";
    echo "2. フィーチャー画像設定<br>";
    echo "3. 基本設定（フォールバック）<br>";
    
    // 推奨設定
    echo "<h2>推奨設定</h2>";
    echo "Unsplash画像取得を動作させるには、以下のいずれかにAccess Keyを設定してください：<br>";
    echo "- 基本設定 → Unsplash Access Key<br>";
    echo "- フィーチャー画像設定 → Unsplash Access Key<br>";
    echo "- ジャンル設定 → Unsplash Access Key<br>";
    
    // 現在の設定状況の要約
    echo "<h2>現在の設定状況の要約</h2>";
    
    $has_access_key = false;
    $access_key_source = '';
    
    if (!empty($basic_settings['unsplash_access_key'])) {
        $has_access_key = true;
        $access_key_source = '基本設定';
    }
    
    if (!empty($featured_settings['unsplash_access_key'])) {
        $has_access_key = true;
        $access_key_source = 'フィーチャー画像設定';
    }
    
    foreach ($genre_settings as $setting) {
        if (!empty($setting['unsplash_access_key'])) {
            $has_access_key = true;
            $access_key_source = 'ジャンル設定（' . $setting['genre_name'] . '）';
            break;
        }
    }
    
    if ($has_access_key) {
        echo "✅ <strong>Unsplash Access Keyが設定されています</strong><br>";
        echo "- 設定場所: " . $access_key_source . "<br>";
        echo "- アイキャッチ自動生成は動作するはずです<br>";
    } else {
        echo "❌ <strong>Unsplash Access Keyが設定されていません</strong><br>";
        echo "- アイキャッチ自動生成は動作しません<br>";
        echo "- 設定画面でAccess Keyを入力してください<br>";
    }
    
} catch (Exception $e) {
    echo "エラーが発生しました: " . $e->getMessage();
}
?>
