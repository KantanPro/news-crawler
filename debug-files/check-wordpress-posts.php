<?php
/**
 * WordPressの投稿状況を確認
 */

try {
    // WordPressを読み込み
    $wp_root = '/var/www/html';
    require_once($wp_root . '/wp-config.php');
    require_once($wp_root . '/wp-load.php');
    
    echo "<h1>WordPress投稿状況の確認</h1>";
    
    // 投稿の総数を確認
    $post_count = wp_count_posts();
    echo "<h2>投稿統計</h2>";
    echo "- 公開済み: " . $post_count->publish . "<br>";
    echo "- 下書き: " . $post_count->draft . "<br>";
    echo "- 非公開: " . $post_count->private . "<br>";
    echo "- ゴミ箱: " . $post_count->trash . "<br>";
    
    // 最近の投稿を取得（ステータスを指定しない）
    $recent_posts = get_posts(array(
        'numberposts' => 10,
        'post_status' => array('publish', 'draft', 'private'),
        'orderby' => 'date',
        'order' => 'DESC'
    ));
    
    if (empty($recent_posts)) {
        echo "<h2>投稿一覧</h2>";
        echo "❌ 投稿が見つかりません<br>";
        echo "WordPressに投稿が作成されていない可能性があります<br>";
    } else {
        echo "<h2>最近の投稿一覧（最大10件）</h2>";
        foreach ($recent_posts as $post) {
            $has_featured_image = has_post_thumbnail($post->ID);
            $featured_image_id = get_post_thumbnail_id($post->ID);
            
            echo "<strong>投稿ID: " . $post->ID . "</strong><br>";
            echo "- タイトル: " . $post->post_title . "<br>";
            echo "- ステータス: " . $post->post_status . "<br>";
            echo "- 作成日: " . $post->post_date . "<br>";
            echo "- アイキャッチ: " . ($has_featured_image ? '✅ 設定済み' : '❌ 未設定') . "<br>";
            if ($has_featured_image) {
                echo "- 画像ID: " . $featured_image_id . "<br>";
            }
            echo "<br>";
        }
    }
    
    // メディアライブラリの状況も確認
    $media_count = wp_count_posts('attachment');
    echo "<h2>メディアライブラリ</h2>";
    echo "- 添付ファイル数: " . $media_count->inherit . "<br>";
    
    // データベース接続確認
    global $wpdb;
    if ($wpdb->db_connect()) {
        echo "✅ データベース接続: 正常<br>";
        
        // 投稿テーブルの行数を確認
        $posts_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}");
        echo "- 投稿テーブルの総行数: " . $posts_count . "<br>";
        
        // 最新の投稿IDを確認
        $latest_post_id = $wpdb->get_var("SELECT MAX(ID) FROM {$wpdb->posts} WHERE post_type = 'post'");
        echo "- 最新の投稿ID: " . ($latest_post_id ?: 'なし') . "<br>";
        
    } else {
        echo "❌ データベース接続: 失敗<br>";
    }
    
    echo "<h2>次のステップ</h2>";
    if (empty($recent_posts)) {
        echo "1. まずWordPressにテスト投稿を作成する<br>";
        echo "2. 投稿作成後にアイキャッチ自動生成をテストする<br>";
    } else {
        echo "1. 既存の投稿でアイキャッチ自動生成をテストする<br>";
        echo "2. 新しい投稿を作成してアイキャッチ自動生成をテストする<br>";
    }
    
} catch (Exception $e) {
    echo "エラーが発生しました: " . $e->getMessage();
    echo "<br>スタックトレース: <pre>" . $e->getTraceAsString() . "</pre>";
}
?>
