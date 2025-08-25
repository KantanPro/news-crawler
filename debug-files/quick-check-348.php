<?php
/**
 * 投稿ID 348の軽量確認スクリプト
 */

// メモリ制限を増やす
ini_set('memory_limit', '512M');

try {
    // WordPressを読み込み
    $wp_root = dirname(dirname(dirname(__DIR__)));
    require_once($wp_root . '/wp-config.php');
    require_once($wp_root . '/wp-load.php');
    
    echo "<h1>投稿ID 348の確認</h1>";
    
    // 投稿の基本情報
    $post = get_post(348);
    if ($post) {
        echo "<h2>投稿情報</h2>";
        echo "タイトル: " . esc_html($post->post_title) . "<br>";
        echo "ステータス: " . $post->post_status . "<br>";
        
        // アイキャッチの確認
        $thumbnail_id = get_post_thumbnail_id(348);
        if ($thumbnail_id) {
            echo "✅ アイキャッチ設定済み: ID " . $thumbnail_id . "<br>";
            $thumbnail_url = wp_get_attachment_url($thumbnail_id);
            echo "<img src='{$thumbnail_url}' style='max-width: 300px;'><br>";
        } else {
            echo "❌ アイキャッチ未設定<br>";
        }
    } else {
        echo "❌ 投稿ID 348が見つかりません<br>";
        exit;
    }
    
    // 一時保存設定の確認
    echo "<h2>一時保存設定</h2>";
    $current_genre = get_transient('news_crawler_current_genre_setting');
    if ($current_genre) {
        echo "✅ 一時保存された設定が存在<br>";
        echo "アイキャッチ自動生成: " . (isset($current_genre['auto_featured_image']) && $current_genre['auto_featured_image'] ? 'Yes' : 'No') . "<br>";
        if (isset($current_genre['featured_image_method'])) {
            echo "生成方法: " . $current_genre['featured_image_method'] . "<br>";
        }
    } else {
        echo "❌ 一時保存された設定がありません<br>";
    }
    
    // 手動アイキャッチ生成テスト
    echo "<h2>手動テスト</h2>";
    if (class_exists('NewsCrawlerFeaturedImageGenerator')) {
        echo "✅ アイキャッチ生成クラス: 存在<br>";
        
        // テスト用設定を保存
        $test_setting = array(
            'auto_featured_image' => 1,
            'featured_image_method' => 'template'
        );
        set_transient('news_crawler_current_genre_setting', $test_setting, 300);
        
        echo "<strong>テンプレート生成テスト実行中...</strong><br>";
        $generator = new NewsCrawlerFeaturedImageGenerator();
        $result = $generator->generate_and_set_featured_image(
            348, 
            $post->post_title, 
            array('政治', '経済'), 
            'template'
        );
        
        if ($result) {
            echo "✅ <strong>手動テスト成功！</strong><br>";
            echo "添付ファイルID: " . $result . "<br>";
            $thumbnail_url = wp_get_attachment_url($result);
            echo "画像URL: " . $thumbnail_url . "<br>";
            echo "<img src='{$thumbnail_url}' style='max-width: 400px; border: 1px solid #ccc;'><br>";
            
            // 投稿のアイキャッチを確認
            $new_thumbnail_id = get_post_thumbnail_id(348);
            echo "投稿のアイキャッチID: " . ($new_thumbnail_id ? $new_thumbnail_id : 'なし') . "<br>";
        } else {
            echo "❌ <strong>手動テスト失敗</strong><br>";
        }
    } else {
        echo "❌ アイキャッチ生成クラスが見つかりません<br>";
    }
    
    echo "<h2>結論</h2>";
    if ($result) {
        echo "<p>✅ <strong>アイキャッチ生成機能は正常に動作しています</strong></p>";
        echo "<p>問題は投稿作成時にアイキャッチ生成が呼び出されていないことです。</p>";
        echo "<p>次のステップ: ジャンル設定を確認し、再度投稿を作成してください。</p>";
    } else {
        echo "<p>❌ <strong>アイキャッチ生成に問題があります</strong></p>";
        echo "<p>GD拡張やファイル権限を確認してください。</p>";
    }
    
} catch (Exception $e) {
    echo "<h1>エラー</h1>";
    echo "メッセージ: " . $e->getMessage() . "<br>";
} catch (Error $e) {
    echo "<h1>致命的エラー</h1>";
    echo "メッセージ: " . $e->getMessage() . "<br>";
}
?>