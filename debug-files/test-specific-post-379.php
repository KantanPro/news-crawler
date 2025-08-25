<?php
/**
 * 投稿ID 379でアイキャッチ自動生成をテスト
 */

try {
    // WordPressを読み込み
    $wp_root = '/var/www/html';
    require_once($wp_root . '/wp-config.php');
    require_once($wp_root . '/wp-load.php');
    
    echo "<h1>投稿ID 379でのアイキャッチ自動生成テスト</h1>";
    
    // 投稿ID 379を取得
    $post = get_post(379);
    
    if (!$post) {
        echo "❌ 投稿ID 379が見つかりません<br>";
        return;
    }
    
    echo "<h2>投稿情報</h2>";
    echo "- 投稿ID: " . $post->ID . "<br>";
    echo "- タイトル: " . $post->post_title . "<br>";
    echo "- ステータス: " . $post->post_status . "<br>";
    echo "- 作成日: " . $post->post_date . "<br>";
    
    // 現在のアイキャッチ状況を確認
    $has_featured_image = has_post_thumbnail($post->ID);
    $featured_image_id = get_post_thumbnail_id($post->ID);
    
    echo "- アイキャッチ: " . ($has_featured_image ? '✅ 設定済み' : '❌ 未設定') . "<br>";
    if ($has_featured_image) {
        echo "- 画像ID: " . $featured_image_id . "<br>";
        $featured_image_url = get_the_post_thumbnail_url($post->ID, 'full');
        echo "- 画像URL: " . $featured_image_url . "<br>";
    }
    
    // キーワードを抽出（タイトルから）
    $keywords = array();
    $title_words = explode('、', $post->post_title);
    foreach ($title_words as $word) {
        $clean_word = trim($word, '：ニュースまとめ');
        if (!empty($clean_word) && mb_strlen($clean_word) > 1) {
            $keywords[] = $clean_word;
        }
    }
    
    if (empty($keywords)) {
        $keywords = array('政治', 'ニュース');
    }
    
    echo "<h2>抽出されたキーワード</h2>";
    echo implode(', ', $keywords) . "<br>";
    
    // フィーチャー画像生成クラスをインスタンス化
    if (class_exists('NewsCrawlerFeaturedImageGenerator')) {
        echo "<h2>アイキャッチ画像生成テスト</h2>";
        echo "生成方法: unsplash<br>";
        
        $generator = new NewsCrawlerFeaturedImageGenerator();
        
        // アイキャッチ画像を生成
        echo "<br>アイキャッチ画像を生成中...<br>";
        $result = $generator->generate_and_set_featured_image(
            $post->ID, 
            $post->post_title, 
            $keywords, 
            'unsplash'
        );
        
        if ($result) {
            echo "✅ <strong>アイキャッチ画像の生成に成功しました！</strong><br>";
            echo "- 画像ID: " . $result . "<br>";
            
            // 投稿にアイキャッチを設定
            $set_result = set_post_thumbnail($post->ID, $result);
            if ($set_result) {
                echo "✅ 投稿にアイキャッチ画像が設定されました<br>";
                
                // 確認
                $new_featured_image_url = get_the_post_thumbnail_url($post->ID, 'full');
                echo "- 新しいアイキャッチ画像URL: " . $new_featured_image_url . "<br>";
                
                // メディアライブラリの情報も確認
                $attachment = get_post($result);
                if ($attachment) {
                    echo "- 画像ファイル名: " . $attachment->post_title . "<br>";
                    echo "- 画像MIMEタイプ: " . $attachment->post_mime_type . "<br>";
                }
                
            } else {
                echo "❌ 投稿へのアイキャッチ画像の設定に失敗しました<br>";
            }
        } else {
            echo "❌ アイキャッチ画像の生成に失敗しました<br>";
            echo "エラーログを確認してください<br>";
        }
        
    } else {
        echo "❌ NewsCrawlerFeaturedImageGeneratorクラスが見つかりません<br>";
    }
    
    echo "<h2>テスト完了後の確認</h2>";
    // 再度アイキャッチ状況を確認
    $final_has_featured_image = has_post_thumbnail($post->ID);
    $final_featured_image_id = get_post_thumbnail_id($post->ID);
    
    echo "- 最終的なアイキャッチ状況: " . ($final_has_featured_image ? '✅ 設定済み' : '❌ 未設定') . "<br>";
    if ($final_has_featured_image) {
        echo "- 最終的な画像ID: " . $final_featured_image_id . "<br>";
        $final_featured_image_url = get_the_post_thumbnail_url($post->ID, 'full');
        echo "- 最終的な画像URL: " . $final_featured_image_url . "<br>";
    }
    
    echo "<h2>次のステップ</h2>";
    echo "1. WordPress管理画面で投稿ID 379のアイキャッチ画像を確認する<br>";
    echo "2. 実際の投稿作成時にアイキャッチが自動生成されるか確認する<br>";
    echo "3. 設定画面でアイキャッチ自動生成が有効になっているか確認する<br>";
    
} catch (Exception $e) {
    echo "エラーが発生しました: " . $e->getMessage();
    echo "<br>スタックトレース: <pre>" . $e->getTraceAsString() . "</pre>";
}
?>
