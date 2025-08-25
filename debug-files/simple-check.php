<?php
/**
 * 簡単な確認スクリプト（エラーハンドリング付き）
 */

// エラー表示を有効にする
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // WordPressを読み込み（プラグインディレクトリからWordPressルートへ）
    $wp_root = dirname(dirname(dirname(__DIR__)));
    $wp_config = $wp_root . '/wp-config.php';
    $wp_load = $wp_root . '/wp-load.php';
    
    echo "WordPressルートディレクトリ: " . $wp_root . "<br>";
    
    if (!file_exists($wp_config)) {
        echo "wp-config.php が見つかりません: " . $wp_config . "<br>";
        exit;
    }
    
    require_once($wp_config);
    
    if (!file_exists($wp_load)) {
        echo "wp-load.php が見つかりません: " . $wp_load . "<br>";
        exit;
    }
    
    require_once($wp_load);
    
    echo "<h1>WordPress読み込み成功</h1>";
    
    // 基本情報の確認
    echo "<h2>基本情報</h2>";
    echo "WordPress バージョン: " . get_bloginfo('version') . "<br>";
    echo "サイトURL: " . get_site_url() . "<br>";
    
    // 投稿344の確認
    echo "<h2>投稿ID 344の確認</h2>";
    $post = get_post(344);
    if ($post) {
        echo "✅ 投稿が存在します<br>";
        echo "タイトル: " . esc_html($post->post_title) . "<br>";
        echo "ステータス: " . $post->post_status . "<br>";
        
        $thumbnail_id = get_post_thumbnail_id(344);
        if ($thumbnail_id) {
            echo "✅ アイキャッチ設定済み: ID " . $thumbnail_id . "<br>";
        } else {
            echo "❌ アイキャッチ未設定<br>";
        }
    } else {
        echo "❌ 投稿ID 344が見つかりません<br>";
    }
    
    // ジャンル設定の確認
    echo "<h2>ジャンル設定の確認</h2>";
    $genre_settings = get_option('news_crawler_genre_settings', array());
    if (!empty($genre_settings)) {
        echo "✅ ジャンル設定が存在します<br>";
        foreach ($genre_settings as $id => $setting) {
            echo "ジャンル: " . esc_html($setting['genre_name']) . "<br>";
            if ($setting['genre_name'] === '政治・経済') {
                $auto_featured = isset($setting['auto_featured_image']) && $setting['auto_featured_image'];
                echo "- アイキャッチ自動生成: " . ($auto_featured ? '✅ 有効' : '❌ 無効') . "<br>";
                if (isset($setting['featured_image_method'])) {
                    echo "- 生成方法: " . esc_html($setting['featured_image_method']) . "<br>";
                }
            }
        }
    } else {
        echo "❌ ジャンル設定が見つかりません<br>";
    }
    
    // 一時保存の確認
    echo "<h2>一時保存の確認</h2>";
    $current_genre = get_transient('news_crawler_current_genre_setting');
    if ($current_genre) {
        echo "✅ 一時保存された設定が存在します<br>";
        echo "アイキャッチ自動生成: " . (isset($current_genre['auto_featured_image']) && $current_genre['auto_featured_image'] ? 'Yes' : 'No') . "<br>";
    } else {
        echo "❌ 一時保存された設定がありません<br>";
    }
    
    // クラスの存在確認
    echo "<h2>クラスの確認</h2>";
    if (class_exists('NewsCrawlerFeaturedImageGenerator')) {
        echo "✅ NewsCrawlerFeaturedImageGeneratorクラス: 存在<br>";
    } else {
        echo "❌ NewsCrawlerFeaturedImageGeneratorクラス: 見つかりません<br>";
    }
    
    // PHP環境の確認
    echo "<h2>PHP環境の確認</h2>";
    echo "GD拡張: " . (extension_loaded('gd') ? '✅ 有効' : '❌ 無効') . "<br>";
    echo "PHPバージョン: " . PHP_VERSION . "<br>";
    
    $upload_dir = wp_upload_dir();
    echo "アップロードディレクトリ: " . $upload_dir['path'] . "<br>";
    echo "書き込み権限: " . (is_writable($upload_dir['path']) ? '✅ OK' : '❌ NG') . "<br>";
    
} catch (Exception $e) {
    echo "<h1>エラーが発生しました</h1>";
    echo "エラーメッセージ: " . $e->getMessage() . "<br>";
    echo "ファイル: " . $e->getFile() . "<br>";
    echo "行: " . $e->getLine() . "<br>";
} catch (Error $e) {
    echo "<h1>致命的エラーが発生しました</h1>";
    echo "エラーメッセージ: " . $e->getMessage() . "<br>";
    echo "ファイル: " . $e->getFile() . "<br>";
    echo "行: " . $e->getLine() . "<br>";
}
?>