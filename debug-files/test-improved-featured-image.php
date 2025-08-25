<?php
/**
 * 改善されたアイキャッチ生成のテスト
 */

try {
    // WordPressを読み込み
    $wp_root = dirname(dirname(dirname(__DIR__)));
    require_once($wp_root . '/wp-config.php');
    require_once($wp_root . '/wp-load.php');
    
    echo "<h1>改善されたアイキャッチ生成テスト</h1>";
    
    // 投稿ID 348の情報
    $post = get_post(348);
    if (!$post) {
        echo "❌ 投稿ID 348が見つかりません<br>";
        exit;
    }
    
    echo "<h2>テスト対象投稿</h2>";
    echo "ID: 348<br>";
    echo "タイトル: " . esc_html($post->post_title) . "<br>";
    
    // 現在のアイキャッチを削除（テスト用）
    $current_thumbnail = get_post_thumbnail_id(348);
    if ($current_thumbnail) {
        delete_post_thumbnail(348);
        wp_delete_attachment($current_thumbnail, true);
        echo "✅ 既存のアイキャッチを削除しました<br>";
    }
    
    // 改善されたアイキャッチ生成をテスト
    if (class_exists('NewsCrawlerFeaturedImageGenerator')) {
        echo "<h2>改善されたアイキャッチ生成テスト</h2>";
        
        // テスト用設定
        $test_setting = array(
            'auto_featured_image' => 1,
            'featured_image_method' => 'template'
        );
        set_transient('news_crawler_current_genre_setting', $test_setting, 300);
        
        $generator = new NewsCrawlerFeaturedImageGenerator();
        
        echo "<strong>新しいアイキャッチを生成中...</strong><br>";
        $result = $generator->generate_and_set_featured_image(
            348, 
            $post->post_title, 
            array('政治', '経済', 'ニュース'), 
            'template'
        );
        
        if ($result) {
            echo "✅ <strong>改善されたアイキャッチ生成成功！</strong><br>";
            echo "添付ファイルID: " . $result . "<br>";
            $thumbnail_url = wp_get_attachment_url($result);
            echo "画像URL: " . $thumbnail_url . "<br>";
            echo "<h3>生成された画像:</h3>";
            echo "<img src='{$thumbnail_url}' style='max-width: 600px; border: 2px solid #333; margin: 10px 0;'><br>";
            
            // 投稿のアイキャッチ設定を確認
            $new_thumbnail_id = get_post_thumbnail_id(348);
            echo "投稿のアイキャッチID: " . ($new_thumbnail_id ? $new_thumbnail_id : 'なし') . "<br>";
            
            if ($new_thumbnail_id) {
                echo "✅ 投稿にアイキャッチが正常に設定されました<br>";
            } else {
                echo "❌ 投稿へのアイキャッチ設定に失敗しました<br>";
            }
        } else {
            echo "❌ <strong>アイキャッチ生成失敗</strong><br>";
        }
    } else {
        echo "❌ NewsCrawlerFeaturedImageGeneratorクラスが見つかりません<br>";
    }
    
    echo "<h2>改善点</h2>";
    echo "<ul>";
    echo "<li>✅ テキストを複数行に分割</li>";
    echo "<li>✅ 文字を太く見せるために重ね描画</li>";
    echo "<li>✅ より強い影効果</li>";
    echo "<li>✅ 適切な行間設定</li>";
    echo "<li>✅ タイトル文字数の調整</li>";
    echo "</ul>";
    
    echo "<h2>次のステップ</h2>";
    echo "<p>改善されたアイキャッチが正常に生成された場合:</p>";
    echo "<ol>";
    echo "<li>ジャンル設定で生成方法を「template」に設定</li>";
    echo "<li>新しい投稿を作成してテスト</li>";
    echo "<li>自動的にアイキャッチが生成されることを確認</li>";
    echo "</ol>";
    
} catch (Exception $e) {
    echo "<h1>エラー</h1>";
    echo "メッセージ: " . $e->getMessage() . "<br>";
}
?>