<?php
/**
 * 実際の投稿でアイキャッチ自動生成をテスト
 */

try {
    // WordPressを読み込み
    $wp_root = '/var/www/html';
    require_once($wp_root . '/wp-config.php');
    require_once($wp_root . '/wp-load.php');
    
    echo "<h1>実際の投稿でのアイキャッチ自動生成テスト</h1>";
    
    // 最近の投稿を取得
    $recent_posts = get_posts(array(
        'numberposts' => 5,
        'post_status' => 'publish',
        'orderby' => 'date',
        'order' => 'DESC'
    ));
    
    if (empty($recent_posts)) {
        echo "❌ 投稿が見つかりません<br>";
        return;
    }
    
    echo "<h2>最近の投稿一覧</h2>";
    foreach ($recent_posts as $post) {
        $has_featured_image = has_post_thumbnail($post->ID);
        $featured_image_id = get_post_thumbnail_id($post->ID);
        $featured_image_url = $has_featured_image ? get_the_post_thumbnail_url($post->ID, 'full') : 'なし';
        
        echo "<strong>投稿ID: " . $post->ID . "</strong><br>";
        echo "- タイトル: " . $post->post_title . "<br>";
        echo "- アイキャッチ: " . ($has_featured_image ? '✅ 設定済み' : '❌ 未設定') . "<br>";
        if ($has_featured_image) {
            echo "- 画像ID: " . $featured_image_id . "<br>";
            echo "- 画像URL: " . $featured_image_url . "<br>";
        }
        echo "<br>";
    }
    
    // アイキャッチが設定されていない投稿を選択
    $post_without_featured = null;
    foreach ($recent_posts as $post) {
        if (!has_post_thumbnail($post->ID)) {
            $post_without_featured = $post;
            break;
        }
    }
    
    if (!$post_without_featured) {
        echo "<h2>テスト結果</h2>";
        echo "✅ すべての投稿にアイキャッチが設定されています<br>";
        echo "アイキャッチ自動生成は正常に動作しているようです<br>";
        return;
    }
    
    echo "<h2>アイキャッチ生成テスト</h2>";
    echo "投稿ID: " . $post_without_featured->ID . "<br>";
    echo "タイトル: " . $post_without_featured->post_title . "<br>";
    
    // フィーチャー画像生成クラスをインスタンス化
    if (class_exists('NewsCrawlerFeaturedImageGenerator')) {
        $generator = new NewsCrawlerFeaturedImageGenerator();
        
        // キーワードを抽出（タイトルから）
        $keywords = array();
        $title_words = explode(' ', $post_without_featured->post_title);
        foreach ($title_words as $word) {
            $clean_word = trim($word, '、。–-');
            if (!empty($clean_word) && mb_strlen($clean_word) > 1) {
                $keywords[] = $clean_word;
            }
        }
        
        if (empty($keywords)) {
            $keywords = array('ニュース', '記事');
        }
        
        echo "抽出されたキーワード: " . implode(', ', $keywords) . "<br>";
        echo "生成方法: unsplash<br>";
        
        // アイキャッチ画像を生成
        echo "<br>アイキャッチ画像を生成中...<br>";
        $result = $generator->generate_and_set_featured_image(
            $post_without_featured->ID, 
            $post_without_featured->post_title, 
            $keywords, 
            'unsplash'
        );
        
        if ($result) {
            echo "✅ <strong>アイキャッチ画像の生成に成功しました！</strong><br>";
            echo "- 画像ID: " . $result . "<br>";
            
            // 投稿にアイキャッチを設定
            $set_result = set_post_thumbnail($post_without_featured->ID, $result);
            if ($set_result) {
                echo "✅ 投稿にアイキャッチ画像が設定されました<br>";
                
                // 確認
                $new_featured_image_url = get_the_post_thumbnail_url($post_without_featured->ID, 'full');
                echo "- 新しいアイキャッチ画像URL: " . $new_featured_image_url . "<br>";
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
    
    echo "<h2>次のステップ</h2>";
    echo "1. 実際の投稿作成時にアイキャッチが自動生成されるか確認する<br>";
    echo "2. 設定画面でアイキャッチ自動生成が有効になっているか確認する<br>";
    echo "3. 生成方法が'unsplash'に設定されているか確認する<br>";
    
} catch (Exception $e) {
    echo "エラーが発生しました: " . $e->getMessage();
    echo "<br>スタックトレース: <pre>" . $e->getTraceAsString() . "</pre>";
}
?>
